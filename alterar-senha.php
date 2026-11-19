<?php
declare(strict_types=1);
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/alteracao-senha.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($metodo === 'GET') {
    // Links antigos continuam válidos, mas não reabrem a interface com três abas.
    header('Location: config.php?aba=conta', true, 302);
    exit;
}
if ($metodo !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}
if (!agenday_valida_csrf()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Sessão de segurança expirada. Volte às Configurações e tente novamente.';
    exit;
}

$erro = agenday_senha_validar_formulario($_POST);
if ($erro !== null) {
    $feedback = ['tipo' => 'danger', 'texto' => $erro];
} else {
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
    $senhaAtual = $_POST['senha_atual'];
    $novaSenha = $_POST['nova_senha'];

    try {
        require_once __DIR__ . '/conexao.php';
        $consulta = $conexao->prepare(
            'SELECT senha_estabelecimento FROM estabelecimento
             WHERE id_estabelecimento = ? LIMIT 1'
        );
        $consulta->bind_param('i', $idEstabelecimento);
        $consulta->execute();
        $consulta->bind_result($hashAtual);
        $encontrado = $consulta->fetch();
        $consulta->close();

        if (!$encontrado || !is_string($hashAtual)
            || !password_verify($senhaAtual, $hashAtual)) {
            $feedback = ['tipo' => 'danger', 'texto' => 'Senha atual incorreta.'];
        } elseif (password_verify($novaSenha, $hashAtual)) {
            $feedback = [
                'tipo' => 'danger',
                'texto' => 'A nova senha deve ser diferente da senha atual.',
            ];
        } else {
            $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
            // Evita sobrescrever uma troca de senha simultânea.
            $atualizacao = $conexao->prepare(
                'UPDATE estabelecimento SET senha_estabelecimento = ?
                 WHERE id_estabelecimento = ? AND BINARY senha_estabelecimento = ?'
            );
            $atualizacao->bind_param('sis', $novoHash, $idEstabelecimento, $hashAtual);
            $atualizacao->execute();
            $alterados = $atualizacao->affected_rows;
            $atualizacao->close();

            if ($alterados === 1) {
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $feedback = ['tipo' => 'success', 'texto' => 'Senha alterada com sucesso.'];
            } else {
                $feedback = [
                    'tipo' => 'danger',
                    'texto' => 'Não foi possível atualizar a senha. Tente novamente.',
                ];
            }
        }
    } catch (Throwable $e) {
        // Nunca registrar senhas, hashes ou credenciais em logs.
        error_log('AgenDay: erro no processamento de alteração de senha.');
        $feedback = [
            'tipo' => 'danger',
            'texto' => 'Não foi possível alterar a senha agora. Tente novamente.',
        ];
    }
}

// POST/Redirect/GET: mensagens sem expor senha na URL, histórico ou HTML.
$_SESSION['feedback_alteracao_senha'] = $feedback;
header('Location: config.php?aba=conta', true, 303);
exit;
