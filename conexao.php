<?php
if ($_SERVER['SERVER_NAME'] == 'localhost' || $_SERVER['SERVER_NAME'] == '127.0.0.1') {
    // Ambiente Local (XAMPP)
    define('HOST', 'localhost');
    define('USUARIO', 'root');
    define('SENHA', '');
    define('DB', 'agenday');
} else {
    // Ambiente Online (InfinityFree)
    define('HOST', 'sql208.byetcluster.com');
    define('USUARIO', 'if0_43406270');
    define('SENHA', 'DB_PASSWORD_PLACEHOLDER');
    define('DB', 'if0_43406270_agenday');
}

$conexao = mysqli_connect(HOST, USUARIO, SENHA, DB) or die('não conectado');
