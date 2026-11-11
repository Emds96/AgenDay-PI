# Microtarefa 4.1 — Base para agendamentos

**Branch:** `dev-feira-uninorte`. Esta entrega não publica nada na InfinityFree, não altera `main`, nem executa SQL na hospedagem.

## Tabela existente

A tabela `agendamentos` **já estava no `sql/BD_v2.sql`**, importado com sucesso no banco independente na etapa 2.4, junto às outras sete tabelas. Portanto, **não devemos recriar nem importar o SQL novamente**.

Relações existentes:
- `id_estabelecimento` identifica a empresa;
- `id_cliente` referencia um cliente dessa mesma empresa;
- `(id_estabelecimento, id_funcionario, id_servicos)` referencia o par válido em `funcionario_servico`;
- `data_inicio` e `data_fim` são `DATETIME` locais, com duração positiva a validar no salvamento;
- `status` aceita `agendado`, `confirmado`, `concluido`, `cancelado`.

## Código criado

- `lib/agendamentos.php`: funções somente de leitura para consultar agendamentos e carregar catálogos de clientes, serviços e pares funcionário/serviço **sempre da empresa autenticada**. Usa `mysqli::prepare` e `bind_param`, sem depender de `mysqlnd`.
- `api/agendamentos.php`: endpoint JSON protegido por sessão, **somente GET**, para listar os agendamentos de um período; respostas 401, 405, 422 e 503 não expõem credenciais.
- Intervalo: `?inicio=2026-11-01&fim=2026-12-01`; o **fim é exclusivo**, período válido de 1 a 31 dias, e os agendamentos que cruzam o início ou fim também aparecem. Lista inclui cancelados (a interface poderá diferenciá-los).
- Limite de 500 linhas por requisição; períodos mais carregados solicitam janela menor.
- Nenhuma consulta aceita `id_estabelecimento` da URL ou do POST.

## Comportamento e próximos passos

Esta microtarefa **não cria agendamentos**. O formulário vem na 4.2; persistência no MySQL na 4.3; integração visual no calendário na 4.4; conflitos e cancelamento na 4.5. A integridade já existente no MySQL não substitui validação de datas, permissão e anti-sobreposição no momento da escrita.

## Testes e cuidados

- Sintaxe PHP e validação de datas foram verificadas localmente; **ainda não houve execução conectada ao banco do InfinityFree**, publicação ou teste de integração.
- Depois da publicação controlada, verificar resposta JSON com login, resposta 401 sem login, rejeição de intervalos inválidos, dados filtrados por estabelecimento e consultas de períodos diferentes.
- `conexao.php` continua privado e fora do GitHub.
