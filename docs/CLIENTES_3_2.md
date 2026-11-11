# Microtarefa 3.2 — Cadastro de clientes

Alterado somente na branch `dev-feira-uninorte` do AgenDay 2.0. O código **não foi publicado** e não alterou o banco MySQL.

## Entrega implementada

- `cadastro-clientes.php`: valida os dados e o token CSRF, usa o ID de estabelecimento da sessão e INSERT preparado com as colunas corretas do `BD_v2.sql`.
- `info-clientes.php`: SELECT preparado filtrado pelo estabelecimento autenticado. Pesquisa opcional por nome, e-mail ou telefone.
- `cad-cliente.php`: formulário com campos obrigatórios e opcionais, pesquisa GET, avisos de erro/sucesso e saída com `htmlspecialchars()` para evitar XSS. Corrigido `email_cliete` para `email_cliente`.
- Upload opcional de JPG/PNG com tamanho máximo de 2 MB, dimensões de até 4096 x 4096, MIME verificado, nome aleatório e limpeza do arquivo em caso de falha da inserção.
- `.htaccess` em `arquivos/clientes` impede listagem de diretório e tenta bloquear execução de scripts (a eficácia deve ser conferida na hospedagem).

## Testes ainda necessários na hospedagem

1. Criar uma conta de estabelecimento e entrar no sistema após publicação controlada.
2. Abrir `cad-cliente.php` com e sem login; confirmar proteção da sessão.
3. Cadastrar cliente com nome e contato, sem e-mail/foto; verificar a linha em `clientes` no banco independente.
4. Cadastrar cliente com e-mail e foto JPG/PNG; confirmar foto exibida na listagem.
5. Tentar arquivo inválido, foto acima de 2 MB e formulário com token CSRF inválido; verificar rejeição.
6. Pesquisar por nome, contato e e-mail. Verificar nomes com caracteres especiais e saída HTML escapada.
7. Usar dois estabelecimentos e conferir que nenhum vê clientes do outro.

## Atenção

Este é o código preparado, não uma confirmação de execução online. Sem alterações em `main`, no deploy automático original ou nas credenciais privadas da hospedagem. Ainda faltam as microtarefas 3.3 e 3.4 antes da publicação do aplicativo completo.