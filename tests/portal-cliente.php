<?php
/**
 * Teste CLI sem conexão com MySQL — funções puras do portal do cliente.
 * php tests/portal-cliente.php
 *
 * Cobre: slug, telefone (mesma regra da migração SQL), tokens, templates de
 * WhatsApp e guarda de host. Não testa banco, rede nem sessão.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/portal-cliente.php';
require_once __DIR__ . '/../lib/whatsapp.php';
require_once __DIR__ . '/../lib/guarda-host.php';

$testes = 0;

function verificar(bool $condicao, string $rotulo): void
{
    global $testes;
    $testes++;
    if (!$condicao) {
        throw new RuntimeException('FALHA no teste: ' . $rotulo);
    }
}

/* ------------------------------------------------------------------ slug */
verificar(agenday_portal_slug_normalizar('Barbearia do João') === 'barbearia-do-joao', 'slug com acento');
verificar(agenday_portal_slug_normalizar('  Studio   Bella   Vita  ') === 'studio-bella-vita', 'slug com espaços');
verificar(agenday_portal_slug_normalizar('Ação & Cia.') === 'acao-cia', 'slug com símbolos');
verificar(agenday_portal_slug_valido('studio'), 'slug curto válido');
verificar(agenday_portal_slug_valido('barbearia-do-joao'), 'slug com hífen válido');
verificar(!agenday_portal_slug_valido('ab'), 'slug com 2 letras inválido');
verificar(!agenday_portal_slug_valido('-studio'), 'slug começando com hífen inválido');
verificar(!agenday_portal_slug_valido('studio--bella'), 'slug com hífen duplo inválido');
verificar(!agenday_portal_slug_valido('Studio'), 'slug com maiúscula inválido');
verificar(!agenday_portal_slug_valido('api'), 'slug reservado inválido');
verificar(agenday_portal_slug_sugerir('Barbearia do João') === 'barbearia-do-joao', 'sugestão de slug');
verificar(agenday_portal_slug_sugerir('API') === 'api-agenda', 'sugestão evita reservado');
verificar(strlen(agenday_portal_slug_sugerir(str_repeat('nome ', 30))) <= 60, 'sugestão respeita 60 caracteres');

/* -------------------------------------------------------------- telefone */
$casos = [
    '(92) 98111-1001' => '92981111001',
    '92981111001' => '92981111001',
    '92 98111 1001' => '92981111001',
    '+55 (92) 98111-1001' => '92981111001',
    '5592981111001' => '92981111001',
    '92 3333-4444' => '9233334444',
    '55 92 3333-4444' => '9233334444',
];
foreach ($casos as $entrada => $esperado) {
    verificar(agenday_portal_telefone_normalizar($entrada) === $esperado, 'telefone: ' . $entrada);
}
verificar(agenday_portal_telefone_normalizar('') === null, 'telefone vazio');
verificar(agenday_portal_telefone_normalizar('123') === null, 'telefone curto');
verificar(agenday_portal_telefone_normalizar('092981111001') === null, 'telefone com DDD zero');
verificar(agenday_portal_telefone_normalizar('0000000000') === null, 'telefone todo zero');
verificar(agenday_portal_telefone_normalizar(null) === null, 'telefone nulo');
verificar(agenday_portal_telefone_formatar('92981111001') === '(92) 98111-1001', 'formato celular');
verificar(agenday_portal_telefone_formatar('9233334444') === '(92) 3333-4444', 'formato fixo');
verificar(agenday_portal_telefone_mascarado('92981111001') === '(92) 9****-1001', 'máscara celular');
verificar(agenday_portal_telefone_whatsapp('92981111001') === '5592981111001', 'whatsapp com país');

/* ----------------------------------------------------------------- token */
$token = agenday_portal_token_gerar();
verificar((bool) preg_match('/\A[a-f0-9]{64}\z/', $token), 'token 64 hex');
verificar($token !== agenday_portal_token_gerar(), 'tokens diferentes');

/* ------------------------------------------------------- WhatsApp (texto) */
$codigo = agenday_whatsapp_texto('codigo', [
    'estabelecimento' => 'Studio Bella Vita',
    'codigo' => '123456',
    'minutos' => 10,
]);
verificar(strpos($codigo, '123456') !== false, 'texto do código tem o código');
verificar(strpos($codigo, 'Studio Bella Vita') !== false, 'texto do código tem o estabelecimento');
verificar(strpos($codigo, '10 minutos') !== false, 'texto do código tem a validade');

$confirmacao = agenday_whatsapp_texto('confirmacao', [
    'estabelecimento' => 'Studio Bella Vita',
    'nome' => 'Ana',
    'servico' => 'Corte',
    'profissional' => 'Bia',
    'data_inicio' => '2026-11-27 14:30:00',
    'link_cancelar' => 'https://exemplo.test/c?t=abc',
]);
verificar(strpos($confirmacao, 'Olá, Ana!') !== false, 'confirmação saúda pelo nome');
verificar(strpos($confirmacao, '27/11/2026') !== false, 'confirmação com data brasileira');
verificar(strpos($confirmacao, '14:30') !== false, 'confirmação com hora');
verificar(strpos($confirmacao, 'https://exemplo.test/c?t=abc') !== false, 'confirmação com link');

$remarcacao = agenday_whatsapp_texto('remarcacao', [
    'estabelecimento' => 'Studio',
    'nome' => 'Ana',
    'servico' => 'Corte',
    'profissional' => 'Bia',
    'data_inicio' => '2026-11-28 09:00:00',
]);
verificar(strpos($remarcacao, 'remarcado') !== false, 'texto de remarcação');

$cancelamento = agenday_whatsapp_texto('cancelamento', [
    'estabelecimento' => 'Studio',
    'data_inicio' => '2026-11-27 14:30:00',
    'link_agendar' => 'https://exemplo.test/?e=studio',
]);
verificar(strpos($cancelamento, 'cancelado') !== false, 'texto de cancelamento');
verificar(strpos($cancelamento, 'https://exemplo.test/?e=studio') !== false, 'cancelamento com link');

verificar(agenday_whatsapp_chat_id('92981111001') === '5592981111001@c.us', 'chatId do WAHA');

$semConfig = ['ativo' => false, 'base_url' => '', 'api_key' => '', 'sessao' => 'default'];
verificar(!agenday_whatsapp_pronto($semConfig), 'config incompleta não está pronta');
verificar(!agenday_whatsapp_pronto(['ativo' => true, 'base_url' => 'https://x.ngrok-free.app', 'api_key' => '', 'sessao' => 'default']), 'sem chave não está pronta');
verificar(agenday_whatsapp_pronto(['ativo' => true, 'base_url' => 'https://x.ngrok-free.app', 'api_key' => 'k', 'sessao' => 'default']), 'config completa está pronta');
verificar(!agenday_whatsapp_enviar($semConfig, '92981111001', 'oi')['ok'], 'envio sem config falha sem exceção');

/* ----------------------------------------------------------- guarda host */
verificar(agenday_portal_host_normalizar('WWW.Exemplo.com:8080') === 'exemplo.com', 'host normalizado');
verificar(agenday_portal_host_normalizar('agenday-agendamentos.infinityfree.io') === 'agenday-agendamentos.infinityfree.io', 'host do portal');

function simular_host(string $host, string $script): array
{
    $antesHost = $_SERVER['HTTP_HOST'] ?? null;
    $antesScript = $_SERVER['SCRIPT_NAME'] ?? null;
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['SCRIPT_NAME'] = $script;
    $resultado = [
        'cliente' => agenday_guarda_host_e_cliente(),
        'portal' => agenday_guarda_host_rota_portal(),
    ];
    if ($antesHost === null) {
        unset($_SERVER['HTTP_HOST']);
    } else {
        $_SERVER['HTTP_HOST'] = $antesHost;
    }
    if ($antesScript === null) {
        unset($_SERVER['SCRIPT_NAME']);
    } else {
        $_SERVER['SCRIPT_NAME'] = $antesScript;
    }
    return $resultado;
}

$hostPortal = 'agenday-agendamentos.infinityfree.io';
$hostAdmin = 'agenday-sistema.infinityfree.io';

$r = simular_host($hostPortal, '/cliente.php');
verificar($r['cliente'] && $r['portal'], 'portal: cliente.php permitido');
$r = simular_host($hostPortal, '/cliente-cancelar.php');
verificar($r['cliente'] && $r['portal'], 'portal: cliente-cancelar.php permitido');
$r = simular_host($hostPortal, '/api/cliente-dados.php');
verificar($r['cliente'] && $r['portal'], 'portal: api/cliente-*.php permitido');
$r = simular_host($hostPortal, '/index.php');
verificar($r['cliente'] && !$r['portal'], 'portal: index.php bloqueado');
$r = simular_host($hostPortal, '/agenda.php');
verificar($r['cliente'] && !$r['portal'], 'portal: agenda.php bloqueado');
$r = simular_host($hostPortal, '/login.php');
verificar($r['cliente'] && !$r['portal'], 'portal: login.php bloqueado');
$r = simular_host($hostPortal, '/api/horarios.php');
verificar($r['cliente'] && !$r['portal'], 'portal: api/horarios.php bloqueado');
$r = simular_host($hostPortal, '/config.php');
verificar($r['cliente'] && !$r['portal'], 'portal: config.php bloqueado');
$r = simular_host($hostAdmin, '/agenda.php');
verificar(!$r['cliente'] && !$r['portal'], 'admin: agenda.php livre');
/* ----------------------------------------------- link e prazo de cancelamento */
$estabLink = ['slug' => 'studio', 'dominio' => 'agenday-agendamentos.infinityfree.io'];
verificar(
    agenday_portal_link_cancelamento($estabLink, str_repeat('a', 64))
        === 'https://agenday-agendamentos.infinityfree.io/cliente-cancelar.php?t=' . str_repeat('a', 64),
    'link de cancelamento usa o dominio do cliente'
);
verificar(agenday_portal_base_url(['slug' => 'studio', 'dominio' => '']) === '?e=studio', 'link relativo sem dominio');
verificar(agenday_whatsapp_chat_id('92981111001') === '5592981111001@c.us', 'chatId do WhatsApp');

$futuro = (new DateTimeImmutable('+3 days', agenday_portal_fuso()))->format('Y-m-d H:i:s');
$passado = (new DateTimeImmutable('-3 days', agenday_portal_fuso()))->format('Y-m-d H:i:s');
verificar(agenday_portal_pode_cancelar(24, $futuro)['pode'], 'cancelamento dentro do prazo');
verificar(!agenday_portal_pode_cancelar(24, $passado)['pode'], 'cancelamento fora do prazo');
verificar(!agenday_portal_pode_cancelar(24, 'data-invalida')['pode'], 'cancelamento com data invalida');

/* ------------------------------------------------------------ origem (CSRF) */
function simular_origem(?string $origem, ?string $referer, string $host): bool
{
    $antes = [$_SERVER['HTTP_ORIGIN'] ?? null, $_SERVER['HTTP_REFERER'] ?? null, $_SERVER['HTTP_HOST'] ?? null];
    if ($origem === null) { unset($_SERVER['HTTP_ORIGIN']); } else { $_SERVER['HTTP_ORIGIN'] = $origem; }
    if ($referer === null) { unset($_SERVER['HTTP_REFERER']); } else { $_SERVER['HTTP_REFERER'] = $referer; }
    $_SERVER['HTTP_HOST'] = $host;
    $resultado = agenday_portal_origem_valida();
    [$o, $r, $h] = $antes;
    if ($o === null) { unset($_SERVER['HTTP_ORIGIN']); } else { $_SERVER['HTTP_ORIGIN'] = $o; }
    if ($r === null) { unset($_SERVER['HTTP_REFERER']); } else { $_SERVER['HTTP_REFERER'] = $r; }
    if ($h === null) { unset($_SERVER['HTTP_HOST']); } else { $_SERVER['HTTP_HOST'] = $h; }
    return $resultado;
}

verificar(simular_origem('http://127.0.0.1:8080', null, '127.0.0.1:8080'), 'origem com porta confere');
verificar(simular_origem(null, 'https://studio.test/cliente.php', 'studio.test'), 'referer confere');
verificar(!simular_origem('https://outro.test', null, 'studio.test'), 'origem estranha recusada');
verificar(!simular_origem(null, null, 'studio.test'), 'sem origem recusado');
verificar(!simular_origem('http://studio.test.evil.test', null, 'studio.test'), 'host parecido recusado');

$r = simular_host($hostAdmin, '/cliente.php');
verificar(!$r['cliente'], 'admin: cliente.php também abre (plano B)');

echo 'OK: ' . $testes . " verificações do portal do cliente.\n";
