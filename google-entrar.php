<?php
declare(strict_types=1);

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/lib/google-identidade.php';

function agenday_google_erro_login(string $mensagem): never
{
    $_SESSION['erro_google_login'] = $mensagem;
    header('Location: index.php', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php', true, 302);
    exit;
}

$config = agenday_google_config();
$clientId = $config['client_id'] ?? '';
$nonce = $_SESSION['google_nonce'] ?? null;
$cookie = $_COOKIE['g_csrf_token'] ?? null;
$postCsrf = $_POST['g_csrf_token'] ?? null;
$jwt = $_POST['credential'] ?? null;

if ($clientId === '' || !is_string($nonce) || !is_string($cookie) || $cookie === ''
    || strlen($cookie) > 1024 || !is_string($postCsrf)
    || !hash_equals($cookie, $postCsrf)
    || !is_string($jwt) || $jwt === '' || strlen($jwt) > 10000) {
    agenday_google_erro_login('Não foi possível validar o acesso com Google. Tente novamente.');
}

$certificados = agenday_google_certificados();
$identidade = $certificados === null
    ? null
    : agenday_google_validar_token($jwt, $clientId, $nonce, $certificados);

// Cada sessão deve ter um novo nonce após uma tentativa válida ou inválida.
$_SESSION['google_nonce'] = bin2hex(random_bytes(24));
if ($identidade === null) {
    agenday_google_erro_login('Autenticação Google não validada. Tente novamente.');
}

try {
    require_once __DIR__ . '/conexao.php';
    // Identidade estável: sempre procurar pelo sub, nunca vincular por e-mail.
    $stmt = $conexao->prepare(
        'SELECT e.id_estabelecimento, e.nome_estabelecimento, e.email_estabelecimento
         FROM estabelecimento_google g
         INNER JOIN estabelecimento e ON e.id_estabelecimento = g.id_estabelecimento
         WHERE g.google_sub = ? LIMIT 1'
    );
    $stmt->bind_param('s', $identidade['sub']);
    $stmt->execute();
    $stmt->bind_result($id, $nome, $emailBanco);
    $encontrado = $stmt->fetch();
    $stmt->close();

    if ($encontrado) {
        session_regenerate_id(true);
        $_SESSION['id_estabelecimento'] = (int) $id;
        $_SESSION['nome_estabelecimento'] = $nome;
        $_SESSION['email_estabelecimento'] = $emailBanco;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        unset($_SESSION['google_pending'], $_SESSION['erro_google_login']);
        header('Location: agenda.php', true, 303);
        exit;
    }

    // Se já há uma conta local com esse e-mail, NÃO criar duplicata ou vinculá-la.
    $stmt = $conexao->prepare(
        'SELECT id_estabelecimento FROM estabelecimento WHERE email_estabelecimento = ? LIMIT 1'
    );
    $stmt->bind_param('s', $identidade['email']);
    $stmt->execute();
    $stmt->store_result();
    $emailJaUsado = $stmt->num_rows > 0;
    $stmt->close();

    if ($emailJaUsado) {
        // Vinculação somente depois de uma autenticação local por senha.
        // O login pelo Google isolado NÃO concede acesso à conta existente.
        session_regenerate_id(true);
        $_SESSION['google_link_pending'] = [
            'sub' => $identidade['sub'],
            'email' => $identidade['email'],
            'created_at' => time(),
        ];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        agenday_google_erro_login(
            'Este e-mail já possui cadastro. Entre com sua senha do AgenDay para vincular o Google com segurança.'
        );
    }

    session_regenerate_id(true);
    $_SESSION['google_pending'] = [
        'sub' => $identidade['sub'],
        'email' => $identidade['email'],
        'created_at' => time(),
    ];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['erro_google_login']);
    header('Location: index.php', true, 303);
    exit;
} catch (mysqli_sql_exception $e) {
    error_log('AgenDay Google auth: erro SQL ' . $e->getCode());
    agenday_google_erro_login('Não foi possível entrar com Google agora. Tente novamente mais tarde.');
}
