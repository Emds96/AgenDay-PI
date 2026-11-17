# Backup, verificação e recuperação — AgenDay 2.0

**Procedimento atualizado em 17/11/2026.** A rotina automática de backup foi validada no PC autorizado. Backup é requisito para publicação controlada e não é realizado pelo workflow de deploy. **Não executar restauração, upload, exportação, mudanças na hospedagem ou banco sem autorização.** Veja também [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md).

## 1. O que precisa ser protegido e por quê

A aplicação PHP/MySQL usa a hospedagem independente https://agenday-sistema.infinityfree.io/. Um backup útil exige **duas partes da mesma janela temporal**, não somente o GitHub:

1. **Arquivos completos de `htdocs/`** do InfinityFree: PHP em produção, CSS/JS, `.htaccess`, `htdocs/conexao.php` **privado**, arquivos enviados em `arquivos/clientes/` e `arquivos/funcionarios/` e quaisquer outros arquivos remotos. O GitHub **não contém** a configuração privada nem os uploads.
2. **Exportação SQL atual da base MySQL independente**, incluindo estrutura e dados, não apenas o script `sql/BD_v2.sql` do repositório.

A equipe deve manter cópias **privadas, fora do GitHub/ChatGPT**, com proteção de acesso (criptografia se disponível). Arquivos `.sql` e `conexao.php` contêm dados/segredos. Não incluir backup em artifact público do GitHub Actions ou em PR.

## 2. Histórico de falhas observado em 16/11/2026 (superado pelo backup de 17/11)

- Diretório privado: `%USERPROFILE%\Documents\AgenDay-Backups\2026-11-16`. **Inspeção somente leitura confirmou somente `STATUS_BACKUP_PENDENTE.txt`**.
- Em `%USERPROFILE%\Documents\AgenDay-Backups\2026-11-13` há `htdocs.zip` e `agenday-db-2026-11-13-pre-deploy.sql.zip`. Segundo a verificação do arquivo de status do computador, os ZIPs passaram na checagem de integridade, mas `htdocs.zip` tem apenas **3 entradas**, e a exportação SQL precede o QA/cadastros de 16/11. **Não são backup atual recuperável do ambiente completo.**
- O GitHub CLI consegue listar **nomes** dos Secrets do environment `producao-agenday`, **não recuperar seus valores**. Não tentar descobrir nem imprimir credenciais.
- **Nenhum novo backup foi criado nesta revisão**, nem foi validado acesso ao painel FTP/phpMyAdmin. O deploy deve permanecer bloqueado até a obtenção das cópias (ou outra decisão explícita e informada do responsável).

## 3. Método recomendado pelo próprio InfinityFree

Fontes do provedor (consultadas em 16/11/2026):
- [How to backup your website](https://forum.infinityfree.com/t/how-to-backup-your-website/49304): copiar `htdocs/` por cliente FTP de desktop, exportar banco pelo phpMyAdmin; repetir transferências que falharem.
- [Connecting to MySQL from an external application](https://forum.infinityfree.com/t/connecting-to-mysql-from-an-external-application/49339): o plano gratuito **não permite MySQL externo**; não planejar `mysqldump` remoto direto do computador.

O provedor não mantém backups automáticos do plano gratuito e desaconselha scripts pesados de backup. O AgenDay usa uma solução **efêmera e validada em 17/11/2026**, com limite de tamanho, proteção HTTP comprovada, remoção do PHP e SQL remotos, download FTPS e restauração local. Não usar plugins de backup nem compactar todo o site no servidor.

## 3A. Rotina automática validada — PHP temporário + FTPS + MariaDB local

**Estado comprovado em 17/11/2026:** backup real gerado com sucesso em `Documents/AgenDay-Backups/2026-11-17_174312_82e123c1`, contendo `82` arquivos de `htdocs/` e exportação de `9` tabelas (incluindo `estabelecimento_google`, com 51 registros no total). Importação em MariaDB 11.8.9 local temporário aprovada com comparação de tabelas e contagens. ZIP com 83 entradas, CRC e hashes SHA-256 conferidos. **Repetição pelo iniciador Windows também aprovada**: `Documents/AgenDay-Backups/2026-11-17_175147_81686ab8` (82 arquivos e 9 tabelas), iniciando o MariaDB desligado e encerrando-o automaticamente depois. Verificação FTPS posterior: nenhum temporário de backup restou na hospedagem.

**Uso normal no PC autorizado (Windows):**

1. Clicar no atalho **Backup AgenDay** na área de trabalho, ou executar `scripts/Executar-Backup-AgenDay.ps1` pelo PowerShell. O iniciador verifica e inicia o MariaDB portátil em `127.0.0.1` se necessário; ao terminar, encerra somente a instância que iniciou.
2. O Python usa o arquivo privado `Documents/AgenDay-Backups/.env.backup` (credenciais FTPS), fora do GitHub, e o arquivo `.mysql-local.cnf` com credenciais **somente do MariaDB local**. Não colar valores no chat nem em logs.
3. O script envia um exportador PHP temporário com nome aleatório e nonce de uso único. O navegador padrão abre a URL temporária para executar a exportação, sem entrar no phpMyAdmin. Dados exportados ficam em pasta protegida com `.htaccess` (HTTP 403 comprovado em teste real).
4. FTPS baixa o SQL e toda a árvore `htdocs` atual; o exportador, nonce e SQL temporários são removidos da hospedagem. O PHP só permite tabelas/objetos compatíveis com a versão testada e falha explicitamente caso não consiga exportar com segurança.
5. O script valida o SQL e importa em **banco temporário local**, comparando nomes/contagens de tabelas. Verifica ZIP e hashes e só imprime **BACKUP OK** após essas etapas. Falhas são registradas sem substituir um backup anterior.
6. Conferir a pasta datada do backup e o `relatorio.json`. Guardar o ZIP em local privado com cópia adicional protegida, pois contém configuração privada, uploads e dados de usuários. **Não enviar o backup ao GitHub, PR, Actions ou chats.**

**Diagnóstico sem backup:** `powershell -NoProfile -File scripts/Executar-Backup-AgenDay.ps1 -CheckOnly` testa MariaDB local/FTPS. **Plano B manual:** `python scripts/backup_agenday.py --manual-sql CAMINHO_DO_SQL --mysql-cli CAMINHO_MARIADB_EXE` (com MariaDB local ligado).

**Limites honestos:** o arquivo SQL temporário tem limite conservador de 8 MiB; não foram testadas bases maiores, VIEW/TRIGGER/ROUTINE ou motores fora de InnoDB. A restauração comprovada abrange esquema/contagens do banco e integridade ZIP, **não** um teste completo do AgenDay em servidor restaurado. Repetir backup antes de cada deploy, pois um backup antigo não cobre alterações posteriores. O PHP de backup não é permanente no servidor; o workflow do deploy não gera backups.

---
## 4. Procedimento de backup de arquivos — sem alterar produção

**Requer acesso legítimo à conta InfinityFree e autorização do responsável.** Se as credenciais FTP não estão no computador, o usuário pode recuperá-las/confirmá-las **no painel da própria conta** e digitá-las **diretamente no cliente FTP**, sem revelar valores no chat, terminal com logs, repositório ou prints.

1. Abrir o painel InfinityFree **da conta independente do AgenDay 2.0**, nunca a conta do Gabriel; confirmar domínio e `htdocs` de destino.
2. Conectar um cliente FTP de desktop (como FileZilla) a essa conta. Preferir transferência protegida quando suportada e validada; o publicador comprovado usa **FTPS explícito com TLS**. Não desabilitar validação de certificados para “fazer funcionar”.
3. Navegar ao diretório remoto `htdocs/` do site, **baixando dele para a máquina** (não enviar/substituir arquivos). Salvar em subpasta privada identificada, por exemplo `%USERPROFILE%\Documents\AgenDay-Backups\AAAA-MM-DD\site-htdocs\`.
4. Habilitar visibilidade de arquivos ocultos e conferir `.htaccess`, `conexao.php` e uploads. A transferência não deve omitir arquivos nem quebrar nomes/extensões.
5. Revisar a fila de transferências concluídas e falhas; repetir falhas e comparar ao menos **quantidades, caminhos e tamanhos** dos arquivos remotos e locais. Registrar qualquer diferença **sem copiar os conteúdos sensíveis** para um chat.
6. Depois de validar a árvore local, opcionalmente produzir ZIP **no computador** e verificar sua integridade; não deixar a única cópia em uma pasta temporária ou na hospedagem.

**Se faltar acesso FTP:** registrar o bloqueio e solicitar ao responsável que acesse o painel ou forneça credenciais diretamente ao cliente local. Não acionar novo workflow nem alterar Secrets como atalho. Se uma sessão tiver acesso autorizado por navegador ou computador, pode guiar ou executar a cópia **somente após aprovação**, sem expor senhas.

## 5. Procedimento de exportação MySQL — sem escrever no banco

1. Abrir **painel da conta correta no InfinityFree**, selecionar **MySQL Databases / phpMyAdmin** e conectar ao banco **independente AgenDay 2.0**. Confirmar visualmente nome e existência das tabelas antes de prosseguir.
2. No phpMyAdmin, selecionar o banco correto (não somente uma tabela), abrir **Exportar**. Usar método **Rápido** quando oferecer estrutura e dados do banco, formato **SQL**, e iniciar o download. Se for necessário utilizar “Personalizado”, garantir seleção de **todas as tabelas, estrutura e dados**, sem opções destrutivas.
3. Guardar o arquivo `.sql`/`.sql.gz` em pasta privada `%USERPROFILE%\Documents\AgenDay-Backups\AAAA-MM-DD\banco\`. Não enviar o conteúdo ao GitHub, chats ou páginas de terceiros.
4. Verificar que o arquivo existe, tem tamanho não nulo, contém instruções de **estrutura de todas as tabelas existentes na data da exportação** e, quando existirem registros, os respectivos dados exportados. Evitar divulgar nomes, clientes, e-mails, senhas/hash e dados sensíveis na verificação. Uma exportação válida **pode** ter tabela vazia; a ausência de `INSERT` isoladamente não prova falha.
5. Identificar versão e data do backup; manter o arquivo intacto. **Não executar SQL**, importar, “reparar” ou reconfigurar o banco na etapa de backup.

Tabelas de referência do `BD_v2.sql`: `proprietario`, `estabelecimento`, `endereco`, `clientes`, `funcionarios`, `servicos`, `funcionario_servico` e `agendamentos`. A presença e compatibilidade reais precisam ser confirmadas na exportação da hospedagem.

## 6. Critérios para declarar BACKUP VALIDADO

Não declarar sucesso só porque a pasta existe, o ZIP abre, ou o download foi iniciado. Checklist cumulativo:

- [ ] Cópia **recente** e **integral** de `htdocs/` com arquivos privados e uploads.
- [ ] Exportação **recente** e legível do banco correto, com estrutura e dados.
- [ ] Pasta/arquivos conferidos, sem transferência falha nem erro de download; verificações de quantidade, caminhos, tamanhos e legibilidade.
- [ ] Registro privado de data/hora, origem, tipo, tamanho, responsável e **hash SHA-256** de cada arquivo de backup (opcionalmente teste de leitura/CRC).
- [ ] Restauração **planejada** e viável; se possível, teste de restauração **somente em ambiente isolado e com autorização**, nunca na base de produção apenas para testar.
- [ ] Cópias protegidas e separadas da conta hospedada; nenhuma credencial ou SQL publicado.
- [ ] Evidência textual **não sensível** registrada no `docs/STATUS.md` ou registro privado de operação; não incluir hashes de material contendo secrets caso não seja necessário.

**Resultado permitido:** `PENDENTE` / `VALIDADO` / `BLOQUEADO`. “ZIP íntegro” significa só que a estrutura do arquivo passou na checagem, **não que cobre o site atual**.

## 7. Modelo de registro privado de backup

Registrar em arquivo local privado (não anexar o SQL):

```text
Data/hora:
Responsável:
Origem: AgenDay 2.0 / InfinityFree (conferido)
Arquivos htdocs: caminho local, número de arquivos, tamanho, íntegros? 
Banco MySQL: caminho local, 8 tabelas conferidas?, estrutura/dados?
SHA-256 de cada cópia: calculado localmente
Falhas de transferência/exportação:
Situação: PENDENTE | VALIDADO | BLOQUEADO
Próximo passo:
```

PowerShell local para **verificação sem leitura do conteúdo**:

```powershell
# Substituir pelo CAMINHO LOCAL correto, sem publicar o resultado de arquivo privado.
Get-Item -LiteralPath 'CAMINHO_DO_ARQUIVO_BACKUP' | Select-Object Name,Length,LastWriteTime
Get-FileHash -Algorithm SHA256 -LiteralPath 'CAMINHO_DO_ARQUIVO_BACKUP' |
  Select-Object Algorithm,Hash
```

## 8. Recuperação/rollback — somente plano, não execução

O `scripts/publicar_ftp.py` **não apaga arquivos remotos nem faz rollback**, e o upload **não é atômico**. Se um deploy parcial causar falha:

1. **Parar novas alterações** e registrar run, SHA, arquivos/logs, impacto e status das Variables.
2. Identificar se o problema é de arquivos, configuração privada ou dados. Preservar o banco e verificar backups antes de qualquer escrita.
3. Com autorização específica, escolher **republicar um pacote aprovado** ou **restaurar arquivos do backup** via conexão segura. **Não sobrescrever `conexao.php` de outra conta**, não restaurar fotos incompletas e não executar SQL sem plano de impacto.
4. Restaurar banco **somente** com autorização explícita e avaliação de perda de dados posteriores ao backup; idealmente testar antes em banco isolado.
5. Validar autenticação, cadastros, agenda, uploads e isolamento; registrar resultado.

Não há recuperação automática implementada hoje. A documentação deste plano **não autoriza restauração**.

## 9. Na passagem para outra sessão

> O procedimento automático foi validado em 17/11/2026: `Backup AgenDay` no Desktop do PC ou `scripts/Executar-Backup-AgenDay.ps1`. Consultar o relatório privado em `Documents/AgenDay-Backups/`, confirmar backup recente conforme data do deploy e jamais publicar sem autorização do responsável. Não exibir FTP, SQL ou `conexao.php` no chat. `dev-feira-uninorte` é a referência; `main` permanece intocada.
