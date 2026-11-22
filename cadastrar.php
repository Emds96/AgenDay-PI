<?php
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/lib/portal-cliente.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php', true, 302);
    exit;
}

$nome = $_POST['estabelecimento'] ?? null;
$email = $_POST['email'] ?? null;
$senha = $_POST['senha'] ?? null;
$confirmacao = $_POST['confirmar_senha'] ?? null;

if (!agenday_valida_csrf() || !is_string($nome) || !is_string($email)
    || !is_string($senha) || !is_string($confirmacao)) {
    $_SESSION['erro_cadastro'] = 'Não foi possível validar o formulário.';
    header('Location: index.php', true, 302);
    exit;
}

$nome = trim($nome);
$email = trim($email);
if ($nome === '' || mb_strlen($nome) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 190 || strlen($senha) < 8 || strlen($senha) > 72
    || !hash_equals($senha, $confirmacao)) {
    $_SESSION['erro_cadastro'] = 'Confira o nome, e-mail e confirmação da senha (8 a 72 bytes).';
    header('Location: index.php', true, 302);
    exit;
}

require_once __DIR__ . '/lib/horario-funcionamento.php';

// Horário resumido do cadastro: mesmos dias/horas para todos os dias marcados.
// O ajuste fino (almoço e horário diferente por dia) fica em Configurações.
$diasMarcados = isset($_POST['dias']) && is_array($_POST['dias']) ? $_POST['dias'] : [];
$abertura = agenday_horario_hora($_POST['abertura'] ?? null);
$fechamento = agenday_horario_hora($_POST['fechamento'] ?? null);

$diasSelecionados = [];
foreach ($diasMarcados as $valorDia) {
    if (is_string($valorDia) && preg_match('/\A[0-6]\z/', $valorDia) === 1) {
        $diasSelecionados[(int) $valorDia] = true;
    }
}

$erroHorario = null;
if ($diasSelecionados === []) {
    $erroHorario = 'Marque pelo menos um dia de funcionamento.';
} elseif ($abertura === null || $fechamento === null) {
    $erroHorario = 'Informe o horário de abertura e de fechamento.';
} elseif (agenday_horario_minutos($abertura) >= agenday_horario_minutos($fechamento)) {
    $erroHorario = 'O horário de fechamento precisa ser depois do de abertura.';
}

if ($erroHorario !== null) {
    $_SESSION['erro_cadastro'] = $erroHorario;
    header('Location: index.php', true, 302);
    exit;
}

$diasFuncionamento = [];
foreach (agenday_dias_semana() as $numeroDia => $rotuloDia) {
    $diasFuncionamento[$numeroDia] = [
        'aberto' => isset($diasSelecionados[$numeroDia]),
        'abertura' => $abertura,
        'fechamento' => $fechamento,
        'almoco_inicio' => '',
        'almoco_fim' => '',
    ];
}

try {
    require_once __DIR__ . '/conexao.php';

    $stmt = $conexao->prepare('SELECT id_estabelecimento FROM estabelecimento WHERE email_estabelecimento = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows > 0;
    $stmt->close();

    if ($existe) {
        $_SESSION['usuario_existe'] = true;
        header('Location: index.php', true, 302);
        exit;
    }

    $hash = password_hash($senha, PASSWORD_DEFAULT);
    $stmt = $conexao->prepare(
        'INSERT INTO estabelecimento (nome_estabelecimento, email_estabelecimento, senha_estabelecimento)
         VALUES (?, ?, ?)'
    );
    $stmt->bind_param('sss', $nome, $email, $hash);
    $stmt->execute();
    $novoId = (int) $conexao->insert_id;
    $stmt->close();

    // Slug público: nasce do nome do estabelecimento ("barbearia-joao"), com
    // sufixo numérico se já existir. Se a migração do portal não estiver
    // aplicada, o cadastro segue normalmente e o endereço fica pendente.
    try {
        agenday_portal_slug_gravar($conexao, $novoId, $nome);
    } catch (Throwable $e) {
        error_log('AgenDay cadastro: slug público não gerado (' . get_class($e) . ').');
    }

    // A conta já nasce com o horário informado no cadastro gravado.
    agenday_horario_criar_padrao($conexao, $novoId, $diasFuncionamento);

    $_SESSION['status_cadastro'] = true;
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        $_SESSION['usuario_existe'] = true;
    } else {
        error_log('AgenDay cadastro: erro SQL ' . $e->getCode());
        $_SESSION['erro_cadastro'] = 'Não foi possível cadastrar agora. Tente novamente.';
    }
}

header('Location: index.php', true, 302);
exit;
