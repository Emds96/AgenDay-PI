<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/portal-cliente.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

function agenday_erro_cliente_edicao(string $mensagem): void
{
    $_SESSION['erro_cliente'] = $mensagem;
    header('Location: cad-cliente.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_erro_cliente_edicao('A sessão do formulário expirou. Atualize a página e tente novamente.');
}

$idCliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$nome = $_POST['nome'] ?? null;
$email = $_POST['email'] ?? null;
$contato = $_POST['contato'] ?? null;

if (!is_int($idCliente) || !is_string($nome) || !is_string($email) || !is_string($contato)) {
    agenday_erro_cliente_edicao('Informe corretamente os dados do cliente.');
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
    agenday_erro_cliente_edicao('O nome é obrigatório (máximo de 120 caracteres).');
}
if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    agenday_erro_cliente_edicao('Informe um e-mail válido ou deixe esse campo vazio.');
}
if (strlen($contato) > 20
    || ($contato !== '' && !preg_match('/^[0-9()+ .-]{8,20}$/D', $contato))
    || strlen($digitos) > 11) {
    agenday_erro_cliente_edicao('Informe um telefone válido (de 8 a 11 dígitos).');
}
// O celular com WhatsApp é conferido mais abaixo, só quando o número muda:
// cliente antigo com telefone fixo continua podendo ser editado.

// Foto opcional: se enviada, substitui a anterior; mesmas regras do cadastro.
$foto = $_FILES['arquivo'] ?? null;
$extensao = null;
if ($foto !== null) {
    if (!is_array($foto) || !isset($foto['error']) || is_array($foto['error'])) {
        agenday_erro_cliente_edicao('Não foi possível validar a foto enviada.');
    }
    if ($foto['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($foto['error'] !== UPLOAD_ERR_OK
            || !isset($foto['tmp_name'], $foto['size'])
            || !is_string($foto['tmp_name'])
            || !is_uploaded_file($foto['tmp_name'])
            || $foto['size'] < 1 || $foto['size'] > 2 * 1024 * 1024) {
            agenday_erro_cliente_edicao('A foto deve ser JPG ou PNG e ter no máximo 2 MB.');
        }

        $dim = @getimagesize($foto['tmp_name']);
        if ($dim === false || $dim[0] < 1 || $dim[1] < 1
            || $dim[0] > 4096 || $dim[1] > 4096) {
            agenday_erro_cliente_edicao('A imagem enviada é inválida ou muito grande.');
        }
        if ($dim[2] === IMAGETYPE_JPEG) {
            $extensao = 'jpg';
            $mime = 'image/jpeg';
        } elseif ($dim[2] === IMAGETYPE_PNG) {
            $extensao = 'png';
            $mime = 'image/png';
        } else {
            agenday_erro_cliente_edicao('A foto precisa ser JPG ou PNG.');
        }
        if (function_exists('finfo_open')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if ($finfo->file($foto['tmp_name']) !== $mime) {
                agenday_erro_cliente_edicao('O formato da foto é inválido.');
            }
        }
    }
}

$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$caminhoPublico = null;
$caminhoFisico = null;
$arquivoAtual = null;

try {
    require_once __DIR__ . '/conexao.php';

    // O cliente precisa pertencer ao estabelecimento da sessão.
    $stmt = $conexao->prepare(
        'SELECT arquivo_cliente, telefone_digits FROM clientes WHERE id_cliente = ? AND id_estabelecimento = ?'
    );
    $stmt->bind_param('ii', $idCliente, $idEstabelecimento);
    $stmt->execute();
    $stmt->bind_result($arquivoAtual, $telefoneAtual);
    $existe = $stmt->fetch();
    $stmt->close();

    if (!$existe) {
        agenday_erro_cliente_edicao('Cliente não encontrado.');
    }

    // Só exige celular/confirmação quando o número realmente muda: corrigir o
    // nome de um cliente antigo com fixo não pode esbarrar em código.
    $telefoneMudou = (string) ($telefoneAtual ?? '') !== (string) ($telefoneDigits ?? '');
    if ($telefoneMudou && !$semWhatsapp) {
        if ($contato === '' || strlen($digitos) < 8 || strlen($digitos) > 11) {
            agenday_erro_cliente_edicao('Informe o celular do cliente ou marque "não tem WhatsApp".');
        }
        if (!agenday_portal_telefone_whatsapp_ok($telefoneDigits)) {
            agenday_erro_cliente_edicao(
                'Informe um celular com DDD e o 9 (11 dígitos), ou marque "não tem WhatsApp".'
            );
        }
        require_once __DIR__ . '/lib/whatsapp.php';
        try {
            $configWhats = agenday_whatsapp_config($conexao);
            $exigirConfirmacao = agenday_whatsapp_pronto($configWhats);
        } catch (Throwable $e) {
            $exigirConfirmacao = false;
        }
        if ($exigirConfirmacao
            && !agenday_portal_confirmacao_ler($idEstabelecimento, (string) $telefoneDigits)) {
            agenday_erro_cliente_edicao(
                'Confirme o novo celular pelo código do WhatsApp antes de salvar. '
                . 'Se o cliente não tiver WhatsApp, marque a opção correspondente.'
            );
        }
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

    $emailBanco = $email === '' ? null : $email;
    $arquivoFinal = $caminhoPublico !== null ? $caminhoPublico : $arquivoAtual;

    $stmt = $conexao->prepare(
        'UPDATE clientes
         SET nome_cliente = ?, email_cliente = ?, contato_cliente = ?, telefone_digits = ?, arquivo_cliente = ?
         WHERE id_cliente = ? AND id_estabelecimento = ?'
    );
    $stmt->bind_param('sssssii', $nome, $emailBanco, $contato, $telefoneDigits, $arquivoFinal, $idCliente, $idEstabelecimento);
    $stmt->execute();
    $stmt->close();

    // A confirmação vale para uma edição só.
    if ($telefoneMudou && $telefoneDigits !== null) {
        agenday_portal_confirmacao_limpar($idEstabelecimento, (string) $telefoneDigits);
    }

    // Remove a foto antiga somente após a atualização bem-sucedida.
    if ($caminhoPublico !== null && is_string($arquivoAtual)
        && preg_match('~^arquivos/clientes/[a-f0-9]{32}\.(jpg|png)$~D', $arquivoAtual)) {
        $antiga = __DIR__ . '/' . $arquivoAtual;
        if (is_file($antiga)) {
            @unlink($antiga);
        }
    }

    $_SESSION['sucesso_cliente'] = 'Cliente atualizado com sucesso!';
    header('Location: cad-cliente.php', true, 303);
    exit;
} catch (Throwable $e) {
    if ($caminhoFisico !== null && is_file($caminhoFisico)) {
        @unlink($caminhoFisico);
    }
    error_log('AgenDay: erro ao editar cliente: ' . get_class($e));
    agenday_erro_cliente_edicao('Não foi possível salvar as alterações agora. Tente novamente.');
}
