<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/cadastro-reenvio.php';

$_SESSION = [];

$verificacoes = 0;
function conferir(bool $condicao, string $descricao): void
{
    global $verificacoes;
    ++$verificacoes;
    if (!$condicao) {
        throw new RuntimeException('Falha: ' . $descricao);
    }
}

$cliente = agenday_cadastro_token_emitir('clientes');
conferir((bool) preg_match('/^[a-f0-9]{32}$/D', $cliente), 'token aleatório válido');
conferir(agenday_cadastro_token_consumir('clientes', $cliente), 'primeiro envio permitido');
conferir(!agenday_cadastro_token_consumir('clientes', $cliente), 'reenvio bloqueado');
conferir(!agenday_cadastro_token_consumir('funcionarios', $cliente), 'token não muda de tipo');
conferir(!agenday_cadastro_token_consumir('clientes', []), 'payload malformado');

$funcionario = agenday_cadastro_token_emitir('funcionarios');
conferir(agenday_cadastro_token_consumir('funcionarios', $funcionario), 'funcionário permitido');
conferir(!agenday_cadastro_token_consumir('funcionarios', $funcionario), 'funcionário bloqueia replay');

$novo = agenday_cadastro_token_emitir('clientes');
conferir($novo !== $cliente && agenday_cadastro_token_consumir('clientes', $novo),
    'novo cadastro legítimo permitido');

$vencido = agenday_cadastro_token_emitir('clientes');
$_SESSION['agenday_cadastro_tokens']['clientes'][$vencido] =
    time() - AGENDAY_CADASTRO_TOKEN_TTL - 1;
conferir(!agenday_cadastro_token_consumir('clientes', $vencido), 'token vencido bloqueado');

for ($i = 0; $i < 40; ++$i) {
    agenday_cadastro_token_emitir('clientes');
}
conferir(count($_SESSION['agenday_cadastro_tokens']['clientes'])
    <= AGENDAY_CADASTRO_TOKEN_LIMITE, 'memória limitada na sessão');

$excecao = false;
try {
    agenday_cadastro_token_emitir('invalido');
} catch (InvalidArgumentException $e) {
    $excecao = true;
}
conferir($excecao, 'tipo não autorizado rejeitado');

echo 'OK: ' . $verificacoes . ' verificações de prevenção de reenvio.' . PHP_EOL;
