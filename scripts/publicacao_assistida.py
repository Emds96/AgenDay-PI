#!/usr/bin/env python3
"""Assistente de publicacao AgenDay. O padrao e SOMENTE simulacao.

A execucao real exige autorizacao separada para um SHA, backup local valido e
aprovacao humana do environment GitHub. Nao modifica o workflow existente.
"""
import argparse
from datetime import datetime
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import time
import zipfile

REPO = "Emds96/AgenDay-PI"
BRANCH = "dev-feira-uninorte"
WORKFLOW = "agenday-publicar.yml"
SITE = "agenday-sistema.infinityfree.io"
SHA_PATTERN = re.compile(r"[a-f0-9]{40}\Z")
ACTIVE = {"queued", "in_progress", "waiting", "pending", "requested"}


class Bloqueio(RuntimeError):
    pass


def sha256(path):
    digest = hashlib.sha256()
    with path.open("rb") as file:
        for block in iter(lambda: file.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def validar_backup(caminho, horas=6):
    """Confere relatorio e arquivos reais; nunca cria/exporta backup."""
    report_path = Path(caminho).expanduser().resolve()
    if report_path.name != "relatorio.json" or not report_path.is_file():
        raise Bloqueio("Informe o relatorio.json de um backup concluido.")
    try:
        report = json.loads(report_path.read_text(encoding="utf-8"))
        instante = datetime.strptime(report["data"], "%Y-%m-%d_%H%M%S")
    except (ValueError, KeyError, TypeError, json.JSONDecodeError) as exc:
        raise Bloqueio("Relatorio de backup invalido.") from exc
    idade = (datetime.now() - instante).total_seconds()
    if idade < -300 or idade > horas * 3600:
        raise Bloqueio("Backup fora da janela de seis horas; gerar e validar um novo.")
    if (report.get("status") != "BACKUP OK" or
            report.get("restauracao_local") != "aprovada" or
            not isinstance(report.get("arquivos"), int) or report["arquivos"] < 1 or
            not isinstance(report.get("tabelas"), int) or report["tabelas"] < 1):
        raise Bloqueio("Backup sem prova de restauracao local e conteudo completo.")
    for nome, campo in (("banco-agenday.sql", "sha256_sql"),
                        ("backup-completo.zip", "sha256_zip")):
        arquivo = report_path.parent / nome
        esperado = report.get(campo)
        if (not arquivo.is_file() or not isinstance(esperado, str) or
                sha256(arquivo).lower() != esperado.lower()):
            raise Bloqueio("Arquivo ou hash invalido no backup: " + nome)
    try:
        with zipfile.ZipFile(report_path.parent / "backup-completo.zip") as pacote:
            nomes = pacote.namelist()
            if (pacote.testzip() is not None or "banco-agenday.sql" not in nomes or
                    not any(x.startswith("htdocs/") for x in nomes)):
                raise Bloqueio("ZIP do backup incompleto ou com CRC invalido.")
    except (OSError, zipfile.BadZipFile) as exc:
        raise Bloqueio("ZIP do backup invalido.") from exc
    return report["data"]


def gh(*args):
    exe = shutil.which("gh")
    if not exe and os.getenv("LOCALAPPDATA"):
        fallback = Path(os.environ["LOCALAPPDATA"]) / (
            "Microsoft/WinGet/Packages/GitHub.cli_Microsoft.Winget.Source_8wekyb3d8bbwe/bin/gh.exe")
        if fallback.is_file():
            exe = str(fallback)
    if not exe:
        raise Bloqueio("GitHub CLI (gh) indisponivel neste computador.")
    try:
        result = subprocess.run([exe, *args], capture_output=True, text=True,
                                encoding="utf-8", timeout=90, check=False)
    except (OSError, subprocess.TimeoutExpired) as exc:
        raise Bloqueio("Falha ao executar GitHub CLI.") from exc
    if result.returncode != 0:
        # Nunca ecoar stderr, que pode conter detalhes da sessao autenticada.
        raise Bloqueio("GitHub CLI falhou: " + " ".join(args[:2]))
    return result.stdout.strip()


def lista_runs(workflow=WORKFLOW):
    return json.loads(gh("run", "list", "--repo", REPO, "--workflow", workflow,
                         "--branch", BRANCH, "--limit", "30", "--json",
                         "databaseId,event,headSha,status,conclusion,createdAt"))


def verificar_precondicoes(sha, relatorio):
    data_backup = validar_backup(relatorio)
    gh("auth", "status")
    atual = gh("api", "repos/" + REPO + "/git/ref/heads/" + BRANCH,
               "--jq", ".object.sha")
    if atual != sha:
        raise Bloqueio("HEAD da branch diferente do SHA autorizado.")
    variaveis = json.loads(gh("variable", "list", "--repo", REPO,
                              "--json", "name,value"))
    flags = {x["name"]: x["value"] for x in variaveis}
    if (flags.get("AGENDAY_DEPLOY_ENABLED") != "false" or
            flags.get("AGENDAY_DEPLOY_MODE") != "disabled"):
        raise Bloqueio("Flags fora do estado seguro false/disabled; conferir manualmente.")
    runs = lista_runs()
    if any(r["status"] in ACTIVE for r in runs):
        raise Bloqueio("Ha execucao pendente/ativa do publicador. Coordenar sessoes.")
    if not any(r["headSha"] == sha and r["event"] == "push" and
               r["status"] == "completed" and r["conclusion"] == "success"
               for r in runs):
        raise Bloqueio("Nao existe validacao de push concluida com sucesso para este SHA.")
    lint = lista_runs("php-lint.yml")
    if not any(r["headSha"] == sha and r["status"] == "completed" and
               r["conclusion"] == "success" for r in lint):
        raise Bloqueio("PHP lint nao comprovado para o SHA aprovado.")
    return data_backup, {r["databaseId"] for r in runs}


def aguardar_run(run_id, limite_segundos=3600):
    fim = time.monotonic() + limite_segundos
    while time.monotonic() < fim:
        run = json.loads(gh("run", "view", str(run_id), "--repo", REPO,
                          "--json", "status,conclusion,headSha,jobs"))
        if run["status"] == "completed":
            return run
        time.sleep(15)
    raise Bloqueio("Tempo de acompanhamento excedido; consultar run e flags manualmente.")


def publicar(sha, relatorio, confirmacao):
    if confirmacao != "PUBLICAR:" + sha:
        raise Bloqueio("Confirmacao literal do SHA ausente ou diferente.")
    data_backup, anteriores = verificar_precondicoes(sha, relatorio)
    print("Pre-condicoes OK. Backup:", data_backup)
    print("Congele merges e pushes na dev durante esta operacao.")
    alterou_flags = False
    run_id = None
    disparado = False
    terminal = False
    try:
        # Outra checagem imediatamente antes de ligar os gates.
        atual = gh("api", "repos/" + REPO + "/git/ref/heads/" + BRANCH,
                   "--jq", ".object.sha")
        if atual != sha:
            raise Bloqueio("HEAD alterado entre preflight e publicacao.")
        gh("variable", "set", "AGENDAY_DEPLOY_ENABLED", "--repo", REPO,
           "--body", "true")
        alterou_flags = True
        gh("variable", "set", "AGENDAY_DEPLOY_MODE", "--repo", REPO,
           "--body", "publish")
        gh("workflow", "run", WORKFLOW, "--repo", REPO, "--ref", BRANCH)
        disparado = True
        for _ in range(12):
            candidatos = [r for r in lista_runs() if
                          r["databaseId"] not in anteriores and
                          r["event"] == "workflow_dispatch" and r["headSha"] == sha]
            if len(candidatos) == 1:
                run_id = candidatos[0]["databaseId"]
                break
            if len(candidatos) > 1:
                raise Bloqueio("Mais de um disparo novo; identificar run manualmente.")
            time.sleep(5)
        if not run_id:
            raise Bloqueio("Run nao identificado: conferir Actions antes de continuar.")
        print("Run:", "https://github.com/" + REPO + "/actions/runs/" + str(run_id))
        print("Aprovacao do environment 'producao-agenday' deve ser feita por revisor autorizado no GitHub.")
        run = aguardar_run(run_id)
        terminal = True
        if run["headSha"] != sha:
            raise Bloqueio("Run terminou com SHA inesperado.")
        jobs = {j["name"]: j["conclusion"] for j in run["jobs"]}
        if (run["conclusion"] != "success" or
                jobs.get("Validar codigo e pacote") != "success" or
                jobs.get("Enviar ao InfinityFree somente com autorizacao") != "success"):
            raise Bloqueio("Workflow nao confirmou publicacao com sucesso.")
        print("Publicacao confirmada pelo GitHub Actions. Ainda exige QA no site.")
    finally:
        if alterou_flags:
            if disparado and not terminal:
                print("ATENCAO: run pode estar ativo; nao desligar flags durante upload.")
                print("Monitorar/encerrar o run e restaurar as flags pelo manual imediatamente.")
            else:
                # Tentar restaurar ambos os gates mesmo se uma operacao falhar.
                erros = []
                for nome, valor in (("AGENDAY_DEPLOY_MODE", "disabled"),
                                    ("AGENDAY_DEPLOY_ENABLED", "false")):
                    try:
                        gh("variable", "set", nome, "--repo", REPO, "--body", valor)
                    except Bloqueio as exc:
                        erros.append(str(exc))
                if erros:
                    raise Bloqueio("FALHA CRITICA na restauracao das flags: " + "; ".join(erros))
                flags_finais = {x["name"]: x["value"] for x in json.loads(
                    gh("variable", "list", "--repo", REPO, "--json", "name,value"))}
                if (flags_finais.get("AGENDAY_DEPLOY_ENABLED") != "false" or
                        flags_finais.get("AGENDAY_DEPLOY_MODE") != "disabled"):
                    raise Bloqueio("Flags nao confirmadas em false/disabled.")
                print("Flags confirmadas em false/disabled.")


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--sha", required=True, help="SHA completo autorizado da dev")
    parser.add_argument("--relatorio-backup", help="Caminho privado do relatorio.json")
    parser.add_argument("--executar", action="store_true", help="Publicacao REAL, requer nova autorizacao")
    parser.add_argument("--confirmar", default="", help="PUBLICAR:<SHA completo> para execucao real")
    args = parser.parse_args(argv)
    if not SHA_PATTERN.fullmatch(args.sha):
        parser.error("--sha deve conter exatamente 40 caracteres hexadecimais minusculos")
    if not args.executar:
        print("SIMULACAO, sem comandos de escrita ou chamadas ao GitHub.")
        print("Repositorio:", REPO, "| Branch:", BRANCH, "| Destino:", SITE)
        print("SHA previsto:", args.sha)
        print("Ordem: backup recente -> checagem SHA/CI/gates -> flags -> Actions")
        print("-> aprovacao do environment -> verificar jobs -> restaurar flags -> QA mobile.")
        return 0
    if not args.relatorio_backup:
        parser.error("--executar requer --relatorio-backup")
    try:
        publicar(args.sha, args.relatorio_backup, args.confirmar)
        return 0
    except Bloqueio as exc:
        print("BLOQUEADO:", exc)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
