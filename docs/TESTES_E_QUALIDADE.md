# Testes e qualidade (QA) — AgenDay 2.0

**Revisado em 23/11/2026** após o deploy `c8fa689` (migração `segmento`, máscara de 11 dígitos, spinner e rolagem do login). Complementa [STATUS.md](STATUS.md), com a evidência de cada resultado. Este documento reúne **roteiro + estado dos defeitos**; nenhum caso de teste adicional é presumido executado apenas por constar aqui.

## QA FIX-01 — Reenvio de cadastros (19/11/2026)

- **Código e CI:** `tests/cadastro-reenvio.php` executado pelo `tests/responsividade-check.js` no GitHub Actions; 11 verificações anti-replay aprovadas, 164 verificações estruturais, lint PHP aprovado. PR #40, SHA de aplicação `0eb23f1d2e21562a5e326518a31ea8c80fbef9bf`.
- **Deploy com evidência:** run [44662655163](https://github.com/Emds96/AgenDay-PI/actions/runs/44662655163), os três jobs concluídos em success, backup anterior com 105 arquivos e 10 tabelas/restauração local; flags em false/disabled.
- **E2E real em conta de QA:** criação de um cliente e um funcionário (este com serviço vinculado), reenvio de `POST` com **o mesmo token** rejeitado pelo PHP em ambos os casos. A listagem e consulta direta ao MySQL confirmaram apenas um novo ID para cada tentativa (cliente 12 e funcionário 15 no estabelecimento de QA 19). Não houve exclusão de registros nem alteração de SQL.
- **Limite do teste:** não simulou perda de rede nem duas sessões diferentes cadastrando dados iguais; o mecanismo impede reuso da mesma tentativa na mesma sessão, não é um índice global de nomes/telefones. A validação do responsável no celular permanece pendente.

## 1. Quatro níveis diferentes de evidência

1. **Existe em código / tela:** arquivo, componente, endpoint ou formulário presente.
2. **Verificação estática e testes locais:** sintaxe PHP, unitários, scripts Node; não exercem o MySQL remoto.
3. **CI concluída:** GitHub Actions aprovou os passos previstos; **não é deploy** nem prova operação online.
4. **E2E hospedado, com operações reais:** navegar no InfinityFree com conta de teste, executar a operação autorizada, observar resposta, persistência após recarga, isolamento e efeitos.

Um agente deve reportar **qual nível foi atingido**, identificando ferramenta, data, SHA de referência, conta fictícia e restrições. Não concluir “funcional” apenas por botão presente ou sucesso HTTP 200.

## 2. Testes disponíveis no GitHub

```bash
find . -type f -name '*.php' -not -path './deploy-package/*' -print0 | xargs -0 -n 1 php -l
php tests/agendamento-validacao.php
php tests/agendamento-cancelamento.php
php tests/portal-cliente.php
php tests/google-identidade-validacao.php
php tests/recuperacao-senha-test.php
php tests/configuracao-estabelecimento.php
php tests/alteracao-senha-validacao.php
php tests/avatar-estabelecimento.php
php tests/cadastro-reenvio.php
node tests/responsividade-check.js
```

Workflows: `.github/workflows/php-lint.yml` e `.github/workflows/agenday-publicar.yml` (job `validar`). **Testes não acessam o MySQL do InfinityFree.** O teste de responsividade é estrutural; não substitui navegador real.

`tests/portal-cliente.php` (**159 verificações**) cobre normalização do celular com o 9 e formato antigo, variantes de busca, slug, código de acesso, token público, sessão do cliente, CSRF, conferência de origem, prazo de cancelamento, endereço público (normalização, reservado, tamanho, link sem slug e domínio padrão), assinatura das mensagens do WhatsApp ("Enviado pela AgenDay em nome de …") nos cinco tipos de texto, a distinção entre celular com WhatsApp e telefone fixo (TEL-01), o lembrete automático de 24h (título próprio, prazo encerrado, telefone do estabelecimento, sem link e ligação nos fluxos — LEMBRETE-01/MENSAGENS-02), a confirmação com prazo de 24h e um único link do portal, com fallback para o token (MENSAGENS-02) e o domínio compartilhado do portal, que nunca identifica estabelecimento (PORTAL-RAIZ-01).

O histórico de faltas e cancelamentos (FEE-01/FEE-02) **não tem teste no CI**, porque depende do MySQL: foi provado com 34 verificações contra o banco local (`lib/clientes-ausencias.php` — contadores, marca de taxa idempotente, cancelamento com e sem marca, isolamento entre estabelecimentos) mais a renderização real de `cad-cliente.php` e de `agenday_agenda_listar()`. Os testes do CI continuam sem acesso ao banco.

## 3. Ferramentas para auditoria online

- **DeskBrowse:** procedimento principal para autenticação autônoma nas contas fictícias usando apenas `agenday-conta-a` e `agenday-conta-b` no cofre Windows. Conferir `bridge_status` → logout da sessão já aberta → `login_salvo` → `browser__execute` na agenda. Em 18/11/2026 os logins A/B foram confirmados na hospedagem com identidades distintas, inclusive logout entre eles; ver [DESKBROWSE_LOGIN_QA.md](DESKBROWSE_LOGIN_QA.md). **Isso não equivale a aprovar isolamento dos endpoints.**

- **ZERO Browser MCP:** preferido para QA visual/funcional no navegador autorizado; testar desktop e dimensões de celular.
- **TinyFish:** utilizado para auditoria exploratória de leitura de páginas, sem submits. Não considerar o relatório do agente prova de gravação; conferir achados de segurança no código.
- Navegador real com JavaScript é importante porque o InfinityFree pode apresentar desafio anti-bot para clientes HTTP diretos.
- **Sem autorização de escrita**, limitar-se a login, navegação, leitura, abrir menus/modais sem salvar e observar o que existe. Criação, edição, cancelamento, exportação, exclusão ou manipulação de dados são testes com **efeitos persistentes**: confirmar escopo e usar contas próprias/fictícias.
- Não publicar credenciais de teste em `docs/` nem usar a conta/projeto do Gabriel como laboratório. Se o usuário fornecer acesso ao sistema do Gabriel, limitar-se à autorização expressa e preservar seus dados.

## 4. Roteiro principal após deploy autorizado

**Preparação:** conferir SHA publicado, run `publicar=success`, Variables `false/disabled`, backups verificados, dados e conta QA controlados. Registrar horários no fuso `America/Manaus`, se a configuração não foi alterada.

| Etapa | Cenário | Critério de aceite |
| --- | --- | --- |
| Acesso | Cadastro de estabelecimento, login, logout, login inválido, cadastro inválido e CSRF | Acesso autenticado apenas ao próprio estabelecimento e mensagens corretas |
| Cadastros | Criar e pesquisar cliente/serviço/funcionário, fotos opcionais, vínculo funcionário-serviço | Dados persistem após atualizar; rejeitam formatos/IDs inválidos |
| Agendamento | Serviço, funcionário vinculado, data futura, duração e horário válido | Salva com início/fim corretos; surge no calendário |
| Conflito | Dois atendimentos sobrepostos para o mesmo funcionário | Segundo rejeitado sem duplicidade |
| Cancelamento | Cancelar um atendimento ativo e tentar reutilizar horário | Status cancelado preservado e horário liberado; sem excluir histórico |
| Isolamento | Duas contas de estabelecimentos distintas | Nenhum dado da A exibido/alterado por B; testar também endpoints conforme autorização |
| Segurança | Rotas/JSON sem sessão, CSRF inválido, IDs de outra empresa, uploads | Operações indevidas recusadas sem vazar dados |
| Responsividade | 320, 390, 768 e 1366 px; menu, backdrop, modais, login, calendário | Menu abre/fecha dentro da tela, sem overflow ou bloqueio |
| Telas legadas | Configurações, perfil, senha, financeiro e comandas | Diferenciar demonstrativo de funcional; registrar erros sem inventar operação |
| Recuperação | Atualizar e reabrir páginas com sessão válida; falhas de rede | Feedback coerente e ausência de operação duplicada |

Dados QA devem ser **fictícios**; alterações, exclusões e limpeza de registros devem ser autorizadas. Testes de concorrência simultânea podem requerer duas sessões; teste sequencial não comprova o bloqueio sob corrida.

## 5. Defeitos conhecidos e prioridade

Registro-fonte: [STATUS.md](STATUS.md). Situação na última revisão:

| ID | Problema | Situação |
| --- | --- | --- |
| QA-01 | Menu offcanvas fora da tela | PR #4 integrado e incluído nos deploys posteriores; falta regressão visual completa do menu em múltiplos tamanhos |
| QA-02 | `alterar-senha.php` não trocava senha | **Fluxo principal corrigido, publicado e aprovado manualmente** em produção; faltam testes negativos/CSRF e sessões paralelas |
| QA-03 | Recuperação de senha e e-mail transacional | **Fluxo principal aprovado manualmente**, telas padronizadas com aviso de spam, novo remetente verificado e mensagem real recebida na caixa de entrada com `agendaysistema@12832884.brevosend.com` (17/11/2026). Pendentes: reutilização/expiração, limites e entregabilidade multi-provedor |
| QA-04 | Perfil e configurações ilustrativos | Configuração básica publicada via PR #26/deploy #44367305044, testada em produção com QA01: edição, persistência, CSRF, inválidos e layout. Perfil e configurações avançadas pendentes; ver guia QA-04 |
| QA-05 | Comandas, histórico e financeiro sem fluxo funcional comprovado | Definir escopo MVP ou ocultar da demonstração com autorização |
| QA-06 | Erro JavaScript em `js/button-add.js` | Pendente |
| QA-07 | Nome “Barbearia” fixo em cabeçalhos legados | **Corrigido no código e publicado:** [PR #29](https://github.com/Emds96/AgenDay-PI/pull/29) + correção de cache [PR #30](https://github.com/Emds96/AgenDay-PI/pull/30), deploy final [#44414427979](https://github.com/Emds96/AgenDay-PI/actions/runs/44414427979), commit `7a617429`. QA real no Edge (conta fictícia B): desktop 1366px com nome no topo, mobile 412/320px sem nome no botão, dropdown correto e sem overflow; 10 páginas autenticadas conferidas em 412px. **Aprovado visualmente pelo responsável em 18/11/2026** (relato “ok tudo certo”, sem detalhes do dispositivo usado). Testes adicionais de teclado/leitor de tela e regressão em dispositivos reais continuam pendentes. |
| QA-08 | Pequeno overflow horizontal em modais | Pendente |

**Regra de trabalho:** preferir um bug por branch/PR e integração sequencial na dev. Depois de publicar QA-01, verificar menu visualmente antes de declarar resolvido. Priorizar o prazo de 24/11 sem ocultar pendências.

## 6. Evidência anterior e limites

O [STATUS](STATUS.md) descreve auditoria autenticada em 16/11 **relatada por outra sessão** (ZERO Browser), com dois estabelecimentos e operações de gravação/cancelamento em ambiente de teste. Também houve auditorias **somente leitura** via TinyFish no sistema do Gabriel e no nosso: navegação, páginas e possíveis defeitos; esses relatórios **não testaram INSERT/UPDATE/DELETE**.

Quando um relatório automatizado conflitar com código ou QA comprovado, **verificar novamente antes de afirmar**. Exemplo: um relatório TinyFish não enxergou o cadastro inicial, mas `index.php` tem formulário `Criar Conta` — não registrar ausência como fato.

**Não testados integralmente:** concorrência simultânea, toda a superfície de autorização, casos negativos da recuperação de senha (reutilização/expiração/limites), versão PHP exata em produção e todos os dispositivos/navegadores. O fluxo principal da recuperação **já foi aprovado manualmente**; não confundir isso com aprovação de todos os casos de segurança.

## 7. Registro padrão de conclusão de QA

**Gatilho obrigatório:** ao receber confirmação ou captura de validação do responsável, conferir fonte e resultado, **integrar o registro em `STATUS.md` e no manual da funcionalidade antes de responder “concluído”**. Se houver alterações em procedimento, interface ou arquitetura, atualizar também seus resumos ativos. Não reexecutar o teste humano apenas para documentá-lo, nem confundir relato com teste automatizado.

Após teste autorizado, registrar sem senhas/dados pessoais:

- Data, ambiente, URL, branch/SHA (quando identificável), versão de navegador e largura.
- Cenário e pré-condições, ações realizadas, esperado/observado, evidência e IDs fictícios, resultado PASS/FAIL/BLOCKED/NOT_TESTED.
- Persistência após recarga, impacto na conta QA e eventual limpeza autorizada.
- Novo defeito (ID, prioridade, reprodução), limitação de cobertura e responsável pelo próximo passo.
- Link para run GitHub Actions e `docs/STATUS.md` atualizado. Não publicar prints com tokens, hashes, `conexao.php` ou dados de terceiros.

## 8. UI-08 — Configurações em duas abas (publicada, QA inicial parcial)

Documento específico: [CONFIGURACOES_UI08.md](CONFIGURACOES_UI08.md). **Não confundir esta etapa UI-08 com o defeito QA-08 de overflow de modais**, mantido separadamente.

- **Código e CI:** PR #33 integrado ao commit `5637ef4`; PHP lint, testes de config/senha/avatar, Node e pacote aprovados no [run #44428652461](https://github.com/Emds96/AgenDay-PI/actions/runs/44428652461), com PHP lint adicional [#44428652349](https://github.com/Emds96/AgenDay-PI/actions/runs/44428652349).
- **Publicação comprovada:** backup `2026-11-18_201846_a7eb4bb3` BACKUP OK (91 arquivos, 10 tabelas, restauração local e hashes); [deploy #44428793171](https://github.com/Emds96/AgenDay-PI/actions/runs/44428793171) sucesso em 3 jobs; flags novamente `false/disabled`.
- **Navegação real no Edge, conta B (sem gravar):** duas abas e um painel por vez; 320/360/390/412/768/1366px sem overflow; Space Grotesk 500 carregada somente para marca e outras fontes preservadas; telefone testado apenas no frontend; e-mail presente/readonly; modal abre com foco na senha atual, Escape/Cancelar/X fecham quando transição concluída e restauram foco; abas respondem a setas; dez rotas administrativas em mobile sem erros PHP visíveis, redirects legados funcionando; diretório de avatares não lista conteúdo (HTTP 403).
- **Pendente com efeitos persistentes:** salvar nome/telefone no banco, upload e atualização real da foto, senha errada/sucesso, isolamento A/B, QA humana em dispositivo, leitor de tela e regressão funcional de outras páginas. A conta A não foi acessada nesta rodada. Não extrapolar os resultados de navegação.

## 9. UI-09 — Menu hambúrguer padronizado (publicado, QA inicial no Edge)

- O menu deve ter título **Menu**, botão X à esquerda, somente **Agenda, Clientes, Serviços, Funcionários e Configurações** (nesta ordem), ícones com identidade verde original, seleção com fundo verde-claro e **Sair** destacado na parte inferior. Os módulos Comandas/Financeiro existem, mas **não têm entrada no menu simplificado**. A conta superior continua intacta neste escopo.
- **Verificação estrutural executada localmente:** 144 checks em `node tests/responsividade-check.js`; oito páginas autenticadas usam o mesmo `lib/menu-lateral.php` e CSS responsivo versionado. `python scripts/preparar_publicacao.py` empacota o componente e exclui dados privados; pacote local com 89 arquivos.
- **CI e deploy executados:** PR [#35](https://github.com/Emds96/AgenDay-PI/pull/35), merge `f545f15565e98568ad72a63ada9e90d4e7ffa0f8`; CI de tarefa [#44454381950](https://github.com/Emds96/AgenDay-PI/actions/runs/44454381950) e pós-merge [#44454449838](https://github.com/Emds96/AgenDay-PI/actions/runs/44454449838)/[#44454449889](https://github.com/Emds96/AgenDay-PI/actions/runs/44454449889), sucesso. Backup `2026-11-18_234446_3ce8f750` BACKUP OK (96 arquivos, 10 tabelas, restauração local e hashes); deploy [#44454541668](https://github.com/Emds96/AgenDay-PI/actions/runs/44454541668) sucesso; flags `false/disabled` verificadas após retry da falha HTTP 502.
- **Navegação/estilo em produção no Edge:** `config.php` em 320/360/390/412/768/1366px: menu visível após o carregamento e transição, sem overflow, título **Menu**, seis links na ordem (cinco destinos + Sair), rodapé ancorado, opção atual destacada, Escape fecha. Nas oito rotas administrativas, os itens e menu de conta superior permanecem presentes, com marca do cabeçalho preservada; para três leituras que ocorreram antes da conclusão do carregamento, a abertura foi repetida com `waitUntil=load` e passou. Configurações → Clientes navegou corretamente e selecionou Clientes; X e backdrop também fecharam.
- **Limites do QA:** o logout real não foi clicado e nenhuma gravação no banco foi testada (fora do escopo). Aprovação visual humana em Chrome Android/telefone real do responsável, contraste com imagem aprovada e testes com leitor de tela ainda pendentes.

## 10. UI-10 — Remoção pontual do dropdown da conta (publicada, QA inicial aprovado)

- **Escopo:** oito páginas administrativas deixam de incluir o avatar/nome/seta/dropdown da conta no canto superior direito; hambúrguer, Configurações e Sair no componente `lib/menu-lateral.php`, marca Space Grotesk e informações persistidas permanecem. As duas abas de Configurações e a foto salva não são alteradas.
- **Verificações locais reais:** 159 testes estruturais passaram em `node tests/responsividade-check.js`; pacote de 88 arquivos preparado com o hambúrguer presente e sem o componente PHP antigo; `git diff --check` aprovado. Isso não prova renderização ou deploy.
- **Publicação comprovada:** PR #37 integrado no SHA `22ff46775d4c373159538b4a6436e1e67fd752e8`; CI [#44519684723](https://github.com/Emds96/AgenDay-PI/actions/runs/44519684723), [#44522416531](https://github.com/Emds96/AgenDay-PI/actions/runs/44522416531) e [#44522416532](https://github.com/Emds96/AgenDay-PI/actions/runs/44522416532) aprovadas. Backup privado `2026-11-19_081115_3ea3891b` BACKUP OK (97 arquivos, 10 tabelas, restauração local e hashes); deploy [#44522593229](https://github.com/Emds96/AgenDay-PI/actions/runs/44522593229) com 3 jobs `success`; flags `false/disabled` restauradas.
- **QA real read-only no Edge:** página Configurações em 320/360/390/412/768/1366px com centro do título deslocado 0px do centro do cabeçalho em todos os casos; duas únicas peças no topo (botão do menu e marca), sem conta, avatar, seta ou dropdown, fonte Space Grotesk 500 e sem overflow. As oito páginas administrativas em 412px também passaram (marca centralizada 0px, CSS UI-10, menu mantido, nenhum erro PHP visível). Hambúrguer abriu e fechou por Escape/X; links Configurações e Sair presentes. Navegação Agenda → Configurações passou; abas Estabelecimento/Minha Conta e foto/upload da conta mantidos.
- **Ainda pendente:** aceite visual em celular físico do responsável, ativação real do link Sair e leitores de tela/navegadores adicionais. Nenhuma alteração de dados foi feita neste QA.

## 11. PORTAL-01 a PORTAL-05 — Portal do cliente (publicado; fluxo principal validado em produção)

- **Escopo:** domínio do cliente com `cliente.php` e `cliente-cancelar.php`; login por celular (código de 6 dígitos só no primeiro acesso); agendamento com serviço e profissional em menu e dia em calendário; remarcação; cancelamento logado e por link; fila de WhatsApp (WAHA) com cinco tipos de mensagem.
- **Testes automatizados:** `tests/portal-cliente.php` (100 verificações, incluindo host local, links locais, assinatura do formulário de cancelamento e autorização do POST sem `Origin`) mais `agendamento-validacao` (19), `agendamento-cancelamento` (20) e `responsividade-check` (210), executados na CI e no local; `php -l` e `node --check` limpos nos arquivos alterados.
- **Validação local (21/11/2026):** fluxo completo em 1366 px e 360 px, com agendamento real gravado e cancelado em seguida; sem rolagem horizontal em 360 px. Envio real pelo WAHA/GOWS com número novo e antigo (HTTP 201) e ciclo completo com WhatsApp real.
- **Validação em produção (21/11/2026, relato do responsável + conferência no banco):** conta criada pelo portal com o código recebido no WhatsApp, agendamento confirmado (id 189, 23/11 15:00–17:00) e mensagem `confirmacao` `enviado` em 3 segundos; cliente novo no Studio (17 para 18).
- **Ainda pendente:** cancelamento pelo link no **celular físico** em produção e leitores de tela.

### PORTAL-06 — correções no link de cancelamento e no aviso de remarcação (21/11/2026)

- **Defeito 1 (reproduzido):** POST sem `Origin`/`Referer` devolvia `403 Pedido não reconhecido`. A página usa `Referrer-Policy: no-referrer` e dependia só do cabeçalho. Agora o formulário envia `assinatura` (`sha256` do token) e o `Origin` só é exigido quando o navegador o manda: com assinatura correta e sem `Origin` → `200 Agendamento cancelado`; assinatura errada → `403`; `Origin` falso → `403`.
- **Defeito 2 (reproduzido):** mensagens geradas no PC levavam o domínio de produção com token só do banco local. Agora `agenday_portal_host_local()` faz os links apontarem para o host atual no desenvolvimento.
- **Envio imediato:** `agenday_whatsapp_enviar_agora()` entrega a mensagem recém-enfileirada sem esperar a trava de 1 minuto; confirmado com `mensagem_whatsapp.status = enviado` no mesmo instante.
- **Verificações:** 93 em `tests/portal-cliente.php`, 19 + 20 + 13 + 16 + 210 nos demais; `php -l` limpo. **Publicado** em 21/11/2026 no deploy do SHA `4dbf227` (run `44965206160`); falta o clique no celular físico.

### PORTAL-07 — "Pedido não reconhecido" ainda no celular (21/11/2026)

- **Relato:** depois do deploy do PORTAL-06 o botão continuava devolvendo `403 Pedido não reconhecido` no aparelho. Em produção a assinatura do formulário conferia com o `sha256` esperado, então o 403 vinha da exigência de `Origin`/`Referer` (navegador embutido manda `Origin: null` ou nada).
- **Correção:** `agenday_portal_cancelamento_autorizado()` aceita a **assinatura** sozinha **ou** um `Origin`/`Referer` do próprio site; `Referrer-Policy` passou a `same-origin`, que devolve o `Referer` ao próprio site sem vazar o token para o CDN.
- **Verificações:** `tests/portal-cliente.php` de 93 para **100**; demais 19 + 20 + 13 + 16 + 210; `php -l` limpo. HTTP local: sem `Origin` + assinatura → `200`; `Origin: null` + assinatura → `200`; sem nada → `403`; assinatura errada → `403`. **Publicado** em 21/11/2026 no deploy do SHA `b6887f6` (run `44967236148`) e **validado pelo responsável** no celular.

## 12. CONFIRMACAO-AGENDAMENTO-01 — confirmação de presença por link (22/11/2026)

- **Escopo:** link no lembrete de 24h e ação **Confirmar** no menu Ações do painel; página `cliente-confirmar.php?t=<token>`, sem login. Abrir a página não altera nada; só o POST assinado muda `agendado` para `confirmado`.
- **Testes automatizados:** `tests/portal-cliente.php` **159** (lembrete com link, assinatura e link de confirmação), `agendamento-validacao` 19, `agendamento-cancelamento` 20, `responsividade-check` 233; `php -l` limpo.
- **Prova HTTP local:** **7 verificações, 0 falha** — GET, assinatura inválida recusada com 403, confirmação real, repetição idempotente e confirmação pelo painel.
- **Teste com WhatsApp real (22/11/2026, PC local):** lembrete enviado pelo WAHA para o número do responsável, que abriu o link e confirmou; o banco passou a `confirmado`. Registro honesto: houve **dois envios**, embora só um estivesse autorizado — o primeiro saiu com o domínio público e um token que só existia no banco local. Os dados temporários foram removidos.
- **Publicado:** DEPLOY-05, SHA `6a9b48f` (22/11/2026).
- **Ainda pendente:** teste de ponta a ponta no celular em produção (lembrete real com link público), cancelamento por link no celular e leitores de tela.
