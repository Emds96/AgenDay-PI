# Microtarefa 4.3 — Salvar agendamentos no MySQL

**Entregue no código** da branch `dev-feira-uninorte` em 11/11/2026. **Não publicado no InfinityFree nem testado conectado ao banco online.** Não alterar nem importar novamente o `BD_v2.sql`: a tabela `agendamentos` já existe no banco independente.

## Arquivos

- `salvar-agendamento.php`: endpoint **POST** autenticado, com token CSRF e redirecionamento 303 para `agenda.php`, exibindo mensagem de sucesso ou erro. GET não salva.
- `lib/agendamento-gravar.php`: valida todos os IDs positivos, data real, horário em intervalos de 15 minutos, horário futuro até 366 dias, texto opcional; calcula a duração consultando `servicos` no banco. Insere na tabela `agendamentos` somente após verificar cliente, funcionário e par funcionário/serviço da própria empresa.
- `agenda.php`: formulário 4.2 agora aponta para `salvar-agendamento.php`, o botão **Salvar agendamento** está ativo quando há cliente, serviço e par funcionário/serviço; mensagens após o redirect são escapadas.
- `js/agendamento-form.js`: remove o bloqueio de envio da 4.2, mantém seleção de funcionário filtrada pelo serviço e a prévia de duração, e evita duplo clique após validar o formulário.
- `tests/agendamento-validacao.php`: roteiro CLI de testes da validação, **sem conexão com MySQL**. Executar no ambiente de desenvolvimento com `php tests/agendamento-validacao.php`.

## Segurança e regras

1. `id_estabelecimento` vem **exclusivamente** de `$_SESSION['id_estabelecimento']`; nunca do POST.
2. O servidor consulta a duração `TIME` do serviço vinculado ao funcionário; data de término e status **não vêm do navegador**.
3. As consultas e o INSERT usam prepared statements; chaves compostas da tabela validam o vínculo com a empresa.
4. Há uma **transação** com `SELECT ... FOR UPDATE` sobre a linha do funcionário para serializar reservas concorrentes da mesma pessoa. A consulta de conflitos verifica interseção de intervalos semiabertos `[início, fim)` em reservas `agendado` ou `confirmado`; cancelados e concluídos não bloqueiam.
5. Qualquer erro durante a transação executa `rollback`. Uma atualização da página após o POST não repete o INSERT, pois é usado redirect 303.
6. O fuso horário adotado **provisoriamente** é `America/Manaus` (ou `AGENDAY_TIMEZONE`, se definido na configuração privada). Quando houver estabelecimentos em cidades com fusos diferentes, será necessário salvar fuso por estabelecimento.

## O que falta verificar antes de publicar o sistema

- Testar PHP na hospedagem (versão, mysqli, datas), formulário com login real e escrita/consulta real no banco independente.
- Cadastrar cliente, serviço e funcionário associado; criar uma reserva para data futura. Confirmar registros e duração em `agendamentos`.
- Testar data inválida, CSRF inválido, cliente de outra empresa, funcionário de outra empresa, par não vinculado, serviço inativo/inexistente.
- Tentar agendamento no mesmo horário em duas sessões ao mesmo tempo, confirmar que apenas um é salvo; depois tentar horários consecutivos para o mesmo funcionário.
- Testar duas contas de estabelecimento distintas e comprovar isolamento e tratamento de transações.
- Verificar avisos e redirecionamento após POST e aparência no celular.

## Próximas tarefas

- **4.4:** conectar o calendário visual aos registros de agendamentos, sem mostrar horários estáticos como reservas reais.
- **4.5:** implementar cancelamento, regras adicionais de conflitos/horários e validar a integração completa.

**Não colocar senhas no GitHub e não publicar a aplicação inteira antes de executar os testes de segurança em um ambiente controlado.**