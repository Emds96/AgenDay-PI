<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/info-funcionario.php';
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
    <link rel="stylesheet" href="css/cad_funcionarios.css" />

    <!-- ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <!-- AgenDay 2.0: tokens e componentes visuais padronizados (5.1) -->
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css">
</head>

<body class="agenday-app">
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
                    <?= htmlspecialchars((string) ($_SESSION['nome_estabelecimento'] ?? 'Estabelecimento'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
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
                    <h2 class="text-center">Funcionários</h2>
                </div>

                <?php if (!empty($_SESSION['sucesso_funcionario'])): ?>
                    <div class="alert alert-success mx-3 mt-3" role="status">Funcionário cadastrado com sucesso!</div>
                <?php endif; unset($_SESSION['sucesso_funcionario']); ?>
                <?php if (!empty($_SESSION['erro_funcionario'])): ?>
                    <div class="alert alert-danger mx-3 mt-3" role="alert">
                        <?= htmlspecialchars((string) $_SESSION['erro_funcionario'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; unset($_SESSION['erro_funcionario']); ?>

                <div class="container-fluid buscar">
                    <form class="d-flex d-flex justify-content-around" method="GET" action="cad-funcionario.php">
                        <div class="col-md-4">
                            <div class="input-group flex-nowrap">
                                <input type="search" class="form-control shadow input-pesquisa"
                                    name="busca" maxlength="120" placeholder="Buscar funcionário"
                                    aria-label="Buscar funcionários"
                                    value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <button type="submit" aria-label="Pesquisar funcionários">
                                    <span class="input-group-text button-pesquisa shadow">
                                        <i class="bi bi-search"></i>
                                    </span>
                                </button>
                            </div>
                        </div>
                        <button class="rounded-3 border shadow rounded adicionar" data-bs-toggle="modal"
                            data-bs-target="#exampleModal" type="button">Adicionar</button>
                    </form>
                </div>

                <div class="modal" tabindex="-1" id="exampleModal" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class=" modal-title m-2">Cadastrar funcionarios</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <form enctype="multipart/form-data" action="cadastro-funcionario.php" method="POST">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="row">
                                        <div class="mb-3 col-7">
                                            <input type="text" class="form-control rounded-3 shadow"
                                                placeholder="Nome do funcionário" name="nome" maxlength="120" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-4 mb-3">
                                            <input type="tel" class="form-control rounded-3 shadow"
                                                placeholder="Contato" name="contato" maxlength="20" required>
                                        </div>
                                    </div>

                                    <fieldset class="mb-3">
                                        <legend class="fs-6">Serviços que realiza (opcional)</legend>
                                        <?php if ($servicosDisponiveis === []): ?>
                                            <p class="text-muted mb-2">Ainda não há serviços cadastrados.
                                                Você pode cadastrar o funcionário agora e vincular serviços depois.</p>
                                        <?php else: ?>
                                            <?php foreach ($servicosDisponiveis as $servico): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="servicos[]"
                                                        value="<?= (int) $servico['id_servicos'] ?>"
                                                        id="func-servico-<?= (int) $servico['id_servicos'] ?>">
                                                    <label class="form-check-label"
                                                        for="func-servico-<?= (int) $servico['id_servicos'] ?>">
                                                        <?= htmlspecialchars((string) $servico['nome_servico'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </fieldset>

                                    <div class="input-group">
                                        <input type="file" class="form-control shadow" id="inputGroupFile04"
                                            aria-label="Foto do funcionário (opcional)" name="arquivo"
                                            accept="image/jpeg,image/png">
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
                    <?php if ($funcionarios === []): ?>
                        <p class="text-center text-muted py-4">Nenhum funcionário encontrado.</p>
                    <?php else: ?>
                        <?php foreach ($funcionarios as $funcionario): ?>
                            <div class="container conteudo">
                                <div class="row">
                                    <div class="col-1" style="text-align: center;">
                                        <?php
                                            $fotoFuncionario = $funcionario['arquivo_funcionario'];
                                            $fotoValida = is_string($fotoFuncionario)
                                                && preg_match('~^arquivos/funcionarios/[a-f0-9]{32}\.(jpg|png)$~D', $fotoFuncionario);
                                        ?>
                                        <?php if ($fotoValida): ?>
                                            <img src="<?= htmlspecialchars($fotoFuncionario, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                                class="perfil" alt="Foto do funcionário">
                                        <?php else: ?>
                                            <i class="bi bi-person-circle fs-2" aria-hidden="true"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col pt-3">
                                        <strong><?= htmlspecialchars((string) $funcionario['nome_funcionario'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                                        <br>
                                        <?= htmlspecialchars((string) $funcionario['contato_funcionario'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        <br>
                                        <small><?= $funcionario['servicos_vinculados'] !== null && $funcionario['servicos_vinculados'] !== ''
                                            ? htmlspecialchars((string) $funcionario['servicos_vinculados'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                                            : 'Sem serviços vinculados' ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    <section class="overlay"></section>
    <script src="js\menu-lateral.js"></script>


    <footer>
        <div class="rodape">
        </div>
    </footer>
</body>

</html>