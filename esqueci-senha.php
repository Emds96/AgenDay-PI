<?php
declare(strict_types=1);
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/lib/recuperacao-senha.php';

header('Cache-Control: no-store, private');
header('Referrer-Policy: no-referrer');

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$mensagem = '';
$erro = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!agenday_valida_csrf()) {
        http_response_code(403);
        $erro = 'Não foi possível validar o formulário. Atualize a página.';
    } else {
        $email = $_POST['email'] ?? null;
        if (!is_string($email) || strlen($email) > 190
            || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } else {
            // Resposta idêntica para conta existente ou inexistente.
            $mensagem = 'Se o e-mail estiver cadastrado, enviaremos um link de recuperação. O recebimento pode levar alguns minutos; confira também o spam ou lixo eletrônico.';
            // Uma requisição por sessão a cada 60 s; o banco limita por conta a 15 min.
            $agora = time();
            $ultima = $_SESSION['recuperacao_ultima_solicitacao'] ?? 0;
            if (!is_int($ultima) || $agora - $ultima >= 60) {
                $_SESSION['recuperacao_ultima_solicitacao'] = $agora;
                try {
                    require_once __DIR__ . '/conexao.php';
                    require_once __DIR__ . '/lib/brevo-email.php';
                    $email = trim($email);
                    $consulta = $conexao->prepare(
                        'SELECT id_estabelecimento FROM estabelecimento
                         WHERE email_estabelecimento = ? LIMIT 1'
                    );
                    $consulta->bind_param('s', $email);
                    $consulta->execute();
                    $consulta->bind_result($idEstabelecimento);
                    $existe = $consulta->fetch();
                    $consulta->close();

                    if ($existe) {
                        $idEstabelecimento = (int) $idEstabelecimento;
                        $conexao->begin_transaction();
                        // Serializar pedidos da mesma conta para evitar corridas.
                        $trava = $conexao->prepare(
                            'SELECT id_estabelecimento FROM estabelecimento
                             WHERE id_estabelecimento = ? FOR UPDATE'
                        );
                        $trava->bind_param('i', $idEstabelecimento);
                        $trava->execute();
                        $trava->close();

                        $limite = $conexao->prepare(
                            'SELECT COUNT(*) FROM recuperacao_senha
                             WHERE id_estabelecimento = ?
                               AND criado_em > (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
                        );
                        $limite->bind_param('i', $idEstabelecimento);
                        $limite->execute();
                        $limite->bind_result($recentes);
                        $limite->fetch();
                        $limite->close();

                        if ((int) $recentes === 0) {
                            $token = agenday_recuperacao_novo_token();
                            $hash = agenday_recuperacao_hash($token);
                            $ins = $conexao->prepare(
                                'INSERT INTO recuperacao_senha
                                 (id_estabelecimento, token_hash, criado_em, expira_em)
                                 VALUES (?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP() + INTERVAL 30 MINUTE)'
                            );
                            $ins->bind_param('is', $idEstabelecimento, $hash);
                            $ins->execute();
                            $novoId = (int) $conexao->insert_id;
                            $ins->close();
                            $conexao->commit();

                            $enviado = agenday_brevo_enviar_recuperacao(
                                $email, agenday_recuperacao_url($token)
                            );
                            if (!$enviado) {
                                // Não permitir uso de um token cujo envio falhou.
                                $invalidar = $conexao->prepare(
                                    'UPDATE recuperacao_senha SET utilizado_em = UTC_TIMESTAMP()
                                     WHERE id = ? AND utilizado_em IS NULL'
                                );
                                $invalidar->bind_param('i', $novoId);
                                $invalidar->execute();
                                $invalidar->close();
                            }
                            unset($token, $hash);
                        } else {
                            $conexao->commit();
                        }
                    }
                } catch (Throwable $e) {
                    if (isset($conexao) && $conexao instanceof mysqli) {
                        try { $conexao->rollback(); } catch (Throwable $ignored) {}
                    }
                    error_log('AgenDay: erro durante pedido de recuperacao de senha.');
                }
            }
        }
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
    <title>Recuperar senha — AgenDay</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/recuperacao-senha.css">
</head>
<body class="agenday-auth agenday-recovery">
<main class="ad-recovery-layout">
    <section class="ad-recovery-content" aria-labelledby="titulo-recuperacao">
        <a class="ad-recovery-brand" href="index.php" aria-label="AgenDay — voltar para o login">
            <span class="ad-recovery-brand-mark" aria-hidden="true">A</span>
            <span>Agen<span class="ad-recovery-brand-accent">Day</span></span>
        </a>
        <p class="ad-recovery-kicker">Acesso à sua conta</p>
        <h1 id="titulo-recuperacao">Esqueceu a senha?</h1>
        <p class="ad-recovery-description">Informe o e-mail cadastrado no seu estabelecimento. Enviaremos um link para você criar uma nova senha, válido por 30 minutos.</p>

        <?php if ($mensagem !== ''): ?>
            <div class="alert alert-success" role="status"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form class="ad-recovery-form" action="esqueci-senha.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <label for="email">E-mail do estabelecimento</label>
            <input type="email" id="email" name="email" maxlength="190" placeholder="seuemail@exemplo.com"
                   autocomplete="email" inputmode="email" required>
            <button type="submit" class="ad-recovery-submit">Enviar link de recuperação</button>
        </form>

        <div class="ad-recovery-notice" role="note">
            <span class="ad-recovery-notice-icon" aria-hidden="true">i</span>
            <span><strong>Não encontrou o e-mail?</strong> O envio pode levar alguns minutos. Verifique também a pasta de spam ou lixo eletrônico.</span>
        </div>
        <a class="ad-recovery-back" href="index.php"><span aria-hidden="true">←</span> Voltar para o login</a>
    </section>

    <aside class="ad-recovery-side" aria-label="Informações sobre recuperação de senha">
        <div class="ad-recovery-side-icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"
                 fill="none" stroke="#18544d" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="5" y="10" width="14" height="11" rx="2"></rect>
                <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
                <circle cx="12" cy="15.5" r="1"></circle>
            </svg>
        </div>
        <h2>Recupere seu acesso com segurança</h2>
        <p>Você receberá um link exclusivo para redefinir sua senha. Simples, seguro e no mesmo padrão do AgenDay.</p>
    </aside>
</main>
</body>
</html>
