<?php
declare(strict_types=1);
require_once __DIR__ . '/../sessao.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');

function agenday_comparecimento_json(string $mensagem, int $codigo): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'erro' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['id_estabelecimento']) || !is_int($_SESSION['id_estabelecimento'])
    || $_SESSION['id_estabelecimento'] < 1) {
    agenday_comparecimento_json('É necessário entrar no sistema.', 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    agenday_comparecimento_json('Método não permitido.', 405);
}
if (!agenday_valida_csrf()) {
    agenday_comparecimento_json('A sessão do formulário expirou. Recarregue a página.', 403);
}

require_once __DIR__ . '/../lib/comparecimento.php';

$idAgendamento = filter_input(INPUT_POST, 'id_agendamento', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';

if (!is_int($idAgendamento)) {
    agenday_comparecimento_json('Informe um agendamento válido.', 422);
}

try {
    require_once __DIR__ . '/../conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
    $novoStatus = agenday_comparecimento_marcar($conexao, $idEstabelecimento, $idAgendamento, $acao);
    echo json_encode(['ok' => true, 'status' => $novoStatus], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException | DomainException $e) {
    agenday_comparecimento_json($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('AgenDay: erro ao marcar comparecimento (' . get_class($e) . ').');
    agenday_comparecimento_json('Não foi possível atualizar o atendimento agora.', 503);
}
