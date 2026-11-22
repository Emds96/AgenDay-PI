# Checklist — Portal do cliente

Atualizado em 21/11/2026. `[x]` = feito e verificado no PC local; `[ ]` = pendente.
Nada aqui significa publicado: o deploy é um passo separado, com autorização de SHA e destino.

## Regras que valem durante todo o trabalho

- [x] Trabalhar em branch própria (`feat/portal-cliente`), um PR para `dev-feira-uninorte`.
- [x] Não alterar `main`, secrets, workflow de deploy nem `conexao.php` de produção.
- [x] SQL de produção só com autorização e aplicação manual no phpMyAdmin.
- [ ] Deploy só com SHA e destino exatos, backup verificado e autorização.
- [x] Registrar em `docs/STATUS.md` no mesmo PR.
- [x] Atualizar este checklist a cada entrega.

## Fase 0 — Fundação

- [x] `sql/migracao-portal-cliente.sql` (seções 5.1 a 5.8 do plano).
- [x] Migração aplicada no banco local (`agenday_local`), duas execuções, sem erro (reexecutável).
- [x] `lib/portal-cliente.php` (slug, telefone, token, sessão, CSRF, origem).
- [x] `lib/guarda-host.php` (lista de domínios do cliente).
- [x] `lib/whatsapp.php` (configuração, fila, envio, templates, gatilho pós-resposta).
- [x] Telefone normalizado nos cadastros existentes (`cadastro-clientes.php`, `criar-cliente-rapido.php`, `editar-cliente.php`); 29/29 clientes normalizados no backfill.
- [x] `php -l` em tudo que foi criado/alterado.

## Fase 1 — Acesso do cliente

- [x] `api/cliente-acesso.php`: `estado`, `entrar`, `verificar`, `sair`.
- [x] Código de 6 dígitos, hash SHA-256 com salt, validade de 10 minutos, uso único.
- [x] Limites: 3/10 min por telefone, 10/dia por telefone, 20/hora por IP.
- [x] Sessão no banco + cookie `AGENDAY_CLIENTE` (30 dias, HttpOnly, SameSite=Lax).
- [x] Cliente criado no primeiro acesso (nome + telefone + código), sem duplicar por telefone.
- [x] Cliente já cadastrado entra só com o celular, sem código (regra de 21/11/2026).
- [x] `cliente.php` com as etapas de telefone e código (código só no cadastro).
- [x] Acesso em duas abas: **Entrar** (só o celular) e **Cadastro** (celular + nome); celular sem cadastro em Entrar leva a tela para Cadastro já preenchida.
- [x] Celular padronizado com o 9 (DDD + 9 + 8 dígitos); busca aceita a forma antiga para não duplicar cadastros.
- [x] Envio resolve o JID real do WhatsApp (`check-exists`), cobrindo números no formato antigo sem o 9.
- [x] Código enviado pelo caminho imediato (WAHA); falha de envio apaga a verificação.
- [x] Contingência: `exigir_codigo_whatsapp` desligado entra só com o celular, inclusive no primeiro acesso.
- [x] Testes de `agenday_portal_acesso_decidir` cobrindo as quatro combinações.

## Fase 2 — Agendar

- [x] `api/cliente-dados.php` (estabelecimento, serviços, profissionais, regras).
- [x] `api/cliente-horarios.php` (reusa `lib/disponibilidade.php`, sem recalcular regra).
- [x] `api/cliente-agendamentos.php` com `acao=criar` (transação + `FOR UPDATE`).
- [x] Etapas da página: serviço → profissional → data/hora → confirmação.
- [x] Token público gerado no agendamento (64 hex, único).
- [x] Mensagem de confirmação enfileirada e enviada na hora quando o WAHA responde.
- [x] Tela de sucesso com link de cancelamento e arquivo `.ics`.

## Fase 2.1 — Redesenho da escolha de serviço, profissional e dia (21/11/2026)

- [x] Serviço e profissional em menu dropdown, no visual de campo dos controles atuais.
- [x] Opção escolhida marcada com `aria-current` e ícone de check; painel com `role="group"` e rótulo da etapa.
- [x] Teclado: setas, `Home`, `End` e `Esc`; foco devolvido ao gatilho e levado à etapa seguinte ao escolher.
- [x] Dia escolhido em calendário, com navegação limitada ao mês atual e ao mês de `dataMax`.
- [x] Dias fora da janela desabilitados; hoje e dia escolhido destacados.
- [x] Horários carregados depois da escolha do dia, abaixo do calendário.
- [x] Painel do dropdown abre dentro do cartão abaixo de 576 px.
- [x] Fluxo completo validado em 1366 px e 360 px, com agendamento real no local e cancelamento em seguida.

## Fase 3 — Meus agendamentos e cancelar

- [x] `acao=listar` (próximos + últimos 90 dias).
- [x] `acao=cancelar` (sessão), respeitando `horas_cancelamento`.
- [x] `cliente-cancelar.php?t=token` (sem login, POST com conferência de origem).
- [x] Mensagem de cancelamento enfileirada.
- [x] Tela "Meus agendamentos" com status e motivo de bloqueio.

## Fase 4 — Remarcar

- [x] `acao=remarcar` em uma transação (bloqueio na ordem crescente de ID).
- [x] Validação de disponibilidade do novo horário e vínculo profissional × serviço.
- [x] Mensagem de remarcação enfileirada.
- [x] Interface de remarcação dentro do cartão do agendamento.

## Fase 5 — WhatsApp

- [x] Instalar Docker Desktop + WSL2 (com reinício autorizado) — guia: `docs/WHATSAPP_WAHA_GUIA.md`. Dados do Docker em `E:\Docker\wsl\disk`.
- [x] Subir o WAHA (motor **GOWS**, `devlikeapro/waha:gows` 2026.10.1) e parear o número: sessão `default` **WORKING**, conta `559294331267@c.us`.
- [x] Publicar o endereço HTTPS do túnel (ngrok dev domain `additionally-nondeluded-elane.ngrok-free.dev`) e testar de fora — envio real HTTP 201.
- [x] Aba "WhatsApp" em Configurações (URL, chave, sessão, ativo, exigir código, link do portal).
- [x] Salvamento com CSRF e validação dentro de `config.php` (`acao=salvar_whatsapp`) — não foi criado `salvar-whatsapp.php`; a tela já concentra os formulários das outras abas.
- [x] Botão "Testar conexão" e "Enviar pendentes agora".
- [x] Processamento da fila ao abrir a agenda/portal, depois de entregar a página (trava de 1 minuto).
- [x] Templates: código, confirmação, lembrete, remarcação e cancelamento.

## Fase 6 — Guarda de host e publicação

- [x] Guarda em `sessao.php` cobrindo todas as rotas administrativas.
- [x] `index.php` no domínio do cliente abre o portal.
- [x] Resolução por `?e=slug` e por domínio.
- [x] `scripts/publicar_ftp.py` publicando nas duas pastas (cliente primeiro).
- [x] Workflow sem alteração estrutural; teste de FTPS somente leitura confere as duas pastas.
- [x] Teste local com `Host` falso: `agenda.php`, `index.php`, `login.php`, `api/horarios.php`, `config.php` → 302 `/cliente.php`; `cliente.php`, `cliente-cancelar.php` e `api/cliente-*.php` → 200.

## Fase 7 — Testes e PR

- [x] `tests/portal-cliente.php` (telefone, slug, código, token, sessão, CSRF, origem, prazo) — 84 verificações.
- [x] Regressão: `tests/agendamento-validacao.php` (19) e `tests/agendamento-cancelamento.php` (20).
- [x] `node tests/responsividade-check.js` — 210 verificações (inclui as quatro abas de Configurações).
- [x] Fluxo completo por HTTP local: entrar com código, criar, listar, remarcar, cancelar, cancelar por link.
- [x] Envio real pelo WAHA/GOWS com número no formato novo e no antigo (HTTP 201 nos dois).
- [x] Ciclo completo com WhatsApp real: código, confirmação, remarcação e cancelamento (3 mensagens `enviado`).
- [x] `docs/STATUS.md` atualizado.
- [x] PR #64 aberto, revisado e integrado em `dev-feira-uninorte` (`1e670cd`).
- [x] CI verde: `PHP syntax check` e `Validacao e publicacao controlada` (run `44936376746`), com o teste do portal no workflow.
- [x] Validação do responsável no navegador local (21/11/2026): entrou com `92994331267` e criou a conta `92 98135-1207` na aba Cadastro, concluindo com o código recebido no WhatsApp.

## Fase 8 — Deploy e feira

- [x] Backup verificado e registrado: `2026-11-21_174618_aa74da7d` (`BACKUP OK`, 126 arquivos, 12 tabelas, restauração local aprovada).
- [x] Autorização de SHA e destino (21/11/2026): `4bd707aa33d4be09c5367b2745fffcb80872f96a`, as duas pastas (`htdocs` e `agenday-agendamentos.infinityfree.io/htdocs`).
- [x] Migração aplicada em produção (phpMyAdmin) e conferida: colunas `estabelecimento.slug/dominio/exigir_codigo_whatsapp`, `clientes.telefone_digits`, `agendamentos.token_publico`; índices `uq_estabelecimento_slug/dominio`, `idx_clientes_telefone`, `uq_agendamentos_token`; 4 tabelas novas; `whatsapp_config` com a linha 1; backfill de 30 dos 32 clientes (2 sem telefone).
- [x] Deploy único com as duas pastas: run `44953952790` (workflow_dispatch), `publicar` concluído em 4m18s e teste FTPS somente leitura aprovado.
- [x] Flags de deploy desligadas ao final (`AGENDAY_DEPLOY_ENABLED=false`, `AGENDAY_DEPLOY_MODE=disabled` às 21:59 UTC).
- [x] Conferir os dois domínios no navegador: login do sistema e portal `?e=studio` carregando com abas Entrar/Cadastro, dropdowns e calendário; `agenda.php` no domínio do cliente redireciona para `cliente.php`.
- [x] Preencher **Configurações → WhatsApp** em produção (endereço do ngrok, chave e sessão `default`) e ativar — sem isso o código de acesso não sai em produção. **Feito pelo responsável em 21/11/2026** (`ativo=1`, domínio fixo do ngrok, chave de 32 caracteres, sessão `default`).
- [x] Agendamento real de teste + mensagem recebida: responsável criou conta com código no WhatsApp e confirmou agendamento no portal em 21/11/2026 (agendamento 189, 23/11 15:00–17:00; `confirmacao` `enviado` em 3 s).
- [x] Cancelamento pelo link no celular físico em produção. PORTAL-06 publicado no SHA `4dbf227`; o relato de 21/11/2026 mostrou que o botão ainda dava "Pedido não reconhecido" no aparelho, e o PORTAL-07 tirou a dependência de `Origin`/`Referer` (assinatura do token basta). PORTAL-07 publicado no SHA `b6887f6` (run `44967236148`) e **validado pelo responsável em 21/11/2026**.
- [ ] Login de demonstração criado no dia anterior (sessão de 30 dias).
- [ ] QR code gerado e impresso.
- [ ] Registro final em `docs/STATUS.md`.
