<?php
/**
 * Executar sem banco: php tests/alteracao-senha-validacao.php
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/alteracao-senha.php';

$validos = [
    'senha_atual' => 'SenhaAnterior#2026',
    'nova_senha' => 'NovaSenha#2026',
    'confirmar_nova_senha' => 'NovaSenha#2026',
];

$total = 0;
function qa02_verificar(bool $condicao, string $nome): void
{
    global $total;
    $total++;
    if (!$condicao) {
        throw new RuntimeException('Falha QA-02: ' . $nome);
    }
}

qa02_verificar(agenday_senha_validar_formulario($validos) === null, 'Campos válidos');

$casos = [
    'Sem senha atual' => ['senha_atual' => ''],
    'Senha atual enviada como array' => ['senha_atual' => ['invalido']],
    'Nova senha enviada como array' => ['nova_senha' => ['invalido']],
    'Confirmação enviada como array' => ['confirmar_nova_senha' => ['invalido']],
    'Nova senha ausente' => ['nova_senha' => null],
    'Confirmação em branco' => ['confirmar_nova_senha' => ''],
    'Nova senha curta' => ['nova_senha' => 'curta', 'confirmar_nova_senha' => 'curta'],
    'Nova senha além de 72 bytes' => [
        'nova_senha' => str_repeat('X', 73),
        'confirmar_nova_senha' => str_repeat('X', 73),
    ],
    'Nova senha UTF-8 além de 72 bytes' => [
        'nova_senha' => str_repeat('á', 37),
        'confirmar_nova_senha' => str_repeat('á', 37),
    ],
    'Confirmação diferente' => ['confirmar_nova_senha' => 'OutraSenha#2026'],
    'Mesma senha anterior' => [
        'nova_senha' => 'SenhaAnterior#2026',
        'confirmar_nova_senha' => 'SenhaAnterior#2026',
    ],
    'Senha atual extremamente longa' => ['senha_atual' => str_repeat('Z', 4097)],
];

foreach ($casos as $nome => $alteracao) {
    qa02_verificar(
        agenday_senha_validar_formulario(array_replace($validos, $alteracao)) !== null,
        $nome
    );
}

$limite = array_replace($validos, [
    'nova_senha' => str_repeat('A', 72),
    'confirmar_nova_senha' => str_repeat('A', 72),
]);
qa02_verificar(agenday_senha_validar_formulario($limite) === null, '72 bytes válidos');

$hash = password_hash($validos['nova_senha'], PASSWORD_DEFAULT);
qa02_verificar(password_verify($validos['nova_senha'], $hash), 'Hash aceita senha nova');
qa02_verificar(!password_verify($validos['senha_atual'], $hash), 'Hash rejeita senha antiga');

echo 'OK: ' . $total . ' verificações QA-02.' . PHP_EOL;
