# AGENTS.md — Orientações para agentes de IA

Este arquivo orienta qualquer agente ou colaborador que trabalhe no **AgenDay 2.0**, sistema web acadêmico de agendamentos preparado para a feira tecnológica da UniNorte. Ele complementa, mas não substitui, a leitura do código e a autorização do responsável.

## Contexto e fontes de verdade

- **Repositório de trabalho:** `Emds96/AgenDay-PI` (fork de `oliv-gabriel/AgenDay-PI`).
- **Branch de desenvolvimento:** `dev-feira-uninorte`. É a referência principal do trabalho atual; não usar a `main` como referência para avaliar funcionalidades recentes.
- **Estratégia aprovada (16/11/2026):** continuar o desenvolvimento e os testes na `dev-feira-uninorte`, preferencialmente por branches de tarefa e PRs para ela. Somente quando houver uma versão estável, testada e aprovada para a apresentação, propor PR de `dev-feira-uninorte` para a `main` **deste fork**. Essa integração chama-se promoção da versão; não acontece automaticamente, depende de autorização específica e não altera o repositório de Gabriel. Não é necessário esperar o dia da feira se a versão estiver validada antes.
- **Tecnologias:** PHP, MySQL/MariaDB, HTML, CSS, JavaScript e Bootstrap.
- **Hospedagem:** conta independente no InfinityFree (`https://agenday-sistema.infinityfree.io/`). Há evidência de publicação FTPS bem-sucedida em 13/11/2026 e de site público acessível em 16/11/2026; isso **não comprova que o último commit foi implantado nem que os fluxos PHP/MySQL funcionem**. Conferir versão e estado atual antes de afirmar.
- **Documentação:** `README.md`, `docs/STATUS.md` e os documentos por microtarefa em `docs/`. O histórico de commits e o código atual prevalecem sobre descrições antigas.

## Como começar uma tarefa

1. Confirmar repositório, branch atual, último commit e escopo solicitado.
2. Ler este arquivo, `docs/STATUS.md` e `README.md`; consultar somente os documentos e arquivos de código pertinentes à tarefa.
3. Identificar o que já existe, os riscos, as dependências e os testes possíveis antes de propor alterações.
4. Diferenciar expressamente **implementado no código**, **testado localmente/na CI**, **validado na hospedagem** e **pendente**.
5. Apresentar um plano proporcional à tarefa. Não inventar resultados, funcionalidades ou decisões.

## Autonomia, autorização e Git

- **Permitido sem autorização adicional:** leitura do repositório, investigação, consultas de documentação e elaboração de propostas.
- **Exige autorização prévia:** criar, editar ou excluir arquivos; criar branches, commits ou Pull Requests; realizar push/merge; modificar banco, hospedagem, credenciais, configurações ou deploy.
- Uma autorização pode cobrir a tarefa inteira; não é necessário confirmar cada passo já incluído no escopo aprovado.
- **Fluxo preferencial para alterações autorizadas:** criar branch a partir de `dev-feira-uninorte` → modificar apenas o escopo aprovado → validar → abrir Pull Request **com base em `dev-feira-uninorte`** → aguardar revisão e merge autorizado.
- Nunca modificar a `main` do fork ou o repositório original sem autorização explícita. A promoção futura da `dev-feira-uninorte` à `main` do fork exigirá PR, revisão do workflow legado de deploy da `main`, testes e autorização específica. Não fazer merge, deploy ou alterações destrutivas automaticamente.
- Não reescrever histórico nem sobrescrever mudanças de terceiros. Antes de alterar, verificar se arquivos e branches já foram modificados.

## Estrutura do projeto

- `index.php`, `login.php`, `cadastrar.php`, `sessao.php`, `verifica_login.php`: autenticação e sessão.
- `cad-cliente.php`, `cad-funcionario.php`, `cad.servicos.php` e respectivos endpoints: cadastros e consultas.
- `agenda.php`, `salvar-agendamento.php`, `cancelar-agendamento.php`, `api/`, `lib/`: calendário e regras de agendamentos.
- `sql/BD_v2.sql`: modelo atual de banco para ambiente independente; arquivos SQL legados não são a base do desenvolvimento atual.
- `css/`, `js/`, `img/`: interface; `tests/`: verificações automatizadas; `.github/workflows/`: CI e publicação controlada.
- `docs/`: histórico técnico por microtarefa e status consolidado.

## Segurança e integridade

- Não expor, imprimir ou versionar senhas, tokens, cookies, arquivos privados ou credenciais. `conexao.php` deve permanecer fora do Git; usar `conexao.exemplo.php` somente como referência sem valores reais.
- Usar **prepared statements** para SQL e validar/escapar entradas e saídas conforme o contexto.
- Aplicar verificação de sessão nas rotas privadas e proteção **CSRF** nas operações de escrita.
- Obter `id_estabelecimento` **da sessão autenticada**, nunca confiar em um ID enviado pelo navegador. Filtrar acessos por estabelecimento, inclusive em consultas, mudanças e uploads.
- Senhas com `password_hash()` / `password_verify()`; não reintroduzir MD5.
- Validar uploads por formato, tamanho, conteúdo e destino; não permitir execução de scripts em pastas de arquivos enviados.
- Não executar SQL em banco existente, importar novamente `BD_v2.sql`, alterar automações ou publicar código sem autorização e avaliação de backup/impacto.

## Verificação e continuidade

Testes disponíveis no repositório (executar quando pertinentes e o ambiente permitir):

```bash
php tests/agendamento-validacao.php
php tests/agendamento-cancelamento.php
node tests/responsividade-check.js
```

Também conferir sintaxe dos PHPs alterados (`php -l`) e resultados da CI, quando aplicável. **Testes estáticos/locais não comprovam integração com o MySQL nem comportamento no InfinityFree.** Registrar sempre o que foi efetivamente executado, onde e com qual resultado.

Ao concluir uma implementação autorizada, atualizar `docs/STATUS.md` **no mesmo Pull Request**, com data, mudanças reais, testes, pendências e próxima ação. Preservar os documentos por microtarefa como histórico, corrigindo divergências quando estiver no escopo autorizado.

**Regra central:** entender o estado real antes de agir; modificar somente com autorização; validar e registrar antes de declarar uma tarefa concluída.
