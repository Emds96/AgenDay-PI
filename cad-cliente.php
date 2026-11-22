<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/info-clientes.php';
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
    <link rel="stylesheet" href="css/cad_cliente.css" />

    <!-- ICONS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <!-- AgenDay 2.0: tokens e componentes visuais padronizados (5.1) -->
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
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
                    <h2 class="text-center">Clientes</h2>
                </div>

                <?php if (!empty($_SESSION['sucesso_cliente'])): ?>
                    <div class="alert alert-success mx-3 mt-3" role="status"><?= htmlspecialchars(is_string($_SESSION['sucesso_cliente']) ? $_SESSION['sucesso_cliente'] : 'Cliente cadastrado com sucesso!', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
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

                <div class="modal" tabindex="-1" id="exampleModal" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class=" modal-title m-2">Cadastrar Clientes</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body">
                                <form enctype="multipart/form-data" action="cadastro-clientes.php" method="POST" data-agenday-cadastro>
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="cadastro_token" value="<?= htmlspecialchars(agenday_cadastro_token_emitir('clientes'), ENT_QUOTES, 'UTF-8') ?>">
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
                                        <div class="col-6 mb-3">
                                            <div data-confirmar-telefone>
                                                <input type="tel" class="form-control rounded-3 shadow" placeholder="Celular com WhatsApp"
                                                       name="contato" id="contatoCliente" maxlength="20" required
                                                       data-telefone-campo aria-describedby="contatoAjuda">
                                                <div class="form-text" id="contatoAjuda">Confirmamos o número por código no WhatsApp.</div>
                                                <div class="d-flex gap-2 mt-1">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" data-enviar-codigo>
                                                        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Confirmar no WhatsApp
                                                    </button>
                                                </div>
                                                <div class="mt-2" data-codigo-area hidden>
                                                    <label class="form-label small" for="codigoTelefone">Código recebido</label>
                                                    <div class="d-flex gap-2">
                                                        <input type="text" class="form-control form-control-sm" id="codigoTelefone"
                                                               maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                                                               data-codigo-campo aria-label="Código recebido no WhatsApp">
                                                        <button type="button" class="btn btn-primary btn-sm" data-confirmar-codigo>Confirmar</button>
                                                    </div>
                                                </div>
                                                <p class="form-text" data-status role="status" aria-live="polite"></p>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="sem_whatsapp" value="1"
                                                           id="semWhatsapp" data-sem-whatsapp>
                                                    <label class="form-check-label small" for="semWhatsapp">Este cliente não tem WhatsApp</label>
                                                </div>
                                            </div>
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

                <div class="modal" tabindex="-1" id="modalEditarCliente" aria-labelledby="modalEditarClienteLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h3 class="modal-title m-2" id="modalEditarClienteLabel">Editar cliente</h3>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>

                            <div class="modal-body">
                                <form enctype="multipart/form-data" action="editar-cliente.php" method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id_cliente" id="editar-id" value="">
                                    <div class="row">
                                        <div class="mb-3 col-7">
                                            <input type="text" class="form-control rounded-3 shadow" placeholder="Nome do cliente" id="editar-nome" name="nome" maxlength="120" aria-label="Nome do cliente" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-7 mb-3">
                                            <input type="email" class="form-control rounded-3 shadow" placeholder="E-mail (opcional)" id="editar-email" name="email" maxlength="190" aria-label="E-mail do cliente (opcional)">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-6 mb-3">
                                            <div data-confirmar-telefone>
                                                <input type="tel" class="form-control rounded-3 shadow" placeholder="Celular com WhatsApp"
                                                       id="editar-contato" name="contato" maxlength="20"
                                                       aria-label="Contato do cliente" required data-telefone-campo>
                                                <div class="d-flex gap-2 mt-1">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" data-enviar-codigo>
                                                        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Confirmar no WhatsApp
                                                    </button>
                                                </div>
                                                <div class="mt-2" data-codigo-area hidden>
                                                    <label class="form-label small" for="editar-codigo">Código recebido</label>
                                                    <div class="d-flex gap-2">
                                                        <input type="text" class="form-control form-control-sm" id="editar-codigo"
                                                               maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                                                               data-codigo-campo aria-label="Código recebido no WhatsApp">
                                                        <button type="button" class="btn btn-primary btn-sm" data-confirmar-codigo>Confirmar</button>
                                                    </div>
                                                </div>
                                                <p class="form-text" data-status role="status" aria-live="polite"></p>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="sem_whatsapp" value="1"
                                                           id="editar-sem-whatsapp" data-sem-whatsapp>
                                                    <label class="form-check-label small" for="editar-sem-whatsapp">Este cliente não tem WhatsApp</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="input-group">
                                        <input type="file" class="form-control shadow" id="editar-arquivo" name="arquivo"
                                            accept="image/jpeg,image/png" aria-label="Nova foto do cliente (opcional)">
                                    </div>
                                    <small class="text-muted" id="editar-foto-atual">A foto atual será mantida se você não escolher outra.</small>
                                    <div class="float-end m-3">
                                        <button type="submit" class="concluir rounded-3 border shadow">Salvar</button>
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
                                        <?php $faltasCliente = (int) ($cliente['faltas'] ?? 0); ?>
                                        <?php $cancelamentosCliente = (int) ($cliente['cancelamentos'] ?? 0); ?>
                                        <?php if ($faltasCliente > 0 || $cancelamentosCliente > 0): ?>
                                            <div class="mt-1 d-flex flex-wrap gap-1" data-historico-ausencias>
                                                <?php if ($faltasCliente > 0): ?>
                                                    <span class="badge text-bg-danger">
                                                        <?= $faltasCliente ?> <?= $faltasCliente === 1 ? 'falta' : 'faltas' ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($cancelamentosCliente > 0): ?>
                                                    <span class="badge text-bg-secondary">
                                                        <?= $cancelamentosCliente ?> <?= $cancelamentosCliente === 1 ? 'cancelamento' : 'cancelamentos' ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-auto d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center gap-2 pe-2 ag-acoes-registro">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#modalEditarCliente"
                                            data-id="<?= (int) $cliente['id_cliente'] ?>"
                                            data-nome="<?= htmlspecialchars((string) $cliente['nome_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                            data-email="<?= htmlspecialchars((string) ($cliente['email_cliente'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                            data-contato="<?= htmlspecialchars((string) $cliente['contato_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                        data-sem-whatsapp="<?= empty($cliente['telefone_digits']) ? '1' : '0' ?>"
                                            data-foto="<?= htmlspecialchars((string) ($cliente['arquivo_cliente'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                            aria-label="Editar cliente <?= htmlspecialchars((string) $cliente['nome_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                            <span class="d-none d-sm-inline ms-1">Editar</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#modalExcluirCliente"
                                            data-id="<?= (int) $cliente['id_cliente'] ?>"
                                            data-nome="<?= htmlspecialchars((string) $cliente['nome_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                                            aria-label="Excluir cliente <?= htmlspecialchars((string) $cliente['nome_cliente'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                            <span class="d-none d-sm-inline ms-1">Excluir</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade ag-modal-excluir" id="modalExcluirCliente" tabindex="-1"
         aria-labelledby="modalExcluirClienteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="modalExcluirClienteLabel">Excluir cliente</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja excluir <strong data-excluir-nome>este cliente</strong>?</p>
                    <p class="text-muted mb-0">Essa ação não pode ser desfeita.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="post" action="excluir-cliente.php" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id_cliente" value="" data-excluir-id>
                        <button type="submit" class="btn btn-danger" data-excluir-confirmar>Excluir</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <section class="overlay"></section>
    <script src="js\menu-lateral.js"></script>
    <script src="js/cadastro-envio.js?v=20261119-antiduplo"></script>
    <script src="js/excluir-registro.js?v=20261120-01"></script>
    <script>
        // Preenche o modal de edição com os dados da linha clicada.
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('modalEditarCliente');
            if (!modal) {
                return;
            }
            modal.addEventListener('show.bs.modal', function (evento) {
                var botao = evento.relatedTarget;
                if (!botao) {
                    return;
                }
                document.getElementById('editar-id').value = botao.getAttribute('data-id') || '';
                document.getElementById('editar-nome').value = botao.getAttribute('data-nome') || '';
                document.getElementById('editar-email').value = botao.getAttribute('data-email') || '';
                document.getElementById('editar-contato').value = botao.getAttribute('data-contato') || '';
                var caixaSemWhatsapp = document.getElementById('editar-sem-whatsapp');
                if (caixaSemWhatsapp) {
                    caixaSemWhatsapp.checked = botao.getAttribute('data-sem-whatsapp') === '1';
                    caixaSemWhatsapp.dispatchEvent(new Event('change'));
                }
                document.getElementById('editar-contato').dispatchEvent(new Event('input'));
                document.getElementById('editar-arquivo').value = '';
                document.getElementById('editar-foto-atual').textContent = botao.getAttribute('data-foto')
                    ? 'A foto atual será mantida se você não escolher outra.'
                    : 'Sem foto cadastrada.';
            });
        });
    </script>

    <script src="js/confirmar-telefone.js"></script>

    <footer>
        <div class="rodape">
        </div>
    </footer>
</body>

</html>