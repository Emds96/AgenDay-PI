<?php
/**
 * AgenDay 2.0 — guarda de host.
 *
 * O sistema inteiro (admin + portal do cliente) é publicado nas duas pastas.
 * No domínio do cliente só podem responder as rotas do portal; qualquer rota
 * administrativa (login, agenda, configurações, uploads por PHP etc.) é
 * redirecionada para o portal.
 *
 * Uso: sessao.php chama agenday_guarda_host_aplicar() ANTES de session_start().
 * cliente.php e os endpoints api/cliente-*.php são rotas permitidas e não
 * precisam chamar nada.
 */
declare(strict_types=1);

/**
 * Domínios em que o site se comporta como portal do cliente.
 *
 * Pode ser sobrescrito por uma constante definida antes do include
 * (útil para testes locais e para um domínio novo):
 *
 *   define('AGENDAY_HOSTS_CLIENTE', ['portal.exemplo.com']);
 */
function agenday_guarda_hosts_cliente(): array
{
    if (defined('AGENDAY_HOSTS_CLIENTE')) {
        $valor = constant('AGENDAY_HOSTS_CLIENTE');
        if (is_array($valor)) {
            return array_values(array_filter(array_map('strval', $valor)));
        }
        if (is_string($valor) && $valor !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $valor))));
        }
    }
    return ['agenday-agendamentos.infinityfree.io'];
}

/** Host normalizado da requisição atual (sem porta, minúsculo, sem "www."). */
function agenday_guarda_host_atual(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    if (!is_string($host) || $host === '') {
        return '';
    }
    $host = strtolower(trim($host));
    $host = preg_replace('/:\d+\z/', '', $host) ?? $host;
    if (strncmp($host, 'www.', 4) === 0) {
        $host = substr($host, 4);
    }
    return $host;
}

/** A requisição atual chegou pelo domínio do cliente? */
function agenday_guarda_host_e_cliente(): bool
{
    $host = agenday_guarda_host_atual();
    if ($host === '') {
        return false;
    }
    foreach (agenday_guarda_hosts_cliente() as $dominio) {
        if (strtolower(trim($dominio)) === $host) {
            return true;
        }
    }
    return false;
}

/** Caminho do script atual, relativo à raiz do site (ex.: "/api/x.php"). */
function agenday_guarda_host_caminho(): string
{
    $caminho = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!is_string($caminho) || $caminho === '') {
        return '';
    }
    return str_replace('\\', '/', $caminho);
}

/**
 * A rota atual pertence ao portal público?
 *
 * Permitidas: cliente.php, cliente-cancelar.php, cliente-confirmar.php e api/cliente-*.php.
 * Tudo o mais é administrativo.
 */
function agenday_guarda_host_rota_portal(): bool
{
    $caminho = agenday_guarda_host_caminho();
    if ($caminho === '') {
        return false;
    }
    $arquivo = basename($caminho);
    if ($arquivo === 'cliente.php' || $arquivo === 'cliente-cancelar.php' || $arquivo === 'cliente-confirmar.php') {
        return true;
    }
    // Endpoints do portal ficam em api/cliente-*.php (qualquer subpasta api/).
    if (strpos($caminho, '/api/') !== false && preg_match('/\Acliente-[a-z0-9-]+\.php\z/', $arquivo)) {
        return true;
    }
    return false;
}

/**
 * Aplica a guarda. Deve ser chamada no início de qualquer página que use
 * sessão administrativa (sessao.php faz isso).
 *
 * No domínio do cliente, rota administrativa é redirecionada para o portal,
 * preservando o parâmetro ?e= quando presente.
 */
function agenday_guarda_host_aplicar(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!agenday_guarda_host_e_cliente()) {
        return;
    }
    if (agenday_guarda_host_rota_portal()) {
        return;
    }

    $destino = '/cliente.php';
    $slug = $_GET['e'] ?? null;
    if (is_string($slug) && $slug !== '' && preg_match('/\A[a-z0-9-]{1,60}\z/', $slug)) {
        $destino .= '?e=' . rawurlencode($slug);
    }

    header('Location: ' . $destino, true, 302);
    exit;
}
