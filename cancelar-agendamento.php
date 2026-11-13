<?php
/** Cancela agendamento ativo via POST autenticado e CSRF. */
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}
if (!agenday_valida_csrf()) {
    $_SESSION['erro_cancelamento'] = 'A sessão do formulário expirou. Atualize a agenda e tente novamente.';
    header('Location: agenda.php#agenda-calendario-titulo', true, 303);
    exit;
}

try {
    require_once __DIR__ . '/conexao.php';
    require_once __DIR__ . '/lib/agendamento-cancelar.php';
    $id = agenday_agenda_validar_id_cancelamento($_POST['id_agendamento'] ?? null);
    $resultado = agenday_agenda_cancelar($conexao, $id);

    $_SESSION['sucesso_cancelamento'] = $resultado['ja_cancelado']
        ? 'Este agendamento já estava cancelado.'
        : 'Agendamento #' . $id . ' cancelado com sucesso. O horário foi liberado.';

    // Reabre o mês do atendimento após o redirecionamento.
    $_SESSION['agenda_dia_recem_salvo'] = substr($resultado['data_inicio'], 0, 10);
} catch (InvalidArgumentException | DomainException $e) {
    $_SESSION['erro_cancelamento'] = $e->getMessage();
} catch (Throwable $e) {
    error_log('AgenDay: erro ao cancelar agendamento (' . get_class($e) . ').');
    $_SESSION['erro_cancelamento'] = 'Não foi possível cancelar agora. Tente novamente.';
}

header('Location: agenda.php#agenda-calendario-titulo', true, 303);
exit;
