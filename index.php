<?php
require_once __DIR__ . '/sessao.php';
if (isset($_SESSION['id_estabelecimento']) && is_int($_SESSION['id_estabelecimento'])) {
    header('Location: agenda.php');
    exit;
}
$csrfToken = agenday_token_csrf();
$abrirCadastro = isset($_SESSION['erro_cadastro']) || isset($_SESSION['usuario_existe']);

// O Client ID é público. O helper tolera falhas no arquivo privado,
// preservando a tela de login mesmo que a configuração local esteja corrompida.
require_once __DIR__ . '/lib/google-identidade.php';
$googleConfig = agenday_google_config();
$googleClientId = $googleConfig['client_id'] ?? '';
$googleNonce = '';
if ($googleClientId !== '') {
    if (empty($_SESSION['google_nonce']) || !is_string($_SESSION['google_nonce'])) {
        $_SESSION['google_nonce'] = bin2hex(random_bytes(24));
    }
    $googleNonce = $_SESSION['google_nonce'];
}
// O pop-up só abre após validação do Google no servidor, jamais por GET.
$googlePending = $_SESSION['google_pending'] ?? null;
if (!is_array($googlePending)
    || !isset($googlePending['sub'], $googlePending['email'], $googlePending['created_at'])
    || !is_string($googlePending['sub']) || !is_string($googlePending['email'])
    || !is_int($googlePending['created_at']) || time() - $googlePending['created_at'] > 600) {
    $googlePending = null;
    unset($_SESSION['google_pending']);
}
?>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>


    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

    <!-- JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3" crossorigin="anonymous"></script>

    <link href="https://fonts.googleapis.com/css?family=Arvo" rel="stylesheet">
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css'>
    <link rel="stylesheet" href="css/login.css?v=20261120-cad01">
    <!-- AgenDay 2.0: tokens e componentes visuais padronizados (5.1) -->
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css">
    <link rel="stylesheet" href="css/google-auth.css">
    <?php if ($googleClientId !== '') : ?>
        <script src="https://accounts.google.com/gsi/client?hl=pt-BR" async defer></script>
    <?php endif; ?>
</head>

<body class="agenday-auth<?= $googleClientId !== '' ? ' google-enabled' : '' ?>">
    <?php if ($googleClientId !== '') : ?>
        <div id="g_id_onload"
             data-client_id="<?= htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8') ?>"
             data-login_uri="https://agenday-sistema.infinityfree.io/google-entrar.php"
             data-auto_prompt="false" data-ux_mode="popup"
             data-nonce="<?= htmlspecialchars($googleNonce, ENT_QUOTES, 'UTF-8') ?>"></div>
    <?php endif; ?>
    <div class="container box <?= $abrirCadastro ? 'right-panel-active' : '' ?>" id="container">
        <div class="auth-mobile-switch" role="group" aria-label="Acesso ao AgenDay">
            <button type="button" id="auth-ir-login" aria-pressed="<?= $abrirCadastro ? 'false' : 'true' ?>"
                aria-controls="formulario-login">Entrar</button>
            <button type="button" id="auth-ir-cadastro" aria-pressed="<?= $abrirCadastro ? 'true' : 'false' ?>"
                aria-controls="formulario-cadastro">Criar conta</button>
        </div>

        <div class="form-container sign-up-container" id="formulario-cadastro">
            <form action="cadastrar.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <h1>Criar Conta</h1>

                <div class="row">
                    <div class="mb-2 col">
                        <div class="input-group ">
                            <input class="input rounded rounded-4" name="estabelecimento" type="text" placeholder=" " required />
                            <label class="letra">Nome do estabelecimento</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-2 col">
                        <div class="input-group">
                            <input class="input rounded rounded-4" name="email" type="email" placeholder=" " required />
                            <label class="letra">E-mail</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-2 col">
                        <div class="input-group">
                            <input class="input rounded rounded-4" name="senha" type="password" placeholder=" " minlength="8" required />
                            <label class="letra">Senha</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-2 col">
                        <div class="input-group">
                            <input class="input rounded rounded-4" name="confirmar_senha" type="password" placeholder=" " minlength="8" required />
                            <label class="letra">Repita a senha</label>
                        </div>
                    </div>
                </div>

                <fieldset class="ad-horario-cadastro">
                    <legend>Horário de funcionamento</legend>
                    <div class="ad-horario-dias" role="group" aria-label="Dias de funcionamento">
                        <?php foreach ([1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'] as $numeroDia => $rotuloDia) : ?>
                            <label class="ad-horario-dia">
                                <input type="checkbox" name="dias[]" value="<?= (int) $numeroDia ?>"
                                    <?= $numeroDia !== 0 ? 'checked' : '' ?>>
                                <span><?= $rotuloDia ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="ad-horario-horas">
                        <label>
                            <span>Abre às</span>
                            <input class="rounded rounded-4" type="time" name="abertura" value="08:00" step="300" required>
                        </label>
                        <label>
                            <span>Fecha às</span>
                            <input class="rounded rounded-4" type="time" name="fechamento" value="18:00" step="300" required>
                        </label>
                    </div>
                    <p class="ad-horario-ajuda">Pode ajustar depois em Configurações → Horários.</p>
                </fieldset>



                <button class="rounded rounded-4" name="bcadastrar">Cadastrar-se</button>
                <?php if ($googleClientId !== '') : ?>
                    <div class="ad-google-choice" aria-label="Outra forma de criar conta">
                        <span class="ad-google-divider">OU</span>
                        <div class="ad-google-button">
                            <div class="g_id_signin" data-type="standard" data-theme="outline"
                                 data-size="large" data-shape="pill" data-text="signup_with"
                                 data-logo_alignment="left" data-locale="pt-BR"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="form-container sign-in-container" id="formulario-login">
            <form action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <h1>Entrar</h1>

                <div class="row">
                    <div class="mb-2 col">
                        <div class="input-group">
                            <input class="input rounded rounded-4" type="email" placeholder=" " name="email" />
                            <label class="letra">E-mail</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="mb-2 col">
                        <div class="input-group ">
                            <input class="input rounded rounded-4" type="password" placeholder=" " name="senha" />
                            <label class="letra">Senha</label>
                        </div>
                    </div>
                </div>

                <a href="esqueci-senha.php">Esqueceu a senha?</a>

                <button class=" rounded rounded-4">Entrar</button>
                <?php if ($googleClientId !== '') : ?>
                    <div class="ad-google-choice" aria-label="Outra forma de entrar">
                        <span class="ad-google-divider">OU</span>
                        <div class="ad-google-button">
                            <div class="g_id_signin" data-type="standard" data-theme="outline"
                                 data-size="large" data-shape="pill" data-text="signin_with"
                                 data-logo_alignment="left" data-locale="pt-BR"></div>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>Bem vindo de volta!</h1>
                    <p>Para se manter conectado conosco, faça login com seus dados do seu estabelecimento</p>
                    <button class="ghost rounded rounded-4 " id="signIn">Entrar</button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1>Olá!</h1>
                    <p>Insira os dados do seu estabelecimento para abrir uma conta conosco</p>
                    <button class="ghost rounded rounded-4" id="signUp">Cadastrar-se</button>
                </div>
            </div>
        </div>

    </div>

    <?php
    if (isset($_SESSION['status_cadastro'])) :
    ?>
        <div class="alert alert-success col-2 container fixed-bottom" role="alert">
            Cadastro efetuado com sucesso!

        </div>
    <?php
    endif;
    unset($_SESSION['status_cadastro']);
    ?>

    <?php
    if (isset($_SESSION['usuario_existe'])) :
    ?>
        <div class="alert alert-danger col-2 container fixed-bottom" role="alert">
            E-mail de estabelecimento já cadastrado
        </div>
    <?php
    endif;
    unset($_SESSION['usuario_existe']);
    ?>


    <?php
    if (isset($_SESSION['nao_autenticado'])) :
    ?>

        <div class="alert alert-danger col-2 container fixed-bottom" role="alert">

            E-mail ou senha inválidos.

        </div>
    <?php
    endif;
    unset($_SESSION['nao_autenticado']);
    ?>


    <?php if (isset($_SESSION['erro_google_login'])) : ?>
        <div class="alert alert-danger col-4 container fixed-bottom" role="alert">
            <?= htmlspecialchars((string) $_SESSION['erro_google_login'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; unset($_SESSION['erro_google_login']); ?>

    <?php if (isset($_SESSION['erro_cadastro'])) : ?>
        <div class="alert alert-danger col-4 container fixed-bottom" role="alert">
            <?= htmlspecialchars($_SESSION['erro_cadastro'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; unset($_SESSION['erro_cadastro']); ?>

    <?php if ($googlePending !== null) : ?>
        <dialog id="ad-google-onboarding" class="ad-google-dialog"
                aria-labelledby="ad-google-dialog-titulo" aria-describedby="ad-google-dialog-descricao">
            <div class="ad-google-dialog__header">
                <h2 id="ad-google-dialog-titulo">Bem-vindo ao AgenDay!</h2>
                <p id="ad-google-dialog-descricao">Complete o cadastro do seu estabelecimento para começar.</p>
            </div>
            <form action="google-cadastro-concluir.php" method="POST" id="ad-google-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <p class="ad-google-hint">Conta Google: <strong><?= htmlspecialchars($googlePending['email'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                <?php if (isset($_SESSION['erro_google_cadastro'])) : ?>
                    <p class="alert alert-danger" role="alert"><?= htmlspecialchars((string) $_SESSION['erro_google_cadastro'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; unset($_SESSION['erro_google_cadastro']); ?>
                <div class="ad-google-field">
                    <label for="ad-google-estabelecimento">Nome do estabelecimento *</label>
                    <input type="text" id="ad-google-estabelecimento" name="estabelecimento"
                           maxlength="120" autocomplete="organization" required
                           placeholder="Ex.: Meu estabelecimento">
                </div>
                <div class="ad-google-field">
                    <label for="ad-google-senha">Criar senha do AgenDay (opcional)</label>
                    <input type="password" id="ad-google-senha" name="senha"
                           autocomplete="new-password" minlength="8" maxlength="72"
                           placeholder="Opcional: entrar também com e-mail">
                </div>
                <div class="ad-google-field" id="ad-google-confirmar-wrapper" hidden>
                    <label for="ad-google-confirmar">Confirmar senha do AgenDay</label>
                    <input type="password" id="ad-google-confirmar" name="confirmar_senha"
                           autocomplete="new-password" minlength="8" maxlength="72">
                </div>
                <p class="ad-google-notice"><strong>Importante:</strong> esta senha é exclusiva
                    para acessar o AgenDay com seu e-mail. Não é a senha da sua conta Google.
                    Nunca informe sua senha do Google aqui.</p>
                <div class="ad-google-actions">
                    <button type="submit" name="concluir" value="1">Concluir cadastro</button>
                    <button type="submit" name="sem_senha" value="1">Agora não — usar só o Google</button>
                </div>
            </form>
        </dialog>
    <?php endif; ?>

    <script src="js/login.js" charset="utf-8"></script>
    <script src="js/google-auth.js" charset="utf-8"></script>
</body>


</html>