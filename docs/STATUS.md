# STATUS — AgenDay 2.0

## QA-04 — Configurações básicas do estabelecimento (18/11/2026, em implementação)

- Autorizado nesta sessão: carregar nome/e-mail/contato reais, editar nome/contato, salvar no MySQL, aplicar sessão/CSRF/queries preparadas e melhorar visual; deploy autorizado sujeito a backup, CI e gates.
- Código em branch de tarefa: config.php, lib/configuracao-estabelecimento.php, js/button-add.js e tests/configuracao-estabelecimento.php. SELECT/UPDATE restritos ao estabelecimento autenticado; nome da sessão atualizado após salvar; escape HTML e POST/Redirect/GET.
- Ainda não publicado e não validado em produção neste registro. Ver [QA-04](QA04_CONFIG_ESTABELECIMENTO.md). Não incluir perfil, endereço, CNPJ e horários neste escopo.

---

**Atualizado em:** 17/11/2026 (QA-03 validada manualmente em produção, incluindo identidade do e-mail AgenDay Sistema; isolamento entre estabelecimentos permanece parcialmente auditado)  
**Repositório:** `Emds96/AgenDay-PI`  
**Referência de desenvolvimento:** `dev-feira-uninorte`  
**Commit publicado comprovado:** `5749f333338e599c67c9e6050e39caa684091bac` (novo remetente QA-03, PR #23) via [GitHub Actions #44212874922](https://github.com/Emds96/AgenDay-PI/actions/runs/44212874922) em 17/11/2026, com as flags de deploy desligadas novamente após o envio. Conferir HEAD atual da `dev-feira-uninorte` antes de próximas tarefas.  
**Fonte da evidência funcional:** QA autenticada relatada pelo responsável em 16/11/2026, login Google testado manualmente em 17/11/2026, e **relatos/capturas de e-mail reais da QA-03** no mesmo dia. Os testes automatizados de sintaxe/CI, FTPS e sandbox Brevo são identificados separadamente; não são confundidos com leitura da caixa postal.

## Última validação confirmada pelo responsável — QA-03 (17/11/2026)

- **Recuperação de senha:** concluída e confirmada em teste real: link recebido (na primeira tentativa, no spam), definição de senha nova, login válido com a nova e rejeição da antiga; relato manual do responsável.
- **Interface:** páginas `esqueci-senha.php` e `redefinir-senha.php` com identidade do login e aviso sobre atraso/spam publicadas no PR #21, deploy #44207273591; QA visual do navegador em 390px e POST com e-mail fictício aprovados.
- **Remetente profissionalizado:** `AgenDay Sistema` verificado na Brevo; [PR #23](https://github.com/Emds96/AgenDay-PI/pull/23) publicado no deploy [#44212874922](https://github.com/Emds96/AgenDay-PI/actions/runs/44212874922); sandbox HTTP 201 pela hospedagem. **Captura real do responsável:** Caixa de entrada às 21h40, `De: agendaysistema@12832884.brevosend.com`, `Responder para: agendaysistema@gmail.com`. **APROVADO no cenário testado**.
- **Restantes não cobertos:** expiração/reutilização de token, limites de tentativa, medição de entrega em múltiplos provedores e auditoria geral de segurança. A `main` não mudou; flags de deploy restabelecidas em `false/disabled`.
- **Regra de continuidade:** toda confirmação de validação pelo responsável deve gerar, **antes da resposta de encerramento**, atualização integrada em `docs/STATUS.md` e nos documentos ativos afetados. Ver `AGENTS.md` e `docs/GUIA_DE_CONTINUIDADE.md`.

---

## Backup PHP efêmero + FTPS (17/11/2026; teste real aprovado)

- [PR #19](https://github.com/Emds96/AgenDay-PI/pull/19) **integrado** em `dev-feira-uninorte` no merge `2885411e726607475ec2d085716dbe20c98db6c0` (17/11/2026). Codigo do backup/documentacao integrados, **sem deploy da aplicação**; o exportador PHP existiu somente durante o backup e foi removido.
- Criados `scripts/backup_agenday.py`, `scripts/backup_php_template.txt`, configuracoes de exemplo sem credenciais, testes e `docs/BACKUP_EFEMERO_POC.md`. O template PHP nao entra no pacote de deploy.
- Script validado: gera endpoint e nonce temporarios, abre URL propria do site com formulario GET/POST no mesmo dominio, exporta MySQL usando `mysqli` e `utf8mb4`, baixa por FTPS e espelha htdocs, valida SQL, testa restauracao apenas em MySQL local, gera ZIP/relatorio privado. Ha fallback `--manual-sql`.
- **Testes:** 10 testes Python aprovados, lint Python, [CI pós-merge #44185531738](https://github.com/Emds96/AgenDay-PI/actions/runs/44185531738) `validar=success` e [PHP lint #44185531595](https://github.com/Emds96/AgenDay-PI/actions/runs/44185531595) `success` com PHP-lint e pacote público; deploy `skipped`. Após isso, exportação e restauração reais no PC passaram.
- **Execução real VALIDADA:** usuário configurou FTPS diretamente no PC via Pairdesk (sem compartilhar credenciais). MariaDB 11.8.9 portátil configurado apenas em `127.0.0.1`. Teste HTTP 403 em pasta com `.htaccess` passou; PHP HTTP 200 passou. Backup `2026-11-17_174312_82e123c1`: `82` arquivos (incluindo `conexao.php`, configuração privada Google e uploads), SQL de `9` tabelas / `51` linhas, restauração local com contagens aprovada, ZIP de `83` entradas, SHA-256 SQL/ZIP conferidos, sem temporários no servidor. O primeiro teste do exportador teve timeout; código foi ajustado com diagnóstico por fase, e o segundo concluiu. **Segunda execução integral**, iniciada pelo launcher Windows com MariaDB inicialmente desligado, também teve `BACKUP OK`: `2026-11-17_175147_81686ab8`, 82 arquivos, 9 tabelas, 83 entradas ZIP, SHA-256 coincidente e banco local encerrado após execução.
- O provedor recomenda FTP + phpMyAdmin; a POC PHP e uma alternativa experimental com risco de timeout/tamanho. Nao assumir backup valido antes de verificacao e restauracao. Nao guardar dumps nem segredos em repositorio ou artifacts.
- Procedimento oficial atualizado em `docs/BACKUP_E_RECUPERACAO.md` com modo automático validado, atalho **Backup AgenDay** na área de trabalho e fallback manual. A QA-03 foi publicada após backups validados, e a **entrega real e a redefinição de senha foram confirmadas manualmente pelo responsável** (ver seção QA-03).

---

## QA-03 — Recuperação de senha com Brevo (17/11/2026; PR #18 integrado)

- [PR #18](https://github.com/Emds96/AgenDay-PI/pull/18) **integrado** em `dev-feira-uninorte` no commit `4ebaffea224e2a825723af3de1b513c52b978f60`, autorizado pelo responsável em 17/11/2026. A `main` permanece preservada.
- Código integrado: botão Esqueceu a senha, telas `esqueci-senha.php`/`redefinir-senha.php`, token de 30 minutos com SHA-256 e uso único, envio HTTPS Brevo e migração incremental `sql/QA03_recuperacao_senha.sql`.
- **Banco de produção:** antes da migração foi criado novo backup completo `2026-11-17_190728_fe52b711` com `BACKUP OK` e restauração local. A migração incremental `sql/QA03_recuperacao_senha.sql` foi aplicada por executor PHP temporário de uso único; retorno `QA03_MIGRATION_OK`, seis colunas e chave estrangeira validadas, temporários remotos removidos.
- **Publicação:** commit `43676874d7a3abeba3de72ec00da62af2cd8bc68` enviado pelo workflow [#44197680873](https://github.com/Emds96/AgenDay-PI/actions/runs/44197680873); validação, diagnóstico FTPS e publicação `success`. Depois, `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`.
- **Site real:** `esqueci-senha.php` HTTP 200, `redefinir-senha.php` rejeita token inválido, configuração Brevo privada HTTP 403. Formulário com e-mail fictício inexistente exibiu mensagem genérica esperada, sem disparo real. Arquivo de configuração Brevo foi confirmado por FTPS sem leitura de seu conteúdo.
- **Brevo:** Secret `AGENDAY_BREVO_API_KEY` presente. Sandbox do endpoint `/v3/smtp/email` retornou HTTP 401 antes da autorização de IP. Painel mostrava 1 IP bloqueado e 0 autorizados. Após confirmação pelo responsável, painel mostrava **1 IP autorizado e 0 bloqueados**, mantendo a restrição por IP ativada; o teste sandbox do endpoint de envio passou com **HTTP 201**, sem entrega de mensagem. Temporários do teste foram removidos.
- **Novo remetente AgenDay Sistema publicado:** o responsável criou `agendaysistema@gmail.com` e verificou o remetente com nome **AgenDay Sistema** no painel Brevo (o remetente anterior foi mantido no painel). [PR #23](https://github.com/Emds96/AgenDay-PI/pull/23) integrado na `dev-feira-uninorte`; commit `5749f333338e599c67c9e6050e39caa684091bac` publicado no [workflow #44212874922](https://github.com/Emds96/AgenDay-PI/actions/runs/44212874922) (validação, FTPS, publicação success). Backup pré-deploy `2026-11-17_205855_4f8c886e` concluiu `BACKUP OK` com restauração local. FTPS confirmou `lib/brevo-email.php` com remetente/nome novos e sem o e-mail antigo. O endpoint Brevo `/v3/smtp/email` aceitou **sandbox HTTP 201** com o novo remetente, sem envio real; temporários removidos. Login e recuperação HTTP 200, config Brevo privada HTTP 403. Variáveis restauradas: `AGENDAY_DEPLOY_ENABLED=false`, `AGENDAY_DEPLOY_MODE=disabled`. **Validação real recebida após o deploy:** o responsável apresentou captura do Gmail com mensagem entregue à **Caixa de entrada** (17/11/2026, 21h40), nome **AgenDay Sistema**, campo **De:** `agendaysistema@12832884.brevosend.com` e **Responder para:** `agendaysistema@gmail.com`. Isso confirma o endereço mostrado no caso testado; taxa de entregabilidade em outros provedores não foi medida. A chave API não foi rotacionada/alterada nesta tarefa.
- **Teste real relatado pelo responsável (17/11/2026):** o e-mail de recuperação chegou com atraso na pasta spam de sua conta; o link permitiu criar uma nova senha, que foi aceita no login, e a senha antiga foi rejeitada. É relato de validação manual, não repetição automatizada. Permanecem pendentes os testes de expiração/reutilização do token, limites de solicitações e regressão do login Google.
- **Melhoria visual QA-03 publicada e verificada:** [PR #21](https://github.com/Emds96/AgenDay-PI/pull/21) no commit `393b281e` publicado no [run #44207273591](https://github.com/Emds96/AgenDay-PI/actions/runs/44207273591), validação/FTPS/deploy `success`. Backup pré-publicação `2026-11-17_201733_0559724e` com `BACKUP OK` e restauração local aprovada. As telas `esqueci-senha.php` e `redefinir-senha.php` respondem HTTP 200 com novo CSS, visual alinhado ao login e alerta de spam visível. Teste móvel de 390px: uma coluna, sem rolagem horizontal; POST com e-mail fictício retorna mensagem genérica com aviso de atraso/spam, sem erro. Token inválido rejeitado e config Brevo privada continua HTTP 403. Flags novamente `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. Nenhuma senha de usuário foi modificada neste deploy.
- **Segurança:** rotação da chave previamente transitada por Google Keep/Drive continua recomendada; a API não revela se a chave publicada já foi rotacionada. Não registrar a chave no Git ou no chat.
- Procedimento de ativação em `docs/QA03_RECUPERACAO_SENHA_BREVO.md`.

---
## Login com Google — publicação direta autorizada e hotfix (17/11/2026)

**Autorização:** responsável pediu publicação direta para teste no site oficial, sem criar ambiente de testes separado. **Alvo:** somente `agenday-sistema.infinityfree.io`, branch `dev-feira-uninorte`, sem mexer na `main` ou na conta do repositório original.

**Código e banco:**
- [PR #12](https://github.com/Emds96/AgenDay-PI/pull/12) integrado à dev no commit `ce8358018ff06e24786064d489741f312df04c68`: botões Google via GIS, token verificado no PHP, cadastro inicial com pop-up de visual AgenDay, senha opcional, aviso de que não é senha Google, sessões e vinculação segura a conta existente após login por senha; testes de ID token no workflow. **Os fluxos principais de cadastro/login Google foram validados manualmente em produção em 17/11/2026, após o hotfix**, conforme registro abaixo.
- Google Auth Platform: projeto `agenday-515003`, público Externo, status **Testando**, **1 conta de teste** autorizada. O client ID é identificador público, não Client Secret.
- No phpMyAdmin do banco independente, após exportar backup SQL privado, aplicado com retorno sem erro: `CREATE TABLE IF NOT EXISTS estabelecimento_google` (sub único, FK estabelecimento), e `ALTER TABLE estabelecimento MODIFY senha_estabelecimento VARCHAR(255) NULL DEFAULT NULL`. **Não foram executados DELETE, DROP ou UPDATE em registros existentes.**
- Backup SQL de 17/11, pasta privada `Documents/AgenDay-Backups/2026-11-17-pre-google/`, verificado por leitura ZIP, 8 definições de tabelas e SHA-256. **Backup completo de arquivos mais recente confirmado permanece de 16/11**, de antes desta publicação. Não afirmar backup integral da mesma janela temporal nem restauração testada.
- Criado `htdocs/google-oauth-config.php` por File Manager. O upload original do PR #12 foi concluído no [run #44065895502](https://github.com/Emds96/AgenDay-PI/actions/runs/44065895502), mas a inspeção do navegador encontrou **HTTP 500 em `index.php`**. Suspeita principal: carregamento direto do arquivo privado de configuração, **causa exata não confirmada por log PHP**.
- [PR #13](https://github.com/Emds96/AgenDay-PI/pull/13) corrige carregamento: centraliza no helper, captura exceções do arquivo privado e fornece fallback com **OAuth Client ID público**, sem expor Client Secret; merge `b06ae1f1019cee78ebdc90285ea808f6c6062730`. [Deploy hotfix #44067516631](https://github.com/Emds96/AgenDay-PI/actions/runs/44067516631): `validar=success`, `diagnosticar_ftp=success`, `publicar=success`. Após terminar, flags conferidas: `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`.
- **Verificação inicial pós-hotfix via ZERO Browser MCP**: `https://agenday-sistema.infinityfree.io/index.php` retornou página `Login` em vez de HTTP 500, com login e cadastro tradicionais presentes, script oficial GIS, `g_id_onload` e **2 contêineres de botões Google**. **Essa inspeção isolada não executou cadastro nem autenticação Google**; os testes manuais posteriores do responsável estão registrados a seguir. A inspeção não comprovou separadamente a compatibilidade cURL com os certificados Google.

### Validação manual dos fluxos principais do login Google — 17/11/2026

**Evidência:** testes executados no site publicado e informados diretamente pelo responsável. Não houve repetição desses cenários por automação nesta etapa. Detalhes e pendências em [`docs/LOGIN_GOOGLE_CHECKLIST.md`](LOGIN_GOOGLE_CHECKLIST.md).

- [x] Primeiro cadastro via Google, com pop-up de complemento e nome do estabelecimento, escolhendo **Agora não** para não cadastrar senha local.
- [x] Logout e novo login pelo botão Google na conta criada sem senha do AgenDay.
- [x] Cadastro de outra conta via Google, desta vez com **senha própria do AgenDay**.
- [x] Logout e login tradicional usando **e-mail e senha do AgenDay** nessa segunda conta.
- [x] Logout e login via botão **Entrar com Google** nessa segunda conta que também possui senha local.
- [x] Alternância no seletor de contas Google, retornando com sucesso à primeira conta (sem senha local).

**Resultado:** **APROVADO — fluxos principais de cadastro e login Google em produção**, conforme teste manual do responsável. Isso não equivale à aprovação de todos os casos de segurança ou de isolamento entre estabelecimentos.

**Situação atual:** **login Google aprovado nos fluxos principais testados manualmente em produção**. Pendências independentes: vincular conta tradicional já existente, avaliar visual/responsividade do pop-up, casos negativos de autenticação e regressão ampla do sistema. **O isolamento de dados entre estabelecimentos não invalida a validação do login Google**: sua revisão estática e verificações de consistência foram favoráveis, mas o teste cruzado autenticado A/B permanece pendente. Não presumir Google Cloud em `Produção` sem confirmação; não executar outro deploy sem autorização.

## QA-02 — alteração de senha ([PR #10](https://github.com/Emds96/AgenDay-PI/pull/10) integrado e publicado, 16/11/2026)

**Problema confirmado no código:** `alterar-senha.php` mostrava formulário legado de cliente (nome, contato, e-mail, foto), sem ação para trocar a senha.

**Implementação em branch de tarefa** `fix/qa02-alteracao-senha-20261116`, derivada da `dev-feira-uninorte` no SHA `e2f5d61e5289261b8b74dc31ebbdf1f7aa82cce3`:
- `alterar-senha.php`: formulário autenticado com senha atual, nova senha e confirmação; `POST` com CSRF, tratamento seguro de falhas e sucesso, `303` após envio válido, mensagem acessível. Consulta o hash somente da conta autenticada, usa `password_verify`, grava `password_hash(PASSWORD_DEFAULT)` com `UPDATE` preparado e verificação do hash anterior para evitar sobrescrita simultânea; renova o ID de sessão e token CSRF após sucesso.
- `lib/alteracao-senha.php`: valida entrada e aplica o limite de 8–72 bytes compatível com o cadastro, rejeita repetição e divergência.
- `css/alterar-senha.css`: remove altura fixa para acomodar campos/mensagens em celular.
- `tests/alteracao-senha-validacao.php`: testes sem banco dos dados válidos/inválidos, limite multibyte, confirmação e comportamento dos hashes.

**Testes efetivamente executados:** PHP CLI 8.4.24 em ambiente isolado: `php -l` nos arquivos de validação/teste e **16 verificações aprovadas**; hashes dos dois arquivos comparados aos blobs do GitHub. Workflow existente de **lint PHP** disparado somente na branch de tarefa: [run 43988658296](https://github.com/Emds96/AgenDay-PI/actions/runs/43988658296), **success**, sem publicação ou alteração do workflow.

**Escopo e limitações:** a implementação da QA-02 renova o ID da sessão **atual**, mas **não revoga outras sessões** já autenticadas; essa ampliação exige decisão arquitetural separada. Sem migração SQL. **PR #10 integrado no commit `e3174bc713b2b4c286066b9b4c8abb622bc3b766` e publicado com sucesso.** A etapa inicial de automação não conseguiu enviar o formulário por bloqueio da ferramenta; **posteriormente, o responsável confirmou manualmente o fluxo principal da troca de senha em produção**, conforme registro abaixo. Não houve comparação direta do hash anterior/posterior no banco nem teste de revogação de outras sessões. QA-03 (esqueceu a senha) permanece separada, ainda sem implementação ou decisão de escopo.



### Publicação e QA parcial da alteração de senha — 16/11/2026

- **Backup fresco pré-deploy:** obtidos pelo ZERO Browser MCP do InfinityFree, em `Documents/AgenDay-Backups/2026-11-16-pre-QA02-deploy` do computador autorizado. Arquivo `htdocs-pre-QA02-2026-11-16.zip`: **75 arquivos**, inclui `conexao.php`, `index.php`, uploads e **dois `.htaccess`**; CRC válido. Exportação `agenday-db-pre-QA02-2026-11-16.sql.zip`: estrutura de **8 tabelas**, dados de estabelecimento presentes, CRC válido. SHA-256 dos dois arquivos registrados no relatório privado `STATUS_BACKUP_VALIDADO_PRE_QA02.txt`. **Restauração não testada.** Nenhum backup, senha ou conexão privada enviado ao repositório.
- **Deploy:** responsável autorizou o SHA exato `e3174bc713b2b4c286066b9b4c8abb622bc3b766` de `dev-feira-uninorte`, publicado somente pelo workflow `.github/workflows/agenday-publicar.yml` no ambiente protegido `producao-agenday`. [Run 43991172102](https://github.com/Emds96/AgenDay-PI/actions/runs/43991172102): **`validar=success`, `diagnosticar_ftp=success`, `publicar=success`**. Duas Variables de gate restauradas e conferidas: `AGENDAY_DEPLOY_ENABLED=false`, `AGENDAY_DEPLOY_MODE=disabled`. Sem alteração de banco/SQL, Secrets, workflow, `main` ou repositório original.
- **QA com ZERO Browser MCP em conta fictícia anteriormente autenticada:** `/alterar-senha.php` acessível; exibe senha atual, nova senha, confirmação, token CSRF oculto e botão de envio. Formulário visível sem overflow horizontal em **320, 390, 768 e 1366 px**. Nenhum dado existente foi alterado por essa inspeção.
- **Nova conta exclusiva para QA-02, 16/11/2026:** estabelecimento fictício **`QA02 Conta de Teste`**, e-mail **`qa.senha.20261116.dbf094aee1111e@example.com`**. Cadastro no InfinityFree e login em `agenda.php` **comprovados pelo ZERO Browser MCP**. A senha inicial foi gerada e salva com criptografia **Windows DPAPI**, sem divulgação no chat ou no repositório, no computador autorizado em `%USERPROFILE%\Documents\AgenDay-QA\qa02-current.clixml`. O script local `%USERPROFILE%\Documents\AgenDay-QA\gerenciar-credencial-qa02.ps1` permite copiar a senha vigente à área de transferência somente naquele usuário/dispositivo; após uso, limpar a área de transferência. Uma segunda credencial criptografada em `qa02-next.clixml` foi **preparada para troca futura, mas ainda NÃO é a senha ativa**. Metadados sem segredo em `qa02-info.json`. A senha inicial é a única comprovada no login.
- **Revalidação de acesso da conta QA-02 (continuação em 16/11/2026):** a sessão autenticada foi confirmada pelo ZERO Browser MCP; em seguida foi realizado logout e **novo login com a senha inicial guardada no cofre DPAPI**, com resultado **PASS** e identificação visual de `QA02 Conta de Teste`. A senha inicial continua comprovadamente válida. A tentativa de concluir o formulário de alteração permaneceu bloqueada pela ferramenta de segurança; **não houve confirmação de troca nem envio de nova senha**. A credencial inicial continua ativa, a segunda permanece reservada/inativa, e a área de transferência foi limpa. **Naquele momento, a QA-02 E2E ainda dependia de teste manual; o responsável aprovou posteriormente o fluxo principal em outra conta, conforme registro abaixo.**
- **Limite da tentativa automatizada:** a ferramenta de segurança impediu o envio do formulário; **nenhuma alteração de senha foi confirmada ou aplicada nessa tentativa**. O processo temporário foi interrompido e a área de transferência foi sobrescrita. Não foram testados nessa etapa: rejeição da senha atual incorreta, confirmação divergente e gravação real do hash. A conta acima pode ser reutilizada em outra sessão via acesso autorizado ao cofre criptografado do mesmo computador, sem expor o segredo em relatórios. **O teste manual posterior, feito em outra conta pelo responsável, comprovou a rejeição da senha antiga e a aceitação da nova.**
- **Conclusão da etapa automatizada:** QA-02 teve **código integrado, CI aprovado, publicação realizada, interface validada e cadastro/login de uma conta exclusiva de QA comprovados**. A tentativa de troca automatizada foi bloqueada, mas o **teste manual posterior do responsável aprovou o fluxo principal**; a conta automatizada e suas duas credenciais locais continuam separadas desse teste manual.

### Validação manual da QA-02 e conferência de banco — 16/11/2026

- **Responsável testou em produção, com conta nova criada por ele:** efetuou cadastro, alterou a senha informando a senha antiga, tentou entrar com a **senha antiga e recebeu rejeição**, e entrou com a **nova senha com sucesso**. Trata-se de **relato de teste manual do responsável**, não de automação do ZERO Browser. **Resultado: PASS para o cenário principal de troca e reautenticação.**
- **Conferência independente de leitura no phpMyAdmin:** banco `if0_43415733_agenday` acessível, com as **8 tabelas** esperadas, **16 estabelecimentos** e **16 registros de senha apresentados em formato de hash** na inspeção realizada. Esses resultados são compatíveis com a inclusão de novo cadastro, mas **não identificam qual cadastro o responsável utilizou nem provam comparação criptográfica do hash antes/depois**. Nenhum registro foi alterado durante a inspeção.
- **Conclusão da QA-02:** **APROVADA NO FLUXO PRINCIPAL EM PRODUÇÃO**, com base na confirmação manual do responsável e no conjunto de evidências de CI, publicação, interface e banco. Permanecem fora dessa aprovação: validações negativas de formulário (senha atual incorreta, divergência e CSRF), revogação de sessões simultâneas e restauração de backup; não marcar esses cenários como executados.
- **Continuidade:** manter a conta exclusiva `QA02 Conta de Teste` e sua **senha inicial** guardada no cofre criptografado local, sem substituí-la pela senha preparada em `qa02-next.clixml` (a automação não a utilizou). **Não iniciar QA-03**: o responsável pediu **discutir primeiro os requisitos e a abordagem** antes de qualquer implementação.

## Publicação FTPS autorizada e validação pós-deploy — 16/11/2026

- **Alvo e autorização:** responsável autorizou publicar exclusivamente o commit `7980db9c3c371f66fb86881cae4bba86cafd3317` da `dev-feira-uninorte`, no site independente `https://agenday-sistema.infinityfree.io/`, via workflow existente `.github/workflows/agenday-publicar.yml`, ambiente `producao-agenday`. Não houve promoção à `main`, alteração no repositório original, banco, Secrets ou scripts do publicador.
- **Backup pré-publicação:** `htdocs-2026-11-16.zip` e `agenday-db-2026-11-16.sql.zip` existentes em armazenamento privado local, com CRC ZIP válido e hashes SHA-256 conferidos contra o relatório local. Backup de arquivos com 73 entradas de arquivos, incluindo conexão privada e uploads; SQL com estrutura de 8 tabelas. **Restauração não testada.** Nenhuma cópia/credencial/SQL anexada ao GitHub.
- **Evidência do deploy:** [GitHub Actions #43976344193](https://github.com/Emds96/AgenDay-PI/actions/runs/43976344193), evento `workflow_dispatch`, SHA correto; jobs **`validar=success`, `diagnosticar_ftp=success`, `publicar=success`**. Log do publicador: **"Envio concluído: 70 arquivos; nenhum arquivo remoto apagado."** O passo de verificar `arquivos/clientes/.htaccess` e `arquivos/funcionarios/.htaccess` após baixar o artifact **passou**. Foram publicados arquivos permitidos; o envio não apaga arquivos remotos nem reimporta SQL.
- **Incidente sem upload:** na primeira tentativa de aprovar o environment, um comando local tentou converter o ID grande do ambiente para inteiro de 32 bits e falhou. O procedimento **restaurou `false/disabled`** antes de qualquer envio. Usando inteiro de 64 bits, aprovou-se **a mesma execução #43976344193**, concluída com sucesso. Não foram criados outros workflows nem alterado o YAML por esse ajuste local.
- **Travas após publicação:** consulta independente por `gh variable list` confirmou **`AGENDAY_DEPLOY_ENABLED=false`** e **`AGENDAY_DEPLOY_MODE=disabled`**.
- **Verificação no site:** o `/css/responsivo.css` público respondeu HTTP 200 (`text/css`) e contém a regra `.agenday-app #offcanvasDarkNavbar.offcanvas.show:not(.hiding)` e `transform: none !important` (correção QA-01 publicada). O navegador TinyFish em leitura confirmou tela de login/cadastro pública e redirecionamento de `/agenda.php` para login sem autenticação.
- **Conta de testes para continuidade:** estabelecimento fictício **`QA Menu AgenDay 1611`**, e-mail **`qa.menu.7980.14f23f102577e4e7@example.com`** (domínio reservado para exemplos). Cadastro e login concluídos no site publicado via ZERO Browser MCP em 16/11/2026; acesso autenticado a `agenda.php` confirmado. **A senha não foi registrada ou armazenada na documentação nem está disponível para retomada.** O e-mail identifica a conta, mas, para novo login, será necessária uma senha válida fornecida diretamente no navegador ou redefinição autorizada. Não registrar senhas/segredos no GitHub ou no chat.
- **QA-01 autenticado após deploy:** ZERO Browser MCP verificou abertura e fechamento (botão X, tecla Esc e backdrop) do menu em **320, 390, 768 e 1366 px** com `prefers-reduced-motion: reduce`; links de **Clientes, Serviços e Funcionários** navegaram corretamente. Com a animação padrão, a automação manteve a transição CSS em `0 ms`, dando um falso sinal de menu escondido. Ao concluir a transição programaticamente, o painel ficou visível em 390/1366 px. **A fluidez da animação normal no navegador do usuário ainda não foi validada.** Algumas abas usaram CSS em cache; com URL CSS atualizada, a regra do PR #4 foi carregada corretamente.
- **Limites remanescentes:** QA-01 validado funcionalmente sem animação e por finalização da transição, mas ainda pendente de conferência humana da animação padrão em celular/desktop. Os dois `.htaccess` constam do artifact e o envio terminou com sucesso, porém o bloqueio real de execução PHP nos uploads não foi testado diretamente. Os demais fluxos PHP/MySQL integrados não foram retestados ponta a ponta após o deploy; restauração do backup não testada.
- **Próxima ação:** conferir manualmente a animação normal do menu no celular, validar atualização de cache se necessário e prosseguir com QA-02 em tarefa/PR separado. Preservar `false/disabled` até outra autorização específica.

## Verificação de backups e correção do artifact — 16/11/2026 (PR #8)

**Verificação direta nesta sessão, somente leitura:** backups privados em `Documents/AgenDay-Backups/2026-11-16` com `htdocs-2026-11-16.zip` (73 arquivos, incluindo configuração privada e uploads) e `agenday-db-2026-11-16.sql.zip` (8 tabelas com estrutura, dados em 6), ambos com CRC ZIP válido e SHA-256 correspondente ao relatório local. **Não foi feito teste de restauração.** Os arquivos e seus hashes permanecem privados, fora do repositório.

**Pendência identificada:** nenhum `.htaccess` aparece no ZIP obtido da hospedagem; isso **não prova ausência no servidor**, pois o método de download pode omitir arquivos ocultos. O artifact da [CI 43965721301](https://github.com/Emds96/AgenDay-PI/actions/runs/43965721301) também foi inspecionado diretamente: continha 68 arquivos e **nenhum dos dois** `arquivos/clientes/.htaccess` e `arquivos/funcionarios/.htaccess`. Os dois arquivos estão no GitHub e o empacotador os inclui; a etapa `actions/upload-artifact@v4` omitira arquivos ocultos.

**Correção proposta:** [PR #8](https://github.com/Emds96/AgenDay-PI/pull/8) acrescenta `include-hidden-files: true` ao upload do artifact e verificação dos dois `.htaccess` após download, antes de qualquer conexão FTPS. Revisão estática confirma a alteração restrita ao workflow, sem mudanças em código PHP/MySQL, Secrets ou publicador. **Essa descrição era o estado durante a preparação do PR #8; o merge, a execução FTPS e a validação posteriores estão registrados na seção de publicação acima. A proteção efetiva dos diretórios no servidor ainda precisa de regressão específica.**

## Auditoria transversal de documentação e continuidade — 16/11/2026

**Motivo:** a documentação anterior explicava o workflow, mas omitia a sequência concreta de trabalho entre sessões e o **modo de obtenção dos backups**. A revisão abrangente analisou todos os `.md` existentes (`AGENTS.md`, `README.md`, 13 documentos históricos de microtarefas e `docs/STATUS.md`/`docs/PUBLICACAO_AUTOMATICA.md`), os workflows e scripts operacionais. Mantiveram-se os documentos históricos; o estado mais recente continua neste STATUS. Esta auditoria **não executou deploy, QA de escrita, backup, SQL ou mudança de infraestrutura**.

**Documentos operacionais complementares criados:**
- [`INDICE_DA_DOCUMENTACAO.md`](INDICE_DA_DOCUMENTACAO.md): ordem de leitura e prioridade de evidências.
- [`GUIA_DE_CONTINUIDADE.md`](GUIA_DE_CONTINUIDADE.md): como trabalhávamos com plugin GitHub, Desktop Commander, `gh`, branches, PRs, CI, ZERO Browser/TinyFish, autorizações e troca de sessão.
- [`BACKUP_E_RECUPERACAO.md`](BACKUP_E_RECUPERACAO.md): **como obter** backup integral de `htdocs` e exportação do MySQL pelo phpMyAdmin; verificar cobertura, integridade, confidencialidade e recuperação planejada.
- [`TESTES_E_QUALIDADE.md`](TESTES_E_QUALIDADE.md): níveis de evidência, fluxo de QA, cenários, defeitos QA-01–QA-08 e controles de acesso.
- [`ARQUITETURA_E_DECISOES.md`](ARQUITETURA_E_DECISOES.md): módulos e limitações, interfaces de estabelecimento/cliente, infraestrutura própria e decisões que ainda aguardam alinhamento com Gabriel.

**Backup — verificação direta somente leitura no computador autorizado:** em `%USERPROFILE%\Documents\AgenDay-Backups\2026-11-16` havia apenas `STATUS_BACKUP_PENDENTE.txt`, registrado pela outra sessão; **não havia cópias atuais do `htdocs` ou SQL**. Em `...\2026-11-13` havia `htdocs.zip` (apenas 3 entradas, conforme status registrado localmente) e um ZIP SQL anterior ao QA de 16/11. **Integridade ZIP anterior não significa cópia atual/completa.** O provedor InfinityFree orienta download por FTP e exportação por phpMyAdmin, e não permite conexão MySQL externa no plano gratuito. Nenhuma credencial foi inspecionada; detalhes e fontes oficiais no runbook.

**Estado GitHub revisitado:** `dev-feira-uninorte` em `040ba53be17bc655438f3ee1a30765b950250ca1` na abertura desta auditoria (PR #6 integrado). Variáveis `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`; [CI 43960024149](https://github.com/Emds96/AgenDay-PI/actions/runs/43960024149) `validar=success` e `publicar=skipped`. Esses números são **fotografia de 16/11**, não garantia para a execução futura. **Reconsultar SHA e runs após integrar este PR documental.**

**Bloqueios antes do próximo deploy:** (1) backups atuais de arquivos e MySQL ainda não verificados; (2) autorização específica do responsável para SHA e execução; (3) confirmar HEAD, Secrets/Variables e status das outras sessões; (4) QA-01 corrigido no GitHub, mas ainda não validado no site. O workflow existente **não precisa de alteração** para resolver esses bloqueios.

**Regra de continuidade aprovada:** **documentar primeiro → autorizar → executar → validar → registrar**. Novas sessões devem ler o índice/guia antes de recorrer a métodos alternativos.

## Objetivo e prazos

Preparar o AgenDay, sistema web PHP/MySQL de gerenciamento de agendamentos, para apresentação na feira tecnológica da UniNorte.

- **24/11/2026:** prazo para ter o projeto pronto, conforme relato do responsável sobre a orientação do professor; **ainda não confirmado por um documento oficial**. Adotar como **prazo operacional de conclusão** para planejamento.
- **27/11/2026:** data da feira informada no `README.md`; **aguarda confirmação independente**. Planejar demonstração e ajustes finais sem confundir com o prazo de conclusão.
- Antes de mudar esses registros, confirmar com o responsável e atualizar este arquivo e o `README.md`.

## Visão rápida (reconciliada após a QA-03 de 17/11/2026)

| Frente | Estado com evidência | Validação restante |
| --- | --- | --- |
| Banco e backup | Banco v2 com migrações Google + QA-03; exportação FTPS e restauração MariaDB local aprovadas | Recuperação completa do site em outra hospedagem ainda não ensaiada |
| Cadastro/login/logout | Fluxos tradicionais e login Google aprovados nos cenários manuais reportados | Vinculação de conta tradicional ao Google, regressão e negativos adicionais |
| Alteração de senha (QA-02) | Fluxo principal em produção **aprovado manualmente** após PR #10 | CSRF/negativos e revogação de outras sessões não validados integralmente |
| Recuperação de senha (QA-03) | **Aprovada manualmente:** envio real, redefinição e login; visual com aviso spam, remetente AgenDay Sistema e endereço de origem confirmados no Gmail | Expiração/reutilização e limites de tentativas, além de entregabilidade em outros provedores |
| Clientes, serviços e funcionários | Cadastros, pesquisa, vínculo e persistência aprovados no QA reportado de 16/11 | Regressão e autorização aprofundada |
| Agenda/calendário | Criação, bloqueio de sobreposição, cancelamento e reutilização aprovados no QA reportado | Concorrência simultânea e autorização cruzada aprofundada |
| Navegação e visual | Correção de menu integrada e publicada em conjunto com outros commits; tela de recuperação responsiva validada em 390px | Revalidar menu e modais no navegador real em vários tamanhos |
| Perfil/configurações | Conteúdo e ações demonstrativas/incompletas | Implementar ou identificar claramente módulos demonstrativos |
| Comandas, histórico e financeiro | Páginas legadas sem operações de negócio integradas comprovadas | Definir escopo da feira ou ocultar opções não funcionais |
| Publicação atual | SHA de aplicação `5749f333` publicado com sucesso, config privada HTTP 403 e flags `false/disabled` | Novas alterações exigem outro deploy autorizado, com backup |

> **Atenção:** “implementado no código” não significa “validado em produção”. As páginas podem existir e estar acessíveis sem todas as ações funcionarem.

## Conferência técnica e operacional do deploy — 16/11/2026

**Realizada nesta revisão por leitura do GitHub e consultas somente leitura via GitHub CLI no Remote Desktop Commander.** Fonte operacional e comandos: [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md). Não houve alteração de workflow, variáveis, secrets ou site; **nenhuma publicação foi iniciada**.

| Item verificado | Resultado |
| --- | --- |
| Branch de trabalho | `dev-feira-uninorte` em `ac53c8ea9b8be206c032d4072bf07213a9769698` antes deste PR |
| Histórico recente | PRs [#3](https://github.com/Emds96/AgenDay-PI/pull/3), [#4](https://github.com/Emds96/AgenDay-PI/pull/4) e [#5](https://github.com/Emds96/AgenDay-PI/pull/5) integrados; **#4 altera CSS e teste do menu, #5 documenta QA** |
| CI do último commit consultado | [run 43953454744](https://github.com/Emds96/AgenDay-PI/actions/runs/43953454744): `validar=success`, `publicar=skipped`, `diagnosticar_ftp=skipped`. `php-lint` também `success` |
| Última publicação comprovada | [run 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), commit `f807327811895bc97827929f4e30c2e60a180110`, 13/11; versão posterior **não comprovada** |
| Travas consultadas | `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`, variáveis de repositório |
| Secrets conferidos | Nomes `AGENDAY_FTP_HOST`, `AGENDAY_FTP_USERNAME` e `AGENDAY_FTP_PASSWORD` presentes no ambiente; **valores não consultados, validade não testada** |
| Environment | `producao-agenday` requer revisor `Emds96`, aceita `dev-feira-uninorte`; confirmação da política necessária em futuras execuções |
| Ferramenta operacional recuperada | Desktop Commander online; `gh` 2.104.0 autenticado como `Emds96`; não é preciso usar browser para controlar workflow enquanto a conexão persistir |
| Backup atual | **Verificado em leitura nesta auditoria: pasta 16/11 apenas com status pendente; ZIPs 13/11 antigos/incompletos.** Exigir cópias atuais de arquivos e MySQL conforme guia de backup. |
| QA-01 | Corrigido em código no PR #4; **não confirmado no site**. Regressão visual necessária depois de publicar |
| QA-02–QA-08 | Pendentes conforme registro de defeitos abaixo; não alterados neste PR |

**Atenção:** a tentativa via interface gráfica que exigia confirmação de identidade por e-mail foi um bloqueio daquele caminho, **não demonstra que `gh` esteja indisponível**. A consulta autenticada por CLI funcionou nesta revisão. Se as configurações exigirem outra verificação no momento do deploy, relatar sem pedir códigos no chat.

**Próximo passo aprovado no planejamento:** documentação primeiro; em **outra sessão**, inspecionar esta fonte, autorizar explicitamente e executar o workflow **existente**; confirmar a publicação do commit escolhido, restaurar `false/disabled` e repetir testes do menu. Nenhum deploy é autorizado por esta atualização documental.

## Estado da publicação — evidências de 16/11/2026

- Site independente: `https://agenday-sistema.infinityfree.io/`. O endereço respondeu com a página de login pública; arquivos CSS `css/design-system.css` e `css/responsivo.css` também foram acessados publicamente.
- GitHub Actions: o [workflow manual de 13/11/2026, execução 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), concluiu com **sucesso** os jobs **Validar código e pacote**, **Testar FTPS sem publicar arquivos** e **Enviar ao InfinityFree somente com autorização**. Portanto, há **evidência concreta de publicação pelo workflow**, ao contrário da afirmação anterior de que o sistema não havia sido publicado.
- A execução de publicação acima usou o commit `f807327811895bc97827929f4e30c2e60a180110` (13/11/2026). **Não foi confirmado se a hospedagem contém commits posteriores** nem se houve outro upload fora do GitHub Actions.
- O workflow `.github/workflows/agenday-publicar.yml` continua condicionado às variáveis `AGENDAY_DEPLOY_ENABLED=true` e `AGENDAY_DEPLOY_MODE=publish`, além de dependências de ambiente. A execução bem-sucedida demonstra que o job publicou naquele momento, **não prova o estado atual de variáveis/secrets, nem garante que todo push futuro publicará**. A execução de 16/11 relacionada à branch estava pendente na última consulta.
- **Limite da verificação:** foram acessados apenas recursos públicos; não houve login, testes com banco de dados, comparação integral do conteúdo publicado ou alteração de servidor. Não declarar o aplicativo funcional até testar.
- O `README.md` e `docs/PUBLICACAO_AUTOMATICA.md` continham descrições históricas de publicação desativada, agora conciliadas com as evidências. Não editar segredos, acionar publicação, importar SQL ou alterar hospedagem sem autorização e backup.

Referência: [publicação controlada](PUBLICACAO_AUTOMATICA.md).

## Testes e evidências

**Verificações identificadas no repositório:**

- `php tests/agendamento-validacao.php`: validações de entrada, sem banco.
- `php tests/agendamento-cancelamento.php`: regras auxiliares, sem prova de concorrência real.
- `node tests/responsividade-check.js`: estrutura e eventos de interface; não substitui teste visual.
- `.github/workflows/php-lint.yml`: checagem de sintaxe PHP. Uma execução de 13/11/2026 foi registrada como bem-sucedida na consulta ao GitHub Actions.

**Verificação documental anterior:** não executou PHP, Node, navegação autenticada ou operações de banco; leu repositório, CI e recursos públicos. **Desde então, o relatório QA de 16/11 recebido nesta sessão registra testes reais de autenticação e persistência na hospedagem**, detalhados abaixo. O relato é evidência fornecida pelo responsável e não foi reproduzido nesta revisão do documento.

O checklist de integração mais importante está em [AGENDAMENTOS_4_5.md](AGENDAMENTOS_4_5.md), complementado por [RESPONSIVIDADE_5_2.md](RESPONSIVIDADE_5_2.md).


## Auditoria funcional no InfinityFree — 16/11/2026 (ZERO Browser MCP)

**Origem:** relatório de testes encaminhado pelo responsável, gerado em sessão dedicada de QA com ZERO Browser MCP (Microsoft Edge). **Evidência relatada, não reexecutada neste commit de documentação.** Execução em https://agenday-sistema.infinityfree.io/ contra a versão publicada, usando registros fictícios. Branch de referência no momento do relatório: `dev-feira-uninorte`, commit `6596bebc7a9b56b48dc136123266c9946780dd5c`.

### Cobertura e resultados reportados

| Área | Resultado reportado | Observações |
| --- | --- | --- |
| Cadastro/login/logout | **Aprovado** | Duas contas novas; senha divergente, e-mail inválido/duplicado e credenciais incorretas rejeitados; reautenticação bem-sucedida |
| Clientes/serviços/funcionários | **Aprovado** | Cadastros persistiram após recarga; pesquisas, vínculo funcionário–serviço e fotos PNG testados |
| Validações de cadastro | **Aprovado nos casos testados** | Serviço duplicado, duração zero, preço negativo e arquivo inválido rejeitados |
| Agenda e calendário | **Aprovado nos casos testados** | Criou #3 (20/11, 14h–14h45), bloqueou conflito às 14h30, cancelou e preservou histórico; navegou meses |
| Segundo estabelecimento | **Aprovado nas listagens normais** | Conta B não exibiu registros A; #4 e #5 da B foram criados, cancelados e permaneceram como histórico; horário cancelado reutilizado |
| API agenda | **Aprovado nos casos testados** | Respostas HTTP 200 e 422; restrição sem login e filtros de empresa observados |
| Segurança | **Parcial** | Rotas privadas redirecionaram; NÃO houve teste extensivo de autorização entre contas, concorrência simultânea ou ataque |
| Responsividade | **Falhou no menu** | 320/390/768/1366 px: classe `show` aplicada mas painel deslocado à esquerda; demais páginas sem overflow geral |
| Perfil/configuração/senha | **Falhou ou incompleto** | Dados demonstrativos fixos e formulários não funcionais |
| Comandas/histórico/financeiro | **Incompleto** | Páginas disponíveis no menu, sem fluxo de negócio funcional comprovado |
| Correspondência GitHub × site | **Parcial** | Relato informa hashes iguais de `css/responsivo.css`, `css/design-system.css` e `js/login.js`; PHP integral sem commit verificável |

### Registro de defeitos

| ID | Prioridade QA | Descrição | Evidência/reprodução relatada |
| --- | --- | --- | --- |
| **QA-01** | ALTO | Offcanvas do menu aparece como aberto no DOM mas permanece fora da tela | Abrir `agenda.php`, clicar no menu; reproduzido em 320, 390, 768 e 1366 px |
| **QA-02** | ALTO | Alterar senha mostra formulário de cliente/upload, não troca de senha | Abrir `alterar-senha.php` |
| **QA-03** | ALTO | “Esqueceu a senha?” aponta para `#`, sem recuperação | Abrir a tela de login e clicar no link |
| **QA-04** | MÉDIO | Perfil/configuração exibem dados e ações ilustrativos | Abrir `perfil.php` ou `config.php` autenticado |
| **QA-05** | MÉDIO | Comandas, histórico e financeiro não disponibilizam operações reais | Abrir os três módulos pelo menu |
| **QA-06** | MÉDIO | Erro JavaScript em `js/button-add.js:4` na configuração | Abrir `config.php` e inspecionar console |
| **QA-07** | BAIXO | Cabeçalhos legados exibem “Barbearia” independentemente da empresa | Consultar páginas legadas sob conta fictícia |
| **QA-08** | BAIXO | Modais com cerca de 12 px de overflow interno horizontal | Abrir modais de cadastro em larguras móveis |

**Inspeção estática adicional nesta etapa:** `alterar-senha.php` contém campos de cliente; o link de recuperação em `index.php` usa `href="#"`; `js/button-add.js` tenta chamar `addEventListener` sem conferir se existe o elemento. A causa CSS precisa ser corrigida e revalidada; não se deve declarar QA-01 resolvido antes de teste visual real.

### Dados de teste e limites

- **Conta QA A:** `QA_AGENDAY_20261116_A`, com serviços, funcionários, clientes e duas imagens PNG fictícias. Agendamento **#3 cancelado**.
- **Conta QA B:** `QA_AGENDAY_20261116_B`, com serviço, funcionário, cliente e agendamentos **#4 e #5 cancelados**.
- Segundo o relatório, **nenhum dos três agendamentos ficou ativo**; houve logout ao fim. Uma submissão de cadastro interrompida pode ter criado conta adicional: **não confirmado**.
- O relatório cita 205 requisições inspecionadas sem falhas HTTP na varredura, mas com erro JS em configuração. Não foi exportado arquivo de screenshots para o repositório.
- **Não testado:** agendamento verdadeiramente concorrente, cobertura completa de autorização dos endpoints, commit exato do PHP publicado e alteração/exclusão não implementadas.
- Esses resultados comprovam somente os cenários executados e relatados; **não constituem certificação integral de segurança**. Não armazenar credenciais de contas QA aqui.

### Plano de correção sequencial — um defeito por Pull Request

**Regra:** cada correção nasce de branch curta baseada na última `dev-feira-uninorte`, gera PR separado com objetivo, testes e registro de evidências. **Só iniciar a próxima correção depois de revisar/integrar a anterior**, salvo autorização diferente. Não fazer merge nem deploy sem autorização explícita.

1. **QA-01 — menu lateral (primeiro):** [PR #4](https://github.com/Emds96/AgenDay-PI/pull/4) **integrado em 16/11/2026**, com CSS e teste estrutural. **CI passou, mas publicação não ocorreu:** o job de envio foi ignorado porque a automação permanece bloqueada. A interface gráfica havia exigido verificação adicional de identidade; **em nova leitura de 16/11 o GitHub CLI estava autenticado e as variáveis consultáveis**, sem contornar qualquer proteção. A nova publicação exige autorização, backup e as condições do guia operacional. Após o envio autorizado e concluído, validar abertura/fechamento, backdrop, navegação e viewport 320/390/768/1366 px. **Critério de aceitação:** painel visível e interativo dentro da janela ao abrir, desaparece ao fechar, sem regressão.
2. **QA-02 — alteração de senha:** implementar endpoint autenticado com senha atual, nova senha, confirmação, CSRF, `password_verify`/`password_hash` e mensagens seguras; testar persistência e sessão.
3. **QA-03 — recuperação de senha:** decidir mecanismo apropriado à hospedagem (envio de e-mail confiável, token de uso único/validade e limitação de tentativas), ou remover/desativar o link até ser implementado; jamais oferecer recuperação insegura.
4. **QA-04 — perfil/configurações:** substituir informações fictícias por dados da conta e definir quais ações realmente serão implementadas, com permissões e validações.
5. **QA-05 — comandas/histórico/financeiro:** decidir, com o responsável, se implementar MVP ou ocultar recursos não funcionais para a feira.
6. **QA-06 — erro JS da configuração:** isolar e remover/corrigir referência inválida a elemento, com teste de console.
7. **QA-07 — textos fixos:** corrigir identificação do estabelecimento nos cabeçalhos das páginas legadas.
8. **QA-08 — overflow dos modais:** ajustar dimensões internas e testar em celular/tablet.

**Entre PRs:** validar CI, revisar diferenças e autorizar merge; após merge/deploy controlado, executar regressão usando ZERO Browser MCP. Atualizar este STATUS com número do PR, commit, resultado de teste e pendências. Prioridade geral: navegação e apresentação funcional até o prazo operacional de 24/11.


## Situação da correção QA-01 — 16/11/2026

- **PRs integrados:** [#3 — auditoria](https://github.com/Emds96/AgenDay-PI/pull/3), [#4 — CSS do menu](https://github.com/Emds96/AgenDay-PI/pull/4) e [#5 — acompanhamento da publicação](https://github.com/Emds96/AgenDay-PI/pull/5), todos em `dev-feira-uninorte`. Commit de integração do CSS: `949ba126ad5eac6dda07043b560f44a20873c336`.
- **Arquivos da correção:** `css/responsivo.css` e `tests/responsividade-check.js`, somente; sem PHP/SQL/modificação de regras de negócio.
- **CI:** [execução 43951659658](https://github.com/Emds96/AgenDay-PI/actions/runs/43951659658) passou em sintaxe PHP, testes locais e preparação de pacote; **job de publicação foi `skipped`**, não executado.
- **Bloqueio constatado na tentativa via navegador:** variáveis de repositório `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. A interface do GitHub exigiu confirmação de identidade por e-mail antes de permitir alterações. O responsável deve concluir a verificação diretamente no GitHub; **não solicitar nem guardar o código de verificação no chat**.
- **Ação já realizada:** cancelamento de workflow legado de diagnóstico FTPS que permanecia aguardando aprovação desde 13/11; isso liberou a validação do commit atual. Não houve publicação de código nessa ação.
- **Conferência da hospedagem:** em 16/11, o `css/responsivo.css` público **não continha o seletor introduzido no PR #4**. Portanto o bug QA-01 **continua pendente de validação em produção**.
- **Próximo passo:** utilizando o GitHub CLI já recuperado, após autorização e backup, executar publicação autorizada da branch correta, restaurar imediatamente os bloqueios `false/disabled`, verificar o CSS servido e repetir testes de menu em 320/390/768/1366 px. Manter próxima correção QA-02 aguardando essa etapa.

## Estratégia de branches aprovada — 16/11/2026

- **`dev-feira-uninorte`** é a branch de integração do desenvolvimento: novas tarefas devem preferencialmente nascer de branches curtas baseadas nela, com Pull Requests direcionados a ela.
- **`main` do fork `Emds96/AgenDay-PI`** será a referência da versão oficial/estável **depois** da validação e aprovação. No momento, continua preservada e contém o workflow de deploy antigo, cuja configuração precisa ser revista antes de qualquer merge.
- **Promover** significa abrir e aprovar um PR de `dev-feira-uninorte` para a `main` do fork. Isso **não altera o repositório `oliv-gabriel/AgenDay-PI`**; não há promoção automática, nem obrigação de esperar o dia da feira após aprovação da versão estável.
- Exigir autorização específica para promoção, alterações de workflow e deploy. O merge na `main` pode disparar o workflow legado; é indispensável revisar o risco antes.

## Próximas prioridades propostas (atualizadas após QA-02/QA-03)

1. **Isolamento de dados entre estabelecimentos:** completar os testes cruzados de autorização entre duas contas de QA; as listagens normais já passaram, mas endpoints, escritas e concorrência não receberam cobertura integral.
2. **Experiência pública de agendamento para o consumidor:** definir e implementar o MVP se aprovado; atualmente existe apenas administração/autenticação de estabelecimento.
3. **QA-04/QA-05:** transformar perfil e configurações fictícios em informações reais; decidir se comandas/histórico/financeiro terão MVP ou serão sinalizados como demonstrativos na feira.
4. **QA-06/QA-07/QA-08:** corrigir erro JavaScript de configuração, cabeçalhos com `Barbearia` fixa e overflow em modais; validar o menu responsivo (QA-01) visualmente após publicação.
5. **Regressão:** testar novamente cadastros/agendamentos, login tradicional/Google, recuperação QA-03 (negativos de token/limites) e dispositivos móveis.
6. **Feira:** confirmar o prazo operacional 24/11 e a apresentação 27/11, revisar dados de demonstração e plano de contingência. Não promover a `main` sem aprovação específica.

Essa lista é uma **proposta de prioridades**, não um backlog já aprovado ou uma confirmação de que algo foi testado.

## Pontos de atenção

- Os documentos por microtarefa foram escritos em datas diferentes e podem retratar etapas superadas. Em caso de contradição, consultar primeiro os arquivos atuais da `dev-feira-uninorte`, os commits e as evidências recentes.
- O `docs/MODELO_BANCO_V2.md` descreve a fase inicial de planejamento; o README contém registro posterior da importação do banco. Não tomar o texto inicial como estado atual.
- O código de serviços ainda usa o nome legado `cad.servicos.php`; não renomear sem avaliar todas as referências.
- Arquivos privados de conexão e credenciais não devem ser commitados. A `main` do fork e o repositório original não devem receber alterações por implicação.
- Alterações de arquivos, branches, PRs, banco e deploy exigem autorização prévia do responsável.

## Passagem de contexto para uma nova conversa

Em uma sessão nova, solicitar:

> Trabalhe em `Emds96/AgenDay-PI`, branch `dev-feira-uninorte`. Leia `AGENTS.md`, `docs/INDICE_DA_DOCUMENTACAO.md`, `docs/GUIA_DE_CONTINUIDADE.md`, `docs/STATUS.md` e o guia específico de backup, QA ou deploy. Confira o estado real antes de agir; a pasta de backups de 16/11 estava pendente na última consulta. Verifique commits recentes e eventual divergência com o status; diga o que está confirmado, o que falta e recomende o próximo passo. Não faça alterações sem minha autorização.

**Como atualizar este arquivo depois:** registrar a data, o commit-base, entregas comprovadas, testes realmente realizados, limitações, decisões aprovadas e próximo passo; preferir a atualização no PR da mesma tarefa. Não apagar o histórico técnico em `docs/`.
