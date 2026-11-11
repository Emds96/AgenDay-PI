<?php
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

function agenday_erro_cliente(string $mensagem): void
{
    $_SESSION['erro_cliente'] = $mensagem;
    header('Location: cad-cliente.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_erro_cliente('A sessão do formulário expirou. Atualize a página e tente novamente.');
}

$nome = $_POST['nome'] ?? null;
$email = $_POST['email'] ?? null;
$contato = $_POST['contato'] ?? null;
if (!is_string($nome) || !is_string($email) || !is_string($contato)) {
    agenday_erro_cliente('Informe corretamente os dados do cliente.');
}

$nome = trim($nome);
$email = trim($email);
$contato = trim($contato);
$letrasNome = strlen($nome) <= 480 && preg_match('//u', $nome) ? preg_match_all('/./us', $nome) : 0;
$digitos = preg_replace('/[^0-9]/', '', $contato);

if ($nome === '' || $letrasNome === 0 || $letrasNome > 120) {
    agenday_erro_cliente('O nome é obrigatório (máximo de 120 caracteres).');
}
if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    agenday_erro_cliente('Informe um e-mail válido ou deixe esse campo vazio.');
}
if (strlen($contato) > 20 || !preg_match('/^[0-9()+ .-]{8,20}$/D', $contato)
    || strlen($digitos) < 8 || strlen($digitos) > 15) {
    agenday_erro_cliente('Informe um telefone válido (de 8 a 15 dígitos).');
}

// Foto opcional: apenas JPG/PNG real, até 2 MB; nome gerado pelo servidor.
$foto = $_FILES['arquivo'] ?? null;
$extensao = null;
if ($foto !== null) {
    if (!is_array($foto) || !isset($foto['error']) || is_array($foto['error'])) {
        agenday_erro_cliente('Não foi possível validar a foto enviada.');
    }
    if ($foto['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($foto['error'] !== UPLOAD_ERR_OK
            || !isset($foto['tmp_name'], $foto['size'])
            || !is_string($foto['tmp_name'])
            || !is_uploaded_file($foto['tmp_name'])
            || $foto['size'] < 1 || $foto['size'] > 2 * 1024 * 1024) {
            agenday_erro_cliente('A foto deve ser JPG ou PNG e ter no máximo 2 MB.');
        }

        $dim = @getimagesize($foto['tmp_name']);
        if ($dim === false || $dim[0] < 1 || $dim[1] < 1
            || $dim[0] > 4096 || $dim[1] > 4096) {
            agenday_erro_cliente('A imagem enviada é inválida ou muito grande.');
        }
        if ($dim[2] === IMAGETYPE_JPEG) {
            $extensao = 'jpg';
            $mime = 'image/jpeg';
        } elseif ($dim[2] === IMAGETYPE_PNG) {
            $extensao = 'png';
            $mime = 'image/png';
        } else {
            agenday_erro_cliente('A foto precisa ser JPG ou PNG.');
        }
        if (function_exists('finfo_open')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if ($finfo->file($foto['tmp_name']) !== $mime) {
                agenday_erro_cliente('O formato da foto é inválido.');
            }
        }
    }
}

$caminhoPublico = null;
$caminhoFisico = null;
try {
    require_once __DIR__ . '/conexao.php';

    if ($extensao !== null) {
        $pasta = __DIR__ . '/arquivos/clientes';
        if (!is_dir($pasta) && !@mkdir($pasta, 0755, true) && !is_dir($pasta)) {
            throw new RuntimeException('Diretório de fotos indisponível.');
        }
        $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
        $caminhoPublico = 'arquivos/clientes/' . $nomeArquivo;
        $caminhoFisico = $pasta . '/' . $nomeArquivo;
        if (!move_uploaded_file($foto['tmp_name'], $caminhoFisico)) {
            throw new RuntimeException('Não foi possível salvar a foto.');
        }
    }

    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
    $emailBanco = $email === '' ? null : $email;
    $stmt = $conexao->prepare(
        'INSERT INTO clientes
         (id_estabelecimento, nome_cliente, email_cliente, contato_cliente, arquivo_cliente)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $idEstabelecimento, $nome, $emailBanco, $contato, $caminhoPublico);
    $stmt->execute();
    $stmt->close();

    $_SESSION['sucesso_cliente'] = true;
    header('Location: cad-cliente.php', true, 303);
    exit;
} catch (Throwable $e) {
    if ($caminhoFisico !== null && is_file($caminhoFisico)) {
        @unlink($caminhoFisico);
    }
    error_log('AgenDay: erro ao cadastrar cliente: ' . get_class($e));
    agenday_erro_cliente('Não foi possível cadastrar o cliente agora. Tente novamente.');
}
