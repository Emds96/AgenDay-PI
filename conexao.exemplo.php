<?php
/**
 * AgenDay 2.0 — modelo de conexão para o banco próprio.
 *
 * NÃO RENOMEIE ESTE ARQUIVO NO GITHUB E NÃO COLOQUE SENHAS NO REPOSITÓRIO.
 * Faça uma cópia como "conexao.php" somente no servidor InfinityFree
 * (ou em seu ambiente local), preencha SENHA nessa cópia privada.
 *
 * O arquivo "conexao.php" já consta do .gitignore.
 */
declare(strict_types=1);

define('HOST', 'sql307.infinityfree.com');
define('USUARIO', 'if0_43415733');
define('DB', 'if0_43415733_agenday');
define('SENHA', 'PREENCHA_APENAS_NO_SERVIDOR');

if (SENHA === 'PREENCHA_APENAS_NO_SERVIDOR') {
    http_response_code(503);
    exit('A conexão com o banco ainda não foi configurada.');
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conexao = mysqli_connect(HOST, USUARIO, SENHA, DB, 3306);
    mysqli_set_charset($conexao, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    // Nunca exponha dados da conexão nem mensagens internas na resposta HTTP.
    error_log('AgenDay: falha ao conectar com MySQL, código ' . $e->getCode());
    http_response_code(503);
    exit('Não foi possível conectar ao banco de dados.');
}
