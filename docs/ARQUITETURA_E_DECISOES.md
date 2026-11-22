# Mapa técnico, decisões e pendências — AgenDay 2.0

**Revisado em 17/11/2026** após conclusão da QA-03. Código/GitHub e evidências recentes são as fontes de verdade; documentos históricos das etapas registram seu contexto original. Não transformar propostas em decisões aprovadas.

## 1. Limites de ambientes

| Ambiente | Uso |
| --- | --- |
| `Emds96/AgenDay-PI` / `dev-feira-uninorte` | Integração do AgenDay 2.0; branch base de tarefas |
| `Emds96/AgenDay-PI` / `main` | Preservada; possui deploy legado a revisar antes de qualquer promoção |
| `oliv-gabriel/AgenDay-PI` | Projeto original de Gabriel; não escrever/mesclar sem autorização |
| `agenday-sistema.infinityfree.io` | Nosso ambiente independente, publicado por workflow controlado |
| `agenday.infinityfree.me` | Site do Gabriel, **não é nossa hospedagem**, não é nosso banco |

O site do Gabriel pode ser comparado **somente para análise autorizada**, não para copiar sem alinhamento. Ele permitiu o fork e mencionou agenda/dashboard, pedindo preservação de padrão visual; não houve decisão de unificação automática.

## 2. Componentes reais do nosso fork

| Área | Arquivos principais | Estado reportado / cautela |
| --- | --- | --- |
| Autenticação de estabelecimento | `index.php`, `login.php`, `cadastrar.php`, `sessao.php`, `verifica_login.php`, `logout.php`, `google-entrar.php` | Cadastro/login tradicionais e Google, fluxos principais testados manualmente; negativos e vinculação de conta existente ainda com cobertura parcial |
| Senhas e comunicação | `alterar-senha.php`, `esqueci-senha.php`, `redefinir-senha.php`, `lib/brevo-email.php`, `css/recuperacao-senha.css` | QA-02 alteração e QA-03 recuperação implementadas/publicadas, aprovadas manualmente no fluxo principal; remetente verificado **AgenDay Sistema** e e-mail real entregue na caixa de entrada, com `De: agendaysistema@12832884.brevosend.com` (caso relatado de 17/11). API Brevo reutiliza a chave configurada; não armazenar chave no Git |
| Clientes | `cad-cliente.php`, `cadastro-clientes.php`, `editar-cliente.php`, `info-clientes.php` | Cadastro/pesquisa/upload adaptados à empresa e **edição em modal** (PR #42, publicada em 20/11/2026); exclusão não implementada |
| Serviços | `cad.servicos.php`, `cadastro-servicos.php`, `editar-servico.php`, `info-servicos.php` | Preço, duração, descrição, pesquisa e **edição em modal** (PR #43, publicada em 20/11/2026); exclusão não implementada |
| Funcionários | `cad-funcionario.php`, `cadastro-funcionario.php`, `editar-funcionario.php`, `info-funcionario.php` | Upload, múltiplos serviços via `funcionario_servico` e **edição em modal, inclusive dos vínculos** (PR #42, publicada em 20/11/2026) |
| Horário, bloqueios e regras | `lib/horario-funcionamento.php`, `lib/bloqueios.php`, `config.php`, `sql/migracao-horario-funcionamento.sql` | Tabelas `horario_funcionamento` e `bloqueio`, mais 5 colunas de regras em `estabelecimento`; tela em Configurações → Horários (PRs #44 a #48, publicadas em 20/11/2026). `agenday_horario_janelas()` entrega as janelas do dia sem o almoço e é usada pelo cálculo de disponibilidade |
| Agendamento | `agendar.php`, `lib/disponibilidade.php`, `criar-agendamento.php`, `api/horarios.php`, `api/clientes-busca.php`, `criar-cliente-rapido.php` | Tela dedicada com horários livres (dia, serviço, profissional), "qualquer profissional", cadastro rápido de cliente, **pacote de sessões** e **retorno recorrente**; gravação transacional com lock por profissional (publicada em 21/11/2026 — SHA `47fb029`) |
| Comparecimento | `lib/comparecimento.php`, `api/comparecimento.php`, `js/calendario.js` | Chegou (`em_atendimento`), Faltou, Concluir e Cancelar; fechamento automático na leitura (sem cron) e sugestão de falta após a tolerância (publicado em 21/11/2026) |
| Agenda | `agenda.php`, `lib/agendamentos.php` | Calendário mensal e painel do dia com status e ações; o formulário antigo foi removido e a marcação acontece em `agendar.php` |
| API/calendário/cancelamento | `api/agendamentos.php`, `api/comparecimento.php`, `js/calendario.js`, `lib/agendamento-cancelar.php`, `cancelar-agendamento.php` | Agenda por mês, cancelamento e comparecimento; histórico preservado por status |
| Estilos e responsividade | `css/design-system.css`, `css/responsivo.css`, `css/recuperacao-senha.css`, `tests/responsividade-check.js` | Identidade turquesa; QA-01 integrada/publicada, mas sem regressão visual completa confirmada; recuperação QA-03 publicada e testada em viewport 390px sem overflow |
| Conta, perfil e configurações | `config.php`, `perfil.php`, `alterar-senha.php`, `whatsapp-plataforma.php` | Central com **três abas** (**Estabelecimento**, **Horários** e **Minha Conta**); configuração básica (nome/contato) publicada e testada em QA01 em 18/11/2026 (PR #26); aba Horários publicada em 20/11/2026; configuração do WhatsApp publicada em 21/11/2026 como **global** (uma linha em `whatsapp_config`, mesmo número para todos os estabelecimentos) e, em 21/11/2026, **retirada da tela do estabelecimento** para a página separada `whatsapp-plataforma.php`, com confirmação da chave de API para salvar (WA-01); perfil ainda ilustrativo; **alteração de senha (QA-02) funcional no fluxo principal validado em produção** |
| Comandas e financeiro | `comandas-abertas.php`, `historico-comandas.php`, `financeiro.php` | Arquivos/telas protegidos, **sem fluxo integrado de negócio validado**; não marcar concluídos |
| Portal público do cliente | `cliente.php`, `cliente-cancelar.php`, `api/cliente-*.php`, `lib/portal-cliente.php`, `lib/guarda-host.php`, `css/cliente.css`, `js/cliente.js` | **Publicado em 21/11/2026** (SHA `4bd707a`) em `agenday-agendamentos.infinityfree.io/?e=slug`. O slug é gerado automaticamente no cadastro e editável em Configurações → Estabelecimento (SLUG-01, 21/11/2026). Login por celular com código no primeiro acesso, sessão de 30 dias, agendamento com serviço/profissional em menu e dia no calendário, remarcação e cancelamento (inclusive por link com token). Guarda de host limita o domínio do cliente a essas rotas |
| Celular confirmado no painel | `api/painel-telefone.php`, `js/confirmar-telefone.js`, `cad-cliente.php`, `cadastro-clientes.php`, `editar-cliente.php`, `criar-cliente-rapido.php`, `agendar.php` | **Publicado** em 22/11/2026 (DEPLOY-04, SHA `fd6d2d3`) — TEL-01, 22/11/2026. A recepcionista confirma o número por código antes de gravar `telefone_digits`; "não tem WhatsApp" salva sem número. A confirmação fica na sessão por 30 minutos, então não há coluna nova |

## 3. Banco v2: fonte e cuidados

`sql/BD_v2.sql` descreve 12 tabelas: `proprietario`, `estabelecimento`, `endereco`, `clientes`, `funcionarios`, `servicos`, `funcionario_servico`, `agendamentos`, `estabelecimento_google`, `recuperacao_senha`, `horario_funcionamento` e `bloqueio`. Banco independente no InfinityFree recebeu a migração `sql/migracao-horario-funcionamento.sql` em 20/11/2026 (2 tabelas novas e 5 colunas em `estabelecimento`, sem apagar dados) e, em 21/11/2026, a `sql/migracao-portal-cliente.sql` (colunas `slug`, `dominio`, `exigir_codigo_whatsapp`, `telefone_digits` e `token_publico`, mais as tabelas `verificacao_acesso`, `sessao_cliente`, `mensagem_whatsapp` e `whatsapp_config`), conforme README/STATUS; **não reimportar** o SQL em ambiente existente.

O `htdocs/conexao.php` de produção é **privado** e fora do GitHub. Cada request autenticado usa `id_estabelecimento` da sessão e consultas preparadas nas rotas adaptadas. Há `password_hash/password_verify`, CSRF, tratamento de uploads e locks/transações para reservas — **essas proteções só cobrem rotas implementadas/revisadas; não são auditoria geral de segurança**.

Desde 22/11/2026 o `verifica_login.php` confere se o `id_estabelecimento` da sessão ainda existe em `estabelecimento` (uma consulta por chave primária). Se a conta foi apagada com a sessão aberta, a sessão é encerrada como no logout, em vez de deixar as telas abrirem e só as gravações falharem com mensagem genérica. Banco indisponível **não** desloga ninguém: a exceção é registrada no log e a sessão é mantida.

No código de agendamento, data/hora local é interpretada em `America/Manaus` por padrão (ou `AGENDAY_TIMEZONE` na configuração privada); estabelecimentos em fusos distintos exigiriam solução adicional.

## 4. Experiência de estabelecimento × consumidor — decisão aberta

**Problema de produto:** o link de login administrativo não deve ser divulgado como link de agendamento de clientes no Instagram de uma barbearia.

**Alternativa ainda NÃO aprovada:** manter **o mesmo sistema/site** com duas rotas:
- Link de administração: login do estabelecimento.
- Link público específico por barbearia: por exemplo `/agendar/nome-da-barbearia` — **endereço ilustrativo, não existente**.

**Alternativas que ainda precisam de decisão do responsável/equipe:**
- Link por slug/ID de estabelecimento, ou outra estratégia de identificação.
- Agendamento como visitante com nome/telefone versus conta do consumidor com e-mail/senha.
- Escolha de serviço/funcionário/data/horário; regras de abertura, duração, fuso e conflitos.
- Confirmação/anti-spam, notificações, cancelamento pelo cliente, prevenção de reserva indevida e privacidade.
- Não reutilizar publicamente a API autenticada `api/agendamentos.php` nem expor dados de outras empresas; criar fluxo público específico com validação e limites.
- Preservar a identidade visual e conversar com Gabriel antes de desenvolver alternativa concorrente.

**Observação de auditoria exploratória (não E2E):** em outra sessão, o TinyFish relatou página pública `cliente.php` no site de Gabriel. O formulário existia, mas **não foi comprovado que salvava dados**. Isso é informação para alinhamento de trabalho, não autorização para copiar ou integrar.

## 5. Decisões tomadas versus pendentes

**Confirmadas pelo responsável e no repositório:**
- Fork independente, `dev-feira-uninorte` para desenvolvimento, `main` preservada e PRs por tarefa.
- Infraestrutura/banco separados do Gabriel; deploy apenas via workflow controlado, Secrets protegidos, autorização.
- Preservar identidade visual original, melhorar responsividade, priorizar demonstração da feira.
- **Documentação antes das próximas ações de produção**; outra sessão é responsável pela publicação, esta sessão documenta.

**Pendentes:**
- Manter backups atuais de `htdocs` e MySQL antes de cada deploy. Rotina automática FTPS/PHP + restauração MariaDB foi comprovada em 17/11 e repetida em 20/11/2026 (ex.: `2026-11-20_192137_a1d631ca`, `BACKUP OK`, 8,8 MB + SQL 22,1 KB); ensaio de recuperação completa em outra hospedagem ainda não foi feito.
- **Evoluir a agenda** para as visões dia/semana/mês (hoje é mensal com painel do dia); o cálculo de horário livre já existe em `lib/disponibilidade.php` e é usado pela tela de agendamento.
- **Reagendamento com vínculo** e **lembretes ao cliente**: o lembrete automático de 24h por WhatsApp está implementado, testado e **publicado** em 22/11/2026 (LEMBRETE-01, DEPLOY-04, SHA `fd6d2d3`), mas ainda **sem envio real comprovado na hospedagem** (depende do WAHA ligado no PC); SMS/e-mail seguem como alternativa a avaliar.
- **Modo híbrido de sessões** (PEND-01): ativar/desativar no cadastro e nas Configurações.
- Definir a taxa de cancelamento como regra/aviso, sem gateway de pagamento.
- Completar regressão visual da correção de menu (QA-01), já integrada e incluída nos deploys posteriores; não presumir cobertura completa apenas pelo código publicado.
- QA-02 e QA-03 estão concluídas no fluxo principal testado; ainda revisar os cenários negativos de senha. Priorizar QA-04 a QA-08 (configuração, módulos legados, JS, textos fixos, modais) conforme impacto na feira.
- Definir página pública do consumidor, links e fluxo sem conta/com conta.
- Decidir escopo real de comandas, financeiro, dashboard e perfil para a apresentação.
- Decidir integração/colaboração com Gabriel; não unificar branches sem entendimento.
- Confirmar oficialmente prazo final e data da feira e definir plano de demonstração/contingência.
- Revalidar limites de isolamento e autorização entre estabelecimentos. Documentar assim que o responsável aprovar cada nova entrega, antes de comunicar a conclusão (ver `AGENTS.md`).

## 6. Mapa de leitura por tarefa

- Estado e evidências: [STATUS.md](STATUS.md)
- Instruções e autonomia: [AGENTS.md](../AGENTS.md)
- Rotina entre sessões e ferramentas: [GUIA_DE_CONTINUIDADE.md](GUIA_DE_CONTINUIDADE.md)
- Publicação: [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md)
- Backups/restauração: [BACKUP_E_RECUPERACAO.md](BACKUP_E_RECUPERACAO.md)
- Testes: [TESTES_E_QUALIDADE.md](TESTES_E_QUALIDADE.md)
- Modelagem e história técnica: [MODELO_BANCO_V2.md](MODELO_BANCO_V2.md) e documentos de microtarefas.
