<?php
/**
 * AgenDay 2.0 — portal público do cliente.
 *
 * Aberto por:
 *   https://agenday-agendamentos.infinityfree.io/?e=studio   (domínio do cliente)
 *   https://agenday-sistema.infinityfree.io/cliente.php?e=studio (plano B)
 *
 * Não usa a sessão administrativa. A sessão do cliente é um cookie próprio
 * (AGENDAY_CLIENTE) validado no banco por lib/portal-cliente.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/portal-cliente.php';
require_once __DIR__ . '/lib/whatsapp.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$esc = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$estabelecimento = null;
$sessao = null;
$erroConexao = false;

try {
    require_once __DIR__ . '/conexao.php';
    $estabelecimento = agenday_portal_estabelecimento_requisicao($conexao);
    if ($estabelecimento !== null) {
        $sessao = agenday_portal_sessao_ler($conexao, (int) $estabelecimento['id_estabelecimento']);
        // Sem cron: aproveita a visita para esvaziar a fila, após a resposta.
        agenday_whatsapp_gatilho_apos_resposta($conexao);
    }
} catch (Throwable $e) {
    error_log('AgenDay portal: falha ao abrir a página (' . get_class($e) . ').');
    $erroConexao = true;
}

if ($estabelecimento === null) {
    http_response_code(404);
    $mensagem = $erroConexao
        ? 'Não foi possível abrir a agenda agora. Tente novamente em instantes.'
        : 'Este endereço de agendamento não existe ou foi alterado. Confira o link ou o QR code do estabelecimento.';
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Agendamento não encontrado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/cliente.css?v=20261121-cl14">
</head>
<body class="agenday-auth agenday-portal">
    <main class="container cliente-main">
        <section class="cliente-card text-center">
            <p class="cliente-icone-erro" aria-hidden="true"><i class="bi bi-calendar-x"></i></p>
            <h1 class="cliente-titulo">Agendamento indisponível</h1>
            <p class="cliente-texto"><?= $esc($mensagem) ?></p>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}

$nomeEstabelecimento = trim((string) $estabelecimento['nome_estabelecimento']);
$slug = (string) ($estabelecimento['slug'] ?? '');
$logado = $sessao !== null;
$nomeCliente = $logado ? (string) $sessao['nome_cliente'] : '';
$exigirCodigo = (int) $estabelecimento['exigir_codigo_whatsapp'] === 1;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#18544D">
    <title><?= $esc($nomeEstabelecimento) ?> — Agendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/cliente.css?v=20261121-cl14">
</head>
<body class="agenday-auth agenday-portal"
      data-slug="<?= $esc($slug) ?>"
      data-logado="<?= $logado ? '1' : '0' ?>"
      data-exigir-codigo="<?= $exigirCodigo ? '1' : '0' ?>"
      data-csrf="<?= $logado ? $esc(agenday_portal_csrf_token($sessao)) : '' ?>"
      data-cliente="<?= $esc($nomeCliente) ?>">

<header class="cliente-topo">
    <div class="container cliente-topo-conteudo">
        <div class="cliente-marca">
            <span class="cliente-logo" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
            <div class="cliente-marca-texto">
                <p class="cliente-sobretitulo">Agendamento online</p>
                <h1 class="cliente-titulo"><?= $esc($nomeEstabelecimento) ?></h1>
            </div>
        </div>
        <div class="cliente-sessao" id="cliente-sessao" <?= $logado ? '' : 'hidden' ?>>
            <span class="cliente-sessao-nome" data-cliente-nome><?= $esc($nomeCliente) ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-acao="sair">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Sair</span>
                <span class="visually-hidden d-sm-none">Sair</span>
            </button>
        </div>
    </div>
</header>

<main class="container cliente-main" id="cliente-conteudo">
    <noscript>
        <div class="alert alert-warning" role="alert">
            Para agendar, ative o JavaScript no navegador ou fale diretamente com o estabelecimento.
        </div>
    </noscript>

    <div id="cliente-alerta" class="alert d-none" role="alert" aria-live="polite"></div>

    <!-- Etapa 1: entrar (login) ou primeiro acesso (cadastro) -->
    <section id="etapa-entrar" class="cliente-card" <?= $logado ? 'hidden' : '' ?>>
        <div class="cliente-abas" role="tablist" aria-label="Acesso ao portal">
            <button type="button" class="cliente-aba is-ativa" role="tab" aria-selected="true"
                    aria-controls="painel-login" id="aba-login" data-acesso="login">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Entrar
            </button>
            <button type="button" class="cliente-aba" role="tab" aria-selected="false"
                    aria-controls="painel-cadastro" id="aba-cadastro" data-acesso="cadastro">
                <i class="bi bi-person-plus" aria-hidden="true"></i> Cadastro
            </button>
        </div>

        <div id="painel-login" role="tabpanel" aria-labelledby="aba-login">
            <h2 class="cliente-card-titulo">Entrar</h2>
            <p class="cliente-texto">Digite o celular que você cadastrou no estabelecimento.</p>
            <form id="form-entrar" novalidate>
                <div class="cliente-campo">
                    <label for="campo-telefone">Celular com DDD</label>
                    <input type="tel" class="form-control" id="campo-telefone" name="telefone"
                           inputmode="tel" autocomplete="tel" placeholder="(92) 99999-9999"
                           maxlength="20" required>
                </div>
                <button type="submit" class="btn btn-primary cliente-botao-largo">
                    <span class="cliente-botao-texto">Entrar</span>
                    <span class="spinner-border spinner-border-sm d-none" data-carregando aria-hidden="true"></span>
                </button>
            </form>
            <p class="cliente-acesso-alternar">
                Ainda não tem cadastro?
                <button type="button" class="btn btn-link" data-acesso="cadastro">Faça o primeiro acesso</button>
            </p>
        </div>

        <div id="painel-cadastro" role="tabpanel" aria-labelledby="aba-cadastro" hidden>
            <h2 class="cliente-card-titulo">Primeiro acesso</h2>
            <p class="cliente-texto">
                <?php if ($exigirCodigo): ?>
                    Informe o celular e seu nome. Enviamos um código de 6 números no WhatsApp para confirmar o número.
                <?php else: ?>
                    Informe o celular e seu nome para começar a agendar.
                <?php endif; ?>
            </p>
            <form id="form-cadastro" novalidate>
                <div class="cliente-campo">
                    <label for="campo-telefone-cadastro">Celular com DDD</label>
                    <input type="tel" class="form-control" id="campo-telefone-cadastro" name="telefone"
                           inputmode="tel" autocomplete="tel" placeholder="(92) 99999-9999"
                           maxlength="20" required>
                </div>
                <div class="cliente-campo">
                    <label for="campo-nome">Seu nome completo</label>
                    <input type="text" class="form-control" id="campo-nome" name="nome"
                           autocomplete="name" placeholder="Nome e sobrenome" maxlength="120" required>
                </div>
                <button type="submit" class="btn btn-primary cliente-botao-largo">
                    <span class="cliente-botao-texto">Criar cadastro</span>
                    <span class="spinner-border spinner-border-sm d-none" data-carregando aria-hidden="true"></span>
                </button>
            </form>
            <p class="cliente-acesso-alternar">
                Já tem cadastro?
                <button type="button" class="btn btn-link" data-acesso="login">Entrar com o celular</button>
            </p>
        </div>
    </section>

    <!-- Etapa 2: código recebido -->
    <section id="etapa-codigo" class="cliente-card" hidden>
        <h2 class="cliente-card-titulo">Digite o código</h2>
        <p class="cliente-texto">
            Enviamos 6 números para <strong data-telefone-mascarado></strong>.
            O código vale por <span data-minutos>10</span> minutos.
        </p>
        <form id="form-codigo" novalidate>
            <div class="cliente-campo">
                <label for="campo-codigo">Código recebido no WhatsApp</label>
                <input type="text" class="form-control cliente-codigo" id="campo-codigo" name="codigo"
                       inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
                       maxlength="6" placeholder="000000" required>
            </div>
            <button type="submit" class="btn btn-primary cliente-botao-largo">
                <span class="cliente-botao-texto">Entrar</span>
                <span class="spinner-border spinner-border-sm d-none" data-carregando aria-hidden="true"></span>
            </button>
            <div class="cliente-acoes-secundarias">
                <button type="button" class="btn btn-link" data-acao="reenviar-codigo">Reenviar código</button>
                <button type="button" class="btn btn-link" data-acao="trocar-telefone">Usar outro número</button>
            </div>
        </form>
    </section>

    <!-- Etapa 3: agendar -->
    <section id="etapa-agendar" <?= $logado ? '' : 'hidden' ?>>
        <div class="cliente-abas" role="tablist" aria-label="Área do cliente">
            <button type="button" class="cliente-aba is-ativa" role="tab" aria-selected="true"
                    aria-controls="painel-agendar" id="aba-agendar" data-aba="agendar">
                <i class="bi bi-calendar-plus" aria-hidden="true"></i> Agendar
            </button>
            <button type="button" class="cliente-aba" role="tab" aria-selected="false"
                    aria-controls="painel-meus" id="aba-meus" data-aba="meus">
                <i class="bi bi-calendar-check" aria-hidden="true"></i> Meus agendamentos
            </button>
        </div>

        <div id="painel-agendar" role="tabpanel" aria-labelledby="aba-agendar">
            <section class="cliente-card" id="bloco-servico">
                <h2 class="cliente-card-titulo" id="rotulo-servico">1. Escolha o serviço</h2>
                <div class="cliente-dropdown" id="dropdown-servico">
                    <button type="button" class="cliente-dropdown-gatilho" id="gatilho-servico"
                            aria-expanded="false">
                        <span class="cliente-dropdown-conteudo" id="conteudo-servico">
                            <span class="cliente-dropdown-placeholder">Selecione o serviço</span>
                        </span>
                        <i class="bi bi-chevron-down cliente-dropdown-seta" aria-hidden="true"></i>
                    </button>
                    <div class="cliente-dropdown-painel" id="opcoes-servico" role="group"
                         aria-labelledby="rotulo-servico" hidden>
                        <p class="cliente-vazio">Carregando serviços…</p>
                    </div>
                </div>
            </section>

            <section class="cliente-card" id="bloco-profissional" hidden>
                <h2 class="cliente-card-titulo" id="rotulo-profissional">2. Escolha o profissional</h2>
                <div class="cliente-dropdown" id="dropdown-profissional">
                    <button type="button" class="cliente-dropdown-gatilho" id="gatilho-profissional"
                            aria-expanded="false">
                        <span class="cliente-dropdown-conteudo" id="conteudo-profissional">
                            <span class="cliente-dropdown-placeholder">Selecione o profissional</span>
                        </span>
                        <i class="bi bi-chevron-down cliente-dropdown-seta" aria-hidden="true"></i>
                    </button>
                    <div class="cliente-dropdown-painel" id="opcoes-profissional" role="group"
                         aria-labelledby="rotulo-profissional" hidden></div>
                </div>
            </section>

            <section class="cliente-card" id="bloco-data" hidden>
                <h2 class="cliente-card-titulo" id="rotulo-data">3. Escolha o dia</h2>
                <div class="cliente-calendario" id="calendario">
                    <div class="cliente-calendario-topo">
                        <button type="button" class="cliente-calendario-nav" data-calendario="anterior"
                                aria-label="Mês anterior">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </button>
                        <p class="cliente-calendario-mes" id="calendario-mes" aria-live="polite"></p>
                        <button type="button" class="cliente-calendario-nav" data-calendario="proximo"
                                aria-label="Próximo mês">
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="cliente-calendario-semana" aria-hidden="true">
                        <span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span>
                    </div>
                    <div class="cliente-calendario-grade" id="calendario-grade" role="group"
                         aria-labelledby="rotulo-data calendario-mes"></div>
                    <p class="form-text" id="dica-data"></p>
                </div>
            </section>

            <section class="cliente-card" id="bloco-horarios" hidden>
                <h2 class="cliente-card-titulo">4. Escolha o horário</h2>
                <div id="lista-horarios" class="cliente-horarios" aria-live="polite"></div>
                <p class="cliente-vazio" id="horarios-vazio" hidden></p>
            </section>

            <section class="cliente-card" id="bloco-confirmar" hidden>
                <h2 class="cliente-card-titulo">5. Confirme</h2>
                <dl class="cliente-resumo" id="resumo-agendamento"></dl>
                <div class="cliente-campo">
                    <label for="campo-observacoes">Alguma observação? (opcional)</label>
                    <textarea class="form-control" id="campo-observacoes" rows="2" maxlength="500"></textarea>
                </div>
                <button type="button" class="btn btn-primary cliente-botao-largo" data-acao="confirmar">
                    <span class="cliente-botao-texto">Confirmar agendamento</span>
                    <span class="spinner-border spinner-border-sm d-none" data-carregando aria-hidden="true"></span>
                </button>
            </section>

            <section class="cliente-card cliente-sucesso" id="bloco-sucesso" hidden>
                <p class="cliente-icone-sucesso" aria-hidden="true"><i class="bi bi-check-circle"></i></p>
                <h2 class="cliente-card-titulo">Agendamento confirmado</h2>
                <dl class="cliente-resumo" id="resumo-sucesso"></dl>
                <div class="cliente-acoes-secundarias cliente-acoes-coluna">
                    <button type="button" class="btn btn-outline-primary" data-acao="baixar-ics">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i> Adicionar ao calendário
                    </button>
                    <button type="button" class="btn btn-link" data-acao="novo-agendamento">Agendar outro horário</button>
                </div>
            </section>
        </div>

        <div id="painel-meus" role="tabpanel" aria-labelledby="aba-meus" hidden>
            <section class="cliente-card">
                <h2 class="cliente-card-titulo">Próximos agendamentos</h2>
                <div id="lista-proximos" aria-live="polite">
                    <p class="cliente-vazio">Carregando…</p>
                </div>
            </section>
            <section class="cliente-card">
                <h2 class="cliente-card-titulo">Histórico</h2>
                <div id="lista-historico" aria-live="polite">
                    <p class="cliente-vazio">Carregando…</p>
                </div>
            </section>
        </div>
    </section>
</main>

<footer class="cliente-rodape">
    <div class="container">
        <p>Agendamento online · <?= $esc($nomeEstabelecimento) ?></p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"></script>
<script defer src="js/cliente.js?v=20261121-cl14"></script>
</body>
</html>
