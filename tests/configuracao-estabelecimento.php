<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/configuracao-estabelecimento.php';

$casos = [
    ['Nome válido', '(92) 99999-9999', true],
    ['Empresa Árvore', '+55 (92) 99999-9999', false],
    ['Empresa de Testes', '', true],
    ['', '', false],
    ['A', '', false],
    [str_repeat('a', 121), '', false],
    ["Nome\nInvalido", '', false],
    ['      ', '', false],
    ['Empresa boa', '123', false],
    ['Empresa boa', '12345678', false],
    ['Empresa boa', '9299999999', false],
    ['Empresa boa', '92999999999', true],
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

// Telefone no cadastro de contas novas: obrigatório (o número aparece no
// lembrete ao cliente). Em Configurações continua opcional.
$casosTelefone = [
    ['(92) 99999-9999', true, true],
    ['+55 (92) 99999-9999', true, false],
    ['(55) 90000-1234', true, true],
    ['(55) 80000-1234', true, false],
    ['92999999999', true, true],
    ['92345678', true, false],
    ['9299999999', true, false],
    ['', true, false],
    ['   ', true, false],
    ['123', true, false],
    ['1234567', true, false],
    ['1234567890123456', true, false],
    ['telefone', true, false],
    ['(92) 99999-9999 ramal 2', true, false],
    ['', false, true],
    ['123', false, false],
    ['(92) 98888-7700', false, true],
];
foreach ($casosTelefone as [$telefone, $obrigatorio, $esperado]) {
    $valido = agenday_telefone_estabelecimento_valido($telefone, $obrigatorio);
    if ($valido !== $esperado) {
        fwrite(STDERR, "FAIL telefone: " . var_export([$telefone, $obrigatorio], true) . PHP_EOL);
        exit(1);
    }
    $aprovados++;
}

// Configurações segue aceitando o telefone vazio (contas antigas).
if (agenday_config_validar('Empresa Antiga', '') !== null) {
    fwrite(STDERR, "FAIL: Configurações deveria aceitar telefone vazio" . PHP_EOL);
    exit(1);
}
$aprovados++;

echo "Configuração do estabelecimento: " . ($aprovados + 1) . " testes aprovados." . PHP_EOL;
