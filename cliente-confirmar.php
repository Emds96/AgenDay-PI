<?php
/**
 * AgenDay 2.0 — confirmação de presença pelo link do lembrete.
 *
 * O token público identifica um único agendamento. Abrir o link não altera
 * nada; a confirmação exige o POST assinado do botão desta página.
 */
declare(strict_types=1);

require_once __DIR__ . '/lib/portal-cliente.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$esc = static fn($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

function agenday_confirmar_pagina(
    string $titulo,
    string $mensagem,
    string $icone,
    bool $sucesso,
    int $codigo = 200,
    string $estabelecimento = '',
    array $resumo = []
): void {
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
    <link rel="stylesheet" href="css/cliente.css?v=20261123-spinner-grid">
</head>
<body class="agenday-auth agenday-portal">
    <main class="container cliente-main">
        <section class="cliente-card text-center cliente-resultado">
            <p class="cliente-resultado-icone <?= $sucesso ? 'cliente-resultado-icone-ok' : 'cliente-resultado-icone-erro' ?>"
               aria-hidden="true">
                <i class="bi <?= $esc($icone) ?>"></i>
            </p>
            <?php if ($estabelecimento !== ''): ?>
                <p class="cliente-resultado-estabelecimento"><?= $esc($estabelecimento) ?></p>
            <?php endif; ?>
            <h1 class="cliente-resultado-titulo"><?= $esc($titulo) ?></h1>
            <p class="cliente-resultado-texto"><?= $esc($mensagem) ?></p>
            <?php if ($resumo !== []): ?>
                <dl class="cliente-resumo cliente-resultado-resumo">
                    <?php foreach ($resumo as $rotulo => $valor): ?>
                        <dt><?= $esc($rotulo) ?></dt>
                        <dd><?= $esc($valor) ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
    <?php
    exit;
}

$token = $_GET['t'] ?? null;
if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    agenday_confirmar_pagina(
        'Link inválido',
        'Este link de confirmação não é válido. Abra a mensagem recebida no WhatsApp.',
        'bi-link-45deg',
        false,
        404
    );
}

try {
    require_once __DIR__ . '/conexao.php';
} catch (Throwable $e) {
    error_log('AgenDay confirmação: falha ao conectar (' . get_class($e) . ').');
    agenday_confirmar_pagina(
        'Indisponível agora',
        'Não foi possível abrir a confirmação neste momento. Tente novamente em instantes.',
        'bi-wifi-off',
        false,
        503
    );
}

$stmt = $conexao->prepare(
    'SELECT a.id_agendamento, a.id_estabelecimento, a.data_inicio, a.status,
            s.nome_servico, f.nome_funcionario, e.nome_estabelecimento
       FROM agendamentos a
       INNER JOIN servicos s
          ON s.id_estabelecimento = a.id_estabelecimento AND s.id_servicos = a.id_servicos
       INNER JOIN funcionarios f
          ON f.id_estabelecimento = a.id_estabelecimento AND f.id_funcionario = a.id_funcionario
       INNER JOIN estabelecimento e ON e.id_estabelecimento = a.id_estabelecimento
      WHERE a.token_publico = ?
      LIMIT 1'
);
$stmt->bind_param('s', $token);
$stmt->execute();
$agendamento = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$agendamento) {
    agenday_confirmar_pagina(
        'Agendamento não encontrado',
        'Este link não corresponde a um agendamento ativo.',
        'bi-calendar-x',
        false,
        404
    );
}

$nomeEstabelecimento = trim((string) $agendamento['nome_estabelecimento']) ?: 'Estabelecimento';
$inicio = (string) $agendamento['data_inicio'];
$fuso = agenday_portal_fuso();
$dataObjeto = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $inicio, $fuso);
$dataTexto = $dataObjeto !== false ? $dataObjeto->format('d/m/Y \à\s H:i') : $inicio;
$agora = new DateTimeImmutable('now', $fuso);
$resumo = [
    'Serviço' => (string) $agendamento['nome_servico'],
    'Profissional' => (string) $agendamento['nome_funcionario'],
    'Data' => $dataTexto,
];

if ((string) $agendamento['status'] === 'confirmado') {
    agenday_confirmar_pagina(
        'Presença já confirmada',
        'Seu horário está confirmado. Até lá!',
        'bi-check-circle',
        true,
        200,
        $nomeEstabelecimento,
        $resumo
    );
}
if ((string) $agendamento['status'] !== 'agendado' || ($dataObjeto !== false && $dataObjeto <= $agora)) {
    agenday_confirmar_pagina(
        'Não é mais possível confirmar',
        'Este agendamento não está mais aguardando confirmação. Fale com o estabelecimento se precisar.',
        'bi-info-circle',
        false,
        409,
        $nomeEstabelecimento
    );
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!agenday_portal_confirmacao_autorizada($token, $_POST)) {
        agenday_confirmar_pagina(
            'Pedido não reconhecido',
            'Recarregue esta página e tente novamente.',
            'bi-shield-exclamation',
            false,
            403,
            $nomeEstabelecimento
        );
    }

    $agoraBanco = $agora->format('Y-m-d H:i:s');
    $stmt = $conexao->prepare(
        "UPDATE agendamentos
            SET status = 'confirmado'
          WHERE token_publico = ? AND status = 'agendado' AND data_inicio > ?"
    );
    $stmt->bind_param('ss', $token, $agoraBanco);
    $stmt->execute();
    $alterados = $stmt->affected_rows;
    $stmt->close();

    if ($alterados !== 1) {
        agenday_confirmar_pagina(
            'Não foi possível confirmar',
            'Este agendamento foi alterado. Abra novamente o link recebido para conferir a situação.',
            'bi-exclamation-circle',
            false,
            409,
            $nomeEstabelecimento
        );
    }

    agenday_confirmar_pagina(
        'Presença confirmada',
        'Tudo certo! Seu horário está confirmado.',
        'bi-check-circle',
        true,
        200,
        $nomeEstabelecimento,
        $resumo
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Confirmar presença — <?= $esc($nomeEstabelecimento) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/cliente.css?v=20261123-spinner-grid">
</head>
<body class="agenday-auth agenday-portal">
    <main class="container cliente-main">
        <section class="cliente-card">
            <p class="cliente-resultado-estabelecimento"><?= $esc($nomeEstabelecimento) ?></p>
            <h1 class="cliente-titulo">Confirmar presença</h1>
            <dl class="cliente-resumo">
                <dt>Serviço</dt>
                <dd><?= $esc($agendamento['nome_servico']) ?></dd>
                <dt>Profissional</dt>
                <dd><?= $esc($agendamento['nome_funcionario']) ?></dd>
                <dt>Data</dt>
                <dd><?= $esc($dataTexto) ?></dd>
            </dl>
            <p class="cliente-texto">Você confirma que poderá comparecer a este horário?</p>
            <form method="post" action="cliente-confirmar.php?t=<?= $esc($token) ?>">
                <input type="hidden" name="assinatura" value="<?= $esc(agenday_portal_assinatura_confirmacao($token)) ?>">
                <button type="submit" class="btn btn-primary cliente-botao-largo">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    Confirmar presença
                </button>
            </form>
        </section>
    </main>
</body>
</html>
