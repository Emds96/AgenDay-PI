<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/google-identidade.php';

function teste(bool $ok, string $nome): void
{
    if (!$ok) {
        fwrite(STDERR, 'FALHOU: ' . $nome . PHP_EOL);
        exit(1);
    }
    echo 'OK: ' . $nome . PHP_EOL;
}

function base64url(string $valor): string
{
    return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
}

function gerarJwt(array $payload, $chave, array $header = []): string
{
    $header = array_merge(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'teste'], $header);
    $data = base64url(json_encode($header, JSON_THROW_ON_ERROR)) . '.'
        . base64url(json_encode($payload, JSON_THROW_ON_ERROR));
    if (!openssl_sign($data, $assinatura, $chave, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Falha na assinatura do teste');
    }
    return $data . '.' . base64url($assinatura);
}

$privada = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
if ($privada === false) {
    throw new RuntimeException('OpenSSL indisponível');
}
$publica = openssl_pkey_get_details($privada);
$certs = ['teste' => $publica['key']];
$agora = 1800000000;
$client = '123456789-abc.apps.googleusercontent.com';
$nonce = 'nonce-da-sessao';
$base = [
    'iss' => 'https://accounts.google.com',
    'aud' => $client,
    'exp' => $agora + 1800,
    'iat' => $agora - 30,
    'sub' => '10987654321',
    'email' => 'qa@example.com',
    'email_verified' => true,
    'nonce' => $nonce,
];

$jwt = gerarJwt($base, $privada);
$ok = agenday_google_validar_token($jwt, $client, $nonce, $certs, $agora);
teste($ok === ['sub' => '10987654321', 'email' => 'qa@example.com'], 'Token assinado corretamente');
teste(agenday_google_validar_token($jwt, $client, 'outro', $certs, $agora) === null, 'Nonce divergente');
teste(agenday_google_validar_token($jwt, 'outra-app', $nonce, $certs, $agora) === null, 'Audience divergente');
teste(agenday_google_validar_token($jwt, $client, $nonce, [], $agora) === null, 'Certificado ausente');
teste(agenday_google_validar_token('nao.eh.jwt', $client, $nonce, $certs, $agora) === null, 'JWT malformado');

$alterados = [
    ['aud' => 'cliente-errado', 'caso' => 'Audience inválida'],
    ['iss' => 'https://attacker.invalid', 'caso' => 'Issuer inválido'],
    ['email_verified' => false, 'caso' => 'E-mail não verificado'],
    ['exp' => $agora - 1, 'caso' => 'Token expirado'],
    ['iat' => $agora + 120, 'caso' => 'Emissão futura'],
    ['nonce' => 'outro-nonce', 'caso' => 'Nonce do token inválido'],
    ['email' => 'nao-e-um-email', 'caso' => 'E-mail inválido'],
    ['sub' => '', 'caso' => 'Sub vazio'],
];
foreach ($alterados as $alteracao) {
    $nome = $alteracao['caso'];
    unset($alteracao['caso']);
    $outro = gerarJwt(array_replace($base, $alteracao), $privada);
    teste(agenday_google_validar_token($outro, $client, $nonce, $certs, $agora) === null, $nome);
}

$errado = gerarJwt($base, $privada, ['alg' => 'HS256']);
teste(agenday_google_validar_token($errado, $client, $nonce, $certs, $agora) === null, 'Algoritmo diferente de RS256');
$adulterado = substr($jwt, 0, -1) . ($jwt[-1] === 'A' ? 'B' : 'A');
teste(agenday_google_validar_token($adulterado, $client, $nonce, $certs, $agora) === null, 'Assinatura adulterada');

echo 'Validação offline de identidade Google concluída.' . PHP_EOL;
