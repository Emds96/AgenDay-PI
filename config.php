<?php require_once __DIR__ . '/verifica_login.php'; ?>
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

    <!-- icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">

    <!-- CSS -->
    <link rel="stylesheet" href="css/agenda.css" />
    <link rel="stylesheet" href="css/body.css" />
    <link rel="stylesheet" href="css/box.css">
    <link rel="stylesheet" href="css/configurações.css">
    <!-- AgenDay 2.0: tokens e componentes visuais padronizados (5.1) -->
    <link rel="stylesheet" href="css/design-system.css">
</head>

<body class="agenday-app">
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
                    <li><a class="dropdown-item fw-bold" href="logout.php">Sair</a></li>
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
                <div class="">
                    <div class="card-header text-center">
                        <ul class="nav nav-tabs card-header-tabs d-flex justify-content-evenly">
                            <li class="nav-item">
                                <a class="nav-link active" aria-current="true" href="config.php">Empresa</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link text-dark" aria-current="true" href="perfil.php">Perfil</a>
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
                                <h5 class="card-title fw-bold fs-3 text-center">Dados do estabelecimento</h5>
                            </div>
                            <div class="dados-estabelecimento text-start border-card">
                                <p>
                                    <strong>Nome do estabelecimento:</strong>
                                    Barbearia
                                </p>
                                <p>
                                    <strong>E-mail:</strong>
                                    exemplo@email.com
                                </p>
                                <p>
                                    <strong>Contato:</strong>
                                    (92) 0 0000-0000
                                </p>
                            </div>
                            <div class="dados-estabelecimento text-start border-card">
                                <p>
                                    <strong>Cnpj:</strong>
                                    00.000.000/0001-00
                                </p>
                                <p>
                                    <strong>Endereço:</strong>
                                    Avenida rua de exemplo
                                </p>
                                <p>
                                    <strong>Nº:</strong>
                                    0
                                </p>
                                <p>
                                    <strong>Cepº:</strong>
                                    0000-00
                                </p>
                                <p>
                                    <strong>Complemento:</strong>
                                    apartamento 0
                                </p>
                                <p>
                                    <strong>Cidade:</strong>
                                    Manaus
                                </p>
                                <p>
                                    <strong>Estado:</strong>
                                    Amazonas
                                </p>
                            </div>
                            <div class="dados-estabelecimento text-start border-card">
                                <p>
                                    <strong>Facebook:</strong>
                                </p>
                                <p>
                                    <strong>Twitter:</strong>
                                    <link rel="stylesheet" href="">
                                </p>
                                <p>
                                    <strong>Instagran:</strong>
                                    <link rel="stylesheet" href="">
                                </p>
                                <p>
                                    <strong>Site:</strong>
                                    <link rel="stylesheet" href="">
                                </p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-success button-editar float-end" data-bs-toggle="modal"
                            data-bs-target="#dados-empresa">
                            Editar
                        </button>
                    </div>
                </div>
            </div>
        </section>
        <section>
            <!-- Modal dados da empresa -->
            <div class="modal fade modal-lg" id="dados-empresa" tabindex="-1" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="exampleModalLabel">Alterar dados da empresa
                            </h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class=" container modal-body">
                            <div class="card-header text-center">
                                <ul class="nav nav-tabs card-header-tabs d-flex justify-content-evenly">
                                    <li class="nav-item">
                                        <a class="nav-link active" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#dados-empresa">Alterar
                                            dados</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-dark" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#loc-empresa">Alterar
                                            localização</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-dark" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#hora-empresa">Alterar
                                            horario</a>
                                    </li>
                                </ul>
                            </div>

                            <form class="container w-75 card" style="border: none;" novalidate>
                                <div class="">
                                    <label for="validationCustom01" class="form-label">Nome estabelecimento</label>
                                    <div class="input-group mb-3 col-md-10">
                                        <input type="text" class="form-control" id="validationCustom01">
                                    </div>
                                </div>
                                <div class="">
                                    <label for="validationCustom01" class="form-label">E-mail</label>
                                    <div class="input-group mb-3 col-md-10">
                                        <input type="text" class="form-control" id="validationCustom01">
                                    </div>
                                </div>
                                <div id="input-contato1">
                                    <label for="validationCustom01" class="form-label">Contato</label>
                                    <div class="input-group mb-3 col-md-10">
                                        <input type="text" class="form-control" id="validationCustom01"
                                            aria-describedby="basic-addon2">
                                        <span class="input-group-text btn border" id="basic-addon2">
                                            <i class="bi bi-plus"></i>
                                        </span>
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
            <!-- Modal localização da empresa -->
            <div class="modal fade modal-lg" id="loc-empresa" tabindex="-1" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="exampleModalLabel">Alterar localização da
                                empresa</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs d-flex justify-content-evenly">
                                    <li class="nav-item">
                                        <a class="nav-link text-dark" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#dados-empresa">Alterar
                                            dados</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link active" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#loc-empresa">Alterar
                                            localização</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-dark" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#hora-empresa">Alterar
                                            horario</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="modal-main">
                                <form class="container w-75 card" style="border: none;" novalidate>
                                    <div class="">
                                        <label for="validationCustom01" class="form-label">CNPJ</label>
                                        <div class="input-group mb-3 col-md-10">
                                            <input type="text" class="form-control" id="validationCustom01">
                                        </div>
                                    </div>
                                    <div class="">
                                        <label for="validationCustom01" class="form-label">Endereço</label>
                                        <div class="input-group mb-3 col-md-10">
                                            <input type="text" class="form-control" id="validationCustom01">
                                        </div>
                                    </div>
                                    <div id="input-contato1">
                                        <label for="validationCustom01" class="form-label">Nº</label>
                                        <div class="input-group mb-3 col-md-10">
                                            <input type="text" class="form-control" id="validationCustom01">
                                        </div>
                                    </div>
                                    <div id="input-contato1">
                                        <label for="validationCustom01" class="form-label">CEP</label>
                                        <div class="input-group mb-3 col-md-10">
                                            <input type="text" class="form-control" id="validationCustom01">
                                        </div>
                                    </div>
                                    <div id="input-contato1">
                                        <label for="validationCustom01" class="form-label">Complemento</label>
                                        <div class="input-group mb-3 col-md-10">
                                            <input type="text" class="form-control" id="validationCustom01">
                                        </div>
                                    </div>
                                    <div id="input-contato1">
                                        <label for="validationCustom01" class="form-label">Cidade</label>
                                        <div class="input-group mb-3 col-md-10">
                                            <input type="text" class="form-control" id="validationCustom01">
                                        </div>
                                    </div>
                                    <div id="input-contato1">
                                        <label for="validationCustom01" class="form-label">Estado</label>
                                        <div class="input-group mb-3 col-md-10">
                                            <input type="text" class="form-control" id="validationCustom01">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary">Salvar</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal horario da empresa -->
            <div class="modal fade modal-lg" id="hora-empresa" tabindex="-1" aria-labelledby="exampleModalLabel"
                aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="exampleModalLabel">Alterar horario da empresa
                            </h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs d-flex justify-content-evenly">
                                    <li class="nav-item">
                                        <a class="nav-link text-dark" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#dados-empresa">Alterar
                                            dados</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link text-dark" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#loc-empresa">Alterar
                                            localização</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link active" aria-current="true" data-bs-toggle="modal"
                                            data-bs-target="#hora-empresa">Alterar
                                            horario</a>
                                    </li>
                                </ul>
                            </div>
                            <div class="modal-main">
                                <form class="container w-75 card" style="border: none;" novalidate>

                                    <div class="label-input-1">
                                        <label for="validationCustom01" class="form-label">Informe os dias</label>
                                        <select class="form-select" id="validationCustom01">
                                            <option selected></option>
                                            <option value="1">Domingo</option>
                                            <option value="2">Segunda</option>
                                            <option value="3">Terça</option>
                                            <option value="4">Quarta</option>
                                            <option value="5">Quinta</option>
                                            <option value="6">Sexta</option>
                                            <option value="7">Sabado</option>
                                        </select>
                                    </div>

                                    <div class="input-group">
                                        <div class="label-input">
                                            <label for="validationCustom01" class="form-label">Hora de entrada 1</label>
                                            <input type="text" class="form-control" id="validationCustom01"
                                                placeholder="Ex: 00:00">
                                        </div>

                                        <div class="label-input">
                                            <label for="validationCustom01" class="form-label">Hora de saida 1</label>
                                            <input type="text" class="form-control" id="validationCustom01"
                                                placeholder="Ex: 00:00">
                                        </div>
                                    </div>

                                    <div class="input-group">
                                        <div class="label-input">
                                            <label for="validationCustom01" class="form-label">Hora de entrada 2</label>
                                            <input type="text" class="form-control" id="validationCustom01"
                                                placeholder="Ex: 00:00">
                                        </div>

                                        <div class="label-input">
                                            <label for="validationCustom01" class="form-label">Hora de saida 2</label>
                                            <input type="text" class="form-control" id="validationCustom01"
                                                placeholder="Ex: 00:00">
                                        </div>
                                    </div>
                                </form>
                                <form action="" class="container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Dia</th>
                                                <th scope="col">1º Entrada</th>
                                                <th scope="col">1º Saida</th>
                                                <th scope="col">2º Entrada</th>
                                                <th scope="col">2º Saida</th>
                                            </tr>
                                        </thead>
                                        <tbody class="table-group-divider">
                                            <tr>
                                                <th scope="row">Dom</th>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Seg</th>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Ter</th>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Qua</th>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Qui</th>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Sex</th>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Sab</th>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </form>
                            </div>
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
    <script src="js\button-add.js"></script>
</body>

</html>