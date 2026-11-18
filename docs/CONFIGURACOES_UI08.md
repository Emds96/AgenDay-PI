# UI-08 — Configurações em duas abas + Space Grotesk Medium

**Data:** 18/11/2026
**Branch de implementação:** `feat/configuracoes-duas-abas-ui08`
**Base:** `dev-feira-uninorte`, início no commit `2ee2d43345f5a3e392ba512fec5fbc4387481c4a`
**Estado:** implementado somente na branch de tarefa; sem merge/deploy. Ver `docs/STATUS.md` e o PR correspondente para resultados atuais.

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

## Verificações de implementação e QA

| Cenário | Estado nesta branch |
| --- | --- |
| UI com exatamente duas abas; somente painel ativo visível | Implementado; smoke test estrutural executado, navegador real pendente |
| Nome/telefone e atualização do cabeçalho | Código e validação reaproveitados; gravação no banco de produção pendente |
| E-mail real, imutável | Consulta original por ID da sessão preservada; verificação em QA A/B pendente |
| Foto JPG/PNG e atualização do avatar | Código de upload/validação implementado; teste real de upload/recarga pendente |
| Modal e senha atual/nova/confirmação | Segurança de backend reaproveitada; fluxo real erro/sucesso pendente |
| Teclado, foco, Escape e bloqueio durante POST | Lógica implementada; teste interativo e leitor de tela pendentes |
| Mobile 360/390/412px e desktop 1366px | CSS responsivo criado; inspeção visual em ambiente publicado pendente |
| Isolamento entre contas A/B | Código isola por ID de sessão; teste cruzado em produção pendente |
| Space Grotesk 500 apenas na marca | Regra CSS criada; confirmar `computed font-family` no navegador após deploy |
| Regressão nas páginas administrativas | Testes estruturais disponíveis; revisão visual completa pendente |

**Não executar testes de escrita no site publicado como prova desta versão:** até autorização de deploy, o InfinityFree permanece na versão anterior. O mecanismo `DeskBrowse` permite autenticação segura das contas de QA A/B quando um teste em produção for aprovado; credenciais nunca ficam no repositório.

## Operação futura

1. Revisar PR e validar PHP/Node e artefato na CI **sem publicar**.
2. Obter aprovação de merge e deploy separadamente; conferir `AGENTS.md`, `docs/BACKUP_E_RECUPERACAO.md` e `docs/PUBLICACAO_AUTOMATICA.md`.
3. Criar backup novo e íntegro de `htdocs` + SQL, incluindo a nova pasta de fotos caso já exista.
4. Confirmar que a regra `.htaccess` foi enviada e protege os temporários, e que a pasta aceita somente imagens JPG/PNG conforme código.
5. Testar com contas fictícias A e B: abas, dados separados, upload, avatar no cabeçalho, senha atual incorreta, confirmação, sucesso, teclado, tela mobile e regressão; registrar evidência e devolver contas aos dados esperados.

**Proibido neste escopo:** alterar MySQL, dar merge na `main`, executar deploy ou afirmar que as funcionalidades novas já estão operantes na hospedagem.
