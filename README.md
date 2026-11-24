<div align="center">

![Logo AgenDay](img/Logo.png)

# AgenDay 2.0

**Sistema web de gestão de agendamentos para estabelecimentos de serviços**

Projeto acadêmico para a feira da **UniNorte — 27 de novembro de 2026 (data indicada no planejamento, sujeita a confirmação)**. **Prazo operacional de conclusão: 24 de novembro de 2026**, conforme relato do responsável sobre orientação do professor; confirmar ambos os marcos.

![PHP](https://img.shields.io/badge/PHP-Backend-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Banco%20de%20dados-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?logo=javascript&logoColor=black)
![Bootstrap](https://img.shields.io/badge/Bootstrap-Interface-7952B3?logo=bootstrap&logoColor=white)

</div>

## Sobre o projeto

O **AgenDay 2.0** é uma aplicação web desenvolvida para facilitar a organização de atendimentos. Em um único ambiente, cada estabelecimento pode gerenciar seus clientes, funcionários, serviços e agendamentos.

Esta versão evolui a base do projeto **AgenDay-PI**, com um banco de dados independente, autenticação de estabelecimentos, associação entre funcionários e serviços e uma agenda conectada aos registros cadastrados.

> **Estado confirmado em 17/11/2026:** aplicação publicada no site independente [AgenDay](https://agenday-sistema.infinityfree.io/) pelo workflow [#44212874922](https://github.com/Emds96/AgenDay-PI/actions/runs/44212874922), commit de aplicação `5749f333`; a branch de desenvolvimento possui commits documentais posteriores. Cadastro/login, agenda e outras operações foram validados em cenários delimitados; **login Google e recuperação/alteração de senha também tiveram testes manuais positivos em produção**. Na recuperação, o responsável confirmou o remetente **AgenDay Sistema** entregue na caixa de entrada com endereço `agendaysistema@12832884.brevosend.com`. Não significa que todas as telas e controles de segurança já passaram por QA. Consulte [docs/STATUS.md](docs/STATUS.md) e [docs/QA03_RECUPERACAO_SENHA_BREVO.md](docs/QA03_RECUPERACAO_SENHA_BREVO.md).
>
> **Estado confirmado em 20/11/2026:** novo deploy publicado pelo workflow [44837030817](https://github.com/Emds96/AgenDay-PI/actions/runs/44837030817), commit `84eef5d`, após backup verificado e aplicação da migração `sql/migracao-horario-funcionamento.sql` no phpMyAdmin. Entraram: edição de cliente, funcionário e serviço; aba **Horários** em Configurações; horário no cadastro; e tela de **Bloqueios**.
>
> **Estado confirmado em 21/11/2026:** deploy do commit `47fb029` pelo workflow [44856321866](https://github.com/Emds96/AgenDay-PI/actions/runs/44856321866), com backup verificado e migração `sql/migracao-status-comparecimento.sql` aplicada no phpMyAdmin. Entraram: **tela de agendamento** (`agendar.php`) com horários livres calculados pelo servidor, pacote de sessões e retorno recorrente; **comparecimento** (Chegou / Faltou / Concluir) e fechamento automático; e a **conta de demonstração** `studio@demonstracao.com` com 173 agendamentos fictícios. A agenda agora usa horário, bloqueios e regras para calcular a disponibilidade. Detalhes em [docs/STATUS.md](docs/STATUS.md).
>
> **Estado confirmado em 21/11/2026 (portal do cliente):** deploy do commit `4bd707a` pelo workflow [44953952790](https://github.com/Emds96/AgenDay-PI/actions/runs/44953952790), nas duas pastas (`htdocs` e `agenday-agendamentos.infinityfree.io/htdocs`), após backup verificado (`2026-11-21_174618`) e aplicação de `sql/migracao-portal-cliente.sql` no phpMyAdmin. Entrou o **portal público do cliente** em https://agenday-agendamentos.infinityfree.io/?e=studio: entrada por celular com código no primeiro acesso, agendamento com serviço e profissional em menu e dia no calendário, remarcação e cancelamento (inclusive por link). A aba **Configurações → WhatsApp** foi preenchida e testada pelo responsável em 21/11/2026, junto com um agendamento real no portal (código recebido no WhatsApp e confirmação enviada). Detalhes em [docs/STATUS.md](docs/STATUS.md) (DEPLOY-01).

## Funcionalidades

| Módulo | Implementação atual |
| --- | --- |
| Conta do estabelecimento | Cadastro, login tradicional e Google, sessão e logout; fluxos principais testados |
| Senhas (QA-02 e QA-03) | Alteração autenticada de senha e recuperação por e-mail, link temporário e uso único; testes manuais dos fluxos principais aprovados |
| E-mail transacional | Brevo HTTPS, remetente verificado **AgenDay Sistema**, entrega real confirmada em um teste na caixa de entrada |
| Clientes | Cadastro, pesquisa, foto opcional e **edição em modal** (PR #42) |
| Funcionários | Cadastro, pesquisa, foto opcional, vínculo a vários serviços e **edição em modal** (PR #42) |
| Serviços | Cadastro com nome, duração, preço e descrição; pesquisa e **edição em modal** (PR #43) |
| Agendamento | Tela própria (`agendar.php`) com **horários livres** calculados pelo servidor, busca de cliente, cadastro rápido, "qualquer profissional", **pacote de sessões** e **retorno recorrente** |
| Controle de horários | Disponibilidade combina horário de funcionamento, almoço, bloqueios, agendamentos ativos, duração do serviço, intervalo, antecedência e janela |
| Comparecimento | Chegou (`em_atendimento`), Faltou, Concluir e Cancelar por atendimento; fechamento automático ao passar do fim e sugestão de falta após a tolerância |
| Calendário | Navegação mensal, atendimentos do dia com status (agendado, confirmado, em atendimento, concluído, faltou, cancelado) e histórico |
| Segurança | Consultas preparadas, proteção CSRF e isolamento de dados por estabelecimento nos fluxos adaptados |
| Configurações | **Estabelecimento**, **Horários** e **Minha Conta**. O estabelecimento edita seu contato, o link do portal e a exigência de código de acesso pelo WhatsApp. Em Horários, define funcionamento, regras e bloqueios. A conexão WhatsApp compartilhada (WAHA, túnel e sessão) é administrada em `whatsapp-plataforma.php`; salvar alterações exige confirmar a chave atual da conexão configurada. |
| Portal do cliente | Domínio público (`agenday-agendamentos.infinityfree.io/?e=slug`): entrar só com o celular, código de 6 dígitos no primeiro acesso, agendar com serviço e profissional em menu e dia em calendário, remarcar, cancelar e cancelar por link. Envia pelo WhatsApp o código, a confirmação, a remarcação, o cancelamento e o **lembrete automático 24h antes** (agendado na fila; sem cron, sai no próximo uso do sistema) |
| Horário e bloqueios | Funcionamento semanal em `horario_funcionamento`, bloqueios por estabelecimento ou funcionário em `bloqueio` e regras na tabela `estabelecimento` (intervalo, antecedência, janela, cancelamento e tolerância). **Aplicados pela tela de agendamento** no cálculo dos horários livres. |

**Ainda não concluído:** concorrência simultânea, auditoria aprofundada de autorização entre estabelecimentos, revisão final do menu/modais, testes negativos de links de senha, reagendamento com vínculo, lembretes ao cliente (SMS/e-mail), modo híbrido de sessões (PEND-01), comandas e financeiro. Há **cenários integrados de criação, conflito e cancelamento de agendamentos já aprovados** no QA reportado; não confundir essa cobertura com testes simultâneos exaustivos.

## Tecnologias

- **PHP** — autenticação, validações e regras de negócio.
- **MySQL / MariaDB compatível** — armazenamento relacional.
- **HTML, CSS e JavaScript** — páginas, formulários e calendário.
- **Bootstrap 5** — componentes visuais e responsividade.
- **Git e GitHub** — histórico e organização do desenvolvimento.
- **InfinityFree** — ambiente de hospedagem e banco de dados próprios do projeto.

## Estrutura do repositório

```text
AgenDay-PI/
├── index.php                    # Login e cadastro de estabelecimento
├── agenda.php                   # Formulário e calendário
├── salvar-agendamento.php      # Endpoint de gravação de agendamento
├── cad-cliente.php              # Tela de clientes
├── cad-funcionario.php          # Tela de funcionários
├── cad.servicos.php             # Tela de serviços
├── sessao.php                   # Sessão e proteção CSRF
├── verifica_login.php           # Proteção das páginas privadas
├── conexao.exemplo.php          # Modelo da conexão (sem senha real)
├── api/
│   └── agendamentos.php         # Consulta JSON dos agendamentos
├── lib/
│   ├── agendamentos.php         # Consultas de agenda e opções
│   └── agendamento-gravar.php   # Validação e transação de gravação
├── sql/
│   └── BD_v2.sql                # Estrutura do banco independente
├── css/                         # Estilos
├── js/                          # Comportamento das páginas
├── img/                         # Imagens da interface
├── tests/                       # Testes de validação
└── docs/                        # Documentação técnica das etapas
```

## Banco de dados

O modelo atual está em [`sql/BD_v2.sql`](sql/BD_v2.sql) e contém **12 tabelas**:

| Tabela | Finalidade |
| --- | --- |
| `proprietario` | Dados opcionais do proprietário |
| `estabelecimento` | Contas dos estabelecimentos e regras de agendamento |
| `endereco` | Endereço do estabelecimento |
| `clientes` | Clientes de cada estabelecimento |
| `funcionarios` | Profissionais cadastrados |
| `servicos` | Serviços, duração e preço |
| `funcionario_servico` | Associação de funcionários aos serviços que realizam |
| `agendamentos` | Cliente, funcionário, serviço, início, fim e situação do atendimento |
| `estabelecimento_google` | Vínculo de login Google por estabelecimento |
| `recuperacao_senha` | Tokens de recuperação de senha |
| `horario_funcionamento` | Funcionamento por dia da semana, incluindo almoço |
| `bloqueio` | Feriado, folga ou fechamento parcial, por conta ou funcionário |

> Em bancos já existentes, a estrutura nova entra pelo arquivo `sql/migracao-horario-funcionamento.sql`, aplicado no phpMyAdmin antes de publicar o código.

Os dados de clientes, funcionários, serviços e agendamentos são vinculados ao estabelecimento. O objetivo é impedir que contas diferentes misturem seus registros.

Para conhecer os relacionamentos e decisões de modelagem, consulte [`docs/MODELO_BANCO_V2.md`](docs/MODELO_BANCO_V2.md). **Esse documento registra a concepção inicial do banco; o estado de implementação mais recente é descrito neste README e nos documentos das tarefas.**

## Como executar em ambiente de desenvolvimento

**Pré-requisitos:** PHP com extensão `mysqli`, servidor MySQL/MariaDB compatível e um servidor web com suporte a PHP. PHP 8 ou superior é recomendado; a compatibilidade exata deve ser testada no ambiente escolhido.

### 1. Obter a versão de desenvolvimento

```bash
git clone --branch dev-feira-uninorte https://github.com/Emds96/AgenDay-PI.git
cd AgenDay-PI
```

### 2. Preparar um banco de dados próprio

Crie **um banco vazio e independente**, selecionando-o no phpMyAdmin. Importe nele o arquivo `sql/BD_v2.sql` **apenas se estiver configurando um novo ambiente**.

> **Atenção:** o banco independente já configurado para o projeto foi criado e, após a migração de 20/11/2026, reúne 12 tabelas. **Não reimporte ou execute o SQL nesse banco sem necessidade e sem backup.** Nunca execute esse script no banco do projeto original.

### 3. Configurar a conexão privada

Crie uma cópia de `conexao.exemplo.php` com o nome `conexao.php`, no mesmo diretório das páginas PHP. Na **cópia local ou no servidor sob seu controle**, configure os valores de `HOST`, `USUARIO`, `SENHA` e `DB` para o seu banco.

O arquivo `conexao.php` está incluído no `.gitignore` e **não deve ser enviado ao GitHub**. Não compartilhe senhas, credenciais ou arquivos privados em commits.

### 4. Abrir a aplicação

Em um ambiente local com PHP, por exemplo:

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000/index.php`. O servidor embutido é destinado ao **desenvolvimento**, não à produção. Dependendo do provedor de banco, conexões remotas podem exigir configuração específica; utilize um MySQL local de teste se necessário.

> **No PC de desenvolvimento do projeto** o ambiente já está montado: PHP 8.4 e MariaDB 11.8 em `http://127.0.0.1:8080`, banco `agenday_local` e scripts de subir/parar. Caminhos, testes e diagnóstico estão em [`docs/AMBIENTE_LOCAL.md`](docs/AMBIENTE_LOCAL.md). Para retomar o projeto em outra sessão ou outra IA, ver [`docs/PASSAGEM_DE_CONTEXTO.md`](docs/PASSAGEM_DE_CONTEXTO.md).

Crie um estabelecimento de teste, faça login e cadastre um **cliente**, um **serviço** e um **funcionário vinculado ao serviço** antes de testar os agendamentos.

## Testes

Os testes do projeto incluem verificações locais e CI, e o [STATUS](docs/STATUS.md) registra **testes integrados relatados pelo responsável**, operações reais em navegador e publicações confirmadas até 17/11/2026. **QA-02 e QA-03 tiveram fluxos principais validados manualmente**; o PR #4 (menu) foi incluído em publicações posteriores, mas sua regressão visual completa ainda deve ser confirmada. Isso não é certificação de todo o aplicativo.

Há roteiros automatizados para validação de dados e identificadores de cancelamento, sem conectar ao banco:

```bash
php tests/agendamento-validacao.php
php tests/agendamento-cancelamento.php
```

A CI passou nos commits publicados. Nos cenários de QA relatados, houve cadastros reais, agendamentos com bloqueio de sobreposição e cancelamentos no MySQL integrado. **Ainda falta ensaio de concorrência verdadeiramente simultânea e cobertura ampla de autorização**; testes estáticos não substituem essas verificações.

## Publicação controlada — procedimento operacional

**Antes de qualquer novo deploy, leia o [guia completo de publicação](docs/PUBLICACAO_AUTOMATICA.md).** Mantemos o workflow existente `.github/workflows/agenday-publicar.yml`: código na `dev-feira-uninorte` → GitHub Actions → pacote seguro → FTPS → `htdocs/` no InfinityFree. **Não é necessário migrar para outro workflow**, navegador ou `main`.

O **plugin GitHub** gerencia código/PRs e consulta execuções; o **Remote Desktop Commander**, conectado ao computador autorizado, executa o **GitHub CLI (`gh`)** para consultar e alterar temporariamente as duas variáveis de autorização, disparar `workflow_dispatch` e, quando permitido e autorizado, aprovar o ambiente `producao-agenday`. Segredos FTP permanecem no GitHub Environments. Após a execução, as variáveis devem voltar a `false/disabled`.

**Verificação mais recente (23/11/2026):** [deploy do PR #119](https://github.com/Emds96/AgenDay-PI/actions/runs/45304476963), SHA `c76772d`: indicador centralizado nos botões do portal do cliente. Testes e limites em [STATUS](docs/STATUS.md).

**Verificação anterior (23/11/2026):** o responsável aprovou no celular a rolagem até **Cadastrar-se** após o [deploy do PR #117](https://github.com/Emds96/AgenDay-PI/actions/runs/45299388389), SHA 63a6d57.

**Verificação de 22/11/2026:** SHA `6a9b48f` (run [#45096761768](https://github.com/Emds96/AgenDay-PI/actions/runs/45096761768)) levou ao ar SESSÃO-CONTA-01, PRIMEIROS-PASSOS-05 a 12 e a confirmação de presença pelo lembrete e pelo painel (PR #110). Backup verificado antes de publicar (`2026-11-22_211520`, 145 arquivos, 16 tabelas) e complemento do portal com 129 arquivos; **130 arquivos** enviados em cada pasta, nenhum arquivo remoto apagado e **nenhum SQL executado**. Os gates voltaram a `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. **QA do responsável no celular pendente** (confirmação e cancelamento por link).

**Verificação anterior (22/11/2026):** SHA `fd6d2d3` (run [#45068966986](https://github.com/Emds96/AgenDay-PI/actions/runs/45068966986)) levou ao ar o lote do primeiro uso e das mensagens — PRIMEIROS-PASSOS-01 a 04, CADASTRO-TELEFONE-01, FEE-01, FEE-02, PORTAL-RAIZ-01, MENSAGENS-02 e LEMBRETE-01. Backup verificado antes de publicar (`2026-11-22_164620`, 137 arquivos, 16 tabelas), **128 arquivos** enviados em cada pasta, nenhum arquivo remoto apagado e **nenhum SQL executado**. Os gates voltaram a `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. **QA do responsável na hospedagem pendente.**

**Verificação de 21/11/2026:** quatro deploys no mesmo dia — SHA `47fb029` (run [#44856321866](https://github.com/Emds96/AgenDay-PI/actions/runs/44856321866), agenda com horários livres e comparecimento), SHA `4bd707a` (run [#44953952790](https://github.com/Emds96/AgenDay-PI/actions/runs/44953952790), portal do cliente nas duas pastas), SHA `4dbf227` (run [#44965206160](https://github.com/Emds96/AgenDay-PI/actions/runs/44965206160), link de cancelamento no celular e aviso de remarcação) e SHA `b6887f6` (run [#44967236148](https://github.com/Emds96/AgenDay-PI/actions/runs/44967236148), cancelamento por link sem exigir `Origin`). Nos quatro, `validar`, FTPS e `publicar` foram aprovados e os gates voltaram a `AGENDAY_DEPLOY_ENABLED=false` e `AGENDAY_DEPLOY_MODE=disabled`. O responsável confirmou o envio real de WhatsApp e um agendamento pelo portal. Registro detalhado em [docs/STATUS.md](docs/STATUS.md); os commits documentais seguintes **não** demandaram novo deploy.

## Fluxo de branches

- **Desenvolvimento:** `dev-feira-uninorte`, preferencialmente com branches curtas de tarefa e PRs destinados à branch de desenvolvimento.
- **Versão oficial do fork:** `main`, preservada até haver versão estável, validada e aprovada. Promoção = PR de `dev-feira-uninorte` para `main` do fork, sem alterar o repositório original do Gabriel.
- **Cuidado:** a `main` ainda contém workflow antigo de deploy. Revisar antes de qualquer merge para ela. Nenhuma promoção ou publicação acontece por autorização implícita.

## Status e próximos passos

**QA-04 (18/11/2026):** configurações básicas publicadas no SHA 5f2c8666 pelo [workflow #44367305044](https://github.com/Emds96/AgenDay-PI/actions/runs/44367305044), após backup com restauração local; leitura e gravação reais testadas via DeskBrowse com conta fictícia QA01 e dados restaurados. Perfil, endereço, CNPJ e horários ainda não foram implementados nesta entrega. Ver [guia QA-04](docs/QA04_CONFIG_ESTABELECIMENTO.md).


- [x] Fork de desenvolvimento independente do projeto original.
- [x] Modelo de banco v2 criado e importado em conta própria; conexão básica testada.
- [x] Código de login, clientes, funcionários e serviços adaptado ao modelo v2.
- [x] Código de criação de agendamentos e calendário conectado à API.
- [x] Implementar no código o cancelamento de agendamentos ativos e revisar as regras de conflitos (**4.5**).
- [ ] Validar cancelamento e concorrência com sessões simultâneas no MySQL real.
- [x] Criar o tema base turquesa e padronizar cores, tipografia e componentes (**5.1**, revisão visual pendente).
- [x] Aplicar no código a responsividade ao login, menu, cadastros e calendário (**5.2**, validação visual em dispositivos ainda pendente).
- [ ] Executar testes funcionais e de segurança na hospedagem.
- [x] Preparar automação de publicação segura GitHub → InfinityFree e registrar publicação FTPS concluída em 13/11/2026 ([evidência](https://github.com/Emds96/AgenDay-PI/actions/runs/43518952935)).
- [x] Confirmar último commit da aplicação publicado, flags de deploy e backups com restauração local aprovados em 17/11/2026.
- [x] Publicar e validar no fluxo principal a recuperação de senha com visual do login, aviso de spam e remetente **AgenDay Sistema** (QA-03).
- [ ] Executar regressão ampliada (concorrência simultânea, isolamento entre empresas, módulos legados e casos negativos de senha) antes de promover uma versão para a `main`.
- [ ] Preparar dados fictícios, imagens das telas e apresentação da feira.
- [ ] Após testes e autorização específica, **promover** por Pull Request a versão estável de `dev-feira-uninorte` para a `main` **deste fork**, revisando antes o workflow antigo de deploy da `main`.

## Procedimentos de trabalho, recuperação e continuidade

**Roteiro para começar em outra sessão:** consultar o [índice da documentação](docs/INDICE_DA_DOCUMENTACAO.md) e o [guia de continuidade](docs/GUIA_DE_CONTINUIDADE.md). Eles registram **não só o deploy**, mas também o uso de GitHub, Desktop Commander, GitHub CLI, ZERO Browser/TinyFish, autorizações, PRs, backups, testes, ambiente publicado, decisões de produto e o padrão de registro de trabalho.

**Não iniciar nova publicação sem backup recente e validado** de arquivos de `htdocs/` e MySQL; seguir [BACKUP_E_RECUPERACAO.md](docs/BACKUP_E_RECUPERACAO.md). Em 17/11 foi validado o backup automático via FTPS + exportação PHP + restauração local MariaDB; o backup pré-deploy do remetente (`2026-11-17_205855_4f8c886e`) terminou em `BACKUP OK`. Backup não autoriza deploy sem aprovação específica.

Guias separados: [QA e testes](docs/TESTES_E_QUALIDADE.md), [mapa técnico e decisões](docs/ARQUITETURA_E_DECISOES.md), [procedimento de deploy](docs/PUBLICACAO_AUTOMATICA.md) e [estado real/pendências](docs/STATUS.md). Se um guia histórico divergir do repositório, revalidar em vez de assumir.

## Documentação complementar

- [Instruções para agentes e fluxo de trabalho](AGENTS.md)
- [Índice de toda a documentação e ordem de leitura](docs/INDICE_DA_DOCUMENTACAO.md)
- [Continuidade entre sessões e como a equipe trabalhava](docs/GUIA_DE_CONTINUIDADE.md)
- [Backups e recuperação, com roteiro completo](docs/BACKUP_E_RECUPERACAO.md)
- [Testes e QA, níveis de evidência e bugs](docs/TESTES_E_QUALIDADE.md)
- [Mapa técnico, decisões feitas e pendentes](docs/ARQUITETURA_E_DECISOES.md)
- [Status consolidado, prazos, publicação e próximos passos](docs/STATUS.md)

- [Autenticação — 3.1](docs/AUTENTICACAO_3_1.md)
- [Clientes — 3.2](docs/CLIENTES_3_2.md)
- [Funcionários — 3.3](docs/FUNCIONARIOS_3_3.md)
- [Serviços — 3.4](docs/SERVICOS_3_4.md)
- [Modelo do banco de dados](docs/MODELO_BANCO_V2.md)
- [Base de agendamentos — 4.1](docs/AGENDAMENTOS_4_1.md)
- [Tela de agendamentos — 4.2](docs/AGENDAMENTOS_4_2.md)
- [Gravação de agendamentos — 4.3](docs/AGENDAMENTOS_4_3.md)
- [Calendário — 4.4](docs/AGENDAMENTOS_4_4.md)
- [Cancelamento e conflitos — 4.5](docs/AGENDAMENTOS_4_5.md)
- [Design System — 5.1](docs/DESIGN_SYSTEM_5_1.md)
- [Responsividade — 5.2](docs/RESPONSIVIDADE_5_2.md)
- [Publicação automatizada — configuração segura](docs/PUBLICACAO_AUTOMATICA.md)

## Histórico do projeto e créditos

O AgenDay 2.0 foi desenvolvido a partir do repositório acadêmico original [**oliv-gabriel/AgenDay-PI**](https://github.com/oliv-gabriel/AgenDay-PI). As melhorias aqui documentadas são realizadas no [**fork Emds96/AgenDay-PI**](https://github.com/Emds96/AgenDay-PI), utilizando uma branch de desenvolvimento separada.

Os nomes e papéis dos demais integrantes poderão ser adicionados após confirmação da equipe. **O projeto original é preservado**: alterações no fork não são enviadas automaticamente ao repositório de origem.

---

<div align="center">

**AgenDay 2.0** · Projeto acadêmico em evolução

</div>
