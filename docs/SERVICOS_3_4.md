# Microtarefa 3.4 — Cadastro de serviços

Alterações feitas somente em `dev-feira-uninorte`. **Não foram publicadas** no InfinityFree, nem alteram o MySQL ou a branch `main`.

## Entrega

- **`cadastro-servicos.php`**: verifica login, método POST e token CSRF, valida os campos e faz INSERT preparado com `id_estabelecimento` da sessão.
- Nome obrigatório (até 120 caracteres); descrição opcional (até 2.000 caracteres). A coluna correta do banco é `descricao_servico`.
- Duração digitada em **minutos inteiros de 1 a 1440**; armazenada como `HH:MM:00` no campo MySQL `TIME` (`duracao_servico`).
- Preço digitado em reais: `35`, `35,90` ou `35.90`; armazenado como string decimal exata em `DECIMAL(10,2)` (`preco_servico`), inclusive `0` para serviços gratuitos. Preços negativos são rejeitados. Valores acima de 8 dígitos inteiros são rejeitados.
- O banco tem índice único `(id_estabelecimento, nome_servico)`; serviço duplicado na mesma empresa recebe mensagem amigável, mas empresas distintas podem usar o mesmo nome.
- **`info-servicos.php`**: listagem e pesquisa por nome ou descrição, sempre filtradas pelo estabelecimento autenticado usando consultas preparadas.
- **`cad.servicos.php`**: formulário acessível com campo de minutos, preço brasileiro, descrição; listagem com preços formatados em R$, busca, mensagens de erro/sucesso e HTML escapado.

## Testes funcionais que faltam

1. Após publicação segura, cadastrar estabelecimento e fazer login.
2. Cadastrar `Corte`, duração `45` e preço `35,90`; confirmar que o banco guardou `00:45:00` e `35.90`.
3. Cadastrar serviço gratuito `0` e duração `90`; confirmar `01:30:00` e `0.00`.
4. Cadastrar nome duplicado na mesma empresa; confirmar a mensagem e apenas um registro.
5. Confirmar que outra empresa consegue cadastrar o mesmo nome sem ver dados da primeira.
6. Testar números inválidos, descrição acima de 2.000 caracteres e formulário sem token CSRF.
7. Pesquisar por nome e descrição, conferir o escape de caracteres especiais.
8. Na página de funcionários (3.3), escolher um ou mais serviços cadastrados e testar o vínculo N:N.

## Limitações

Implementação e revisão estática não substituem testes PHP/MySQL na hospedagem. Os formulários de alteração/exclusão de serviços ainda não estão implementados. O cronograma de agendamentos será tratado na etapa 4.