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
- [x] Cliente criado no primeiro acesso (nome + telefone), sem duplicar por telefone.
- [x] `cliente.php` com as etapas de telefone e código.
- [x] Código enviado pelo caminho imediato (WAHA); falha de envio apaga a verificação.
- [x] Contingência: `exigir_codigo_whatsapp` desligado entra só com o celular.

## Fase 2 — Agendar

- [x] `api/cliente-dados.php` (estabelecimento, serviços, profissionais, regras).
- [x] `api/cliente-horarios.php` (reusa `lib/disponibilidade.php`, sem recalcular regra).
- [x] `api/cliente-agendamentos.php` com `acao=criar` (transação + `FOR UPDATE`).
- [x] Etapas da página: serviço → profissional → data/hora → confirmação.
- [x] Token público gerado no agendamento (64 hex, único).
- [x] Mensagem de confirmação enfileirada e enviada na hora quando o WAHA responde.
- [x] Tela de sucesso com link de cancelamento e arquivo `.ics`.

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

- [ ] Instalar Docker Desktop + WSL2 (com reinício autorizado).
- [ ] Subir o WAHA e parear o número (QR escaneado pelo responsável do número).
- [ ] Publicar o endereço HTTPS do túnel (ngrok) e testar de fora.
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

- [x] `tests/portal-cliente.php` (telefone, slug, código, token, sessão, CSRF, origem, prazo) — 69 verificações.
- [x] Regressão: `tests/agendamento-validacao.php` (19) e `tests/agendamento-cancelamento.php` (20).
- [x] `node tests/responsividade-check.js` — 210 verificações (inclui as quatro abas de Configurações).
- [x] Fluxo completo por HTTP local: entrar com código, criar, listar, remarcar, cancelar, cancelar por link.
- [x] Envio real contra um WAHA simulado local (confirmação, retry após falha e cancelamento).
- [x] `docs/STATUS.md` atualizado.
- [ ] PR aberto para `dev-feira-uninorte` com resumo e riscos.
- [ ] CI verde.
- [ ] Validação visual pelo responsável no navegador (celular e desktop).

## Fase 8 — Deploy e feira

- [ ] Backup verificado e registrado.
- [ ] Autorização de SHA e destino.
- [ ] Migração aplicada em produção (phpMyAdmin) e conferida.
- [ ] Deploy único com as duas pastas.
- [ ] Flags de deploy desligadas ao final (também em caso de falha).
- [ ] Conferir os dois domínios no navegador.
- [ ] Agendamento real de teste + mensagem recebida.
- [ ] Cancelamento pelo link testado.
- [ ] Login de demonstração criado no dia anterior (sessão de 30 dias).
- [ ] QR code gerado e impresso.
- [ ] Registro final em `docs/STATUS.md`.
