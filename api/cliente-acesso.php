<?php
/**
 * AgenDay 2.0 — acesso do cliente ao portal (telefone + código no WhatsApp).
 *
 * Ações:
 *   GET  ?acao=estado     → sessão atual, estabelecimento e exigência de código
 *   POST ?acao=entrar     → cliente já cadastrado entra só com o celular; primeiro
 *                           acesso pede o nome e envia o código no WhatsApp
 *   POST ?acao=verificar  → confere o código do cadastro e abre a sessão
 *   POST ?acao=sair       → encerra a sessão
 *
 * Não usa sessão do PHP: a sessão do cliente é um cookie próprio + registro
 * na tabela sessao_cliente (revogável e independente do AGENDAYSESSID).
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/portal-cliente.php';
require_once __DIR__ . '/../lib/whatsapp.php';

$metodo = $_SERVER['REQUEST_METHOD'] ?? '';
$corpo = agenday_portal_corpo();
$acao = $_GET['acao'] ?? ($corpo['acao'] ?? '');
if (!is_string($acao) || $acao === '') {
    agenday_portal_erro('Ação não informada.', 400);
}

require_once __DIR__ . '/../conexao.php';

$estabelecimento = agenday_portal_estabelecimento_requisicao($conexao);
if ($estabelecimento === null) {
    agenday_portal_erro('Estabelecimento não encontrado.', 404);
}
$empresa = (int) $estabelecimento['id_estabelecimento'];
$exigirCodigo = (int) $estabelecimento['exigir_codigo_whatsapp'] === 1;

/** Texto curto do estabelecimento para as mensagens. */
function agenday_portal_nome_estabelecimento(array $estabelecimento): string
{
    $nome = trim((string) ($estabelecimento['nome_estabelecimento'] ?? ''));
    return $nome !== '' ? $nome : 'AgenDay';
}

/* ---------------------------------------------------------------------------
 * estado
 * ------------------------------------------------------------------------- */
if ($acao === 'estado') {
    if ($metodo !== 'GET') {
        header('Allow: GET');
        agenday_portal_erro('Método não permitido.', 405);
    }

    // Limpeza oportunista de sessões vencidas (não existe cron na hospedagem).
    if (random_int(1, 40) === 1) {
        agenday_portal_sessao_limpar($conexao);
    }

    $sessao = agenday_portal_sessao_ler($conexao, $empresa);
    $config = agenday_whatsapp_config($conexao);
    $resposta = [
        'ok' => true,
        'estabelecimento' => [
            'nome' => agenday_portal_nome_estabelecimento($estabelecimento),
            'slug' => (string) ($estabelecimento['slug'] ?? ''),
        ],
        'exigir_codigo' => $exigirCodigo,
        'whatsapp_ativo' => agenday_whatsapp_pronto($config),
        'sessao' => null,
    ];
    if ($sessao !== null) {
        agenday_portal_sessao_tocar($conexao, (string) $sessao['id_sessao']);
        $resposta['sessao'] = [
            'csrf' => agenday_portal_csrf_token($sessao),
            'cliente' => [
                'nome' => (string) $sessao['nome_cliente'],
                'telefone' => agenday_portal_telefone_mascarado((string) $sessao['telefone_digits']),
            ],
        ];
    }
    agenday_portal_json($resposta);
}

/* ---------------------------------------------------------------------------
 * entrar
 * ------------------------------------------------------------------------- */
if ($acao === 'entrar') {
    if ($metodo !== 'POST') {
        header('Allow: POST');
        agenday_portal_erro('Método não permitido.', 405);
    }
    agenday_portal_exigir_origem();

    $telefone = agenday_portal_telefone_normalizar($corpo['telefone'] ?? null);
    if ($telefone === null) {
        agenday_portal_erro('Informe o celular com DDD. Exemplo: (92) 99999-9999.', 422);
    }

    $nome = isset($corpo['nome']) && is_string($corpo['nome']) ? trim($corpo['nome']) : '';
    if ($nome !== '') {
        $quantidade = preg_match_all('/./us', $nome);
        if ($quantidade === false || $quantidade < 2 || $quantidade > 120) {
            agenday_portal_erro('Informe seu nome completo (2 a 120 caracteres).', 422);
        }
    }

    // Cliente já cadastrado entra só com o celular; o código fica no cadastro.
    $clienteExistente = agenday_portal_cliente_por_telefone($conexao, $empresa, $telefone);
    $decisao = agenday_portal_acesso_decidir($clienteExistente !== null, $exigirCodigo, $nome);

    if ($decisao['acao'] === 'pedir_nome') {
        agenday_portal_json([
            'ok' => false,
            'precisa_nome' => true,
            'erro' => 'É o seu primeiro acesso. Informe seu nome completo.',
        ], 422);
    }

    // Acesso direto: cliente conhecido (ou contingência com a chave desligada).
    if ($decisao['acao'] === 'direto') {
        $registro = $clienteExistente ?? agenday_portal_cliente_garantir($conexao, $empresa, $nome, $telefone);
        agenday_portal_sessao_criar($conexao, $empresa, (int) $registro['id_cliente']);
        $sessao = agenday_portal_sessao_ler($conexao, $empresa);
        agenday_portal_json([
            'ok' => true,
            'sem_codigo' => true,
            'cliente_existente' => $clienteExistente !== null,
            'csrf' => $sessao !== null ? agenday_portal_csrf_token($sessao) : '',
            'cliente' => ['nome' => (string) $registro['nome_cliente']],
            'estabelecimento' => [
                'nome' => agenday_portal_nome_estabelecimento($estabelecimento),
                'slug' => (string) ($estabelecimento['slug'] ?? ''),
            ],
        ]);
    }

    // Limites antiabuso por telefone e por origem.
    $stmt = $conexao->prepare(
        'SELECT
            SUM(criado_em > DATE_SUB(NOW(), INTERVAL 10 MINUTE)) AS dez_minutos,
            SUM(criado_em > DATE_SUB(NOW(), INTERVAL 1 DAY)) AS um_dia
           FROM verificacao_acesso
          WHERE id_estabelecimento = ? AND telefone = ?'
    );
    $stmt->bind_param('is', $empresa, $telefone);
    $stmt->execute();
    $contagem = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();
    if ((int) ($contagem['dez_minutos'] ?? 0) >= 3) {
        agenday_portal_erro('Você pediu muitos códigos. Aguarde 10 minutos e tente de novo.', 429);
    }
    if ((int) ($contagem['um_dia'] ?? 0) >= 10) {
        agenday_portal_erro('Limite de códigos por dia atingido. Tente novamente amanhã.', 429);
    }

    $ipHash = agenday_portal_ip_hash();
    if ($ipHash !== '') {
        $stmt = $conexao->prepare(
            'SELECT COUNT(*) AS total FROM verificacao_acesso
              WHERE ip_hash = ? AND criado_em > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
        );
        $stmt->bind_param('s', $ipHash);
        $stmt->execute();
        $totalIp = (int) (($stmt->get_result()->fetch_assoc() ?: [])['total'] ?? 0);
        $stmt->close();
        if ($totalIp >= 20) {
            agenday_portal_erro('Muitas tentativas desta rede. Aguarde alguns minutos.', 429);
        }
    }

    // O WhatsApp precisa estar pronto: sem ele não há como confirmar o número.
    $config = agenday_whatsapp_config($conexao);
    if (!agenday_whatsapp_pronto($config)) {
        agenday_portal_erro(
            'O envio de códigos está indisponível no momento. Tente novamente em instantes ou fale com o estabelecimento.',
            503
        );
    }

    $codigo = str_pad((string) random_int(0, 999999), AGENDAY_PORTAL_CODIGO_DIGITOS, '0', STR_PAD_LEFT);
    $salt = bin2hex(random_bytes(16));
    $hash = hash('sha256', $salt . ':' . $codigo);
    $nomeInformado = $nome !== '' ? $nome : null;

    $stmt = $conexao->prepare(
        'INSERT INTO verificacao_acesso
            (id_estabelecimento, telefone, nome_informado, codigo_hash, salt, expira_em, ip_hash)
         VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . AGENDAY_PORTAL_CODIGO_MINUTOS . ' MINUTE), ?)'
    );
    $stmt->bind_param('isssss', $empresa, $telefone, $nomeInformado, $hash, $salt, $ipHash);
    $stmt->execute();
    $idVerificacao = (int) $conexao->insert_id;
    $stmt->close();

    $texto = agenday_whatsapp_texto('codigo', [
        'estabelecimento' => agenday_portal_nome_estabelecimento($estabelecimento),
        'codigo' => $codigo,
        'minutos' => AGENDAY_PORTAL_CODIGO_MINUTOS,
    ]);
    $envio = agenday_whatsapp_enviar($config, $telefone, $texto);
    if (!$envio['ok']) {
        // Nada fica pela metade: apaga a verificação que não pôde ser entregue.
        $stmt = $conexao->prepare('DELETE FROM verificacao_acesso WHERE id_verificacao = ?');
        $stmt->bind_param('i', $idVerificacao);
        $stmt->execute();
        $stmt->close();
        agenday_portal_erro('Não foi possível enviar o código agora. Tente novamente em instantes.', 503);
    }

    agenday_portal_json([
        'ok' => true,
        'codigo_enviado' => true,
        'telefone' => agenday_portal_telefone_mascarado($telefone),
        'expira_minutos' => AGENDAY_PORTAL_CODIGO_MINUTOS,
        'tentativas' => AGENDAY_PORTAL_CODIGO_TENTATIVAS,
    ]);
}

/* ---------------------------------------------------------------------------
 * verificar
 * ------------------------------------------------------------------------- */
if ($acao === 'verificar') {
    if ($metodo !== 'POST') {
        header('Allow: POST');
        agenday_portal_erro('Método não permitido.', 405);
    }
    agenday_portal_exigir_origem();

    $telefone = agenday_portal_telefone_normalizar($corpo['telefone'] ?? null);
    if ($telefone === null) {
        agenday_portal_erro('Informe novamente o celular com DDD.', 422);
    }
    $codigo = isset($corpo['codigo']) && is_string($corpo['codigo'])
        ? preg_replace('/\D+/', '', $corpo['codigo'])
        : '';
    if (!is_string($codigo) || strlen($codigo) !== AGENDAY_PORTAL_CODIGO_DIGITOS) {
        agenday_portal_erro('Digite os ' . AGENDAY_PORTAL_CODIGO_DIGITOS . ' números do código.', 422);
    }

    $stmt = $conexao->prepare(
        'SELECT id_verificacao, codigo_hash, salt, tentativas, nome_informado
           FROM verificacao_acesso
          WHERE id_estabelecimento = ? AND telefone = ?
            AND usado_em IS NULL AND expira_em > NOW()
          ORDER BY id_verificacao DESC
          LIMIT 1'
    );
    $stmt->bind_param('is', $empresa, $telefone);
    $stmt->execute();
    $verificacao = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$verificacao) {
        agenday_portal_json([
            'ok' => false,
            'codigo_expirado' => true,
            'erro' => 'O código expirou. Peça um novo código.',
        ], 422);
    }
    if ((int) $verificacao['tentativas'] >= AGENDAY_PORTAL_CODIGO_TENTATIVAS) {
        agenday_portal_erro('Muitas tentativas com este código. Peça um novo código.', 429);
    }

    $esperado = hash('sha256', (string) $verificacao['salt'] . ':' . $codigo);
    if (!hash_equals((string) $verificacao['codigo_hash'], $esperado)) {
        $stmt = $conexao->prepare(
            'UPDATE verificacao_acesso SET tentativas = tentativas + 1 WHERE id_verificacao = ?'
        );
        $stmt->bind_param('i', $verificacao['id_verificacao']);
        $stmt->execute();
        $stmt->close();
        $restantes = AGENDAY_PORTAL_CODIGO_TENTATIVAS - ((int) $verificacao['tentativas'] + 1);
        agenday_portal_json([
            'ok' => false,
            'codigo_invalido' => true,
            'restantes' => max(0, $restantes),
            'erro' => 'Código incorreto. Tentativas restantes: ' . max(0, $restantes) . '.',
        ], 422);
    }

    // Uso único: só uma requisição consegue consumir a verificação.
    $stmt = $conexao->prepare(
        'UPDATE verificacao_acesso SET usado_em = NOW()
          WHERE id_verificacao = ? AND usado_em IS NULL'
    );
    $stmt->bind_param('i', $verificacao['id_verificacao']);
    $stmt->execute();
    $consumida = $stmt->affected_rows === 1;
    $stmt->close();
    if (!$consumida) {
        agenday_portal_erro('Este código já foi usado. Peça um novo código.', 409);
    }

    $nome = '';
    if (isset($verificacao['nome_informado']) && is_string($verificacao['nome_informado'])) {
        $nome = trim($verificacao['nome_informado']);
    }
    if ($nome === '' && isset($corpo['nome']) && is_string($corpo['nome'])) {
        $nome = trim($corpo['nome']);
    }
    $cliente = agenday_portal_cliente_por_telefone($conexao, $empresa, $telefone);
    if ($cliente === null && $nome === '') {
        agenday_portal_json([
            'ok' => false,
            'precisa_nome' => true,
            'erro' => 'É o seu primeiro acesso. Informe seu nome completo.',
        ], 422);
    }

    $registro = agenday_portal_cliente_garantir($conexao, $empresa, $nome, $telefone);
    agenday_portal_sessao_criar($conexao, $empresa, (int) $registro['id_cliente']);
    $sessao = agenday_portal_sessao_ler($conexao, $empresa);

    agenday_portal_json([
        'ok' => true,
        'csrf' => $sessao !== null ? agenday_portal_csrf_token($sessao) : '',
        'cliente' => [
            'nome' => (string) $registro['nome_cliente'],
            'telefone' => agenday_portal_telefone_mascarado($telefone),
        ],
        'estabelecimento' => [
            'nome' => agenday_portal_nome_estabelecimento($estabelecimento),
            'slug' => (string) ($estabelecimento['slug'] ?? ''),
        ],
    ]);
}

/* ---------------------------------------------------------------------------
 * sair
 * ------------------------------------------------------------------------- */
if ($acao === 'sair') {
    if ($metodo !== 'POST') {
        header('Allow: POST');
        agenday_portal_erro('Método não permitido.', 405);
    }
    agenday_portal_exigir_origem();

    $sessao = agenday_portal_sessao_ler($conexao, $empresa);
    if ($sessao !== null) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($corpo['csrf_token'] ?? null);
        if (!agenday_portal_csrf_valido($sessao, is_string($token) ? $token : null)) {
            agenday_portal_erro('Sessão do formulário expirada. Recarregue a página.', 403);
        }
    }
    agenday_portal_sessao_apagar($conexao);
    agenday_portal_json(['ok' => true]);
}

agenday_portal_erro('Ação desconhecida.', 400);
