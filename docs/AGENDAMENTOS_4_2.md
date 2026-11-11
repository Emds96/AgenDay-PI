# Microtarefa 4.2 — Tela de novo agendamento

Implementada na branch de desenvolvimento `dev-feira-uninorte`, sem publicar no InfinityFree, executar SQL ou alterar a main.

## Entrega

- **`agenda.php`** agora apresenta formulário de cliente, serviço, funcionário, data, horário e observações, com autenticação obrigatória.
- As escolhas vêm de `agenday_agenda_opcoes()` (4.1): clientes e serviços do próprio estabelecimento e **somente funcionários vinculados ao serviço** pela tabela `funcionario_servico`.
- A função de opções usa o `id_estabelecimento` da sessão; as opções são escapadas com `htmlspecialchars` e o JSON de relações usa `JSON_HEX_*` e substituição de UTF-8 inválido.
- **`js/agendamento-form.js`** atualiza os funcionários quando o serviço muda, evita envio acidental e mostra uma **prévia** com data/hora de início e término usando a duração cadastrada. Define o mínimo da data como hoje no relógio do visitante.
- **`css/agendamento-form.css`** fornece estilo responsivo isolado, sem quebrar os estilos do calendário antigo.
- Caso não existam clientes, serviços ou vínculos entre funcionários e serviços, o formulário informa o que cadastrar primeiro.
- Pequena correção de compatibilidade em `js/calendario.js`: janeiro não é mais substituído pelo mês atual; a ausência do toggle de modo escuro deixa de causar erro de JavaScript.

## Limites da 4.2

**O formulário ainda não cria agendamentos.** O botão de salvar está desabilitado, o JavaScript cancela o submit e o PHP responde 405 a POST em `agenda.php`. Isso não substitui a validação do servidor na 4.3. A prévia não confirma disponibilidade nem verifica conflitos.

Os horários estáticos e o calendário antigo são ilustrativos, não dados reais do MySQL. A gravação será implementada na 4.3, a integração do calendário na 4.4 e cancelamentos/conflitos na 4.5.

## Testes pendentes após publicação segura

1. Entrar no sistema e abrir `agenda.php`.
2. Cadastrar cliente, serviço e funcionário associado; conferir opções corretas no formulário.
3. Alternar serviços e verificar funcionários disponíveis e ausência de vínculos.
4. Escolher data e horário futuros e conferir cálculo de duração e término.
5. Sem clientes/serviços/vínculos, confirmar avisos e links para cadastros.
6. Em navegação sem login, confirmar proteção; testar que POST não salva nem altera dados.
7. Conferir uso no celular e dados isolados entre dois estabelecimentos.

Revisão do código no GitHub não é teste PHP/MySQL em execução. `conexao.php` permanece privado no servidor.