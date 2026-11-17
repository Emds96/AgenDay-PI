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

    if ($encontrado && is_string($hash) && password_verify($senha, $hash)) {
        // Só vincular o Google após provar posse da senha desta conta.
        // A associação pendente é efêmera e restrita ao mesmo e-mail verificado.
        $vinculo = $_SESSION['google_link_pending'] ?? null;
        unset($_SESSION['google_link_pending']);
        if (is_array($vinculo)
            && isset($vinculo['email'], $vinculo['sub'], $vinculo['created_at'])
            && is_string($vinculo['email']) && is_string($vinculo['sub'])
            && is_int($vinculo['created_at'])
            && $vinculo['created_at'] <= time()
            && time() - $vinculo['created_at'] <= 600
            && strlen($vinculo['sub']) > 0 && strlen($vinculo['sub']) <= 255
            && hash_equals(strtolower($emailBanco), strtolower($vinculo['email']))) {
            try {
                $stmtVinculo = $conexao->prepare(
                    'INSERT INTO estabelecimento_google
                     (google_sub, id_estabelecimento, email_google)
                     VALUES (?, ?, ?)'
                );
                $idVinculo = (int) $id;
                $stmtVinculo->bind_param('sis', $vinculo['sub'], $idVinculo, $vinculo['email']);
                $stmtVinculo->execute();
                $stmtVinculo->close();
                $_SESSION['google_vinculo_concluido'] = true;
            } catch (mysqli_sql_exception $e) {
                // Não bloquear a autenticação tradicional se a associação falhar.
                error_log('AgenDay vinculo Google: erro SQL ' . $e->getCode());
            }
        }
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
