<?php
require_once __DIR__ . '/verifica_login.php';
include("info-funcionario.php");
?>

<!DOCTYPE html>
<!-- Coding by CodingLab | www.codinglabweb.com-->
<html lang="en">

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
                    <h2 class="text-center">Funcionarios</h2>
                </div>

                <div class="container-fluid buscar">
                    <form class="d-flex d-flex justify-content-around ">
                        <div class="col-md-4">
                            <div class="input-group flex-nowrap">
                                <input type="text" class="form-control shadow input-pesquisa" placeholder="Buscar" aria-label="Username" aria-describedby="addon-wrapping">

                                <button>
                                    <span class="input-group-text button-pesquisa shadow" id="addon-wrapping"><i class="bi bi-search"></i></span>
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
                                <h3 class=" modal-title m-2">Cadastrar funcionarios</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <form enctype="multipart/form-data" action="cadastro-funcionario.php" method="POST">
                                    <div class="row">
                                        <div class="mb-3 col-7">
                                            <input type="text" class="form-control rounded-3 shadow" placeholder="Nome do funcionario" name="nome">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-7 mb-3">
                                            <input type="text" class="form-control rounded-3 shadow" placeholder="Serviço" name="servico">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-4 mb-3">
                                            <input type="text" class="form-control rounded-3 shadow" placeholder="Contato" name="contato">
                                        </div>
                                    </div>

                                    <div class="input-group">
                                        <input type="file" class="form-control shadow" id="inputGroupFile04" aria-describedby="inputGroupFileAddon04" aria-label="Upload" name="arquivo">
                                    </div>

                                    <div class="float-end m-3">
                                        <div class="">
                                            <button class="concluir rounded-3 border shadow">Concluir</button>
                                        </div>
                                    </div>
                                </form>
        
                            </div>
                        </div>
                    </div>
                </div>

                <div class='container scroll'>
                    <?php
                    while ($user_data = mysqli_fetch_assoc($result)) {
                        echo
                            "<div class='container conteudo'>
                                <div class='row'>
                                    <div class='col-1' style='text-align: center;'>
                                        <img src='".$user_data['arquivo_funcionario']."' class='perfil' alt='...'>
                                    </div>
                                    <div class='col pt-3'>
                                        <strong>".
                                        $user_data['nome_funcionario']." <br>
                                        </strong>".
                                        $user_data['contato_funcionario']."<br>
                                        <strong>".
                                        $user_data['servico_funcionario']."
                                        </strong>
                                    </div>
                                </div>   
                            </div>";
                    }
                    ?>
                </div>
        </section>
    </main>
    <section class="overlay"></section>
    <script src="js\menu-lateral.js"></script>
    <?php
    if (isset($_SESSION['msg'])) :
    ?>
        <div class="alert alert-danger col-2 container fixed-bottom" role="alert">
            Funcionario não cadastrado. <br>
            Formato de imagem invalida.
        </div>
    <?php
    endif;
    unset($_SESSION['msg']);
    ?>

    <footer>
        <div class="rodape">
        </div>
    </footer>
</body>

</html>