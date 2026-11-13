<div align="center">

![Logo AgenDay](img/Logo.png)

# AgenDay 2.0

**Sistema web de gestão de agendamentos para estabelecimentos de serviços**

Projeto acadêmico em desenvolvimento para apresentação na feira da **UniNorte — 27 de novembro de 2026**.

![PHP](https://img.shields.io/badge/PHP-Backend-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Banco%20de%20dados-4479A1?logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?logo=javascript&logoColor=black)
![Bootstrap](https://img.shields.io/badge/Bootstrap-Interface-7952B3?logo=bootstrap&logoColor=white)

</div>

## Sobre o projeto

O **AgenDay 2.0** é uma aplicação web desenvolvida para facilitar a organização de atendimentos. Em um único ambiente, cada estabelecimento pode gerenciar seus clientes, funcionários, serviços e agendamentos.

Esta versão evolui a base do projeto **AgenDay-PI**, com um banco de dados independente, autenticação de estabelecimentos, associação entre funcionários e serviços e uma agenda conectada aos registros cadastrados.

> **Estado atual:** as funcionalidades descritas abaixo estão **implementadas no código da branch `dev-feira-uninorte`**, mas ainda dependem de testes integrados na hospedagem. A versão atualizada **não foi publicada no InfinityFree**. Este README não deve ser interpretado como garantia de que o sistema completo já está em produção.

## Funcionalidades

| Módulo | Implementação atual |
| --- | --- |
| Conta do estabelecimento | Cadastro, login, sessão autenticada e logout |
| Clientes | Cadastro, pesquisa e foto opcional |
| Funcionários | Cadastro, pesquisa, foto opcional e vínculo a vários serviços |
| Serviços | Cadastro com nome, duração, preço e descrição; pesquisa |
| Agendamentos | Seleção de cliente, serviço, funcionário, data e hora; gravação programada no MySQL |
| Controle de horários | Validação de dados e verificação de sobreposição de atendimentos na gravação |
| Calendário | Navegação mensal, visualização de agendamentos por dia, status e cancelamento de atendimentos ativos |
| Segurança | Consultas preparadas, proteção CSRF e isolamento de dados por estabelecimento nos fluxos adaptados |

**Ainda não concluído:** testes integrados de cancelamento e concorrência, revisão final da interface, testes de ponta a ponta e publicação da versão completa. Algumas telas legadas, como comandas e financeiro, permanecem fora do escopo funcional já validado.

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

O modelo atual está em [`sql/BD_v2.sql`](sql/BD_v2.sql) e contém **8 tabelas**:

| Tabela | Finalidade |
| --- | --- |
| `proprietario` | Dados opcionais do proprietário |
| `estabelecimento` | Contas dos estabelecimentos |
| `endereco` | Endereço do estabelecimento |
| `clientes` | Clientes de cada estabelecimento |
| `funcionarios` | Profissionais cadastrados |
| `servicos` | Serviços, duração e preço |
| `funcionario_servico` | Associação de funcionários aos serviços que realizam |
| `agendamentos` | Cliente, funcionário, serviço, início, fim e situação do atendimento |

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

> **Atenção:** o banco independente já configurado para o projeto foi criado e recebeu as 8 tabelas. **Não reimporte ou execute o SQL nesse banco sem necessidade e sem backup.** Nunca execute esse script no banco do projeto original.

### 3. Configurar a conexão privada

Crie uma cópia de `conexao.exemplo.php` com o nome `conexao.php`, no mesmo diretório das páginas PHP. Na **cópia local ou no servidor sob seu controle**, configure os valores de `HOST`, `USUARIO`, `SENHA` e `DB` para o seu banco.

O arquivo `conexao.php` está incluído no `.gitignore` e **não deve ser enviado ao GitHub**. Não compartilhe senhas, credenciais ou arquivos privados em commits.

### 4. Abrir a aplicação

Em um ambiente local com PHP, por exemplo:

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000/index.php`. O servidor embutido é destinado ao **desenvolvimento**, não à produção. Dependendo do provedor de banco, conexões remotas podem exigir configuração específica; utilize um MySQL local de teste se necessário.

Crie um estabelecimento de teste, faça login e cadastre um **cliente**, um **serviço** e um **funcionário vinculado ao serviço** antes de testar os agendamentos.

## Testes

Há roteiros automatizados para validação de dados e identificadores de cancelamento, sem conectar ao banco:

```bash
php tests/agendamento-validacao.php
php tests/agendamento-cancelamento.php
```

A execução dos testes em PHP, os cadastros reais, os cancelamentos e os agendamentos concorrentes **ainda precisam ser confirmados em ambiente integrado**. Revisões estáticas e simulações do calendário não equivalem a um teste completo com MySQL.

## Status e próximos passos

- [x] Fork de desenvolvimento independente do projeto original.
- [x] Modelo de banco v2 criado e importado em conta própria; conexão básica testada.
- [x] Código de login, clientes, funcionários e serviços adaptado ao modelo v2.
- [x] Código de criação de agendamentos e calendário conectado à API.
- [x] Implementar no código o cancelamento de agendamentos ativos e revisar as regras de conflitos (**4.5**).
- [ ] Validar cancelamento e concorrência com sessões simultâneas no MySQL real.
- [x] Criar o tema base turquesa e padronizar cores, tipografia e componentes (**5.1**, revisão visual pendente).
- [ ] Revisar responsividade e experiência em celular (**5.2**).
- [ ] Executar testes funcionais e de segurança na hospedagem.
- [ ] Publicar a versão atualizada com controle e backup.
- [ ] Preparar dados fictícios, imagens das telas e apresentação da feira.
- [ ] Após aprovação e testes, fazer o merge para a `main` **deste fork**.

## Documentação complementar

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

## Histórico do projeto e créditos

O AgenDay 2.0 foi desenvolvido a partir do repositório acadêmico original [**oliv-gabriel/AgenDay-PI**](https://github.com/oliv-gabriel/AgenDay-PI). As melhorias aqui documentadas são realizadas no [**fork Emds96/AgenDay-PI**](https://github.com/Emds96/AgenDay-PI), utilizando uma branch de desenvolvimento separada.

Os nomes e papéis dos demais integrantes poderão ser adicionados após confirmação da equipe. **O projeto original é preservado**: alterações no fork não são enviadas automaticamente ao repositório de origem.

---

<div align="center">

**AgenDay 2.0** · Projeto acadêmico em evolução

</div>
