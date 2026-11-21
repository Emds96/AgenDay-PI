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
if (mb_strlen($termo) > 60) {
    $termo = mb_substr($termo, 0, 60);
}

/** @return array<int,array{id:int,nome:string,contato:string}> */
function agenday_clientes_buscar(mysqli $conexao, int $idEstabelecimento, string $padrao): array
{
    $consulta = $conexao->prepare(
        'SELECT id_cliente, nome_cliente, contato_cliente
         FROM clientes
         WHERE id_estabelecimento = ?
           AND (nome_cliente LIKE ? OR contato_cliente LIKE ?)
         ORDER BY nome_cliente
         LIMIT 20'
    );
    $consulta->bind_param('iss', $idEstabelecimento, $padrao, $padrao);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $lista = [];
    while ($linha = $resultado->fetch_assoc()) {
        $lista[] = [
            'id' => (int) $linha['id_cliente'],
            'nome' => (string) $linha['nome_cliente'],
            'contato' => (string) ($linha['contato_cliente'] ?? ''),
        ];
    }
    $consulta->close();
    return $lista;
}

try {
    require_once __DIR__ . '/../conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    if ($termo === '') {
        // Ao clicar no campo: primeiros cadastrados.
        $clientes = agenday_clientes_buscar($conexao, $idEstabelecimento, '%');
    } else {
        // Prioriza quem começa com o texto (Ar → Ariel, Arnaldo, Arlando).
        $clientes = agenday_clientes_buscar($conexao, $idEstabelecimento, $termo . '%');
        if ($clientes === []) {
            // Sem começo correspondente: procura em qualquer parte do nome/telefone.
            $clientes = agenday_clientes_buscar($conexao, $idEstabelecimento, '%' . $termo . '%');
        }
    }

    echo json_encode(['ok' => true, 'clientes' => $clientes], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('AgenDay: erro na busca de clientes (' . get_class($e) . ').');
    http_response_code(503);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível buscar clientes agora.']);
}
