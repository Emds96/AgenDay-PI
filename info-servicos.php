<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/conexao.php';

// O estabelecimento é definido pela sessão, nunca pelo formulário ou URL.
$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$busca = isset($_GET['busca']) && is_string($_GET['busca']) ? trim($_GET['busca']) : '';
$busca = preg_match('/^.{0,120}/us', $busca, $trechoBusca) ? $trechoBusca[0] : '';
$servicos = [];

if ($busca !== '') {
    $termo = '%' . $busca . '%';
    $stmt = $conexao->prepare(
        'SELECT id_servicos, nome_servico, duracao_servico, preco_servico, descricao_servico
         FROM servicos
         WHERE id_estabelecimento = ?
           AND (nome_servico LIKE ? OR descricao_servico LIKE ?)
         ORDER BY nome_servico ASC, id_servicos ASC'
    );
    $stmt->bind_param('iss', $idEstabelecimento, $termo, $termo);
} else {
    $stmt = $conexao->prepare(
        'SELECT id_servicos, nome_servico, duracao_servico, preco_servico, descricao_servico
         FROM servicos WHERE id_estabelecimento = ?
         ORDER BY nome_servico ASC, id_servicos ASC'
    );
    $stmt->bind_param('i', $idEstabelecimento);
}

$stmt->execute();
$stmt->bind_result($idServico, $nomeServico, $duracaoServico, $precoServico, $descricaoServico);
while ($stmt->fetch()) {
    $servicos[] = [
        'id_servicos' => (int) $idServico,
        'nome_servico' => $nomeServico,
        'duracao_servico' => $duracaoServico,
        'preco_servico' => $precoServico,
        'descricao_servico' => $descricaoServico,
    ];
}
$stmt->close();
