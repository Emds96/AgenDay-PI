# Mapa técnico, decisões e pendências — AgenDay 2.0

**Retrato documental de 16/11/2026**, sujeito a mudanças. Código/GitHub e testes recentes são as fontes de verdade; histórico das etapas em `docs/*_3_*.md` e `docs/AGENDAMENTOS_4_*.md`. Não transformar propostas em decisões aprovadas.

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
| Autenticação de estabelecimento | `index.php`, `login.php`, `cadastrar.php`, `sessao.php`, `verifica_login.php`, `logout.php` | Cadastro e login implementados, QA autenticado relatado; sem recuperação de senha |
| Clientes | `cad-cliente.php`, `cadastro-clientes.php`, `info-clientes.php` | Cadastro/pesquisa/upload adaptados à empresa; edição/exclusão não consideradas prontas |
| Serviços | `cad.servicos.php`, `cadastro-servicos.php`, `info-servicos.php` | Preço, duração, descrição e pesquisa; edição/exclusão pendentes |
| Funcionários | `cad-funcionario.php`, `cadastro-funcionario.php`, `info-funcionario.php` | Upload e múltiplos serviços via `funcionario_servico`; edição posterior de vínculos não pronta |
| Agenda | `agenda.php`, `lib/agendamentos.php`, `lib/agendamento-gravar.php`, `salvar-agendamento.php` | Criação e bloqueio de conflito implementados, casos de QA relatados; concorrência simultânea não comprovada |
| API/calendário/cancelamento | `api/agendamentos.php`, `js/calendario.js`, `lib/agendamento-cancelar.php`, `cancelar-agendamento.php` | Agenda por mês e cancelamento com status mantido no histórico; validar produção após mudanças |
| Estilos e responsividade | `css/design-system.css`, `css/responsivo.css`, `tests/responsividade-check.js` | Identidade turquesa; QA-01 menu corrigido em código no PR #4 mas ainda não confirmado online |
| Conta, perfil e configurações | `config.php`, `perfil.php`, `alterar-senha.php` | Telas legadas incompletas/ilustrativas, senha não funcional (QA-02) |
| Comandas e financeiro | `comandas-abertas.php`, `historico-comandas.php`, `financeiro.php` | Arquivos/telas protegidos, **sem fluxo integrado de negócio validado**; não marcar concluídos |
| Página pública do consumidor | **Não implementada no nosso fork** | Discutida, não aprovada nem desenvolvida; não confundir com o cadastro administrativo do cliente |

## 3. Banco v2: fonte e cuidados

`sql/BD_v2.sql` descreve 8 tabelas relacionadas: `proprietario`, `estabelecimento`, `endereco`, `clientes`, `funcionarios`, `servicos`, `funcionario_servico` e `agendamentos`. Banco independente no InfinityFree foi preparado em etapa anterior, conforme README/STATUS; **não reimportar** o SQL em ambiente existente.

O `htdocs/conexao.php` de produção é **privado** e fora do GitHub. Cada request autenticado usa `id_estabelecimento` da sessão e consultas preparadas nas rotas adaptadas. Há `password_hash/password_verify`, CSRF, tratamento de uploads e locks/transações para reservas — **essas proteções só cobrem rotas implementadas/revisadas; não são auditoria geral de segurança**.

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
- Novos backups atuais de `htdocs` e MySQL, depois validação formal (runbook [BACKUP_E_RECUPERACAO.md](BACKUP_E_RECUPERACAO.md)).
- Publicar PR #4 (mais commits integrados, conforme SHA aprovado) e testar correção do menu; confirmar efetivamente versão remota.
- Corrigir QA-02 a QA-08 na ordem acordada, sem considerar sugestões como escopo já autorizado.
- Definir página pública do consumidor, links e fluxo sem conta/com conta.
- Decidir escopo real de comandas, financeiro, dashboard e perfil para a apresentação.
- Decidir integração/colaboração com Gabriel; não unificar branches sem entendimento.
- Confirmar oficialmente prazo final e data da feira e definir plano de demonstração/contingência.

## 6. Mapa de leitura por tarefa

- Estado e evidências: [STATUS.md](STATUS.md)
- Instruções e autonomia: [AGENTS.md](../AGENTS.md)
- Rotina entre sessões e ferramentas: [GUIA_DE_CONTINUIDADE.md](GUIA_DE_CONTINUIDADE.md)
- Publicação: [PUBLICACAO_AUTOMATICA.md](PUBLICACAO_AUTOMATICA.md)
- Backups/restauração: [BACKUP_E_RECUPERACAO.md](BACKUP_E_RECUPERACAO.md)
- Testes: [TESTES_E_QUALIDADE.md](TESTES_E_QUALIDADE.md)
- Modelagem e história técnica: [MODELO_BANCO_V2.md](MODELO_BANCO_V2.md) e documentos de microtarefas.
