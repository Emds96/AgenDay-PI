#!/usr/bin/env python3
"""Backup AgenDay: PHP temporario, FTPS e verificacao local obrigatoria."""
from __future__ import annotations
import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import shutil
import ssl
import subprocess
import sys
import time
import webbrowser
import zipfile
from datetime import datetime
from ftplib import FTP_TLS, error_perm
from io import BytesIO
from urllib.parse import quote

REPO = Path(__file__).resolve().parent.parent
DEFAULT_ROOT = Path.home() / "Documents" / "AgenDay-Backups"
SITE = "https://agenday-sistema.infinityfree.io"
DDL = re.compile(r"(?im)^\s*CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?\x60?([^\x60\s(]+)\x60?")
TABLE = re.compile(r"^-- AGENDAY_TABLE ([A-Za-z0-9+/=]+) (\d+)$")
END = re.compile(r"^-- AGENDAY_BACKUP_SUCCESS tables=(\d+)$")


class BackupError(Exception):
    pass


def settings(path):
    data = {}
    if path.exists():
        for line in path.read_text(encoding="utf-8-sig").splitlines():
            if line.strip() and not line.lstrip().startswith("#"):
                if "=" not in line:
                    raise BackupError("Configuracao invalida no arquivo privado")
                k, v = line.split("=", 1)
                data[k.strip()] = v.strip().strip('"')
    return data


def ftp_login(conf):
    values = [os.getenv(k) or conf.get(k) for k in
              ("AGENDAY_FTP_HOST", "AGENDAY_FTP_USERNAME", "AGENDAY_FTP_PASSWORD")]
    if not all(values):
        raise BackupError("FTP_CONFIG: dados FTPS nao configurados no PC")
    host, user, password = values
    if not re.fullmatch(r"[A-Za-z0-9.-]+", host):
        raise BackupError("FTP_CONFIG: host invalido")
    ftp = FTP_TLS(context=ssl.create_default_context(), timeout=35)
    ftp.connect(host, 21)
    ftp.login(user, password)
    ftp.prot_p()
    ftp.set_pasv(True)
    ftp.cwd("htdocs")
    return ftp


def entries(ftp):
    try:
        out = []
        for name, facts in ftp.mlsd():
            if name in (".", ".."):
                continue
            if "/" in name or "\\" in name:
                raise BackupError("FTP_LIST: nome inseguro")
            kind = facts.get("type")
            if kind not in ("file", "dir"):
                raise BackupError("FTP_LIST: tipo desconhecido")
            out.append((name, kind, int(facts["size"]) if kind == "file" and facts.get("size") else None))
        return sorted(out)
    except error_perm:
        pass
    root = ftp.pwd()
    out = []
    try:
        # Fallback conservador: inclui ocultos onde o servidor aceitar NLST -a.
        try:
            listing = ftp.nlst("-a")
        except error_perm:
            listing = ftp.nlst()
        for raw in listing:
            name = raw.rstrip("/").rsplit("/", 1)[-1]
            if name in ("", ".", ".."):
                continue
            if "/" in name or "\\" in name:
                raise BackupError("FTP_LIST: caminho inseguro")
            try:
                ftp.cwd(name)
                ftp.cwd(root)
                out.append((name, "dir", None))
            except error_perm:
                ftp.voidcmd("TYPE I")
                size = ftp.size(name)
                if size is None:
                    raise BackupError("FTP_LIST: tamanho desconhecido")
                out.append((name, "file", size))
        return sorted(set(out))
    finally:
        ftp.cwd(root)


def upload(ftp, name, content):
    ftp.storbinary("STOR " + name, BytesIO(content))


def download(ftp, name, destination, expected=None):
    destination.parent.mkdir(parents=True, exist_ok=True)
    part = destination.with_name(destination.name + ".partial")
    for attempt in range(3):
        try:
            with part.open("wb") as stream:
                ftp.retrbinary("RETR " + name, stream.write)
            if expected is None:
                ftp.voidcmd("TYPE I")
                expected = ftp.size(name)
            if expected is not None and part.stat().st_size != expected:
                raise BackupError("FTP_TRANSFER: arquivo incompleto")
            part.replace(destination)
            return
        except Exception:
            part.unlink(missing_ok=True)
            if attempt == 2:
                raise
            time.sleep(attempt + 1)


def mirror(ftp, dest, is_root=True):
    dest.mkdir(parents=True, exist_ok=True)
    root = ftp.pwd()
    result = [0, 0]
    for name, kind, size in entries(ftp):
        if is_root and (name.startswith("_agenday_export_") or name.startswith(".agenday-backup-")):
            continue
        if kind == "dir":
            ftp.cwd(name)
            child = mirror(ftp, dest / name, False)
            ftp.cwd(root)
            result[0] += child[0]
            result[1] += child[1]
        else:
            download(ftp, name, dest / name, size)
            result[0] += 1
            result[1] += (dest / name).stat().st_size
    ftp.cwd(root)
    return result


def validate_sql(path, manual=False):
    payload = path.read_bytes()
    if not payload or len(payload) > 100 * 1024 * 1024:
        raise BackupError("SQL_INVALIDO: tamanho inesperado")
    try:
        text = payload.decode("utf-8")
    except UnicodeDecodeError as e:
        raise BackupError("SQL_INVALIDO: charset invalido") from e
    names = DDL.findall(text)
    if not names or len(set(names)) != len(names):
        raise BackupError("SQL_INVALIDO: falta estrutura de tabela")
    if manual:
        return {t: -1 for t in names}
    lines = text.splitlines()
    last = END.fullmatch(lines[-1]) if lines else None
    if lines[0] != "-- AGENDAY_BACKUP_BEGIN" or not last or not payload.endswith(b"\n"):
        raise BackupError("SQL_INVALIDO: dump truncado ou sem marcador")
    counts = {}
    for line in lines:
        m = TABLE.fullmatch(line)
        if m:
            name = base64.b64decode(m[1], validate=True).decode("utf-8")
            if name in counts:
                raise BackupError("SQL_INVALIDO: tabela repetida")
            counts[name] = int(m[2])
    if set(names) != set(counts) or int(last[1]) != len(counts):
        raise BackupError("SQL_INVALIDO: divergencia de tabelas")
    return counts


def mysql_run(binary, cnf, db=None, query=None, file=None):
    if not binary.is_file() or not cnf.is_file():
        raise BackupError("RESTORE_CONFIG: MySQL local e .mysql-local.cnf obrigatorios")
    cmd = [str(binary), "--defaults-extra-file=" + str(cnf), "--host=127.0.0.1",
           "--port=3306", "--batch", "--skip-column-names", "--default-character-set=utf8mb4"]
    if db:
        cmd.append("--database=" + db)
    if query:
        cmd += ["--execute", query]
    with (file.open("rb") if file else open(os.devnull, "rb")) as stream:
        res = subprocess.run(cmd, stdin=stream, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=90)
    if res.returncode:
        raise BackupError("RESTORE_FALHOU: MySQL rejeitou importacao/consulta")
    return res.stdout.decode("utf-8", errors="replace").strip()


def restore(path, expected, mysql, cnf):
    db = "agenday_backup_verify_" + secrets.token_hex(6)
    created = False
    try:
        mysql_run(mysql, cnf, query="CREATE DATABASE " + db + " CHARACTER SET utf8mb4")
        created = True
        mysql_run(mysql, cnf, db=db, file=path)
        tables = mysql_run(mysql, cnf, db=db, query="SHOW FULL TABLES")
        got = {line.split("\t")[0] for line in tables.splitlines() if line}
        if got != set(expected):
            raise BackupError("RESTORE_FALHOU: estrutura divergente")
        for name, count in expected.items():
            if count < 0:
                continue
            safe = chr(96) + name.replace(chr(96), chr(96) * 2) + chr(96)
            found = mysql_run(mysql, cnf, db=db, query="SELECT COUNT(*) FROM " + safe)
            if int(found) != count:
                raise BackupError("RESTORE_FALHOU: contagem divergente")
    finally:
        if created:
            mysql_run(mysql, cnf, query="DROP DATABASE IF EXISTS " + db)


def remove_remote(ftp, root, folder, php_name):
    errors = []
    try:
        ftp.cwd(root)
        ftp.delete(php_name)
    except error_perm as e:
        if not str(e).startswith('550'):
            errors.append(php_name)
    except Exception:
        errors.append(php_name)
    try:
        ftp.cwd(folder)
        # Remover nomes conhecidos inclusive arquivos ocultos omitidos por NLST.
        for name in (".htaccess", "nonce.txt", "nonce.txt.used", "database.sql", "database.partial"):
            try:
                ftp.delete(name)
            except error_perm as e:
                if not str(e).startswith("550"):
                    errors.append(folder + "/" + name)
        ftp.cwd(root)
        ftp.rmd(folder)
    except Exception:
        errors.append(folder)
    try:
        ftp.cwd(root)
    except Exception:
        pass
    return errors


def sha256(file):
    digest = hashlib.sha256()
    with file.open("rb") as stream:
        for chunk in iter(lambda: stream.read(65536), b""):
            digest.update(chunk)
    return digest.hexdigest()


def recover_stale(ftp, remote_root, local_root):
    """Limpa somente artefatos deixados por uma execucao NOSSA anterior."""
    pending = local_root / ".backup_pending.json"
    if not pending.is_file():
        return
    try:
        state = json.loads(pending.read_text(encoding="utf-8"))
        ident = state["id"]
        created = int(state["created"])
        if not re.fullmatch(r"[a-f0-9]{32}", ident):
            raise ValueError("identificador invalido")
    except (OSError, ValueError, KeyError, TypeError) as e:
        raise BackupError("LIMPEZA_PENDENTE: manifesto local invalido") from e
    if time.time() - created < 600:
        raise BackupError("LIMPEZA_PENDENTE: backup anterior recente; evitar concorrencia")
    folder = ".agenday-backup-" + ident
    php_name = "_agenday_export_" + ident + ".php"
    problems = remove_remote(ftp, remote_root, folder, php_name)
    if problems:
        raise BackupError("LIMPEZA_PENDENTE: verificar arquivos remotos antes de continuar")
    pending.unlink()
    print("Limpeza de execucao anterior concluida.")


def backup(args):
    root = args.backup_root.expanduser().resolve()
    conf = settings(args.env_file.expanduser().resolve())
    if not all(os.getenv(k) or conf.get(k) for k in
               ("AGENDAY_FTP_HOST", "AGENDAY_FTP_USERNAME", "AGENDAY_FTP_PASSWORD")):
        raise BackupError("FTP_CONFIG: informe credenciais no PC antes de executar")
    mysql = args.mysql_cli.expanduser().resolve()
    cnf = args.mysql_defaults_file.expanduser().resolve()
    if not mysql.is_file() or not cnf.is_file():
        raise BackupError("RESTORE_CONFIG: configurar MySQL local e arquivo .mysql-local.cnf")
    if not args.manual_sql and not args.guard_verified:
        raise BackupError("SEGURANCA: antes do uso real, verificar protecao HTTP e informar --guard-verified")
    ident = secrets.token_hex(16)
    folder = ".agenday-backup-" + ident
    php_name = "_agenday_export_" + ident + ".php"
    stamp = datetime.now().strftime("%Y-%m-%d_%H%M%S")
    root.mkdir(parents=True, exist_ok=True)
    work = root / ("_incompleto_" + stamp + "_" + ident[:8])
    work.mkdir()
    trigger = work / "iniciar-exportacao.html"
    ftp = None
    uploaded = False
    remote_root = ""
    cleanup_errors = []
    sql = work / "banco-agenday.sql"
    try:
        ftp = ftp_login(conf)
        remote_root = ftp.pwd()
        recover_stale(ftp, remote_root, root)
        if args.manual_sql:
            shutil.copy2(args.manual_sql.expanduser().resolve(), sql)
        else:
            ftp.mkd(folder)
            uploaded = True
            (root / ".backup_pending.json").write_text(
                json.dumps({"id": ident, "created": int(time.time())}), encoding="utf-8")
            ftp.cwd(folder)
            upload(ftp, ".htaccess", b"Require all denied\n")
            nonce = secrets.token_hex(32)
            upload(ftp, "nonce.txt", (nonce + "\n").encode())
            ftp.cwd(remote_root)
            template = (REPO / "scripts" / "backup_php_template.txt").read_text(encoding="utf-8")
            generated = template.replace("__ID__", ident).replace("__EXPIRES__", str(int(time.time()) + 300))
            if "__ID__" in generated or "__EXPIRES__" in generated:
                raise BackupError("PHP_TEMPLATE: falha ao compilar template")
            upload(ftp, php_name, generated.encode("utf-8"))
            url = SITE + "/" + quote(php_name)
            # A pagina PHP fornece formulario GET -> POST no mesmo dominio,
            # preservando cookies SameSite apos o desafio normal do navegador.
            if not webbrowser.open(url):
                raise BackupError("NAVEGADOR: nao foi possivel abrir a pagina temporaria")
            print("Navegador acionado. Verificando exportacao SQL...")
            deadline = time.monotonic() + 110
            while time.monotonic() < deadline:
                ftp.cwd(folder)
                try:
                    ftp.voidcmd("TYPE I")
                    size = ftp.size("database.sql")
                except error_perm:
                    size = None
                ftp.cwd(remote_root)
                if size is not None:
                    if size <= 0 or size >= 8 * 1024 * 1024:
                        raise BackupError("SQL_REMOTO: tamanho inesperado")
                    ftp.cwd(folder)
                    download(ftp, "database.sql", sql, size)
                    ftp.cwd(remote_root)
                    break
                time.sleep(3)
            if not sql.exists():
                raise BackupError("PHP_TIMEOUT: SQL nao gerado. Conferir aba do navegador.")
        expected = validate_sql(sql, bool(args.manual_sql))
        if uploaded:
            cleanup_errors = remove_remote(ftp, remote_root, folder, php_name)
            uploaded = False
            if not cleanup_errors:
                (root / ".backup_pending.json").unlink(missing_ok=True)
            if cleanup_errors:
                raise BackupError("LIMPEZA_FTP: temporarios remotos pendentes")
        ftp.cwd(remote_root)
        stats = mirror(ftp, work / "htdocs")
        if not (work / "htdocs" / "index.php").is_file() or not (work / "htdocs" / "conexao.php").is_file():
            raise BackupError("FTP_MIRROR: index.php ou conexao.php ausentes")
        ftp.quit()
        ftp = None
        print("Arquivos transferidos. Importando SQL em MySQL local temporario...")
        restore(sql, expected, mysql, cnf)
        archive = work / "backup-completo.zip"
        with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED) as z:
            z.write(sql, "banco-agenday.sql")
            for file in (work / "htdocs").rglob("*"):
                if file.is_file():
                    z.write(file, Path("htdocs") / file.relative_to(work / "htdocs"))
        with zipfile.ZipFile(archive) as z:
            if z.testzip():
                raise BackupError("ZIP_FALHOU: erro de integridade")
        trigger.unlink(missing_ok=True)
        report = {"status": "BACKUP OK", "data": stamp,
                  "modo": "manual" if args.manual_sql else "php-efemero",
                  "arquivos": stats[0], "bytes": stats[1], "tabelas": len(expected),
                  "restauracao_local": "aprovada", "sha256_sql": sha256(sql),
                  "sha256_zip": sha256(archive)}
        (work / "relatorio.json").write_text(json.dumps(report, indent=2), encoding="utf-8")
        dest = root / (stamp + "_" + ident[:8])
        work.rename(dest)
        print("BACKUP OK:", dest)
        return 0
    finally:
        trigger.unlink(missing_ok=True)
        if ftp:
            if uploaded:
                cleanup_errors += remove_remote(ftp, remote_root, folder, php_name)
                if not cleanup_errors:
                    (root / ".backup_pending.json").unlink(missing_ok=True)
            try:
                ftp.quit()
            except Exception:
                ftp.close()
        if cleanup_errors:
            print("ATENCAO: verificar/remover temporarios:", ", ".join(cleanup_errors), file=sys.stderr)
        if work.exists():
            print("BACKUP NAO APROVADO. Evidencias privadas em:", work, file=sys.stderr)


def main():
    p = argparse.ArgumentParser(description="AgenDay Backup: PHP efemero + FTPS")
    p.add_argument("--env-file", type=Path, default=DEFAULT_ROOT / ".env.backup")
    p.add_argument("--backup-root", type=Path, default=DEFAULT_ROOT)
    p.add_argument("--mysql-cli", type=Path, default=Path(shutil.which("mysql") or "mysql.exe"))
    p.add_argument("--mysql-defaults-file", type=Path, default=DEFAULT_ROOT / ".mysql-local.cnf")
    p.add_argument("--manual-sql", type=Path)
    p.add_argument("--guard-verified", action="store_true",
                   help="Somente apos testar bloqueio HTTP .htaccess na hospedagem")
    p.add_argument("--check-ftps", action="store_true",
                   help="Testar conexao e listagem FTPS sem baixar nem alterar arquivos")
    args = p.parse_args()
    try:
        if args.check_ftps:
            ftp = ftp_login(settings(args.env_file.expanduser().resolve()))
            try:
                listing = entries(ftp)
                print("FTPS OK: htdocs acessivel; entradas na raiz:", len(listing))
            finally:
                ftp.quit()
            return 0
        return backup(args)
    except Exception as e:
        print("BACKUP FALHOU:", type(e).__name__, str(e), file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
