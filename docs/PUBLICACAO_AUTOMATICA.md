# Publicação controlada — GitHub Actions → FTPS → InfinityFree

**Procedimento operacional atualizado em 17/11/2026 (backup automático validado).** Este documento é a **fonte de verdade para executar deploy** do AgenDay 2.0. Antes de publicar, seguir também o procedimento real de **obtenção, verificação e proteção de backups** em [BACKUP_E_RECUPERACAO.md](BACKUP_E_RECUPERACAO.md) e as regras de coordenação em [GUIA_DE_CONTINUIDADE.md](GUIA_DE_CONTINUIDADE.md). Leia também [AGENTS.md](../AGENTS.md) e [STATUS.md](STATUS.md). Não invente outro workflow ou migre para navegador/FTP manual sem justificativa técnica e autorização.

## Identidade e arquitetura

| Item | Referência |
| --- | --- |
| Repositório de trabalho | `Emds96/AgenDay-PI` |
| Branch publicada | `dev-feira-uninorte`; **não usar `main`** |
| Site de destino | https://agenday-sistema.infinityfree.io/ |
| Workflow | `.github/workflows/agenday-publicar.yml` |
| Empacotamento | `scripts/preparar_publicacao.py` |
| Envio | `scripts/publicar_ftp.py` |
| Ambiente protegido | `producao-agenday` |
| Orquestração remota já utilizada | **Remote Desktop Commander (Desktop Commander) → PowerShell do computador autorizado → GitHub CLI (`gh`)** |
| Transportes | GitHub Actions → FTPS explícito com TLS validado, porta 21 → `htdocs/` do InfinityFree |

O **plugin GitHub** é utilizado preferencialmente para leitura/alteração do código, branches, commits, PRs e consulta das Actions; ele **não oferece nesta conexão** as operações de alteração das Variables e disparo manual do workflow. Estas ações eram realizadas pelo **`gh` autenticado no computador autorizado**, através do **Remote Desktop Commander**. Não é necessário usar ZERO Browser ou TinyFish para o deploy se esse acesso continuar disponível.

**Aprovação humana é obrigatória a cada publicação.** A automação existente não autoriza publicar só porque houve push, testes verdes ou merge. O responsável precisa autorizar a publicação **e o commit/escopo exatos**. A publicação de documentos e código não é autorização para alterar o banco ou executar SQL.

## Estado verificado em 16/11/2026 (antes da atualização documental)

- GitHub CLI **2.104.0** identificado no Windows conectado, autenticado como `Emds96` (escopos visíveis: `repo` e `workflow`, entre outros); o Desktop Commander estava **online nesta verificação**, não é uma garantia de disponibilidade futura.
- Branch `dev-feira-uninorte`: commit `ac53c8ea9b8be206c032d4072bf07213a9769698`, depois da integração dos PRs **#3, #4 e #5**. A correção do menu foi integrada no PR **#4**; o PR **#5** foi documental.
- Variáveis de **repositório** conferidas por `gh variable list`: `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. Portanto, **push e merge não publicam** enquanto estas travas permanecem desligadas.
- Nomes dos três secrets presentes no ambiente `producao-agenday`, **sem inspecionar valores**: `AGENDAY_FTP_HOST`, `AGENDAY_FTP_USERNAME`, `AGENDAY_FTP_PASSWORD`. A presença dos nomes **não garante que as credenciais ainda sejam válidas**.
- Ambiente `producao-agenday` com revisor obrigatório `Emds96`, `prevent_self_review=false` e política de publicação restrita à branch `dev-feira-uninorte`; estas regras podem mudar e devem ser verificadas novamente.
- GitHub Actions da branch no commit `ac53c8e...`: [validação 43953454744](https://github.com/Emds96/AgenDay-PI/actions/runs/43953454744) **success**; jobs `publicar` e `diagnosticar_ftp` **skipped**. CI verde **não é deploy**.
- **Último envio FTPS comprovado** nesta revisão: [run 43518952935, 13/11/2026](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), commit `f807327811895bc97827929f4e30c2e60a180110`. **Não está comprovado que os commits posteriores, incluindo o PR #4, estejam no servidor.**
- O responsável relatou QA integrado autenticado em 16/11 (detalhes em [STATUS.md](STATUS.md)); não confundir com prova de publicação do último commit.

## Comportamento EXATO do workflow atual (não alterar para seguir este guia)

O YAML possui `on.push` **somente** para `dev-feira-uninorte` e `workflow_dispatch` para execução manual.

1. **`validar`:** faz checkout, `php -l` nos PHP, executa `php tests/agendamento-validacao.php`, `php tests/agendamento-cancelamento.php` e `node tests/responsividade-check.js`, prepara `deploy-package/`, confere exclusões privadas e sobe um artifact com retenção de **1 dia**.
2. **`diagnosticar_ftp`:** depende da validação e executa **somente em `workflow_dispatch` ou push contendo `[teste-ftps]` na mensagem de commit**. Requer ambiente `producao-agenday`, verifica login FTPS e `htdocs/` **sem upload**. Mesmo com flags desligadas, um disparo manual pode deixar este job aguardando aprovação.
3. **`publicar`:** depende da validação e só é elegível quando repo/branch correspondem a `Emds96/AgenDay-PI`/`dev-feira-uninorte` **e** as duas variáveis de repositório são `true`/`publish`. Requer aprovação do ambiente `producao-agenday`. Faz checkout, baixa o artifact e executa `scripts/publicar_ftp.py`, que **volta a conferir as variáveis** no ambiente do job.
4. `concurrency.group=agenday-infinityfree` e `cancel-in-progress=false` evitam concorrência entre execuções deste workflow, mas **não são uma autorização de publicação**.
5. `.github/workflows/php-lint.yml` é um workflow **separado** que verifica sintaxe em pushes na mesma branch. O workflow antigo existente na `main` **não faz parte deste procedimento**.

**Atenção à janela de ativação:** enquanto ambas as flags estiverem ativas, **qualquer push novo na `dev-feira-uninorte` pode habilitar outra publicação**. Congelar merges/pushes e conferir a lista de runs antes de ativar. **Não desligar as flags antes de o job de publicação terminar**, porque o script também as verifica no momento do envio.

## Pré-condições obrigatórias (somente leitura)

1. Receber autorização explícita do responsável para **publicar o commit/escopo identificados**; conferir os PRs integrados, `git ref` atual e o diff desde a última publicação conhecida.
2. Confirmar que não há outro agente/sessão alterando `dev-feira-uninorte` e que não existem execuções inesperadas, pendentes ou concorrentes.
3. Verificar resultados de validação e do `php-lint.yml` para o SHA a publicar; refazer os testes necessários se o SHA mudou.
4. Confirmar **cópia integral e recente de `htdocs/` e SQL atual** segundo [BACKUP_E_RECUPERACAO.md](BACKUP_E_RECUPERACAO.md). O AgenDay validou em 17/11/2026 o backup automático PHP temporário + FTPS + restauração local (`82` arquivos e `9` tabelas), com ZIP privado e relatório no PC. Antes de **cada** deploy, confirmar um backup datado recente e os resultados `BACKUP OK`/hashes/restauração (ou executar novo backup pelo atalho **Backup AgenDay** no PC). O phpMyAdmin continua plano B. O workflow **não** gera backup nem faz rollback. Se a cópia estiver desatualizada, falhar ou não for verificável, não ativar as flags e informar o bloqueio.
5. Verificar a identidade da hospedagem, o destino `htdocs/`, a conexão privada `htdocs/conexao.php` (sem expor valores) e que a base é **independente da do Gabriel**.
6. Confirmar que `gh` e o plugin Desktop Commander estão disponíveis e autenticados; confirmar nomes de secrets e as regras do environment, **sem imprimir credenciais**.
7. Se qualquer pré-condição falhar, **parar e relatar o bloqueio**, sem alterar o workflow para contorná-la.

## Procedimento reproduzível com PowerShell + GitHub CLI

Usar o **Remote Desktop Commander** para executar comandos no **computador autorizado pelo usuário**. No Windows consultado, o `gh` foi instalado via WinGet; ele pode não aparecer automaticamente no `PATH` de uma sessão remota. Descobrir o executável, em vez de instalar outra ferramenta:

```powershell
$gh = (Get-Command gh -ErrorAction SilentlyContinue).Source
if (-not $gh) {
  $gh = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\GitHub.cli_Microsoft.Winget.Source_8wekyb3d8bbwe\bin\gh.exe'
}
if (-not (Test-Path $gh)) { throw 'GitHub CLI não encontrado; confirmar instalação e sessão autorizada.' }
$repo = 'Emds96/AgenDay-PI'
& $gh auth status
& $gh api "repos/$repo/git/ref/heads/dev-feira-uninorte" --jq '.object.sha'
& $gh variable list --repo $repo
& $gh secret list --repo $repo --env producao-agenday
& $gh run list --repo $repo --branch dev-feira-uninorte --limit 8
```

Os comandos acima são **somente leitura**. Nunca usar `gh auth token`, imprimir secrets, instalar credenciais novas ou pedir senha/código de verificação no chat. Não prosseguir se a conta autenticada ou o SHA estiver errado.

### Após autorização e backup

Congele pushes/merges durante a publicação. **Ative as duas variáveis de repositório** (não alterar os Secrets):

```powershell
& $gh variable set AGENDAY_DEPLOY_ENABLED --repo $repo --body 'true'
& $gh variable set AGENDAY_DEPLOY_MODE --repo $repo --body 'publish'
& $gh variable list --repo $repo
```

Execute **o workflow já existente**, na branch correta:

```powershell
& $gh workflow run agenday-publicar.yml --repo $repo --ref dev-feira-uninorte
& $gh run list --repo $repo --workflow agenday-publicar.yml --branch dev-feira-uninorte --limit 5 --json databaseId,event,headSha,status,conclusion,createdAt
```

Identifique o `run_id` da execução **`workflow_dispatch`** iniciada agora e confira que `headSha` é o SHA aprovado (não use cegamente a primeira execução da lista se houver runs simultâneos).

```powershell
$runId = 123456789  # TROCAR pelo run_id real; nunca usar este valor de exemplo
& $gh run view $runId --repo $repo
& $gh api "repos/$repo/actions/runs/$runId/pending_deployments" --jq '[.[] | {ambiente: .environment.name, id: .environment.id}]'
```

### Aprovar ambiente protegido sem trocar o workflow

O ambiente `producao-agenday` exige aprovação do revisor autorizado. A pessoa responsável deve autorizar a execução específica antes da aprovação. Pode aprovar na interface do GitHub Actions **ou**, se a API permitir e a permissão permanecer válida, pelo mesmo `gh` autenticado:

```powershell
# Usar SOMENTE com autorização explícita para este run.
# Substituir pelo ID REAL retornado no endpoint pending_deployments.
$idDoAmbiente = 0
if ($idDoAmbiente -le 0) { throw 'ID do ambiente não preenchido.' }
$corpo = @{ environment_ids = @([int]$idDoAmbiente); state = 'approved'; comment = 'Publicação autorizada para o run verificado.' } | ConvertTo-Json -Compress
$corpo | & $gh api --method POST "repos/$repo/actions/runs/$runId/pending_deployments" --input -
```

O job `diagnosticar_ftp` também usa esse ambiente no disparo manual; conferir os jobs pendentes antes de interpretar o pedido de revisão. Se a aprovação não funcionar, **não contornar a proteção**; pedir a intervenção do revisor. A configuração observada permitia autorrevisão, mas isso deve ser revalidado.

Acompanhe a execução e comprove **`validar=success`, `publicar=success` e upload no log**. Um job `skipped` ou `waiting`, ou somente `diagnosticar_ftp=success`, **não significa publicação**.

```powershell
& $gh run view $runId --repo $repo --json headSha,conclusion,jobs --jq '{sha:.headSha,conclusion,jobs:[.jobs[]|{name,status,conclusion}]}'
```

### Desativação obrigatória — também em caso de falha

**Somente depois de o job de publicação terminar (sucesso ou falha)**, desative primeiro o modo e depois o habilitador. Se a execução for interrompida, não abandone as flags ativas; investigue o status e desative assim que seguro:

```powershell
& $gh variable set AGENDAY_DEPLOY_MODE --repo $repo --body 'disabled'
& $gh variable set AGENDAY_DEPLOY_ENABLED --repo $repo --body 'false'
& $gh variable list --repo $repo
```

Registre evidência do `run_id`, `headSha`, três jobs, resultado e confirmação `false/disabled`. Se qualquer comando falhar, **não afirmar que a trava foi restaurada** até confirmar em nova consulta.

## Validação pós-deploy e contingência

- Acessar https://agenday-sistema.infinityfree.io/ em navegador com JavaScript habilitado (InfinityFree pode exibir desafio anti-bot em clientes HTTP simples). Conferir rotas e assets e **identificar efetivamente a versão publicada**; a versão PHP completa não expõe necessariamente SHA de build.
- Para o **QA-01**, confirmar no CSS publicado a regra nova do PR #4 e testar abertura/fechamento do offcanvas, backdrop, links, 320/390/768/1366 px.
- Executar regressão autenticada com dados próprios/de teste: cadastro/login/logout, clientes, serviços, funcionário + vínculo, gravação de agendamento, conflito, cancelamento, calendário e isolamento entre estabelecimentos. Testes CLI e CI não substituem essa verificação; testes de escrita **somente com autorização e contas de teste**.
- Conferir páginas legadas (alteração/recuperação de senha, perfil, config, comandas, financeiro), distinguindo defeitos já conhecidos de regressões. Consultar [STATUS.md](STATUS.md).
- O script faz uploads de arquivos individualmente (`index.php` por último) e **não realiza publicação atômica, exclusões remotas ou rollback**. Se falhar, pode restar versão parcial. **Parar, preservar logs e decidir restauração de backup ou republicação autorizada**; jamais importar `sql/BD_v2.sql` no banco existente como tentativa de correção.
- O pacote admite apenas PHP na raiz/`api`/`lib`, CSS/JS/imagens e regras `.htaccess` para uploads; exclui `conexao.php`, documentação, SQL, testes, workflows e fotos de clientes/funcionários. O `htdocs/conexao.php` existente permanece remoto e privado.
- Em caso de erro de autorização do GitHub CLI, confirmar `gh auth status` e as permissões no GitHub; não pedir token/OTP ao usuário no chat. Não substituir o fluxo por ZERO Browser, TinyFish ou novo workflow por conta própria.
- Após concluir, atualizar `docs/STATUS.md` com SHA publicado (quando comprovável), URL do run, resultados reais e pendências; separar claramente **integrado no GitHub**, **validado na CI** e **testado em produção**.

## Histórico e limites

- **13/11/2026:** deploy via FTPS comprovado no [run 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), commit `f807327811895bc97827929f4e30c2e60a180110`; sem apagar arquivos do servidor.
- **16/11/2026:** PRs #3, #4, #5 integrados; CI na versão `ac53c8e...` aprovada, **publicação ignorada por flags desativadas**; configuração e acesso CLI conferidos apenas em leitura.
- **Nenhum deploy, backup, alteração de secrets, banco ou workflow foi realizado durante a revisão documental de 16/11/2026.** A execução posterior exige nova autorização e verificação de estado.
