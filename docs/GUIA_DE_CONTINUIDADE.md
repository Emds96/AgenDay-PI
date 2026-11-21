# Guia operacional e de continuidade — AgenDay 2.0

**Revisão:** 21/11/2026 · **Status:** procedimento de trabalho e passagem de contexto; não é autorização para alterar sistemas.

> **Leia este guia primeiro em uma sessão nova.** Depois consulte [AGENTS.md](../AGENTS.md), [PASSAGEM_DE_CONTEXTO.md](PASSAGEM_DE_CONTEXTO.md), [STATUS.md](STATUS.md) e o documento específico da tarefa. Revalide o estado no GitHub: estas instruções não substituem os dados atuais. Para trabalhar no PC, leia também [AMBIENTE_LOCAL.md](AMBIENTE_LOCAL.md).

## 1. Identidade, objetivo e prazos

- Projeto acadêmico **AgenDay 2.0**, gerenciamento web de agendamentos para a UniNorte; tecnologias PHP, MySQL/MariaDB, HTML, CSS, JavaScript e Bootstrap.
- Repositório de trabalho: `Emds96/AgenDay-PI` (fork). Repositório original do Gabriel: `oliv-gabriel/AgenDay-PI`, **não alterar sem autorização específica**.
- Branch de integração: `dev-feira-uninorte`; base de branches curtas para cada tarefa e destino de PRs. **`main` do fork está preservada** e possui workflow legado potencialmente perigoso; não usar para desenvolvimento corrente nem promover sem revisão/autorização.
- Site independente: https://agenday-sistema.infinityfree.io/ — hospedado no InfinityFree. **Não confundir** com o site do Gabriel, https://agenday.infinityfree.me/.
- Portal público do cliente (publicado em 21/11/2026): https://agenday-agendamentos.infinityfree.io/?e=slug — mesma hospedagem, pasta `htdocs` própria; o envio de código pelo WhatsApp depende do WAHA no PC do responsável ([WHATSAPP_WAHA_GUIA.md](WHATSAPP_WAHA_GUIA.md), atalho **AgenDay - Iniciar WhatsApp** e `scripts/iniciar-feira.ps1`).
- Prazo de conclusão **24/11/2026**, conforme relato do responsável sobre orientação do professor; feira indicada para **27/11/2026**, data ainda sujeita a confirmação.
- Priorizar fluxo que realmente funciona, proteção dos dados, regressão e demonstração no celular; não considerar interface pronta como funcionalidade validada.

## 2. Como as sessões trabalhavam de fato

| Necessidade | Ferramenta/caminho que usamos | Limite e precaução |
| --- | --- | --- |
| Ler código, histórico, comparar branches e PRs | **Plugin GitHub** do ChatGPT, conferindo sempre `Emds96/AgenDay-PI` + `dev-feira-uninorte` | Não usar `main` como fonte do desenvolvimento; não presumir que o conteúdo do site seja igual ao GitHub |
| Criar alterações após autorização | Branch curta derivada da `dev-feira-uninorte` → commit → testes → **Pull Request direcionado à dev** → revisão/merge autorizado | Não editar diretamente a `main`, não sobrescrever outras sessões |
| Administrar Variables, iniciar Actions e observar GitHub | **Remote Desktop Commander** → computador Windows autorizado → **PowerShell e GitHub CLI (`gh`)** autenticado como `Emds96` | O plugin GitHub desta conexão não tem todas as operações de Actions/Variables; CLI só funciona com computador online/autorizado; evitar exibir tokens |
| Publicar site | Workflow já existente [`agenday-publicar.yml`](../.github/workflows/agenday-publicar.yml): Actions → pacote → FTPS → InfinityFree | Duas Variables temporárias + aprovação do environment; instruções completas em [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md) |
| Backup pré-publicação | Painel InfinityFree/phpMyAdmin + download integral de `htdocs/` por cliente FTP/FTPS para pasta privada no computador | **Não é feito pelo GitHub Actions**, não obter senhas a partir dos Secrets; [BACKUP_E_RECUPERACAO.md](BACKUP_E_RECUPERACAO.md) |
| QA no sistema real | **DeskBrowse** para login automático usando cofre local; **ZERO Browser MCP** para navegação/QA quando disponível; **TinyFish** para auditoria exploratória | Conferir conexão, identidade da conta e sessão; escrita exige escopo próprio; não misturar cookies entre testes |
| Pesquisa externa/documentação de provedores | Documentação oficial, fontes confiáveis e código atual | Não substituir o exame do nosso repositório por suposições; separar fatos e hipóteses |
| Comunicação entre conversas | Documentação persistente versionada em `docs/`, especialmente `STATUS.md` | Uma sessão não acessa automaticamente todo o histórico de outra; nunca depender de chat como única memória |

> **Ferramentas são exemplos, não requisitos.** As colunas acima descrevem o que as sessões anteriores usaram. O trabalho depende das capacidades (ler/editar arquivos, `git`/`gh`, terminal, navegador), não dos produtos. A tabela de equivalência — inclusive para agentes que rodam no PC e agentes que rodam só na nuvem — está em [PASSAGEM_DE_CONTEXTO.md](PASSAGEM_DE_CONTEXTO.md#5-equivalência-de-ferramentas).

### Login autônomo de contas QA — procedimento vigente (18/11/2026)

**Manual detalhado:** [DESKBROWSE_LOGIN_QA.md](DESKBROWSE_LOGIN_QA.md). A operação depende de Windows, Edge e DeskBrowse conectados a cada sessão; não exigir que o responsável redigite senhas quando o cofre local estiver disponível.

- Primeiro chamar **DeskBrowse `bridge_status`**. Confirmar o endereço do AgenDay, a aba e se há uma sessão já autenticada.
- Se outra conta estiver conectada, selecionar **Sair** e confirmar o formulário público em `/index.php`; abrir `index.php` sem logout não substitui esse passo.
- Chamar **`login_salvo`** com `{"conta":"agenday-conta-a"}`; **`browser__execute`** precisa confirmar `/agenda.php`, área administrativa e cabeçalho `qa01teste`.
- Encerrar a sessão A por **Sair**. Em seguida chamar **`login_salvo`** com `{"conta":"agenday-conta-b"}` e confirmar no navegador o cabeçalho `aq02teste`. O argumento opcional `tab` é útil para reaproveitar uma aba já desautenticada.
- **Validação observada em 18/11/2026:** ambos os logins e o logout intermediário passaram; a tentativa inicial de A com B já autenticada não enviou login e foi resolvida por logout. Não houve leitura de credenciais nem alteração de dados de negócio. A sessão B permaneceu aberta na aba testada.
- A mesma sessão do Edge pode ser usada em mais de uma aba. Coordenar com o responsável se ele estiver verificando alterações visuais ao mesmo tempo. Não confundir login com auditoria cruzada de acesso aos dados.

**Estado do acesso observado em 16/11:** Desktop Commander online e `gh` 2.104.0 autenticado como `Emds96`. Em outra sessão, **verificar novamente**; nunca tratar conexão como permanente. Não registrar ID de dispositivo, token, senha, código OTP ou caminho de secrets no repositório.

## Regra de fechamento validado pelo responsável (17/11/2026)

**Confirmação humana de que funcionou = gatilho imediato para registrar, antes da resposta de conclusão.** Conferir o estado real e atualizar no GitHub `docs/STATUS.md`, o manual da funcionalidade e todos os documentos ativos diretamente afetados. Incluir PR, SHA, execução de deploy, fonte da evidência (teste da ferramenta ou relato do responsável), limitações e pendências. Corrigir textos antigos que continuam marcando como pendente o que já foi aprovado, sem apagar o histórico cronológico. Usar uma branch documental curta derivada de `dev-feira-uninorte`, PR, validação e merge, com `main` preservada. Registro documental não exige novo deploy.

**Ordem ao receber "funcionou", "aprovado" ou screenshot de validação:** confirmar fato → atualizar documentação ativa e integrar PR → responder ao responsável → iniciar próxima tarefa. Caso ferramentas estejam indisponíveis, declarar o registro pendente e sua causa, sem afirmar que foi concluído. Esta regra vale para **todas** as funcionalidades futuras.

### Modo rapido de desenvolvimento (proposta em PR, 19/11/2026)

O responsavel pode identificar o nivel no proprio pedido: **"simples"**, **"media"** ou **"complexa"**. O agente nao deve reinterpretar arbitrariamente a categoria, mas deve explicar e elevar os controles se a mudanca atingir autenticacao, SQL, isolamento de dados, permissoes ou integracoes.

- **Simples (UI/texto/CSS isolado):** checar HEAD, arquivos diretamente afetados e outras sessoes; branch curta, ajuste minimo, testes de sintaxe/responsividade conforme o arquivo; PR para `dev-feira-uninorte`; CI; publicacao somente autorizada; QA no celular; registro curto no PR/`STATUS.md`.
- **Media (formulario ou regra existente):** investigar dependencias e efeito em dados, testes direcionados + CI, PR, validacao real do caminho modificado e registro de regressao/pendencias.
- **Complexa (nova arquitetura, login/seguranca, mudanca SQL):** plano e aprovacao de riscos antes de escrever, testes ampliados, backup/recuperacao e rollout com monitoramento.

**Autorizacao unica:** se o responsavel incluir expressamente branch, codigo, PR, merge, testes e publicacao na mesma autorizacao, executar sem novas perguntas para passos ja incluidos, respeitando condicoes e riscos; verificar o SHA efetivamente resultante antes de publicar. Uma tarefa autorizada apenas para PR **para** no PR. Nao alterar `main`, Secrets, banco, workflow, hospedagem ou publicar fora do escopo.

**Fechamento enxuto:** registrar somente fatos verificaveis, SHA/PR/run, teste real relatado e pendencias; para alteracoes simples, nao abrir um novo documento historico longo. Se QA ocorrer apos merge, seguir a regra de documentacao aprovada acima, com PR documental pequeno quando necessario. Nunca declarar `publicado` com base apenas em CI.

**Deploy assistido:** consultar [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md). O script `scripts/publicacao_assistida.py` substitui passos manuais repetitivos apenas apos validacao no Windows e autorizacao de publicacao; por padrao ele apenas simula. Mesmo no modo real, exige backup, SHA validado e aprovacao do environment; nao faz rollback atomico.

## 3. Nosso ciclo de trabalho — da necessidade ao registro

1. **Entender:** ler pedido e restrições; confirmar escopo, responsável, data, critérios de aceitação, repositório, branch e SHA.
2. **Investigar sem efeitos:** ler documentação e código, logs, CI e, quando aplicável, examinar o site; distinguir **observado**, **verificado em código**, **relatado por outra sessão** e **hipótese**.
3. **Planejar mínimo necessário:** priorizar correção simples e testes; avaliar impacto em banco, segurança, design, hospedagem e relação com o projeto do Gabriel.
4. **Autorizar:** pedir aprovação antes de criar/editar/apagar arquivos, branches e PRs, merge, banco, hospedagem, deploy e mudanças persistentes. Uma autorização de tarefa cobre seu escopo, não autoriza trabalhos não relacionados.
5. **Implementar em branch de tarefa:** consultar HEAD antes de editar, evitar alterações simultâneas nas mesmas rotas e proteger `main`.
6. **Validar:** `php -l`, testes PHP/Node, CI e, se autorizado, QA integrado na hospedagem. Não declarar sucesso sem evidência.
7. **Revisar e integrar:** PR → verificar diff e base → merge autorizado na `dev-feira-uninorte`; registrar SHA exato.
8. **Publicar quando explicitamente autorizado:** backup atual, gate do workflow, aprovação, envio, restauração das Variables, verificação do site. **Merge não é deploy.**
9. **Registrar:** atualizar `docs/STATUS.md` com data, SHA, PR/run, evidências de teste, limitações e próximo passo. Novas decisões arquiteturais entram também em [ARQUITETURA_E_DECISOES.md](ARQUITETURA_E_DECISOES.md).

**Regra de coordenação:** se outra sessão estiver realizando deploy ou backup, não modificar a mesma branch sem combinar o momento; verificar HEAD imediatamente antes de abrir PR/mesclar. Enviar a outra sessão o link do documento e o SHA, não uma descrição vaga.

## 3A. Vocabulário do dia a dia (20/11/2026)

Para evitar confusão entre "subir no GitHub" e "colocar no site":

| Termo | O que é | O que muda | Desfazer |
| --- | --- | --- | --- |
| **Commit** | registro da mudança no histórico do Git, na máquina local | só o PC | sim |
| **Push** | envio dos commits para o GitHub | GitHub | sim |
| **PR + merge** | revisão e junção da branch na `dev-feira-uninorte` | GitHub | sim (revert) |
| **CI** | testes automáticos do workflow em cada push | nada no site | — |
| **Deploy** | processo completo: backup → SQL (se houver) → variáveis → workflow → aprovação → desligar variáveis | GitHub Actions | republicando o SHA anterior |
| **Publicar** | etapa final do deploy: cópia dos arquivos para o InfinityFree | site oficial | republicando o SHA anterior |

Regras práticas:

- **Cada lote** gera commit + push + PR + merge. Isso é barato e reversível.
- **Publicar** é no máximo **1 vez por dia**, de preferência ao concluir uma funcionalidade, e sempre com **backup verificado antes**.
- **CI verde não é publicação.** Significa apenas que o código passou nos testes.
- "Deploy no GitHub" não existe: para o GitHub se faz push/merge.

## 4. Autorizações e limites

**Sem nova autorização:** ler repositório, GitHub Actions e documentação; analisar e propor; fazer pesquisas públicas; inspecionar backup **somente leitura** se computador estiver autorizado.

**Com autorização da tarefa:** criar/alterar arquivos, branches, commits, PRs e merges no fork; executar testes de escrita em contas de QA, publicar; operações no InfinityFree ou banco; copiar arquivos de produção; instalar ferramentas no computador; criar/alterar backups.

**Aprovação especial para riscos:** qualquer alteração na `main`, repositório original, restauração destrutiva, SQL em banco com dados, renomeações/migrações de tabela e mudança de workflow/Secrets requer justificativa, confirmação do alvo e autorização explícita. Não enviar credenciais ao chat. Não assumir aprovação do Gabriel para unificar repositórios.

## 5. Registro do que aconteceu e do que NÃO foi comprovado

- **13/11:** a publicação controlada já funcionou via Actions/FTPS: [run 43518952935](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935), SHA `f807327811895bc97827929f4e30c2e60a180110`. Backups daquela data foram feitos **antes** da publicação e não refletem os cadastros da auditoria de 16/11.
- **16/11:** QA autenticado realizado em outra sessão com ZERO Browser MCP foi **registrado como relato** em [STATUS.md](STATUS.md): criação de duas contas de teste, cadastro, login, persistência de registros, conflitos e cancelamentos em cenários delimitados. Não é certificação geral.
- **16/11:** PRs #3 (auditoria), #4 (correção CSS do menu), #5 (registro QA), #6 (guia de deploy) integrados à `dev-feira-uninorte`. Após #6, SHA `040ba53be17bc655438f3ee1a30765b950250ca1`, CI de [run 43960024149](https://github.com/Emds96/AgenDay-PI/actions/runs/43960024149) aprovada, **`publicar=skipped`**.
- **16/11, verificação documental:** Variables do repositório `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`; Secrets FTP listados (nomes apenas) e environment `producao-agenday` com aprovação. Precisa ser reconfirmado no momento de publicar.
- **16/11, outra sessão:** pasta privada de backup preparada em `%USERPROFILE%\Documents\AgenDay-Backups\2026-11-16` com `STATUS_BACKUP_PENDENTE.txt`. Inspeção somente leitura confirmou **sem backups atuais** nessa pasta. Os ZIPs de 13/11 têm integridade de arquivo, mas são antigos/incompletos.
- **Limitação desta fotografia de 16/11:** naquele momento ainda não havia confirmação do último SHA PHP implantado, backup atual do site/banco, visual do menu, concorrência simultânea ou cobertura total de segurança. **Atualização posterior (17/11):** backups completos foram aprovados com restauração local, o deploy da QA-03 no commit de aplicação `5749f333` foi confirmado, a troca de remetente Brevo foi publicada e o responsável comprovou mensagem real com `De: agendaysistema@12832884.brevosend.com` na caixa de entrada. Menu e segurança continuam exigindo regressão ampliada. Fonte: [STATUS.md](STATUS.md) e [QA03_RECUPERACAO_SENHA_BREVO.md](QA03_RECUPERACAO_SENHA_BREVO.md).

Nenhum procedimento de continuidade ou arquivo documental autoriza automaticamente publicar ou testar operações destrutivas.

## 6. Comunicação e decisões de produto

O Gabriel mantém o projeto original e relatou trabalhar em agenda/dashboard; permitiu o fork, mas pediu preservação do padrão visual. **Não foi acordada integração automática de códigos/repositórios**. Antes de copiar soluções ou alterar o design, alinhar escopo e autoria com ele.

Há dois papéis de usuário previstos:
- **Estabelecimento:** conta administrativa existente; login, clientes, serviços, funcionários e agenda.
- **Cliente final:** página pública para reservar diretamente pelo link do estabelecimento, **ainda não criada no nosso fork** (não confundir com `cad-cliente.php`, que é cadastro administrativo).

**Decisões em aberto:** formato do link público por estabelecimento (`/agendar/slug` é só exemplo); agendamento como convidado versus conta do consumidor; identidade/verificação, confirmação, anti-spam, cancelamento e UX; eventual reutilização do design da página pública do Gabriel. **Não implementar essas escolhas sem alinhamento.**

## 7. Como iniciar outra sessão

Para uma IA ou pessoa nova, o caminho mais curto é [PASSAGEM_DE_CONTEXTO.md](PASSAGEM_DE_CONTEXTO.md) (estado, regras, equivalência de ferramentas e checklist de acessos) + [AMBIENTE_LOCAL.md](AMBIENTE_LOCAL.md) (se o trabalho for no PC). O antigo `HANDOFF-CHATGPT.md`, na pasta `AgenDay-Local`, está vencido e é apenas histórico.

Mensagem de retomada sugerida:

> Trabalhe no AgenDay 2.0, repositório `Emds96/AgenDay-PI`, branch `dev-feira-uninorte`, pasta local `C:\Users\EDER\AgenDay-PI`. Antes de agir, leia `AGENTS.md`, `docs/PASSAGEM_DE_CONTEXTO.md`, `docs/AMBIENTE_LOCAL.md`, `docs/GUIA_DE_CONTINUIDADE.md`, `docs/INDICE_DA_DOCUMENTACAO.md` e o topo de `docs/STATUS.md`. Para a tarefa, leia o runbook específico de backup, QA ou deploy. Confira HEAD, PRs, execução CI, Variables e possível atividade simultânea de outra sessão. Diferencie integrado/testado/publicado, não use `main`, não altere código/banco/infraestrutura sem minha autorização. Comece pelo estado real e informe a próxima ação segura.

**Ao terminar qualquer trabalho:** atualizar status, links e pendências **no repositório**, não apenas no texto da conversa. Se uma etapa falhar, registrar *o que foi tentado*, evidência e bloqueio; não afirmar conclusão e não contornar segurança.
