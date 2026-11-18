# Login automático de contas QA — DeskBrowse (18/11/2026)

**Sistema:** AgenDay 2.0 — https://agenday-sistema.infinityfree.io/  
**Ambiente:** Windows + Microsoft Edge + plugin DeskBrowse.  
**Escopo:** autenticação de duas contas fictícias de estabelecimento, sem senhas no chat ou repositório.  
**Estado:** **validado em produção no fluxo A → logout → B**, no navegador da ferramenta em 18/11/2026. Não é auditoria integral de autorização entre estabelecimentos.

## Objetivo

Permitir que uma sessão futura de ChatGPT acesse o AgenDay usando apenas o identificador de uma conta previamente cadastrada no **cofre local do Windows**, sem solicitar, ler, imprimir ou registrar e-mail/senha. O cofre e a implementação de `login_salvo` são recursos **externos ao repositório AgenDay**. Os arquivos de credenciais não devem ser copiados nem versionados aqui.

## Contas de QA disponíveis (somente identificadores)

| Identificador usado por `login_salvo` | Estabelecimento confirmado no cabeçalho |
| --- | --- |
| `agenday-conta-a` | `qa01teste` |
| `agenday-conta-b` | `aq02teste` |

Não confundir o **identificador do cofre** com o nome de estabelecimento mostrado na aplicação. Conferir os nomes novamente depois de cada login; são referências ao teste de 18/11 e podem mudar depois.

## Pré-requisitos de cada nova sessão

1. Windows ligado, DeskBrowse conectado e Microsoft Edge disponível; confirmar com **`bridge_status`**. Nesse teste, estavam conectados os upstreams desktop e browser.
2. Confirmar que o site é o AgenDay próprio: `https://agenday-sistema.infinityfree.io/`. Não reutilizar o cofre para outros domínios sem autorização.
3. Contas A/B já cadastradas no cofre pela interface local **DeskBrowse - Cofre de contas**. O ChatGPT nunca recebe a senha.
4. Antes de alternar contas, verificar abas e sessão atual: **as abas do mesmo perfil do Edge podem compartilhar cookies**. Se o responsável estiver fazendo QA visual na mesma sessão, avisar que o logout pode interromper o trabalho dele e coordenar a troca.
5. Autorização para login não autoriza criar/alterar/excluir clientes, serviços, agendamentos ou dados cadastrais; obter escopo para testes com escrita.

## Procedimento recomendado (ordem obrigatória para alternância)

**1 — Conferir conexão.** Chamar a ferramenta **DeskBrowse `bridge_status`**, sem argumentos. Se desktop ou browser estiver desconectado, registrar bloqueio; não solicitar credenciais no chat.

**2 — Conferir a sessão atual.** Usar **DeskBrowse `browser__tabs`** para obter a aba e **`browser__execute`** para verificar URL e cabeçalho. Se já estiver autenticada outra conta, **fazer logout pelo menu do AgenDay antes de tentar `login_salvo`**. O comportamento observado no teste foi: um `login_salvo` inicial contra a sessão já autenticada como B falhou porque não havia formulário público de entrada, mesmo com a conexão funcionando.

**3 — Entrar na conta A.** Chamar **DeskBrowse `login_salvo`** com exatamente:
```json
{"conta":"agenday-conta-a"}
```
Se estiver trabalhando na mesma aba já aberta e desautenticada, incluir o argumento opcional `"tab": <id-da-aba>`. A resposta **“login enviado” não comprova autenticação**.

**4 — Verificar no navegador.** Usar **DeskBrowse `browser__execute`** na aba retornada para verificar ao mesmo tempo: URL `/agenda.php`, área administrativa (por exemplo, título **Novo agendamento**) e nome do estabelecimento no cabeçalho **`qa01teste`**. Se não bater, registrar falha; nunca presumir sucesso só pelo clique ou HTTP 200.

**5 — Sair da conta A.** Na aba da ferramenta, abrir o menu do estabelecimento, selecionar **Sair** e verificar retorno a `/index.php` com formulário público. Não assumir que navegar até `index.php` encerra a sessão.

**6 — Entrar na conta B.** Chamar **DeskBrowse `login_salvo`** com:
```json
{"conta":"agenday-conta-b"}
```
Usar `tab` se reaproveitar a aba já desautenticada.

**7 — Verificar no navegador.** Conferir `/agenda.php`, área administrativa e cabeçalho **`aq02teste`**. Informar qual sessão ficou aberta ao final.

**8 — Tratar erros.** Se `login_salvo` informar que não conseguiu enviar o formulário, **inspecionar a URL e a sessão atual antes de atribuir falha às credenciais**. Não tentar obter senha por PowerShell, clipboard, leitura de arquivo, logs ou mensagens. Se houver verificação humana/2FA, seguir os controles da ferramenta, sem contornar mecanismos de segurança.

## Evidência executada nesta sessão — 18/11/2026

| Etapa | Observado | Resultado |
| --- | --- | --- |
| `bridge_status` | Desktop e navegador conectados | **PASS** |
| Primeira tentativa de `login_salvo` A | Aba já autenticada como `aq02teste`; ferramenta não encontrou formulário para enviar | **BLOCKED pela pré-condição de sessão**, corrigido com logout |
| Logout da sessão anterior | `/index.php` e campos de senha presentes | **PASS** |
| `login_salvo` com `agenday-conta-a` | Formulário enviado sem expor credenciais | **PASS (envio)** |
| Verificação A com `browser__execute` | `/agenda.php`, cabeçalho `qa01teste`, título **Novo agendamento** | **PASS (autenticação)** |
| Logout A | Volta a `/index.php` com formulário de login | **PASS** |
| `login_salvo` com `agenday-conta-b` | Formulário enviado sem expor credenciais | **PASS (envio)** |
| Verificação B com `browser__execute` | `/agenda.php`, cabeçalho `aq02teste`, título **Novo agendamento** | **PASS (autenticação)** |

**Estado final do teste:** conta **B** autenticada na aba usada para QA. Nenhum cadastro, senha, configuração de conta ou agendamento foi modificado. O teste **não** provou isolamento de leitura/escrita cruzada (IDOR), concorrência de agendamentos, recuperação de senha, nem independência de sessão entre abas do mesmo perfil.

## Como cadastrar/substituir credenciais

Operação local e manual, quando necessário: abrir **DeskBrowse - Cofre de contas** na área de trabalho do Windows, cadastrar identificador, URL de login, usuário e senha, e conferir apenas os identificadores na interface do cofre. Não colocar e-mails, senhas, hashes, códigos, dumps, backups do cofre ou caminhos privados em `docs/` ou nos commits.

## Próxima tarefa relacionada

Com as duas contas acessíveis sem interação, é possível planejar os **testes cruzados de isolamento** já registrados em [TESTES_E_QUALIDADE.md](TESTES_E_QUALIDADE.md), **mediante escopo/autorização para testes de escrita**. A confirmação de login das duas contas não deve ser relatada como aprovação dessa auditoria.

**Referências:** [AGENTS.md](../AGENTS.md), [GUIA_DE_CONTINUIDADE.md](GUIA_DE_CONTINUIDADE.md), [STATUS.md](STATUS.md).
