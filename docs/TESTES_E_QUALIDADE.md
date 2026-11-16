# Testes e qualidade (QA) — AgenDay 2.0

**Revisado em 16/11/2026.** Complementa [STATUS.md](STATUS.md), onde constam os resultados e defeitos reportados. Este documento é um **roteiro**: nenhuma etapa marcada aqui significa que um novo teste foi executado.

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
node tests/responsividade-check.js
```

Workflows: `.github/workflows/php-lint.yml` e `.github/workflows/agenday-publicar.yml` (job `validar`). **Testes não acessam o MySQL do InfinityFree.** O teste de responsividade é estrutural; não substitui navegador real.

## 3. Ferramentas para auditoria online

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
| QA-01 | Menu offcanvas fora da tela | **PR #4 integrado no GitHub; não publicado/validado na produção na última verificação** |
| QA-02 | `alterar-senha.php` apresenta formulário de cliente, não troca de senha | Pendente |
| QA-03 | Link “Esqueceu a senha?” sem recuperação | Pendente; não oferecer reset inseguro |
| QA-04 | Perfil/configuração com dados ilustrativos | Pendente |
| QA-05 | Comandas, histórico e financeiro sem fluxo funcional comprovado | Definir escopo MVP ou ocultar da demonstração com autorização |
| QA-06 | Erro JavaScript em `js/button-add.js` | Pendente |
| QA-07 | Nome “Barbearia” fixo em cabeçalhos legados | Pendente |
| QA-08 | Pequeno overflow horizontal em modais | Pendente |

**Regra de trabalho:** preferir um bug por branch/PR e integração sequencial na dev. Depois de publicar QA-01, verificar menu visualmente antes de declarar resolvido. Priorizar o prazo de 24/11 sem ocultar pendências.

## 6. Evidência anterior e limites

O [STATUS](STATUS.md) descreve auditoria autenticada em 16/11 **relatada por outra sessão** (ZERO Browser), com dois estabelecimentos e operações de gravação/cancelamento em ambiente de teste. Também houve auditorias **somente leitura** via TinyFish no sistema do Gabriel e no nosso: navegação, páginas e possíveis defeitos; esses relatórios **não testaram INSERT/UPDATE/DELETE**.

Quando um relatório automatizado conflitar com código ou QA comprovado, **verificar novamente antes de afirmar**. Exemplo: um relatório TinyFish não enxergou o cadastro inicial, mas `index.php` tem formulário `Criar Conta` — não registrar ausência como fato.

**Não testados integralmente:** concorrência simultânea, toda a superfície de autorização, recuperação de senha, versão PHP exata em produção e todos os dispositivos/navegadores. “Nenhum erro encontrado” na navegação não significa “sem vulnerabilidades”.

## 7. Registro padrão de conclusão de QA

Após teste autorizado, registrar sem senhas/dados pessoais:

- Data, ambiente, URL, branch/SHA (quando identificável), versão de navegador e largura.
- Cenário e pré-condições, ações realizadas, esperado/observado, evidência e IDs fictícios, resultado PASS/FAIL/BLOCKED/NOT_TESTED.
- Persistência após recarga, impacto na conta QA e eventual limpeza autorizada.
- Novo defeito (ID, prioridade, reprodução), limitação de cobertura e responsável pelo próximo passo.
- Link para run GitHub Actions e `docs/STATUS.md` atualizado. Não publicar prints com tokens, hashes, `conexao.php` ou dados de terceiros.
