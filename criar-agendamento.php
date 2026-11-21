<?php
declare(strict_types=1);
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

function agenday_agendar_voltar(string $tipo, string $mensagem): void
{
    $_SESSION[$tipo] = $mensagem;
    header('Location: agendar.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_agendar_voltar('erro_agendar', 'A sessão do formulário expirou. Recarregue e tente novamente.');
}

$idCliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idServico = filter_input(INPUT_POST, 'id_servicos', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idFuncionarioBruto = $_POST['id_funcionario'] ?? '';
$idFuncionario = ($idFuncionarioBruto === '' || $idFuncionarioBruto === '0')
    ? null
    : filter_var($idFuncionarioBruto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

$modo = isset($_POST['modo']) && is_string($_POST['modo']) ? $_POST['modo'] : 'unico';
if (!in_array($modo, ['unico', 'pacote', 'retorno'], true)) {
    $modo = 'unico';
}

require_once __DIR__ . '/lib/disponibilidade.php';

$sessoesEntrada = isset($_POST['sessoes']) && is_array($_POST['sessoes']) ? $_POST['sessoes'] : [];
$sessoes = [];
foreach ($sessoesEntrada as $indice => $item) {
    if (!is_array($item)) {
        continue;
    }
    $dia = agenday_disp_data_valida($item['data'] ?? null);
    $hora = agenday_disp_hora_valida($item['hora'] ?? null);
    if ($dia === null || $hora === null) {
        agenday_agendar_voltar('erro_agendar', 'Escolha data e horário para todas as sessões.');
    }
    $sessoes[] = ['data' => $dia, 'hora' => $hora];
}

if ($sessoes === []) {
    agenday_agendar_voltar('erro_agendar', 'Escolha cliente, serviço, data e horário.');
}
if (!is_int($idCliente) || !is_int($idServico) || $idFuncionario === false) {
    agenday_agendar_voltar('erro_agendar', 'Escolha cliente, serviço, data e horário.');
}
if ($modo === 'unico' && count($sessoes) !== 1) {
    agenday_agendar_voltar('erro_agendar', 'Um agendamento único precisa de um horário só.');
}
if (count($sessoes) > 12) {
    agenday_agendar_voltar('erro_agendar', 'O limite é de 12 sessões por agendamento.');
}

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    // Cliente da conta.
    $consulta = $conexao->prepare(
        'SELECT id_cliente FROM clientes WHERE id_estabelecimento = ? AND id_cliente = ? LIMIT 1'
    );
    $consulta->bind_param('ii', $idEstabelecimento, $idCliente);
    $consulta->execute();
    $consulta->store_result();
    $clienteExiste = $consulta->num_rows === 1;
    $consulta->close();
    if (!$clienteExiste) {
        agenday_agendar_voltar('erro_agendar', 'Cliente não encontrado neste estabelecimento.');
    }

    // Valida cada sessão e escolhe o profissional.
    $plano = [];
    foreach ($sessoes as $indice => $sessao) {
        $rotulo = count($sessoes) > 1 ? 'Sessão ' . ($indice + 1) . ': ' : '';
        $disponibilidade = agenday_disponibilidade_dia(
            $conexao,
            $idEstabelecimento,
            $sessao['data'],
            $idServico,
            $idFuncionario
        );
        if ($disponibilidade['servico'] === null || $disponibilidade['motivo'] !== null) {
            agenday_agendar_voltar(
                'erro_agendar',
                $rotulo . ($disponibilidade['motivo'] ?? 'serviço não encontrado.')
            );
        }
        $escolhido = null;
        foreach ($disponibilidade['slots'] as $slot) {
            if ($slot['hora'] === $sessao['hora'] && $slot['livre']) {
                $escolhido = (int) $slot['profissionais'][0]['id'];
                break;
            }
        }
        if ($escolhido === null) {
            agenday_agendar_voltar('erro_agendar', $rotulo . 'este horário não está mais livre. Escolha outro.');
        }
        $plano[] = [
            'data' => $sessao['data'],
            'hora' => $sessao['hora'],
            'funcionario' => $escolhido,
            'duracao' => (int) $disponibilidade['servico']['duracao'],
        ];
    }

    // Grava tudo junto: se uma sessão falhar, nenhuma entra.
    $conexao->begin_transaction();
    try {
        $funcionarios = array_values(array_unique(array_column($plano, 'funcionario')));
        sort($funcionarios);
        foreach ($funcionarios as $idFunc) {
            $lock = $conexao->prepare(
                'SELECT id_funcionario FROM funcionarios
                 WHERE id_estabelecimento = ? AND id_funcionario = ? FOR UPDATE'
            );
            $lock->bind_param('ii', $idEstabelecimento, $idFunc);
            $lock->execute();
            $lock->store_result();
            $existe = $lock->num_rows === 1;
            $lock->close();
            if (!$existe) {
                throw new DomainException('Um dos profissionais não pertence a este estabelecimento.');
            }
        }

        $insercao = $conexao->prepare(
            "INSERT INTO agendamentos
             (id_estabelecimento, id_cliente, id_funcionario, id_servicos,
              data_inicio, data_fim, status, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, 'agendado', NULL)"
        );

        $ids = [];
        foreach ($plano as $indice => $item) {
            $rotulo = count($plano) > 1 ? 'Sessão ' . ($indice + 1) . ': ' : '';
            $inicio = DateTimeImmutable::createFromFormat(
                '!Y-m-d H:i',
                $item['data'] . ' ' . $item['hora'],
                agenday_disp_fuso()
            );
            if ($inicio === false) {
                throw new DomainException($rotulo . 'data inválida.');
            }
            $fim = $inicio->modify('+' . $item['duracao'] . ' minutes');
            $inicioBanco = $inicio->format('Y-m-d H:i:s');
            $fimBanco = $fim->format('Y-m-d H:i:s');

            $conflito = $conexao->prepare(
                "SELECT id_agendamento FROM agendamentos
                 WHERE id_estabelecimento = ? AND id_funcionario = ?
                   AND status IN ('agendado', 'confirmado')
                   AND data_inicio < ? AND data_fim > ?
                 LIMIT 1 FOR UPDATE"
            );
            $conflito->bind_param('iiss', $idEstabelecimento, $item['funcionario'], $fimBanco, $inicioBanco);
            $conflito->execute();
            $conflito->store_result();
            $emConflito = $conflito->num_rows > 0;
            $conflito->close();
            if ($emConflito) {
                throw new DomainException($rotulo . 'este horário acabou de ser ocupado. Escolha outro.');
            }

            $insercao->bind_param(
                'iiiiss',
                $idEstabelecimento,
                $idCliente,
                $item['funcionario'],
                $idServico,
                $inicioBanco,
                $fimBanco
            );
            $insercao->execute();
            $ids[] = (int) $conexao->insert_id;
        }
        $insercao->close();
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }

    $_SESSION['sucesso_agendar'] = count($ids) === 1
        ? 'Agendamento #' . $ids[0] . ' salvo com sucesso.'
        : count($ids) . ' sessões salvas com sucesso.';
    $_SESSION['agenda_dia_recem_salvo'] = $plano[0]['data'];
    header('Location: agendar.php?salvo=1', true, 303);
    exit;
} catch (DomainException $e) {
    agenday_agendar_voltar('erro_agendar', $e->getMessage());
} catch (Throwable $e) {
    error_log('AgenDay: erro ao criar agendamento (' . get_class($e) . ').');
    agenday_agendar_voltar('erro_agendar', 'Não foi possível salvar o agendamento agora. Tente novamente.');
}
