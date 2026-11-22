<?php
/**
 * AgenDay 2.0 — confirmação do celular do cliente dentro do painel.
 *
 * A recepcionista digita o número, o cliente recebe um código no WhatsApp e
 * ela digita o código de volta. Só então o número é gravado como confiável em
 * `telefone_digits`, que é a mesma identidade usada pelo portal do cliente.
 *
 * A confirmação fica na sessão do painel (não em coluna nova): vale apenas
 * para o cadastro que está sendo feito agora e expira em 30 minutos.
 *
 * Ações: enviar | confirmar. Sempre POST autenticado com CSRF.
 */
declare(strict_types=1);

require_once __DIR__ . '/../sessao.php';
require_once __DIR__ . '/../lib/portal-cliente.php';
require_once __DIR__ . '/../lib/whatsapp.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');

function agenday_painel_telefone_json(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function agenday_painel_telefone_erro(string $mensagem, int $codigo = 422, array $extra = []): void
{
    agenday_painel_telefone_json(['ok' => false, 'erro' => $mensagem] + $extra, $codigo);
}

if (!isset($_SESSION['id_estabelecimento']) || !is_int($_SESSION['id_estabelecimento'])
    || $_SESSION['id_estabelecimento'] < 1) {
    agenday_painel_telefone_erro('É necessário entrar no sistema.', 401);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    agenday_painel_telefone_erro('Método não permitido.', 405);
}
if (!agenday_valida_csrf()) {
    agenday_painel_telefone_erro('A sessão do formulário expirou. Recarregue a página.', 403);
}

$empresa = (int) $_SESSION['id_estabelecimento'];
$acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';
$digitos = agenday_portal_telefone_normalizar($_POST['telefone'] ?? null);

if ($digitos === null || !agenday_portal_telefone_whatsapp_ok($digitos)) {
    agenday_painel_telefone_erro(
        'Informe um celular com DDD e o 9 (11 dígitos). Fixo não recebe WhatsApp.'
    );
}

try {
    require_once __DIR__ . '/../conexao.php';
} catch (Throwable $e) {
    error_log('AgenDay: banco indisponível na confirmação do celular.');
    agenday_painel_telefone_erro('Não foi possível falar com o banco agora.', 503);
}

if ($acao === 'enviar') {
    $permitirDuplicado = isset($_POST['permitir_duplicado']) && $_POST['permitir_duplicado'] === '1';

    try {
        // Número de outro cliente: avisa antes de gastar um código.
        if (!$permitirDuplicado) {
            $existente = agenday_portal_cliente_por_telefone($conexao, $empresa, $digitos);
            if ($existente) {
                agenday_painel_telefone_json([
                    'ok' => false,
                    'duplicado' => true,
                    'nome' => (string) $existente['nome_cliente'],
                    'erro' => 'Esse celular já está cadastrado para ' . $existente['nome_cliente'] . '.',
                ], 409);
            }
        }

        // Um código por minuto e limites diários (mesma regra do portal).
        $stmt = $conexao->prepare(
            'SELECT criado_em,
                    SUM(criado_em > DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS dez_minutos,
                    SUM(criado_em > DATE_SUB(NOW(), INTERVAL 1 DAY)) AS um_dia
               FROM verificacao_acesso
              WHERE id_estabelecimento = ? AND telefone = ?'
        );
        $stmt->bind_param('is', $empresa, $digitos);
        $stmt->execute();
        $contagem = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();

        if ((int) ($contagem['dez_minutos'] ?? 0) >= 3) {
            agenday_painel_telefone_erro('Foram pedidos muitos códigos. Aguarde 10 minutos.', 429);
        }
        if ((int) ($contagem['um_dia'] ?? 0) >= 10) {
            agenday_painel_telefone_erro('Limite de códigos por dia atingido. Tente amanhã.', 429);
        }

        $stmt = $conexao->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, criado_em, NOW()) AS segundos
               FROM verificacao_acesso
              WHERE id_estabelecimento = ? AND telefone = ?
              ORDER BY id_verificacao DESC LIMIT 1'
        );
        $stmt->bind_param('is', $empresa, $digitos);
        $stmt->execute();
        $ultimo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($ultimo !== null && (int) $ultimo['segundos'] < 60) {
            $esperar = 60 - (int) $ultimo['segundos'];
            agenday_painel_telefone_erro('Aguarde ' . $esperar . ' segundos para pedir outro código.', 429);
        }

        $config = agenday_whatsapp_config($conexao);
        if (!agenday_whatsapp_pronto($config)) {
            agenday_painel_telefone_erro(
                'O envio pelo WhatsApp está indisponível. Cadastre o cliente sem número.',
                503
            );
        }

        $stmt = $conexao->prepare(
            'SELECT nome_estabelecimento FROM estabelecimento
              WHERE id_estabelecimento = ? LIMIT 1'
        );
        $stmt->bind_param('i', $empresa);
        $stmt->execute();
        $stmt->bind_result($nomeEstabelecimento);
        $stmt->fetch();
        $stmt->close();

        $codigo = str_pad((string) random_int(0, 999999), AGENDAY_PORTAL_CODIGO_DIGITOS, '0', STR_PAD_LEFT);
        $salt = bin2hex(random_bytes(16));
        $hash = hash('sha256', $salt . ':' . $codigo);
        $ipHash = agenday_portal_ip_hash();
        $ipHash = $ipHash !== '' ? $ipHash : null;

        $stmt = $conexao->prepare(
            'INSERT INTO verificacao_acesso
                (id_estabelecimento, telefone, nome_informado, codigo_hash, salt, expira_em, ip_hash)
             VALUES (?, ?, NULL, ?, ?, DATE_ADD(NOW(), INTERVAL ' . AGENDAY_PORTAL_CODIGO_MINUTOS . ' MINUTE), ?)'
        );
        $stmt->bind_param('issss', $empresa, $digitos, $hash, $salt, $ipHash);
        $stmt->execute();
        $idVerificacao = (int) $conexao->insert_id;
        $stmt->close();

        $texto = agenday_whatsapp_texto('codigo', [
            'estabelecimento' => (string) ($nomeEstabelecimento ?? 'AgenDay'),
            'codigo' => $codigo,
            'minutos' => AGENDAY_PORTAL_CODIGO_MINUTOS,
        ]);
        $envio = agenday_whatsapp_enviar($config, $digitos, $texto);
        if (!$envio['ok']) {
            // Nada fica pela metade: apaga a verificação que não pôde ser entregue.
            $stmt = $conexao->prepare('DELETE FROM verificacao_acesso WHERE id_verificacao = ?');
            $stmt->bind_param('i', $idVerificacao);
            $stmt->execute();
            $stmt->close();
            agenday_painel_telefone_erro(
                'Não foi possível enviar o código para esse número. Confira o WhatsApp ou marque que o cliente não tem.',
                502
            );
        }

        agenday_painel_telefone_json([
            'ok' => true,
            'mascarado' => agenday_portal_telefone_mascarado($digitos),
            'minutos' => AGENDAY_PORTAL_CODIGO_MINUTOS,
        ]);
    } catch (Throwable $e) {
        error_log('AgenDay: falha ao enviar código no painel (' . get_class($e) . ').');
        agenday_painel_telefone_erro('Não foi possível enviar o código agora.', 503);
    }
}

if ($acao === 'confirmar') {
    $codigo = isset($_POST['codigo']) && is_string($_POST['codigo'])
        ? preg_replace('/\D+/', '', $_POST['codigo'])
        : '';
    if (!is_string($codigo) || strlen($codigo) !== AGENDAY_PORTAL_CODIGO_DIGITOS) {
        agenday_painel_telefone_erro('Digite os ' . AGENDAY_PORTAL_CODIGO_DIGITOS . ' números do código.');
    }

    try {
        $stmt = $conexao->prepare(
            'SELECT id_verificacao, codigo_hash, salt, tentativas
               FROM verificacao_acesso
              WHERE id_estabelecimento = ? AND telefone = ?
                AND usado_em IS NULL AND expira_em > NOW()
              ORDER BY id_verificacao DESC
              LIMIT 1'
        );
        $stmt->bind_param('is', $empresa, $digitos);
        $stmt->execute();
        $verificacao = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$verificacao) {
            agenday_painel_telefone_erro('O código expirou. Peça um novo código.', 422, ['expirou' => true]);
        }
        if ((int) $verificacao['tentativas'] >= AGENDAY_PORTAL_CODIGO_TENTATIVAS) {
            agenday_painel_telefone_erro('Muitas tentativas com este código. Peça um novo.', 429);
        }

        $esperado = hash('sha256', (string) $verificacao['salt'] . ':' . $codigo);
        if (!hash_equals((string) $verificacao['codigo_hash'], $esperado)) {
            $stmt = $conexao->prepare(
                'UPDATE verificacao_acesso SET tentativas = tentativas + 1 WHERE id_verificacao = ?'
            );
            $stmt->bind_param('i', $verificacao['id_verificacao']);
            $stmt->execute();
            $stmt->close();
            agenday_painel_telefone_erro('Código incorreto. Confira e tente de novo.');
        }

        $stmt = $conexao->prepare(
            'UPDATE verificacao_acesso SET usado_em = NOW() WHERE id_verificacao = ?'
        );
        $stmt->bind_param('i', $verificacao['id_verificacao']);
        $stmt->execute();
        $stmt->close();

        agenday_portal_confirmacao_guardar($empresa, $digitos);
        agenday_painel_telefone_json([
            'ok' => true,
            'mascarado' => agenday_portal_telefone_mascarado($digitos),
        ]);
    } catch (Throwable $e) {
        error_log('AgenDay: falha ao confirmar código no painel (' . get_class($e) . ').');
        agenday_painel_telefone_erro('Não foi possível conferir o código agora.', 503);
    }
}

agenday_painel_telefone_erro('Ação desconhecida.', 400);
