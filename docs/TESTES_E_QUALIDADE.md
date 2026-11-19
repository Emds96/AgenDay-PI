# Testes e qualidade (QA) — AgenDay 2.0

**Revisado em 18/11/2026** após a validação do login automático das contas QA A/B via DeskBrowse. Complementa [STATUS.md](STATUS.md), com a evidência de cada resultado. Este documento reúne **roteiro + estado dos defeitos**; nenhum caso de teste adicional é presumido executado apenas por constar aqui.

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
php tests/configuracao-estabelecimento.php
php tests/alteracao-senha-validacao.php
php tests/avatar-estabelecimento.php
node tests/responsividade-check.js
```

Workflows: `.github/workflows/php-lint.yml` e `.github/workflows/agenday-publicar.yml` (job `validar`). **Testes não acessam o MySQL do InfinityFree.** O teste de responsividade é estrutural; não substitui navegador real.

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

## 9. UI-09 — Menu hambúrguer padronizado (implementação autorizada)

- O menu deve ter título **Menu**, botão X à esquerda, somente **Agenda, Clientes, Serviços, Funcionários e Configurações** (nesta ordem), ícones com identidade verde original, seleção com fundo verde-claro e **Sair** destacado na parte inferior. Os módulos Comandas/Financeiro existem, mas **não têm entrada no menu simplificado**. A conta superior continua intacta neste escopo.
- **Verificação estrutural executada localmente:** 144 checks em `node tests/responsividade-check.js`; oito páginas autenticadas usam o mesmo `lib/menu-lateral.php` e CSS responsivo versionado. `python scripts/preparar_publicacao.py` empacota o componente e exclui dados privados; pacote local com 89 arquivos.
- **Critérios de QA após publicação:** abertura/fechamento por toque, botão X e Escape; título e sequência de itens; destaque da rota ativa em Agenda/Clientes/Serviços/Funcionários/Configurações; footer Sair no limite inferior; navegação sem modificar cadastros; 320/360/390/412/768/1366px e ausência de overflow. Preservar o botão da conta, marca do cabeçalho, página Configurações e outros módulos. Registrar separadamente sucesso de CI, deploy e validação humana.
