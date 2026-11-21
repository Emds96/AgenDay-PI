<?php
/**
 * AgenDay 2.0 — WhatsApp via WAHA (fila + envio).
 *
 * O WAHA roda fora da hospedagem (PC do responsável, atrás de um túnel HTTPS).
 * O site SEMPRE chama o WAHA; o WAHA nunca chama o site. Isso é o que torna o
 * envio possível na InfinityFree, onde não há cron e acessos automatizados de
 * entrada são bloqueados.
 *
 * Regras:
 * - a chave de API nunca é impressa, registrada em log ou devolvida em JSON;
 * - falha de envio NUNCA desfaz o agendamento: a mensagem fica na fila;
 * - nenhuma função deste arquivo abre conexão: recebe mysqli já aberto.
 */
declare(strict_types=1);

/** Tentativas máximas por mensagem antes de desistir. */
const AGENDAY_WHATSAPP_MAX_TENTATIVAS = 5;

/** Intervalo mínimo entre duas execuções automáticas da fila (sem cron). */
const AGENDAY_WHATSAPP_TRAVA_SEGUNDOS = 60;

/** Limite de pendências processadas por execução automática. */
const AGENDAY_WHATSAPP_LOTE_PADRAO = 2;

/* ---------------------------------------------------------------------------
 * Configuração
 * ------------------------------------------------------------------------- */

/**
 * Lê a configuração do WAHA (linha única). Devolve sempre um array com as
 * chaves esperadas, mesmo antes de existir configuração salva.
 */
function agenday_whatsapp_config(mysqli $conexao): array
{
    $padrao = [
        'ativo' => false,
        'base_url' => '',
        'api_key' => '',
        'sessao' => 'default',
        'ultima_execucao' => null,
    ];
    try {
        $resultado = $conexao->query(
            'SELECT ativo, base_url, api_key, sessao, ultima_execucao
               FROM whatsapp_config WHERE id_config = 1 LIMIT 1'
        );
        if ($resultado === false) {
            return $padrao;
        }
        $linha = $resultado->fetch_assoc();
        $resultado->free();
    } catch (Throwable $e) {
        error_log('AgenDay WhatsApp: configuracao indisponivel (' . get_class($e) . ').');
        return $padrao;
    }
    if (!$linha) {
        return $padrao;
    }
    $sessao = trim((string) ($linha['sessao'] ?? ''));
    return [
        'ativo' => (int) ($linha['ativo'] ?? 0) === 1,
        'base_url' => rtrim(trim((string) ($linha['base_url'] ?? '')), '/'),
        'api_key' => (string) ($linha['api_key'] ?? ''),
        'sessao' => $sessao !== '' ? $sessao : 'default',
        'ultima_execucao' => $linha['ultima_execucao'] ?? null,
    ];
}

/**
 * Salva a configuração. Quando $novaChave for vazia, mantém a chave atual
 * (a tela nunca devolve a chave para o navegador).
 */
function agenday_whatsapp_salvar_config(
    mysqli $conexao,
    bool $ativo,
    string $baseUrl,
    string $novaChave,
    string $sessao
): void {
    $baseUrl = rtrim(trim($baseUrl), '/');
    $sessao = trim($sessao) !== '' ? trim($sessao) : 'default';
    $ativoInt = $ativo ? 1 : 0;

    if ($novaChave !== '') {
        $stmt = $conexao->prepare(
            'UPDATE whatsapp_config SET ativo = ?, base_url = ?, api_key = ?, sessao = ?
              WHERE id_config = 1'
        );
        $stmt->bind_param('isss', $ativoInt, $baseUrl, $novaChave, $sessao);
    } else {
        $stmt = $conexao->prepare(
            'UPDATE whatsapp_config SET ativo = ?, base_url = ?, sessao = ?
              WHERE id_config = 1'
        );
        $stmt->bind_param('iss', $ativoInt, $baseUrl, $sessao);
    }
    $stmt->execute();
    $stmt->close();
}

/** A configuração está completa o bastante para enviar? */
function agenday_whatsapp_pronto(array $config): bool
{
    if (empty($config['ativo'])) {
        return false;
    }
    $url = (string) ($config['base_url'] ?? '');
    if ($url === '' || !preg_match('~\Ahttps?://[^\s/]+~i', $url)) {
        return false;
    }
    return (string) ($config['api_key'] ?? '') !== '';
}

/* ---------------------------------------------------------------------------
 * Número e conteúdo
 * ------------------------------------------------------------------------- */

/** chatId aceito pelo WAHA: 55 + DDD + número + "@c.us". */
function agenday_whatsapp_chat_id(string $digitos): string
{
    return '55' . $digitos . '@c.us';
}

/** Formata data/hora do banco (Y-m-d H:i:s) para exibição em mensagem. */
function agenday_whatsapp_data_hora(string $dataHora): array
{
    $ts = strtotime($dataHora);
    if ($ts === false) {
        return ['data' => $dataHora, 'hora' => ''];
    }
    return [
        'data' => date('d/m/Y', $ts),
        'hora' => date('H:i', $ts),
    ];
}

/**
 * Texto das mensagens. Mantém as mensagens curtas e com um único link.
 *
 * Dados esperados por tipo:
 * - codigo: estabelecimento, codigo, minutos
 * - confirmacao/lembrete: estabelecimento, nome, servico, profissional,
 *   data_inicio (Y-m-d H:i:s), link_cancelar
 * - remarcacao: os mesmos de confirmacao
 * - cancelamento: estabelecimento, data_inicio, link_agendar
 */
function agenday_whatsapp_texto(string $tipo, array $dados): string
{
    $estab = trim((string) ($dados['estabelecimento'] ?? 'AgenDay'));
    $nome = trim((string) ($dados['nome'] ?? ''));
    $saudacao = $nome !== '' ? 'Olá, ' . $nome . '!' : 'Olá!';

    switch ($tipo) {
        case 'codigo':
            $codigo = (string) ($dados['codigo'] ?? '');
            $minutos = (int) ($dados['minutos']
                ?? (defined('AGENDAY_PORTAL_CODIGO_MINUTOS') ? AGENDAY_PORTAL_CODIGO_MINUTOS : 10));
            return $estab . "\n\nSeu código de acesso é: " . $codigo
                . "\nEle vale por " . $minutos . " minutos e só pode ser usado uma vez."
                . "\nSe não foi você, ignore esta mensagem.";

        case 'confirmacao':
        case 'lembrete':
        case 'remarcacao':
            $quando = agenday_whatsapp_data_hora((string) ($dados['data_inicio'] ?? ''));
            $titulo = $tipo === 'remarcacao' ? 'Agendamento remarcado' : 'Agendamento confirmado';
            $linhas = [
                $saudacao,
                '',
                $titulo . ' no ' . $estab . ':',
                'Serviço: ' . trim((string) ($dados['servico'] ?? '')),
                'Profissional: ' . trim((string) ($dados['profissional'] ?? '')),
                'Data: ' . $quando['data'] . ' às ' . $quando['hora'],
            ];
            $link = trim((string) ($dados['link_cancelar'] ?? ''));
            if ($link !== '') {
                $linhas[] = '';
                $linhas[] = 'Precisa cancelar? ' . $link;
            }
            return implode("\n", $linhas);

        case 'cancelamento':
            $quando = agenday_whatsapp_data_hora((string) ($dados['data_inicio'] ?? ''));
            $linhas = [
                $saudacao,
                '',
                'Seu horário no ' . $estab . ' em ' . $quando['data'] . ' às ' . $quando['hora']
                    . ' foi cancelado.',
            ];
            $link = trim((string) ($dados['link_agendar'] ?? ''));
            if ($link !== '') {
                $linhas[] = 'Para escolher outro horário: ' . $link;
            }
            return implode("\n", $linhas);
    }

    return trim((string) ($dados['texto'] ?? ''));
}

/* ---------------------------------------------------------------------------
 * Envio (chamada HTTPS de saída)
 * ------------------------------------------------------------------------- */

/** Envia uma mensagem agora. Devolve ['ok' => bool, 'erro' => string, 'status' => int]. */
function agenday_whatsapp_enviar(array $config, string $digitos, string $texto): array
{
    if (!agenday_whatsapp_pronto($config)) {
        return ['ok' => false, 'erro' => 'WhatsApp não configurado.', 'status' => 0];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'erro' => 'cURL indisponível no servidor.', 'status' => 0];
    }

    $payload = json_encode([
        'session' => (string) $config['sessao'],
        'chatId' => agenday_whatsapp_chat_id($digitos),
        'text' => $texto,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        return ['ok' => false, 'erro' => 'Mensagem inválida.', 'status' => 0];
    }

    $curl = curl_init((string) $config['base_url'] . '/api/sendText');
    if ($curl === false) {
        return ['ok' => false, 'erro' => 'Não foi possível iniciar o envio.', 'status' => 0];
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'content-type: application/json',
            'X-Api-Key: ' . (string) $config['api_key'],
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($resposta === false) {
        return ['ok' => false, 'erro' => 'Sem resposta do serviço de WhatsApp.', 'status' => $status];
    }
    if ($status < 200 || $status >= 300) {
        // Nunca registrar o corpo: pode conter dados do número/conversa.
        return ['ok' => false, 'erro' => 'Serviço de WhatsApp respondeu HTTP ' . $status . '.', 'status' => $status];
    }
    return ['ok' => true, 'erro' => '', 'status' => $status];
}

/**
 * Testa a conexão com o WAHA e o estado da sessão.
 * Devolve ['ok' => bool, 'estado' => string, 'erro' => string].
 */
function agenday_whatsapp_testar(array $config): array
{
    if (!agenday_whatsapp_pronto($config)) {
        return ['ok' => false, 'estado' => '', 'erro' => 'Configure e ative o WhatsApp antes de testar.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'estado' => '', 'erro' => 'cURL indisponível no servidor.'];
    }

    $url = (string) $config['base_url'] . '/api/sessions/' . rawurlencode((string) $config['sessao']);
    $curl = curl_init($url);
    if ($curl === false) {
        return ['ok' => false, 'estado' => '', 'erro' => 'Não foi possível iniciar o teste.'];
    }
    curl_setopt_array($curl, [
        CURLOPT_HTTPHEADER => ['accept: application/json', 'X-Api-Key: ' . (string) $config['api_key']],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($resposta === false) {
        return ['ok' => false, 'estado' => '', 'erro' => 'Sem resposta do serviço de WhatsApp.'];
    }
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'estado' => '', 'erro' => 'Serviço respondeu HTTP ' . $status . '.'];
    }

    $dados = json_decode((string) $resposta, true);
    $estado = is_array($dados) && isset($dados['status']) && is_string($dados['status'])
        ? $dados['status']
        : 'desconhecido';
    return [
        'ok' => $estado === 'WORKING',
        'estado' => $estado,
        'erro' => $estado === 'WORKING' ? '' : 'A sessão do WhatsApp não está conectada (estado: ' . $estado . ').',
    ];
}

/* ---------------------------------------------------------------------------
 * Fila
 * ------------------------------------------------------------------------- */

/**
 * Coloca uma mensagem na fila.
 *
 * $quando: data/hora (Y-m-d H:i:s) para envio; null = agora.
 * Devolve o id da mensagem ou 0 em caso de falha.
 */
function agenday_whatsapp_enfileirar(
    mysqli $conexao,
    int $idEstabelecimento,
    ?int $idAgendamento,
    string $digitos,
    string $tipo,
    string $corpo,
    ?string $quando = null
): int {
    $tipos = ['codigo', 'confirmacao', 'lembrete', 'cancelamento', 'remarcacao'];
    if (!in_array($tipo, $tipos, true)) {
        return 0;
    }
    if ($quando === null || $quando === '') {
        $sql = 'INSERT INTO mensagem_whatsapp
                    (id_estabelecimento, id_agendamento, telefone, tipo, corpo, agendado_para)
                VALUES (?, ?, ?, ?, ?, NOW())';
    } else {
        $sql = 'INSERT INTO mensagem_whatsapp
                    (id_estabelecimento, id_agendamento, telefone, tipo, corpo, agendado_para)
                VALUES (?, ?, ?, ?, ?, ?)';
    }

    $stmt = $conexao->prepare($sql);
    if ($quando === null || $quando === '') {
        $stmt->bind_param('iisss', $idEstabelecimento, $idAgendamento, $digitos, $tipo, $corpo);
    } else {
        $stmt->bind_param('iissss', $idEstabelecimento, $idAgendamento, $digitos, $tipo, $corpo, $quando);
    }
    try {
        $stmt->execute();
        $id = (int) $conexao->insert_id;
    } catch (Throwable $e) {
        error_log('AgenDay WhatsApp: falha ao enfileirar (' . get_class($e) . ').');
        $id = 0;
    }
    $stmt->close();
    return $id;
}

/**
 * Agenda uma tentativa de envio para depois de a página ser entregue.
 *
 * Não existe cron na hospedagem, então o gatilho é o uso do sistema. A função
 * registra um encerramento que primeiro entrega a resposta ao navegador e só
 * depois tenta a fila: a página nunca fica esperando o WhatsApp.
 */
function agenday_whatsapp_gatilho_apos_resposta(mysqli $conexao): void
{
    static $agendado = false;
    if ($agendado) {
        return;
    }
    $agendado = true;

    register_shutdown_function(static function () use ($conexao): void {
        ignore_user_abort(true);
        // Entrega o HTML antes de qualquer chamada de rede.
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @flush();
        }
        try {
            if (agenday_whatsapp_pendentes($conexao) === 0) {
                return;
            }
            agenday_whatsapp_processar_fila($conexao, 2);
        } catch (Throwable $e) {
            error_log('AgenDay WhatsApp: gatilho de fila falhou (' . get_class($e) . ').');
        }
    });
}

/**
 * Processa até $limite mensagens pendentes.
 *
 * Sem cron na hospedagem, esta função é chamada por gatilho de uso (abrir a
 * agenda ou o portal) e pelo botão "Enviar pendentes agora". A trava de 1
 * minuto evita rajadas quando várias páginas abrem juntas.
 *
 * Devolve ['ok'=>bool, 'motivo'=>string, 'enviadas'=>int, 'falhas'=>int].
 */
function agenday_whatsapp_processar_fila(mysqli $conexao, int $limite = AGENDAY_WHATSAPP_LOTE_PADRAO): array
{
    $limite = max(1, min(10, $limite));
    $config = agenday_whatsapp_config($conexao);
    if (!agenday_whatsapp_pronto($config)) {
        return ['ok' => false, 'motivo' => 'nao_configurado', 'enviadas' => 0, 'falhas' => 0];
    }

    // Trava de 1 minuto (não há cron; o gatilho é o uso do sistema).
    $stmt = $conexao->prepare(
        'UPDATE whatsapp_config SET ultima_execucao = NOW()
          WHERE id_config = 1
            AND (ultima_execucao IS NULL
                 OR ultima_execucao < DATE_SUB(NOW(), INTERVAL ' . AGENDAY_WHATSAPP_TRAVA_SEGUNDOS . ' SECOND))'
    );
    $stmt->execute();
    $podeExecutar = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$podeExecutar) {
        return ['ok' => true, 'motivo' => 'aguardando', 'enviadas' => 0, 'falhas' => 0];
    }

    $stmt = $conexao->prepare(
        'SELECT id_mensagem, telefone, corpo, tentativas
           FROM mensagem_whatsapp
          WHERE status IN (\'pendente\', \'erro\')
            AND tentativas < ' . AGENDAY_WHATSAPP_MAX_TENTATIVAS . '
            AND agendado_para <= NOW()
          ORDER BY agendado_para, id_mensagem
          LIMIT ' . $limite
    );
    $stmt->execute();
    $pendentes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $enviadas = 0;
    $falhas = 0;
    foreach ($pendentes as $mensagem) {
        $id = (int) $mensagem['id_mensagem'];

        // Reserva a mensagem para não enviar duas vezes em pedidos simultâneos.
        $marca = $conexao->prepare(
            'UPDATE mensagem_whatsapp SET status = \'enviando\'
              WHERE id_mensagem = ? AND status IN (\'pendente\', \'erro\')'
        );
        $marca->bind_param('i', $id);
        $marca->execute();
        $reservada = $marca->affected_rows > 0;
        $marca->close();
        if (!$reservada) {
            continue;
        }

        $resultado = agenday_whatsapp_enviar(
            $config,
            (string) $mensagem['telefone'],
            (string) $mensagem['corpo']
        );

        if ($resultado['ok']) {
            $confirma = $conexao->prepare(
                'UPDATE mensagem_whatsapp
                    SET status = \'enviado\', enviado_em = NOW(), erro = NULL
                  WHERE id_mensagem = ?'
            );
            $confirma->bind_param('i', $id);
            $confirma->execute();
            $confirma->close();
            $enviadas++;
            continue;
        }

        $tentativas = (int) $mensagem['tentativas'] + 1;
        $espera = min(3600, (int) (60 * (2 ** ($tentativas - 1))));
        $erro = mb_substr($resultado['erro'], 0, 250);
        $falha = $conexao->prepare(
            'UPDATE mensagem_whatsapp
                SET status = \'erro\',
                    tentativas = ?,
                    erro = ?,
                    agendado_para = DATE_ADD(NOW(), INTERVAL ? SECOND)
              WHERE id_mensagem = ?'
        );
        $falha->bind_param('iisi', $tentativas, $erro, $espera, $id);
        $falha->execute();
        $falha->close();
        $falhas++;
    }

    return ['ok' => true, 'motivo' => '', 'enviadas' => $enviadas, 'falhas' => $falhas];
}

/** Quantas mensagens estão pendentes/em erro (para a tela de configurações). */
function agenday_whatsapp_pendentes(mysqli $conexao): int
{
    try {
        $resultado = $conexao->query(
            'SELECT COUNT(*) AS total FROM mensagem_whatsapp
              WHERE status IN (\'pendente\', \'erro\') AND tentativas < ' . AGENDAY_WHATSAPP_MAX_TENTATIVAS
        );
    } catch (Throwable $e) {
        return 0;
    }
    if ($resultado === false) {
        return 0;
    }
    $linha = $resultado->fetch_assoc();
    $resultado->free();
    return (int) ($linha['total'] ?? 0);
}
