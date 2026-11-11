<?php
require_once __DIR__ . '/sessao.php';

// Sempre validar o ID do estabelecimento, nunca o e-mail enviado pelo cliente.
if (!isset($_SESSION['id_estabelecimento'])
    || !is_int($_SESSION['id_estabelecimento'])
    || $_SESSION['id_estabelecimento'] < 1) {
    header('Location: index.php', true, 302);
    exit;
}

// Impede exibir telas antigas via cache depois de sair.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
