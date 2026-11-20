<?php
declare(strict_types=1);
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/configuracao-estabelecimento.php';
require_once __DIR__ . '/lib/avatar-estabelecimento.php';
require_once __DIR__ . '/lib/horario-funcionamento.php';
require_once __DIR__ . '/lib/bloqueios.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$abasPermitidas = ['estabelecimento', 'funcionamento', 'conta'];
$abaSolicitada = isset($_GET['aba']) && is_string($_GET['aba']) ? $_GET['aba'] : '';
$abaSelecionada = in_array($abaSolicitada, $abasPermitidas, true) ? $abaSolicitada : 'estabelecimento';
$alertaEstabelecimento = null;
$alertaConta = null;
$alertaSenha = null;
$alertaFuncionamento = null;
$reabrirModalSenha = false;
$dadosCarregados = false;
$funcionamentoDisponivel = false;
$diasFuncionamento = agenday_horario_padrao();
$regrasFuncionamento = agenday_regras_padrao();
$bloqueios = [];
$funcionariosBloqueio = [];
$formularioBloqueio = ['inicio' => '', 'fim' => '', 'motivo' => '', 'id_funcionario' => ''];
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

if (isset($_SESSION['feedback_config_funcionamento'])
    && is_string($_SESSION['feedback_config_funcionamento'])) {
    $alertaFuncionamento = ['tipo' => 'success', 'texto' => $_SESSION['feedback_config_funcionamento']];
    unset($_SESSION['feedback_config_funcionamento']);
    $abaSelecionada = 'funcionamento';
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

// Horário de funcionamento e regras: se a migração ainda não foi aplicada,
// a página continua funcionando e só a aba de horários fica bloqueada.
if (isset($conexao) && $conexao instanceof mysqli) {
    try {
        $diasFuncionamento = agenday_horario_carregar($conexao, $idEstabelecimento);
        $regrasFuncionamento = agenday_regras_carregar($conexao, $idEstabelecimento);
        $bloqueios = agenday_bloqueio_listar($conexao, $idEstabelecimento);
        $funcionariosBloqueio = agenday_bloqueio_funcionarios($conexao, $idEstabelecimento);
        $funcionamentoDisponivel = true;
    } catch (Throwable $e) {
        error_log('AgenDay: horário de funcionamento indisponível (' . get_class($e) . ').');
        $funcionamentoDisponivel = false;
    }
}

$nomeAtual = (string) $nomeAtual;
$emailAtual = (string) $emailAtual;
$contatoAtual = (string) ($contatoAtual ?? '');
$nomeFormulario = $nomeAtual;
$contatoFormulario = $contatoAtual;

if ($metodo === 'POST') {
    $acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';
    if ($acao === 'atualizar_foto') {
        $abaSelecionada = 'conta';
    } elseif (in_array($acao, ['salvar_funcionamento', 'criar_bloqueio', 'excluir_bloqueio'], true)) {
        $abaSelecionada = 'funcionamento';
    } else {
        $abaSelecionada = 'estabelecimento';
    }

    if (!agenday_valida_csrf()) {
        http_response_code(403);
        $erro = ['tipo' => 'danger', 'texto' => 'Sessão de segurança expirada. Recarregue e tente novamente.'];
        if ($abaSelecionada === 'conta') {
            $alertaConta = $erro;
        } elseif ($abaSelecionada === 'funcionamento') {
            $alertaFuncionamento = $erro;
        } else {
            $alertaEstabelecimento = $erro;
        }
    } elseif (!$dadosCarregados) {
        $erro = ['tipo' => 'danger', 'texto' => 'Não foi possível validar esta conta.'];
        if ($abaSelecionada === 'conta') {
            $alertaConta = $erro;
        } elseif ($abaSelecionada === 'funcionamento') {
            $alertaFuncionamento = $erro;
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
    } elseif ($acao === 'salvar_funcionamento') {
        [$diasValidados, $errosDias] = agenday_horario_validar($_POST);
        [$regrasValidadas, $errosRegras] = agenday_regras_validar($_POST);
        $diasFuncionamento = $diasValidados;
        $regrasFuncionamento = $regrasValidadas;
        $errosFuncionamento = array_merge($errosDias, $errosRegras);

        if (!$funcionamentoDisponivel) {
            $errosFuncionamento[] = 'A estrutura de horários ainda não existe neste banco. '
                . 'Aplique o arquivo sql/migracao-horario-funcionamento.sql antes de salvar.';
        }

        if ($errosFuncionamento !== []) {
            $alertaFuncionamento = [
                'tipo' => 'danger',
                'texto' => implode(' ', array_unique($errosFuncionamento)),
            ];
        } else {
            try {
                agenday_horario_salvar($conexao, $idEstabelecimento, $diasFuncionamento, $regrasFuncionamento);
                $_SESSION['feedback_config_funcionamento'] = 'Horário de funcionamento salvo com sucesso.';
                header('Location: config.php?aba=funcionamento', true, 303);
                exit;
            } catch (Throwable $e) {
                error_log('AgenDay: falha ao salvar horário de funcionamento (' . get_class($e) . ').');
                $alertaFuncionamento = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível salvar o horário de funcionamento agora. Tente novamente.',
                ];
            }
        }
    } elseif ($acao === 'criar_bloqueio') {
        foreach (array_keys($formularioBloqueio) as $campo) {
            $formularioBloqueio[$campo] = isset($_POST[$campo]) && is_string($_POST[$campo])
                ? trim($_POST[$campo]) : '';
        }
        [$dadosBloqueio, $errosBloqueio] = agenday_bloqueio_validar($_POST);
        if (!$funcionamentoDisponivel) {
            $errosBloqueio[] = 'A estrutura de bloqueios ainda não existe neste banco.';
        }

        if ($errosBloqueio !== []) {
            $alertaFuncionamento = ['tipo' => 'danger', 'texto' => implode(' ', $errosBloqueio)];
        } else {
            try {
                agenday_bloqueio_criar($conexao, $idEstabelecimento, $dadosBloqueio);
                $_SESSION['feedback_config_funcionamento'] = 'Bloqueio adicionado com sucesso.';
                header('Location: config.php?aba=funcionamento#bloqueios', true, 303);
                exit;
            } catch (DomainException $e) {
                $alertaFuncionamento = ['tipo' => 'danger', 'texto' => $e->getMessage()];
            } catch (Throwable $e) {
                error_log('AgenDay: falha ao criar bloqueio (' . get_class($e) . ').');
                $alertaFuncionamento = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível adicionar o bloqueio agora. Tente novamente.',
                ];
            }
        }
    } elseif ($acao === 'excluir_bloqueio') {
        $idBloqueio = filter_var($_POST['id_bloqueio'] ?? null, FILTER_VALIDATE_INT);
        if ($idBloqueio === false || $idBloqueio < 1) {
            $alertaFuncionamento = ['tipo' => 'danger', 'texto' => 'Bloqueio inválido.'];
        } else {
            try {
                if (agenday_bloqueio_excluir($conexao, $idEstabelecimento, $idBloqueio)) {
                    $_SESSION['feedback_config_funcionamento'] = 'Bloqueio excluído com sucesso.';
                    header('Location: config.php?aba=funcionamento#bloqueios', true, 303);
                    exit;
                }
                $alertaFuncionamento = ['tipo' => 'danger', 'texto' => 'Bloqueio não encontrado.'];
            } catch (Throwable $e) {
                error_log('AgenDay: falha ao excluir bloqueio (' . get_class($e) . ').');
                $alertaFuncionamento = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível excluir o bloqueio agora. Tente novamente.',
                ];
            }
        }
    } else {
        http_response_code(400);
        $alertaEstabelecimento = ['tipo' => 'danger', 'texto' => 'Ação desconhecida.'];
    }
}

$csrfToken = agenday_token_csrf();
$avatarContaUrl = agenday_avatar_url($idEstabelecimento);
$abaContaAtiva = $abaSelecionada === 'conta';
$abaFuncionamentoAtiva = $abaSelecionada === 'funcionamento';
$abaEstabelecimentoAtiva = !$abaContaAtiva && !$abaFuncionamentoAtiva;
$nomesDias = agenday_dias_semana();
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
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/configuracoes-v2.css?v=20261120-ui16">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3"
            crossorigin="anonymous"></script>
    <script defer src="js/configuracoes.js?v=20261120-ui12"></script>
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
        <h1 class="fw-bold">Configurações</h1>
        <p>Gerencie os dados do estabelecimento e as informações da sua conta.</p>
    </div>

    <div class="ag-settings-panel">
        <nav class="ag-settings-tabbar" aria-label="Seções das configurações">
            <div class="nav nav-tabs ag-settings-tabs" id="abasConfiguracao" role="tablist">
                <button class="nav-link <?= $abaEstabelecimentoAtiva ? 'active' : '' ?>" id="aba-estabelecimento"
                        type="button" role="tab" data-bs-toggle="tab" data-bs-target="#painel-estabelecimento"
                        aria-controls="painel-estabelecimento" aria-selected="<?= $abaEstabelecimentoAtiva ? 'true' : 'false' ?>"
                        tabindex="<?= $abaEstabelecimentoAtiva ? '0' : '-1' ?>">Estabelecimento</button>
                <button class="nav-link <?= $abaFuncionamentoAtiva ? 'active' : '' ?>" id="aba-horarios"
                        type="button" role="tab" data-bs-toggle="tab" data-bs-target="#painel-horarios"
                        aria-controls="painel-horarios" aria-selected="<?= $abaFuncionamentoAtiva ? 'true' : 'false' ?>"
                        tabindex="<?= $abaFuncionamentoAtiva ? '0' : '-1' ?>">Horários</button>
                <button class="nav-link <?= $abaContaAtiva ? 'active' : '' ?>" id="aba-minha-conta"
                        type="button" role="tab" data-bs-toggle="tab" data-bs-target="#painel-minha-conta"
                        aria-controls="painel-minha-conta" aria-selected="<?= $abaContaAtiva ? 'true' : 'false' ?>"
                        tabindex="<?= $abaContaAtiva ? '0' : '-1' ?>">Minha Conta</button>
            </div>
        </nav>

        <div class="tab-content" id="conteudoConfiguracao">
            <section class="tab-pane fade <?= $abaEstabelecimentoAtiva ? 'show active' : '' ?>" id="painel-estabelecimento"
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

            <section class="tab-pane fade <?= $abaFuncionamentoAtiva ? 'show active' : '' ?>" id="painel-horarios"
                     role="tabpanel" aria-labelledby="aba-horarios" tabindex="0">
                <div class="ag-settings-body">
                    <div class="ag-settings-section-title">
                        <h2>Horário de funcionamento</h2>
                        <p>Define quando o estabelecimento atende. A agenda usa estes horários para calcular os horários realmente livres.</p>
                    </div>

                    <?php if ($alertaFuncionamento !== null): ?>
                        <div class="alert alert-<?= $alertaFuncionamento['tipo'] === 'success' ? 'success' : 'danger' ?>"
                             role="alert" aria-live="polite">
                            <?= agenday_config_html($alertaFuncionamento['texto']) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$funcionamentoDisponivel): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
                            O horário ainda não pode ser salvo porque a estrutura do banco não foi atualizada.
                            Aplique <code>sql/migracao-horario-funcionamento.sql</code> no phpMyAdmin.
                        </div>
                    <?php endif; ?>

                    <form method="post" action="config.php?aba=funcionamento" id="formFuncionamento">
                        <input type="hidden" name="acao" value="salvar_funcionamento">
                        <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">

                        <div class="ag-horarios-tabela">
                            <table class="table align-middle ag-horarios">
                                <caption class="visually-hidden">Horário de funcionamento por dia da semana</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Dia</th>
                                        <th scope="col">Aberto</th>
                                        <th scope="col">Abre</th>
                                        <th scope="col">Fecha</th>
                                        <th scope="col">Almoço (início / fim)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($diasFuncionamento as $numeroDia => $dia): ?>
                                    <?php $numeroDia = (int) $numeroDia; ?>
                                    <tr class="ag-horario-linha" data-dia="<?= $numeroDia ?>">
                                        <th scope="row">
                                            <label class="ag-horario-dia" for="dia-<?= $numeroDia ?>-aberto">
                                                <?= agenday_config_html($nomesDias[$numeroDia]) ?>
                                            </label>
                                        </th>
                                        <td data-rotulo="Aberto">
                                            <input type="hidden" name="dias[<?= $numeroDia ?>][aberto]" value="0">
                                            <input class="form-check-input ag-horario-aberto" type="checkbox"
                                                   id="dia-<?= $numeroDia ?>-aberto"
                                                   name="dias[<?= $numeroDia ?>][aberto]" value="1"
                                                   aria-describedby="dia-<?= $numeroDia ?>-aberto-ajuda"
                                                   <?= !empty($dia['aberto']) ? 'checked' : '' ?>>
                                            <span class="visually-hidden" id="dia-<?= $numeroDia ?>-aberto-ajuda">Aberto em <?= agenday_config_html($nomesDias[$numeroDia]) ?></span>
                                        </td>
                                        <td data-rotulo="Abre">
                                            <label class="visually-hidden" for="dia-<?= $numeroDia ?>-abertura">Abertura de <?= agenday_config_html($nomesDias[$numeroDia]) ?></label>
                                            <input class="form-control ag-horario-campo" type="time" step="300"
                                                   id="dia-<?= $numeroDia ?>-abertura"
                                                   name="dias[<?= $numeroDia ?>][abertura]"
                                                   value="<?= agenday_config_html($dia['abertura']) ?>">
                                        </td>
                                        <td data-rotulo="Fecha">
                                            <label class="visually-hidden" for="dia-<?= $numeroDia ?>-fechamento">Fechamento de <?= agenday_config_html($nomesDias[$numeroDia]) ?></label>
                                            <input class="form-control ag-horario-campo" type="time" step="300"
                                                   id="dia-<?= $numeroDia ?>-fechamento"
                                                   name="dias[<?= $numeroDia ?>][fechamento]"
                                                   value="<?= agenday_config_html($dia['fechamento']) ?>">
                                        </td>
                                        <td data-rotulo="Almoço">
                                            <div class="ag-horario-almoco">
                                                <div>
                                                    <label class="visually-hidden" for="dia-<?= $numeroDia ?>-almoco-inicio">Início do almoço de <?= agenday_config_html($nomesDias[$numeroDia]) ?></label>
                                                    <input class="form-control ag-horario-campo" type="time" step="300"
                                                           id="dia-<?= $numeroDia ?>-almoco-inicio"
                                                           name="dias[<?= $numeroDia ?>][almoco_inicio]"
                                                           value="<?= agenday_config_html($dia['almoco_inicio']) ?>">
                                                </div>
                                                <span class="ag-horario-separador" aria-hidden="true">–</span>
                                                <div>
                                                    <label class="visually-hidden" for="dia-<?= $numeroDia ?>-almoco-fim">Fim do almoço de <?= agenday_config_html($nomesDias[$numeroDia]) ?></label>
                                                    <input class="form-control ag-horario-campo" type="time" step="300"
                                                           id="dia-<?= $numeroDia ?>-almoco-fim"
                                                           name="dias[<?= $numeroDia ?>][almoco_fim]"
                                                           value="<?= agenday_config_html($dia['almoco_fim']) ?>">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <fieldset class="ag-horarios-regras">
                            <legend>Regras de agendamento</legend>
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="regraIntervalo">Novo horário a cada</label>
                                    <select class="form-select" id="regraIntervalo" name="regras[intervalo]"
                                            aria-describedby="regraIntervaloAjuda">
                                        <?php foreach (AGENDAY_HORARIO_INTERVALOS as $minutosIntervalo): ?>
                                            <option value="<?= (int) $minutosIntervalo ?>"
                                                <?= (int) $regrasFuncionamento['intervalo'] === (int) $minutosIntervalo ? 'selected' : '' ?>>
                                                <?= (int) $minutosIntervalo ?> minutos
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text" id="regraIntervaloAjuda">
                                        De quanto em quanto tempo o sistema oferece horários.
                                        Com 30 minutos: 09:00, 09:30, 10:00. Vale para a semana toda e não muda a duração do serviço.
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="regraAntecedencia">Antecedência mínima (minutos)</label>
                                    <input class="form-control" type="number" id="regraAntecedencia"
                                           name="regras[antecedencia]" min="0" max="1440" step="5"
                                           value="<?= (int) $regrasFuncionamento['antecedencia'] ?>">
                                    <div class="form-text">Tempo mínimo entre agora e o horário marcado.</div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="regraJanela">Agendar com até (dias)</label>
                                    <input class="form-control" type="number" id="regraJanela"
                                           name="regras[janela]" min="1" max="365" step="1"
                                           value="<?= (int) $regrasFuncionamento['janela'] ?>">
                                    <div class="form-text">Quantos dias à frente o cliente pode marcar.</div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="regraCancelamento">Cancelar com (horas de antecedência)</label>
                                    <input class="form-control" type="number" id="regraCancelamento"
                                           name="regras[cancelamento]" min="0" max="720" step="1"
                                           value="<?= (int) $regrasFuncionamento['cancelamento'] ?>">
                                    <div class="form-text">Prazo mínimo para cancelar sem taxa. Use 0 para liberar sempre.</div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="regraTolerancia">Tolerância de atraso (minutos)</label>
                                    <input class="form-control" type="number" id="regraTolerancia"
                                           name="regras[tolerancia]" min="0" max="120" step="5"
                                           value="<?= (int) $regrasFuncionamento['tolerancia'] ?>">
                                    <div class="form-text">Depois disso, o atendimento pode ser liberado.</div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="ag-settings-actions">
                            <button class="btn btn-primary" type="submit" <?= !$funcionamentoDisponivel ? 'disabled' : '' ?>>
                                <i class="bi bi-check2 me-1" aria-hidden="true"></i>Salvar horário
                            </button>
                        </div>
                    </form>

                    <section class="ag-bloqueios" id="bloqueios" aria-labelledby="tituloBloqueios">
                        <div class="ag-settings-section-title">
                            <h3 id="tituloBloqueios">Bloqueios</h3>
                            <p>Impeça agendamentos em feriados, folgas ou horários em que o estabelecimento não atende.</p>
                        </div>

                        <form method="post" action="config.php?aba=funcionamento#bloqueios" id="formBloqueio">
                            <input type="hidden" name="acao" value="criar_bloqueio">
                            <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="bloqueioInicio">Início</label>
                                    <input class="form-control" type="datetime-local" id="bloqueioInicio" name="inicio"
                                           value="<?= agenday_config_html($formularioBloqueio['inicio']) ?>" required
                                           <?= !$funcionamentoDisponivel ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="bloqueioFim">Fim</label>
                                    <input class="form-control" type="datetime-local" id="bloqueioFim" name="fim"
                                           value="<?= agenday_config_html($formularioBloqueio['fim']) ?>" required
                                           <?= !$funcionamentoDisponivel ? 'disabled' : '' ?>>
                                    <div class="form-text">Para o dia inteiro, use 00:00 no início e 23:59 no fim.</div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="bloqueioMotivo">Motivo</label>
                                    <input class="form-control" type="text" id="bloqueioMotivo" name="motivo"
                                           maxlength="120" placeholder="Ex.: feriado, folga ou curso" required
                                           value="<?= agenday_config_html($formularioBloqueio['motivo']) ?>"
                                           <?= !$funcionamentoDisponivel ? 'disabled' : '' ?>>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="bloqueioFuncionario">Quem fica bloqueado?</label>
                                    <select class="form-select" id="bloqueioFuncionario" name="id_funcionario"
                                            <?= !$funcionamentoDisponivel ? 'disabled' : '' ?>>
                                        <option value="">Estabelecimento inteiro</option>
                                        <?php foreach ($funcionariosBloqueio as $funcionario): ?>
                                            <option value="<?= (int) $funcionario['id_funcionario'] ?>"
                                                <?= (string) $funcionario['id_funcionario'] === $formularioBloqueio['id_funcionario'] ? 'selected' : '' ?>>
                                                <?= agenday_config_html($funcionario['nome_funcionario']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="ag-settings-actions">
                                <button class="btn btn-primary" type="submit" <?= !$funcionamentoDisponivel ? 'disabled' : '' ?>>
                                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Adicionar bloqueio
                                </button>
                            </div>
                        </form>

                        <?php if ($bloqueios === []): ?>
                            <p class="ag-bloqueios-vazio">Nenhum bloqueio cadastrado.</p>
                        <?php else: ?>
                            <ul class="ag-bloqueios-lista">
                                <?php foreach ($bloqueios as $bloqueio): ?>
                                    <li class="ag-bloqueio-item">
                                        <div class="ag-bloqueio-info">
                                            <strong><?= agenday_config_html($bloqueio['motivo']) ?></strong>
                                            <span>
                                                <?= agenday_config_html((new DateTimeImmutable($bloqueio['inicio']))->format('d/m/Y H:i')) ?>
                                                até
                                                <?= agenday_config_html((new DateTimeImmutable($bloqueio['fim']))->format('d/m/Y H:i')) ?>
                                            </span>
                                            <span class="ag-bloqueio-escopo">
                                                <?= $bloqueio['id_funcionario'] === null
                                                    ? 'Estabelecimento inteiro'
                                                    : agenday_config_html($bloqueio['nome_funcionario'] ?? 'Funcionário') ?>
                                            </span>
                                        </div>
                                        <form method="post" action="config.php?aba=funcionamento#bloqueios" class="ag-bloqueio-excluir">
                                            <input type="hidden" name="acao" value="excluir_bloqueio">
                                            <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                                            <input type="hidden" name="id_bloqueio" value="<?= (int) $bloqueio['id_bloqueio'] ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit"
                                                    aria-label="Excluir bloqueio <?= agenday_config_html($bloqueio['motivo']) ?>">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                <span class="visually-hidden">Excluir</span>
                                            </button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>
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
