<?php
/**
 * AgenDay 2.0 — consultas de agendamentos (microtarefa 4.1).
 * Somente leitura. Criar, alterar e cancelar serão implementados depois.
 */
declare(strict_types=1);
require_once __DIR__ . '/../sessao.php';

/** Obtém o estabelecimento exclusivamente da sessão autenticada. */
function agenday_agenda_estabelecimento(): int
{
    $id = $_SESSION['id_estabelecimento'] ?? null;
    if (!is_int($id) || $id < 1) {
        throw new RuntimeException('Sessão do estabelecimento necessária.');
    }
    return $id;
}

/**
 * Converte um intervalo de dias para DATETIME, usando limite final exclusivo.
 * Exemplo: 2026-11-01 a 2026-12-01 cobre todo o mês de novembro.
 * Não há conversão de fuso: as colunas DATETIME representam horário local do estabelecimento.
 * UTC é usado somente para validar a aritmética das datas, sem aplicar deslocamentos.
 * @return array{0:string,1:string}
 */
function agenday_agenda_periodo(string $inicio, string $fim): array
{
    $utc = new DateTimeZone('UTC');
    $datas = [];
    foreach ([$inicio, $fim] as $valor) {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $valor)) {
            throw new InvalidArgumentException('Informe as datas no formato AAAA-MM-DD.');
        }
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, $utc);
        if ($data === false || $data->format('Y-m-d') !== $valor) {
            throw new InvalidArgumentException('Data inválida no período solicitado.');
        }
        $datas[] = $data;
    }
    [$de, $ate] = $datas;
    $dias = (int) $de->diff($ate)->format('%r%a');
    if ($dias < 1 || $dias > 31) {
        throw new InvalidArgumentException('Escolha um período de 1 a 31 dias, com fim exclusivo.');
    }
    return [$inicio . ' 00:00:00', $fim . ' 00:00:00'];
}

/**
 * Lista agendamentos que INTERCEPTAM o período, incluindo os cancelados.
 * A condição por início/fim inclui agendamentos iniciados no dia anterior.
 * Exige sessão autenticada; nunca recebe id_estabelecimento do visitante.
 *
 * @return array<int,array<string,int|string|null>>
 */
function agenday_agenda_listar(mysqli $conexao, string $inicio, string $fim): array
{
    [$de, $ate] = agenday_agenda_periodo($inicio, $fim);
    $idEstabelecimento = agenday_agenda_estabelecimento();
    $stmt = $conexao->prepare(
        'SELECT a.id_agendamento, a.id_cliente, c.nome_cliente,
                a.id_funcionario, f.nome_funcionario,
                a.id_servicos, s.nome_servico,
                a.data_inicio, a.data_fim, a.status, a.observacoes
         FROM agendamentos a
         INNER JOIN clientes c ON c.id_estabelecimento = a.id_estabelecimento
                              AND c.id_cliente = a.id_cliente
         INNER JOIN funcionarios f ON f.id_estabelecimento = a.id_estabelecimento
                                 AND f.id_funcionario = a.id_funcionario
         INNER JOIN servicos s ON s.id_estabelecimento = a.id_estabelecimento
                             AND s.id_servicos = a.id_servicos
         WHERE a.id_estabelecimento = ?
           AND a.data_inicio < ? AND a.data_fim > ?
         ORDER BY a.data_inicio ASC, a.id_agendamento ASC
         LIMIT 501'
    );
    $stmt->bind_param('iss', $idEstabelecimento, $ate, $de);
    $stmt->execute();
    $stmt->bind_result(
        $idAgendamento, $idCliente, $nomeCliente, $idFuncionario,
        $nomeFuncionario, $idServico, $nomeServico,
        $dataInicio, $dataFim, $status, $observacoes
    );
    $linhas = [];
    while ($stmt->fetch()) {
        $linhas[] = [
            'id_agendamento' => (int) $idAgendamento,
            'id_cliente' => (int) $idCliente,
            'nome_cliente' => $nomeCliente,
            'id_funcionario' => (int) $idFuncionario,
            'nome_funcionario' => $nomeFuncionario,
            'id_servicos' => (int) $idServico,
            'nome_servico' => $nomeServico,
            'data_inicio' => $dataInicio,
            'data_fim' => $dataFim,
            'status' => $status,
            'observacoes' => $observacoes,
        ];
    }
    $stmt->close();
    if (count($linhas) > 500) {
        throw new DomainException('Há muitos agendamentos no período. Selecione menos dias.');
    }
    return $linhas;
}

/**
 * Obtém opções seguras para a futura tela de agendamento.
 * Funcionário/serviço só aparece como combinação se houver vínculo N:N.
 * @return array<string,array<int,array<string,int|string>>>
 */
function agenday_agenda_opcoes(mysqli $conexao): array
{
    $idEstabelecimento = agenday_agenda_estabelecimento();
    $resultado = ['clientes' => [], 'servicos' => [], 'funcionarios_servicos' => []];

    $stmt = $conexao->prepare(
        'SELECT id_cliente, nome_cliente FROM clientes
         WHERE id_estabelecimento = ? ORDER BY nome_cliente, id_cliente'
    );
    $stmt->bind_param('i', $idEstabelecimento);
    $stmt->execute();
    $stmt->bind_result($idCliente, $nomeCliente);
    while ($stmt->fetch()) {
        $resultado['clientes'][] = ['id_cliente' => (int) $idCliente, 'nome_cliente' => $nomeCliente];
    }
    $stmt->close();

    $stmt = $conexao->prepare(
        'SELECT id_servicos, nome_servico, duracao_servico FROM servicos
         WHERE id_estabelecimento = ? ORDER BY nome_servico, id_servicos'
    );
    $stmt->bind_param('i', $idEstabelecimento);
    $stmt->execute();
    $stmt->bind_result($idServico, $nomeServico, $duracaoServico);
    while ($stmt->fetch()) {
        $resultado['servicos'][] = [
            'id_servicos' => (int) $idServico,
            'nome_servico' => $nomeServico,
            'duracao_servico' => $duracaoServico,
        ];
    }
    $stmt->close();

    $stmt = $conexao->prepare(
        'SELECT f.id_funcionario, f.nome_funcionario, fs.id_servicos
         FROM funcionario_servico fs
         INNER JOIN funcionarios f ON f.id_estabelecimento = fs.id_estabelecimento
                                  AND f.id_funcionario = fs.id_funcionario
         WHERE fs.id_estabelecimento = ?
         ORDER BY f.nome_funcionario, f.id_funcionario, fs.id_servicos'
    );
    $stmt->bind_param('i', $idEstabelecimento);
    $stmt->execute();
    $stmt->bind_result($idFuncionario, $nomeFuncionario, $idServico);
    while ($stmt->fetch()) {
        $resultado['funcionarios_servicos'][] = [
            'id_funcionario' => (int) $idFuncionario,
            'nome_funcionario' => $nomeFuncionario,
            'id_servicos' => (int) $idServico,
        ];
    }
    $stmt->close();

    return $resultado;
}
