<?php
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

function agenday_erro_servico_edicao(string $mensagem): void
{
    $_SESSION['erro_servico'] = $mensagem;
    header('Location: cad.servicos.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_erro_servico_edicao('A sessão do formulário expirou. Atualize a página e tente novamente.');
}

$idServico = filter_input(INPUT_POST, 'id_servicos', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$nome = $_POST['nome'] ?? null;
$duracao = $_POST['duracao_minutos'] ?? null;
$preco = $_POST['preco'] ?? null;
$descricao = $_POST['descricao'] ?? null;

if (!is_int($idServico) || !is_string($nome) || !is_string($duracao)
    || !is_string($preco) || !is_string($descricao)) {
    agenday_erro_servico_edicao('Preencha os campos corretamente.');
}

$nome = trim($nome);
$duracao = trim($duracao);
$preco = trim($preco);
$descricao = trim($descricao);

// Limites alinhados ao BD_v2.sql, sem depender de mbstring.
$caracteresNome = strlen($nome) <= 480 && preg_match('//u', $nome)
    ? preg_match_all('/./us', $nome) : 0;
$caracteresDescricao = strlen($descricao) <= 8000 && preg_match('//u', $descricao)
    ? preg_match_all('/./us', $descricao) : 2001;

if ($nome === '' || $caracteresNome === 0 || $caracteresNome > 120) {
    agenday_erro_servico_edicao('Informe o nome do serviço (máximo de 120 caracteres).');
}
if ($descricao !== '' && $caracteresDescricao > 2000) {
    agenday_erro_servico_edicao('A descrição pode ter no máximo 2.000 caracteres.');
}

// A duração da tela é em minutos; o MySQL armazena TIME (HH:MM:SS).
if (!preg_match('/\A[0-9]{1,4}\z/D', $duracao)) {
    agenday_erro_servico_edicao('Informe a duração em minutos, de 1 a 1440.');
}
$minutos = (int) $duracao;
if ($minutos < 1 || $minutos > 1440) {
    agenday_erro_servico_edicao('A duração deve ser de 1 a 1440 minutos.');
}
$duracaoBanco = sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60);

// Preço exato em DECIMAL(10,2), sem aritmética em ponto flutuante.
$precoNormalizado = str_replace(',', '.', $preco);
if (!preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/D', $precoNormalizado)) {
    agenday_erro_servico_edicao('Informe um preço válido, como 35,90 ou 35. Preços negativos não são aceitos.');
}
$partesPreco = explode('.', $precoNormalizado, 2);
$precoBanco = $partesPreco[0] . '.' . str_pad($partesPreco[1] ?? '', 2, '0');
$descricaoBanco = $descricao === '' ? null : $descricao;

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    // O serviço precisa pertencer ao estabelecimento da sessão.
    $stmt = $conexao->prepare(
        'SELECT id_servicos FROM servicos WHERE id_servicos = ? AND id_estabelecimento = ?'
    );
    $stmt->bind_param('ii', $idServico, $idEstabelecimento);
    $stmt->execute();
    $stmt->store_result();
    $existe = $stmt->num_rows === 1;
    $stmt->free_result();
    $stmt->close();

    if (!$existe) {
        agenday_erro_servico_edicao('Serviço não encontrado.');
    }

    $stmt = $conexao->prepare(
        'UPDATE servicos
         SET nome_servico = ?, duracao_servico = ?, preco_servico = ?, descricao_servico = ?
         WHERE id_servicos = ? AND id_estabelecimento = ?'
    );
    $stmt->bind_param('ssssii', $nome, $duracaoBanco, $precoBanco, $descricaoBanco, $idServico, $idEstabelecimento);
    $stmt->execute();
    $stmt->close();

    $_SESSION['sucesso_servico'] = 'Serviço atualizado com sucesso!';
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        $_SESSION['erro_servico'] = 'Já existe um serviço com esse nome no estabelecimento.';
    } else {
        error_log('AgenDay: erro SQL ao editar serviço: código ' . $e->getCode());
        $_SESSION['erro_servico'] = 'Não foi possível salvar as alterações agora. Tente novamente.';
    }
} catch (Throwable $e) {
    error_log('AgenDay: falha ao editar serviço (' . get_class($e) . ').');
    $_SESSION['erro_servico'] = 'Não foi possível salvar as alterações agora. Tente novamente.';
}

header('Location: cad.servicos.php', true, 303);
exit;
