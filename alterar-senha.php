<?php
declare(strict_types=1);
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/alteracao-senha.php';

$alertaSenha = null;
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($metodo !== 'GET' && $metodo !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if ($metodo === 'POST') {
    if (!agenday_valida_csrf()) {
        http_response_code(403);
        $alertaSenha = [
            'tipo' => 'danger',
            'texto' => 'Não foi possível validar sua solicitação. Recarregue a página e tente novamente.',
        ];
    } else {
        $erroValidacao = agenday_senha_validar_formulario($_POST);
        if ($erroValidacao !== null) {
            $alertaSenha = ['tipo' => 'danger', 'texto' => $erroValidacao];
        } else {
            $idEstabelecimento = $_SESSION['id_estabelecimento'];
            $senhaAtual = $_POST['senha_atual'];
            $novaSenha = $_POST['nova_senha'];

            try {
                require_once __DIR__ . '/conexao.php';

                $consulta = $conexao->prepare(
                    'SELECT senha_estabelecimento FROM estabelecimento
                     WHERE id_estabelecimento = ? LIMIT 1'
                );
                $consulta->bind_param('i', $idEstabelecimento);
                $consulta->execute();
                $consulta->bind_result($hashAtual);
                $encontrado = $consulta->fetch();
                $consulta->close();

                if (!$encontrado || !is_string($hashAtual)
                    || !password_verify($senhaAtual, $hashAtual)) {
                    $alertaSenha = ['tipo' => 'danger', 'texto' => 'Senha atual incorreta.'];
                } elseif (password_verify($novaSenha, $hashAtual)) {
                    $alertaSenha = [
                        'tipo' => 'danger',
                        'texto' => 'A nova senha deve ser diferente da senha atual.',
                    ];
                } else {
                    $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);

                    // Confere o hash anterior para evitar sobrescrever uma troca simultânea.
                    $atualizacao = $conexao->prepare(
                        'UPDATE estabelecimento SET senha_estabelecimento = ?
                         WHERE id_estabelecimento = ? AND BINARY senha_estabelecimento = ?'
                    );
                    $atualizacao->bind_param('sis', $novoHash, $idEstabelecimento, $hashAtual);
                    $atualizacao->execute();
                    $alterados = $atualizacao->affected_rows;
                    $atualizacao->close();

                    if ($alterados === 1) {
                        session_regenerate_id(true);
                        unset($_SESSION['csrf_token']);
                        $alertaSenha = [
                            'tipo' => 'success',
                            'texto' => 'Senha alterada com sucesso.',
                        ];
                    } else {
                        $alertaSenha = [
                            'tipo' => 'danger',
                            'texto' => 'Não foi possível atualizar a senha. Tente novamente.',
                        ];
                    }
                }
            } catch (Throwable $e) {
                // Nunca registrar senhas, hashes ou detalhes da conexão em logs.
                error_log('AgenDay: erro no processamento de alteração de senha.');
                $alertaSenha = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível alterar a senha agora. Tente novamente.',
                ];
            }
        }

        // POST/Redirect/GET impede reenvio acidental ao atualizar a página.
        $_SESSION['feedback_alteracao_senha'] = $alertaSenha;
        header('Location: alterar-senha.php', true, 303);
        exit;
    }
} elseif (isset($_SESSION['feedback_alteracao_senha'])
    && is_array($_SESSION['feedback_alteracao_senha'])) {
    $alertaSenha = $_SESSION['feedback_alteracao_senha'];
    unset($_SESSION['feedback_alteracao_senha']);
}

$csrfToken = agenday_token_csrf();
?>
<!DOCTYPE html>
<!-- Coding by CodingLab | www.codinglabweb.com-->
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <Title>AgenDay</Title>
    <link rel="icon" href="img/icon-agenda.png" type="image/x-icon">
    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" integrity="sha384-B0vP5xmATw1+K9KRQjQERJvTumQW0nPEzvF6L/Z6nronJ3oUOFUFpCjEUQouq2+l" crossorigin="anonymous" />

    <!-- font awesome  -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css" integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous" />

    <!-- JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3" crossorigin="anonymous"></script>

    <!-- CSS -->
    <link rel="stylesheet" href="css/body.css" />
    <link rel="stylesheet" href="css/box.css">
    <link rel="stylesheet" href="css/alterar-senha.css">
    <!-- AgenDay 2.0: tokens e componentes visuais padronizados (5.1) -->
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css">
</head>

<body class="agenday-app">
    <header class="navbar navbar-light bg-light">
        <div class="container-fluid">
            <button class="navbar-toggler bg-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar" aria-label="Abrir menu principal">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h3 class="ad-header-brand fw-bold text-center mb-0">
                AgenDay</h3>
            <?php require __DIR__ . '/lib/menu-conta.php'; ?>
        <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasDarkNavbar" aria-labelledby="offcanvasDarkNavbarLabel" style="width:20%">
                <div class="offcanvas-header">
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar menu"></button>
                    <h5 class="offcanvas-title" id="offcanvasDarkNavbarLabel">AgenDay</h5>
                </div>
                <div class="offcanvas-body ">
                    <ul class="navbar-nav justify-content-end flex-grow-1 pe-0">
                        <li class="nav-item " style="padding: 10px;">
                            <a class="nav-link fw-bold" href="agenda.php">
                                <img src="img/icon-agenda.svg" alt="Agenda" width="24" height="24" class="d-inline-block align-text-top" style="margin: 0px 10px 0px 0px;">
                                Agenda
                            </a>
                        </li>
                        <li class="nav-item dropdown" style="padding: 10px;">
                            <a class="nav-link dropdown-toggle fw-bold " href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="img/icon-cadastrar.svg" alt="Cadastrar" width="24" height="24" class="d-inline-block align-text-top" style="margin: 0px 10px 0px 0px;">
                                Cadastrar
                            </a>
                            <ul class="dropdown-menu dropdown-menu-light">
                                <li><a class="dropdown-item fw-bold " style="padding: 2px; font-size: 14px;" href="cad-cliente.php">Clientes</a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item fw-bold " style="padding: 2px; font-size: 14px;" href="cad.servicos.php">Serviços</a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item fw-bold" style="padding: 2px; font-size: 14px;" href="cad-funcionario.php">Funcionarios</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown" style="padding: 10px;">
                            <a class="nav-link dropdown-toggle fw-bold" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="img/icon-comandas.svg" alt="Comandas" width="24" height="24" class="d-inline-block align-text-top" style="margin: 0px 10px 0px 0px;">
                                Comandas
                            </a>
                            <ul class="dropdown-menu dropdown-menu-light ">
                                <li><a class="dropdown-item fw-bold" style="padding: 2px; font-size: 14px;" href="comandas-abertas.php">Comandas</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item fw-bold" style="padding: 2px; font-size: 14px;" href="historico-comandas.php">Historicos</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item" style="padding: 10px;">
                            <a class="nav-link fw-bold" href="financeiro.php">
                                <img src="img/icon-financeiro.svg" alt="Financeiro" width="24" height="24" class="d-inline-block align-text-top" style="margin: 0px 10px 0px 0px;">
                                Financeiro
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="offcanvas-footer">
                    <div class="navbar-nav text-center flex-grow-1 pe-3">
                        <li class="nav-item">
                            <a class="nav-link fw-bold" aria-current="page" href="logout.php">
                                <img src="img/icon-sair.svg" alt="Sair" width="24" height="24" class="d-inline-block align-text-top" style="margin: 0px 10px 0px 0px;">
                                Sair
                            </a>
                        </li>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <main>
        <section class="container">
            <div class="card box">
                <div class="text-center">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs d-flex justify-content-evenly">
                            <li class="nav-item">
                                <a class="nav-link text-dark" aria-current="true" href="config.php">Empresa</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-dark" aria-current="true" href="perfil.php">Perfil</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" aria-current="true" href="alterar-senha.php">Alterar
                                    senha</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card boxOne" style="border: none; background: none;">

                        <div class="titulo-body">
                            <h5 class="card-title fw-bold fs-2">Alterar senha</h5>
                        </div>
                        <?php if ($alertaSenha !== null) : ?>
                            <div class="alert alert-<?= $alertaSenha['tipo'] === 'success' ? 'success' : 'danger' ?> text-start mx-auto" role="alert" style="max-width: 560px;">
                                <?= htmlspecialchars($alertaSenha['texto'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="alterar-senha.php" class="container mx-auto text-start pb-4" style="max-width: 560px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                            <p class="text-muted">Para proteger sua conta, informe sua senha atual e escolha uma nova senha.</p>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="senha-atual">Senha atual</label>
                                <input class="form-control" type="password" id="senha-atual" name="senha_atual"
                                    autocomplete="current-password" required maxlength="4096">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold" for="nova-senha">Nova senha</label>
                                <input class="form-control" type="password" id="nova-senha" name="nova_senha"
                                    autocomplete="new-password" required minlength="8" maxlength="72"
                                    aria-describedby="nova-senha-ajuda">
                                <div id="nova-senha-ajuda" class="form-text">Use de 8 a 72 bytes e evite reutilizar sua senha atual.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold" for="confirmar-nova-senha">Confirmar nova senha</label>
                                <input class="form-control" type="password" id="confirmar-nova-senha"
                                    name="confirmar_nova_senha" autocomplete="new-password"
                                    required minlength="8" maxlength="72">
                            </div>

                            <button class="btn btn-primary w-100" type="submit">Salvar nova senha</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <footer>
        <div class="rodape">

        </div>
    </footer>
    <section class="overlay"></section>
    <script src="js/menu-lateral.js"></script>
    <script src="js/mostra-ocultar-senha.js"></script>
</body>

</html>