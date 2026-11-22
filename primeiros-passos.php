<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/primeiros-passos.php';
require_once __DIR__ . '/lib/portal-cliente.php';
require_once __DIR__ . '/lib/configuracao-estabelecimento.php';

/**
 * PRIMEIROS-PASSOS-01
 *
 * Tela única do primeiro uso: mostra o que falta para a agenda funcionar,
 * sugere serviços pelo tipo de negócio e resolve o caso mais comum ("sou eu
 * que atendo") em um clique.
 */

$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

$conexaoOk = false;
try {
    require_once __DIR__ . '/conexao.php';
    $conexaoOk = isset($conexao) && $conexao instanceof mysqli;
} catch (Throwable $e) {
    error_log('AgenDay primeiros passos: banco indisponível (' . get_class($e) . ').');
    $conexaoOk = false;
}

$segmento = isset($_SESSION['agenday_primeiros_passos_segmento'])
    && is_string($_SESSION['agenday_primeiros_passos_segmento'])
    ? $_SESSION['agenday_primeiros_passos_segmento']
    : '';
if (!agenday_primeiros_passos_segmento_valido($segmento)) {
    // Sem sessão (navegador reaberto), o cookie do navegador lembra a escolha.
    $segmento = agenday_primeiros_passos_segmento_do_cookie($idEstabelecimento);
}

/* -------------------------------------------------------------------------
 * Dados do estabelecimento (usados pelas ações e pela tela)
 * ------------------------------------------------------------------------- */
$nomeEstabelecimento = (string) ($_SESSION['nome_estabelecimento'] ?? 'seu estabelecimento');
$contatoEstabelecimento = '';
$portalDisponivel = false;
$portalSlug = '';
$portalDominio = '';

if ($conexaoOk) {
    try {
        $stmt = $conexao->prepare(
            'SELECT nome_estabelecimento, contato_estabelecimento, slug, dominio
               FROM estabelecimento WHERE id_estabelecimento = ? LIMIT 1'
        );
        $stmt->bind_param('i', $idEstabelecimento);
        $stmt->execute();
        $linha = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($linha !== null) {
            $nomeEstabelecimento = (string) ($linha['nome_estabelecimento'] ?? $nomeEstabelecimento);
            $contatoEstabelecimento = (string) ($linha['contato_estabelecimento'] ?? '');
            $portalSlug = (string) ($linha['slug'] ?? '');
            $portalDominio = (string) ($linha['dominio'] ?? '');
            $portalDisponivel = true;
        }
    } catch (Throwable $e) {
        error_log('AgenDay primeiros passos: dados do estabelecimento indisponíveis (' . get_class($e) . ').');
    }
}

/* -------------------------------------------------------------------------
 * Ações do primeiro uso (POST + redirecionamento, sem reenvio ao atualizar)
 * ------------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';

    if (!$conexaoOk) {
        $_SESSION['pp_erro'] = 'O banco de dados não respondeu. Tente novamente em instantes.';
    } elseif (!agenday_valida_csrf()) {
        $_SESSION['pp_erro'] = 'A sessão do formulário expirou. Atualize a página e tente novamente.';
    } elseif ($acao === 'trocar') {
        unset($_SESSION['agenday_primeiros_passos_segmento']);
        setcookie(agenday_primeiros_passos_cookie_nome($idEstabelecimento), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_SESSION['pp_aviso'] = 'Escolha outro tipo de negócio.';
    } elseif ($acao === 'segmento') {
        $escolhido = isset($_POST['segmento']) && is_string($_POST['segmento']) ? $_POST['segmento'] : '';
        if (agenday_primeiros_passos_segmento_valido($escolhido)) {
            $_SESSION['agenday_primeiros_passos_segmento'] = $escolhido;
            // Guarda também no navegador: a sessão expira, a escolha não precisa.
            setcookie(agenday_primeiros_passos_cookie_nome($idEstabelecimento), $escolhido, [
                'expires' => time() + agenday_primeiros_passos_cookie_duracao(),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_SESSION['pp_aviso'] = 'Sugestões atualizadas para o seu tipo de negócio.';
        } else {
            $_SESSION['pp_erro'] = 'Escolha um tipo de negócio da lista.';
        }
    } elseif ($acao === 'servico') {
        $sugestao = agenday_primeiros_passos_sugestao($segmento, $_POST['sugestao'] ?? null);
        $duracao = $sugestao === null ? null : agenday_primeiros_passos_duracao((int) $sugestao['minutos']);
        $preco = $sugestao === null ? null : agenday_primeiros_passos_preco($sugestao['preco']);

        if ($sugestao === null || $duracao === null || $preco === null) {
            $_SESSION['pp_erro'] = 'Escolha um dos serviços sugeridos na lista.';
        } else {
            try {
                $stmt = $conexao->prepare(
                    'INSERT INTO servicos
                        (id_estabelecimento, nome_servico, duracao_servico, preco_servico)
                     VALUES (?, ?, ?, ?)'
                );
                $nomeServico = $sugestao['nome'];
                $stmt->bind_param('isss', $idEstabelecimento, $nomeServico, $duracao, $preco);
                $stmt->execute();
                $stmt->close();
                $_SESSION['pp_aviso'] = 'Serviço "' . $nomeServico . '" adicionado. Você pode editar o preço em Serviços.';
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $_SESSION['pp_aviso'] = 'Esse serviço já está cadastrado.';
                } else {
                    error_log('AgenDay primeiros passos: erro ao criar serviço (' . $e->getCode() . ').');
                    $_SESSION['pp_erro'] = 'Não foi possível adicionar o serviço agora.';
                }
            }
        }
    } elseif ($acao === 'profissional') {
        $nomeProfissional = isset($_POST['nome']) && is_string($_POST['nome']) ? trim($_POST['nome']) : '';
        $contatoProfissional = isset($_POST['contato']) && is_string($_POST['contato']) ? trim($_POST['contato']) : '';
        $vincular = isset($_POST['vincular']) && $_POST['vincular'] === '1';
        $letras = $nomeProfissional !== '' && preg_match('//u', $nomeProfissional)
            ? preg_match_all('/./us', $nomeProfissional)
            : 0;

        if ($letras < 2 || $letras > 120) {
            $_SESSION['pp_erro'] = 'Informe o nome de quem atende (2 a 120 caracteres).';
        } elseif (!agenday_telefone_estabelecimento_valido($contatoProfissional, true)) {
            $_SESSION['pp_erro'] = 'Informe um telefone com DDD (8 a 15 dígitos).';
        } else {
            $transacao = false;
            try {
                $conexao->begin_transaction();
                $transacao = true;

                $stmt = $conexao->prepare(
                    'INSERT INTO funcionarios (id_estabelecimento, nome_funcionario, contato_funcionario)
                     VALUES (?, ?, ?)'
                );
                $stmt->bind_param('iss', $idEstabelecimento, $nomeProfissional, $contatoProfissional);
                $stmt->execute();
                $idFuncionario = (int) $conexao->insert_id;
                $stmt->close();

                // Vincular todos os serviços existentes evita o beco sem saída de
                // "funcionário cadastrado, mas nenhum horário aparece".
                if ($vincular) {
                    $stmt = $conexao->prepare(
                        'INSERT IGNORE INTO funcionario_servico (id_estabelecimento, id_funcionario, id_servicos)
                         SELECT id_estabelecimento, ?, id_servicos
                           FROM servicos WHERE id_estabelecimento = ?'
                    );
                    $stmt->bind_param('ii', $idFuncionario, $idEstabelecimento);
                    $stmt->execute();
                    $stmt->close();
                }

                $conexao->commit();
                $transacao = false;
                $_SESSION['pp_aviso'] = $nomeProfissional . ' já pode atender.';
            } catch (Throwable $e) {
                if ($transacao) {
                    try {
                        $conexao->rollback();
                    } catch (Throwable $erroRollback) {
                        error_log('AgenDay primeiros passos: falha ao reverter.');
                    }
                }
                error_log('AgenDay primeiros passos: erro ao criar profissional (' . get_class($e) . ').');
                $_SESSION['pp_erro'] = 'Não foi possível cadastrar quem atende agora.';
            }
        }
    } elseif ($acao === 'portal') {
        // Escolha do endereço público: é o nome que aparece no link do cliente.
        $endereco = isset($_POST['endereco']) && is_string($_POST['endereco']) ? trim($_POST['endereco']) : '';
        $slugNovo = $portalSlug;
        $erroSlug = null;

        if (!$portalDisponivel) {
            $erroSlug = 'O endereço do portal não está disponível neste banco.';
        } elseif ($endereco === '') {
            try {
                $slugNovo = agenday_portal_slug_disponivel($conexao, $nomeEstabelecimento, $idEstabelecimento);
            } catch (Throwable $e) {
                error_log('AgenDay primeiros passos: falha ao gerar o endereço do portal.');
                $erroSlug = 'Não foi possível gerar o endereço agora. Tente novamente.';
            }
        } else {
            [$slugNovo, $erroSlug] = agenday_portal_slug_analisar($endereco);

            if ($erroSlug === null && $slugNovo !== $portalSlug) {
                try {
                    if (agenday_portal_slug_ocupado($conexao, $slugNovo, $idEstabelecimento)) {
                        $erroSlug = 'Esse endereço já está em uso. Tente outro nome.';
                    }
                } catch (Throwable $e) {
                    error_log('AgenDay primeiros passos: falha ao conferir o endereço do portal.');
                    $erroSlug = 'Não foi possível conferir o endereço agora. Tente novamente.';
                }
            }
        }

        if ($erroSlug !== null) {
            $_SESSION['pp_erro'] = $erroSlug;
            $_SESSION['pp_endereco'] = $endereco;
        } else {
            try {
                $stmt = $conexao->prepare('UPDATE estabelecimento SET slug = ? WHERE id_estabelecimento = ?');
                $stmt->bind_param('si', $slugNovo, $idEstabelecimento);
                $stmt->execute();
                $stmt->close();
                $_SESSION['pp_aviso'] = 'Endereço salvo. Seu link agora é ' . $slugNovo . '.';
            } catch (mysqli_sql_exception $e) {
                $_SESSION['pp_endereco'] = $endereco;
                if ($e->getCode() === 1062) {
                    $_SESSION['pp_erro'] = 'Esse endereço acabou de ser escolhido por outra pessoa. Tente outro nome.';
                } else {
                    error_log('AgenDay primeiros passos: erro ao salvar o endereço (' . $e->getCode() . ').');
                    $_SESSION['pp_erro'] = 'Não foi possível salvar o endereço agora.';
                }
            }
        }
    } else {
        $_SESSION['pp_erro'] = 'Ação não reconhecida.';
    }

    header('Location: primeiros-passos.php', true, 303);
    exit;
}

/* -------------------------------------------------------------------------
 * Dados da tela
 * ------------------------------------------------------------------------- */
$estado = null;
$servicosExistentes = [];

if ($conexaoOk) {
    $estado = agenday_primeiros_passos_estado($conexao, $idEstabelecimento);

    try {
        $stmt = $conexao->prepare('SELECT nome_servico FROM servicos WHERE id_estabelecimento = ?');
        $stmt->bind_param('i', $idEstabelecimento);
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($servico = $resultado->fetch_assoc()) {
            $servicosExistentes[mb_strtolower((string) $servico['nome_servico'])] = true;
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('AgenDay primeiros passos: serviços indisponíveis (' . get_class($e) . ').');
    }
}

$portalLink = $portalDisponivel && $portalSlug !== ''
    ? agenday_portal_base_url(['slug' => $portalSlug, 'dominio' => $portalDominio])
    : '';
$portalPrefixo = $portalDisponivel ? agenday_portal_url_base($portalDominio) : '';
$enderecoDigitado = isset($_SESSION['pp_endereco']) && is_string($_SESSION['pp_endereco'])
    ? $_SESSION['pp_endereco']
    : $portalSlug;

$progresso = $estado ?? agenday_primeiros_passos_progresso([]);
$sugestoes = agenday_primeiros_passos_sugestoes($segmento);
$segmentos = agenday_primeiros_passos_segmentos();
$segmentoRotulo = $segmento !== '' && isset($segmentos[$segmento])
    ? $segmentos[$segmento]['rotulo']
    : '';
$precisaProfissional = true;
foreach ($progresso['itens'] as $itemProgresso) {
    if ($itemProgresso['chave'] === 'equipe') {
        $precisaProfissional = !$itemProgresso['feito'];
    }
}
$aviso = $_SESSION['pp_aviso'] ?? null;
$erro = $_SESSION['pp_erro'] ?? null;
unset($_SESSION['pp_aviso'], $_SESSION['pp_erro'], $_SESSION['pp_endereco']);
$csrfToken = agenday_token_csrf();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Primeiros passos | AgenDay</title>
    <link rel="icon" href="img/icon-agenda.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/agenda.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/configuracoes-v2.css?v=20261120-ui16">
    <link rel="stylesheet" href="css/primeiros-passos.css?v=20261122-pp05">
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
        <h1 class="fw-bold">Primeiros passos</h1>
        <p><?= $segmento === ''
            ? 'Escolha o seu tipo de negócio: o AgenDay já sugere os serviços. Leva uns 5 minutos.'
            : 'Escolha os serviços, diga quem atende e confira os horários. Leva uns 5 minutos.' ?></p>
    </div>

    <?php if ($aviso !== null): ?>
        <div class="alert alert-success" role="status" aria-live="polite">
            <?= agenday_config_html((string) $aviso) ?>
        </div>
    <?php endif; ?>
    <?php if ($erro !== null): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
            <?= agenday_config_html((string) $erro) ?>
        </div>
    <?php endif; ?>
    <?php if (!$conexaoOk): ?>
        <div class="alert alert-warning" role="alert">
            Não foi possível carregar o seu progresso agora. Atualize a página em instantes.
        </div>
    <?php endif; ?>

    <?php if ($segmento === ''): ?>
        <section class="ag-settings-panel pp-painel" aria-labelledby="pp-titulo-segmento">
            <div class="ag-settings-body">
                <div class="ag-settings-section-title">
                    <h2 id="pp-titulo-segmento">Que tipo de negócio é o seu?</h2>
                    <p>Escolha abaixo: o AgenDay já sugere os serviços mais comuns. Você pode editar tudo depois.</p>
                </div>
                <form method="post" action="primeiros-passos.php" class="pp-segmentos">
                    <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                    <input type="hidden" name="acao" value="segmento">
                    <?php foreach ($segmentos as $chave => $dadosSegmento): ?>
                        <button type="submit" class="pp-chip" name="segmento" value="<?= agenday_config_html($chave) ?>">
                            <strong><?= agenday_config_html($dadosSegmento['rotulo']) ?></strong>
                            <span><?= agenday_config_html($dadosSegmento['descricao']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </form>
            </div>
        </section>
    <?php else: ?>
        <section class="ag-settings-panel pp-painel" aria-labelledby="pp-titulo-sugestoes">
            <div class="ag-settings-body">
                <div class="ag-settings-section-title">
                    <h2 id="pp-titulo-sugestoes">
                        <?= $sugestoes === [] ? 'Cadastre o seu primeiro serviço' : 'Escolha os seus serviços' ?>
                    </h2>
                    <p>
                        <?php if ($sugestoes === []): ?>
                            Este tipo de negócio não tem sugestões prontas. Cadastre o seu serviço —
                            ele já fica disponível para agendar.
                        <?php else: ?>
                            Um clique cria o serviço com duração e preço de exemplo.
                            Ajuste os valores depois em <a href="cad.servicos.php">Serviços</a>.
                        <?php endif; ?>
                    </p>
                </div>

                <div class="pp-trocar pp-tipo">
                    <span>Tipo de negócio: <strong><?= agenday_config_html($segmentoRotulo) ?></strong></span>
                    <form method="post" action="primeiros-passos.php" class="pp-trocar-form">
                        <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                        <input type="hidden" name="acao" value="trocar">
                        <button type="submit" class="link-acao">Trocar</button>
                    </form>
                </div>

                <?php if ($sugestoes === []): ?>
                    <div class="pp-acoes">
                        <a class="btn btn-primary" href="cad.servicos.php">Cadastrar meu serviço</a>
                    </div>
                <?php else: ?>
                    <form method="post" action="primeiros-passos.php" class="pp-sugestoes">
                        <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                        <input type="hidden" name="acao" value="servico">
                        <?php foreach ($sugestoes as $indice => $sugestao): ?>
                            <?php $jaExiste = isset($servicosExistentes[mb_strtolower($sugestao['nome'])]); ?>
                            <button type="submit" class="pp-sugestao" name="sugestao" value="<?= (int) $indice ?>"
                                <?= $jaExiste ? 'disabled' : '' ?>>
                                <span class="pp-sugestao-nome"><?= agenday_config_html($sugestao['nome']) ?></span>
                                <span class="pp-sugestao-meta">
                                    <?= (int) $sugestao['minutos'] ?> min · R$ <?= agenday_config_html($sugestao['preco']) ?>
                                </span>
                                <span class="pp-sugestao-aco">
                                    <?= $jaExiste ? 'Já cadastrado' : '+ Adicionar' ?>
                                </span>
                            </button>
                        <?php endforeach; ?>
                    </form>
                    <p class="pp-outro-servico">
                        Não está na lista? <a href="cad.servicos.php">Cadastre o seu serviço</a>.
                    </p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="ag-settings-panel pp-painel" aria-labelledby="pp-titulo-progresso">
        <div class="ag-settings-body">
            <div class="ag-settings-section-title">
                <h2 id="pp-titulo-progresso">O que falta</h2>
                <p><?= $progresso['completo']
                    ? 'Tudo pronto: sua agenda já pode receber agendamentos.'
                    : 'Estes são os quatro itens que liberam os horários da sua agenda.' ?></p>
            </div>

            <div class="pp-progresso">
                <div class="pp-progresso-topo">
                    <strong><?= (int) $progresso['concluidos'] ?> de <?= (int) $progresso['total'] ?> concluídos</strong>
                    <span><?= (int) $progresso['percentual'] ?>%</span>
                </div>
                <div class="progress" role="progressbar"
                     aria-valuenow="<?= (int) $progresso['percentual'] ?>"
                     aria-valuemin="0" aria-valuemax="100"
                     aria-label="Progresso da configuração">
                    <div class="progress-bar" style="width: <?= (int) $progresso['percentual'] ?>%"></div>
                </div>
            </div>

            <ol class="pp-passos">
                <?php foreach ($progresso['itens'] as $indice => $item): ?>
                    <li class="pp-passo<?= $item['feito'] ? ' pp-passo-feito' : '' ?>">
                        <span class="pp-passo-marca" aria-hidden="true">
                            <?php if ($item['feito']): ?>
                                <i class="bi bi-check-lg"></i>
                            <?php else: ?>
                                <?= (int) $indice + 1 ?>
                            <?php endif; ?>
                        </span>
                        <div class="pp-passo-texto">
                            <h3>
                                <?= agenday_config_html($item['titulo']) ?>
                                <?php if ($item['feito']): ?>
                                    <span class="visually-hidden">(concluído)</span>
                                <?php endif; ?>
                            </h3>
                            <p><?= agenday_config_html($item['ajuda']) ?></p>
                        </div>
                        <?php if ($item['chave'] === 'equipe' && !$item['feito'] && $conexaoOk): ?>
                            <div class="pp-passo-acoes">
                                <a class="btn btn-primary btn-sm pp-passo-acao" href="#sou-eu-que-atendo">
                                    Sou eu que atendo
                                </a>
                                <a class="btn btn-outline-secondary btn-sm pp-passo-acao" href="cad-funcionario.php">
                                    Tenho equipe
                                </a>
                            </div>
                        <?php else: ?>
                            <a class="btn <?= $item['feito'] ? 'btn-outline-secondary' : 'btn-primary' ?> btn-sm pp-passo-acao"
                               href="<?= agenday_config_html($item['link']) ?>">
                                <?= agenday_config_html($item['feito'] ? 'Revisar' : $item['acao']) ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <?php if ($precisaProfissional && $conexaoOk): ?>
        <section class="ag-settings-panel pp-painel" id="sou-eu-que-atendo" aria-labelledby="pp-titulo-profissional">
            <div class="ag-settings-body">
                <div class="ag-settings-section-title">
                    <h2 id="pp-titulo-profissional">Sou eu que atendo</h2>
                    <p>Cadastre você mesmo como profissional. Se a equipe for maior, use
                        <a href="cad-funcionario.php">Funcionários</a>.</p>
                </div>
                <form method="post" action="primeiros-passos.php" class="pp-form">
                    <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                    <input type="hidden" name="acao" value="profissional">
                    <div class="pp-campo">
                        <label for="pp-prof-nome">Nome de quem atende</label>
                        <input type="text" id="pp-prof-nome" name="nome" class="form-control"
                               maxlength="120" required placeholder="Ex.: Dra. Ana Souza"
                               data-focar-ao-chegar="sou-eu-que-atendo">
                    </div>
                    <div class="pp-campo">
                        <label for="pp-prof-contato">Telefone com DDD</label>
                        <input type="tel" id="pp-prof-contato" name="contato" class="form-control"
                               maxlength="20" inputmode="tel" required
                               value="<?= agenday_config_html($contatoEstabelecimento) ?>">
                        <p class="form-text">Já preenchemos com o telefone do estabelecimento.</p>
                    </div>
                    <div class="form-check pp-check">
                        <input class="form-check-input" type="checkbox" id="pp-prof-vincular"
                               name="vincular" value="1" checked>
                        <label class="form-check-label" for="pp-prof-vincular">
                            Vincular todos os serviços já cadastrados
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1" aria-hidden="true"></i>Cadastrar e vincular
                    </button>
                </form>
            </div>
        </section>
    <?php endif; ?>

    <section class="ag-settings-panel pp-painel" aria-labelledby="pp-titulo-portal">
        <div class="ag-settings-body">
            <div class="ag-settings-section-title">
                <h2 id="pp-titulo-portal">Link para o cliente agendar</h2>
                <p>Escolha o nome do seu link. É o endereço que você envia no WhatsApp, no Instagram ou coloca na bio.</p>
            </div>

            <?php if (!$portalDisponivel): ?>
                <p class="form-text mb-0">
                    O endereço público não está disponível neste banco. Use
                    <a href="config.php">Configurações</a> para conferir.
                </p>
            <?php else: ?>
                <form method="post" action="primeiros-passos.php" class="pp-link-form">
                    <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                    <input type="hidden" name="acao" value="portal">
                    <div class="pp-campo">
                        <label for="pp-endereco">Nome do seu link</label>
                        <input type="text" id="pp-endereco" name="endereco" class="form-control"
                               maxlength="60" placeholder="clinica-sorriso"
                               autocapitalize="none" spellcheck="false" autocomplete="off"
                               aria-describedby="pp-endereco-ajuda"
                               value="<?= agenday_config_html($enderecoDigitado) ?>">
                        <p class="form-text" id="pp-endereco-ajuda">
                            Letras, números e hífen. Maiúsculas e acentos são convertidos.
                            Deixe em branco para usar o nome do estabelecimento.
                        </p>
                    </div>

                    <div class="pp-preview">
                        <span class="pp-preview-rotulo">Seu link ficará assim</span>
                        <div class="pp-preview-linha">
                            <code id="pp-portal-preview"
                                  data-prefixo="<?= agenday_config_html($portalPrefixo) ?>"
                                  data-slug="<?= agenday_config_html($portalSlug) ?>"
                                  data-nome="<?= agenday_config_html($nomeEstabelecimento) ?>"><?= agenday_config_html($portalLink !== '' ? $portalLink : $portalPrefixo) ?></code>
                            <button type="button" class="btn btn-outline-secondary" id="copiar-portal-link"
                                    data-link="<?= agenday_config_html($portalLink) ?>"
                                    <?= $portalLink === '' ? 'disabled' : '' ?>>Copiar</button>
                        </div>
                        <p class="pp-preview-aviso" id="pp-preview-aviso" aria-live="polite"<?= $portalLink !== '' ? ' hidden' : '' ?>>
                            Salve o endereço para ativar o link.
                        </p>
                    </div>

                    <div class="pp-link-acoes">
                        <button type="submit" class="btn btn-primary">Salvar endereço</button>
                        <?php
                        // O botão fica sempre visível: sem endereço salvo ele aparece
                        // desativado, em vez de sumir e deixar a pessoa sem saber que existe.
                        $atributosVerPagina = $portalLink !== ''
                            ? 'href="' . agenday_config_html($portalLink) . '"'
                            : 'aria-disabled="true" tabindex="-1"';
                        ?>
                        <a class="btn btn-outline-secondary pp-ver-pagina<?= $portalLink === '' ? ' disabled' : '' ?>"
                           id="abrir-portal-link"
                           data-link="<?= agenday_config_html($portalLink) ?>"
                           target="_blank" rel="noopener"
                           <?= $atributosVerPagina ?>>
                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Ver a página de agendamentos do seu cliente
                        </a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($progresso['completo']): ?>
        <p class="pp-rodape">
            <a class="btn btn-primary" href="agendar.php">
                <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Fazer o primeiro agendamento
            </a>
        </p>
    <?php endif; ?>
</main>

<script defer src="js/primeiros-passos.js?v=20261122-pp04"></script>
</body>

</html>
