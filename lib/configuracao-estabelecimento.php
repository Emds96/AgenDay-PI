<?php
declare(strict_types=1);

/**
 * Regra única do telefone comercial: até 20 caracteres, só dígitos e
 * separadores comuns e 8 a 15 dígitos.
 *
 * `$obrigatorio` é usado no cadastro de contas novas, onde o número é
 * exigido porque aparece na mensagem de lembrete ao cliente. Em
 * Configurações o campo continua opcional para não travar contas antigas.
 */
function agenday_telefone_estabelecimento_valido(string $contato, bool $obrigatorio = false): bool
{
    $contato = trim($contato);
    if ($contato === '') {
        return !$obrigatorio;
    }
    if (strlen($contato) > 20
        || !preg_match('/\A[0-9()+.\s-]+\z/D', $contato)) {
        return false;
    }
    $totalDigitos = preg_match_all('/[0-9]/', $contato);

    return $totalDigitos >= 8 && $totalDigitos <= 15;
}

/**
 * Valida somente os campos editáveis nesta primeira etapa.
 * O e-mail e o ID do estabelecimento nunca vêm do formulário.
 */
function agenday_config_validar(string $nome, string $contato): ?string
{
    $nome = trim($nome);
    $contato = trim($contato);

    if (!preg_match('/\A[^\x00-\x1F\x7F]{2,120}\z/u', $nome)
        || !preg_match('/\S/u', $nome)) {
        return 'Informe um nome de estabelecimento válido (2 a 120 caracteres).';
    }

    if (!agenday_telefone_estabelecimento_valido($contato)) {
        if (strlen($contato) > 20
            || !preg_match('/\A[0-9()+.\s-]+\z/D', $contato)) {
            return 'Informe um contato válido com até 20 caracteres.';
        }
        return 'Informe um telefone com 8 a 15 dígitos ou deixe o contato vazio.';
    }

    return null;
}

function agenday_config_html(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
