<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/conexao.php';

// Filtro obrigatório por estabelecimento autenticado.
$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$sql = "SELECT * FROM clientes WHERE id_estabelecimento = {$idEstabelecimento} ORDER BY id_cliente ASC";
$result = $conexao->query($sql);
