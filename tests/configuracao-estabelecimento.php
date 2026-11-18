<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/configuracao-estabelecimento.php';

$casos = [
    ['Nome válido', '(92) 99999-9999', true],
    ['Empresa Árvore', '+55 (92) 99999-9999', true],
    ['Empresa de Testes', '', true],
    ['', '', false],
    ['A', '', false],
    [str_repeat('a', 121), '', false],
    ["Nome\nInvalido", '', false],
    ['      ', '', false],
    ['Empresa boa', '123', false],
    ['Empresa boa', '12345678', true],
    ['Empresa boa', 'abcde12345678', false],
    ['Empresa boa', '1234567890123456', false],
];
$aprovados = 0;
foreach ($casos as [$nome, $contato, $esperado]) {
    $valido = agenday_config_validar($nome, $contato) === null;
    if ($valido !== $esperado) {
        fwrite(STDERR, "FAIL: " . var_export([$nome, $contato], true) . PHP_EOL);
        exit(1);
    }
    $aprovados++;
}
if (agenday_config_html('<script>"x"</script>') !== '&lt;script&gt;&quot;x&quot;&lt;/script&gt;') {
    fwrite(STDERR, "FAIL: escaping HTML" . PHP_EOL);
    exit(1);
}
echo "Configuração do estabelecimento: " . ($aprovados + 1) . " testes aprovados." . PHP_EOL;
