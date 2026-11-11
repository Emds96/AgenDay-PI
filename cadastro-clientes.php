<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/conexao.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}


$nome = mysqli_real_escape_string($conexao, trim($_POST['nome']));
$email = mysqli_real_escape_string($conexao, trim($_POST['email']));
$contato = mysqli_real_escape_string($conexao, trim(($_POST['contato'])));

if(isset($_FILES['arquivo'])) {
    $arquivo = $_FILES['arquivo'];

    if($arquivo['error'])
        die("falha ao enviar arquivo");

    $pasta = "arquivos/";
    $nomeDoArquivo = $arquivo['name'];
    $novoNomeDoArquivo = uniqid();
    $extensao = strtolower(pathinfo($nomeDoArquivo, PATHINFO_EXTENSION));

    if($extensao !="jpg" && $extensao != "png"){
        $_SESSION['msg'] = true;
	    header('Location:cad-cliente.php');
        die;
	    exit;
    };
        

    $path = $pasta . $novoNomeDoArquivo. "." . $extensao;
    $deu_certo = move_uploaded_file($arquivo["tmp_name"], $path);


    $sql = "SELECT count(*) as total from clientes where nome_cliente = '$nome'";
    $result = mysqli_query($conexao, $sql);
    
    
    $sql = "INSERT INTO `clientes`(`id_cliente`, `nome_cliente`, `email_cliete`, `contato_cliente`, `arquivo_cliente`)
     VALUES (NULL,'$nome','$email','$contato','$path')";
    
    if ($conexao->query($sql) === TRUE) {
        $_SESSION['status_cadastro'] = true;
    }
    
    $conexao->close();
    
    
    header('Location: cad-cliente.php');
    exit;
}

