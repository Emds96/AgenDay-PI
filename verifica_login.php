<?php
require_once __DIR__ . '/sessao.php';

// Sempre validar o ID do estabelecimento, nunca o e-mail enviado pelo cliente.
if (!isset($_SESSION['id_estabelecimento'])
    || !is_int($_SESSION['id_estabelecimento'])
    || $_SESSION['id_estabelecimento'] < 1) {
    header('Location: index.php', true, 302);
    exit;
}

/*
 * A sessão pode sobreviver à exclusão da conta (ou à troca do banco).
 * Sem esta conferência, as telas continuam abrindo para um estabelecimento
 * que não existe mais e só as gravações falham, com mensagem genérica
 * (por exemplo, chave estrangeira ao cadastrar serviço). Aqui a sessão é
 * encerrada do mesmo jeito que no logout.
 */
require_once __DIR__ . '/conexao.php';

$agendayContaDaSessaoAtiva = true;
if (isset($conexao) && $conexao instanceof mysqli) {
    try {
        $stmtContaDaSessao = $conexao->prepare(
            'SELECT 1 FROM estabelecimento WHERE id_estabelecimento = ? LIMIT 1'
        );
        $stmtContaDaSessao->bind_param('i', $_SESSION['id_estabelecimento']);
        $stmtContaDaSessao->execute();
        $agendayContaDaSessaoAtiva = $stmtContaDaSessao->get_result()->fetch_row() !== null;
        $stmtContaDaSessao->close();
    } catch (Throwable $e) {
        // Banco indisponível não é motivo para deslogar quem está trabalhando.
        error_log('AgenDay: não foi possível conferir a conta da sessão (' . get_class($e) . ').');
    }
}

if (!$agendayContaDaSessaoAtiva) {
    error_log('AgenDay: sessão encerrada porque a conta '
        . (int) $_SESSION['id_estabelecimento'] . ' não existe mais.');

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $paramsSessao = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $paramsSessao['path'],
            'domain' => $paramsSessao['domain'],
            'secure' => $paramsSessao['secure'],
            'httponly' => $paramsSessao['httponly'],
            'samesite' => $paramsSessao['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
    header('Location: index.php', true, 302);
    exit;
}

// Impede exibir telas antigas via cache depois de sair.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
