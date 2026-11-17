<?php
declare(strict_types=1);

/**
 * Validação de troca de senha do estabelecimento autenticado.
 * Não acessa a sessão ou o banco; útil para testes sem MySQL.
 * O limite de 72 bytes é o mesmo aplicado no cadastro existente.
 */
function agenday_senha_validar_formulario(array $dados): ?string
{
    $atual = $dados['senha_atual'] ?? null;
    $nova = $dados['nova_senha'] ?? null;
    $confirmacao = $dados['confirmar_nova_senha'] ?? null;

    if (!is_string($atual) || !is_string($nova) || !is_string($confirmacao)) {
        return 'Preencha os três campos de senha.';
    }

    if ($atual === '' || $nova === '' || $confirmacao === '') {
        return 'Preencha os três campos de senha.';
    }

    if (strlen($atual) > 4096) {
        return 'Não foi possível validar a senha atual.';
    }

    if (strlen($nova) < 8 || strlen($nova) > 72) {
        return 'A nova senha deve ter entre 8 e 72 bytes.';
    }

    if (!hash_equals($nova, $confirmacao)) {
        return 'A confirmação não corresponde à nova senha.';
    }

    if (hash_equals($atual, $nova)) {
        return 'A nova senha deve ser diferente da senha atual.';
    }

    return null;
}
