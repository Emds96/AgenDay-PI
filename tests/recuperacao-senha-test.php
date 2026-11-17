<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/recuperacao-senha.php';

function verifica(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        fwrite(STDERR, "FALHOU: {$mensagem}\n");
        exit(1);
    }
}

$t1 = agenday_recuperacao_novo_token();
$t2 = agenday_recuperacao_novo_token();
verifica(agenday_recuperacao_token_valido($t1), 'token gerado valido');
verifica(strlen($t1) === 64, 'token com 32 bytes aleatorios');
verifica($t1 !== $t2, 'tokens distintos');
verifica(!agenday_recuperacao_token_valido(''), 'token vazio rejeitado');
verifica(!agenday_recuperacao_token_valido(str_repeat('a', 63)), 'token curto rejeitado');
verifica(!agenday_recuperacao_token_valido(str_repeat('z', 64)), 'token nao hexadecimal rejeitado');
verifica(strlen(agenday_recuperacao_hash($t1)) === 64, 'hash sha256');
verifica(agenday_recuperacao_hash($t1) !== $t1, 'token nunca armazenado diretamente');

$valida = static function (string $senha, string $confirma): ?string {
    return agenday_recuperacao_validar_senha([
        'nova_senha' => $senha, 'confirmar_nova_senha' => $confirma,
    ]);
};
verifica($valida('12345678', '12345678') === null, 'senha minima aceita');
verifica($valida(str_repeat('a', 72), str_repeat('a', 72)) === null, 'senha maxima aceita');
verifica($valida('1234567', '1234567') !== null, 'senha curta rejeitada');
verifica($valida(str_repeat('a', 73), str_repeat('a', 73)) !== null, 'senha longa rejeitada');
verifica($valida('12345678', '12345679') !== null, 'confirmacao incorreta rejeitada');
verifica(str_starts_with(agenday_recuperacao_url($t1),
    'https://agenday-sistema.infinityfree.io/redefinir-senha.php?token='), 'url com host fixo');

echo "QA-03: testes unitarios OK\n";
