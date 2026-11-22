<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/conexao.php';

// O estabelecimento vem da sessão, nunca da URL/formulário.
$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$busca = isset($_GET['busca']) && is_string($_GET['busca']) ? trim($_GET['busca']) : '';
// Limitar a 120 caracteres UTF-8 sem dividir acentos no meio.
$busca = preg_match('/^.{0,120}/us', $busca, $trechoBusca) ? $trechoBusca[0] : '';
$clientes = [];

if ($busca !== '') {
    $termo = '%' . $busca . '%';
    $stmt = $conexao->prepare(
        'SELECT id_cliente, nome_cliente, email_cliente, contato_cliente, arquivo_cliente
         FROM clientes WHERE id_estabelecimento = ?
         AND (nome_cliente LIKE ? OR email_cliente LIKE ? OR contato_cliente LIKE ?)
         ORDER BY nome_cliente ASC, id_cliente ASC'
    );
    $stmt->bind_param('isss', $idEstabelecimento, $termo, $termo, $termo);
} else {
    $stmt = $conexao->prepare(
        'SELECT id_cliente, nome_cliente, email_cliente, contato_cliente, arquivo_cliente
         FROM clientes WHERE id_estabelecimento = ?
         ORDER BY nome_cliente ASC, id_cliente ASC'
    );
    $stmt->bind_param('i', $idEstabelecimento);
}

$stmt->execute();
$stmt->bind_result($idCliente, $nomeCliente, $emailCliente, $contatoCliente, $arquivoCliente);
while ($stmt->fetch()) {
    $clientes[] = [
        'id_cliente' => $idCliente,
        'nome_cliente' => $nomeCliente,
        'email_cliente' => $emailCliente,
        'contato_cliente' => $contatoCliente,
        'arquivo_cliente' => $arquivoCliente,
        'faltas' => 0,
        'cancelamentos' => 0,
        'taxa' => 0,
    ];
}
$stmt->close();

/*
 * Histórico de ausências (FEE-01): quantas vezes o cliente faltou e quantas
 * cancelou. Serve para o salão cobrar a taxa pessoalmente, sem cobrança
 * automática (o sistema não tem meio de pagamento).
 *
 * Uma consulta só para a página inteira; o id do estabelecimento vem da
 * sessão e os clientes são os da lista atual.
 */
if ($clientes !== []) {
    require_once __DIR__ . '/lib/clientes-ausencias.php';
    $ids = array_map(static fn(array $cliente): int => (int) $cliente['id_cliente'], $clientes);
    $ausencias = agenday_clientes_ausencias($conexao, $idEstabelecimento, $ids);
    foreach ($clientes as $indice => $cliente) {
        $conta = $ausencias[(int) $cliente['id_cliente']] ?? [];
        $clientes[$indice]['faltas'] = (int) ($conta['faltas'] ?? 0);
        $clientes[$indice]['cancelamentos'] = (int) ($conta['cancelamentos'] ?? 0);
        $clientes[$indice]['taxa'] = (int) ($conta['taxa'] ?? 0);
    }
}
