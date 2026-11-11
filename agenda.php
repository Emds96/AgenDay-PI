<!DOCTYPE html>
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
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/agenda.css">
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
        <!-- agenda -->
        <section class="container-fluid">
            <div class="card box-agenda">
                <div class="row g-0">
                    <div class="col-sm-6 col-md-8 card-container shadow-lg p-3 mb-5 bg-body rounded">
                        <div class="card">
                            <ul class="list-group list-group-flush">
                                <a href="">
                                    <li class="list-group-item">8:00</li>
                                </a>

                                <li class="list-group-item">8:30</li>
                                <li class="list-group-item">9:00</li>
                                <li class="list-group-item">9:30</li>
                                <li class="list-group-item">10:00</li>
                                <li class="list-group-item">10:30</li>
                                <li class="list-group-item">11:00</li>
                                <li class="list-group-item">11:30</li>
                                <li class="list-group-item">12:00</li>
                                <li class="list-group-item">12:30</li>
                                <li class="list-group-item">13:00</li>
                                <li class="list-group-item">13:30</li>
                                <li class="list-group-item">14:00</li>
                                <li class="list-group-item">14:30</li>
                                <li class="list-group-item">15:00</li>
                                <li class="list-group-item">15:30</li>
                                <li class="list-group-item">16:00</li>
                                <li class="list-group-item">16:30</li>
                                <li class="list-group-item">17:00</li>
                                <li class="list-group-item">17:30</li>
                                <li class="list-group-item">18:00</li>
                                <li class="list-group-item">18:30</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <!-- calendario -->
            <div class="card box-agenda">
                <div class="col-sm-6 col-md-8 light ">
                    <div class="calendar">
                        <div class="calendar-header">
                            <span class="month-picker" id="month-picker">February</span>
                            <div class="year-picker">
                                <span class="year-change" id="prev-year">
                                    <pre><</pre>
                                </span>
                                <span id="year">2021</span>
                                <span class="year-change" id="next-year">
                                    <pre>></pre>
                                </span>
                            </div>
                        </div>
                        <div class="calendar-body">
                            <div class="calendar-week-day">
                                <div>Dom</div>
                                <div>Seg</div>
                                <div>Ter</div>
                                <div>Qua</div>
                                <div>Qui</div>
                                <div>Sex</div>
                                <div>Sab</div>
                            </div>
                            <div class="calendar-days">

                            </div>
                        </div>
                        <!-- <div class="calendar-footer">
                            <div class="toggle">
                                <span>Dark Mode</span>
                                <div class="dark-mode-switch">
                                    <div class="dark-mode-switch-ident"></div>
                                </div>
                            </div>
                        </div> -->
                        <div class="month-list"></div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    <section class="overlay"></section>
    <script src="js/menu-lateral.js"></script>
    <script src="js/calendario.js"></script>

</body>

</html>