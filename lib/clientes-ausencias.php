<?php
/**
 * AgenDay 2.0 — histórico de ausências do cliente e marca de taxa (FEE-01).
 *
 * O sistema NÃO cobra taxa automaticamente: não existe meio de pagamento
 * cadastrado. O que ele faz é mostrar ao salão quantas vezes o cliente faltou,
 * quantas cancelou e em quantas o salão registrou cobrança de taxa — para a
 * cobrança ser resolvida pessoalmente.
 *
 * Este arquivo não abre conexão nem imprime nada: recebe o mysqli já aberto.
 */
declare(strict_types=1);

/**
 * Marca técnica gravada em `agendamentos.observacoes` quando o salão usa a
 * ação "Cancelar e cobrar taxa".
 *
 * Fica em `observacoes` porque criar uma coluna nova exigiria mudança de banco
 * (adiada para depois da feira). A marca é removida antes de mostrar as
 * observações na agenda, então o salão nunca vê este texto.
 */
const AGENDAY_MARCA_TAXA = '[AgenDay: cobrar taxa]';

/** Texto da marca (para quem precisa montar o LIKE ou exibir em teste). */
function agenday_clientes_marca_taxa(): string
{
    return AGENDAY_MARCA_TAXA;
}

/** As observações já têm a marca de cobrança de taxa? */
function agenday_clientes_tem_marca_taxa(?string $observacoes): bool
{
    return is_string($observacoes) && $observacoes !== '' && str_contains($observacoes, AGENDAY_MARCA_TAXA);
}

/** Acrescenta a marca uma única vez, preservando a observação do cliente. */
function agenday_clientes_com_marca_taxa(?string $observacoes): string
{
    $texto = is_string($observacoes) ? rtrim($observacoes) : '';
    if ($texto !== '' && str_contains($texto, AGENDAY_MARCA_TAXA)) {
        return $texto;
    }
    return $texto === '' ? AGENDAY_MARCA_TAXA : $texto . ' ' . AGENDAY_MARCA_TAXA;
}

/** Observações sem a marca técnica, prontas para aparecer na agenda. */
function agenday_clientes_sem_marca_taxa(?string $observacoes): string
{
    if (!is_string($observacoes) || $observacoes === '') {
        return '';
    }
    return trim(str_replace(AGENDAY_MARCA_TAXA, '', $observacoes));
}

/**
 * Conta faltas, cancelamentos e cobranças de taxa dos clientes informados.
 *
 * @param mysqli $conexao conexão já aberta
 * @param int    $empresa id do estabelecimento (vem SEMPRE da sessão)
 * @param int[]  $ids     ids dos clientes da lista atual
 * @return array<int, array{faltas: int, cancelamentos: int, taxa: int}>
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
    $sql = "SELECT id_cliente,
                   SUM(status = 'faltou') AS faltas,
                   SUM(status = 'cancelado') AS cancelamentos,
                   SUM(status = 'cancelado' AND observacoes LIKE ?) AS taxa
              FROM agendamentos
             WHERE id_estabelecimento = ?
               AND status IN ('faltou', 'cancelado')
               AND id_cliente IN (" . $marcadores . ')
             GROUP BY id_cliente';
    $stmt = $conexao->prepare($sql);
    $filtroTaxa = '%' . AGENDAY_MARCA_TAXA . '%';
    $tipos = 'si' . str_repeat('i', count($ids));
    $stmt->bind_param($tipos, $filtroTaxa, $empresa, ...$ids);
    $stmt->execute();
    $stmt->bind_result($idCliente, $faltas, $cancelamentos, $taxa);

    $resultado = [];
    foreach ($ids as $id) {
        $resultado[$id] = ['faltas' => 0, 'cancelamentos' => 0, 'taxa' => 0];
    }
    while ($stmt->fetch()) {
        $id = (int) $idCliente;
        if (!isset($resultado[$id])) {
            continue;
        }
        $resultado[$id]['faltas'] = (int) $faltas;
        $resultado[$id]['cancelamentos'] = (int) $cancelamentos;
        $resultado[$id]['taxa'] = (int) $taxa;
    }
    $stmt->close();

    return $resultado;
}

/** Rótulo curto de um contador: "1 falta", "2 faltas", "1 em cima da hora". */
function agenday_clientes_ausencia_rotulo(string $tipo, int $total): string
{
    return match ($tipo) {
        'falta' => $total . ($total === 1 ? ' falta' : ' faltas'),
        'cancelamento' => $total . ($total === 1 ? ' cancelamento' : ' cancelamentos'),
        'taxa' => $total . ' em cima da hora',
        default => '',
    };
}

/** Texto curto do histórico: "2 faltas · 1 cancelamento · 1 em cima da hora". */
function agenday_clientes_ausencias_texto(int $faltas, int $cancelamentos, int $taxa = 0): string
{
    $partes = [];
    if ($faltas > 0) {
        $partes[] = agenday_clientes_ausencia_rotulo('falta', $faltas);
    }
    if ($cancelamentos > 0) {
        $partes[] = agenday_clientes_ausencia_rotulo('cancelamento', $cancelamentos);
    }
    if ($taxa > 0) {
        $partes[] = agenday_clientes_ausencia_rotulo('taxa', $taxa);
    }
    return implode(' · ', $partes);
}
