<?php
declare(strict_types=1);

/**
 * Envio por HTTPS com a API Brevo, sem dependências externas.
 * A chave nunca deve ser versionada ou registrada em logs.
 */
function agenday_brevo_enviar_recuperacao(string $destinatario, string $link): bool
{
    $chave = getenv('AGENDAY_BREVO_API_KEY');
    if (!is_string($chave) || $chave === '') {
        $configFile = __DIR__ . '/../config/brevo-api-config.php';
        if (!is_file($configFile)) {
            error_log('AgenDay Brevo: configuracao privada ausente.');
            return false;
        }
        $config = require $configFile;
        $chave = is_array($config) ? ($config['api_key'] ?? null) : null;
    }

    if (!is_string($chave) || $chave === '' || !function_exists('curl_init')) {
        error_log('AgenDay Brevo: chave ou suporte cURL indisponivel.');
        return false;
    }

    $payload = json_encode([
        'sender' => [
            'name' => 'AgenDay',
            'email' => 'agendaysistema@gmail.com',
        ],
        'to' => [['email' => $destinatario]],
        'subject' => 'AgenDay — redefinição de senha',
        'htmlContent' => '<p>Recebemos um pedido para redefinir sua senha do AgenDay.</p>'
            . '<p><a href="' . htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">Redefinir minha senha</a></p>'
            . '<p>Este link vence em 30 minutos e pode ser usado uma única vez.'
            . ' Se você não fez esse pedido, ignore esta mensagem.</p>'
            . '<p>Esta é a senha do AgenDay, não a senha da sua conta Google.</p>',
        'textContent' => "Recebemos um pedido para redefinir sua senha do AgenDay.\n"
            . "Link: {$link}\nValidade: 30 minutos, uso unico.\n"
            . "Se nao solicitou, ignore este e-mail. Esta nao e a senha do Google.",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!is_string($payload)) {
        return false;
    }

    $curl = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($curl === false) {
        return false;
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . $chave,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);

    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    // Nunca registrar resposta do provedor: pode conter informações privadas.
    if ($resposta === false || $status < 200 || $status >= 300) {
        error_log('AgenDay Brevo: falha de envio (HTTP ' . $status . ').');
        return false;
    }
    return true;
}
