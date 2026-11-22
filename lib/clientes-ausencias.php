<?php
/**
 * AgenDay 2.0 — histórico de ausências do cliente (FEE-01).
 *
 * O sistema NÃO cobra taxa automaticamente: não existe meio de pagamento
 * cadastrado. O que ele faz é mostrar ao salão quantas vezes o cliente faltou
 * e quantas cancelou, para a cobrança ser resolvida pessoalmente.
 *
 * Este arquivo não abre conexão nem imprime nada: recebe o mysqli já aberto.
 */
declare(strict_types=1);

/**
 * Conta faltas e cancelamentos dos clientes informados.
 *
 * @param mysqli    $conexao  conexão já aberta
 * @param int       $empresa  id do estabelecimento (vem SEMPRE da sessão)
 * @param int[]     $ids      ids dos clientes da lista atual
 * @return array<int, array{faltas: int, cancelamentos: int}>
 */
function agenday_clientes_ausencias(mysqli $conexao, int $empresa, array $ids): array
{
    $ids = array_values(array_unique(array_filter(
        array_map('intval', $ids),
        static fn(int $id): bool => $id > 0
    )));
    if ($empresa < 1 || $ids === []) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id_cliente, status, COUNT(*) AS total
              FROM agendamentos
             WHERE id_estabelecimento = ?
               AND status IN ('faltou', 'cancelado')
               AND id_cliente IN (" . $marcadores . ')
             GROUP BY id_cliente, status';
    $stmt = $conexao->prepare($sql);
    $tipos = 'i' . str_repeat('i', count($ids));
    $stmt->bind_param($tipos, $empresa, ...$ids);
    $stmt->execute();
    $stmt->bind_result($idCliente, $status, $total);

    $resultado = [];
    foreach ($ids as $id) {
        $resultado[$id] = ['faltas' => 0, 'cancelamentos' => 0];
    }
    while ($stmt->fetch()) {
        $id = (int) $idCliente;
        if (!isset($resultado[$id])) {
            continue;
        }
        if ((string) $status === 'faltou') {
            $resultado[$id]['faltas'] = (int) $total;
        } elseif ((string) $status === 'cancelado') {
            $resultado[$id]['cancelamentos'] = (int) $total;
        }
    }
    $stmt->close();

    return $resultado;
}

/** Texto curto do histórico: "2 faltas · 1 cancelamento" (ou '' quando não há nada). */
function agenday_clientes_ausencias_texto(int $faltas, int $cancelamentos): string
{
    $partes = [];
    if ($faltas > 0) {
        $partes[] = $faltas . ($faltas === 1 ? ' falta' : ' faltas');
    }
    if ($cancelamentos > 0) {
        $partes[] = $cancelamentos . ($cancelamentos === 1 ? ' cancelamento' : ' cancelamentos');
    }
    return implode(' · ', $partes);
}
