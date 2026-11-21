<?php
declare(strict_types=1);

/**
 * Disponibilidade real da agenda.
 *
 * Combina horário de funcionamento, almoço, bloqueios, agendamentos existentes
 * e as regras do estabelecimento (intervalo, antecedência, janela).
 * O id_estabelecimento vem SEMPRE da sessão.
 */

require_once __DIR__ . '/horario-funcionamento.php';

function agenday_disp_fuso(): DateTimeZone
{
    return new DateTimeZone(defined('AGENDAY_TIMEZONE') ? AGENDAY_TIMEZONE : 'America/Manaus');
}

function agenday_disp_data_valida($valor): ?string
{
    if (!is_string($valor) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $valor)) {
        return null;
    }
    $data = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, new DateTimeZone('UTC'));
    return ($data !== false && $data->format('Y-m-d') === $valor) ? $valor : null;
}

function agenday_disp_hora_valida($valor): ?string
{
    if (!is_string($valor) || !preg_match('/\A([01][0-9]|2[0-3]):([0-5][0-9])\z/D', $valor, $m)) {
        return null;
    }
    return $m[1] . ':' . $m[2];
}

/** Minutos de uma duração TIME vinda do banco. */
function agenday_disp_duracao_minutos(string $duracao): int
{
    if (!preg_match('/\A(\d{1,3}):([0-5]\d):([0-5]\d)\z/D', $duracao, $m)) {
        throw new DomainException('A duração cadastrada para este serviço é inválida.');
    }
    $minutos = ((int) $m[1]) * 60 + (int) $m[2];
    if ($minutos < 1 || $minutos > 1440) {
        throw new DomainException('O serviço precisa ter duração entre 1 e 1440 minutos.');
    }
    return $minutos;
}

/** @return array{id:int,nome:string,duracao:int,preco:string}|null */
function agenday_disp_servico(mysqli $conexao, int $idEstabelecimento, int $idServico): ?array
{
    $consulta = $conexao->prepare(
        'SELECT id_servicos, nome_servico, duracao_servico, preco_servico
         FROM servicos WHERE id_estabelecimento = ? AND id_servicos = ? LIMIT 1'
    );
    $consulta->bind_param('ii', $idEstabelecimento, $idServico);
    $consulta->execute();
    $linha = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    if (!$linha) {
        return null;
    }
    return [
        'id' => (int) $linha['id_servicos'],
        'nome' => (string) $linha['nome_servico'],
        'duracao' => agenday_disp_duracao_minutos((string) $linha['duracao_servico']),
        'preco' => (string) $linha['preco_servico'],
    ];
}

/** Profissionais que realizam o serviço. */
function agenday_disp_profissionais(mysqli $conexao, int $idEstabelecimento, int $idServico): array
{
    $consulta = $conexao->prepare(
        'SELECT f.id_funcionario, f.nome_funcionario
         FROM funcionario_servico fs
         INNER JOIN funcionarios f
           ON f.id_estabelecimento = fs.id_estabelecimento
          AND f.id_funcionario = fs.id_funcionario
         WHERE fs.id_estabelecimento = ? AND fs.id_servicos = ?
         ORDER BY f.nome_funcionario'
    );
    $consulta->bind_param('ii', $idEstabelecimento, $idServico);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $lista = [];
    while ($linha = $resultado->fetch_assoc()) {
        $lista[] = [
            'id' => (int) $linha['id_funcionario'],
            'nome' => (string) $linha['nome_funcionario'],
        ];
    }
    $consulta->close();
    return $lista;
}

function agenday_disp_regras(mysqli $conexao, int $idEstabelecimento): array
{
    $regras = agenday_regras_padrao();
    $consulta = $conexao->prepare(
        'SELECT intervalo_minutos, antecedencia_minima_minutos, janela_maxima_dias
         FROM estabelecimento WHERE id_estabelecimento = ? LIMIT 1'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $consulta->bind_result($intervalo, $antecedencia, $janela);
    if ($consulta->fetch()) {
        $regras = [
            'intervalo' => (int) $intervalo,
            'antecedencia' => (int) $antecedencia,
            'janela' => (int) $janela,
        ];
    }
    $consulta->close();
    return $regras;
}

/** Bloqueios que tocam o dia (conta inteira ou de um profissional). */
function agenday_disp_bloqueios(mysqli $conexao, int $idEstabelecimento, string $dia): array
{
    $inicioDia = $dia . ' 00:00:00';
    $fimDia = $dia . ' 23:59:59';
    $consulta = $conexao->prepare(
        'SELECT id_funcionario, inicio, fim
         FROM bloqueio
         WHERE id_estabelecimento = ? AND inicio <= ? AND fim >= ?'
    );
    $consulta->bind_param('iss', $idEstabelecimento, $fimDia, $inicioDia);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $lista = [];
    while ($linha = $resultado->fetch_assoc()) {
        $lista[] = [
            'funcionario' => $linha['id_funcionario'] === null ? null : (int) $linha['id_funcionario'],
            'inicio' => (string) $linha['inicio'],
            'fim' => (string) $linha['fim'],
        ];
    }
    $consulta->close();
    return $lista;
}

/** Agendamentos ativos do dia, por profissional. */
function agenday_disp_ocupados(mysqli $conexao, int $idEstabelecimento, string $dia): array
{
    $consulta = $conexao->prepare(
        "SELECT id_funcionario, data_inicio, data_fim
         FROM agendamentos
         WHERE id_estabelecimento = ?
           AND status IN ('agendado', 'confirmado')
           AND data_inicio < ? AND data_fim > ?"
    );
    $fimDia = $dia . ' 23:59:59';
    $inicioDia = $dia . ' 00:00:00';
    $consulta->bind_param('iss', $idEstabelecimento, $fimDia, $inicioDia);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $lista = [];
    while ($linha = $resultado->fetch_assoc()) {
        $lista[] = [
            'funcionario' => (int) $linha['id_funcionario'],
            'inicio' => (string) $linha['data_inicio'],
            'fim' => (string) $linha['data_fim'],
        ];
    }
    $consulta->close();
    return $lista;
}

/**
 * Horários do dia para um serviço.
 *
 * @param int|null $idFuncionario null = qualquer profissional
 * @return array{data:string,fechado:bool,motivo:?string,servico:?array,profissionais:array,slots:array}
 */
function agenday_disponibilidade_dia(
    mysqli $conexao,
    int $idEstabelecimento,
    string $dia,
    int $idServico,
    ?int $idFuncionario = null
): array {
    $resultado = [
        'data' => $dia,
        'fechado' => false,
        'motivo' => null,
        'servico' => null,
        'profissionais' => [],
        'slots' => [],
    ];

    $servico = agenday_disp_servico($conexao, $idEstabelecimento, $idServico);
    if ($servico === null) {
        $resultado['motivo'] = 'Serviço não encontrado.';
        return $resultado;
    }
    $resultado['servico'] = $servico;

    $profissionais = agenday_disp_profissionais($conexao, $idEstabelecimento, $idServico);
    if ($idFuncionario !== null) {
        $profissionais = array_values(array_filter(
            $profissionais,
            static fn(array $p): bool => $p['id'] === $idFuncionario
        ));
        if ($profissionais === []) {
            $resultado['motivo'] = 'Este profissional não realiza o serviço escolhido.';
            return $resultado;
        }
    }
    $resultado['profissionais'] = $profissionais;
    if ($profissionais === []) {
        $resultado['motivo'] = 'Nenhum profissional está vinculado a este serviço.';
        return $resultado;
    }

    $regras = agenday_disp_regras($conexao, $idEstabelecimento);
    $fuso = agenday_disp_fuso();
    $hoje = new DateTimeImmutable('now', $fuso);
    $dataAlvo = DateTimeImmutable::createFromFormat('!Y-m-d', $dia, $fuso);
    if ($dataAlvo === false) {
        $resultado['motivo'] = 'Data inválida.';
        return $resultado;
    }
    $diasAFrente = (int) $hoje->setTime(0, 0)->diff($dataAlvo->setTime(0, 0))->format('%r%a');
    if ($diasAFrente < 0) {
        $resultado['motivo'] = 'Não é possível agendar em data passada.';
        return $resultado;
    }
    if ($diasAFrente > (int) $regras['janela']) {
        $resultado['motivo'] = 'Esta data está além do limite de ' . (int) $regras['janela'] . ' dias.';
        return $resultado;
    }

    $horario = agenday_horario_carregar($conexao, $idEstabelecimento);
    $numeroDia = (int) $dataAlvo->format('w');
    $janelas = agenday_horario_janelas($horario[$numeroDia] ?? []);
    if ($janelas === []) {
        $resultado['fechado'] = true;
        $resultado['motivo'] = 'O estabelecimento não atende neste dia.';
        return $resultado;
    }

    $duracao = (int) $servico['duracao'];
    $passo = max(5, (int) $regras['intervalo']);
    $bloqueios = agenday_disp_bloqueios($conexao, $idEstabelecimento, $dia);
    $ocupados = agenday_disp_ocupados($conexao, $idEstabelecimento, $dia);
    $limiteAntecedencia = $hoje->modify('+' . max(0, (int) $regras['antecedencia']) . ' minutes');

    foreach ($janelas as $janela) {
        $inicioJanela = agenday_horario_minutos((string) $janela['inicio']);
        $fimJanela = agenday_horario_minutos((string) $janela['fim']);
        for ($minuto = $inicioJanela; $minuto + $duracao <= $fimJanela; $minuto += $passo) {
            $hora = sprintf('%02d:%02d', intdiv($minuto, 60), $minuto % 60);
            $inicioSlot = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dia . ' ' . $hora, $fuso);
            $fimSlot = $inicioSlot->modify('+' . $duracao . ' minutes');
            $inicioBanco = $inicioSlot->format('Y-m-d H:i:s');
            $fimBanco = $fimSlot->format('Y-m-d H:i:s');

            $passado = $inicioSlot < $limiteAntecedencia;
            $livres = [];
            foreach ($profissionais as $profissional) {
                if ($passado) {
                    continue;
                }
                $bloqueado = false;
                foreach ($bloqueios as $bloqueio) {
                    if ($bloqueio['funcionario'] !== null && $bloqueio['funcionario'] !== $profissional['id']) {
                        continue;
                    }
                    if ($bloqueio['inicio'] < $fimBanco && $bloqueio['fim'] > $inicioBanco) {
                        $bloqueado = true;
                        break;
                    }
                }
                if ($bloqueado) {
                    continue;
                }
                $ocupado = false;
                foreach ($ocupados as $agendamento) {
                    if ($agendamento['funcionario'] !== $profissional['id']) {
                        continue;
                    }
                    if ($agendamento['inicio'] < $fimBanco && $agendamento['fim'] > $inicioBanco) {
                        $ocupado = true;
                        break;
                    }
                }
                if (!$ocupado) {
                    $livres[] = $profissional;
                }
            }

            $resultado['slots'][] = [
                'hora' => $hora,
                'fim' => $fimSlot->format('H:i'),
                'livre' => $livres !== [],
                'passado' => $passado,
                'profissionais' => $livres,
            ];
        }
    }

    return $resultado;
}

/**
 * Escolhe um profissional livre para o horário e devolve o id, ou null.
 */
function agenday_disponibilidade_escolher(
    mysqli $conexao,
    int $idEstabelecimento,
    string $dia,
    string $hora,
    int $idServico,
    ?int $idFuncionario
): ?int {
    $resultado = agenday_disponibilidade_dia($conexao, $idEstabelecimento, $dia, $idServico, $idFuncionario);
    foreach ($resultado['slots'] as $slot) {
        if ($slot['hora'] !== $hora || !$slot['livre']) {
            continue;
        }
        if ($idFuncionario !== null) {
            return $idFuncionario;
        }
        // "Qualquer um": usa o primeiro profissional livre do horário.
        $escolhido = $slot['profissionais'][0];
        return (int) $escolhido['id'];
    }
    return null;
}
