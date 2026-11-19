<?php
declare(strict_types=1);
require_once __DIR__ . '/verifica_login.php';

// Compatibilidade: o antigo Perfil era uma tela demonstrativa sem persistência.
// Os dados reais da conta agora ficam na aba Minha Conta.
header('Location: config.php?aba=conta', true, 302);
exit;
