#!/usr/bin/env python3
"""Empacota somente os arquivos publicáveis (sem credenciais, SQL, documentação ou uploads)."""
from pathlib import Path
import shutil

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "deploy-package"
STATIC = {
    "css": {".css"}, "js": {".js"},
    "img": {".png", ".jpg", ".jpeg", ".webp", ".gif", ".jfif", ".svg", ".ico"},
    "icon": {".png", ".jpg", ".jpeg", ".webp", ".gif", ".jfif", ".svg", ".ico"},
    "api": {".php"}, "lib": {".php"},
}
PROHIBITED = {"conexao.php", "conexao.exemplo.php"}

def copy_safe(source: Path, relative: Path) -> None:
    if source.is_symlink() or not source.is_file() or ".." in relative.parts:
        raise ValueError("Arquivo não permitido: " + str(relative))
    target = OUT / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, target)

def build_package() -> None:
    if OUT.exists():
        shutil.rmtree(OUT)
    OUT.mkdir()
    for file in sorted(ROOT.glob("*.php")):
        if file.name not in PROHIBITED:
            copy_safe(file, Path(file.name))
    for dirname, suffixes in STATIC.items():
        directory = ROOT / dirname
        if not directory.is_dir():
            continue
        for file in sorted(directory.rglob("*")):
            if file.is_file() and file.suffix.lower() in suffixes:
                copy_safe(file, file.relative_to(ROOT))

    files = sorted(p.relative_to(OUT).as_posix() for p in OUT.rglob("*") if p.is_file())
    assert "index.php" in files and "agenda.php" in files, "Pacote incompleto"
    assert "lib/agendamento-gravar.php" in files, "Biblioteca ausente"
    assert "cancelar-agendamento.php" in files, "Cancelamento ausente"
    assert not any(p.startswith(("arquivos/", "sql/", "tests/", "docs/", "notas/", ".github/"))
                   or p in PROHIBITED or p.endswith(".env") for p in files), "Arquivo sensível no pacote"
    print(f"Pacote preparado: {len(files)} arquivos, sem SQL, senhas ou uploads.")

if __name__ == "__main__":
    build_package()
