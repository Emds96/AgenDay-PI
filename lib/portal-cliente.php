<?php
/**
 * AgenDay 2.0 — portal do cliente: funções puras e de banco compartilhadas.
 *
 * Este arquivo NÃO inicia sessão, NÃO abre conexão e NÃO imprime nada.
 * É carregado por cliente.php, cliente-cancelar.php, api/cliente-*.php e
 * pelas telas administrativas que precisam normalizar telefone ou slug.
 *
 * Regras gerais do projeto que valem aqui:
 * - o estabelecimento é sempre resolvido no servidor (slug ou domínio);
 * - nunca confiar em id_estabelecimento vindo do navegador;
 * - prepared statements em toda consulta.
 */
declare(strict_types=1);

/** Nome do cookie da sessão do cliente (independente do AGENDAYSESSID). */
const AGENDAY_PORTAL_COOKIE = 'AGENDAY_CLIENTE';

/** Validade da sessão do cliente, em dias. */
const AGENDAY_PORTAL_SESSAO_DIAS = 30;

/** Tamanho do código de acesso enviado pelo WhatsApp. */
const AGENDAY_PORTAL_CODIGO_DIGITOS = 6;

/** Minutos de validade do código de acesso. */
const AGENDAY_PORTAL_CODIGO_MINUTOS = 10;

/** Tentativas permitidas por código. */
const AGENDAY_PORTAL_CODIGO_TENTATIVAS = 5;

/** Slugs que nunca podem ser usados (colidem com arquivos/pastas do site). */
const AGENDAY_PORTAL_SLUGS_RESERVADOS = [
    'api', 'arquivos', 'css', 'img', 'js', 'lib', 'sql', 'tests',
    'cliente', 'clientes', 'admin', 'login', 'logout', 'index', 'config',
    'agenda', 'agendar', 'cadastro', 'cadastrar', 'perfil', 'financeiro',
];

/* ---------------------------------------------------------------------------
 * Slug
 * ------------------------------------------------------------------------- */

/**
 * Converte um texto livre em slug: minúsculas, sem acento, apenas [a-z0-9-].
 * "Barbearia do João" => "barbearia-do-joao".
 */
function agenday_portal_slug_normalizar(string $texto): string
{
    $mapa = [
        'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
    ];
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
    $texto = trim($texto, '-');
    if (strlen($texto) > 60) {
        $texto = rtrim(substr($texto, 0, 60), '-');
    }
    return $texto;
}

/** Slug válido: 3 a 60 caracteres, minúsculas, números e hífen simples. */
function agenday_portal_slug_valido(string $slug): bool
{
    if (strlen($slug) < 3 || strlen($slug) > 60) {
        return false;
    }
    if (!preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug)) {
        return false;
    }
    return !in_array($slug, AGENDAY_PORTAL_SLUGS_RESERVADOS, true);
}

/**
 * Sugere um slug a partir do nome do estabelecimento.
 * Se estiver reservado ou vazio, acrescenta um sufixo.
 */
function agenday_portal_slug_sugerir(string $nome): string
{
    $slug = agenday_portal_slug_normalizar($nome);
    if (strlen($slug) < 3) {
        $slug = 'estabelecimento';
    }
    if (in_array($slug, AGENDAY_PORTAL_SLUGS_RESERVADOS, true)) {
        $slug .= '-agenda';
    }
    return substr($slug, 0, 60);
}

/* ---------------------------------------------------------------------------
 * Telefone
 * ------------------------------------------------------------------------- */

/**
 * Normaliza telefone brasileiro para DDD + número (10 ou 11 dígitos).
 *
 * Aceita "(92) 98111-1001", "92981111001", "+55 92 98111-1001" e
 * "5592981111001". Remove o prefixo 55 quando sobra DDD + número.
 * Retorna null quando não dá para formar um número nacional plausível.
 *
 * Esta é a MESMA regra aplicada na migração SQL (coluna telefone_digits).
 */
function agenday_portal_telefone_normalizar($valor): ?string
{
    if (!is_string($valor) && !is_int($valor)) {
        return null;
    }
    $digitos = preg_replace('/[^0-9]/', '', (string) $valor) ?? '';
    if (strlen($digitos) === 12 || strlen($digitos) === 13) {
        if (strncmp($digitos, '55', 2) === 0) {
            $digitos = substr($digitos, 2);
        }
    }
    if (strlen($digitos) !== 10 && strlen($digitos) !== 11) {
        return null;
    }
    // DDD não começa com zero e o número não pode ser tudo zero.
    if ($digitos[0] === '0' || (int) $digitos === 0) {
        return null;
    }
    return $digitos;
}

/** Formata "92981111001" como "(92) 98111-1001". */
function agenday_portal_telefone_formatar(string $digitos): string
{
    if (strlen($digitos) === 11) {
        return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 5), substr($digitos, 7, 4));
    }
    if (strlen($digitos) === 10) {
        return sprintf('(%s) %s-%s', substr($digitos, 0, 2), substr($digitos, 2, 4), substr($digitos, 6, 4));
    }
    return $digitos;
}

/** Número no formato do WhatsApp (código do país + DDD + número). */
function agenday_portal_telefone_whatsapp(string $digitos): string
{
    return '55' . $digitos;
}

/** Versão parcialmente oculta, para mensagens de tela: "(92) 9****-1001". */
function agenday_portal_telefone_mascarado(string $digitos): string
{
    if (strlen($digitos) === 11) {
        return sprintf('(%s) %s****-%s', substr($digitos, 0, 2), substr($digitos, 2, 1), substr($digitos, 7, 4));
    }
    if (strlen($digitos) === 10) {
        return sprintf('(%s) ****-%s', substr($digitos, 0, 2), substr($digitos, 6, 4));
    }
    return '****';
}

/* ---------------------------------------------------------------------------
 * Tokens e hash de IP
 * ------------------------------------------------------------------------- */

/** Token aleatório de 64 caracteres hexadecimais. */
function agenday_portal_token_gerar(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Hash do IP para limites antiabuso. Não é anonimização forte (IPv4 é
 * enumerável); serve apenas para contar tentativas por origem.
 */
function agenday_portal_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || $ip === '') {
        return '';
    }
    return hash('sha256', 'agenday-portal|' . $ip);
}

/** Normaliza o host recebido: minúsculas, sem porta e sem "www.". */
function agenday_portal_host_normalizar(string $host): string
{
    $host = strtolower(trim($host));
    $host = preg_replace('/:\d+\z/', '', $host) ?? $host;
    if (strncmp($host, 'www.', 4) === 0) {
        $host = substr($host, 4);
    }
    return $host;
}

/* ---------------------------------------------------------------------------
 * Estabelecimento
 * ------------------------------------------------------------------------- */

/** Colunas públicas do estabelecimento usadas pelo portal. */
function agenday_portal_colunas_estabelecimento(): string
{
    return 'id_estabelecimento, nome_estabelecimento, slug, dominio,
            contato_estabelecimento, exigir_codigo_whatsapp,
            intervalo_minutos, antecedencia_minima_minutos, janela_maxima_dias,
            horas_cancelamento, tolerancia_atraso_minutos';
}

/** Busca o estabelecimento pelo slug público. */
function agenday_portal_estabelecimento_por_slug(mysqli $conexao, string $slug): ?array
{
    if (!agenday_portal_slug_valido($slug)) {
        return null;
    }
    $sql = 'SELECT ' . agenday_portal_colunas_estabelecimento()
        . ' FROM estabelecimento WHERE slug = ? LIMIT 1';
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $linha ?: null;
}

/** Busca o estabelecimento pelo domínio do portal (sem "www." e sem porta). */
function agenday_portal_estabelecimento_por_dominio(mysqli $conexao, string $host): ?array
{
    $host = agenday_portal_host_normalizar($host);
    if ($host === '') {
        return null;
    }
    $sql = 'SELECT ' . agenday_portal_colunas_estabelecimento()
        . ' FROM estabelecimento WHERE dominio = ? LIMIT 1';
    $stmt = $conexao->prepare($sql);
    $stmt->bind_param('s', $host);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $linha ?: null;
}

/**
 * Resolve o estabelecimento da requisição: primeiro pelo parâmetro `e`,
 * depois pelo domínio. É a única porta de entrada usada pelas páginas.
 */
function agenday_portal_estabelecimento_resolver(mysqli $conexao, ?string $slugParam, ?string $host): ?array
{
    if (is_string($slugParam) && $slugParam !== '') {
        $porSlug = agenday_portal_estabelecimento_por_slug($conexao, $slugParam);
        if ($porSlug !== null) {
            return $porSlug;
        }
    }
    if (is_string($host) && $host !== '') {
        return agenday_portal_estabelecimento_por_dominio($conexao, $host);
    }
    return null;
}

/**
 * Base pública do estabelecimento para montar links (mensagens e QR).
 * Usa o domínio quando existe; senão devolve caminho relativo com ?e=slug.
 */
function agenday_portal_base_url(array $estabelecimento): string
{
    $dominio = isset($estabelecimento['dominio']) ? trim((string) $estabelecimento['dominio']) : '';
    $slug = isset($estabelecimento['slug']) ? (string) $estabelecimento['slug'] : '';
    if ($dominio !== '') {
        return 'https://' . agenday_portal_host_normalizar($dominio) . '/?e=' . rawurlencode($slug);
    }
    return '?e=' . rawurlencode($slug);
}

/* ---------------------------------------------------------------------------
 * Cliente
 * ------------------------------------------------------------------------- */

/** Procura o cliente do estabelecimento pelo telefone normalizado. */
function agenday_portal_cliente_por_telefone(mysqli $conexao, int $idEstabelecimento, string $digitos): ?array
{
    $stmt = $conexao->prepare(
        'SELECT id_cliente, nome_cliente, telefone_digits
           FROM clientes
          WHERE id_estabelecimento = ? AND telefone_digits = ?
          ORDER BY id_cliente
          LIMIT 1'
    );
    $stmt->bind_param('is', $idEstabelecimento, $digitos);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $linha ?: null;
}

/**
 * Encontra ou cria o cliente do portal.
 *
 * O telefone é a identidade: se já existe cadastro com o mesmo número no
 * estabelecimento, ele é reaproveitado (e o nome só é atualizado quando o
 * cadastro ainda está sem nome). Caso contrário, cria um registro novo.
 *
 * Retorna ['id_cliente' => int, 'nome_cliente' => string, 'novo' => bool].
 */
function agenday_portal_cliente_garantir(
    mysqli $conexao,
    int $idEstabelecimento,
    string $nome,
    string $digitos
): array {
    $existente = agenday_portal_cliente_por_telefone($conexao, $idEstabelecimento, $digitos);
    if ($existente !== null) {
        return [
            'id_cliente' => (int) $existente['id_cliente'],
            'nome_cliente' => (string) $existente['nome_cliente'],
            'novo' => false,
        ];
    }

    $contato = agenday_portal_telefone_formatar($digitos);
    $stmt = $conexao->prepare(
        'INSERT INTO clientes (id_estabelecimento, nome_cliente, contato_cliente, telefone_digits)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('isss', $idEstabelecimento, $nome, $contato, $digitos);
    try {
        $stmt->execute();
        $id = (int) $conexao->insert_id;
    } catch (mysqli_sql_exception $e) {
        // Corrida: outro pedido criou o mesmo telefone entre a busca e o INSERT.
        $stmt->close();
        $repetido = agenday_portal_cliente_por_telefone($conexao, $idEstabelecimento, $digitos);
        if ($repetido === null) {
            throw $e;
        }
        return [
            'id_cliente' => (int) $repetido['id_cliente'],
            'nome_cliente' => (string) $repetido['nome_cliente'],
            'novo' => false,
        ];
    }
    $stmt->close();

    return ['id_cliente' => $id, 'nome_cliente' => $nome, 'novo' => true];
}

/**
 * Decide o caminho do acesso por telefone.
 *
 * Regra do portal: o código no WhatsApp confirma o número **no cadastro**
 * (primeiro acesso). Quem já tem cadastro no estabelecimento entra só com o
 * celular. A chave `exigir_codigo_whatsapp` desligada é contingência: libera
 * até o primeiro acesso sem código.
 *
 * Retorna ['acao' => 'direto'|'pedir_nome'|'enviar_codigo', 'motivo' => string].
 */
function agenday_portal_acesso_decidir(bool $clienteExiste, bool $exigirCodigo, string $nome): array
{
    if ($clienteExiste) {
        return ['acao' => 'direto', 'motivo' => 'cliente_existente'];
    }
    if (!$exigirCodigo) {
        return ['acao' => 'direto', 'motivo' => 'contingencia'];
    }
    if (trim($nome) === '') {
        return ['acao' => 'pedir_nome', 'motivo' => 'primeiro_acesso'];
    }
    return ['acao' => 'enviar_codigo', 'motivo' => 'cadastro'];
}

/* ---------------------------------------------------------------------------
 * Sessão do cliente (cookie próprio + registro no banco)
 * ------------------------------------------------------------------------- */

/** Lê o cookie da sessão do cliente, validando o formato. */
function agenday_portal_cookie_ler(): ?string
{
    $valor = $_COOKIE[AGENDAY_PORTAL_COOKIE] ?? null;
    if (!is_string($valor) || !preg_match('/\A[a-f0-9]{64}\z/', $valor)) {
        return null;
    }
    return $valor;
}

/** Grava o cookie da sessão do cliente. */
function agenday_portal_cookie_gravar(string $idSessao): void
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(AGENDAY_PORTAL_COOKIE, $idSessao, [
        'expires' => time() + (AGENDAY_PORTAL_SESSAO_DIAS * 86400),
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Apaga o cookie da sessão do cliente. */
function agenday_portal_cookie_apagar(): void
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(AGENDAY_PORTAL_COOKIE, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/** Cria a sessão no banco e devolve o identificador (já gravado no cookie). */
function agenday_portal_sessao_criar(mysqli $conexao, int $idEstabelecimento, int $idCliente): string
{
    $idSessao = agenday_portal_token_gerar();
    $csrf = agenday_portal_token_gerar();
    $ipHash = agenday_portal_ip_hash();

    $stmt = $conexao->prepare(
        'INSERT INTO sessao_cliente
            (id_sessao, id_estabelecimento, id_cliente, csrf_token, expira_em, ip_hash)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . AGENDAY_PORTAL_SESSAO_DIAS . ' DAY), ?)'
    );
    $stmt->bind_param('siiss', $idSessao, $idEstabelecimento, $idCliente, $csrf, $ipHash);
    $stmt->execute();
    $stmt->close();

    agenday_portal_cookie_gravar($idSessao);
    // Disponibiliza o cookie na requisição atual: setcookie() só vale na próxima.
    $_COOKIE[AGENDAY_PORTAL_COOKIE] = $idSessao;
    return $idSessao;
}

/**
 * Carrega a sessão atual (cookie válido e não expirado), com os dados do
 * cliente e do estabelecimento. Devolve null quando não há sessão válida.
 */
function agenday_portal_sessao_ler(mysqli $conexao, ?int $idEstabelecimento = null): ?array
{
    $idSessao = agenday_portal_cookie_ler();
    if ($idSessao === null) {
        return null;
    }

    $sql = 'SELECT s.id_sessao, s.id_estabelecimento, s.id_cliente, s.csrf_token,
                   c.nome_cliente, c.telefone_digits,
                   e.nome_estabelecimento, e.slug, e.dominio, e.exigir_codigo_whatsapp
              FROM sessao_cliente s
              JOIN clientes c
                ON c.id_estabelecimento = s.id_estabelecimento
               AND c.id_cliente = s.id_cliente
              JOIN estabelecimento e
                ON e.id_estabelecimento = s.id_estabelecimento
             WHERE s.id_sessao = ? AND s.expira_em > NOW()';
    if ($idEstabelecimento !== null) {
        // A sessão vale apenas no estabelecimento em que foi aberta.
        $sql .= ' AND s.id_estabelecimento = ?';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conexao->prepare($sql);
    if ($idEstabelecimento !== null) {
        $stmt->bind_param('si', $idSessao, $idEstabelecimento);
    } else {
        $stmt->bind_param('s', $idSessao);
    }
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$linha) {
        if ($idEstabelecimento === null) {
            agenday_portal_cookie_apagar();
        }
        return null;
    }
    return $linha;
}

/** Atualiza o último acesso (chamado pelo endpoint que valida a sessão). */
function agenday_portal_sessao_tocar(mysqli $conexao, string $idSessao): void
{
    $stmt = $conexao->prepare(
        'UPDATE sessao_cliente SET ultimo_acesso_em = CURRENT_TIMESTAMP WHERE id_sessao = ?'
    );
    $stmt->bind_param('s', $idSessao);
    $stmt->execute();
    $stmt->close();
}

/** Encerra a sessão no banco e no navegador. */
function agenday_portal_sessao_apagar(mysqli $conexao): void
{
    $idSessao = agenday_portal_cookie_ler();
    if ($idSessao !== null) {
        $stmt = $conexao->prepare('DELETE FROM sessao_cliente WHERE id_sessao = ?');
        $stmt->bind_param('s', $idSessao);
        $stmt->execute();
        $stmt->close();
    }
    agenday_portal_cookie_apagar();
}

/** Remove sessões vencidas (chamado de vez em quando, sem cron). */
function agenday_portal_sessao_limpar(mysqli $conexao): void
{
    $conexao->query('DELETE FROM sessao_cliente WHERE expira_em < DATE_SUB(NOW(), INTERVAL 7 DAY)');
}

/* ---------------------------------------------------------------------------
 * CSRF do portal
 * ------------------------------------------------------------------------- */

/** Token CSRF da sessão informada. */
function agenday_portal_csrf_token(array $sessao): string
{
    return (string) ($sessao['csrf_token'] ?? '');
}

/** Valida o token recebido no cabeçalho X-CSRF-Token ou no campo csrf_token. */
function agenday_portal_csrf_valido(array $sessao, ?string $token): bool
{
    $esperado = agenday_portal_csrf_token($sessao);
    if ($esperado === '' || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($esperado, $token);
}

/**
 * Garante o token público de um agendamento (cria na primeira necessidade).
 * Usado antes de enviar qualquer mensagem com link de cancelamento.
 */
function agenday_portal_garantir_token(mysqli $conexao, int $idEstabelecimento, int $idAgendamento): string
{
    $stmt = $conexao->prepare(
        'SELECT token_publico FROM agendamentos
          WHERE id_estabelecimento = ? AND id_agendamento = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $idEstabelecimento, $idAgendamento);
    $stmt->execute();
    $stmt->bind_result($token);
    $existe = $stmt->fetch();
    $stmt->close();
    if (!$existe) {
        return '';
    }
    if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        return $token;
    }

    $novo = agenday_portal_token_gerar();
    $stmt = $conexao->prepare(
        'UPDATE agendamentos SET token_publico = ?
          WHERE id_estabelecimento = ? AND id_agendamento = ?'
    );
    $stmt->bind_param('sii', $novo, $idEstabelecimento, $idAgendamento);
    $stmt->execute();
    $stmt->close();
    return $novo;
}

/* ---------------------------------------------------------------------------
 * Requisição do portal (endpoints JSON)
 * ------------------------------------------------------------------------- */

/** Fuso usado pelo projeto enquanto não existe fuso por estabelecimento. */
function agenday_portal_fuso(): DateTimeZone
{
    return new DateTimeZone(defined('AGENDAY_TIMEZONE') ? AGENDAY_TIMEZONE : 'America/Manaus');
}

/** Resolve o estabelecimento da requisição atual: `?e=` primeiro, depois o host. */
function agenday_portal_estabelecimento_requisicao(mysqli $conexao): ?array
{
    $slug = $_GET['e'] ?? null;
    $slug = is_string($slug) ? trim($slug) : null;
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return agenday_portal_estabelecimento_resolver($conexao, $slug, is_string($host) ? $host : '');
}

/** Lê o corpo da requisição: JSON (fetch) ou formulário tradicional. */
function agenday_portal_corpo(): array
{
    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';
    if (is_string($tipo) && stripos($tipo, 'application/json') !== false) {
        $bruto = file_get_contents('php://input');
        if (is_string($bruto) && $bruto !== '') {
            $dados = json_decode($bruto, true);
            if (is_array($dados)) {
                return $dados;
            }
        }
        return [];
    }
    return $_POST;
}

/**
 * Confere se o pedido veio do próprio site (Origin ou Referer).
 *
 * Não existe sessão antes do login, então o CSRF dos endpoints públicos é
 * feito pela origem + cabeçalho personalizado exigido pelo cliente JS.
 */
function agenday_portal_origem_valida(): bool
{
    $origem = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!is_string($origem) || $origem === '') {
        $origem = $_SERVER['HTTP_REFERER'] ?? '';
    }
    if (!is_string($origem) || $origem === '') {
        return false;
    }
    $hostOrigem = parse_url($origem, PHP_URL_HOST);
    if (!is_string($hostOrigem) || $hostOrigem === '') {
        return false;
    }
    $hostAtual = $_SERVER['HTTP_HOST'] ?? '';
    if (!is_string($hostAtual) || $hostAtual === '') {
        return false;
    }
    return strtolower($hostOrigem) === strtolower((string) preg_replace('/:\d+\z/', '', $hostAtual));
}

/** Encerra a requisição quando a origem não confere. */
function agenday_portal_exigir_origem(): void
{
    if (!agenday_portal_origem_valida()) {
        agenday_portal_erro('Pedido não reconhecido. Recarregue a página e tente novamente.', 403);
    }
}

/** Link público do portal do estabelecimento. */
function agenday_portal_link(array $estabelecimento): string
{
    return agenday_portal_base_url($estabelecimento);
}

/** Link de cancelamento sem login, enviado nas mensagens. */
function agenday_portal_link_cancelamento(array $estabelecimento, string $token): string
{
    $dominio = trim((string) ($estabelecimento['dominio'] ?? ''));
    $caminho = 'cliente-cancelar.php?t=' . rawurlencode($token);
    if ($dominio !== '') {
        return 'https://' . agenday_portal_host_normalizar($dominio) . '/' . $caminho;
    }
    return $caminho;
}

/**
 * Um agendamento pode ser cancelado pelo cliente agora?
 *
 * @return array{pode:bool,motivo:string,limite:?string}
 */
function agenday_portal_pode_cancelar(int $horasCancelamento, string $dataInicio): array
{
    $fuso = agenday_portal_fuso();
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dataInicio, $fuso);
    if ($inicio === false) {
        return ['pode' => false, 'motivo' => 'Horário do agendamento inválido.', 'limite' => null];
    }
    $horas = max(0, $horasCancelamento);
    $limite = $inicio->modify('-' . $horas . ' hours');
    $agora = new DateTimeImmutable('now', $fuso);
    if ($agora > $limite) {
        $texto = $horas > 0
            ? 'Cancelamentos são aceitos até ' . $horas . ' hora(s) antes do atendimento.'
            : 'Este horário já passou do prazo de cancelamento.';
        return ['pode' => false, 'motivo' => $texto, 'limite' => $limite->format('Y-m-d H:i:s')];
    }
    return ['pode' => true, 'motivo' => '', 'limite' => $limite->format('Y-m-d H:i:s')];
}

/* ---------------------------------------------------------------------------
 * Respostas JSON dos endpoints
 * ------------------------------------------------------------------------- */

/** Responde JSON e encerra a execução. */
function agenday_portal_json(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Resposta de erro padronizada. */
function agenday_portal_erro(string $mensagem, int $codigo = 422): void
{
    agenday_portal_json(['ok' => false, 'erro' => $mensagem], $codigo);
}
