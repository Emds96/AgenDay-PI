<?php
/**
 * AgenDay 2.0 — conexão do WhatsApp da plataforma.
 *
 * O número é único da plataforma: um canal atende todos os estabelecimentos.
 * Por isso esta tela fica FORA das Configurações do estabelecimento — lá fica
 * só o que é do estabelecimento (exigir código de acesso e link do portal).
 *
 * Para salvar, é preciso confirmar a chave de API atual: quem não é o operador
 * da plataforma não consegue alterar a conexão usada por todos. A chave nunca é
 * exibida nem devolvida ao navegador.
 */
declare(strict_types=1);

require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/whatsapp.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$esc = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$alerta = null;
$config = [
    'ativo' => false,
    'base_url' => '',
    'api_key' => '',
    'sessao' => 'default',
    'ultima_execucao' => null,
];
$pendentes = 0;
$disponivel = false;

try {
    require_once __DIR__ . '/conexao.php';
    $config = agenday_whatsapp_config($conexao);
    $pendentes = agenday_whatsapp_pendentes($conexao);
    $disponivel = true;
} catch (Throwable $e) {
    error_log('AgenDay WhatsApp: configuração da plataforma indisponível (' . get_class($e) . ').');
    http_response_code(503);
    $alerta = [
        'tipo' => 'danger',
        'texto' => 'Não foi possível carregar a configuração agora. Tente novamente mais tarde.',
    ];
}

if ($metodo === 'POST' && $disponivel) {
    if (!agenday_valida_csrf()) {
        http_response_code(403);
        $alerta = ['tipo' => 'danger', 'texto' => 'Sessão de segurança expirada. Recarregue e tente novamente.'];
    } else {
        $acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';

        if ($acao === 'salvar') {
            $ativoNovo = isset($_POST['whatsapp_ativo']) && $_POST['whatsapp_ativo'] === '1';
            $baseNova = isset($_POST['whatsapp_base_url']) && is_string($_POST['whatsapp_base_url'])
                ? trim($_POST['whatsapp_base_url'])
                : '';
            $chaveNova = isset($_POST['whatsapp_api_key']) && is_string($_POST['whatsapp_api_key'])
                ? trim($_POST['whatsapp_api_key'])
                : '';
            $sessaoNova = isset($_POST['whatsapp_sessao']) && is_string($_POST['whatsapp_sessao'])
                ? trim($_POST['whatsapp_sessao'])
                : 'default';
            $chaveConfirmada = isset($_POST['chave_confirmacao']) && is_string($_POST['chave_confirmacao'])
                ? trim($_POST['chave_confirmacao'])
                : '';

            // Autorização: sem chave salva ainda (primeira configuração) ou com a chave atual.
            $chaveAtual = (string) $config['api_key'];
            $autorizado = $chaveAtual === ''
                || ($chaveConfirmada !== '' && hash_equals($chaveAtual, $chaveConfirmada));

            $erros = [];
            if (!$autorizado) {
                $erros[] = 'Confirme a chave de API atual para alterar a conexão da plataforma.';
            }
            if ($baseNova !== '' && !preg_match('~\Ahttps?://[^\s/]+\z~i', $baseNova)) {
                $erros[] = 'O endereço do WAHA deve começar com https:// e não ter caminho no final.';
            }
            if ($sessaoNova === '' || !preg_match('/\A[A-Za-z0-9._-]{1,40}\z/', $sessaoNova)) {
                $erros[] = 'O nome da sessão deve ter até 40 caracteres (letras, números, ponto, hífen).';
            }
            if ($ativoNovo && $baseNova === '') {
                $erros[] = 'Informe o endereço do WAHA para ativar o envio.';
            }
            if ($ativoNovo && $chaveNova === '' && $chaveAtual === '') {
                $erros[] = 'Informe a chave de API do WAHA para ativar o envio.';
            }

            if ($erros !== []) {
                $alerta = ['tipo' => 'danger', 'texto' => implode(' ', $erros)];
            } else {
                try {
                    agenday_whatsapp_salvar_config($conexao, $ativoNovo, $baseNova, $chaveNova, $sessaoNova);
                    $config = agenday_whatsapp_config($conexao);
                    $alerta = ['tipo' => 'success', 'texto' => 'Conexão da plataforma salva.'];
                } catch (Throwable $e) {
                    error_log('AgenDay WhatsApp: falha ao salvar configuração da plataforma (' . get_class($e) . ').');
                    $alerta = ['tipo' => 'danger', 'texto' => 'Não foi possível salvar a configuração agora. Tente novamente.'];
                }
            }
        } elseif ($acao === 'testar') {
            try {
                $teste = agenday_whatsapp_testar($config);
                $alerta = $teste['ok']
                    ? ['tipo' => 'success', 'texto' => 'Conexão com o WhatsApp funcionando.']
                    : ['tipo' => 'danger', 'texto' => $teste['erro']];
            } catch (Throwable $e) {
                error_log('AgenDay WhatsApp: falha ao testar conexão (' . get_class($e) . ').');
                $alerta = ['tipo' => 'danger', 'texto' => 'Não foi possível testar agora.'];
            }
        } elseif ($acao === 'enviar_pendentes') {
            try {
                $resultado = agenday_whatsapp_processar_fila($conexao, 5);
                if (!$resultado['ok']) {
                    $alerta = ['tipo' => 'danger', 'texto' => 'O WhatsApp não está configurado ou ativo.'];
                } elseif ($resultado['motivo'] === 'aguardando') {
                    $alerta = [
                        'tipo' => 'warning',
                        'texto' => 'A fila foi processada há menos de um minuto. Aguarde e tente novamente.',
                    ];
                } else {
                    $alerta = [
                        'tipo' => 'success',
                        'texto' => 'Mensagens enviadas: ' . (int) $resultado['enviadas']
                            . '. Falhas: ' . (int) $resultado['falhas'] . '.',
                    ];
                }
                $pendentes = agenday_whatsapp_pendentes($conexao);
            } catch (Throwable $e) {
                error_log('AgenDay WhatsApp: falha ao enviar pendentes (' . get_class($e) . ').');
                $alerta = ['tipo' => 'danger', 'texto' => 'Não foi possível processar a fila agora.'];
            }
        } else {
            http_response_code(400);
            $alerta = ['tipo' => 'danger', 'texto' => 'Ação desconhecida.'];
        }
    }
}

$csrfToken = agenday_token_csrf();
$chaveSalva = (string) $config['api_key'] !== '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp da plataforma | AgenDay</title>
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
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/configuracoes-v2.css?v=20261120-ui16">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3"
            crossorigin="anonymous"></script>
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
        <?php require __DIR__ . '/lib/menu-lateral.php'; ?>
    </div>
</header>

<main class="container ag-settings">
    <div class="ag-settings-heading">
        <h1 class="fw-bold">WhatsApp da plataforma</h1>
        <p>Uma única conexão atende todos os estabelecimentos. Cada mensagem sai com o nome do estabelecimento na assinatura.</p>
    </div>

    <div class="ag-settings-panel">
        <div class="ag-settings-body">
            <?php if ($alerta !== null): ?>
                <div class="alert alert-<?= $alerta['tipo'] === 'success' ? 'success' : ($alerta['tipo'] === 'warning' ? 'warning' : 'danger') ?>"
                     role="alert" aria-live="polite">
                    <?= $esc($alerta['texto']) ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-info" role="note">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                Esta conexão é da plataforma, não de um estabelecimento. Alterar aqui muda o envio de
                <strong>todos</strong> os estabelecimentos.
            </div>

            <dl class="row small mb-4">
                <dt class="col-sm-4">Situação</dt>
                <dd class="col-sm-8">
                    <?= !empty($config['ativo']) ? 'Envio ativo' : 'Envio desligado' ?>
                    · <?= $chaveSalva ? 'chave salva' : 'sem chave' ?>
                    · <?= (int) $pendentes ?> mensagem(ns) na fila
                </dd>
                <dt class="col-sm-4">Endereço</dt>
                <dd class="col-sm-8"><?= $config['base_url'] !== '' ? $esc($config['base_url']) : 'não definido' ?></dd>
                <dt class="col-sm-4">Sessão</dt>
                <dd class="col-sm-8"><?= $esc($config['sessao']) ?></dd>
            </dl>

            <form method="post" action="whatsapp-plataforma.php" class="row g-3" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="acao" value="salvar">

                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="whatsapp-ativo" name="whatsapp_ativo" value="1"
                               <?= !empty($config['ativo']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="whatsapp-ativo">
                            Enviar mensagens pelo WhatsApp
                        </label>
                    </div>
                    <p class="form-text">
                        Enquanto estiver desligado, os agendamentos continuam funcionando normalmente,
                        mas nenhuma mensagem é enviada.
                    </p>
                </div>

                <div class="col-12 col-md-8">
                    <label class="form-label" for="whatsapp-base-url">Endereço do WAHA</label>
                    <input type="url" class="form-control" id="whatsapp-base-url" name="whatsapp_base_url"
                           placeholder="https://exemplo.ngrok-free.app"
                           value="<?= $esc($config['base_url']) ?>">
                    <p class="form-text">Endereço HTTPS do túnel, sem barra no final.</p>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label" for="whatsapp-sessao">Sessão</label>
                    <input type="text" class="form-control" id="whatsapp-sessao" name="whatsapp_sessao"
                           maxlength="40" value="<?= $esc($config['sessao']) ?>">
                    <p class="form-text">Normalmente <code>default</code>.</p>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="whatsapp-api-key">Nova chave de API</label>
                    <input type="password" class="form-control" id="whatsapp-api-key" name="whatsapp_api_key"
                           autocomplete="new-password" placeholder="Deixe em branco para manter a atual">
                    <p class="form-text">
                        A chave fica guardada no banco e nunca é exibida novamente.
                        <?= $chaveSalva ? 'Já existe uma chave salva.' : 'Nenhuma chave salva ainda.' ?>
                    </p>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label" for="chave-confirmacao">Chave de API atual</label>
                    <input type="password" class="form-control" id="chave-confirmacao" name="chave_confirmacao"
                           autocomplete="current-password"
                           <?= $chaveSalva ? 'required' : 'disabled' ?>>
                    <p class="form-text">
                        <?= $chaveSalva
                            ? 'Confirma que é o operador da plataforma. Sem ela, a conexão de todos não muda.'
                            : 'Primeira configuração: ainda não há chave salva.' ?>
                    </p>
                </div>

                <div class="col-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Salvar conexão</button>
                    <a class="btn btn-outline-secondary" href="config.php">Voltar para Configurações</a>
                </div>
            </form>

            <hr class="my-4">

            <form method="post" action="whatsapp-plataforma.php" class="d-flex flex-wrap align-items-center gap-2">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="acao" value="testar">
                <button type="submit" class="btn btn-outline-primary">Testar conexão</button>
                <span class="text-muted small">Mensagens na fila: <?= (int) $pendentes ?></span>
            </form>

            <form method="post" action="whatsapp-plataforma.php" class="mt-2">
                <input type="hidden" name="csrf_token" value="<?= $esc($csrfToken) ?>">
                <input type="hidden" name="acao" value="enviar_pendentes">
                <button type="submit" class="btn btn-outline-secondary btn-sm">Enviar pendentes agora</button>
            </form>

            <details class="mt-4">
                <summary class="fw-bold">Como preparar o envio (uma vez)</summary>
                <ol class="mt-2 small text-muted">
                    <li>Instale o Docker Desktop e rode o WAHA no computador da plataforma.</li>
                    <li>Abra o painel do WAHA e conecte o número em <em>Aparelhos conectados</em> do WhatsApp.</li>
                    <li>Crie o túnel HTTPS com o ngrok e copie o endereço gerado.</li>
                    <li>Cole o endereço e a chave de API acima, ative e clique em Testar conexão.</li>
                </ol>
            </details>
        </div>
    </div>
</main>
</body>
</html>
