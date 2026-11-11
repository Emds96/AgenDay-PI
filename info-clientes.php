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
    ];
}
$stmt->close();
