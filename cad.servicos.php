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
    <link rel="stylesheet" href="css/responsivo.css?v=20261118-ui09">
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
        <?php require __DIR__ . '/lib/menu-lateral.php'; ?>
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