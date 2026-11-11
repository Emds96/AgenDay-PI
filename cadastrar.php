<?php
require_once __DIR__ . '/sessao.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php', true, 302);
    exit;
}

$nome = $_POST['estabelecimento'] ?? null;
$email = $_POST['email'] ?? null;
$senha = $_POST['senha'] ?? null;
$confirmacao = $_POST['confirmar_senha'] ?? null;

if (!agenday_valida_csrf() || !is_string($nome) || !is_string($email)
    || !is_string($senha) || !is_string($confirmacao)) {
    $_SESSION['erro_cadastro'] = 'Não foi possível validar o formulário.';
    header('Location: index.php', true, 302);
    exit;
}

$nome = trim($nome);
$email = trim($email);
if ($nome === '' || mb_strlen($nome) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 190 || strlen($senha) < 8 || strlen($senha) > 72
    || !hash_equals($senha, $confirmacao)) {
    $_SESSION['erro_cadastro'] = 'Confira o nome, e-mail e confirmação da senha (8 a 72 bytes).';
    header('Location: index.php', true, 302);
    exit;
}

try {
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare('SELECT id_estabelecimento FROM estabelecimento WHERE email_estabelecimento = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    if ($existe) {
        $_SESSION['usuario_existe'] = true;
        header('Location: index.php', true, 302);
        exit;
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare(
        'INSERT INTO estabelecimento (nome_estabelecimento, email_estabelecimento, senha_estabelecimento)
         VALUES (?, ?, ?)'
    );
    $stmt->bind_param('sss', $nome, $email, $hash);
    $stmt->execute();
    $stmt->close();
    $_SESSION['status_cadastro'] = true;
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        $_SESSION['usuario_existe'] = true;
    } else {
        error_log('AgenDay cadastro: erro SQL ' . $e->getCode());
        $_SESSION['erro_cadastro'] = 'Não foi possível cadastrar agora. Tente novamente.';
    }
}

header('Location: index.php', true, 302);
exit;
