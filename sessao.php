<?php
declare(strict_types=1);

// No domínio do cliente só o portal responde; rotas administrativas são
// redirecionadas. Roda antes de session_start() e é inerte em outros hosts.
require_once __DIR__ . '/lib/guarda-host.php';
agenday_guarda_host_aplicar();

// Inicialização centralizada para login, cadastro e páginas protegidas.
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_name('AGENDAYSESSID');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function agenday_token_csrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function agenday_valida_csrf(): bool
{
    return isset($_SESSION['csrf_token'], $_POST['csrf_token'])
        && is_string($_POST['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}
