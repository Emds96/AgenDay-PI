<?php
/** Salva agendamento. Somente POST autenticado com CSRF. */
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}
if (!agenday_valida_csrf()) {
    $_SESSION['erro_agendamento'] = 'O formulário expirou. Atualize a página e tente novamente.';
    header('Location: agenda.php#novo-agendamento-titulo', true, 303);
    exit;
}

try {
    require_once __DIR__ . '/conexao.php';
    require_once __DIR__ . '/lib/agendamento-gravar.php';

    $id = agenday_agenda_criar($conexao, $_POST);
    $_SESSION['sucesso_agendamento'] = 'Agendamento #' . $id . ' salvo com sucesso!';
} catch (InvalidArgumentException | DomainException $e) {
    $_SESSION['erro_agendamento'] = $e->getMessage();
} catch (Throwable $e) {
    error_log('AgenDay: erro ao salvar agendamento (' . get_class($e) . ').');
    $_SESSION['erro_agendamento'] = 'Não foi possível salvar o agendamento agora. Tente novamente.';
}

header('Location: agenda.php#novo-agendamento-titulo', true, 303);
exit;
