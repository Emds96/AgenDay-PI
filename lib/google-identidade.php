<?php
declare(strict_types=1);

/**
 * Validação estrita de ID tokens do Google.
 * Assinatura RSA-SHA256 verificada com OpenSSL contra certificados PEM obtidos
 * exclusivamente por HTTPS do Google. Falhas de rede/verificação negam acesso.
 *
 * Não usar tokeninfo como substituto de verificação criptográfica em produção.
 */
function agenday_google_config(): array
{
    $path = dirname(__DIR__) . '/google-oauth-config.php';
    if (!is_file($path)) {
        return [];
    }
    $config = require $path;
    if (!is_array($config) || !isset($config['client_id']) || !is_string($config['client_id'])) {
        return [];
    }
    if (!preg_match('/^[0-9]+-[a-z0-9-]+\\.apps\\.googleusercontent\\.com$/i', $config['client_id'])) {
        return [];
    }
    return $config;
}

function agenday_google_certificados(): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $curl = curl_init('https://www.googleapis.com/oauth2/v1/certs');
    if ($curl === false) {
        return null;
    }
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 9,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($status !== 200 || !is_string($body) || strlen($body) > 150000) {
        return null;
    }
    $keys = json_decode($body, true, 8);
    if (!is_array($keys) || count($keys) < 1 || count($keys) > 12) {
        return null;
    }
    foreach ($keys as $kid => $pem) {
        if (!is_string($kid) || !is_string($pem) || !str_contains($pem, 'BEGIN CERTIFICATE')) {
            return null;
        }
    }
    return $keys;
}

function agenday_google_base64url(string $part): string|false
{
    if ($part === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $part) || strlen($part) % 4 === 1) {
        return false;
    }
    $padding = (4 - strlen($part) % 4) % 4;
    return base64_decode(strtr($part, '-_', '+/') . str_repeat('=', $padding), true);
}

/**
 * $certificados é injetável para testes com pares de chaves sintéticos.
 * Retorna apenas dados necessários; não salva JWT nem reivindicações extras.
 */
function agenday_google_validar_token(
    string $jwt,
    string $clientId,
    string $nonce,
    array $certificados,
    ?int $agora = null
): ?array {
    if ($clientId === '' || $nonce === '' || strlen($jwt) > 10000) {
        return null;
    }
    $partes = explode('.', $jwt);
    if (count($partes) !== 3) {
        return null;
    }
    [$h64, $p64, $s64] = $partes;
    $h = agenday_google_base64url($h64);
    $p = agenday_google_base64url($p64);
    $assinatura = agenday_google_base64url($s64);
    if ($h === false || $p === false || $assinatura === false) {
        return null;
    }
    $cabecalho = json_decode($h, true, 16);
    $dados = json_decode($p, true, 16);
    if (!is_array($cabecalho) || !is_array($dados)
        || ($cabecalho['alg'] ?? null) !== 'RS256'
        || !isset($cabecalho['kid']) || !is_string($cabecalho['kid'])) {
        return null;
    }
    $certificado = $certificados[$cabecalho['kid']] ?? null;
    if (!is_string($certificado)
        || openssl_verify($h64 . '.' . $p64, $assinatura, $certificado, OPENSSL_ALGO_SHA256) !== 1) {
        return null;
    }
    $agora ??= time();
    if (!isset($dados['exp'], $dados['iat'])
        || !is_int($dados['exp']) || !is_int($dados['iat'])
        || $dados['exp'] <= $agora || $dados['iat'] > $agora + 60
        || (isset($dados['nbf']) && (!is_int($dados['nbf']) || $dados['nbf'] > $agora + 60))
        || ($dados['aud'] ?? null) !== $clientId
        || (isset($dados['azp']) && $dados['azp'] !== $clientId)
        || !in_array($dados['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
        || !isset($dados['nonce']) || !is_string($dados['nonce'])
        || !hash_equals($nonce, $dados['nonce'])
        || ($dados['email_verified'] ?? null) !== true) {
        return null;
    }
    $sub = $dados['sub'] ?? null;
    $email = $dados['email'] ?? null;
    if (!is_string($sub) || $sub === '' || strlen($sub) > 255
        || !is_string($email) || strlen($email) > 190
        || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return ['sub' => $sub, 'email' => strtolower($email)];
}
