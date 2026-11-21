#!/usr/bin/env python3
"""Publica via FTPS explícito com TLS verificado; NUNCA apaga arquivos do servidor."""
from ftplib import FTP_TLS, error_perm
from io import BytesIO
import re
from pathlib import Path
import os
import ssl
import sys

ROOT = Path(__file__).resolve().parents[1]
PACKAGE = ROOT / "deploy-package"
PERMITTED = {
    "api": {".php"}, "lib": {".php"}, "css": {".css"}, "js": {".js"},
    "img": {".png", ".jpg", ".jpeg", ".webp", ".gif", ".jfif", ".svg", ".ico"},
    "icon": {".png", ".jpg", ".jpeg", ".webp", ".gif", ".jfif", ".svg", ".ico"},
}
NEVER = {"conexao.php", "conexao.exemplo.php", "brevo-api-config.php"}

def allowed(rel: Path) -> bool:
    if rel.as_posix() in {"arquivos/clientes/.htaccess", "arquivos/funcionarios/.htaccess", "arquivos/estabelecimentos/.htaccess", "config/.htaccess"}:
        return True
    if rel.name in NEVER or any(p.startswith(".") or p == ".." for p in rel.parts):
        return False
    if len(rel.parts) == 1:
        return rel.suffix.lower() == ".php"
    return len(rel.parts) >= 2 and rel.parts[0] in PERMITTED \
        and rel.suffix.lower() in PERMITTED[rel.parts[0]]

def ensure_folder(ftp: FTP_TLS, root: str, folder: Path) -> None:
    ftp.cwd(root)
    for name in folder.parts:
        if name in ("", "."):
            continue
        try:
            ftp.cwd(name)
        except error_perm:
            ftp.mkd(name)
            ftp.cwd(name)

def ir_para(ftp: FTP_TLS, caminho: str) -> str:
    """Entra em uma pasta a partir da raiz da conta, criando o que faltar."""
    ftp.cwd("/")
    for parte in caminho.split("/"):
        if parte in ("", "."):
            continue
        try:
            ftp.cwd(parte)
        except error_perm:
            ftp.mkd(parte)
            ftp.cwd(parte)
    return ftp.pwd()

def enviar_arquivos(ftp: FTP_TLS, raiz: str, files, package: Path) -> int:
    enviados = 0
    for file in files:
        rel = file.relative_to(package)
        ensure_folder(ftp, raiz, rel.parent)
        with file.open("rb") as stream:
            ftp.storbinary("STOR " + rel.name, stream)
        enviados += 1
    return enviados

def publish() -> None:
    if os.getenv("AGENDAY_DEPLOY_ENABLED") != "true" \
       or os.getenv("AGENDAY_DEPLOY_MODE") != "publish":
        raise RuntimeError("Deploy desativado até aprovação.")
    host = os.environ.get("AGENDAY_FTP_HOST", "").strip()
    username = os.environ.get("AGENDAY_FTP_USERNAME", "").strip()
    password = os.environ.get("AGENDAY_FTP_PASSWORD", "")
    if not all((host, username, password)):
        raise RuntimeError("Credenciais FTP não configuradas no GitHub.")
    if "/" in host or "://" in host or any(c.isspace() for c in host):
        raise ValueError("Host FTP inválido.")
    # A chave so existe na memoria do job protegido, nunca em checkout ou artifact.
    brevo_key = os.environ.get("AGENDAY_BREVO_API_KEY", "").strip()
    if not re.fullmatch(r"xkeysib-[A-Za-z0-9_-]{20,180}", brevo_key):
        raise RuntimeError("Chave Brevo ausente ou invalida no ambiente de producao.")
    if not PACKAGE.is_dir() or not (PACKAGE / "index.php").is_file():
        raise RuntimeError("Pacote de publicação ausente.")
    if not (PACKAGE / "config" / ".htaccess").is_file():
        raise RuntimeError("Diretorio privado sem protecao HTTP.")
    files = sorted(p for p in PACKAGE.rglob("*") if p.is_file())
    if not files:
        raise RuntimeError("Pacote vazio.")
    for file in files:
        if file.is_symlink() or not allowed(file.relative_to(PACKAGE)):
            raise RuntimeError("O pacote contém um arquivo proibido.")

    # Conexão FTPS explícita na porta 21, com certificado TLS validado.
    # Sem TLS válido, não enviar arquivos (não usar FTP desprotegido).
    ftp = FTP_TLS(context=ssl.create_default_context(), timeout=45)
    try:
        ftp.connect(host, 21)
        ftp.login(username, password)
        ftp.prot_p()
        ftp.set_pasv(True)

        # O mesmo pacote vai para as duas pastas: o site do estabelecimento e o
        # domínio do cliente. O domínio do cliente é publicado primeiro; se a
        # pasta não existir, o envio falha antes de mexer no site principal.
        pasta_cliente = os.environ.get(
            "AGENDAY_FTP_DIR_CLIENTE", "agenday-agendamentos.infinityfree.io/htdocs"
        ).strip("/")
        pasta_principal = os.environ.get("AGENDAY_FTP_DIR_PRINCIPAL", "htdocs").strip("/")

        # O index.php é enviado por último.
        files.sort(key=lambda f: (
            f.relative_to(PACKAGE).as_posix() == "index.php",
            f.relative_to(PACKAGE).as_posix(),
        ))

        total_cliente = 0
        total_principal = 0
        if pasta_cliente:
            raiz_cliente = ir_para(ftp, pasta_cliente)
            total_cliente = enviar_arquivos(ftp, raiz_cliente, files, PACKAGE)
        raiz_principal = ir_para(ftp, pasta_principal)
        total_principal = enviar_arquivos(ftp, raiz_principal, files, PACKAGE)

        # A regra config/.htaccess foi publicada antes de criar a credencial no servidor.
        # A configuração privada existe apenas na pasta do site principal.
        ensure_folder(ftp, raiz_principal, Path("config"))
        payload = ("<?php\n// Configuracao privada: jamais versionar.\n"
                   "return ['api_key' => '" + brevo_key + "'];\n").encode("ascii")
        ftp.storbinary("STOR brevo-api-config.php", BytesIO(payload))
        print(
            f"Envio concluído: {total_principal} arquivos no site principal, "
            f"{total_cliente} no domínio do cliente e configuração privada protegida; "
            "nenhum arquivo remoto apagado."
        )
    finally:
        try:
            ftp.quit()
        except Exception:
            ftp.close()

if __name__ == "__main__":
    try:
        publish()
    except Exception as exc:
        # Não imprimir senha, host ou traceback de conexão em logs públicos.
        print("Falha segura no FTPS (" + type(exc).__name__ + ").", file=sys.stderr)
        raise SystemExit(1)
