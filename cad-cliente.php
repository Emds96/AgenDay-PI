<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/info-clientes.php';
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

    <!-- JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3" crossorigin="anonymous"></script>

    <!-- CSS -->
    <link rel="stylesheet" href="css/agenda.css" />
    <link rel="stylesheet" href="css/body.css" />
    <link rel="stylesheet" href="css/cad_cliente.css" />

    <!-- ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
</head>

<body>
    <header class="navbar navbar-light bg-light">
        <div class="container-fluid">
            <button class="navbar-toggler bg-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h3 class="offcanvas-title fw-bold text-center" id="offcanvasDarkNavbarLabel">
                AgenDay</h3>
            <div class="dropdown">
                <a class="btn dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="https://github.com/mdo.png" alt="" width="32" height="32" class="rounded-circle me-2">
                    Barbearia
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item fw-bold" href="config.php">Configurações</a></li>
                    <li><a class="dropdown-item fw-bold" href="perfil.php">Perfil</a></li>
                    <li><a class="dropdown-item fw-bold" href="alterar-senha.php">Alterar senha</a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item fw-bold" href="logout.php">Sair</a></li>
                </ul>
            </div>
            <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasDarkNavbar" aria-labelledby="offcanvasDarkNavbarLabel" style="width:20%">
                <div class="offcanvas-header">
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
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

                <div class="card-header">
                    <h2 class="text-center">Clientes</h2>
                </div>

                <?php if (!empty($_SESSION['sucesso_cliente'])): ?>
                    <div class="alert alert-success mx-3 mt-3" role="status">Cliente cadastrado com sucesso!</div>
                <?php endif; unset($_SESSION['sucesso_cliente']); ?>
                <?php if (!empty($_SESSION['erro_cliente'])): ?>
                    <div class="alert alert-danger mx-3 mt-3" role="alert">
                        <?= htmlspecialchars((string) $_SESSION['erro_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; unset($_SESSION['erro_cliente']); ?>

                <div class="container-fluid buscar">
                    <form class="d-flex d-flex justify-content-around" action="cad-cliente.php" method="GET">
                        <div class="col-md-4">
                            <div class="input-group flex-nowrap">
                                <input type="search" class="form-control shadow input-pesquisa" name="busca"
                                    value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                    placeholder="Buscar clientes" aria-label="Buscar clientes" maxlength="120">
                                <button type="submit" aria-label="Pesquisar clientes">
                                    <span class="input-group-text button-pesquisa shadow"><i class="bi bi-search"></i></span>
                                </button>
                            </div>
                        </div>
                        <button class="rounded-3 border shadow rounded adicionar" data-bs-toggle="modal" data-bs-target="#exampleModal" type="button">Adicionar</button>
                    </form>
                </div>

                <div class="modal" tabindex="-1" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class=" modal-title m-2">Cadastrar Clientes</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <form enctype="multipart/form-data" action="cadastro-clientes.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="row">
                                        <div class="mb-3 col-7">
                                            <input type="text" class="form-control rounded-3 shadow" placeholder="Nome do cliente" name="nome" maxlength="120" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-7 mb-3">
                                            <input type="email" class="form-control rounded-3 shadow" placeholder="E-mail (opcional)" name="email" maxlength="190">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-4 mb-3">
                                            <input type="tel" class="form-control rounded-3 shadow" placeholder="Contato" name="contato" maxlength="20" required>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <input type="file" class="form-control shadow" id="inputGroupFile04" name="arquivo"
                                            accept="image/jpeg,image/png" aria-label="Foto do cliente (opcional)">
                                    </div>
                                    <small class="text-muted">Foto opcional: JPG ou PNG, até 2 MB.</small>
                                    <div class="float-end m-3">
                                        <button type="submit" class="concluir rounded-3 border shadow">Concluir</button>
                                    </div>
                                </form>
        
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container scroll">
                    <?php if (empty($clientes)): ?>
                        <p class="text-center text-muted py-4">Nenhum cliente encontrado.</p>
                    <?php else: ?>
                        <?php foreach ($clientes as $cliente): ?>
                            <div class="container conteudo">
                                <div class="row">
                                    <div class="col-1" style="text-align: center;">
                                        <?php
                                            $fotoCliente = $cliente['arquivo_cliente'];
                                            $fotoValida = is_string($fotoCliente)
                                                && preg_match('~^arquivos/clientes/[a-f0-9]{32}\.(jpg|png)$~D', $fotoCliente);
                                        ?>
                                        <?php if ($fotoValida): ?>
                                            <img src="<?= htmlspecialchars($fotoCliente, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                                class="perfil" alt="Foto do cliente">
                                        <?php else: ?>
                                            <i class="bi bi-person-circle fs-2" aria-hidden="true"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col pt-3">
                                        <strong><?= htmlspecialchars((string) $cliente['nome_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                                        <br>
                                        <?= htmlspecialchars((string) $cliente['contato_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        <?php if (!empty($cliente['email_cliente'])): ?>
                                            <br>
                                            <?= htmlspecialchars((string) $cliente['email_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
    </main>

    <section class="overlay"></section>
    <script src="js\menu-lateral.js"></script>

    <footer>
        <div class="rodape">
        </div>
    </footer>
</body>

</html>