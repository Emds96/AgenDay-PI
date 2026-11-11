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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

    <!-- JAVASCRIPT -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3"
        crossorigin="anonymous"></script>

    <!-- CSS -->
    <link rel="stylesheet" href="css/agenda.css" />
    <link rel="stylesheet" href="css/body.css" />
    <link rel="stylesheet" href="css/box.css">
    <link rel="stylesheet" href="css/perfil.css">
</head>

<body>
<header class="navbar navbar-light bg-light">
        <div class="container-fluid">
            <button class="navbar-toggler bg-light" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar">
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
                    <li><a class="dropdown-item fw-bold" href="#">Sair</a></li>
                </ul>
            </div>
            <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasDarkNavbar"
                aria-labelledby="offcanvasDarkNavbarLabel" style="width:20%">
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
                                <li><a class="dropdown-item fw-bold " style="padding: 2px; font-size: 14px;"
                                        href="cad-cliente.php">Clientes</a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item fw-bold " style="padding: 2px; font-size: 14px;"
                                        href="cad.servicos.php">Serviços</a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item fw-bold" style="padding: 2px; font-size: 14px;"
                                        href="cad-funcionario.php">Funcionarios</a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown" style="padding: 10px;">
                            <a class="nav-link dropdown-toggle fw-bold" href="#" role="button" data-bs-toggle="dropdown"
                                aria-expanded="false">
                                <img src="img/icon-comandas.svg" alt="Comandas" width="24" height="24" class="d-inline-block align-text-top" style="margin: 0px 10px 0px 0px;">
                                Comandas
                            </a>
                            <ul class="dropdown-menu dropdown-menu-light ">
                                <li><a class="dropdown-item fw-bold" style="padding: 2px; font-size: 14px;"
                                        href="comandas-abertas.php">Comandas</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item fw-bold" style="padding: 2px; font-size: 14px;"
                                        href="historico-comandas.php">Historicos</a>
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
                <div class="card-header text-center">
                    <ul class="nav nav-tabs card-header-tabs d-flex justify-content-evenly">
                        <li class="nav-item">
                            <a class="nav-link text-dark" aria-current="true" href="config.php">Empresa</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="true" href="perfil.php">Perfil</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-dark" aria-current="true" href="alterar-senha.php">Alterar
                                senha</a>
                        </li>
                    </ul>
                </div>

                <div class="container scroll">
                    <div class="card box-card">
                        <div class="border-card container-fluid">
                            <h5 class="card-title fw-bold fs-2 text-center">Perfil</h5>
                        </div>
                        <div class="container">
                            <div class="text-center">
                                <img class="img-fundo" src="https://github.com/mdo.png" alt="">
                            </div>
                            <div class="dados-estabelecimento text-start border-card-bottom">
                                <p>
                                    <strong>Nome propietario:</strong>
                                    Exemplo de nome
                                </p>
                                <p>
                                    <strong>E-mail:</strong>
                                    Exemplo@email.com
                                </p>
                                <p>
                                    <strong>Contato:</strong>
                                    (92) 0 0000-0000
                                </p>
                                <p>
                                    <strong>Data de Nascismento:</strong>
                                    00-00-0000
                                </p>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-success button-editar float-end" data-bs-toggle="modal"
                        data-bs-target="#exampleModal">
                        Editar
                    </button>
                </div>
            </div>
        </section>
        <section>
            <!-- Modal -->
            <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="exampleModalLabel">Alterar dados do Perfil</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">

                            <form class="row g-0 needs-validation" novalidate>
                                <div class="col-md-10">
                                    <label for="validationCustom01" class="form-label">Nome propietario</label>
                                    <input type="text" class="form-control" id="validationCustom01">
                                    <div class="valid-feedback">
                                        Looks good!
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <label for="validationCustom01" class="form-label">E-mail</label>
                                    <input type="text" class="form-control" id="validationCustom01">
                                    <div class="valid-feedback">
                                        Looks good!
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <label for="validationCustom01" class="form-label">Contato</label>
                                    <input type="text" class="form-control" id="validationCustom01">
                                    <div class="valid-feedback">
                                        Looks good!
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <label for="validationCustom01" class="form-label">Endereço</label>
                                    <input type="text" class="form-control" id="validationCustom01">
                                    <div class="valid-feedback">
                                        Looks good!
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <label for="validationCustom01" class="form-label">Bairro</label>
                                    <input type="text" class="form-control" id="validationCustom01">
                                    <div class="valid-feedback">
                                        Looks good!
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <label for="validationCustom01" class="form-label">Cidade</label>
                                    <input type="text" class="form-control" id="validationCustom01">
                                    <div class="valid-feedback">
                                        Looks good!
                                    </div>
                                </div>
                                <div class="col-md-10">
                                    <label for="validationCustom01" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="validationCustom01">
                                    <div class="valid-feedback">
                                        Looks good!
                                    </div>
                                </div>
                            </form>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary">Salvar</button>
                        </div>
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
    <script src="js\menu-lateral.js"></script>
</body>

</html>