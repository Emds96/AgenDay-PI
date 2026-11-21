<?php
declare(strict_types=1);
require_once __DIR__ . '/../sessao.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');

if (!isset($_SESSION['id_estabelecimento']) || !is_int($_SESSION['id_estabelecimento'])
    || $_SESSION['id_estabelecimento'] < 1) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'erro' => 'É necessário entrar no sistema.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    echo json_encode(['ok' => false, 'erro' => 'Método não permitido.']);
    exit;
}

$termo = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
if (mb_strlen($termo) < 2 || mb_strlen($termo) > 60) {
    echo json_encode(['ok' => true, 'clientes' => []]);
    exit;
}

try {
    require_once __DIR__ . '/../conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
    $busca = '%' . $termo . '%';
    $consulta = $conexao->prepare(
        'SELECT id_cliente, nome_cliente, contato_cliente
         FROM clientes
         WHERE id_estabelecimento = ?
           AND (nome_cliente LIKE ? OR contato_cliente LIKE ?)
         ORDER BY nome_cliente
         LIMIT 8'
    );
    $consulta->bind_param('iss', $idEstabelecimento, $busca, $busca);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $clientes = [];
    while ($linha = $resultado->fetch_assoc()) {
        $clientes[] = [
            'id' => (int) $linha['id_cliente'],
            'nome' => (string) $linha['nome_cliente'],
            'contato' => (string) ($linha['contato_cliente'] ?? ''),
        ];
    }
    $consulta->close();
    echo json_encode(['ok' => true, 'clientes' => $clientes], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('AgenDay: erro na busca de clientes (' . get_class($e) . ').');
    http_response_code(503);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível buscar clientes agora.']);
}
