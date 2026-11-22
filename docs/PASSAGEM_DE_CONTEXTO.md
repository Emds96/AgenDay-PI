# Passagem de contexto — qualquer IA, qualquer ferramenta

**Revisão:** 21/11/2026 · **Público:** qualquer agente de IA (ou pessoa) que vá continuar o AgenDay 2.0, inclusive com ferramentas diferentes das usadas até aqui.

> Este é o documento **oficial** de retomada. O antigo `C:\Users\EDER\AgenDay-Local\HANDOFF-CHATGPT.md` está **vencido** (escrito em 20/11, antes do deploy de 21/11 e da conta demo) e serve apenas como histórico.

## 1. Em uma frase

Sistema web acadêmico de agendamentos (PHP 8 + MySQL/MariaDB + Bootstrap 5), fork `Emds96/AgenDay-PI`, branch `dev-feira-uninorte`, publicado em https://agenday-sistema.infinityfree.io/ — com prazo de congelamento em **23/11/2026** e feira em **27/11/2026**.

## 2. Onde está cada coisa

| O quê | Onde |
| --- | --- |
| Código-fonte | `C:\Users\EDER\AgenDay-PI` (Git) · GitHub `Emds96/AgenDay-PI` |
| Site publicado | https://agenday-sistema.infinityfree.io/ (InfinityFree, conta independente) |
| Banco de produção | MySQL do InfinityFree, acessível só pelo phpMyAdmin do painel |
| Banco local | MariaDB 11.8.9, `agenday_local` em `127.0.0.1:3306` |
| Ambiente local (como subir, caminhos, testes) | [AMBIENTE_LOCAL.md](AMBIENTE_LOCAL.md) |
| Regras de trabalho e autorização | [AGENTS.md](../AGENTS.md) |
| Como a equipe trabalhava, vocabulário, limites | [GUIA_DE_CONTINUIDADE.md](GUIA_DE_CONTINUIDADE.md) |
| Histórico real (PRs, SHAs, CI, publicações) | [STATUS.md](STATUS.md) |
| Mapa técnico e decisões | [ARQUITETURA_E_DECISOES.md](ARQUITETURA_E_DECISOES.md) |
| Backup e recuperação | [BACKUP_E_RECUPERACAO.md](BACKUP_E_RECUPERACAO.md) |
| Publicação (workflow, SQL em produção, aprovação) | [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md) |
| Testes e critérios de QA | [TESTES_E_QUALIDADE.md](TESTES_E_QUALIDADE.md) |
| Índice de todos os documentos | [INDICE_DA_DOCUMENTACAO.md](INDICE_DA_DOCUMENTACAO.md) |
| Credenciais locais de QA | `C:\Users\EDER\AgenDay-Local\credenciais-locais.txt` (fora do Git) |
| Credenciais FTPS e do MariaDB local | `%USERPROFILE%\Documents\AgenDay-Backups\.env.backup` e `.mysql-local.cnf` |

## 3. Estado em 21/11/2026

- **Branch:** `dev-feira-uninorte`. O HEAD avança sobretudo com documentação; **conferir no GitHub antes de agir** (`git log`, `gh run list`), em vez de confiar neste texto.
- **Último código publicado:** SHA `b6887f6` — run [`44967236148`](https://github.com/Emds96/AgenDay-PI/actions/runs/44967236148) (21/11/2026, `workflow_dispatch`), três jobs com sucesso; antes dele, SHA `4dbf227` — run [`44965206160`](https://github.com/Emds96/AgenDay-PI/actions/runs/44965206160), SHA `4bd707a` — run [`44953952790`](https://github.com/Emds96/AgenDay-PI/actions/runs/44953952790) e SHA `47fb029` — run [`44856321866`](https://github.com/Emds96/AgenDay-PI/actions/runs/44856321866). Flags de deploy desligadas ao final de todos.
- **Produção ganhou em 21/11/2026:** tela de agendamento (`agendar.php`) com horários livres calculados no servidor, pacote de sessões e retorno recorrente; comparecimento (Chegou / Faltou / Concluir) e fechamento automático; migração `sql/migracao-status-comparecimento.sql` aplicada no phpMyAdmin; e, no deploy `4bd707a`, o **portal público do cliente** (`agenday-agendamentos.infinityfree.io/?e=studio`) com a migração `sql/migracao-portal-cliente.sql` aplicada.
- **Portal do cliente validado em produção (21/11/2026):** o responsável preencheu **Configurações → WhatsApp**, recebeu o código no WhatsApp, criou conta e confirmou um agendamento; mensagem de confirmação enviada em 3 s. Falta testar o cancelamento pelo link.
- **WhatsApp da plataforma:** `whatsapp_config` é **uma linha única** (`id_config = 1`) — o mesmo número pareado no WAHA atende todos os estabelecimentos. A tela fica em Configurações de cada estabelecimento, então hoje qualquer um pode sobrescrever a configuração de todos (ajuste pendente). Operação: [`WHATSAPP_WAHA_GUIA.md`](WHATSAPP_WAHA_GUIA.md) e `scripts/iniciar-feira.ps1`.
- **Conta de demonstração:** `studio@demonstracao.com`, Studio Bella Vita, 173 agendamentos fictícios — existe **no local e na produção**. A senha está com o responsável, **não** no repositório.
- **Flags de publicação:** `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. **Merge não publica** enquanto estiverem assim.

### 3.1 O que está implementado e o que falta

| Tema | Estado |
| --- | --- |
| Login, cadastros, clientes, funcionários, serviços | Publicado; edição e exclusão com confirmação |
| Horário de funcionamento, bloqueios e regras | Publicado e **aplicado** no cálculo de horários livres |
| Agendamento com sessões e recorrência | Publicado |
| Comparecimento e status (`agendado` → `confirmado` → `em_atendimento` → `concluido`, mais `faltou`/`cancelado`) | Publicado, fechamento sem cron |
| Conta de demonstração | Publicada |
| **PEND-01** — modo híbrido de sessões (ligar/desligar no cadastro e nas Configurações) | A implementar |
| **PEND-02** — lembretes ao cliente (SMS pago; e-mail Brevo como alternativa) | Decisão pendente |
| Reagendamento com vínculo ao atendimento anterior | Pendente |
| Visões dia/semana no calendário | Pendente (hoje é mensal + painel do dia) |
| QA em celular físico e leitor de tela | Pendente de relato do responsável |

## 4. Regras que não podem ser quebradas

1. **Autorização antes de alterar.** Ler e propor é livre; criar/editar/apagar arquivos, branches, PRs, merge, banco, hospedagem e deploy exigem autorização do responsável. Uma autorização cobre todo o escopo pedido — não repetir perguntas já respondidas.
2. **1 lote = 1 branch = 1 PR** com base em `dev-feira-uninorte`, e a documentação (`docs/STATUS.md`) entra **no mesmo PR**.
3. **Nunca alterar `main`** do fork nem o repositório original `oliv-gabriel/AgenDay-PI` sem autorização específica.
4. **Publicar** exige: autorização para **SHA e destino exatos**, backup verificado, no máximo **1 vez por dia**, SQL antes do código, aprovação do environment e desligar as flags depois — inclusive em caso de falha.
5. **CI verde não é publicação.** Merge não é deploy.
6. **Nunca** expor senhas, tokens, cookies, `conexao.php`, dumps ou dados de clientes em commit, PR, chat ou print.
7. SQL com **prepared statements**, `id_estabelecimento` **da sessão**, **CSRF** em escrita, `password_hash`/`password_verify` (nunca MD5).
8. Não importar `sql/BD_v2.sql` em banco existente; migrações vão em `sql/` e são aplicadas manualmente, com backup.
9. Ao receber "testei e funcionou", **primeiro** registrar/atualizar a documentação no GitHub, **depois** responder que concluiu.
10. Não reescrever histórico, não sobrescrever trabalho de outra sessão e conferir o HEAD imediatamente antes de abrir PR.

## 5. Equivalência de ferramentas

Os documentos antigos citam ferramentas específicas de sessões anteriores. **Nada no projeto depende de uma IA ou plugin específico** — depende de capacidades equivalentes. Se a sua ferramenta tem a coluna da direita, você consegue fazer o mesmo trabalho.

| Citado nos docs | Para que servia | Equivalente genérico |
| --- | --- | --- |
| Plugin GitHub do ChatGPT | ler/editar código, abrir PR, consultar Actions | `git` + `gh` no PC (ou a integração Git da sua ferramenta) |
| Remote Desktop Commander | executar PowerShell no PC autorizado | terminal/shell local do agente |
| DeskBrowse (`bridge_status`, `login_salvo`) | login automático em contas de QA | navegador com as contas de teste; o responsável digita uma vez |
| ZERO Browser MCP / `browse` | navegar, clicar, preencher, screenshot | navegador controlado (Playwright, DevTools) ou o próprio responsável |
| TinyFish | auditoria exploratória do site | navegador/HTTP manual |
| `gh api pending_deployments` | aprovar o environment `producao-agenday` | interface do GitHub Actions (Actions → run → Review deployments) |

**Consequência prática:** um agente que roda **no PC** consegue tudo (código, banco local, `gh`, backup, publicar, phpMyAdmin via navegador). Um agente que roda **só na nuvem** consegue GitHub, mas **não** o banco local, o backup nem o phpMyAdmin — nesse caso, pedir ao responsável para executar os passos locais ou usar um agente com acesso à máquina.

## 6. Checklist de acesso — o que passar para a IA nova

| # | Item | Onde está | Quem fornece |
| --- | --- | --- | --- |
| 1 | Pasta do repositório com leitura e escrita | `C:\Users\EDER\AgenDay-PI` | responsável |
| 2 | Terminal no PC (git, `gh`, PHP, Node, Python) | PATH do Windows | já instalado |
| 3 | Autenticação GitHub | `gh auth status` → conta `Emds96`, escopos `repo`/`workflow` | já autenticado; revalidar |
| 4 | `conexao.php` (banco local) | raiz do projeto, fora do Git | já existe; nunca versionar |
| 5 | `.env.backup` + `.mysql-local.cnf` (FTPS e MariaDB) | `Documents\AgenDay-Backups\` | já existem; nunca versionar |
| 6 | Logins de QA locais | `AgenDay-Local\credenciais-locais.txt` | responsável |
| 7 | Senha da conta de demonstração | com o responsável | responsável |
| 8 | Sessão do navegador no InfinityFree/phpMyAdmin (para SQL) | Edge do PC | responsável (ou usar FTPS + script temporário) |
| 9 | Autorização de publicação (SHA + destino) | — | responsável, a cada deploy |
| 10 | Leitura de `AGENTS.md` | repositório | obrigatória antes de alterar |

Sem os itens 4–5, a IA nova **não** consegue rodar o sistema nem fazer backup/publicação. Sem o 8, não altera o banco de produção pelo painel.

## 7. Primeiros 30 minutos de uma sessão nova

```powershell
$repo = 'C:\Users\EDER\AgenDay-PI'

git -C $repo fetch origin
git -C $repo status --short
git -C $repo log --oneline -8
"local : " + (git -C $repo rev-parse --short HEAD)
"origin: " + (git -C $repo rev-parse --short origin/dev-feira-uninorte)

gh auth status
gh run list --repo Emds96/AgenDay-PI --limit 5
gh variable list --repo Emds96/AgenDay-PI
```

Leitura, nesta ordem: `AGENTS.md` → este documento → `docs/STATUS.md` (topo) → `docs/AMBIENTE_LOCAL.md` → documento específico da tarefa.

Depois: subir o ambiente (`iniciar.ps1`), abrir `http://127.0.0.1:8080`, e confirmar que os testes passam antes de mexer em qualquer coisa:

```powershell
$php = "$env:USERPROFILE\Documents\AgenDay-Tools\php-8.4\php.exe"
& $php "$repo\tests\agendamento-validacao.php"
& $php "$repo\tests\agendamento-cancelamento.php"
node "$repo\tests\responsividade-check.js"
```

## 8. O que não fazer

- Não publicar, não rodar SQL em produção e não restaurar backup sem autorização explícita + backup recente.
- Não alterar `main`, workflow, Secrets ou `conexao.php` de produção como atalho para resolver bloqueio.
- Não "consertar" o banco reimportando `sql/BD_v2.sql`.
- Não deixar script PHP temporário na hospedagem (executar, usar e **remover**).
- Não confiar em `docs/` desatualizado: `STATUS.md` + código atual + GitHub prevalecem.
- Não criar documento histórico novo para mudança simples; `STATUS.md` + PR bastam.
- Não afirmar "publicado" com base em CI, e não afirmar "validado" com base em código lido.

## 9. Como encerrar uma tarefa

1. Rodar `php -l` nos PHPs alterados, os testes pertinentes e conferir o CI.
2. Atualizar `docs/STATUS.md` (e o documento específico, se mudou procedimento) **no mesmo PR**, com data, mudança real, testes executados, pendências e próxima ação.
3. Diferenciar sempre: **implementado**, **testado localmente/CI**, **publicado**, **validado pelo responsável**.
4. Se o responsável relatar teste/validação, registrar a fonte (relato, captura) antes de responder que concluiu.
5. Se uma etapa falhar, registrar o bloqueio e o que foi tentado; não contornar controles.

## 10. Mensagem pronta para retomar em qualquer IA

> Trabalhe no AgenDay 2.0, repositório `Emds96/AgenDay-PI`, branch `dev-feira-uninorte`, pasta local `C:\Users\EDER\AgenDay-PI`. Antes de agir, leia `AGENTS.md`, `docs/PASSAGEM_DE_CONTEXTO.md`, `docs/AMBIENTE_LOCAL.md`, `docs/STATUS.md` (topo) e o runbook da tarefa. Confira HEAD, PRs, CI, flags de deploy e possível atividade de outra sessão. Diferencie implementado / testado / publicado / validado. Trabalhe em branch curta com PR para `dev-feira-uninorte`, documentação no mesmo PR, nunca na `main`. Não publique, não altere banco, hospedagem, workflow, Secrets ou credenciais sem minha autorização. Comece pelo estado real e me diga a próxima ação segura.
