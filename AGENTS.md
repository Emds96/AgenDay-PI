# AGENTS.md — Orientações para agentes de IA

Este arquivo orienta qualquer agente ou colaborador que trabalhe no **AgenDay 2.0**, sistema web acadêmico de agendamentos preparado para a feira tecnológica da UniNorte. Ele complementa, mas não substitui, a leitura do código e a autorização do responsável.

## Contexto e fontes de verdade

- **Repositório de trabalho:** `Emds96/AgenDay-PI` (fork de `oliv-gabriel/AgenDay-PI`).
- **Branch de desenvolvimento:** `dev-feira-uninorte`. É a referência principal do trabalho atual; não usar a `main` como referência para avaliar funcionalidades recentes.
- **Estratégia aprovada (16/11/2026):** continuar o desenvolvimento e os testes na `dev-feira-uninorte`, preferencialmente por branches de tarefa e PRs para ela. Somente quando houver uma versão estável, testada e aprovada para a apresentação, propor PR de `dev-feira-uninorte` para a `main` **deste fork**. Essa integração chama-se promoção da versão; não acontece automaticamente, depende de autorização específica e não altera o repositório de Gabriel. Não é necessário esperar o dia da feira se a versão estiver validada antes.
- **Tecnologias:** PHP, MySQL/MariaDB, HTML, CSS, JavaScript e Bootstrap.
- **Hospedagem:** conta independente no InfinityFree (`https://agenday-sistema.infinityfree.io/`). Há evidência de publicação FTPS bem-sucedida em 13/11/2026 e de site público acessível em 16/11/2026; isso **não comprova que o último commit foi implantado nem que os fluxos PHP/MySQL funcionem**. Conferir versão e estado atual antes de afirmar.
- **Documentação:** `README.md`, `docs/STATUS.md` e os documentos por microtarefa em `docs/`. O histórico de commits e o código atual prevalecem sobre descrições antigas.

## Continuidade operacional de todas as atividades (leitura obrigatória)

Antes de qualquer tarefa, **não se limitar ao guia de deploy**. Usar [`docs/INDICE_DA_DOCUMENTACAO.md`](docs/INDICE_DA_DOCUMENTACAO.md) para localizar a informação e ler:
- [`docs/GUIA_DE_CONTINUIDADE.md`](docs/GUIA_DE_CONTINUIDADE.md): **como a equipe trabalhava de fato**, ferramentas, autorizações, branches, coordenação entre sessões, procedimentos, riscos e passagem de contexto.
- [`docs/ARQUITETURA_E_DECISOES.md`](docs/ARQUITETURA_E_DECISOES.md): mapa técnico, separação do site do Gabriel, funcionalidades e decisões ainda pendentes.
- [`docs/TESTES_E_QUALIDADE.md`](docs/TESTES_E_QUALIDADE.md): diferença entre UI, código, CI e integração; critérios de QA e defeitos.
- [`docs/BACKUP_E_RECUPERACAO.md`](docs/BACKUP_E_RECUPERACAO.md): **procedimento de obtenção e verificação dos dois backups** e critérios antes de publicar.
- [`docs/PUBLICACAO_AUTOMATICA.md`](docs/PUBLICACAO_AUTOMATICA.md): somente o workflow vigente e seus gates.

A documentação histórica de cada microtarefa pode descrever etapas já superadas. **Consultar primeiro GitHub/`STATUS.md`** para distinguir o último código integrado, CI, deploy comprovado e QA em produção. Não afirmar que backup existe só porque uma pasta está preparada. Se uma ferramenta estiver offline, registrar o impedimento; não contornar controles nem redesenhar fluxos automaticamente.

## Deploy: procedimento obrigatório antes de alterações na hospedagem

**Não criar workflow novo nem tentar a `main` para resolver bloqueio de deploy.** A publicação existente está descrita passo a passo em [`docs/PUBLICACAO_AUTOMATICA.md`](docs/PUBLICACAO_AUTOMATICA.md): GitHub Actions → FTPS → InfinityFree, operada pelo **Remote Desktop Commander + GitHub CLI (`gh`)** na máquina autorizada. O plugin GitHub serve para código/PRs/CI, mas não fornece atualmente o controle de Variables e disparo manual; verificar `gh auth status` antes de assumir indisponibilidade.

A rotina exige autorização para **SHA e destino exatos**, backup recuperável e confirmação da branch; ativa temporariamente `AGENDAY_DEPLOY_ENABLED=true` e `AGENDAY_DEPLOY_MODE=publish`, dispara `workflow_dispatch`, aprova o environment `producao-agenday` quando autorizado, comprova `publicar=success` e **desativa as duas variáveis novamente**, também após falha. Um push com CI verde **não é deploy**. Não tocar secrets, `conexao.php`, SQL, workflow ou banco no processo documental. Consultar o guia atualizado — não confiar em mensagens de sessões anteriores.

## Como começar uma tarefa

1. Confirmar repositório, branch atual, último commit, atividade de outras sessões e escopo solicitado.
2. Ler este arquivo, `docs/INDICE_DA_DOCUMENTACAO.md`, `docs/GUIA_DE_CONTINUIDADE.md`, `docs/STATUS.md` e o documento específico de backup, QA, deploy ou funcionalidade. Consultar o código pertinente antes de agir.
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
