<?php
require_once __DIR__ . '/sessao.php';
if (isset($_SESSION['id_estabelecimento']) && is_int($_SESSION['id_estabelecimento'])) {
    header('Location: agenda.php');
    exit;
}
$csrfToken = agenday_token_csrf();
?>
<html lang="pt-BR" dir="ltr">

<head>
    <meta charset="utf-8">
    <title>Login</title>


    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

    <!-- JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3" crossorigin="anonymous"></script>

    <link href="https://fonts.googleapis.com/css?family=Arvo" rel="stylesheet">
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.14.0/css/all.min.css'>
    <link rel="stylesheet" href="css/login.css">
    <!-- AgenDay 2.0: tokens e componentes visuais padronizados (5.1) -->
    <link rel="stylesheet" href="css/design-system.css">
</head>

<body class="agenday-auth">
    <div class="container box " id="container">

        <div class="form-container sign-up-container">
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



                <button class="rounded rounded-4" name="bcadastrar">Cadastrar-se</button>
            </form>
        </div>

        <div class="form-container sign-in-container">
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

                <a href="#">Esqueceu a senha?</a>

                <button class=" rounded rounded-4">Entrar</button>
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


    <?php if (isset($_SESSION['erro_cadastro'])) : ?>
        <div class="alert alert-danger col-4 container fixed-bottom" role="alert">
            <?= htmlspecialchars($_SESSION['erro_cadastro'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; unset($_SESSION['erro_cadastro']); ?>

    <script src="js/login.js" charset="utf-8"></script>
</body>


</html>