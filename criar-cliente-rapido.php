<?php
declare(strict_types=1);
require_once __DIR__ . '/sessao.php';
require_once __DIR__ . '/lib/portal-cliente.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');

function agenday_cliente_rapido_erro(string $mensagem, int $codigo = 422): void
{
    http_response_code($codigo);
    echo json_encode(['ok' => false, 'erro' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['id_estabelecimento']) || !is_int($_SESSION['id_estabelecimento'])
    || $_SESSION['id_estabelecimento'] < 1) {
    agenday_cliente_rapido_erro('É necessário entrar no sistema.', 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    agenday_cliente_rapido_erro('Método não permitido.', 405);
}
if (!agenday_valida_csrf()) {
    agenday_cliente_rapido_erro('A sessão do formulário expirou. Recarregue a página.', 403);
}

$nome = isset($_POST['nome']) && is_string($_POST['nome']) ? trim($_POST['nome']) : '';
$telefone = isset($_POST['telefone']) && is_string($_POST['telefone']) ? trim($_POST['telefone']) : '';
$telefone = preg_replace('/\D+/', '', $telefone) ?? '';
$semWhatsapp = isset($_POST['sem_whatsapp']) && $_POST['sem_whatsapp'] === '1';

if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
    agenday_cliente_rapido_erro('Informe o nome do cliente (2 a 120 caracteres).');
}
if ($telefone !== '' && (strlen($telefone) < 8 || strlen($telefone) > 15)) {
    agenday_cliente_rapido_erro('O telefone deve ter de 8 a 15 dígitos, ou ficar vazio.');
}
// Identidade do portal: DDD + número (10 ou 11 dígitos). Null sem WhatsApp.
$telefoneDigits = $semWhatsapp ? null : agenday_portal_telefone_normalizar($telefone);
if (!$semWhatsapp && $telefone !== '' && !agenday_portal_telefone_whatsapp_ok($telefoneDigits)) {
    agenday_cliente_rapido_erro(
        'Informe um celular com DDD e o 9 (11 dígitos), ou marque que o cliente não tem WhatsApp.'
    );
}

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    // Celular só vale depois de confirmado por código. Sem WhatsApp pronto na
    // plataforma, o cadastro continua para não travar o atendimento.
    if (!$semWhatsapp && $telefoneDigits !== null) {
        require_once __DIR__ . '/lib/whatsapp.php';
        try {
            $configWhats = agenday_whatsapp_config($conexao);
            $exigirConfirmacao = agenday_whatsapp_pronto($configWhats);
        } catch (Throwable $e) {
            $exigirConfirmacao = false;
        }
        if ($exigirConfirmacao
            && !agenday_portal_confirmacao_ler($idEstabelecimento, (string) $telefoneDigits)) {
            agenday_cliente_rapido_erro(
                'Confirme o celular pelo código do WhatsApp antes de salvar, '
                . 'ou marque que o cliente não tem WhatsApp.'
            );
        }
    }

    // Telefone repetido reaproveita o cadastro existente.
    if ($telefone !== '') {
        if ($telefoneDigits !== null) {
            // Compara as duas formas do celular (com e sem o 9) para não
            // duplicar cadastros antigos.
            $existente = agenday_portal_cliente_por_telefone($conexao, $idEstabelecimento, $telefoneDigits);
        } else {
            $consulta = $conexao->prepare(
                'SELECT id_cliente, nome_cliente, telefone_digits FROM clientes
                 WHERE id_estabelecimento = ? AND contato_cliente = ? LIMIT 1'
            );
            $consulta->bind_param('is', $idEstabelecimento, $telefone);
            $consulta->execute();
            $existente = $consulta->get_result()->fetch_assoc();
            $consulta->close();
        }
        if ($existente) {
            echo json_encode([
                'ok' => true,
                'cliente' => [
                    'id' => (int) $existente['id_cliente'],
                    'nome' => (string) $existente['nome_cliente'],
                ],
                'reaproveitado' => true,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $insercao = $conexao->prepare(
        'INSERT INTO clientes (id_estabelecimento, nome_cliente, contato_cliente, telefone_digits)
         VALUES (?, ?, ?, ?)'
    );
    // contato_cliente é NOT NULL no banco: vazio vira string vazia.
    $insercao->bind_param('isss', $idEstabelecimento, $nome, $telefone, $telefoneDigits);
    $insercao->execute();
    $idNovo = (int) $conexao->insert_id;
    $insercao->close();

    echo json_encode([
        'ok' => true,
        'cliente' => ['id' => $idNovo, 'nome' => $nome],
        'reaproveitado' => false,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('AgenDay: erro ao criar cliente rápido (' . get_class($e) . ').');
    agenday_cliente_rapido_erro('Não foi possível salvar o cliente agora.', 503);
}
