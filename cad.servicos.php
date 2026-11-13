<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/info-servicos.php';
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
    <link rel="stylesheet" href="css/cad_servico.css" />

    <!-- ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <!-- AgenDay 2.0: tokens e componentes visuais padronizados (5.1) -->
    <link rel="stylesheet" href="css/design-system.css">
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
                    <h2 class="text-center">Serviços</h2>
                </div>

                <?php if (!empty($_SESSION['sucesso_servico'])): ?>
                    <div class="alert alert-success mx-3 mt-3" role="status">Serviço cadastrado com sucesso!</div>
                <?php endif; unset($_SESSION['sucesso_servico']); ?>
                <?php if (!empty($_SESSION['erro_servico'])): ?>
                    <div class="alert alert-danger mx-3 mt-3" role="alert">
                        <?= htmlspecialchars((string) $_SESSION['erro_servico'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                    </div>
                <?php endif; unset($_SESSION['erro_servico']); ?>

                <div class="container-fluid buscar">
                    <form class="d-flex d-flex justify-content-around" action="cad.servicos.php" method="GET">
                        <div class="col-md-4">
                            <div class="input-group flex-nowrap">
                                <input type="search" class="form-control shadow input-pesquisa"
                                    name="busca" maxlength="120" placeholder="Buscar serviços"
                                    aria-label="Buscar serviços"
                                    value="<?= htmlspecialchars($busca, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                <button type="submit" aria-label="Pesquisar serviços">
                                    <span class="input-group-text button-pesquisa shadow"><i class="bi bi-search"></i></span>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="rounded-3 border shadow rounded adicionar"
                            data-bs-toggle="modal" data-bs-target="#exampleModal">Adicionar</button>
                    </form>
                </div>

                <div class="modal" tabindex="-1" id="exampleModal" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class=" modal-title m-2">Cadastrar serviços</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                            <form action="cadastro-servicos.php" method="POST">
                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="row">
                                        <div class="mb-3 col-7">
                                            <label for="nome-servico" class="form-label">Nome do serviço</label>
                                            <input type="text" id="nome-servico" name="nome"
                                                class="form-control rounded-3 shadow" maxlength="120"
                                                placeholder="Ex.: Corte de cabelo" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="mb-3 col-7">
                                            <label for="duracao-servico" class="form-label">Duração (minutos)</label>
                                            <input type="number" id="duracao-servico" name="duracao_minutos"
                                                class="form-control rounded-3 shadow" min="1" max="1440" step="1"
                                                placeholder="Ex.: 45" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="mb-3 col-4">
                                            <label for="preco-servico" class="form-label">Preço (R$)</label>
                                            <input type="text" id="preco-servico" name="preco" inputmode="decimal"
                                                class="form-control rounded-3 shadow" maxlength="11"
                                                pattern="[0-9]{1,8}([,.][0-9]{1,2})?"
                                                placeholder="Ex.: 35,90" required>
                                            <small class="text-muted">Aceita vírgula ou ponto. Use 0 para serviço gratuito.</small>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="mb-3 col-7">
                                            <label for="descricao-servico" class="form-label">Descrição (opcional)</label>
                                            <textarea id="descricao-servico" name="descricao"
                                                class="form-control rounded-3 shadow" rows="3"
                                                maxlength="2000" placeholder="Detalhes do serviço"></textarea>
                                        </div>
                                    </div>
                                    <div class="float-end m-3">
                                        <button type="submit" class="concluir rounded-3 border shadow">Concluir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="container scroll">
                    <?php if ($servicos === []): ?>
                        <p class="text-center text-muted py-4">Nenhum serviço encontrado.</p>
                    <?php else: ?>
                        <?php foreach ($servicos as $servico): ?>
                            <?php
                                $partesDuracao = explode(':', (string) $servico['duracao_servico']);
                                $duracaoMinutos = ((int) $partesDuracao[0] * 60) + (int) ($partesDuracao[1] ?? 0);
                            ?>
                            <div class="container conteudo">
                                <div class="row">
                                    <div class="col pt-3">
                                        <strong><?= htmlspecialchars((string) $servico['nome_servico'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                                        <br>
                                        Duração: <?= $duracaoMinutos ?> min
                                        <br>
                                        <strong>Preço: R$ <?= number_format((float) $servico['preco_servico'], 2, ',', '.') ?></strong>
                                        <?php if (!empty($servico['descricao_servico'])): ?>
                                            <p><?= nl2br(htmlspecialchars((string) $servico['descricao_servico'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></p>
                                        <?php endif; ?>
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