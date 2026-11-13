#!/usr/bin/env python3
"""Publica via FTPS explícito com TLS verificado; NUNCA apaga arquivos do servidor."""
from ftplib import FTP_TLS, error_perm
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
NEVER = {"conexao.php", "conexao.exemplo.php"}

def allowed(rel: Path) -> bool:
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
    if not PACKAGE.is_dir() or not (PACKAGE / "index.php").is_file():
        raise RuntimeError("Pacote de publicação ausente.")
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
        ftp.cwd("htdocs")
        root = ftp.pwd()
        # O index.php é enviado por último.
        files.sort(key=lambda f: (
            f.relative_to(PACKAGE).as_posix() == "index.php",
            f.relative_to(PACKAGE).as_posix(),
        ))
        for file in files:
            rel = file.relative_to(PACKAGE)
            ensure_folder(ftp, root, rel.parent)
            with file.open("rb") as stream:
                ftp.storbinary("STOR " + rel.name, stream)
        print(f"Envio concluído: {len(files)} arquivos; nenhum arquivo remoto apagado.")
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
