"""Testes locais do backup AgenDay; nao acessam servidor nem dados reais."""
import base64
import importlib.util
from pathlib import Path
import tempfile
import unittest

SRC = Path(__file__).resolve().parents[1] / "scripts" / "backup_agenday.py"
spec = importlib.util.spec_from_file_location("agenday_backup", SRC)
backup = importlib.util.module_from_spec(spec)
spec.loader.exec_module(backup)


class ValidacaoDump(unittest.TestCase):
    def exemplo(self, rows=2):
        nome = "estabelecimento_google"
        codificado = base64.b64encode(nome.encode()).decode()
        return (
            "-- AGENDAY_BACKUP_BEGIN\nSET NAMES utf8mb4;\n"
            "CREATE TABLE " + chr(96) + nome + chr(96) + " (id INT, nome VARCHAR(100));\n"
            "INSERT INTO " + chr(96) + nome + chr(96) + " VALUES (1,'João');\n"
            "-- AGENDAY_TABLE " + codificado + " " + str(rows) + "\n"
            "-- AGENDAY_BACKUP_SUCCESS tables=1\n"
        )

    def check(self, payload, manual=False):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "dump.sql"
            path.write_bytes(payload if isinstance(payload, bytes) else payload.encode())
            return backup.validate_sql(path, manual=manual)

    def test_valido_com_utf8mb4(self):
        self.assertEqual(self.check(self.exemplo()), {"estabelecimento_google": 2})

    def test_falta_marcador_final(self):
        with self.assertRaises(backup.BackupError):
            self.check(self.exemplo().replace("-- AGENDAY_BACKUP_SUCCESS tables=1\n", ""))

    def test_marcardor_com_quantidade_errada(self):
        with self.assertRaises(backup.BackupError):
            self.check(self.exemplo().replace("tables=1", "tables=2"))

    def test_duplicidade(self):
        s = self.exemplo()
        s = s.replace("-- AGENDAY_BACKUP_SUCCESS tables=1\n", "-- AGENDAY_TABLE " + base64.b64encode(b"estabelecimento_google").decode() + " 2\n-- AGENDAY_BACKUP_SUCCESS tables=1\n")
        with self.assertRaises(backup.BackupError):
            self.check(s)

    def test_charset_invalido(self):
        with self.assertRaises(backup.BackupError):
            self.check(b"\xff" + self.exemplo().encode())

    def test_backup_manual_sem_marcador(self):
        self.assertEqual(self.check("CREATE TABLE teste (id INT);\n", manual=True), {"teste": -1})

    def test_backup_manual_vazio_rejeitado(self):
        with self.assertRaises(backup.BackupError):
            self.check(" ", manual=True)

    def test_config_env_sem_execucao(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / ".env.backup"
            path.write_text("# comentario\nAGENDAY_FTP_HOST=ftpupload.net\n")
            self.assertEqual(backup.settings(path)["AGENDAY_FTP_HOST"], "ftpupload.net")

    def test_template_tem_protecao(self):
        text = (SRC.parent / "backup_php_template.txt").read_text()
        self.assertIn("hash_equals", text)
        self.assertIn("nonce.txt", text)
        self.assertIn("REQUEST_METHOD'] === 'GET'", text)
        self.assertIn('document.forms[0].submit()', text)
        self.assertIn("START TRANSACTION WITH CONSISTENT SNAPSHOT", text)
        self.assertIn("AGENDAY_BACKUP_SUCCESS", text)

    def test_limpeza_ftp_inclui_arquivos_ocultos(self):
        class FTPFalso:
            def __init__(self):
                self.removidos = []
                self.pastas = []
            def cwd(self, path):
                pass
            def delete(self, path):
                self.removidos.append(path)
            def rmd(self, path):
                self.pastas.append(path)
        ftp = FTPFalso()
        problemas = backup.remove_remote(ftp, "/htdocs", ".agenday-backup-abc", "_agenday_export_abc.php")
        self.assertEqual(problemas, [])
        self.assertIn(".htaccess", ftp.removidos)
        self.assertIn("nonce.txt.used", ftp.removidos)
        self.assertIn("database.sql", ftp.removidos)
        self.assertEqual(ftp.pastas, [".agenday-backup-abc"])


if __name__ == "__main__":
    unittest.main(verbosity=2)
