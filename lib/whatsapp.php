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

/**
 * Nome da plataforma que aparece como remetente.
 *
 * O número é único da AgenDay (um "canal" para todos os estabelecimentos), então
 * cada mensagem precisa dizer em nome de quem está falando — o cliente vê o
 * nome da plataforma no aparelho, não o do estabelecimento.
 */
const AGENDAY_WHATSAPP_REMETENTE = 'AgenDay';

/**
 * Prazo avisado na confirmação para remarcar ou cancelar.
 *
 * É o mesmo valor que o sistema aplica (`horas_cancelamento`, padrão 24) e
 * nenhuma tela altera esse campo — por isso o texto pode dizer 24h fixo.
 */
const AGENDAY_WHATSAPP_PRAZO_AVISO_HORAS = 24;

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

/**
 * Descobre o chatId exato que o WhatsApp usa para o número.
 *
 * Alguns celulares ficam no WhatsApp no formato antigo (DDD + 8 dígitos) e o
 * motor GOWS recusa o envio para a versão com o 9 ("no LID found"). O
 * check-exists devolve o JID real em `pn`, que é o formato aceito. Em qualquer
 * falha da consulta, cai no formato padrão e o envio decide.
 */
function agenday_whatsapp_resolver_chat(array $config, string $digitos): string
{
    $padrao = agenday_whatsapp_chat_id($digitos);
    if (!function_exists('curl_init')) {
        return $padrao;
    }

    $url = (string) $config['base_url'] . '/api/contacts/check-exists?phone='
        . rawurlencode('55' . $digitos) . '&session=' . rawurlencode((string) $config['sessao']);
    $curl = curl_init($url);
    if ($curl === false) {
        return $padrao;
    }
    curl_setopt_array($curl, [
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'X-Api-Key: ' . (string) $config['api_key'],
            'ngrok-skip-browser-warning: true',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($resposta === false || $status < 200 || $status >= 300) {
        return $padrao;
    }
    $dados = json_decode((string) $resposta, true);
    if (!is_array($dados) || empty($dados['numberExists'])) {
        return $padrao;
    }
    $pn = $dados['pn'] ?? '';
    if (is_string($pn) && preg_match('/\A\d{10,15}@c\.us\z/', $pn)) {
        return $pn;
    }
    return $padrao;
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
 * Linha que identifica quem envia.
 *
 * Como o número é da plataforma, o cliente recebe de "AgenDay" mesmo quando a
 * mensagem é do Studio Bella Vita. Esta linha deixa a origem explícita.
 */
function agenday_whatsapp_assinatura(string $estabelecimento): string
{
    $nome = trim($estabelecimento);
    if ($nome === '') {
        return 'Enviado pela ' . AGENDAY_WHATSAPP_REMETENTE . '.';
    }
    return 'Enviado pela ' . AGENDAY_WHATSAPP_REMETENTE . ' em nome de ' . $nome . '.';
}

/**
 * Texto das mensagens. Mantém as mensagens curtas e com um único link.
 *
 * Dados esperados por tipo:
 * - codigo: estabelecimento, codigo, minutos
 * - confirmacao: estabelecimento, nome, servico, profissional,
 *   data_inicio (Y-m-d H:i:s), link_portal (ou link_cancelar sem slug)
 * - lembrete: estabelecimento, nome, servico, profissional,
 *   data_inicio (Y-m-d H:i:s), contato (telefone do estabelecimento),
 *   link_confirmar
 * - remarcacao: os mesmos de confirmacao, com link_cancelar
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
            return implode("\n", [
                $estab,
                '',
                'Seu código de acesso é: ' . $codigo,
                'Ele vale por ' . $minutos . ' minutos e só pode ser usado uma vez.',
                'Se não foi você, ignore esta mensagem.',
                '',
                agenday_whatsapp_assinatura($estab),
            ]);

        case 'confirmacao':
            $quando = agenday_whatsapp_data_hora((string) ($dados['data_inicio'] ?? ''));
            $linhas = [
                $saudacao,
                '',
                'Agendamento confirmado no ' . $estab . ':',
                'Serviço: ' . trim((string) ($dados['servico'] ?? '')),
                'Profissional: ' . trim((string) ($dados['profissional'] ?? '')),
                'Data: ' . $quando['data'] . ' às ' . $quando['hora'],
                '',
                'Remarcar ou cancelar: até ' . AGENDAY_WHATSAPP_PRAZO_AVISO_HORAS
                    . 'h antes do horário marcado.',
            ];
            $linkPortal = trim((string) ($dados['link_portal'] ?? ''));
            $linkCancelar = trim((string) ($dados['link_cancelar'] ?? ''));
            if ($linkPortal !== '') {
                // Um link só: o portal, onde o cliente remarca ou cancela.
                $linhas[] = 'Precisa remarcar ou cancelar? Acesse: ' . $linkPortal;
            } elseif ($linkCancelar !== '') {
                // Estabelecimento sem endereço público: o token ainda resolve o cancelamento.
                $linhas[] = 'Precisa cancelar? ' . $linkCancelar;
            }
            $linhas[] = '';
            $linhas[] = agenday_whatsapp_assinatura($estab);
            return implode("\n", $linhas);

        case 'lembrete':
            $quando = agenday_whatsapp_data_hora((string) ($dados['data_inicio'] ?? ''));
            $linhas = [
                $saudacao,
                '',
                'Lembrete do seu horário no ' . $estab . ':',
                'Serviço: ' . trim((string) ($dados['servico'] ?? '')),
                'Profissional: ' . trim((string) ($dados['profissional'] ?? '')),
                'Data: ' . $quando['data'] . ' às ' . $quando['hora'],
                '',
                'Confirme sua presença:',
            ];
            $linkConfirmar = trim((string) ($dados['link_confirmar'] ?? ''));
            if ($linkConfirmar !== '') {
                $linhas[] = $linkConfirmar;
            }
            $linhas[] = '';
            $linhas[] = 'O prazo para remarcar ou cancelar já passou.';
            $contato = trim((string) ($dados['contato'] ?? ''));
            $linhas[] = $contato !== ''
                ? 'Se não puder comparecer, avise com antecedência: ' . $contato . '.'
                : 'Se não puder comparecer, avise com antecedência.';
            $linhas[] = '';
            $linhas[] = agenday_whatsapp_assinatura($estab);
            return implode("\n", $linhas);

        case 'remarcacao':
            $quando = agenday_whatsapp_data_hora((string) ($dados['data_inicio'] ?? ''));
            $titulo = 'Agendamento remarcado';
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
            $linhas[] = '';
            $linhas[] = agenday_whatsapp_assinatura($estab);
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
            $linhas[] = '';
            $linhas[] = agenday_whatsapp_assinatura($estab);
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
        'chatId' => agenday_whatsapp_resolver_chat($config, $digitos),
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
            // Evita a página de aviso do ngrok gratuito em túneis HTTP.
            'ngrok-skip-browser-warning: true',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($resposta === false) {
        return ['ok' => false, 'erro' => 'Sem resposta do serviço de WhatsApp.', 'status' => $status, 'sem_whatsapp' => false];
    }
    if ($status < 200 || $status >= 300) {
        // O corpo só é lido para distinguir "número sem WhatsApp" de falha do
        // serviço; nunca é registrado em log nem devolvido ao navegador.
        $detalhe = '';
        $json = json_decode((string) $resposta, true);
        if (is_array($json)) {
            $detalhe = (string) ($json['exception']['message'] ?? ($json['message'] ?? ''));
        }
        $semWhatsapp = $detalhe !== ''
            && (stripos($detalhe, 'not on WhatsApp') !== false || stripos($detalhe, 'no LID found') !== false);
        return [
            'ok' => false,
            'erro' => $semWhatsapp
                ? 'Este número não tem WhatsApp. Confira o número e tente de novo.'
                : 'Serviço de WhatsApp respondeu HTTP ' . $status . '.',
            'status' => $status,
            'sem_whatsapp' => $semWhatsapp,
        ];
    }
    return ['ok' => true, 'erro' => '', 'status' => $status, 'sem_whatsapp' => false];
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
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'X-Api-Key: ' . (string) $config['api_key'],
            'ngrok-skip-browser-warning: true',
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

/* ---------------------------------------------------------------------------
 * Lembrete automático (24 horas antes do horário)
 * ------------------------------------------------------------------------- */

/** Horas de antecedência do lembrete automático. */
const AGENDAY_WHATSAPP_LEMBRETE_HORAS = 24;

/**
 * Agenda o lembrete de um agendamento já gravado.
 *
 * Lê do banco cliente, serviço, profissional e estabelecimento, então serve
 * tanto para o portal quanto para o painel. Não agenda nada quando:
 * - o cliente não tem celular com WhatsApp confirmado;
 * - o agendamento está cancelado ou já começou;
 * - faltam menos de 30 minutos para a hora do lembrete (a confirmação acabou
 *   de sair e um segundo aviso na sequência só incomoda).
 *
 * Devolve o id da mensagem ou 0.
 */
function agenday_whatsapp_agendar_lembrete(
    mysqli $conexao,
    int $idEstabelecimento,
    int $idAgendamento
): int {
    if ($idEstabelecimento < 1 || $idAgendamento < 1) {
        return 0;
    }
    require_once __DIR__ . '/portal-cliente.php';

    // Um lembrete por agendamento: remarcar troca o antigo, não acumula. Feito
    // antes das checagens para que um novo horário sem lembrete não deixe o
    // aviso do horário antigo na fila.
    agenday_whatsapp_cancelar_lembrete($conexao, $idAgendamento);

    $horas = AGENDAY_WHATSAPP_LEMBRETE_HORAS;
    $stmt = $conexao->prepare(
        "SELECT a.data_inicio,
                c.nome_cliente, c.telefone_digits,
                s.nome_servico, f.nome_funcionario,
                e.nome_estabelecimento, e.contato_estabelecimento, e.slug, e.dominio,
                DATE_SUB(a.data_inicio, INTERVAL $horas HOUR) AS lembrete_em,
                (a.status IN ('agendado', 'confirmado')
                 AND a.data_inicio > NOW()
                 AND DATE_SUB(a.data_inicio, INTERVAL $horas HOUR)
                     > DATE_ADD(NOW(), INTERVAL 30 MINUTE)) AS pode_agendar
           FROM agendamentos a
           JOIN clientes c
             ON c.id_estabelecimento = a.id_estabelecimento AND c.id_cliente = a.id_cliente
           JOIN servicos s
             ON s.id_estabelecimento = a.id_estabelecimento AND s.id_servicos = a.id_servicos
           JOIN funcionarios f
             ON f.id_estabelecimento = a.id_estabelecimento AND f.id_funcionario = a.id_funcionario
           JOIN estabelecimento e
             ON e.id_estabelecimento = a.id_estabelecimento
          WHERE a.id_estabelecimento = ? AND a.id_agendamento = ?
          LIMIT 1"
    );
    $stmt->bind_param('ii', $idEstabelecimento, $idAgendamento);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$linha || (int) $linha['pode_agendar'] !== 1) {
        return 0;
    }

    $digitos = (string) ($linha['telefone_digits'] ?? '');
    if (!agenday_portal_telefone_whatsapp_ok($digitos)) {
        return 0;
    }

    // Telefone do salão para o cliente avisar que não vai; formatado quando possível.
    $contatoBruto = trim((string) ($linha['contato_estabelecimento'] ?? ''));
    $contatoDigitos = agenday_portal_telefone_normalizar($contatoBruto);
    $contato = $contatoDigitos !== null
        ? agenday_portal_telefone_formatar($contatoDigitos)
        : $contatoBruto;
    $token = agenday_portal_garantir_token($conexao, $idEstabelecimento, $idAgendamento);
    if ($token === '') {
        return 0;
    }
    $texto = agenday_whatsapp_texto('lembrete', [
        'estabelecimento' => (string) $linha['nome_estabelecimento'],
        'nome' => (string) $linha['nome_cliente'],
        'servico' => (string) $linha['nome_servico'],
        'profissional' => (string) $linha['nome_funcionario'],
        'data_inicio' => (string) $linha['data_inicio'],
        'contato' => $contato,
        'link_confirmar' => agenday_portal_link_confirmacao($linha, $token),
    ]);

    return agenday_whatsapp_enfileirar(
        $conexao,
        $idEstabelecimento,
        $idAgendamento,
        $digitos,
        'lembrete',
        $texto,
        (string) $linha['lembrete_em']
    );
}

/** Remove o lembrete ainda pendente de um agendamento. Devolve quantos saíram. */
function agenday_whatsapp_cancelar_lembrete(mysqli $conexao, int $idAgendamento): int
{
    if ($idAgendamento < 1) {
        return 0;
    }
    // 'enviando' fica de fora: outra requisição pode estar entregando agora.
    $stmt = $conexao->prepare(
        "DELETE FROM mensagem_whatsapp
          WHERE id_agendamento = ? AND tipo = 'lembrete'
            AND status IN ('pendente', 'erro')"
    );
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $idAgendamento);
    $stmt->execute();
    $total = $stmt->affected_rows;
    $stmt->close();
    return $total;
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
 * Descarta lembretes que perderam o sentido.
 *
 * Não há cron: a fila roda no uso do sistema, então um lembrete pode ficar
 * esperando horas. Se nesse meio-tempo o agendamento foi cancelado ou já
 * começou, avisar "seu horário é amanhã" seria pior do que não avisar.
 */
function agenday_whatsapp_limpar_lembretes_vencidos(mysqli $conexao): int
{
    try {
        $resultado = $conexao->query(
            "DELETE m FROM mensagem_whatsapp m
              LEFT JOIN agendamentos a ON a.id_agendamento = m.id_agendamento
              WHERE m.tipo = 'lembrete'
                AND m.status IN ('pendente', 'erro')
                AND (a.id_agendamento IS NULL
                     OR a.status NOT IN ('agendado', 'confirmado')
                     OR a.data_inicio <= NOW())"
        );
    } catch (Throwable $e) {
        return 0;
    }
    if ($resultado === false) {
        return 0;
    }
    return (int) $conexao->affected_rows;
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

    // Lembrete de horário cancelado ou já iniciado não deve sair nunca.
    agenday_whatsapp_limpar_lembretes_vencidos($conexao);

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
        $estado = agenday_whatsapp_entregar($conexao, $config, $mensagem);
        if ($estado === 'enviada') {
            $enviadas++;
        } elseif ($estado === 'falha') {
            $falhas++;
        }
    }

    return ['ok' => true, 'motivo' => '', 'enviadas' => $enviadas, 'falhas' => $falhas];
}

/**
 * Reserva e entrega uma mensagem da fila.
 *
 * A reserva (`pendente`/`erro` -> `enviando`) impede envio duplicado quando
 * duas requisições pegam a mesma mensagem. Devolve 'enviada', 'falha' ou
 * 'ocupada' (outra requisição já reservou).
 */
function agenday_whatsapp_entregar(mysqli $conexao, array $config, array $mensagem): string
{
    $id = (int) $mensagem['id_mensagem'];

    $marca = $conexao->prepare(
        'UPDATE mensagem_whatsapp SET status = \'enviando\'
          WHERE id_mensagem = ? AND status IN (\'pendente\', \'erro\')'
    );
    $marca->bind_param('i', $id);
    $marca->execute();
    $reservada = $marca->affected_rows > 0;
    $marca->close();
    if (!$reservada) {
        return 'ocupada';
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
        return 'enviada';
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
    return 'falha';
}

/**
 * Entrega agora uma mensagem recém-enfileirada, sem esperar a janela de 1 minuto.
 *
 * Criação, remarcação e cancelamento avisam o cliente na hora: sem cron na
 * hospedagem, a trava de 1 minuto da fila poderia deixar a confirmação parada
 * até a próxima página abrir. A reserva por mensagem continua valendo.
 *
 * @return array{ok:bool,motivo:string}
 */
function agenday_whatsapp_enviar_agora(mysqli $conexao, int $idMensagem): array
{
    if ($idMensagem <= 0) {
        return ['ok' => false, 'motivo' => 'sem_mensagem'];
    }
    $config = agenday_whatsapp_config($conexao);
    if (!agenday_whatsapp_pronto($config)) {
        return ['ok' => false, 'motivo' => 'nao_configurado'];
    }

    $stmt = $conexao->prepare(
        'SELECT id_mensagem, telefone, corpo, tentativas
           FROM mensagem_whatsapp
          WHERE id_mensagem = ? AND status IN (\'pendente\', \'erro\')
            AND tentativas < ' . AGENDAY_WHATSAPP_MAX_TENTATIVAS . '
          LIMIT 1'
    );
    $stmt->bind_param('i', $idMensagem);
    $stmt->execute();
    $mensagem = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$mensagem) {
        return ['ok' => false, 'motivo' => 'nao_encontrada'];
    }

    $estado = agenday_whatsapp_entregar($conexao, $config, $mensagem);
    return ['ok' => $estado === 'enviada', 'motivo' => $estado];
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
