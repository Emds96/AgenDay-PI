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

require_once __DIR__ . '/../lib/disponibilidade.php';

$dia = agenday_disp_data_valida($_GET['data'] ?? null);
$idServico = filter_var($_GET['servico'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$idFuncionario = $_GET['funcionario'] ?? '';
$idFuncionario = ($idFuncionario === '' || $idFuncionario === '0' || $idFuncionario === null)
    ? null
    : filter_var($idFuncionario, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($dia === null || !is_int($idServico) || $idFuncionario === false) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Informe data, serviço e profissional válidos.']);
    exit;
}

try {
    require_once __DIR__ . '/../conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
    $disponibilidade = agenday_disponibilidade_dia(
        $conexao,
        $idEstabelecimento,
        $dia,
        $idServico,
        $idFuncionario
    );
    echo json_encode(
        ['ok' => true] + $disponibilidade,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (DomainException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('AgenDay: erro ao calcular horários (' . get_class($e) . ').');
    http_response_code(503);
    echo json_encode(['ok' => false, 'erro' => 'Não foi possível calcular os horários agora.']);
}
