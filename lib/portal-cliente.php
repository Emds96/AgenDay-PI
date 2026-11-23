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

// A lista de domínios públicos do portal fica em guarda-host.php. Incluir aqui
// garante que os links absolutos funcionem também fora de sessao.php.
require_once __DIR__ . '/guarda-host.php';

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
    return rtrim(substr($slug, 0, 60), '-');
}

/**
 * Um slug já pertence a outro estabelecimento?
 *
 * $ignorarId permite validar o próprio registro durante a edição.
 */
function agenday_portal_slug_ocupado(mysqli $conexao, string $slug, int $ignorarId = 0): bool
{
    if (!agenday_portal_slug_valido($slug)) {
        return true;
    }
    $stmt = $conexao->prepare(
        'SELECT id_estabelecimento FROM estabelecimento
          WHERE slug = ? AND id_estabelecimento <> ? LIMIT 1'
    );
    $stmt->bind_param('si', $slug, $ignorarId);
    $stmt->execute();
    $stmt->store_result();
    $ocupado = $stmt->num_rows > 0;
    $stmt->close();
    return $ocupado;
}

/**
 * Primeiro slug livre a partir do nome: "barbearia-joao", "barbearia-joao-2"…
 *
 * Não grava nada. O sufixo numérico é o que resolve dois estabelecimentos com
 * o mesmo nome — o dono pode trocar depois na tela de Configurações.
 */
function agenday_portal_slug_disponivel(mysqli $conexao, string $nome, int $ignorarId = 0): string
{
    $base = agenday_portal_slug_sugerir($nome);
    if (!agenday_portal_slug_ocupado($conexao, $base, $ignorarId)) {
        return $base;
    }
    for ($numero = 2; $numero <= 50; $numero++) {
        $sufixo = '-' . $numero;
        $candidato = rtrim(substr($base, 0, 60 - strlen($sufixo)), '-') . $sufixo;
        if (!agenday_portal_slug_ocupado($conexao, $candidato, $ignorarId)) {
            return $candidato;
        }
    }
    // Fallback improvável: base cheia de colisões.
    $aleatorio = '-' . bin2hex(random_bytes(3));
    return rtrim(substr($base, 0, 60 - strlen($aleatorio)), '-') . $aleatorio;
}

/**
 * Grava o slug de uma conta recém-criada, resolvendo colisão de última hora.
 *
 * Duas contas com o mesmo nome podem calcular o mesmo slug ao mesmo tempo; o
 * índice único recusa a segunda e a tentativa seguinte já usa "-2".
 */
function agenday_portal_slug_gravar(mysqli $conexao, int $idEstabelecimento, string $nome): ?string
{
    for ($tentativa = 0; $tentativa < 5; $tentativa++) {
        $slug = agenday_portal_slug_disponivel($conexao, $nome, $idEstabelecimento);
        try {
            $stmt = $conexao->prepare(
                'UPDATE estabelecimento SET slug = ? WHERE id_estabelecimento = ?'
            );
            $stmt->bind_param('si', $slug, $idEstabelecimento);
            $stmt->execute();
            $stmt->close();
            return $slug;
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1062) {
                throw $e;
            }
        }
    }
    return null;
}

/**
 * Valida o endereço digitado pelo estabelecimento.
 *
 * Devolve [slug normalizado, erro]. O erro é null quando está tudo certo.
 * Texto com acento ou maiúscula é convertido, não recusado.
 */
function agenday_portal_slug_analisar(string $texto): array
{
    $slug = agenday_portal_slug_normalizar($texto);
    if ($slug === '') {
        return ['', 'Informe o endereço do portal.'];
    }
    if (in_array($slug, AGENDAY_PORTAL_SLUGS_RESERVADOS, true)) {
        return [$slug, 'Esse endereço é reservado pelo sistema. Escolha outro.'];
    }
    if (!agenday_portal_slug_valido($slug)) {
        return [$slug, 'Use de 3 a 60 caracteres: letras minúsculas, números e hífen.'];
    }
    return [$slug, null];
}

/* ---------------------------------------------------------------------------
 * Telefone
 * ------------------------------------------------------------------------- */

/**
 * Normaliza telefone brasileiro para DDD + número.
 *
 * Aceita "(92) 98111-1001", "92981111001", "+55 92 98111-1001" e
 * "5592981111001". Remove o prefixo 55 quando sobra DDD + número.
 * Retorna null quando não dá para formar um número nacional plausível.
 *
 * O padrão atual do celular no Brasil é DDD + 9 + 8 dígitos. Quem digita o
 * formato antigo (DDD + 8 dígitos começando em 6, 7, 8 ou 9) recebe o 9 na
 * frente. Fixos (começam em 2, 3, 4 ou 5) continuam com 10 dígitos.
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
    // Celular antigo (10 dígitos) passa para o padrão atual com o 9.
    if (strlen($digitos) === 10 && strpos('6789', $digitos[2]) !== false) {
        $digitos = substr($digitos, 0, 2) . '9' . substr($digitos, 2);
    }
    return $digitos;
}

/**
 * O número é um celular no padrão atual (11 dígitos, 9 na terceira posição)?
 *
 * Um telefone fixo continua servindo de contato na ficha do cliente, mas não
 * recebe mensagem: o WhatsApp não entrega nesse número.
 */
function agenday_portal_telefone_whatsapp_ok($digitos): bool
{
    return is_string($digitos)
        && strlen($digitos) === 11
        && $digitos[2] === '9';
}

/** Minutos que uma confirmação de celular vale dentro do painel. */
const AGENDAY_PORTAL_CONFIRMACAO_MINUTOS = 30;

/**
 * Guarda que este celular foi confirmado por código nesta sessão do painel.
 *
 * Fica na sessão (e não em coluna nova) para não exigir alteração de banco:
 * a confirmação vale só para o cadastro que está sendo feito agora.
 */
function agenday_portal_confirmacao_guardar(int $idEstabelecimento, string $digitos): void
{
    if (session_status() !== PHP_SESSION_ACTIVE || $digitos === '') {
        return;
    }
    $_SESSION['telefone_confirmado'][$idEstabelecimento . '|' . $digitos] = time();
}

/** O celular foi confirmado por código nos últimos minutos? */
function agenday_portal_confirmacao_ler(int $idEstabelecimento, string $digitos): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE || $digitos === '') {
        return false;
    }
    $quando = $_SESSION['telefone_confirmado'][$idEstabelecimento . '|' . $digitos] ?? 0;
    return is_int($quando)
        && $quando > time() - AGENDAY_PORTAL_CONFIRMACAO_MINUTOS * 60;
}

/** Descarta a confirmação depois de usada, para não valer em outro cadastro. */
function agenday_portal_confirmacao_limpar(int $idEstabelecimento, string $digitos): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    unset($_SESSION['telefone_confirmado'][$idEstabelecimento . '|' . $digitos]);
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

/**
 * Formas equivalentes do mesmo celular brasileiro.
 *
 * O WhatsApp guarda alguns celulares no formato antigo (DDD + 8 dígitos) e
 * outros no atual (DDD + 9 + 8 dígitos). Como o cliente pode digitar qualquer
 * uma das duas, a busca por cadastro testa as duas para não duplicar.
 *
 * @return string[]
 */
function agenday_portal_telefone_variantes(string $digitos): array
{
    if (strlen($digitos) !== 10 && strlen($digitos) !== 11) {
        return [$digitos];
    }
    $ddd = substr($digitos, 0, 2);
    $assinante = substr($digitos, 2);
    $variantes = [$digitos];

    if (strlen($digitos) === 11 && $assinante[0] === '9') {
        $variantes[] = $ddd . substr($assinante, 1);
    } elseif (strlen($digitos) === 10 && strpos('6789', $assinante[0]) !== false) {
        $variantes[] = $ddd . '9' . $assinante;
    }
    return array_values(array_unique($variantes));
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

/**
 * Domínios públicos onde o portal responde. O primeiro é o padrão.
 *
 * Um estabelecimento sem domínio próprio compartilha esse endereço com os
 * demais — o que diferencia cada um é o ?e=slug.
 */
function agenday_portal_hosts_publicos(): array
{
    $hosts = array_values(array_filter(array_map(
        static fn($host): string => agenday_portal_host_normalizar((string) $host),
        agenday_guarda_hosts_cliente()
    )));
    return $hosts !== [] ? $hosts : ['agenday-agendamentos.infinityfree.io'];
}

/**
 * O host é um dos endereços compartilhados do portal?
 *
 * Nesses endereços o estabelecimento é identificado SÓ pelo `?e=slug`. Se um
 * deles fosse aceito como "domínio próprio" no banco, o estabelecimento que o
 * gravasse abriria a própria agenda em qualquer acesso sem slug.
 */
function agenday_portal_dominio_eh_publico(string $host): bool
{
    $host = agenday_portal_host_normalizar($host);
    return $host !== '' && in_array($host, agenday_portal_hosts_publicos(), true);
}

/**
 * O sistema está respondendo em um host local (desenvolvimento)?
 *
 * Serve para montar links que funcionem onde o sistema está rodando. Sem isso,
 * uma mensagem gerada no local aponta para o domínio de produção com um token
 * que só existe no banco local — o link abre e diz "agendamento não encontrado".
 */
function agenday_portal_host_local(): bool
{
    $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
    if ($host === '') {
        return false;
    }
    $host = preg_replace('/:\d+\z/', '', $host) ?? $host;
    if ($host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || $host === '[::1]') {
        return true;
    }
    if (preg_match('/\A(10\.|127\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $host)) {
        return true;
    }
    return (bool) preg_match('/\.local\z/', $host);
}

/** Host atual com porta, usado nos links locais (ex.: 127.0.0.1:8080). */
function agenday_portal_host_atual_completo(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || !preg_match('/\A[A-Za-z0-9.\-\[\]:]{1,80}\z/', $host)) {
        return '';
    }
    return $host;
}

/**
 * Assinatura do formulário de cancelamento por link.
 *
 * A página não tem sessão: o token do link é a própria capacidade de cancelar.
 * A assinatura impede um POST cego de outro site e não depende do cabeçalho
 * Origin/Referer, que alguns navegadores móveis não enviam em formulários.
 */
function agenday_portal_assinatura_cancelamento(string $token): string
{
    return hash('sha256', 'agenday-cancelar-v1|' . $token);
}

/** Assinatura do formulário de confirmação de presença por link. */
function agenday_portal_assinatura_confirmacao(string $token): string
{
    return hash('sha256', 'agenday-confirmar-v1|' . $token);
}

/** A página pública usa token + assinatura para impedir POSTs cegos. */
function agenday_portal_confirmacao_autorizada(string $token, array $post): bool
{
    $assinatura = $post['assinatura'] ?? '';
    return is_string($assinatura)
        && $assinatura !== ''
        && hash_equals(agenday_portal_assinatura_confirmacao($token), $assinatura);
}

/**
 * Decide se um POST de cancelamento por link pode prosseguir.
 *
 * Duas provas independentes valem:
 *  1. a assinatura derivada do token, que o formulário reenvia no campo oculto;
 *  2. um Origin/Referer que aponte para o próprio site.
 *
 * A assinatura vem primeiro porque não depende de cabeçalho nenhum. O Origin
 * continua aceito como alternativa (páginas que ficaram em cache antes desta
 * correção), mas nunca é exigido: navegadores móveis e navegadores embutidos
 * (WhatsApp, Instagram) podem enviar `Origin: null` ou omitir o cabeçalho.
 */
function agenday_portal_cancelamento_autorizado(string $token, array $post): bool
{
    $assinatura = $post['assinatura'] ?? '';
    if (
        is_string($assinatura)
        && $assinatura !== ''
        && hash_equals(agenday_portal_assinatura_cancelamento($token), $assinatura)
    ) {
        return true;
    }

    return agenday_portal_origem_valida();
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

/**
 * Busca o estabelecimento pelo domínio do portal (sem "www." e sem porta).
 *
 * A coluna `dominio` é para domínio PRÓPRIO (ex.: studiobellavita.com.br). Os
 * domínios compartilhados do portal ficam de fora: neles quem identifica o
 * estabelecimento é sempre o slug, nunca o domínio.
 */
function agenday_portal_estabelecimento_por_dominio(mysqli $conexao, string $host): ?array
{
    $host = agenday_portal_host_normalizar($host);
    if ($host === '') {
        return null;
    }
    if (agenday_portal_dominio_eh_publico($host)) {
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
 * Prefixo do link do portal, terminado em "?e=".
 *
 * A tela de primeiro uso monta a prévia com este prefixo enquanto a pessoa
 * digita o nome do link. No local usa o host atual (para o link abrir o
 * sistema que gerou a mensagem); fora dele usa o domínio próprio do
 * estabelecimento e, sem domínio, o domínio público do portal.
 */
function agenday_portal_url_base(?string $dominio = null): string
{
    if (agenday_portal_host_local()) {
        $host = agenday_portal_host_atual_completo();
        if ($host !== '') {
            return 'http://' . $host . '/cliente.php?e=';
        }
    }
    $dominio = trim((string) $dominio);
    if ($dominio === '') {
        $dominio = agenday_portal_hosts_publicos()[0];
    }
    return 'https://' . agenday_portal_host_normalizar($dominio) . '/?e=';
}

/**
 * Base pública do estabelecimento para montar links (mensagens e QR).
 *
 * Sem slug não existe link: devolve '' para a tela mostrar "ainda não definido"
 * em vez de um endereço quebrado. O prefixo vem de agenday_portal_url_base().
 */
function agenday_portal_base_url(array $estabelecimento): string
{
    $slug = isset($estabelecimento['slug']) ? trim((string) $estabelecimento['slug']) : '';
    if ($slug === '') {
        return '';
    }
    $dominio = isset($estabelecimento['dominio']) ? (string) $estabelecimento['dominio'] : '';

    return agenday_portal_url_base($dominio) . rawurlencode($slug);
}

/* ---------------------------------------------------------------------------
 * Cliente
 * ------------------------------------------------------------------------- */

/** Procura o cliente do estabelecimento pelo telefone normalizado. */
function agenday_portal_cliente_por_telefone(mysqli $conexao, int $idEstabelecimento, string $digitos): ?array
{
    $variantes = agenday_portal_telefone_variantes($digitos);
    $marcadores = implode(', ', array_fill(0, count($variantes), '?'));
    $stmt = $conexao->prepare(
        'SELECT id_cliente, nome_cliente, telefone_digits
           FROM clientes
          WHERE id_estabelecimento = ? AND telefone_digits IN (' . $marcadores . ')
          ORDER BY id_cliente
          LIMIT 1'
    );
    $tipos = 'i' . str_repeat('s', count($variantes));
    $parametros = array_merge([$idEstabelecimento], $variantes);
    $stmt->bind_param($tipos, ...$parametros);
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
    $caminho = 'cliente-cancelar.php?t=' . rawurlencode($token);
    if (agenday_portal_host_local()) {
        $host = agenday_portal_host_atual_completo();
        if ($host !== '') {
            return 'http://' . $host . '/' . $caminho;
        }
    }
    $dominio = trim((string) ($estabelecimento['dominio'] ?? ''));
    if ($dominio === '') {
        // Sem domínio próprio, o link continua absoluto no domínio do portal:
        // um caminho relativo não abre quando chega por WhatsApp.
        $dominio = agenday_portal_hosts_publicos()[0];
    }
    return 'https://' . agenday_portal_host_normalizar($dominio) . '/' . $caminho;
}

/** Link público de confirmação de presença, enviado junto do lembrete. */
function agenday_portal_link_confirmacao(array $estabelecimento, string $token): string
{
    $caminho = 'cliente-confirmar.php?t=' . rawurlencode($token);
    if (agenday_portal_host_local()) {
        $host = agenday_portal_host_atual_completo();
        if ($host !== '') {
            return 'http://' . $host . '/' . $caminho;
        }
    }
    $dominio = trim((string) ($estabelecimento['dominio'] ?? ''));
    if ($dominio === '') {
        $dominio = agenday_portal_hosts_publicos()[0];
    }
    return 'https://' . agenday_portal_host_normalizar($dominio) . '/' . $caminho;
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
