<?php
/**
 * AgenDay 2.0 — cancelamento pelo link enviado no WhatsApp.
 *
 * Aberto por cliente-cancelar.php?t=<token de 64 caracteres>. Não exige login:
 * o próprio token aleatório identifica o agendamento. A escrita é POST com
 * conferência de origem, e o prazo de cancelamento é o mesmo da agenda.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/portal-cliente.php';
require_once __DIR__ . '/lib/agendamento-cancelar.php';
require_once __DIR__ . '/lib/whatsapp.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$esc = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

/** Desenha a página de resultado e encerra. */
function agenday_cancelar_pagina(string $titulo, string $mensagem, string $icone, bool $sucesso, int $codigo = 200): void
{
    http_response_code($codigo);
    $esc = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $esc($titulo) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/cliente.css?v=20261121-cl01">
</head>
<body class="agenday-auth agenday-portal">
    <main class="container cliente-main">
        <section class="cliente-card text-center">
            <p class="<?= $sucesso ? 'cliente-icone-sucesso' : 'cliente-icone-erro' ?>" aria-hidden="true">
                <i class="bi <?= $esc($icone) ?>"></i>
            </p>
            <h1 class="cliente-titulo"><?= $esc($titulo) ?></h1>
            <p class="cliente-texto"><?= $esc($mensagem) ?></p>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}

$token = $_GET['t'] ?? null;
if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    agenday_cancelar_pagina(
        'Link inválido',
        'Este link de cancelamento não é válido. Abra a mensagem recebida no WhatsApp ou entre no portal com o seu celular.',
        'bi-link-45deg',
        false,
        404
    );
}

try {
    require_once __DIR__ . '/conexao.php';
} catch (Throwable $e) {
    error_log('AgenDay cancelamento: falha ao conectar (' . get_class($e) . ').');
    agenday_cancelar_pagina(
        'Indisponível agora',
        'Não foi possível abrir o cancelamento neste momento. Tente novamente em instantes.',
        'bi-wifi-off',
        false,
        503
    );
}

$stmt = $conexao->prepare(
    'SELECT a.id_agendamento, a.id_estabelecimento, a.id_cliente, a.data_inicio, a.data_fim, a.status,
            s.nome_servico, f.nome_funcionario,
            e.nome_estabelecimento, e.horas_cancelamento, e.slug, e.dominio
       FROM agendamentos a
       INNER JOIN servicos s
          ON s.id_estabelecimento = a.id_estabelecimento AND s.id_servicos = a.id_servicos
       INNER JOIN funcionarios f
          ON f.id_estabelecimento = a.id_estabelecimento AND f.id_funcionario = a.id_funcionario
       INNER JOIN estabelecimento e
          ON e.id_estabelecimento = a.id_estabelecimento
      WHERE a.token_publico = ?
      LIMIT 1'
);
$stmt->bind_param('s', $token);
$stmt->execute();
$agendamento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$agendamento) {
    agenday_cancelar_pagina(
        'Agendamento não encontrado',
        'Este link não corresponde a nenhum agendamento. Se você já cancelou, não é preciso fazer nada.',
        'bi-calendar-x',
        false,
        404
    );
}

$empresa = (int) $agendamento['id_estabelecimento'];
$nomeEstabelecimento = trim((string) $agendamento['nome_estabelecimento']) ?: 'Estabelecimento';
$slugPortal = trim((string) ($agendamento['slug'] ?? ''));
$inicio = (string) $agendamento['data_inicio'];
$fuso = agenday_portal_fuso();
$dataObjeto = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $inicio, $fuso);
$dataTexto = $dataObjeto !== false ? $dataObjeto->format('d/m/Y \à\s H:i') : $inicio;
$ativo = $agendamento['status'] === 'agendado' || $agendamento['status'] === 'confirmado';

if (!$ativo) {
    $rotulo = match ((string) $agendamento['status']) {
        'cancelado' => 'Este agendamento já foi cancelado.',
        'concluido' => 'Este atendimento já foi concluído.',
        'faltou' => 'Este atendimento foi registrado como falta.',
        default => 'Este agendamento não está mais ativo.',
    };
    agenday_cancelar_pagina(
        'Nada a cancelar',
        $rotulo . ' Se precisar de um novo horário, fale com ' . $nomeEstabelecimento . '.',
        'bi-info-circle',
        true
    );
}

$janela = agenday_portal_pode_cancelar((int) $agendamento['horas_cancelamento'], $inicio);

/* ---------------------------------------------------------------------------
 * Confirmação (POST)
 * ------------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    /*
     * Sem sessão nesta página, a proteção do POST é a assinatura derivada do
     * token, que o navegador reenvia no campo oculto. O cabeçalho Origin é
     * conferido só quando o navegador o envia: alguns navegadores móveis não
     * mandam Origin nem Referer (a página usa Referrer-Policy: no-referrer),
     * e exigir o cabeçalho fazia o botão falhar com "Pedido não reconhecido".
     */
    $assinatura = $_POST['assinatura'] ?? '';
    $assinaturaOk = is_string($assinatura) && $assinatura !== ''
        && hash_equals(agenday_portal_assinatura_cancelamento($token), $assinatura);
    $origemEnviada = ($_SERVER['HTTP_ORIGIN'] ?? '') !== '' || ($_SERVER['HTTP_REFERER'] ?? '') !== '';
    if (!$assinaturaOk || ($origemEnviada && !agenday_portal_origem_valida())) {
        agenday_cancelar_pagina(
            'Pedido não reconhecido',
            'Recarregue esta página e tente novamente.',
            'bi-shield-exclamation',
            false,
            403
        );
    }
    if (!$janela['pode']) {
        agenday_cancelar_pagina(
            'Fora do prazo de cancelamento',
            $janela['motivo'] . ' Fale diretamente com ' . $nomeEstabelecimento . '.',
            'bi-clock-history',
            false,
            409
        );
    }

    try {
        agenday_agenda_cancelar_para($conexao, $empresa, (int) $agendamento['id_agendamento']);
    } catch (DomainException $e) {
        agenday_cancelar_pagina('Não foi possível cancelar', $e->getMessage(), 'bi-exclamation-circle', false, 409);
    } catch (Throwable $e) {
        error_log('AgenDay cancelamento: falha ao cancelar (' . get_class($e) . ').');
        agenday_cancelar_pagina(
            'Não foi possível cancelar',
            'Tente novamente em instantes ou fale diretamente com ' . $nomeEstabelecimento . '.',
            'bi-exclamation-circle',
            false,
            503
        );
    }

    // Aviso ao cliente pela fila do WhatsApp (não bloqueia a confirmação).
    $stmt = $conexao->prepare('SELECT telefone_digits FROM clientes WHERE id_estabelecimento = ? AND id_cliente = ? LIMIT 1');
    if ($stmt) {
        $idCliente = (int) $agendamento['id_cliente'];
        $stmt->bind_param('ii', $empresa, $idCliente);
        $stmt->execute();
        $stmt->bind_result($telefoneCliente);
        $stmt->fetch();
        $stmt->close();
        if (is_string($telefoneCliente) && $telefoneCliente !== '') {
            $texto = agenday_whatsapp_texto('cancelamento', [
                'estabelecimento' => $nomeEstabelecimento,
                'nome' => '',
                'data_inicio' => $inicio,
                'link_agendar' => agenday_portal_base_url($agendamento),
            ]);
            $idMensagem = agenday_whatsapp_enfileirar(
                $conexao,
                $empresa,
                (int) $agendamento['id_agendamento'],
                $telefoneCliente,
                'cancelamento',
                $texto
            );
            try {
                // Envia na hora: sem cron, esperar a próxima página poderia deixar a mensagem parada.
                agenday_whatsapp_enviar_agora($conexao, $idMensagem);
            } catch (Throwable $e) {
                error_log('AgenDay cancelamento: fila do WhatsApp falhou (' . get_class($e) . ').');
            }
        }
    }

    agenday_cancelar_pagina(
        'Agendamento cancelado',
        'O horário de ' . $dataTexto . ' foi liberado. Se precisar, você pode agendar novamente pelo portal.',
        'bi-check-circle',
        true
    );
}

/* ---------------------------------------------------------------------------
 * Página de confirmação (GET)
 * ------------------------------------------------------------------------- */
if (!$janela['pode']) {
    agenday_cancelar_pagina(
        'Fora do prazo de cancelamento',
        $janela['motivo'] . ' Fale diretamente com ' . $nomeEstabelecimento . '.',
        'bi-clock-history',
        false,
        409
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Cancelar agendamento — <?= $esc($nomeEstabelecimento) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/cliente.css?v=20261121-cl01">
</head>
<body class="agenday-auth agenday-portal">
    <main class="container cliente-main">
        <section class="cliente-card">
            <p class="cliente-sobretitulo"><?= $esc($nomeEstabelecimento) ?></p>
            <h1 class="cliente-titulo">Cancelar agendamento</h1>
            <dl class="cliente-resumo">
                <dt>Serviço</dt>
                <dd><?= $esc($agendamento['nome_servico']) ?></dd>
                <dt>Profissional</dt>
                <dd><?= $esc($agendamento['nome_funcionario']) ?></dd>
                <dt>Data</dt>
                <dd><?= $esc($dataTexto) ?></dd>
            </dl>
            <p class="cliente-texto">
                Ao cancelar, o horário é liberado para outra pessoa e não pode ser recuperado.
            </p>
            <form method="post" action="cliente-cancelar.php?t=<?= $esc($token) ?>">
                <input type="hidden" name="assinatura" value="<?= $esc(agenday_portal_assinatura_cancelamento($token)) ?>">
                <button type="submit" class="btn btn-danger cliente-botao-largo">
                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                    Confirmar cancelamento
                </button>
            </form>
            <div class="cliente-acoes-secundarias">
                <a class="btn btn-link" href="cliente.php<?= $esc($slugPortal !== '' ? '?e=' . rawurlencode($slugPortal) : '') ?>">Voltar sem cancelar</a>
            </div>
        </section>
    </main>
</body>
</html>
