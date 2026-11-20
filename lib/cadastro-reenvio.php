<?php
declare(strict_types=1);

/**
 * Identificador de uso único por formulário de cadastro.
 * A sessão PHP permanece aberta até o fim do POST: dois envios do mesmo
 * navegador são serializados pelo bloqueio de sessão padrão do PHP.
 * Não substitui o token CSRF nem cria restrição por nome/telefone.
 */
const AGENDAY_CADASTRO_TOKEN_TTL = 1800;
const AGENDAY_CADASTRO_TOKEN_LIMITE = 12;

function agenday_cadastro_tipo_valido(string $tipo): bool
{
    return in_array($tipo, ['clientes', 'funcionarios'], true);
}

function agenday_cadastro_tokens_validos(string $tipo): array
{
    $armazenados = $_SESSION['agenday_cadastro_tokens'][$tipo] ?? [];
    if (!is_array($armazenados)) {
        return [];
    }
    $agora = time();
    $validos = [];
    foreach ($armazenados as $token => $criadoEm) {
        if (is_string($token) && preg_match('/^[a-f0-9]{32}$/D', $token)
            && is_int($criadoEm) && $criadoEm <= $agora
            && ($agora - $criadoEm) <= AGENDAY_CADASTRO_TOKEN_TTL) {
            $validos[$token] = $criadoEm;
        }
    }
    return array_slice($validos, -AGENDAY_CADASTRO_TOKEN_LIMITE, null, true);
}

function agenday_cadastro_token_emitir(string $tipo): string
{
    if (!agenday_cadastro_tipo_valido($tipo)) {
        throw new InvalidArgumentException('Tipo de cadastro inválido.');
    }

    $tokens = agenday_cadastro_tokens_validos($tipo);
    $token = bin2hex(random_bytes(16));
    $tokens[$token] = time();
    $_SESSION['agenday_cadastro_tokens'][$tipo] =
        array_slice($tokens, -AGENDAY_CADASTRO_TOKEN_LIMITE, null, true);

    return $token;
}

function agenday_cadastro_token_consumir(string $tipo, mixed $token): bool
{
    if (!agenday_cadastro_tipo_valido($tipo) || !is_string($token)
        || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
        return false;
    }

    $tokens = agenday_cadastro_tokens_validos($tipo);
    if (!array_key_exists($token, $tokens)) {
        $_SESSION['agenday_cadastro_tokens'][$tipo] = $tokens;
        return false;
    }

    // Remover antes de qualquer upload ou INSERT. Um replay não pode inserir de novo.
    unset($tokens[$token]);
    $_SESSION['agenday_cadastro_tokens'][$tipo] = $tokens;
    return true;
}
