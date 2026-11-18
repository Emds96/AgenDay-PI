<?php
declare(strict_types=1);
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/configuracao-estabelecimento.php';
require_once __DIR__ . '/lib/avatar-estabelecimento.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$abaSelecionada = ($_GET['aba'] ?? '') === 'conta' ? 'conta' : 'estabelecimento';
$alertaEstabelecimento = null;
$alertaConta = null;
$alertaSenha = null;
$reabrirModalSenha = false;
$dadosCarregados = false;
$nomeAtual = '';
$emailAtual = '';
$contatoAtual = '';

if (isset($_SESSION['feedback_config_estabelecimento'])
    && is_string($_SESSION['feedback_config_estabelecimento'])) {
    $alertaEstabelecimento = [
        'tipo' => 'success',
        'texto' => $_SESSION['feedback_config_estabelecimento'],
    ];
    unset($_SESSION['feedback_config_estabelecimento']);
}

if (isset($_SESSION['feedback_config_avatar']) && is_string($_SESSION['feedback_config_avatar'])) {
    $alertaConta = ['tipo' => 'success', 'texto' => $_SESSION['feedback_config_avatar']];
    unset($_SESSION['feedback_config_avatar']);
    $abaSelecionada = 'conta';
}

if (isset($_SESSION['feedback_alteracao_senha'])
    && is_array($_SESSION['feedback_alteracao_senha'])) {
    $alertaSenha = $_SESSION['feedback_alteracao_senha'];
    unset($_SESSION['feedback_alteracao_senha']);
    $abaSelecionada = 'conta';
    $reabrirModalSenha = ($alertaSenha['tipo'] ?? '') !== 'success';
}

try {
    require_once __DIR__ . '/conexao.php';
    $consulta = $conexao->prepare(
        'SELECT nome_estabelecimento, email_estabelecimento, contato_estabelecimento
         FROM estabelecimento WHERE id_estabelecimento = ? LIMIT 1'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $consulta->bind_result($nomeAtual, $emailAtual, $contatoAtual);
    $dadosCarregados = (bool) $consulta->fetch();
    $consulta->close();
    if (!$dadosCarregados) {
        http_response_code(403);
        $alertaEstabelecimento = ['tipo' => 'danger', 'texto' => 'Conta não encontrada.'];
        $alertaConta = $alertaEstabelecimento;
    }
} catch (Throwable $e) {
    error_log('AgenDay: falha ao consultar configurações da conta.');
    http_response_code(503);
    $alertaEstabelecimento = [
        'tipo' => 'danger',
        'texto' => 'Não foi possível carregar os dados agora. Tente novamente mais tarde.',
    ];
    $alertaConta = $alertaEstabelecimento;
}

$nomeAtual = (string) $nomeAtual;
$emailAtual = (string) $emailAtual;
$contatoAtual = (string) ($contatoAtual ?? '');
$nomeFormulario = $nomeAtual;
$contatoFormulario = $contatoAtual;

if ($metodo === 'POST') {
    $acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';
    $abaSelecionada = $acao === 'atualizar_foto' ? 'conta' : 'estabelecimento';

    if (!agenday_valida_csrf()) {
        http_response_code(403);
        $erro = ['tipo' => 'danger', 'texto' => 'Sessão de segurança expirada. Recarregue e tente novamente.'];
        if ($abaSelecionada === 'conta') {
            $alertaConta = $erro;
        } else {
            $alertaEstabelecimento = $erro;
        }
    } elseif (!$dadosCarregados) {
        $erro = ['tipo' => 'danger', 'texto' => 'Não foi possível validar esta conta.'];
        if ($abaSelecionada === 'conta') {
            $alertaConta = $erro;
        } else {
            $alertaEstabelecimento = $erro;
        }
    } elseif ($acao === 'salvar_estabelecimento') {
        $nomeFormulario = isset($_POST['nome_estabelecimento']) && is_string($_POST['nome_estabelecimento'])
            ? trim($_POST['nome_estabelecimento']) : '';
        $contatoFormulario = isset($_POST['contato_estabelecimento']) && is_string($_POST['contato_estabelecimento'])
            ? trim($_POST['contato_estabelecimento']) : '';
        $erroValidacao = agenday_config_validar($nomeFormulario, $contatoFormulario);

        if ($erroValidacao !== null) {
            $alertaEstabelecimento = ['tipo' => 'danger', 'texto' => $erroValidacao];
        } else {
            try {
                $atualizacao = $conexao->prepare(
                    'UPDATE estabelecimento
                     SET nome_estabelecimento = ?, contato_estabelecimento = ?
                     WHERE id_estabelecimento = ?'
                );
                $atualizacao->bind_param('ssi', $nomeFormulario, $contatoFormulario, $idEstabelecimento);
                $atualizacao->execute();
                $alterados = $atualizacao->affected_rows;
                $atualizacao->close();

                if ($alterados === 1
                    || ($nomeFormulario === $nomeAtual && $contatoFormulario === $contatoAtual)) {
                    $_SESSION['nome_estabelecimento'] = $nomeFormulario;
                    $_SESSION['feedback_config_estabelecimento'] = 'Alterações salvas com sucesso.';
                    header('Location: config.php?aba=estabelecimento', true, 303);
                    exit;
                }
                $alertaEstabelecimento = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível confirmar a alteração. Recarregue e tente novamente.',
                ];
            } catch (Throwable $e) {
                error_log('AgenDay: falha ao salvar dados do estabelecimento.');
                $alertaEstabelecimento = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível salvar as alterações agora. Tente novamente.',
                ];
            }
        }
    } elseif ($acao === 'atualizar_foto') {
        try {
            agenday_avatar_salvar($idEstabelecimento, $_FILES['foto_conta'] ?? null);
            $_SESSION['feedback_config_avatar'] = 'Foto da conta atualizada com sucesso.';
            header('Location: config.php?aba=conta', true, 303);
            exit;
        } catch (DomainException $e) {
            $alertaConta = ['tipo' => 'danger', 'texto' => $e->getMessage()];
        } catch (Throwable $e) {
            error_log('AgenDay: falha ao armazenar foto de estabelecimento.');
            $alertaConta = [
                'tipo' => 'danger',
                'texto' => 'Não foi possível salvar a foto agora. Tente novamente.',
            ];
        }
    } else {
        http_response_code(400);
        $alertaEstabelecimento = ['tipo' => 'danger', 'texto' => 'Ação desconhecida.'];
    }
}

$csrfToken = agenday_token_csrf();
$avatarContaUrl = agenday_avatar_url($idEstabelecimento);
$abaContaAtiva = $abaSelecionada === 'conta';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações | AgenDay</title>
    <link rel="icon" href="img/icon-agenda.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/agenda.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/box.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261118-ui08">
    <link rel="stylesheet" href="css/configuracoes-v2.css?v=20261118-ui08">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3"
            crossorigin="anonymous"></script>
    <script defer src="js/configuracoes.js?v=20261118-ui08"></script>
</head>
<body class="agenday-app">
<header class="navbar navbar-light bg-light">
    <div class="container-fluid">
        <button class="navbar-toggler bg-light" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar"
                aria-label="Abrir menu principal">
            <span class="navbar-toggler-icon"></span>
        </button>
        <h3 class="ad-header-brand fw-bold text-center mb-0">AgenDay</h3>
        <?php require __DIR__ . '/lib/menu-conta.php'; ?>
        <div class="offcanvas offcanvas-start ag-settings-navigation" tabindex="-1"
             id="offcanvasDarkNavbar" aria-labelledby="menuAgenDay">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="menuAgenDay">AgenDay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                        aria-label="Fechar menu"></button>
            </div>
            <div class="offcanvas-body">
                <nav class="navbar-nav">
                    <a class="nav-link fw-bold py-2" href="agenda.php"><i class="bi bi-calendar3 me-2"></i>Agenda</a>
                    <a class="nav-link fw-bold py-2" href="cad-cliente.php"><i class="bi bi-people me-2"></i>Clientes</a>
                    <a class="nav-link fw-bold py-2" href="cad.servicos.php"><i class="bi bi-card-list me-2"></i>Serviços</a>
                    <a class="nav-link fw-bold py-2" href="cad-funcionario.php"><i class="bi bi-person-badge me-2"></i>Funcionários</a>
                    <a class="nav-link fw-bold py-2 active" aria-current="page" href="config.php">
                        <i class="bi bi-gear me-2"></i>Configurações
                    </a>
                    <a class="nav-link fw-bold py-2" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a>
                </nav>
            </div>
        </div>
    </div>
</header>

<main class="container ag-settings">
    <div class="ag-settings-heading">
        <h1 class="fw-bold">Configurações</h1>
        <p>Gerencie os dados do estabelecimento e as informações da sua conta.</p>
    </div>

    <div class="ag-settings-panel">
        <nav class="ag-settings-tabbar" aria-label="Seções das configurações">
            <div class="nav nav-tabs ag-settings-tabs" id="abasConfiguracao" role="tablist">
                <button class="nav-link <?= !$abaContaAtiva ? 'active' : '' ?>" id="aba-estabelecimento"
                        type="button" role="tab" data-bs-toggle="tab" data-bs-target="#painel-estabelecimento"
                        aria-controls="painel-estabelecimento" aria-selected="<?= !$abaContaAtiva ? 'true' : 'false' ?>"
                        tabindex="<?= !$abaContaAtiva ? '0' : '-1' ?>">Estabelecimento</button>
                <button class="nav-link <?= $abaContaAtiva ? 'active' : '' ?>" id="aba-minha-conta"
                        type="button" role="tab" data-bs-toggle="tab" data-bs-target="#painel-minha-conta"
                        aria-controls="painel-minha-conta" aria-selected="<?= $abaContaAtiva ? 'true' : 'false' ?>"
                        tabindex="<?= $abaContaAtiva ? '0' : '-1' ?>">Minha Conta</button>
            </div>
        </nav>

        <div class="tab-content" id="conteudoConfiguracao">
            <section class="tab-pane fade <?= !$abaContaAtiva ? 'show active' : '' ?>" id="painel-estabelecimento"
                     role="tabpanel" aria-labelledby="aba-estabelecimento" tabindex="0">
                <div class="ag-settings-body">
                    <div class="ag-settings-section-title">
                        <h2>Dados do estabelecimento</h2>
                        <p>Informações exibidas no seu ambiente de agendamentos.</p>
                    </div>

                    <?php if ($alertaEstabelecimento !== null): ?>
                        <div class="alert alert-<?= $alertaEstabelecimento['tipo'] === 'success' ? 'success' : 'danger' ?>"
                             role="alert" aria-live="polite">
                            <?= agenday_config_html($alertaEstabelecimento['texto']) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="config.php?aba=estabelecimento" autocomplete="on" id="formEstabelecimento">
                        <input type="hidden" name="acao" value="salvar_estabelecimento">
                        <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                        <div class="mb-4">
                            <label for="nomeEstabelecimento" class="form-label fw-semibold">Nome do estabelecimento</label>
                            <input id="nomeEstabelecimento" name="nome_estabelecimento" type="text"
                                   class="form-control" maxlength="120" minlength="2" required
                                   autocomplete="organization" value="<?= agenday_config_html($nomeFormulario) ?>"
                                   <?= !$dadosCarregados ? 'disabled' : '' ?>>
                            <div class="form-text">Esse nome também aparecerá no cabeçalho do sistema.</div>
                        </div>
                        <div class="mb-4">
                            <label for="contatoEstabelecimento" class="form-label fw-semibold">Telefone comercial</label>
                            <input id="contatoEstabelecimento" name="contato_estabelecimento" type="tel"
                                   class="form-control" maxlength="20" inputmode="tel" autocomplete="tel"
                                   placeholder="(92) 99999-9999" aria-describedby="telefoneAjuda"
                                   value="<?= agenday_config_html($contatoFormulario) ?>"
                                   <?= !$dadosCarregados ? 'disabled' : '' ?>>
                            <div class="form-text" id="telefoneAjuda">Opcional. Informe de 8 a 15 dígitos. Números brasileiros com DDD são formatados automaticamente.</div>
                        </div>
                        <div class="ag-settings-actions">
                            <button class="btn btn-primary" type="submit" <?= !$dadosCarregados ? 'disabled' : '' ?>>
                                <i class="bi bi-check2 me-1" aria-hidden="true"></i>Salvar alterações
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="tab-pane fade <?= $abaContaAtiva ? 'show active' : '' ?>" id="painel-minha-conta"
                     role="tabpanel" aria-labelledby="aba-minha-conta" tabindex="0">
                <div class="ag-settings-body">
                    <div class="ag-settings-section-title">
                        <h2>Minha Conta</h2>
                        <p>Personalize a imagem e cuide da segurança do seu acesso.</p>
                    </div>

                    <?php if ($alertaConta !== null): ?>
                        <div class="alert alert-<?= $alertaConta['tipo'] === 'success' ? 'success' : 'danger' ?>"
                             role="alert" aria-live="polite">
                            <?= agenday_config_html($alertaConta['texto']) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($alertaSenha !== null && !$reabrirModalSenha): ?>
                        <div class="alert alert-<?= ($alertaSenha['tipo'] ?? '') === 'success' ? 'success' : 'danger' ?>"
                             role="alert" aria-live="polite">
                            <?= agenday_config_html(is_string($alertaSenha['texto'] ?? null) ? $alertaSenha['texto'] : '') ?>
                        </div>
                    <?php endif; ?>

                    <div class="ag-settings-group">
                        <h3>Foto da conta</h3>
                        <p class="ag-settings-help">Use a logo ou uma imagem que represente seu estabelecimento.</p>
                        <div class="ag-settings-photo-row">
                            <span class="ag-settings-photo" aria-label="Imagem atual da conta">
                                <?php if ($avatarContaUrl !== null): ?>
                                    <img src="<?= agenday_config_html($avatarContaUrl) ?>"
                                         alt="Foto atual do estabelecimento">
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" aria-hidden="true" focusable="false">
                                        <circle cx="32" cy="32" r="31" fill="#E7F5F2"/>
                                        <circle cx="32" cy="24" r="11" fill="#18544D"/>
                                        <path d="M12 53c2-13 9-19 20-19s18 6 20 19" fill="#18544D"/>
                                    </svg>
                                <?php endif; ?>
                            </span>
                            <form method="post" action="config.php?aba=conta" enctype="multipart/form-data"
                                  id="formFotoConta" class="ag-settings-photo-form">
                                <input type="hidden" name="acao" value="atualizar_foto">
                                <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                                <label for="fotoConta" class="form-label fw-semibold">Adicionar ou trocar foto</label>
                                <input id="fotoConta" name="foto_conta" type="file" class="form-control"
                                       accept=".jpg,.jpeg,.png,image/jpeg,image/png" required
                                       aria-describedby="fotoContaAjuda"
                                       <?= !$dadosCarregados ? 'disabled' : '' ?>>
                                <div id="fotoContaAjuda" class="form-text">JPG ou PNG, até 2 MB, máximo de 4096 × 4096 pixels.</div>
                                <button class="btn btn-outline-primary mt-3" type="submit"
                                        <?= !$dadosCarregados ? 'disabled' : '' ?>>
                                    <i class="bi bi-image me-1" aria-hidden="true"></i>Salvar foto
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="ag-settings-group">
                        <h3>E-mail de acesso</h3>
                        <label for="emailEstabelecimento" class="form-label fw-semibold">E-mail cadastrado</label>
                        <input id="emailEstabelecimento" type="email" class="form-control ag-settings-readonly"
                               value="<?= agenday_config_html($emailAtual) ?>" readonly autocomplete="email"
                               aria-describedby="emailContaAjuda">
                        <div class="form-text" id="emailContaAjuda">
                            <i class="bi bi-lock me-1" aria-hidden="true"></i>Por segurança, este e-mail não pode ser alterado.
                        </div>
                    </div>

                    <div class="ag-settings-group ag-settings-security">
                        <div>
                            <h3>Segurança</h3>
                            <p class="ag-settings-help mb-0">Atualize sua senha para manter a conta protegida.</p>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="abrirModalSenha"
                                data-bs-toggle="modal" data-bs-target="#modalAlterarSenha"
                                <?= !$dadosCarregados ? 'disabled' : '' ?>>Alterar senha</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<div class="modal fade" id="modalAlterarSenha" tabindex="-1"
     aria-labelledby="tituloModalSenha" aria-describedby="descricaoModalSenha"
     aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true"
     data-reabrir-erro="<?= $reabrirModalSenha ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content ag-settings-password-modal">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="tituloModalSenha">Alterar senha</h2>
                <button type="button" class="btn-close ag-modal-dismiss"
                        data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="alterar-senha.php" id="formAlterarSenha">
                <div class="modal-body">
                    <p id="descricaoModalSenha" class="ag-settings-help">Informe sua senha atual e escolha uma nova senha.</p>
                    <?php if ($reabrirModalSenha && $alertaSenha !== null): ?>
                        <div class="alert alert-danger" role="alert">
                            <?= agenday_config_html(is_string($alertaSenha['texto'] ?? null) ? $alertaSenha['texto'] : '') ?>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="senha-atual">Senha atual</label>
                        <input class="form-control" type="password" id="senha-atual" name="senha_atual"
                               autocomplete="current-password" required maxlength="4096">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="nova-senha">Nova senha</label>
                        <input class="form-control" type="password" id="nova-senha" name="nova_senha"
                               autocomplete="new-password" required minlength="8" maxlength="72"
                               aria-describedby="ajudaNovaSenha">
                        <div class="form-text" id="ajudaNovaSenha">Use de 8 a 72 bytes e não repita a senha atual.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold" for="confirmar-nova-senha">Confirmar nova senha</label>
                        <input class="form-control" type="password" id="confirmar-nova-senha"
                               name="confirmar_nova_senha" autocomplete="new-password"
                               required minlength="8" maxlength="72">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary ag-modal-dismiss"
                            data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="salvarNovaSenha">Salvar senha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="js/menu-lateral.js"></script>
</body>
</html>
