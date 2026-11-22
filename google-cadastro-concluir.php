<?php
declare(strict_types=1);

require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/lib/portal-cliente.php';

function agenday_google_erro_cadastro(string $mensagem): never
{
    $_SESSION['erro_google_cadastro'] = $mensagem;
    header('Location: index.php', true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.php', true, 302);
    exit;
}

$pendente = $_SESSION['google_pending'] ?? null;
if (!is_array($pendente)
    || !isset($pendente['sub'], $pendente['email'], $pendente['created_at'])
    || !is_string($pendente['sub']) || !is_string($pendente['email'])
    || !is_int($pendente['created_at']) || time() - $pendente['created_at'] > 600) {
    unset($_SESSION['google_pending']);
    $_SESSION['erro_google_login'] = 'Seu primeiro acesso expirou. Entre novamente com Google.';
    header('Location: index.php', true, 303);
    exit;
}

$nome = $_POST['estabelecimento'] ?? null;
$contato = $_POST['contato_estabelecimento'] ?? null;
$senha = $_POST['senha'] ?? null;
$confirmacao = $_POST['confirmar_senha'] ?? null;
$semSenha = ($_POST['sem_senha'] ?? null) === '1';

if (!agenday_valida_csrf() || !is_string($nome) || !is_string($contato)
    || !is_string($senha) || !is_string($confirmacao)) {
    agenday_google_erro_cadastro('Não foi possível validar o formulário. Confira os dados.');
}

$nome = trim($nome);
$contato = trim($contato);
if ($nome === '' || mb_strlen($nome) > 120
    || !filter_var($pendente['email'], FILTER_VALIDATE_EMAIL)
    || strlen($pendente['email']) > 190) {
    agenday_google_erro_cadastro('Informe um nome de estabelecimento válido (até 120 caracteres).');
}

// Telefone obrigatório no cadastro: é ele que aparece na mensagem de
// lembrete ao cliente. Contas antigas continuam podendo ficar sem número.
require_once __DIR__ . '/lib/configuracao-estabelecimento.php';
if (!agenday_telefone_estabelecimento_valido($contato, true)) {
    agenday_google_erro_cadastro('Informe o telefone do estabelecimento com DDD (8 a 15 dígitos).');
}

$hash = null;
if (!$semSenha && $senha !== '') {
    if (strlen($senha) < 8 || strlen($senha) > 72
        || !hash_equals($senha, $confirmacao)) {
        agenday_google_erro_cadastro('Confira a senha do AgenDay e sua confirmação (8 a 72 bytes).');
    }
    $hash = password_hash($senha, PASSWORD_DEFAULT);
}

try {
    require_once __DIR__ . '/conexao.php';
    $conexao->begin_transaction();

    // Uma conta Google nova cria somente seu próprio estabelecimento.
    // UNIQUE(email_estabelecimento) e UNIQUE(google_sub) garantem que
    // acessos simultâneos não criem duplicatas; rollback preserva integridade.
    $stmt = $conexao->prepare(
        'INSERT INTO estabelecimento
            (nome_estabelecimento, email_estabelecimento, senha_estabelecimento, contato_estabelecimento)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('ssss', $nome, $pendente['email'], $hash, $contato);
    $stmt->execute();
    $id = (int) $conexao->insert_id;
    $stmt->close();

    $stmt = $conexao->prepare(
        'INSERT INTO estabelecimento_google (google_sub, id_estabelecimento, email_google)
         VALUES (?, ?, ?)'
    );
    $stmt->bind_param('sis', $pendente['sub'], $id, $pendente['email']);
    $stmt->execute();
    $stmt->close();

    // Mesmo slug automático do cadastro comum. Falha aqui não impede a conta
    // de ser criada: o endereço pode ser definido depois em Configurações.
    try {
        agenday_portal_slug_gravar($conexao, $id, $nome);
    } catch (Throwable $e) {
        error_log('AgenDay Google: slug público não gerado (' . get_class($e) . ').');
    }

    // Mesma base de tempo do cadastro comum: a conta nasce com os 7 dias
    // gravados, sem sobrescrever nada e sem derrubar o cadastro se a
    // migração de horário ainda não existir.
    require_once __DIR__ . '/lib/horario-funcionamento.php';
    agenday_horario_criar_padrao($conexao, $id);

    $conexao->commit();

    session_regenerate_id(true);
    $_SESSION['id_estabelecimento'] = $id;
    $_SESSION['nome_estabelecimento'] = $nome;
    $_SESSION['email_estabelecimento'] = $pendente['email'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['google_pending'], $_SESSION['erro_google_cadastro'], $_SESSION['erro_google_login']);
    header('Location: agenda.php', true, 303);
    exit;
} catch (mysqli_sql_exception $e) {
    if (isset($conexao)) {
        try {
            $conexao->rollback();
        } catch (mysqli_sql_exception $ignored) {
            error_log('AgenDay Google cadastro: rollback falhou ' . $ignored->getCode());
        }
    }
    if ($e->getCode() === 1062) {
        agenday_google_erro_cadastro(
            'Este e-mail ou Google já está cadastrado. Faça login na conta existente.'
        );
    }
    error_log('AgenDay Google cadastro: erro SQL ' . $e->getCode());
    agenday_google_erro_cadastro('Não foi possível concluir o cadastro. Tente novamente.');
}
