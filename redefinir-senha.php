<?php
declare(strict_types=1);
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/lib/recuperacao-senha.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}
$token = $metodo === 'POST' ? ($_POST['token'] ?? null) : ($_GET['token'] ?? null);
$tokenValido = agenday_recuperacao_token_valido($token);
$erro = '';
$sucesso = false;

if ($metodo === 'POST') {
    if (!agenday_valida_csrf()) {
        http_response_code(403);
        $erro = 'Falha de segurança. Recarregue a página.';
    } elseif (!$tokenValido) {
        $erro = 'Este link é inválido ou expirou.';
    } else {
        $erro = agenday_recuperacao_validar_senha($_POST) ?? '';
        if ($erro === '') {
            try {
                require_once __DIR__ . '/conexao.php';
                $hashToken = agenday_recuperacao_hash($token);
                $conexao->begin_transaction();
                $stmt = $conexao->prepare(
                    'SELECT id, id_estabelecimento FROM recuperacao_senha
                     WHERE token_hash = ? AND utilizado_em IS NULL
                       AND expira_em > UTC_TIMESTAMP() LIMIT 1 FOR UPDATE'
                );
                $stmt->bind_param('s', $hashToken);
                $stmt->execute();
                $stmt->bind_result($idRecuperacao, $idEstabelecimento);
                $existe = $stmt->fetch();
                $stmt->close();

                if (!$existe) {
                    $conexao->rollback();
                    $erro = 'Este link é inválido, já foi utilizado ou expirou.';
                } else {
                    $idEstabelecimento = (int) $idEstabelecimento;
                    $novoHash = password_hash($_POST['nova_senha'], PASSWORD_DEFAULT);
                    $atualizar = $conexao->prepare(
                        'UPDATE estabelecimento SET senha_estabelecimento = ?
                         WHERE id_estabelecimento = ?'
                    );
                    $atualizar->bind_param('si', $novoHash, $idEstabelecimento);
                    $atualizar->execute();
                    $alterados = $atualizar->affected_rows;
                    $atualizar->close();
                    if ($alterados !== 1) {
                        throw new RuntimeException('Conta indisponivel.');
                    }
                    // Um reset bem-sucedido invalida todos os links anteriores da conta.
                    $invalidar = $conexao->prepare(
                        'UPDATE recuperacao_senha SET utilizado_em = UTC_TIMESTAMP()
                         WHERE id_estabelecimento = ? AND utilizado_em IS NULL'
                    );
                    $invalidar->bind_param('i', $idEstabelecimento);
                    $invalidar->execute();
                    $invalidar->close();
                    $conexao->commit();
                    unset($_SESSION['csrf_token']);
                    session_regenerate_id(true);
                    $sucesso = true;
                    $tokenValido = false;
                }
            } catch (Throwable $e) {
                if (isset($conexao) && $conexao instanceof mysqli) {
                    try { $conexao->rollback(); } catch (Throwable $ignored) {}
                }
                error_log('AgenDay: falha na redefinicao de senha.');
                $erro = 'Não foi possível redefinir a senha agora. Tente novamente.';
            }
        }
    }
} elseif ($tokenValido) {
    try {
        require_once __DIR__ . '/conexao.php';
        $hashToken = agenday_recuperacao_hash($token);
        $consulta = $conexao->prepare(
            'SELECT id FROM recuperacao_senha WHERE token_hash = ?
             AND utilizado_em IS NULL AND expira_em > UTC_TIMESTAMP() LIMIT 1'
        );
        $consulta->bind_param('s', $hashToken);
        $consulta->execute();
        $consulta->store_result();
        $tokenValido = $consulta->num_rows === 1;
        $consulta->close();
    } catch (Throwable $e) {
        $tokenValido = false;
    }
}
$csrf = agenday_token_csrf();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Redefinir senha — AgenDay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/recuperacao-senha.css">
</head>
<body class="agenday-auth agenday-recovery">
<main class="ad-recovery-layout">
    <section class="ad-recovery-content" aria-labelledby="titulo-redefinicao">
        <a class="ad-recovery-brand" href="index.php" aria-label="AgenDay — voltar para o login">
            <span class="ad-recovery-brand-mark" aria-hidden="true">A</span>
            <span>Agen<span class="ad-recovery-brand-accent">Day</span></span>
        </a>
        <p class="ad-recovery-kicker">Acesso à sua conta</p>
        <h1 id="titulo-redefinicao">Redefinir senha</h1>

        <?php if ($sucesso): ?>
            <p class="ad-recovery-description">Sua senha foi atualizada. Agora você pode acessar sua conta com a nova senha.</p>
            <div class="alert alert-success" role="status">Senha redefinida com sucesso. Você já pode entrar no AgenDay.</div>
            <a class="ad-recovery-action" href="index.php">Ir para o login</a>
        <?php elseif (!$tokenValido): ?>
            <p class="ad-recovery-description">O link de recuperação não está mais disponível. Solicite um novo para continuar.</p>
            <div class="alert alert-danger" role="alert">Link inválido, expirado ou já utilizado.</div>
            <a class="ad-recovery-action" href="esqueci-senha.php">Solicitar outro link</a>
        <?php else: ?>
            <p class="ad-recovery-description">Crie uma nova senha para o AgenDay. Use pelo menos 8 caracteres e confirme antes de salvar.</p>
            <?php if ($erro !== ''): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form class="ad-recovery-form" method="post" action="redefinir-senha.php" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                <label for="nova_senha">Nova senha (8 a 72 bytes)</label>
                <input id="nova_senha" name="nova_senha" type="password" minlength="8" maxlength="72"
                       autocomplete="new-password" required>
                <label for="confirmar_nova_senha">Confirme a nova senha</label>
                <input id="confirmar_nova_senha" name="confirmar_nova_senha" type="password" minlength="8" maxlength="72"
                       autocomplete="new-password" required>
                <button type="submit" class="ad-recovery-submit">Salvar nova senha</button>
            </form>
        <?php endif; ?>

        <a class="ad-recovery-back" href="index.php"><span aria-hidden="true">←</span> Voltar para o login</a>
    </section>

    <aside class="ad-recovery-side" aria-label="Informações sobre a segurança da sua conta">
        <div class="ad-recovery-side-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"
                 fill="none" stroke="#18544d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                <circle cx="12" cy="15.5" r="1"></circle>
            </svg>
        </div>
        <h2>Uma nova senha, a mesma segurança</h2>
        <p>Atualize sua senha com tranquilidade. O acesso ao AgenDay continua protegido.</p>
    </aside>
</main>
</body>
</html>
