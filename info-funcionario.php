<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/conexao.php';

// Filtro obrigatório por estabelecimento autenticado.
$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$sql = "SELECT * FROM funcionarios WHERE id_estabelecimento = {$idEstabelecimento} ORDER BY id_funcionario ASC";
$result = $conexao->query($sql);
