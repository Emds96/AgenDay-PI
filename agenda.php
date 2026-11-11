<?php
require_once __DIR__ . '/verifica_login.php';

// Esta página somente exibe dados. O POST autenticado é salvo por salvar-agendamento.php.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Use o formulário para criar agendamentos.');
}

require_once __DIR__ . '/lib/agendamentos.php';

$opcoesAgenda = ['clientes' => [], 'servicos' => [], 'funcionarios_servicos' => []];
$erroOpcoesAgenda = false;
try {
    require_once __DIR__ . '/conexao.php';
    $opcoesAgenda = agenday_agenda_opcoes($conexao);
} catch (Throwable $e) {
    error_log('AgenDay: falha ao carregar agendamento (' . get_class($e) . ').');
    $erroOpcoesAgenda = true;
}
$agendaTemClientes = $opcoesAgenda['clientes'] !== [];
$agendaTemServicos = $opcoesAgenda['servicos'] !== [];
$agendaTemVinculos = $opcoesAgenda['funcionarios_servicos'] !== [];
$agendaPodePreparar = !$erroOpcoesAgenda && $agendaTemClientes && $agendaTemServicos && $agendaTemVinculos;
?>
<!DOCTYPE html>
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
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/agenda.css">
    <link rel="stylesheet" href="css/agendamento-form.css">
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
        <!-- 4.3: formulário autenticado; validação final e gravação no servidor. -->
        <section class="container-fluid py-4" aria-labelledby="novo-agendamento-titulo">
            <div class="card shadow-sm agendamento-card mx-auto">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <h1 id="novo-agendamento-titulo" class="h4 mb-0">Novo agendamento</h1>
                        <span class="badge text-bg-primary">Agendamentos · Etapa 4.3</span>
                    </div>
                    <p class="text-muted mb-3">Escolha cliente, serviço, funcionário, data e horário para registrar um atendimento.</p>
                    <?php if (!empty($_SESSION['sucesso_agendamento'])): ?>
                        <div class="alert alert-success" role="status">
                            <?= htmlspecialchars((string) $_SESSION['sucesso_agendamento'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                    <?php endif; unset($_SESSION['sucesso_agendamento']); ?>
                    <?php if (!empty($_SESSION['erro_agendamento'])): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= htmlspecialchars((string) $_SESSION['erro_agendamento'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                        </div>
                    <?php endif; unset($_SESSION['erro_agendamento']); ?>
                    <div class="alert alert-info" role="note">
                        O sistema confere a disponibilidade ao salvar. O calendário abaixo ainda é ilustrativo.
                    </div>
                    <?php if ($erroOpcoesAgenda): ?>
                        <div class="alert alert-danger" role="alert">
                            Não foi possível carregar os dados do formulário. Tente novamente mais tarde.
                        </div>
                    <?php else: ?>
                        <?php if (!$agendaTemClientes): ?>
                            <p class="alert alert-warning mb-2">Primeiro cadastre um <a href="cad-cliente.php">cliente</a>.</p>
                        <?php endif; ?>
                        <?php if (!$agendaTemServicos): ?>
                            <p class="alert alert-warning mb-2">Primeiro cadastre um <a href="cad.servicos.php">serviço</a>.</p>
                        <?php endif; ?>
                        <?php if (!$agendaTemVinculos): ?>
                            <p class="alert alert-warning mb-2">
                                É necessário <a href="cad-funcionario.php">vincular um funcionário a um serviço</a>.
                            </p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <script id="agendamento-pares" type="application/json"><?= json_encode(
                        $opcoesAgenda['funcionarios_servicos'],
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
                    ) ?></script>
                    <form id="form-novo-agendamento" method="post" action="salvar-agendamento.php" autocomplete="off">
                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="agendamento-cliente">Cliente</label>
                                <select class="form-select" id="agendamento-cliente" name="id_cliente" required
                                    <?= !$agendaPodePreparar ? 'disabled' : '' ?>>
                                    <option value="">Selecione um cliente</option>
                                    <?php foreach ($opcoesAgenda['clientes'] as $cliente): ?>
                                        <option value="<?= (int) $cliente['id_cliente'] ?>">
                                            <?= htmlspecialchars((string) $cliente['nome_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="agendamento-servico">Serviço</label>
                                <select class="form-select" id="agendamento-servico" name="id_servicos" required
                                    <?= !$agendaPodePreparar ? 'disabled' : '' ?>>
                                    <option value="">Selecione um serviço</option>
                                    <?php foreach ($opcoesAgenda['servicos'] as $servico): ?>
                                        <option value="<?= (int) $servico['id_servicos'] ?>"
                                            data-duracao="<?= htmlspecialchars((string) $servico['duracao_servico'], ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string) $servico['nome_servico'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="agendamento-funcionario">Funcionário</label>
                                <select class="form-select" id="agendamento-funcionario" name="id_funcionario" required disabled>
                                    <option value="">Selecione o serviço primeiro</option>
                                </select>
                                <small class="form-text text-muted" id="agendamento-aviso-funcionario" aria-live="polite">
                                    Serão mostrados apenas funcionários vinculados ao serviço.
                                </small>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <label class="form-label" for="agendamento-data">Data</label>
                                <input type="date" class="form-control" id="agendamento-data"
                                    name="data" required <?= !$agendaPodePreparar ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-12 col-sm-6 col-md-3">
                                <label class="form-label" for="agendamento-hora">Horário de início</label>
                                <input type="time" class="form-control" id="agendamento-hora"
                                    name="hora" step="900" required <?= !$agendaPodePreparar ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="agendamento-observacoes">Observações (opcional)</label>
                                <textarea class="form-control" id="agendamento-observacoes" name="observacoes"
                                    rows="2" maxlength="1000" placeholder="Detalhes do atendimento"
                                    <?= !$agendaPodePreparar ? 'disabled' : '' ?>></textarea>
                            </div>
                        </div>
                        <div class="agendamento-resumo rounded-3 mt-3 p-3" id="agendamento-resumo" aria-live="polite">
                            Selecione os campos para visualizar a previsão do atendimento.
                        </div>
                        <div class="mt-3 d-flex flex-wrap gap-3 align-items-center">
                            <button type="submit" class="btn btn-primary" id="agendamento-salvar"
                                <?= !$agendaPodePreparar ? 'disabled' : '' ?>>Salvar agendamento</button>
                            <span class="text-muted small">A confirmação acontece após a validação no servidor.</span>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Calendário antigo: a integração real virá na 4.4. -->
        <section class="container-fluid" aria-label="Calendário ilustrativo">
            <p class="text-muted small mt-2 mb-2">Os horários e o calendário abaixo são ilustrativos. Ainda não representam a disponibilidade real.</p>
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
    <script src="js/agendamento-form.js"></script>

</body>

</html>