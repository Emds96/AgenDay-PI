<?php
/** Endpoint JSON somente-leitura: GET ?inicio=AAAA-MM-DD&fim=AAAA-MM-DD (fim exclusivo). */
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

require_once __DIR__ . '/../lib/agendamentos.php';
$inicio = $_GET['inicio'] ?? null;
$fim = $_GET['fim'] ?? null;
if (!is_string($inicio) || !is_string($fim)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => 'Informe início e fim no formato AAAA-MM-DD.']);
    exit;
}

try {
    // O arquivo privado conexao.php existe apenas na hospedagem do proprietário.
    require_once __DIR__ . '/../conexao.php';
    require_once __DIR__ . '/../lib/comparecimento.php';

    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    // Fechamento preguiçoso: em_atendimento que já passou do fim vira concluido.
    agenday_comparecimento_fechar($conexao, $idEstabelecimento);

    $agendamentos = agenday_agenda_listar($conexao, $inicio, $fim);

    // Tolerância de chegada para sugerir "faltou".
    $tolerancia = 30;
    $consulta = $conexao->prepare(
        'SELECT tolerancia_atraso_minutos FROM estabelecimento WHERE id_estabelecimento = ? LIMIT 1'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $consulta->bind_result($toleranciaBanco);
    if ($consulta->fetch() && $toleranciaBanco !== null) {
        $tolerancia = (int) $toleranciaBanco;
    }
    $consulta->close();

    $agora = agenday_comparecimento_agora();
    foreach ($agendamentos as $indice => $registro) {
        $agendamentos[$indice]['sugere_faltou'] = agenday_comparecimento_sugere_falta(
            (string) $registro['status'],
            (string) $registro['data_inicio'],
            $tolerancia,
            $agora
        );
    }

    echo json_encode(
        ['ok' => true, 'agendamentos' => $agendamentos],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (InvalidArgumentException | DomainException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('AgenDay: erro na consulta da agenda (' . get_class($e) . ').');
    http_response_code(503);
    echo json_encode(['ok' => false, 'erro' => 'Agenda indisponível no momento.']);
}
