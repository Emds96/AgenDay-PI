<?php
/**
 * AgenDay 2.0 — cancelamento transacional de agendamentos (microtarefa 4.5).
 */
declare(strict_types=1);
require_once __DIR__ . '/agendamentos.php';

/** Valida o identificador enviado no POST (INT UNSIGNED do MySQL). */
function agenday_agenda_validar_id_cancelamento($valor): int
{
    if (!is_string($valor) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', $valor)) {
        throw new InvalidArgumentException('Informe um agendamento válido para cancelar.');
    }
    $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false || $id > 4294967295) {
        throw new InvalidArgumentException('Informe um agendamento válido para cancelar.');
    }
    return $id;
}

/**
 * Cancela um agendamento ativo da empresa autenticada.
 *
 * Primeiro descobre o funcionário sem bloquear; em seguida adquire
 * o lock do funcionário, igual ao usado na criação (4.3), e só depois
 * bloqueia e atualiza o registro. Evita ciclos de bloqueio ao cancelar
 * simultaneamente à criação para o mesmo funcionário.
 *
 * @return array{ja_cancelado:bool,data_inicio:string}
 */
function agenday_agenda_cancelar(mysqli $conexao, int $idAgendamento): array
{
    if ($idAgendamento < 1 || $idAgendamento > 4294967295) {
        throw new InvalidArgumentException('Identificador de agendamento inválido.');
    }
    $empresa = agenday_agenda_estabelecimento();

    // Consulta inicial sem lock; a linha será relida sob bloqueio na transação.
    $stmt = $conexao->prepare(
        'SELECT id_funcionario FROM agendamentos
         WHERE id_estabelecimento = ? AND id_agendamento = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $empresa, $idAgendamento);
    $stmt->execute();
    $stmt->bind_result($idFuncionario);
    $existe = $stmt->fetch();
    $stmt->close();
    if (!$existe) {
        throw new DomainException('Agendamento não encontrado neste estabelecimento.');
    }
    $funcionario = (int) $idFuncionario;

    $conexao->begin_transaction();
    try {
        // Toda alteração de horários deste funcionário deve seguir a mesma ordem de locks.
        $stmt = $conexao->prepare(
            'SELECT id_funcionario FROM funcionarios
             WHERE id_estabelecimento = ? AND id_funcionario = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $empresa, $funcionario);
        $stmt->execute();
        $stmt->store_result();
        $funcionarioValido = $stmt->num_rows === 1;
        $stmt->close();
        if (!$funcionarioValido) {
            throw new DomainException('O funcionário deste atendimento não está disponível.');
        }

        // Releitura bloqueante: o status pode ter mudado enquanto o lock era aguardado.
        $stmt = $conexao->prepare(
            'SELECT id_funcionario, status, data_inicio FROM agendamentos
             WHERE id_estabelecimento = ? AND id_agendamento = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $empresa, $idAgendamento);
        $stmt->execute();
        $stmt->bind_result($funcionarioAtual, $status, $dataInicio);
        $encontrado = $stmt->fetch();
        $stmt->close();

        if (!$encontrado || (int) $funcionarioAtual !== $funcionario) {
            throw new DomainException('O agendamento foi modificado. Atualize a agenda e tente novamente.');
        }
        if ($status === 'cancelado') {
            // Repetir o POST não modifica o registro nem causa um segundo cancelamento.
            $conexao->commit();
            return ['ja_cancelado' => true, 'data_inicio' => $dataInicio];
        }
        if ($status !== 'agendado' && $status !== 'confirmado') {
            throw new DomainException('Somente agendamentos ativos podem ser cancelados.');
        }

        // Preserva histórico. Cancelados deixam de ocupar horário na verificação de conflitos.
        $stmt = $conexao->prepare(
            "UPDATE agendamentos SET status = 'cancelado'
             WHERE id_estabelecimento = ? AND id_agendamento = ?
               AND status IN ('agendado', 'confirmado')"
        );
        $stmt->bind_param('ii', $empresa, $idAgendamento);
        $stmt->execute();
        $alterados = $stmt->affected_rows;
        $stmt->close();
        if ($alterados !== 1) {
            throw new DomainException('O agendamento foi alterado. Atualize a agenda e tente novamente.');
        }

        $conexao->commit();
        return ['ja_cancelado' => false, 'data_inicio' => $dataInicio];
    } catch (Throwable $e) {
        try {
            $conexao->rollback();
        } catch (Throwable $falhaRollback) {
            error_log('AgenDay: erro ao desfazer cancelamento.');
        }
        throw $e;
    }
}
