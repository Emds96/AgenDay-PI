# AgenDay — Revisão do banco de dados (microtarefa 2.1)

**Data:** 11/11/2026  
**Branch:** `dev-feira-uninorte`  
**Status:** revisão estática concluída; nenhuma mudança de esquema ou conexão foi realizada.

## Arquivos analisados

- `sql/BD.sql`
- `sql/BD1.sql`
- `login.php` e `cadastrar.php`
- `cadastro-clientes.php`, `cadastro-funcionario.php`, `cadastro-servicos.php`
- `info-clientes.php`, `info-funcionario.php`, `info-servicos.php`

## Estado identificado

O `sql/BD.sql` cria o banco `agenday` com **6 tabelas**:

| Tabela | Função prevista | Observação |
| --- | --- | --- |
| `proprietario` | Informações do proprietário | Não relacionada às demais |
| `estabelecimento` | Contas dos estabelecimentos | Usada em cadastro/login; senha armazenada em MD5 pelo PHP |
| `endereco` | Endereço e documentos | Não vinculada ao estabelecimento |
| `servicos` | Serviços e preços | Sem vínculo com estabelecimento ou funcionários |
| `clientes` | Cadastro de clientes | Sem vínculo com estabelecimento |
| `funcionarios` | Cadastro de funcionários | `servico_funcionario` é texto, não chave estrangeira |

O arquivo **não define chaves estrangeiras** e **não cria tabela de agendamentos**.

`sql/BD1.sql` descreve outro modelo, com tabela `usuarios`, que não corresponde às consultas atuais de login (que usam `estabelecimento`). Além disso, há uma vírgula final indevida após `senha VARCHAR(40) NOT NULL`, antes de `)`, causando erro de sintaxe SQL.

## Problemas concretos e riscos

1. **Isolamento dos dados:** não existe `id_estabelecimento` em `clientes`, `funcionarios` ou `servicos`; as consultas `SELECT *` não filtram pelo estabelecimento. Isso inviabiliza a separação adequada entre empresas.
2. **Agenda:** falta uma tabela de agendamentos com relações a cliente, funcionário, serviço e estabelecimento, além de data, horário e status.
3. **Telefones e documentos:** `INT(15)`, `INT(20)` e `INT(7)` não são tamanhos de armazenamento em dígitos; números podem exceder o limite de `INT` e perder zeros à esquerda. Usar `VARCHAR` para telefone, CNPJ e CEP.
4. **Preço:** `FLOAT(10,2)` não é a escolha ideal para valores monetários. Preferir `DECIMAL(10,2)`.
5. **Senhas:** o PHP usa MD5; migrar para `password_hash()`/`password_verify()` e ampliar `senha_estabelecimento` para `VARCHAR(255)`, sem reutilizar hashes MD5 como se fossem seguros.
6. **Integridade:** faltam chaves estrangeiras, restrições úteis (como unicidade do e-mail de login) e índices para buscas por data e estabelecimento.
7. **Nomes legados:** `email_cliete` e `descricacao_servico` apresentam grafias inconsistentes, mas são usados pelo PHP atual. Renomear somente com atualização coordenada das consultas.
8. **Cadastro de serviços:** o PHP envia a string `'null'` para `id_servicos` auto-incremental, em vez de omitir a coluna ou usar `NULL`. Pode falhar dependendo das configurações do MySQL.
9. **Arquivos SQL divergentes:** não executar `BD.sql` e `BD1.sql` indiscriminadamente; definir uma única fonte de verdade para o esquema.

## Decisões para a microtarefa 2.2

- Projetar o esquema corrigido para um banco **novo, independente**; não executar alterações no banco de terceiros.
- Preservar a compatibilidade do PHP atual enquanto o novo modelo é construído, ou documentar cada migração necessária.
- Vincular dados ao estabelecimento e incluir tabelas/relacionamentos de agendamento.
- Preparar script de criação e, quando necessário, scripts de migração separados.
- Não armazenar senhas ou credenciais do banco no repositório.

## Limites desta revisão

Esta é uma **inspeção do código versionado**, não um teste de execução SQL. Não foi possível confirmar o esquema efetivamente presente em qualquer servidor. O banco online independente ainda **não foi criado** e nenhuma conexão foi testada. A próxima microtarefa é **2.2 — corrigir a modelagem e os relacionamentos**.
