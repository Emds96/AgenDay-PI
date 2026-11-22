<?php
require_once __DIR__ . '/verifica_login.php';

// Esta página somente exibe dados. O POST autenticado é salvo por salvar-agendamento.php.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Use o formulário para criar agendamentos.');
}

require_once __DIR__ . '/lib/agendamentos.php';
require_once __DIR__ . '/lib/whatsapp.php';
require_once __DIR__ . '/lib/primeiros-passos.php';

$opcoesAgenda = ['clientes' => [], 'servicos' => [], 'funcionarios_servicos' => []];
$erroOpcoesAgenda = false;
try {
    require_once __DIR__ . '/conexao.php';
    $opcoesAgenda = agenday_agenda_opcoes($conexao);
} catch (Throwable $e) {
    error_log('AgenDay: falha ao carregar agendamento (' . get_class($e) . ').');
    $erroOpcoesAgenda = true;
}
if (isset($conexao) && $conexao instanceof mysqli) {
    // Sem cron: aproveita a abertura da agenda para esvaziar a fila.
    agenday_whatsapp_gatilho_apos_resposta($conexao);
}

// PRIMEIROS-PASSOS-01: enquanto a configuração básica não termina, a agenda
// mostra o que falta. É o primeiro contato de quem acabou de criar a conta.
$primeirosPassos = null;
$primeirosPassosProximo = '';
if (isset($conexao) && $conexao instanceof mysqli) {
    $segmentoPrimeirosPassos = isset($_SESSION['agenday_primeiros_passos_segmento'])
        && is_string($_SESSION['agenday_primeiros_passos_segmento'])
        ? $_SESSION['agenday_primeiros_passos_segmento']
        : agenday_primeiros_passos_segmento_do_cookie((int) $_SESSION['id_estabelecimento']);
    $primeirosPassos = agenday_primeiros_passos_estado(
        $conexao,
        (int) $_SESSION['id_estabelecimento'],
        $segmentoPrimeirosPassos
    );
    if ($primeirosPassos !== null) {
        foreach ($primeirosPassos['itens'] as $passo) {
            if (!$passo['feito']) {
                $primeirosPassosProximo = (string) $passo['titulo'];
                break;
            }
        }
    }
}
// Data do último agendamento salvo, validada pelo backend (uso único na interface).
$agendaDiaInicial = $_SESSION['agenda_dia_recem_salvo'] ?? '';
unset($_SESSION['agenda_dia_recem_salvo']);
if (!is_string($agendaDiaInicial) || !preg_match('/\\A\\d{4}-\\d{2}-\\d{2}\\z/D', $agendaDiaInicial)) {
    $agendaDiaInicial = '';
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
    <link rel="stylesheet" href="css/agenda-calendario.css">
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
        <?php if ($primeirosPassos !== null && !$primeirosPassos['completo']): ?>
            <section class="container-fluid pt-4 pb-0" aria-labelledby="primeiros-passos-titulo">
                <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-3 mb-0"
                     role="status">
                    <div>
                        <h2 id="primeiros-passos-titulo" class="h6 mb-1">
                            Sua agenda ainda não está pronta
                            (<?= (int) $primeirosPassos['concluidos'] ?> de <?= (int) $primeirosPassos['total'] ?>)
                        </h2>
                        <p class="mb-0 small">
                            Próximo passo: <strong><?= htmlspecialchars($primeirosPassosProximo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong>.
                            Leva uns 5 minutos e destrava os agendamentos.
                        </p>
                    </div>
                    <a class="btn btn-primary" href="primeiros-passos.php">
                        Continuar configuração
                    </a>
                </div>
            </section>
        <?php endif; ?>
        <!-- 4.3: formulário autenticado; validação final e gravação no servidor. -->
                <!-- O agendamento tem tela própria (AGD-01); a agenda apenas consulta. -->
        <section class="container-fluid py-4" aria-labelledby="novo-agendamento-titulo">
            <div class="card shadow-sm agendamento-card mx-auto">
                <div class="card-body p-3 p-md-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <h1 id="novo-agendamento-titulo" class="h4 mb-1">Novo agendamento</h1>
                        <p class="text-muted mb-0">Escolha serviço, profissional e um horário livre.</p>
                    </div>
                    <a class="btn btn-primary" href="agendar.php">
                        <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>
                        Agendar
                    </a>
                </div>
            </div>
        </section>
<!-- Microtarefa 4.4: agendamentos reais via API protegida por sessão. -->
        <section class="container-fluid py-3 pb-4 agenda-consulta" aria-labelledby="agenda-calendario-titulo">
            <?php if (!empty($_SESSION['sucesso_cancelamento'])): ?>
                <div class="alert alert-success" role="status">
                    <?= htmlspecialchars((string) $_SESSION['sucesso_cancelamento'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
            <?php endif; unset($_SESSION['sucesso_cancelamento']); ?>
            <?php if (!empty($_SESSION['erro_cancelamento'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?= htmlspecialchars((string) $_SESSION['erro_cancelamento'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                </div>
            <?php endif; unset($_SESSION['erro_cancelamento']); ?>
            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <div class="card shadow-sm agenda-calendario-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <h2 class="h5 mb-1" id="agenda-calendario-titulo">Calendário de agendamentos</h2>
                                    <p class="text-muted small mb-0">Selecione uma data para conferir os atendimentos registrados.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="agenda-voltar-hoje">
                                    Hoje
                                </button>
                            </div>
                            <div class="calendar" id="agenda-calendario"
                                data-csrf="<?= htmlspecialchars(agenday_token_csrf(), ENT_QUOTES, 'UTF-8') ?>"
                                data-dia-inicial="<?= htmlspecialchars($agendaDiaInicial, ENT_QUOTES, 'UTF-8') ?>"
                                data-fuso="<?= htmlspecialchars(
                                    defined('AGENDAY_TIMEZONE') ? (string) AGENDAY_TIMEZONE : 'America/Manaus',
                                    ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'
                                ) ?>">
                                <div class="calendar-header" aria-label="Navegação pelo calendário">
                                    <button type="button" class="agenda-navegacao-btn" id="agenda-mes-anterior"
                                        aria-label="Mês anterior" title="Mês anterior">
                                        <span aria-hidden="true">‹</span>
                                    </button>
                                    <h3 class="agenda-mes-titulo mb-0" id="agenda-mes-titulo" aria-live="polite">Carregando mês...</h3>
                                    <button type="button" class="agenda-navegacao-btn" id="agenda-mes-seguinte"
                                        aria-label="Próximo mês" title="Próximo mês">
                                        <span aria-hidden="true">›</span>
                                    </button>
                                </div>
                                <div class="calendar-body">
                                    <div class="calendar-week-day" aria-hidden="true">
                                        <div>Dom</div>
                                        <div>Seg</div>
                                        <div>Ter</div>
                                        <div>Qua</div>
                                        <div>Qui</div>
                                        <div>Sex</div>
                                        <div>Sáb</div>
                                    </div>
                                    <div class="calendar-days" id="agenda-dias" aria-label="Dias do mês"></div>
                                </div>
                            </div>
                            <p class="small text-muted mt-3 mb-0">
                                O número nos dias indica quantos registros existem, incluindo os cancelados.
                                A disponibilidade para novos agendamentos é verificada ao salvar.
                            </p>
                            <noscript><p class="alert alert-warning">Ative o JavaScript para visualizar os agendamentos no calendário.</p></noscript>
                            <div id="agenda-estado" class="small mt-2" role="status" aria-live="polite">
                                Carregando atendimentos...
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="agenda-tentar-novamente" hidden>
                                Tentar novamente
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <section class="card shadow-sm agenda-dia-card h-100" aria-labelledby="agenda-dia-titulo">
                        <div class="card-body">
                            <h2 class="h5 mb-2" id="agenda-dia-titulo">Atendimentos do dia</h2>
                            <p class="small text-muted" id="agenda-dia-resumo" role="status" aria-live="polite">
                                Escolha um dia no calendário.
                            </p>
                            <div id="agenda-lista-dia" class="agenda-lista-dia" aria-label="Atendimentos do dia">
                                <p class="text-muted">Carregando agendamentos...</p>
                            </div>
                        </div>
                    </section>
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