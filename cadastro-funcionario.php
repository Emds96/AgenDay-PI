<?php
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

function agenday_erro_funcionario(string $mensagem): void
{
    $_SESSION['erro_funcionario'] = $mensagem;
    header('Location: cad-funcionario.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_erro_funcionario('O formulário expirou. Atualize a página e tente novamente.');
}

$nome = $_POST['nome'] ?? null;
$contato = $_POST['contato'] ?? null;
$servicosEnviados = $_POST['servicos'] ?? [];

if (!is_string($nome) || !is_string($contato) || !is_array($servicosEnviados)
    || count($servicosEnviados) > 100) {
    agenday_erro_funcionario('Confira os dados e os serviços selecionados.');
}

$nome = trim($nome);
$contato = trim($contato);
$letrasNome = strlen($nome) <= 480 && preg_match('//u', $nome)
    ? preg_match_all('/./us', $nome)
    : 0;
$digitos = preg_replace('/[^0-9]/', '', $contato);

if ($nome === '' || $letrasNome === 0 || $letrasNome > 120) {
    agenday_erro_funcionario('Informe o nome do funcionário (até 120 caracteres).');
}
if (strlen($contato) > 20 || !preg_match('/^[0-9()+ .-]{8,20}$/D', $contato)
    || strlen($digitos) < 8 || strlen($digitos) > 15) {
    agenday_erro_funcionario('Informe um telefone válido (8 a 15 dígitos).');
}

// Valida todos os IDs recebidos. Um serviço de outra empresa nunca será aceito.
$idsServicos = [];
foreach ($servicosEnviados as $valor) {
    if (!is_string($valor) || !ctype_digit($valor)) {
        agenday_erro_funcionario('Seleção de serviços inválida.');
    }
    $idServico = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($idServico === false) {
        agenday_erro_funcionario('Seleção de serviços inválida.');
    }
    $idsServicos[(int) $idServico] = (int) $idServico;
}
$idsServicos = array_values($idsServicos);

// Foto opcional: nunca confia no nome/extensão do arquivo enviado.
$foto = $_FILES['arquivo'] ?? null;
$extensao = null;
if ($foto !== null) {
    if (!is_array($foto) || !isset($foto['error']) || is_array($foto['error'])) {
        agenday_erro_funcionario('Não foi possível validar a foto.');
    }
    if ($foto['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($foto['error'] !== UPLOAD_ERR_OK
            || !isset($foto['tmp_name'], $foto['size'])
            || !is_string($foto['tmp_name'])
            || !is_uploaded_file($foto['tmp_name'])
            || $foto['size'] < 1 || $foto['size'] > 2 * 1024 * 1024) {
            agenday_erro_funcionario('A foto deve ser JPG ou PNG e ter até 2 MB.');
        }
        $dim = @getimagesize($foto['tmp_name']);
        if ($dim === false || $dim[0] < 1 || $dim[1] < 1 || $dim[0] > 4096 || $dim[1] > 4096) {
            agenday_erro_funcionario('A imagem é inválida ou muito grande.');
        }
        if ($dim[2] === IMAGETYPE_JPEG) {
            $extensao = 'jpg';
            $mime = 'image/jpeg';
        } elseif ($dim[2] === IMAGETYPE_PNG) {
            $extensao = 'png';
            $mime = 'image/png';
        } else {
            agenday_erro_funcionario('A foto precisa ser JPG ou PNG.');
        }
        if (function_exists('finfo_open')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            if ($finfo->file($foto['tmp_name']) !== $mime) {
                agenday_erro_funcionario('O formato da imagem é inválido.');
            }
        }
    }
}

$caminhoPublico = null;
$caminhoFisico = null;
$transacaoIniciada = false;

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    // A relação N:N usa uma FK composta que reforça a separação entre empresas.
    // Verifica antes de cadastrar para evitar funcionários parcialmente associados.
    $conexao->begin_transaction();
    $transacaoIniciada = true;
    if ($idsServicos !== []) {
        $validar = $conexao->prepare(
            'SELECT id_servicos FROM servicos WHERE id_estabelecimento = ? AND id_servicos = ? LIMIT 1'
        );
        foreach ($idsServicos as $idServico) {
            $validar->bind_param('ii', $idEstabelecimento, $idServico);
            $validar->execute();
            $validar->store_result();
            $valido = $validar->num_rows === 1;
            $validar->free_result();
            if (!$valido) {
                $validar->close();
                throw new DomainException('Serviço não pertence ao estabelecimento.');
            }
        }
        $validar->close();
    }

    if ($extensao !== null) {
        $pasta = __DIR__ . '/arquivos/funcionarios';
        if (!is_dir($pasta) && !@mkdir($pasta, 0755, true) && !is_dir($pasta)) {
            throw new RuntimeException('Pasta de fotos indisponível.');
        }
        $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
        $caminhoPublico = 'arquivos/funcionarios/' . $nomeArquivo;
        $caminhoFisico = $pasta . '/' . $nomeArquivo;
        if (!move_uploaded_file($foto['tmp_name'], $caminhoFisico)) {
            throw new RuntimeException('Falha ao salvar a foto.');
        }
    }

    $inserir = $conexao->prepare(
        'INSERT INTO funcionarios
         (id_estabelecimento, nome_funcionario, contato_funcionario, arquivo_funcionario)
         VALUES (?, ?, ?, ?)'
    );
    $inserir->bind_param('isss', $idEstabelecimento, $nome, $contato, $caminhoPublico);
    $inserir->execute();
    $idFuncionario = (int) $conexao->insert_id;
    $inserir->close();

    if ($idsServicos !== []) {
        $vincular = $conexao->prepare(
            'INSERT INTO funcionario_servico (id_estabelecimento, id_funcionario, id_servicos)
             VALUES (?, ?, ?)'
        );
        foreach ($idsServicos as $idServico) {
            $vincular->bind_param('iii', $idEstabelecimento, $idFuncionario, $idServico);
            $vincular->execute();
        }
        $vincular->close();
    }
    $conexao->commit();
    $transacaoIniciada = false;

    $_SESSION['sucesso_funcionario'] = true;
    header('Location: cad-funcionario.php', true, 303);
    exit;
} catch (Throwable $e) {
    if ($transacaoIniciada) {
        try {
            $conexao->rollback();
        } catch (Throwable $erroRollback) {
            error_log('AgenDay: falha ao reverter o cadastro do funcionário.');
        }
    }
    if ($caminhoFisico !== null && is_file($caminhoFisico)) {
        @unlink($caminhoFisico);
    }
    error_log('AgenDay: falha no cadastro do funcionário (' . get_class($e) . ').');
    agenday_erro_funcionario(
        $e instanceof DomainException
            ? 'Um dos serviços selecionados não está disponível para este estabelecimento.'
            : 'Não foi possível cadastrar o funcionário agora. Tente novamente.'
    );
}
