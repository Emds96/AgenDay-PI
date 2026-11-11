<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/conexao.php';

$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$busca = isset($_GET['busca']) && is_string($_GET['busca']) ? trim($_GET['busca']) : '';
$busca = preg_match('/^.{0,120}/us', $busca, $buscaEncontrada) ? $buscaEncontrada[0] : '';
$funcionarios = [];
$servicosDisponiveis = [];

// Carrega somente os serviços pertencentes ao estabelecimento autenticado.
$stmt = $conexao->prepare(
    'SELECT id_servicos, nome_servico FROM servicos
     WHERE id_estabelecimento = ? ORDER BY nome_servico ASC, id_servicos ASC'
);
$stmt->bind_param('i', $idEstabelecimento);
$stmt->execute();
$stmt->bind_result($idServico, $nomeServico);
while ($stmt->fetch()) {
    $servicosDisponiveis[] = [
        'id_servicos' => (int) $idServico,
        'nome_servico' => $nomeServico,
    ];
}
$stmt->close();

// A subconsulta mostra os serviços associados ao funcionário, sem misturar empresas.
$query = 'SELECT f.id_funcionario, f.nome_funcionario, f.contato_funcionario,
                 f.arquivo_funcionario,
                 (SELECT GROUP_CONCAT(s.nome_servico ORDER BY s.nome_servico SEPARATOR \', \')
                  FROM funcionario_servico fs
                  JOIN servicos s ON s.id_estabelecimento = fs.id_estabelecimento
                                 AND s.id_servicos = fs.id_servicos
                  WHERE fs.id_estabelecimento = f.id_estabelecimento
                    AND fs.id_funcionario = f.id_funcionario) AS servicos_vinculados
          FROM funcionarios f
          WHERE f.id_estabelecimento = ?';

if ($busca !== '') {
    $query .= ' AND (f.nome_funcionario LIKE ? OR f.contato_funcionario LIKE ?
                 OR EXISTS (
                   SELECT 1 FROM funcionario_servico fs2
                   JOIN servicos s2 ON s2.id_estabelecimento = fs2.id_estabelecimento
                                  AND s2.id_servicos = fs2.id_servicos
                   WHERE fs2.id_estabelecimento = f.id_estabelecimento
                     AND fs2.id_funcionario = f.id_funcionario
                     AND s2.nome_servico LIKE ?
                 ))';
}
$query .= ' ORDER BY f.nome_funcionario ASC, f.id_funcionario ASC';
$stmt = $conexao->prepare($query);

if ($busca !== '') {
    $termo = '%' . $busca . '%';
    $stmt->bind_param('isss', $idEstabelecimento, $termo, $termo, $termo);
} else {
    $stmt->bind_param('i', $idEstabelecimento);
}
$stmt->execute();
$stmt->bind_result($idFuncionario, $nomeFuncionario, $contatoFuncionario, $arquivoFuncionario, $servicosVinculados);
while ($stmt->fetch()) {
    $funcionarios[] = [
        'id_funcionario' => (int) $idFuncionario,
        'nome_funcionario' => $nomeFuncionario,
        'contato_funcionario' => $contatoFuncionario,
        'arquivo_funcionario' => $arquivoFuncionario,
        'servicos_vinculados' => $servicosVinculados,
    ];
}
$stmt->close();
