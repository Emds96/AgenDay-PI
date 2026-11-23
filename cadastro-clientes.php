<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/cadastro-reenvio.php';
require_once __DIR__ . '/lib/portal-cliente.php';

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
$semWhatsapp = isset($_POST['sem_whatsapp']) && $_POST['sem_whatsapp'] === '1';
// Identidade do portal: DDD + número. Null quando não há WhatsApp confirmado.
$telefoneDigits = $semWhatsapp ? null : agenday_portal_telefone_normalizar($contato);

if ($nome === '' || $letrasNome === 0 || $letrasNome > 120) {
    agenday_erro_cliente('O nome é obrigatório (máximo de 120 caracteres).');
}
if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    agenday_erro_cliente('Informe um e-mail válido ou deixe esse campo vazio.');
}
if (strlen($contato) > 20
    || ($contato !== '' && !preg_match('/^[0-9()+ .-]{8,20}$/D', $contato))
    || strlen($digitos) > 11) {
    agenday_erro_cliente('Informe um telefone válido (de 8 a 11 dígitos).');
}
if (!$semWhatsapp) {
    if ($contato === '' || strlen($digitos) < 8 || strlen($digitos) > 11) {
        agenday_erro_cliente('Informe o celular do cliente ou marque "não tem WhatsApp".');
    }
    if (!agenday_portal_telefone_whatsapp_ok($telefoneDigits)) {
        agenday_erro_cliente('Informe um celular com DDD e o 9 (11 dígitos), ou marque "não tem WhatsApp".');
    }
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

// Uma mesma tentativa só pode chegar ao INSERT uma vez. A marca é consumida
// depois da conferência do celular, para um erro de confirmação não obrigar a
// recarregar a página e perder o que já foi digitado.
$caminhoPublico = null;
$caminhoFisico = null;
try {
    require_once __DIR__ . '/conexao.php';

    // Celular confirmado por código: o número só vale como identidade do portal
    // depois que o cliente leu o código. Se o WhatsApp da plataforma estiver
    // fora do ar, não bloqueia o cadastro — só deixa de exigir a confirmação.
    if (!$semWhatsapp) {
        require_once __DIR__ . '/lib/whatsapp.php';
        $idEstabelecimentoAtual = (int) $_SESSION['id_estabelecimento'];
        try {
            $configWhats = agenday_whatsapp_config($conexao);
            $exigirConfirmacao = agenday_whatsapp_pronto($configWhats);
        } catch (Throwable $e) {
            $exigirConfirmacao = false;
        }
        if ($exigirConfirmacao
            && !agenday_portal_confirmacao_ler($idEstabelecimentoAtual, (string) $telefoneDigits)) {
            agenday_erro_cliente(
                'Confirme o celular pelo código do WhatsApp antes de salvar. '
                . 'Se o cliente não tiver WhatsApp, marque a opção correspondente.'
            );
        }
    }

    if (!agenday_cadastro_token_consumir('clientes', $_POST['cadastro_token'] ?? null)) {
        agenday_erro_cliente('Este cadastro já foi enviado ou expirou. Atualize a página antes de tentar novamente.');
    }

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
         (id_estabelecimento, nome_cliente, email_cliente, contato_cliente, telefone_digits, arquivo_cliente)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('isssss', $idEstabelecimento, $nome, $emailBanco, $contato, $telefoneDigits, $caminhoPublico);
    $stmt->execute();
    $stmt->close();

    // A confirmação vale para um cadastro só.
    if (!$semWhatsapp && $telefoneDigits !== null) {
        agenday_portal_confirmacao_limpar($idEstabelecimento, (string) $telefoneDigits);
    }

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
