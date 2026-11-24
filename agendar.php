<?php
declare(strict_types=1);
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/agendamentos.php';

$erro = isset($_SESSION['erro_agendar']) && is_string($_SESSION['erro_agendar'])
    ? $_SESSION['erro_agendar'] : null;
$sucesso = isset($_SESSION['sucesso_agendar']) && is_string($_SESSION['sucesso_agendar'])
    ? $_SESSION['sucesso_agendar'] : null;
unset($_SESSION['erro_agendar'], $_SESSION['sucesso_agendar']);

$servicos = [];
$pares = [];
$erroDados = false;
try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    $consulta = $conexao->prepare(
        'SELECT id_servicos, nome_servico, duracao_servico, preco_servico
         FROM servicos WHERE id_estabelecimento = ? ORDER BY nome_servico, id_servicos'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $resultado = $consulta->get_result();
    while ($linha = $resultado->fetch_assoc()) {
        $servicos[] = [
            'id' => (int) $linha['id_servicos'],
            'nome' => (string) $linha['nome_servico'],
            'duracao' => (string) $linha['duracao_servico'],
            'preco' => (string) $linha['preco_servico'],
        ];
    }
    $consulta->close();

    $consulta = $conexao->prepare(
        'SELECT f.id_funcionario, f.nome_funcionario, fs.id_servicos
         FROM funcionario_servico fs
         INNER JOIN funcionarios f
           ON f.id_estabelecimento = fs.id_estabelecimento
          AND f.id_funcionario = fs.id_funcionario
         WHERE fs.id_estabelecimento = ?
         ORDER BY f.nome_funcionario'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $resultado = $consulta->get_result();
    while ($linha = $resultado->fetch_assoc()) {
        $pares[] = [
            'id_funcionario' => (int) $linha['id_funcionario'],
            'nome_funcionario' => (string) $linha['nome_funcionario'],
            'id_servicos' => (int) $linha['id_servicos'],
        ];
    }
    $consulta->close();
} catch (Throwable $e) {
    error_log('AgenDay: falha ao carregar dados de agendamento (' . get_class($e) . ').');
    $erroDados = true;
}

$csrf = agenday_token_csrf();
$hoje = (new DateTimeImmutable('now', new DateTimeZone(defined('AGENDAY_TIMEZONE') ? AGENDAY_TIMEZONE : 'America/Manaus')))->format('Y-m-d');
$dataInicial = isset($_GET['data']) && is_string($_GET['data']) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $_GET['data'])
    ? $_GET['data'] : $hoje;
$moeda = static fn(string $valor): string => 'R$ ' . number_format((float) $valor, 2, ',', '.');
$minutosDuracao = static function (string $duracao): int {
    $partes = explode(':', $duracao);
    return ((int) ($partes[0] ?? 0)) * 60 + (int) ($partes[1] ?? 0);
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo agendamento | AgenDay</title>
    <link rel="icon" href="img/icon-agenda.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/agendar.css?v=20261121-ag07">
</head>
<body class="agenday-app" data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
      data-hoje="<?= htmlspecialchars($hoje, ENT_QUOTES, 'UTF-8') ?>">
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

<main class="agendar-pagina">
    <div class="agendar-topo">
        <div>
            <h1 class="agendar-titulo">Novo agendamento</h1>
            <p class="agendar-subtitulo">Escolha o serviço, quem atende e o horário. Cliente novo pode ser cadastrado aqui mesmo.</p>
        </div>
        <a class="btn btn-outline-secondary" href="agenda.php">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Agenda
        </a>
    </div>

    <?php if ($sucesso !== null): ?>
        <div class="alert alert-success" role="status" aria-live="polite"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($erro !== null): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($erroDados): ?>
        <div class="alert alert-danger" role="alert">Não foi possível carregar serviços e profissionais agora.</div>
    <?php endif; ?>
    <?php if ($servicos === []): ?>
        <div class="alert alert-warning" role="alert">
            Cadastre um <a href="cad.servicos.php">serviço</a> e vincule a um
            <a href="cad-funcionario.php">funcionário</a> para começar.
        </div>
    <?php endif; ?>

    <form method="post" action="criar-agendamento.php" class="agendar-grade" id="formAgendar">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id_cliente" id="campoClienteId" value="">
        <input type="hidden" name="id_funcionario" id="campoFuncionario" value="">
        <input type="hidden" name="modo" id="campoModo" value="unico">
        <div id="camposSessoes"></div>

        <section class="agendar-painel agendar-form" aria-label="Dados do agendamento">
            <div class="campo">
                <label for="buscaCliente">Cliente <span class="obrigatorio">*</span></label>
                <div class="busca">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input id="buscaCliente" type="text" placeholder="Buscar por nome ou telefone"
                           autocomplete="off" aria-describedby="clienteEscolhido">
                </div>
                <p class="campo-ajuda" id="clienteEscolhido">Nenhum cliente escolhido.</p>
                <ul class="sugestoes" id="sugestoesClientes" hidden></ul>
                <button type="button" class="link-acao" id="abrirNovoCliente">+ Cadastrar novo cliente</button>

                <div class="novo-cliente" id="novoCliente" hidden>
                    <label for="novoNome" class="visually-hidden">Nome do novo cliente</label>
                    <input id="novoNome" type="text" placeholder="Nome do cliente" maxlength="120">
                    <div data-confirmar-telefone id="confirmarTelefoneNovo">
                        <label for="novoTel" class="visually-hidden">Celular do novo cliente</label>
                        <input id="novoTel" type="tel" placeholder="Celular com WhatsApp (opcional)" maxlength="20"
                               data-telefone-campo>
                        <div class="acoes">
                            <button type="button" class="btn btn-outline-primary btn-sm" data-enviar-codigo>
                                <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>Confirmar no WhatsApp
                            </button>
                        </div>
                        <div data-codigo-area hidden>
                            <label for="novoCodigo" class="visually-hidden">Código recebido no WhatsApp</label>
                            <div class="acoes">
                                <input id="novoCodigo" type="text" placeholder="Código" maxlength="6"
                                       inputmode="numeric" autocomplete="one-time-code" data-codigo-campo>
                                <button type="button" class="btn btn-primary btn-sm" data-confirmar-codigo>Confirmar</button>
                            </div>
                        </div>
                        <p class="campo-ajuda" data-status role="status" aria-live="polite"></p>
                        <label class="campo-ajuda d-block">
                            <input type="checkbox" name="sem_whatsapp" value="1" data-sem-whatsapp>
                            Este cliente não tem WhatsApp
                        </label>
                    </div>
                    <div class="acoes">
                        <button type="button" class="btn btn-primary btn-sm" id="salvarNovoCliente">Salvar e usar</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelarNovoCliente">Voltar</button>
                    </div>
                    <p class="campo-ajuda" id="avisoNovoCliente" role="status" aria-live="polite"></p>
                </div>
            </div>

            <div class="campo">
                <label for="servico">Serviço <span class="obrigatorio">*</span></label>
                <select id="servico" name="id_servicos" required>
                    <option value="">Selecione o serviço</option>
                    <?php foreach ($servicos as $servico): ?>
                        <option value="<?= (int) $servico['id'] ?>"
                                data-duracao="<?= (int) $minutosDuracao($servico['duracao']) ?>"
                                data-preco="<?= htmlspecialchars($servico['preco'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($servico['nome'], ENT_QUOTES, 'UTF-8') ?>
                            · <?= (int) $minutosDuracao($servico['duracao']) ?> min
                            · <?= htmlspecialchars($moeda($servico['preco']), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="campo-ajuda" id="servicoAjuda">Duração definida no cadastro do serviço.</p>
            </div>

            <div class="campo">
                <label for="profissional">Profissional</label>
                <select id="profissional">
                    <option value="">Qualquer um (o sistema escolhe)</option>
                </select>
                <p class="campo-ajuda" id="profAjuda">O sistema escolhe um profissional livre no horário.</p>
            </div>

            <div class="campo" id="campoRepeticao">
                <label for="repeticao">Repetição</label>
                <select id="repeticao">
                    <option value="unico" selected>Não se repete</option>
                    <option value="pacote">Pacote de sessões</option>
                    <option value="retorno">Retorno recorrente</option>
                </select>
                <p class="campo-ajuda" id="repeticaoAjuda">Um atendimento só.</p>
            </div>

            <div class="campo" id="campoIntervalo" hidden>
                <label for="intervalo">A cada</label>
                <select id="intervalo">
                    <option value="7">7 dias (semanal)</option>
                    <option value="14">14 dias</option>
                    <option value="20">20 dias</option>
                    <option value="30" selected>30 dias (mensal)</option>
                    <option value="45">45 dias</option>
                    <option value="60">60 dias</option>
                </select>
            </div>

            <div class="campo" id="campoRepeticoes" hidden>
                <label for="repeticoes">Quantas vezes</label>
                <select id="repeticoes">
                    <option value="2">2 vezes</option>
                    <option value="3" selected>3 vezes</option>
                    <option value="4">4 vezes</option>
                    <option value="6">6 vezes</option>
                    <option value="10">10 vezes</option>
                    <option value="12">12 vezes</option>
                </select>
                <p class="campo-ajuda" id="repeticoesAjuda"></p>
            </div>

            <div class="mapa" id="mapa" hidden aria-label="Mapa das sessões"></div>
            <div class="sessoes" id="listaSessoes" hidden></div>
        </section>

        <section class="agendar-painel agendar-horarios" aria-labelledby="diaTitulo">
            <div class="horarios-topo">
                <div>
                    <h2 class="dia-titulo" id="diaTitulo">Carregando…</h2>
                    <p class="dia-resumo" id="diaResumo">Escolha um serviço para ver os horários.</p>
                </div>
                <div class="dia-controles">
                    <button type="button" class="seta" id="diaAnterior" aria-label="Dia anterior">‹</button>
                    <label class="visually-hidden" for="data">Data do agendamento</label>
                    <input id="data" type="date" value="<?= htmlspecialchars($dataInicial, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="seta" id="diaSeguinte" aria-label="Próximo dia">›</button>
                    <button type="button" class="hoje" id="botaoHoje" hidden>Hoje</button>
                </div>
            </div>
            <div class="legenda" aria-hidden="true">
                <span><i class="ponto ponto-livre"></i>livre</span>
                <span><i class="ponto ponto-ocupado"></i>ocupado</span>
            </div>
            <div id="listaHorarios" aria-live="polite"></div>
        </section>
    </form>
</main>

<footer class="agendar-resumo">
    <div class="resumo-inner">
        <div class="resumo-texto">
            <div class="resumo-linha1" id="resumoTitulo">Escolha cliente e horário</div>
            <div class="resumo-linha2" id="resumoDetalhe">Nenhum serviço escolhido</div>
        </div>
        <div class="resumo-acoes">
            <a class="btn btn-outline-secondary" href="agenda.php">Cancelar</a>
            <button class="btn btn-primary" id="confirmar" type="submit" form="formAgendar" disabled>Agendar horário</button>
        </div>
    </div>
</footer>

<section class="overlay"></section>

<script id="dados-agendar" type="application/json"><?= json_encode(
    ['pares' => $pares, 'servicos' => $servicos],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
) ?></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3"
        crossorigin="anonymous"></script>
<script src="js/menu-lateral.js"></script>
<script src="js/telefone-celular.js?v=20261123-ddd"></script>
<script src="js/confirmar-telefone.js"></script>
<script src="js/agendar.js?v=20261122-tel01"></script>
</body>
</html>
