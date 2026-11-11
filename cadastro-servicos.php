<?php
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

function agenday_erro_servico(string $mensagem): void
{
    $_SESSION['erro_servico'] = $mensagem;
    header('Location: cad.servicos.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_erro_servico('A sessão do formulário expirou. Atualize a página e tente novamente.');
}

$nome = $_POST['nome'] ?? null;
$duracao = $_POST['duracao_minutos'] ?? null;
$preco = $_POST['preco'] ?? null;
$descricao = $_POST['descricao'] ?? null;

if (!is_string($nome) || !is_string($duracao) || !is_string($preco) || !is_string($descricao)) {
    agenday_erro_servico('Preencha os campos corretamente.');
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
    agenday_erro_servico('Informe o nome do serviço (máximo de 120 caracteres).');
}
if ($descricao !== '' && $caracteresDescricao > 2000) {
    agenday_erro_servico('A descrição pode ter no máximo 2.000 caracteres.');
}

// A duração da tela é em minutos; o MySQL armazena TIME (HH:MM:SS).
if (!preg_match('/\A[0-9]{1,4}\z/D', $duracao)) {
    agenday_erro_servico('Informe a duração em minutos, de 1 a 1440.');
}
$minutos = (int) $duracao;
if ($minutos < 1 || $minutos > 1440) {
    agenday_erro_servico('A duração deve ser de 1 a 1440 minutos.');
}
$duracaoBanco = sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60);

// Preço exato em DECIMAL(10,2), sem aritmética em ponto flutuante.
// Exemplos aceitos: 35,90; 35.90; 35; 0.
$precoNormalizado = str_replace(',', '.', $preco);
if (!preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/D', $precoNormalizado)) {
    agenday_erro_servico('Informe um preço válido, como 35,90 ou 35. Preços negativos não são aceitos.');
}
$partesPreco = explode('.', $precoNormalizado, 2);
$precoBanco = $partesPreco[0] . '.' . str_pad($partesPreco[1] ?? '', 2, '0');
$descricaoBanco = $descricao === '' ? null : $descricao;

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    $stmt = $conexao->prepare(
        'INSERT INTO servicos
         (id_estabelecimento, nome_servico, duracao_servico, preco_servico, descricao_servico)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $idEstabelecimento, $nome, $duracaoBanco, $precoBanco, $descricaoBanco);
    $stmt->execute();
    $stmt->close();

    $_SESSION['sucesso_servico'] = true;
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1062) {
        $_SESSION['erro_servico'] = 'Já existe um serviço com esse nome no estabelecimento.';
    } else {
        error_log('AgenDay: erro SQL ao cadastrar serviço: código ' . $e->getCode());
        $_SESSION['erro_servico'] = 'Não foi possível cadastrar o serviço agora. Tente novamente.';
    }
}

header('Location: cad.servicos.php', true, 303);
exit;
