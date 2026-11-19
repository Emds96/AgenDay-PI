"""Testes offline: nunca usam gh, FTPS, banco ou hosting."""
import hashlib
import importlib.util
import json
from datetime import datetime, timedelta
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
import zipfile

SCRIPT = Path(__file__).resolve().parents[1] / "scripts/publicacao_assistida.py"
spec = importlib.util.spec_from_file_location("assistente", SCRIPT)
assistente = importlib.util.module_from_spec(spec)
spec.loader.exec_module(assistente)
SHA = "a" * 40


class PublicacaoAssistidaOffline(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.path = Path(self.tmp.name)
        (self.path / "banco-agenday.sql").write_bytes(b"backup-teste-offline")
        with zipfile.ZipFile(self.path / "backup-completo.zip", "w") as z:
            z.writestr("banco-agenday.sql", b"backup-teste-offline")
            z.writestr("htdocs/index.php", b"<?php echo 'teste';")
        self.report = self.path / "relatorio.json"
        self.dados = {"status": "BACKUP OK", "data": datetime.now().strftime("%Y-%m-%d_%H%M%S"),
                      "arquivos": 10, "tabelas": 3, "restauracao_local": "aprovada",
                      "sha256_sql": hashlib.sha256((self.path / "banco-agenday.sql").read_bytes()).hexdigest(),
                      "sha256_zip": hashlib.sha256((self.path / "backup-completo.zip").read_bytes()).hexdigest()}
        self.gravar()

    def tearDown(self):
        self.tmp.cleanup()

    def gravar(self):
        self.report.write_text(json.dumps(self.dados), encoding="utf-8")

    def test_simulacao_nao_chama_gh(self):
        with patch.object(assistente, "gh", side_effect=AssertionError("gh nao pode ser chamado")):
            self.assertEqual(assistente.main(["--sha", SHA]), 0)

    def test_relatorio_valido(self):
        self.assertEqual(assistente.validar_backup(self.report), self.dados["data"])

    def test_arquivo_alterado_bloqueia(self):
        (self.path / "banco-agenday.sql").write_bytes(b"conteudo adulterado")
        with self.assertRaises(assistente.Bloqueio):
            assistente.validar_backup(self.report)

    def test_zip_corrompido_bloqueia(self):
        (self.path / "backup-completo.zip").write_bytes(b"arquivo-invalido")
        self.dados["sha256_zip"] = hashlib.sha256((self.path / "backup-completo.zip").read_bytes()).hexdigest()
        self.gravar()
        with self.assertRaises(assistente.Bloqueio):
            assistente.validar_backup(self.report)

    def test_backup_antigo_bloqueia(self):
        self.dados["data"] = (datetime.now() - timedelta(days=2)).strftime("%Y-%m-%d_%H%M%S")
        self.gravar()
        with self.assertRaises(assistente.Bloqueio):
            assistente.validar_backup(self.report)

    def test_confirmacao_incorreta_nao_chama_gh(self):
        with patch.object(assistente, "gh", side_effect=AssertionError("gh nao pode ser chamado")):
            with self.assertRaises(assistente.Bloqueio):
                assistente.publicar(SHA, self.report, "NAO AUTORIZO")

    def test_sha_invalido_rejeitado(self):
        with self.assertRaises(SystemExit) as saida:
            assistente.main(["--sha", "123"])
        self.assertEqual(saida.exception.code, 2)


if __name__ == "__main__":
    unittest.main()
