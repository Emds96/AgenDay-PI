# Microtarefa 3.3 — Cadastro de funcionários

Alterações feitas somente na branch `dev-feira-uninorte`. **Nenhuma publicação no InfinityFree, alteração de banco ou modificação na main.**

## Entrega
- **`cadastro-funcionario.php`**: valida nome e telefone, token CSRF, foto opcional (JPG/PNG, até 2 MB) e IDs dos serviços; grava com consultas preparadas.
- A inserção em `funcionarios` e as relações em `funcionario_servico` ocorrem numa **transação**; uma falha reverte a operação e apaga eventual foto nova.
- `id_estabelecimento` é lido **somente da sessão autenticada**. IDs de serviços são validados no mesmo estabelecimento; a FK composta do BD V2 reforça essa regra.
- **`info-funcionario.php`**: lista/pesquisa funcionários por nome, contato ou serviço; carrega a lista de serviços próprios para o formulário.
- **`cad-funcionario.php`**: formulário com nome/telefone obrigatórios; lista de serviços opcionais com múltipla seleção; foto opcional, busca, mensagens de erro e sucesso, saída HTML escapada.
- **`arquivos/funcionarios/.htaccess`**: tenta impedir listagem do diretório e execução de scripts.

## Detalhe de modelagem
O banco novo não possui a coluna antiga `servico_funcionario`. Os serviços de um funcionário são ligados pela tabela N:N `funcionario_servico`. Por isso, agora é possível selecionar **mais de um serviço**. Se não existirem serviços, o funcionário pode ser cadastrado sem vínculo. A tela informa que o vínculo poderá ser feito depois, mas **a interface de edição/vinculação posterior ainda não foi implementada**.

## Testes pendentes
1. Após publicação controlada, cadastrar funcionário **sem serviço nem foto** e conferir no MySQL.
2. Cadastrar serviço em 3.4 e testar a associação de um funcionário a um ou mais serviços.
3. Testar busca por nome, telefone e nome de serviço.
4. Validar rejeição de telefone inválido, serviço de outro estabelecimento, CSRF inválido, foto malformada e foto maior que 2 MB.
5. Confirmar que dois estabelecimentos não enxergam os funcionários/serviços um do outro.
6. Conferir se foto JPG/PNG pode ser exibida e scripts no diretório são bloqueados.

**Status:** Código implementado e revisado estaticamente, **ainda não testado em execução no InfinityFree**. Falta a microtarefa 3.4 e validação online.