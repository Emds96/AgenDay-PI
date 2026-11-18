<?php
declare(strict_types=1);
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/configuracao-estabelecimento.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo !== 'GET' && $metodo !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$idEstabelecimento = $_SESSION['id_estabelecimento'];
$alerta = null;
$dadosCarregados = false;
$nomeAtual = '';
$emailAtual = '';
$contatoAtual = '';

if (isset($_SESSION['feedback_config_estabelecimento'])
    && is_string($_SESSION['feedback_config_estabelecimento'])) {
    $alerta = ['tipo' => 'success', 'texto' => $_SESSION['feedback_config_estabelecimento']];
    unset($_SESSION['feedback_config_estabelecimento']);
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
        $alerta = ['tipo' => 'danger', 'texto' => 'Não foi possível encontrar os dados desta conta.'];
    }
} catch (Throwable $e) {
    error_log('AgenDay: erro ao consultar os dados de configuração do estabelecimento.');
    http_response_code(503);
    $alerta = ['tipo' => 'danger', 'texto' => 'Não foi possível carregar os dados agora. Tente novamente mais tarde.'];
}

$nomeAtual = (string) $nomeAtual;
$emailAtual = (string) $emailAtual;
$contatoAtual = (string) ($contatoAtual ?? '');
$nomeFormulario = $nomeAtual;
$contatoFormulario = $contatoAtual;

if ($metodo === 'POST') {
    $nomeFormulario = isset($_POST['nome_estabelecimento']) && is_string($_POST['nome_estabelecimento'])
        ? trim($_POST['nome_estabelecimento']) : '';
    $contatoFormulario = isset($_POST['contato_estabelecimento']) && is_string($_POST['contato_estabelecimento'])
        ? trim($_POST['contato_estabelecimento']) : '';

    if (!agenday_valida_csrf()) {
        http_response_code(403);
        $alerta = [
            'tipo' => 'danger',
            'texto' => 'Sessão de segurança expirada. Recarregue a página e tente novamente.',
        ];
    } elseif (!$dadosCarregados) {
        $alerta = ['tipo' => 'danger', 'texto' => 'Não foi possível salvar os dados desta conta.'];
    } else {
        $erroValidacao = agenday_config_validar($nomeFormulario, $contatoFormulario);
        if ($erroValidacao !== null) {
            $alerta = ['tipo' => 'danger', 'texto' => $erroValidacao];
        } else {
            try {
                $atualizacao = $conexao->prepare(
                    'UPDATE estabelecimento
                     SET nome_estabelecimento = ?, contato_estabelecimento = ?
                     WHERE id_estabelecimento = ?'
                );
                $atualizacao->bind_param('ssi', $nomeFormulario, $contatoFormulario, $idEstabelecimento);
                $atualizacao->execute();
                $linhasAlteradas = $atualizacao->affected_rows;
                $atualizacao->close();

                if ($linhasAlteradas === 1
                    || ($nomeFormulario === $nomeAtual && $contatoFormulario === $contatoAtual)) {
                    // A agenda e as páginas de cadastro leem o nome a partir da sessão.
                    $_SESSION['nome_estabelecimento'] = $nomeFormulario;
                    $_SESSION['feedback_config_estabelecimento'] = 'Dados atualizados com sucesso.';
                    header('Location: config.php', true, 303);
                    exit;
                }

                $alerta = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível confirmar a atualização. Recarregue e tente novamente.',
                ];
            } catch (Throwable $e) {
                error_log('AgenDay: falha ao atualizar os dados básicos do estabelecimento.');
                $alerta = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível salvar as alterações agora. Tente novamente.',
                ];
            }
        }
    }
}

$csrfToken = agenday_token_csrf();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações do estabelecimento | AgenDay</title>
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
    <link rel="stylesheet" href="css/responsivo.css">
    <style>
        .config-container { max-width: 980px; margin: 1.5rem auto 3rem; }
        .config-card { border: 1px solid #e4e8eb; border-radius: 1rem; overflow: hidden; }
        .config-card .card-body { padding: clamp(1rem, 4vw, 2rem); }
        .config-description { color: #5a6771; }
        .config-field-readonly { background: #f5f7f8; cursor: not-allowed; }
        .config-actions { display: flex; justify-content: flex-end; gap: .75rem; }
        .config-tabs { flex-wrap: wrap; gap: .35rem; }
        .config-tabs .nav-link { white-space: nowrap; }
        .config-navigation { width: min(82vw, 315px) !important; }
        @media (max-width: 576px) {
            .config-container { margin: .75rem auto 2rem; padding: 0 .7rem; }
            .config-card .card-body { padding: 1rem; }
            .config-actions .btn { width: 100%; }
            .config-tabs .nav-link { padding: .55rem .75rem; font-size: .92rem; }
        }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3"
            crossorigin="anonymous"></script>
</head>
<body class="agenday-app">
<header class="navbar navbar-light bg-light">
    <div class="container-fluid">
        <button class="navbar-toggler bg-light" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar"
                aria-label="Abrir menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <h3 class="ad-header-brand fw-bold text-center mb-0">AgenDay</h3>
        <?php require __DIR__ . '/includes/menu-conta.php'; ?>
        <div class="offcanvas offcanvas-start config-navigation" tabindex="-1"
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
<main class="container config-container">
    <div class="card config-card shadow-sm">
        <div class="card-header bg-white pt-3">
            <ul class="nav nav-tabs config-tabs" aria-label="Configurações da conta">
                <li class="nav-item"><a class="nav-link active" aria-current="page" href="config.php">Empresa</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="perfil.php">Perfil</a></li>
                <li class="nav-item"><a class="nav-link text-dark" href="alterar-senha.php">Alterar senha</a></li>
            </ul>
        </div>
        <div class="card-body">
            <div class="mb-4">
                <h1 class="h3 fw-bold mb-2">Dados do estabelecimento</h1>
                <p class="config-description mb-0">Consulte e atualize as informações básicas da sua empresa.</p>
            </div>

            <?php if ($alerta !== null): ?>
                <div class="alert alert-<?= agenday_config_html($alerta['tipo']) ?>" role="alert">
                    <?= agenday_config_html($alerta['texto']) ?>
                </div>
            <?php endif; ?>

            <form method="post" action="config.php" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                <div class="mb-3">
                    <label for="nomeEstabelecimento" class="form-label fw-semibold">Nome do estabelecimento</label>
                    <input id="nomeEstabelecimento" name="nome_estabelecimento" type="text"
                           class="form-control" maxlength="120" minlength="2" required
                           autocomplete="organization"
                           value="<?= agenday_config_html($nomeFormulario) ?>"
                           <?= !$dadosCarregados ? 'disabled' : '' ?>>
                    <div class="form-text">Este nome também aparecerá no menu das páginas administrativas.</div>
                </div>
                <div class="mb-3">
                    <label for="emailEstabelecimento" class="form-label fw-semibold">E-mail de acesso</label>
                    <input id="emailEstabelecimento" type="email" class="form-control config-field-readonly"
                           value="<?= agenday_config_html($emailAtual) ?>" readonly
                           autocomplete="email" aria-describedby="emailAjuda">
                    <div class="form-text" id="emailAjuda">O e-mail não pode ser alterado nesta etapa.</div>
                </div>
                <div class="mb-4">
                    <label for="contatoEstabelecimento" class="form-label fw-semibold">Telefone de contato</label>
                    <input id="contatoEstabelecimento" name="contato_estabelecimento" type="tel"
                           class="form-control" maxlength="20" inputmode="tel"
                           autocomplete="tel" placeholder="Ex.: (92) 99999-9999"
                           value="<?= agenday_config_html($contatoFormulario) ?>"
                           <?= !$dadosCarregados ? 'disabled' : '' ?>>
                    <div class="form-text">Opcional. Use 8 a 15 dígitos, com DDD quando aplicável.</div>
                </div>
                <div class="config-actions">
                    <button class="btn btn-primary px-4" type="submit" <?= !$dadosCarregados ? 'disabled' : '' ?>>
                        <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Salvar alterações
                    </button>
                </div>
            </form>
            <p class="text-muted small mt-4 mb-0">
                Endereço, CNPJ e horários de funcionamento terão configuração própria em uma etapa futura.
            </p>
        </div>
    </div>
</main>
<script src="js/menu-lateral.js"></script>
</body>
</html>
