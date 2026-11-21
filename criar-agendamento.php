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
$observacoes = isset($_POST['observacoes']) && is_string($_POST['observacoes'])
    ? trim($_POST['observacoes']) : '';

require_once __DIR__ . '/lib/disponibilidade.php';

$dia = agenday_disp_data_valida($_POST['data'] ?? null);
$hora = agenday_disp_hora_valida($_POST['hora'] ?? null);

if (!is_int($idCliente) || !is_int($idServico) || $idFuncionario === false
    || $dia === null || $hora === null) {
    agenday_agendar_voltar('erro_agendar', 'Escolha cliente, serviço, data e horário.');
}
if (mb_strlen($observacoes) > 1000) {
    agenday_agendar_voltar('erro_agendar', 'As observações podem ter no máximo 1.000 caracteres.');
}

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    // O cliente precisa pertencer à conta.
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

    // Revalida a disponibilidade inteira no servidor, no momento de salvar.
    $disponibilidade = agenday_disponibilidade_dia(
        $conexao,
        $idEstabelecimento,
        $dia,
        $idServico,
        $idFuncionario
    );
    if ($disponibilidade['servico'] === null) {
        agenday_agendar_voltar('erro_agendar', $disponibilidade['motivo'] ?? 'Serviço não encontrado.');
    }
    if ($disponibilidade['motivo'] !== null) {
        agenday_agendar_voltar('erro_agendar', $disponibilidade['motivo']);
    }

    $escolhido = null;
    foreach ($disponibilidade['slots'] as $slot) {
        if ($slot['hora'] === $hora && $slot['livre']) {
            $escolhido = $slot['profissionais'][0]['id'];
            break;
        }
    }
    if ($escolhido === null) {
        agenday_agendar_voltar('erro_agendar', 'Este horário não está mais livre. Escolha outro.');
    }

    $duracao = (int) $disponibilidade['servico']['duracao'];
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dia . ' ' . $hora, agenday_disp_fuso());
    $fim = $inicio->modify('+' . $duracao . ' minutes');
    $inicioBanco = $inicio->format('Y-m-d H:i:s');
    $fimBanco = $fim->format('Y-m-d H:i:s');

    // Gravação com lock por funcionário, igual ao fluxo antigo.
    $conexao->begin_transaction();
    try {
        $lock = $conexao->prepare(
            'SELECT id_funcionario FROM funcionarios
             WHERE id_estabelecimento = ? AND id_funcionario = ? FOR UPDATE'
        );
        $lock->bind_param('ii', $idEstabelecimento, $escolhido);
        $lock->execute();
        $lock->store_result();
        $funcionarioExiste = $lock->num_rows === 1;
        $lock->close();
        if (!$funcionarioExiste) {
            throw new DomainException('O profissional escolhido não pertence a este estabelecimento.');
        }

        $conflito = $conexao->prepare(
            "SELECT id_agendamento FROM agendamentos
             WHERE id_estabelecimento = ? AND id_funcionario = ?
               AND status IN ('agendado', 'confirmado')
               AND data_inicio < ? AND data_fim > ?
             LIMIT 1 FOR UPDATE"
        );
        $conflito->bind_param('iiss', $idEstabelecimento, $escolhido, $fimBanco, $inicioBanco);
        $conflito->execute();
        $conflito->store_result();
        $emConflito = $conflito->num_rows > 0;
        $conflito->close();
        if ($emConflito) {
            throw new DomainException('Este horário acabou de ser ocupado. Escolha outro.');
        }

        $insercao = $conexao->prepare(
            "INSERT INTO agendamentos
             (id_estabelecimento, id_cliente, id_funcionario, id_servicos,
              data_inicio, data_fim, status, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, 'agendado', ?)"
        );
        $observacoesBanco = $observacoes === '' ? null : $observacoes;
        $insercao->bind_param(
            'iiiisss',
            $idEstabelecimento,
            $idCliente,
            $escolhido,
            $idServico,
            $inicioBanco,
            $fimBanco,
            $observacoesBanco
        );
        $insercao->execute();
        $idNovo = (int) $conexao->insert_id;
        $insercao->close();
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }

    $_SESSION['sucesso_agendar'] = 'Agendamento #' . $idNovo . ' salvo com sucesso.';
    $_SESSION['agenda_dia_recem_salvo'] = $dia;
    header('Location: agendar.php?salvo=1', true, 303);
    exit;
} catch (DomainException $e) {
    agenday_agendar_voltar('erro_agendar', $e->getMessage());
} catch (Throwable $e) {
    error_log('AgenDay: erro ao criar agendamento (' . get_class($e) . ').');
    agenday_agendar_voltar('erro_agendar', 'Não foi possível salvar o agendamento agora. Tente novamente.');
}
