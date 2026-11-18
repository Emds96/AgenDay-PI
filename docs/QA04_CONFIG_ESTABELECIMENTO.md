# QA-04 — Configurações do estabelecimento (18/11/2026)

## Escopo autorizado

O responsável autorizou implementação, integração e deploy:
1. Nome, e-mail e telefone reais da conta autenticada.
2. Editar somente nome e telefone. E-mail de login somente leitura.
3. Salvar no MySQL, com validações e mensagens.
4. Exigir sessão, CSRF e atualizar somente o estabelecimento da sessão, por consultas preparadas.
5. Remover exemplos da seção básica, corrigir erro JavaScript e adaptar ao celular.

**Fora de escopo:** edição de e-mail, CNPJ, endereço, horários, foto/logo, dados do proprietário e novas tabelas/migrações.

## Implementação

- config.php: leitura e atualização SELECT/UPDATE limitadas ao ID de estabelecimento da sessão. Apenas nome e contato editáveis; e-mail real em somente leitura, com escape HTML. Feedback seguro de sucesso/erro.
- lib/configuracao-estabelecimento.php: validação do nome (2–120 caracteres), telefone opcional (8–15 dígitos, máximo 20 caracteres) e escape HTML.
- js/button-add.js: corrige exceção antiga de evento sem elemento e referência inválida ao campo de contato. Formulário básico não depende desse script.
- tests/configuracao-estabelecimento.php: teste isolado das validações e escape HTML.
- Atualização de sessão do nome após gravação, para atualizar o cabeçalho da agenda e cadastros imediatamente.
- POST + token CSRF da sessão + prepared statement + POST/Redirect/GET. Nem e-mail nem ID de estabelecimento são aceitos como campos editáveis.
- Formulário Bootstrap 5 responsivo; nenhuma informação ilustrativa de endereço, horário e redes sociais é exibida como real.

## Resultados da validação (18/11/2026)

- [x] PR #26 integrado em dev-feira-uninorte, SHA 5f2c8666. GitHub PHP lint #44365662706 e validação do pacote #44365662758 aprovadas.
- [x] Backup 2026-11-18_140222_e2169559 com BACKUP OK, 89 arquivos, 10 tabelas, SQL/ZIP com hash confirmado e restauração MariaDB local aprovada.
- [x] Workflow de deploy #44367305044 concluiu success nos três jobs; flags verificadas false/disabled.
- [x] QA em produção na conta fictícia QA01: dados reais carregados; nome e telefone gravados; mensagem de sucesso; persistência após recarga; nome atualizado no cabeçalho.
- [x] Dados anteriores da conta QA01 restaurados e conferidos após recarga.
- [x] CSRF inválido rejeitado com HTTP 403, nome curto/telefone inválido rejeitado com feedback, sem alteração de dados.
- [x] Teste de layout em 320/390/768/1366 px sem overflow horizontal.
- [ ] Teste independente tests/configuracao-estabelecimento.php ainda não executado como unidade separada; sintaxe PHP coberta pela CI.
- [ ] Teste cruzado A/B, simulação de falha de banco e validação humana ainda não executados nesta tarefa.

## Rastreabilidade

- PR: https://github.com/Emds96/AgenDay-PI/pull/26
- Commit publicado: 5f2c8666df9d3e63828509b4dd923f58020aac28
- Deploy: https://github.com/Emds96/AgenDay-PI/actions/runs/44367305044
- Evidências E2E: teste direto realizado por DeskBrowse no Edge em 18/11/2026. Não é relato de aprovação do responsável.

## Limitações

Testes isolados/CI não são prova de persistência no MySQL de produção. A tela perfil.php e ajustes de localização/horário ficam para outra etapa. Não publicar credenciais, dados de terceiros ou dumps.

**Estado:** publicado e validado no fluxo principal da conta fictícia QA01 em 18/11/2026; validação humana e cobertura ampliada pendentes.
