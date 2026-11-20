<?php
declare(strict_types=1);

/**
 * Horário de funcionamento, bloqueios e regras de agendamento.
 *
 * O id_estabelecimento vem sempre da sessão autenticada, nunca do formulário.
 * Quando o estabelecimento ainda não tem linhas em horario_funcionamento,
 * devolvemos o padrão (seg-sáb 08:00-18:00, domingo fechado) para que a tela
 * e a agenda funcionem sem precisar popular o banco.
 */

const AGENDAY_HORARIO_INTERVALOS = [10, 15, 20, 30, 45, 60];

function agenday_dias_semana(): array
{
    return [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado',
    ];
}

function agenday_horario_padrao(): array
{
    $dias = [];
    foreach (agenday_dias_semana() as $numero => $nome) {
        $dias[$numero] = [
            'aberto' => $numero !== 0,
            'abertura' => '08:00',
            'fechamento' => $numero === 6 ? '12:00' : '18:00',
            'almoco_inicio' => '',
            'almoco_fim' => '',
        ];
    }
    return $dias;
}

function agenday_regras_padrao(): array
{
    return [
        'intervalo' => 30,
        'antecedencia' => 60,
        'janela' => 60,
        'cancelamento' => 24,
        'tolerancia' => 10,
    ];
}

/** Aceita "08:00", "08:00:00" e devolve sempre "HH:MM"; null se inválido. */
function agenday_horario_hora($valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }
    $valor = trim($valor);
    if (!preg_match('/\A([01][0-9]|2[0-3]):([0-5][0-9])(?::[0-5][0-9])?\z/D', $valor, $partes)) {
        return null;
    }
    return $partes[1] . ':' . $partes[2];
}

function agenday_horario_minutos(string $hora): int
{
    $partes = explode(':', $hora);
    return ((int) ($partes[0] ?? 0)) * 60 + ((int) ($partes[1] ?? 0));
}

function agenday_horario_carregar(mysqli $conexao, int $idEstabelecimento): array
{
    $dias = agenday_horario_padrao();
    $consulta = $conexao->prepare(
        'SELECT dia_semana, aberto, hora_abertura, hora_fechamento, inicio_almoco, fim_almoco
         FROM horario_funcionamento
         WHERE id_estabelecimento = ?'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $consulta->bind_result($diaSemana, $aberto, $abertura, $fechamento, $inicioAlmoco, $fimAlmoco);
    while ($consulta->fetch()) {
        $diaSemana = (int) $diaSemana;
        if (!array_key_exists($diaSemana, $dias)) {
            continue;
        }
        $dias[$diaSemana] = [
            'aberto' => (int) $aberto === 1,
            'abertura' => substr((string) $abertura, 0, 5),
            'fechamento' => substr((string) $fechamento, 0, 5),
            'almoco_inicio' => $inicioAlmoco !== null ? substr((string) $inicioAlmoco, 0, 5) : '',
            'almoco_fim' => $fimAlmoco !== null ? substr((string) $fimAlmoco, 0, 5) : '',
        ];
    }
    $consulta->close();
    return $dias;
}

function agenday_regras_carregar(mysqli $conexao, int $idEstabelecimento): array
{
    $regras = agenday_regras_padrao();
    $consulta = $conexao->prepare(
        'SELECT intervalo_minutos, antecedencia_minima_minutos, janela_maxima_dias,
                horas_cancelamento, tolerancia_atraso_minutos
         FROM estabelecimento WHERE id_estabelecimento = ? LIMIT 1'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $consulta->bind_result($intervalo, $antecedencia, $janela, $cancelamento, $tolerancia);
    if ($consulta->fetch()) {
        $regras = [
            'intervalo' => (int) $intervalo,
            'antecedencia' => (int) $antecedencia,
            'janela' => (int) $janela,
            'cancelamento' => (int) $cancelamento,
            'tolerancia' => (int) $tolerancia,
        ];
    }
    $consulta->close();
    return $regras;
}

/**
 * Valida o POST de funcionamento.
 * @return array{0: array, 1: string[]} [dias normalizados, erros]
 */
function agenday_horario_validar(array $post): array
{
    $erros = [];
    $dias = [];
    $entrada = isset($post['dias']) && is_array($post['dias']) ? $post['dias'] : [];
    $nomes = agenday_dias_semana();
    $algumAberto = false;

    foreach (agenday_horario_padrao() as $numero => $padrao) {
        $item = isset($entrada[$numero]) && is_array($entrada[$numero]) ? $entrada[$numero] : [];
        $aberto = isset($item['aberto']) && (string) $item['aberto'] === '1';
        $abertura = agenday_horario_hora($item['abertura'] ?? null) ?? '';
        $fechamento = agenday_horario_hora($item['fechamento'] ?? null) ?? '';
        $inicioAlmoco = agenday_horario_hora($item['almoco_inicio'] ?? null) ?? '';
        $fimAlmoco = agenday_horario_hora($item['almoco_fim'] ?? null) ?? '';

        $dias[$numero] = [
            'aberto' => $aberto,
            'abertura' => $abertura !== '' ? $abertura : $padrao['abertura'],
            'fechamento' => $fechamento !== '' ? $fechamento : $padrao['fechamento'],
            'almoco_inicio' => $aberto ? $inicioAlmoco : '',
            'almoco_fim' => $aberto ? $fimAlmoco : '',
        ];

        if (!$aberto) {
            continue;
        }

        $algumAberto = true;
        $rotulo = $nomes[$numero];

        if ($abertura === '' || $fechamento === '') {
            $erros[] = $rotulo . ': informe o horário de abertura e de fechamento.';
            continue;
        }
        if (agenday_horario_minutos($abertura) >= agenday_horario_minutos($fechamento)) {
            $erros[] = $rotulo . ': o fechamento precisa ser depois da abertura.';
            continue;
        }
        if (($inicioAlmoco === '') !== ($fimAlmoco === '')) {
            $erros[] = $rotulo . ': preencha o início e o fim do almoço, ou deixe os dois vazios.';
            continue;
        }
        if ($inicioAlmoco !== '') {
            $inicio = agenday_horario_minutos($inicioAlmoco);
            $fim = agenday_horario_minutos($fimAlmoco);
            if ($inicio >= $fim) {
                $erros[] = $rotulo . ': o fim do almoço precisa ser depois do início.';
            } elseif ($inicio <= agenday_horario_minutos($abertura)
                || $fim >= agenday_horario_minutos($fechamento)) {
                $erros[] = $rotulo . ': o almoço precisa ficar dentro do horário de funcionamento.';
            }
        }
    }

    if (!$algumAberto) {
        $erros[] = 'Mantenha pelo menos um dia de funcionamento aberto.';
    }

    return [$dias, array_values(array_unique($erros))];
}

/**
 * @return array{0: array, 1: string[]}
 */
function agenday_regras_validar(array $post): array
{
    $entrada = isset($post['regras']) && is_array($post['regras']) ? $post['regras'] : [];
    $padrao = agenday_regras_padrao();
    $regras = [];
    $erros = [];

    // Intervalo da agenda: um só para a semana inteira.
    $intervalo = filter_var($entrada['intervalo'] ?? null, FILTER_VALIDATE_INT);
    if (!in_array($intervalo, AGENDAY_HORARIO_INTERVALOS, true)) {
        $erros[] = 'Escolha de quanto em quanto tempo os horários são oferecidos.';
        $intervalo = $padrao['intervalo'];
    }
    $regras['intervalo'] = $intervalo;

    $limites = [
        'antecedencia' => [0, 1440, 'A antecedência mínima deve ficar entre 0 e 1440 minutos.'],
        'janela' => [1, 365, 'A janela de agendamento deve ficar entre 1 e 365 dias.'],
        'cancelamento' => [0, 720, 'O prazo de cancelamento deve ficar entre 0 e 720 horas.'],
        'tolerancia' => [0, 120, 'A tolerância de atraso deve ficar entre 0 e 120 minutos.'],
    ];

    foreach ($limites as $chave => [$minimo, $maximo, $mensagem]) {
        $valor = filter_var($entrada[$chave] ?? null, FILTER_VALIDATE_INT);
        if ($valor === false || $valor < $minimo || $valor > $maximo) {
            $erros[] = $mensagem;
            $valor = $padrao[$chave];
        }
        $regras[$chave] = $valor;
    }

    return [$regras, $erros];
}

/**
 * Cria os 7 dias padrão para um estabelecimento que acabou de ser cadastrado.
 * Não sobrescreve nada (INSERT IGNORE) e nunca interrompe o cadastro: se a
 * migração de horário ainda não estiver aplicada, apenas registra no log e a
 * conta continua sendo criada normalmente.
 */
function agenday_horario_criar_padrao(mysqli $conexao, int $idEstabelecimento): bool
{
    try {
        $gravacao = $conexao->prepare(
            'INSERT IGNORE INTO horario_funcionamento
                (id_estabelecimento, dia_semana, aberto, hora_abertura, hora_fechamento,
                 inicio_almoco, fim_almoco)
             VALUES (?, ?, ?, ?, ?, NULL, NULL)'
        );

        foreach (agenday_horario_padrao() as $numeroDia => $dia) {
            $numeroDia = (int) $numeroDia;
            $aberto = !empty($dia['aberto']) ? 1 : 0;
            $abertura = (string) $dia['abertura'] . ':00';
            $fechamento = (string) $dia['fechamento'] . ':00';
            $gravacao->bind_param(
                'iiiss',
                $idEstabelecimento,
                $numeroDia,
                $aberto,
                $abertura,
                $fechamento
            );
            $gravacao->execute();
        }
        $gravacao->close();
        return true;
    } catch (Throwable $e) {
        error_log(
            'AgenDay: horário padrão não criado para o estabelecimento ' . $idEstabelecimento
            . ' (' . get_class($e) . ').'
        );
        return false;
    }
}

/** Grava os 7 dias e as regras na mesma transação. */
function agenday_horario_salvar(mysqli $conexao, int $idEstabelecimento, array $dias, array $regras): void
{
    $conexao->begin_transaction();
    try {
        $gravacao = $conexao->prepare(
            'INSERT INTO horario_funcionamento
                (id_estabelecimento, dia_semana, aberto, hora_abertura, hora_fechamento,
                 inicio_almoco, fim_almoco)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                aberto = VALUES(aberto),
                hora_abertura = VALUES(hora_abertura),
                hora_fechamento = VALUES(hora_fechamento),
                inicio_almoco = VALUES(inicio_almoco),
                fim_almoco = VALUES(fim_almoco)'
        );

        foreach ($dias as $numeroDia => $dia) {
            $numeroDia = (int) $numeroDia;
            $aberto = !empty($dia['aberto']) ? 1 : 0;
            $abertura = (string) $dia['abertura'] . ':00';
            $fechamento = (string) $dia['fechamento'] . ':00';
            $inicioAlmoco = $dia['almoco_inicio'] !== '' ? (string) $dia['almoco_inicio'] . ':00' : null;
            $fimAlmoco = $dia['almoco_fim'] !== '' ? (string) $dia['almoco_fim'] . ':00' : null;
            $gravacao->bind_param(
                'iiissss',
                $idEstabelecimento,
                $numeroDia,
                $aberto,
                $abertura,
                $fechamento,
                $inicioAlmoco,
                $fimAlmoco
            );
            $gravacao->execute();
        }
        $gravacao->close();

        $atualizacao = $conexao->prepare(
            'UPDATE estabelecimento
             SET intervalo_minutos = ?, antecedencia_minima_minutos = ?, janela_maxima_dias = ?,
                 horas_cancelamento = ?, tolerancia_atraso_minutos = ?
             WHERE id_estabelecimento = ?'
        );
        $intervalo = (int) $regras['intervalo'];
        $antecedencia = (int) $regras['antecedencia'];
        $janela = (int) $regras['janela'];
        $cancelamento = (int) $regras['cancelamento'];
        $tolerancia = (int) $regras['tolerancia'];
        $atualizacao->bind_param(
            'iiiiii',
            $intervalo,
            $antecedencia,
            $janela,
            $cancelamento,
            $tolerancia,
            $idEstabelecimento
        );
        $atualizacao->execute();
        $atualizacao->close();

        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }
}

/**
 * Janelas de atendimento de um dia, já sem o almoço.
 * Ex.: [['inicio' => '08:00', 'fim' => '12:00'], ['inicio' => '13:00', 'fim' => '18:00']]
 * A agenda e o futuro portal do cliente usam esta mesma função.
 */
function agenday_horario_janelas(array $dia): array
{
    if (empty($dia['aberto'])) {
        return [];
    }
    $abertura = (string) ($dia['abertura'] ?? '');
    $fechamento = (string) ($dia['fechamento'] ?? '');
    if ($abertura === '' || $fechamento === ''
        || agenday_horario_minutos($abertura) >= agenday_horario_minutos($fechamento)) {
        return [];
    }

    $inicioAlmoco = (string) ($dia['almoco_inicio'] ?? '');
    $fimAlmoco = (string) ($dia['almoco_fim'] ?? '');
    $inicio = agenday_horario_minutos($abertura);
    $fim = agenday_horario_minutos($fechamento);

    if ($inicioAlmoco !== '' && $fimAlmoco !== '') {
        $inicioPausa = agenday_horario_minutos($inicioAlmoco);
        $fimPausa = agenday_horario_minutos($fimAlmoco);
        if ($inicioPausa > $inicio && $fimPausa < $fim && $inicioPausa < $fimPausa) {
            return [
                ['inicio' => $abertura, 'fim' => $inicioAlmoco],
                ['inicio' => $fimAlmoco, 'fim' => $fechamento],
            ];
        }
    }

    return [['inicio' => $abertura, 'fim' => $fechamento]];
}
