<?php
declare(strict_types=1);

/**
 * Comparecimento do cliente: chegou, faltou e fechamento automático.
 *
 * Nunca recebe id_estabelecimento do navegador; o chamador passa o id da sessão.
 * O "fechamento" é calculado na leitura (sem cron), porque o plano gratuito do
 * InfinityFree não tem agendador confiável.
 */

function agenday_comparecimento_agora(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(
        defined('AGENDAY_TIMEZONE') ? AGENDAY_TIMEZONE : 'America/Manaus'
    ));
}

/** Conclui automaticamente quem estava em atendimento e já passou do fim. */
function agenday_comparecimento_fechar(mysqli $conexao, int $idEstabelecimento): int
{
    $agora = agenday_comparecimento_agora()->format('Y-m-d H:i:s');
    $stmt = $conexao->prepare(
        "UPDATE agendamentos
            SET status = 'concluido'
          WHERE id_estabelecimento = ?
            AND status = 'em_atendimento'
            AND data_fim <= ?"
    );
    $stmt->bind_param('is', $idEstabelecimento, $agora);
    $stmt->execute();
    $alterados = $stmt->affected_rows;
    $stmt->close();
    return $alterados;
}

/**
 * @param string $acao chegou|faltou|concluir
 * @return string novo status
 */
function agenday_comparecimento_marcar(
    mysqli $conexao,
    int $idEstabelecimento,
    int $idAgendamento,
    string $acao
): string {
    $regras = [
        'chegou' => ['de' => ['agendado', 'confirmado'], 'para' => 'em_atendimento'],
        'faltou' => ['de' => ['agendado', 'confirmado'], 'para' => 'faltou'],
        'concluir' => ['de' => ['em_atendimento'], 'para' => 'concluido'],
    ];
    if (!isset($regras[$acao])) {
        throw new InvalidArgumentException('Ação inválida.');
    }
    $para = $regras[$acao]['para'];
    $de = $regras[$acao]['de'];

    $consulta = $conexao->prepare(
        'SELECT status FROM agendamentos WHERE id_estabelecimento = ? AND id_agendamento = ? LIMIT 1'
    );
    $consulta->bind_param('ii', $idEstabelecimento, $idAgendamento);
    $consulta->execute();
    $linha = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    if (!$linha) {
        throw new DomainException('Agendamento não encontrado neste estabelecimento.');
    }
    $statusAtual = (string) $linha['status'];
    if ($statusAtual === $para) {
        return $para; // repetir a ação não altera nada
    }
    if (!in_array($statusAtual, $de, true)) {
        throw new DomainException('Este atendimento não está em um status que permita essa ação.');
    }

    $marcadores = implode(',', array_fill(0, count($de), '?'));
    $sql = "UPDATE agendamentos SET status = ?
             WHERE id_estabelecimento = ? AND id_agendamento = ?
               AND status IN ($marcadores)";
    $stmt = $conexao->prepare($sql);
    $tipos = 'sii' . str_repeat('s', count($de));
    $valores = array_merge([$para, $idEstabelecimento, $idAgendamento], $de);
    $stmt->bind_param($tipos, ...$valores);
    $stmt->execute();
    $alterados = $stmt->affected_rows;
    $stmt->close();

    if ($alterados !== 1) {
        throw new DomainException('O atendimento foi alterado. Atualize a agenda e tente novamente.');
    }
    return $para;
}

/**
 * O atendimento já passou do início + tolerância e continua sem marcação?
 */
function agenday_comparecimento_sugere_falta(
    string $status,
    string $dataInicio,
    int $toleranciaMinutos,
    DateTimeImmutable $agora
): bool {
    if ($status !== 'agendado' && $status !== 'confirmado') {
        return false;
    }
    $inicio = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dataInicio);
    if ($inicio === false) {
        return false;
    }
    $limite = $inicio->modify('+' . max(0, $toleranciaMinutos) . ' minutes');
    return $agora >= $limite;
}
