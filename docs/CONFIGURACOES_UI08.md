# UI-08 — Configurações em duas abas + Space Grotesk Medium

**Data:** 18/11/2026
**Branch de implementação:** `feat/configuracoes-duas-abas-ui08`
**Base:** `dev-feira-uninorte`, início no commit `2ee2d43345f5a3e392ba512fec5fbc4387481c4a`
**Estado:** [PR #33](https://github.com/Emds96/AgenDay-PI/pull/33) **integrado** à `dev-feira-uninorte` (merge `5637ef4092792d6493c461a0d15de5636244101a`) e **publicado** pelo [workflow #44428793171](https://github.com/Emds96/AgenDay-PI/actions/runs/44428793171) com `success`. QA de navegação/UI no Edge aprovado parcialmente, persistência e aprovação humana ainda pendentes. Ver `docs/STATUS.md`.

## Escopo e comportamento

A interface `config.php` passa a ter somente **Estabelecimento** e **Minha Conta**, com um painel visível de cada vez. No desktop as duas abas ficam horizontais e o conteúdo é centralizado; no celular, 360–412px, os controles e o formulário se ajustam à tela.

- **Estabelecimento:** nome real e telefone opcional, ambos gravados por `UPDATE estabelecimento ... WHERE id_estabelecimento = ?` com ID da sessão e CSRF obrigatório. Reusa `lib/configuracao-estabelecimento.php`, atualiza `$_SESSION['nome_estabelecimento']` e aplica POST/Redirect/GET. E-mail não fica nesse formulário. No navegador, telefones brasileiros completos de 10/11 dígitos são formatados com DDD; a validação 8–15 dígitos do backend foi preservada para não quebrar contatos existentes/internacionais.
- **Minha Conta:** foto (com fallback), e-mail real carregado do banco em campo somente leitura e área Segurança com botão **Alterar senha**. Não há campo de alteração de e-mail.
- **Senha:** modal Bootstrap com senha atual, nova e confirmação. O POST continua usando `alterar-senha.php` e `lib/alteracao-senha.php`, CSRF, `password_verify`, `password_hash`, filtro por ID da sessão e compare-and-swap do hash. PRG redireciona a `config.php?aba=conta`; erros reabrem o modal, sem persistir senhas digitadas. Escape/Cancelar/fechar funcionam antes do envio; durante o envio o JS impede fechar/reenvio. Foco inicial e retorno são geridos pelo modal Bootstrap.
- **Links antigos:** `perfil.php` (antigo formulário demonstrativo não funcional) redireciona para Minha Conta. O GET de `alterar-senha.php` também redireciona para Minha Conta; o POST seguro continua operacional. Links externos antigos não recebem 404.
- **Marca:** CSS em `css/responsivo.css` importa Space Grotesk 500 por HTTPS, aplicada exclusivamente a `.ad-header-brand` com fallback. Os demais títulos, textos, campos e botões mantêm a tipografia anterior. URL versionada `?v=20261118-ui08` nas páginas administrativas para evitar o problema de cache identificado no UI-07.

## Armazenamento de fotos sem migração de banco

A tabela `estabelecimento` não tem coluna de avatar no esquema referencial; as fotos de clientes e funcionários já utilizam diretórios `arquivos/` com `.htaccess`. Para não solicitar migração nesta etapa:

- Cada estabelecimento grava no próprio arquivo `arquivos/estabelecimentos/avatar-<id>.jpg` ou `.png`, onde `<id>` vem exclusivamente de `$_SESSION['id_estabelecimento']`. O navegador nunca envia identificadores ou caminhos. Foto e nome do negócio ficam separados por estabelecimento.
- O backend valida POST autenticado, CSRF, upload HTTP real, tamanho **até 2 MiB**, dimensões **até 4096×4096**, tipo real pelo `getimagesize()` e MIME pelo `finfo` quando disponível. Aceita JPG/PNG; rejeita SVG, PHP, outros formatos e arquivos inválidos. Um arquivo temporário é movido com nome aleatório e substituído por rename; a extensão anterior é removida após sucesso.
- A pasta nova tem `.htaccess` com `Options -Indexes` e bloqueio de extensões executáveis e `.tmp`; o empacotador/publicador somente incluem **a regra `.htaccess`**, nunca arquivos enviados. O fluxo de deploy atual deve disponibilizar a pasta e a proteção antes de aceitar uploads.
- A imagem é **pública por URL**, como uma logo de estabelecimento; não serve para imagens privadas ou documentos. Não há endpoint de visualização privada e não se promete sigilo da imagem. URLs são construídas do ID da sessão para o cabeçalho e incluem um fragmento do hash SHA-256 da imagem para invalidar o cache mesmo após duas trocas rápidas.
- O banco não registra a imagem. Por isso, **backup/restauração devem incluir a pasta completa** em `htdocs/arquivos/estabelecimentos` e o SQL já previsto no backup geral. Um backup apenas do GitHub não recupera fotos. Confirmar permissões de escrita e `.htaccess` no InfinityFree depois de autorização de deploy.

## Verificações e QA da versão publicada (18/11/2026)

**Evidências de liberação:** CI da branch de tarefa [#44424603604](https://github.com/Emds96/AgenDay-PI/actions/runs/44424603604), CI pós-merge [#44428652461](https://github.com/Emds96/AgenDay-PI/actions/runs/44428652461) e [#44428652349](https://github.com/Emds96/AgenDay-PI/actions/runs/44428652349), todos com sucesso. Backup privado `2026-11-18_201846_a7eb4bb3`: BACKUP OK (91 arquivos, 10 tabelas, restauração local/ZIP/SHA-256 confirmados). Deploy [#44428793171](https://github.com/Emds96/AgenDay-PI/actions/runs/44428793171): três jobs `success`; flags depois do envio `false/disabled`.

| Cenário | Resultado observado | Limitação |
| --- | --- | --- |
| Duas abas com apenas um painel visível | **PASS**, clique e seta do teclado no Edge | Teste com leitor de tela pendente |
| Responsividade 320, 360, 390, 412, 768 e 1366px | **PASS**, sem overflow horizontal na página Configurações | Outros navegadores/dispositivos físicos pendentes |
| Nome/telefone e cabeçalho | **PASS parcial**, nome carregado; telefone opcional e máscara brasileira testada só no DOM | Gravação/persistência reais ainda não testadas |
| E-mail da conta B em campo somente leitura | **PASS** para presença e atributo readonly | Conteúdo comparado com banco/conta A ainda não testado |
| Foto/avatar e pasta protegida | **PASS parcial**, campo de arquivo e fallback no cabeçalho renderizam; listagem pública da pasta retorna HTTP 403 | Upload/recusa de inválidos/troca real da imagem ainda não testados |
| Modal senha | **PASS de UI**, três campos, foco, Escape, Cancelar e X | POST de erro/sucesso, bloqueio durante envio e persistência pendentes |
| Fontes | **PASS**, Space Grotesk peso 500 carregada no cabeçalho e system-ui mantida em títulos/campos | Verificação visual humana pendente |
| Rotas legadas e regressão administrativa | **PASS de navegação**, dez rotas em 412px, redirects de Perfil/Senha, sem erro PHP visível | Fluxos de escrita das demais páginas não retestados |
| Isolamento A/B | Biblioteca filtra por ID da sessão; leitura autenticada na conta B | Teste cruzado real A/B não executado nesta etapa |

**Não foram enviados POSTs que alterassem banco, nome, telefone, foto ou senha.** Essa distinção importa: tela carregada, CI verde e modal abrindo não substituem QA integrado dos fluxos.

## Próxima etapa de validação com o responsável

1. Abrir [Configurações](https://agenday-sistema.infinityfree.io/config.php) em dispositivo real e validar aparência de Estabelecimento/Minha Conta.
2. Testar alteração de nome e telefone em conta de teste, confirmando persistência após recarga e atualização do cabeçalho; registrar valores anteriores para restaurar se necessário.
3. Enviar JPG/PNG válido de até 2 MiB, confirmar preview e avatar em outras telas; testar rejeição de imagem inválida. Lembrar que logo é pública e que o backup deve incluir `htdocs/arquivos/estabelecimentos`.
4. Abrir modal, testar senha antiga incorreta, confirmação divergente e atualização real. **Se alterar senha de conta QA do cofre**, atualizar o cofre autorizado imediatamente; não registrar credenciais na conversa ou no GitHub.
5. Validar isolamento da conta A vs B e regressão geral com autorização de escrita delimitada. Os testes humanos devem ser documentados como relato do responsável, separados de CI e navegação automatizada.

**Após o deploy:** não realizar novo deploy para registrar documentação. O SHA publicado de aplicação é `5637ef4`; commits documentais posteriores na `dev-feira-uninorte` não provam nova versão hospedada.
