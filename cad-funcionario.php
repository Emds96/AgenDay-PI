<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/info-funcionario.php';
require_once __DIR__ . '/lib/cadastro-reenvio.php';
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
    <link rel="stylesheet" href="css/responsivo.css?v=20261119-ui10">
</head>

<body class="agenday-app">
    <header class="navbar navbar-light bg-light">
        <div class="container-fluid">
            <button class="navbar-toggler bg-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar" aria-label="Abrir menu principal">
                <span class="navbar-toggler-icon"></span>
            </button>
            <h3 class="ad-header-brand fw-bold text-center mb-0">
                AgenDay</h3>
        <?php require __DIR__ . '/lib/menu-lateral.php'; ?>
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
                                <form enctype="multipart/form-data" action="cadastro-funcionario.php" method="POST" data-agenday-cadastro>
                                    <input type="hidden" name="csrf_token"
                                        value="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="cadastro_token" value="<?= htmlspecialchars(agenday_cadastro_token_emitir('funcionarios'), ENT_QUOTES, 'UTF-8') ?>">
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
    <script src="js/cadastro-envio.js?v=20261119-antiduplo"></script>


    <footer>
        <div class="rodape">
        </div>
    </footer>
</body>

</html>