<?php
declare(strict_types=1);

/**
 * Rotinas puras da recuperação de senha: sem acesso ao banco, à sessão ou ao Brevo.
 */
function agenday_recuperacao_novo_token(): string
{
    return bin2hex(random_bytes(32));
}

function agenday_recuperacao_token_valido($token): bool
{
    return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D', $token) === 1;
}

function agenday_recuperacao_hash(string $token): string
{
    return hash('sha256', $token);
}

function agenday_recuperacao_validar_senha(array $dados): ?string
{
    $senha = $dados['nova_senha'] ?? null;
    $confirmacao = $dados['confirmar_nova_senha'] ?? null;

    if (!is_string($senha) || !is_string($confirmacao)
        || $senha === '' || $confirmacao === '') {
        return 'Preencha a nova senha e a confirmação.';
    }

    // Alinhado às regras existentes de cadastro e alteração de senha.
    if (strlen($senha) < 8 || strlen($senha) > 72) {
        return 'A senha deve ter entre 8 e 72 bytes.';
    }

    if (!hash_equals($senha, $confirmacao)) {
        return 'A confirmação não corresponde à nova senha.';
    }

    return null;
}

function agenday_recuperacao_url(string $token): string
{
    // Host fixo: nunca montar links de recuperação com o cabeçalho Host da requisição.
    return 'https://agenday-sistema.infinityfree.io/redefinir-senha.php?token=' . $token;
}
