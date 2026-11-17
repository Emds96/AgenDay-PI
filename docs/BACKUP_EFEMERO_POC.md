# AgenDay Backup Efemero — prova de conceito

**Status: PROVA REAL VALIDADA EM 17/11/2026; NAO HÁ EXPORTADOR PERMANENTE PUBLICADO.**
Origem: branch feat/backup-efemero, integrada na dev-feira-uninorte pelo PR #19 (merge 2885411e, 17/11/2026).
Nao substitui docs/BACKUP_E_RECUPERACAO.md ate prova de restauracao real.

## Objetivo

Backup em um comando no PC: PHP temporario gera exportacao MySQL,
formulario POST em navegador comum aciona o PHP, FTPS baixa SQL e htdocs,
MySQL/MariaDB local valida restauracao, e ZIP + SHA-256 sao criados no PC.
Sem Playwright/phpMyAdmin no fluxo padrao e sem backups no GitHub Actions.
O script NAO altera dados nem esquema do banco de producao.

## Arquivos versionados

- scripts/backup_agenday.py: orquestra FTPS, gatilho, copia, restauracao e relatorio.
- scripts/backup_php_template.txt: molde PHP sem tokens ou senhas reais; nao entra no deploy.
- tests/backup_test.py: testes locais puros, sem acessar producao.
- .env.backup.example e scripts/mysql-local.cnf.example: exemplos ficticios.

## Antes de qualquer teste real

1. Instalar e configurar MySQL/MariaDB **local** com cliente mysql, em localhost,
   usuario com CREATE/DROP apenas para bancos de TESTE (nunca credenciais de producao).
2. Criar fora do repositorio:
   Documents/AgenDay-Backups/.env.backup com dados FTPS,
   Documents/AgenDay-Backups/.mysql-local.cnf com credenciais so do MySQL local.
   Nao registrar segredos no chat, logs, Git ou artefatos de Actions.
3. Conferir a conta, a pasta remota htdocs e as permissões.
4. **Antes de gerar SQL:** testar pelo navegador real que arquivos de prova no
   diretorio protegido com .htaccess retornam 403, mesmo apos o desafio HTTP.
   NUNCA usar --guard-verified sem realizar essa verificacao.
5. Confirmar que o endpoint POST temporario e o diretorio nao sao publicos
   depois da execucao; verificar limpeza por FTPS.
6. Conferir compatibilidade do PHP com mysqli, tempo de execucao, formato e
   tamanho do banco. O limite de seguranca do dump no servidor e 8 MiB;
   bancos maiores exigem outra tecnica ou fallback manual.

## Comandos (na pasta do repositorio no PC)

- Uso cotidiano no Windows: atalho **Backup AgenDay** (Desktop), ou PowerShell com `scripts/Executar-Backup-AgenDay.ps1`. O iniciador liga/desliga o MariaDB local quando necessario.
- Testes offline: python -m unittest discover -s tests -p backup_test.py -v
- Diagnostico FTPS sem alteracoes:
  python scripts/backup_agenday.py --check-ftps
- Backup automatico, **somente depois do teste de protecao HTTP**:
  python scripts/backup_agenday.py --guard-verified --mysql-cli "CAMINHO_MYSQL_EXE"
- Fallback SQL do phpMyAdmin (sem exportador PHP):
  python scripts/backup_agenday.py --manual-sql "CAMINHO\arquivo.sql" --mysql-cli "CAMINHO_MYSQL_EXE"

## Seguranca e comportamento

- Geracao de identificador aleatorio de 128 bits e nonce aleatorio de 256 bits;
  GET de URL temporaria abre formulario no proprio dominio, que envia token por POST,
  nunca na query string. Quem conhece a URL aleatoria pode ver o token no formulario;
  por isso ela expira em poucos minutos e o nonce e de uso unico.
- PHP exige nonce, validade de cinco minutos e consumo unico por rename.
- SQL e nonce ficam em pasta com Require all denied (Apache).
- **O diretorio precisa ter protecao HTTP comprovada antes do primeiro teste**.
- SQL incompleto, tipo de objeto desconhecido, arquivo ausente, FTP incompleto,
  falha de importacao ou limpeza => BACKUP FALHOU, sem sobrescrever copias anteriores.
- A POC falha de maneira explicita se houver VIEW, TRIGGER, ROUTINE ou tabelas
  fora de InnoDB; incluir suporte antes de declarar o banco correspondente recuperavel.
- Restauracao acontece apenas em banco temporario aleatorio no MySQL do PC,
  conectado a 127.0.0.1, com comparacao de nomes e contagens. O banco temporario
  e excluido ao final. Sem MySQL local funcional: backup NAO e aprovado.
- Se falhar a remocao dos temporarios remotos, exigir limpeza manual por FTPS
  antes de nova execucao. Nao deixar exportador permanente.
- Hashes e logs privados ficam em Documents/AgenDay-Backups.
- Arquivos privados conexao.php, config, OAuth e uploads devem estar no ZIP privado;
  o GitHub sozinho nao representa uma copia completa.

## Resultado real em 17/11/2026 e limites

- Python e GitHub CLI estavam disponiveis no PC.
- Credenciais FTPS configuradas diretamente no PC pelo responsavel, sem acesso a valores no chat.
- MariaDB 11.8.9 portatil em 127.0.0.1, com senha local privada e ciclo liga/desliga verificado.
- FTP diagnostico OK, pasta privada bloqueada HTTP 403 pelo Edge, PHP teste HTTP 200.
- Backup real gerado em `Documents/AgenDay-Backups/2026-11-17_174312_82e123c1`: 82 arquivos, 9 tabelas incluindo `estabelecimento_google`, 51 linhas, SQL importado sem erro e comparado em banco temporario local.
- ZIP e SQL passaram SHA-256, ZIP tem 83 entradas e CRC OK; 0 arquivos temporarios remotos pendentes.
- Repeticao integral pelo launcher, iniciando com o MariaDB desligado, tambem aprovada: `2026-11-17_175147_81686ab8`. O banco local foi desligado apos o backup.
- Um primeiro teste de exportacao expirou sem SQL; diagnostico por fase implementado e teste posterior aprovado.
- O script continua sendo ferramenta separada do deploy, que exige autorizacao propia e backup recente.
- Para banco maior/charset especial, gerar casos de teste representativos.
- Backup de arquivos e SQL nao garante snapshot atomico em uploads concorrentes;
  preferir janela sem alteracoes durante a copia.

## Passos de aprovacao

- Testes unitarios offline aprovados e PHP gerado passa lint.
- FTPS diagnostico e espelhamento completos, inclusive arquivos ocultos.
- Gatilho POST funciona no navegador normal.
- Protecao HTTP do diretorio privado conferida pelo navegador.
- Exportacao de banco real conclui e o SQL restaura num MySQL/MariaDB local.
- Nenhum token, SQL ou exportador ficam no servidor depois da conclusao.
- Relatorio privado contem BACKUP OK, hashes, contagens e pasta datada.
- Os documentos `docs/BACKUP_E_RECUPERACAO.md` e `docs/STATUS.md` foram atualizados apos a validacao real.
- Ainda nao foi executado um teste completo do aplicativo PHP em servidor restaurado; isso nao invalida a importacao/contagens, mas limita a prova de recuperacao funcional.
