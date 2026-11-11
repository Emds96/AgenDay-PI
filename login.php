<?php
require_once __DIR__ . '/sessao.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php', true, 302);
    exit;
}

$email = $_POST['email'] ?? null;
$senha = $_POST['senha'] ?? null;
if (!agenday_valida_csrf() || !is_string($email) || !is_string($senha)
    || !filter_var(trim($email), FILTER_VALIDATE_EMAIL) || $senha === '') {
    $_SESSION['nao_autenticado'] = true;
    header('Location: index.php', true, 302);
    exit;
}

$email = trim($email);

try {
    require_once __DIR__ . '/conexao.php';
    $stmt = $conexao->prepare(
        'SELECT id_estabelecimento, nome_estabelecimento, email_estabelecimento, senha_estabelecimento
         FROM estabelecimento WHERE email_estabelecimento = ? LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($id, $nome, $emailBanco, $hash);
    $encontrado = $stmt->fetch();
    $stmt->close();

    if ($encontrado && password_verify($senha, $hash)) {
        session_regenerate_id(true);
        $_SESSION['id_estabelecimento'] = (int) $id;
        $_SESSION['nome_estabelecimento'] = $nome;
        $_SESSION['email_estabelecimento'] = $emailBanco;
        unset($_SESSION['nao_autenticado']);
        header('Location: agenda.php', true, 302);
        exit;
    }
} catch (mysqli_sql_exception $e) {
    error_log('AgenDay login: erro SQL ' . $e->getCode());
}

$_SESSION['nao_autenticado'] = true;
header('Location: index.php', true, 302);
exit;
