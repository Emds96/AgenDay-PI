<?php
/**
 * AgenDay 2.0 — dados públicos do estabelecimento para o portal do cliente.
 *
 * GET ?e=slug → serviços, profissionais, regras e, se houver sessão, os dados
 * do próprio cliente. Nenhum dado de outro cliente é exposto.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/portal-cliente.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    agenday_portal_erro('Método não permitido.', 405);
}

require_once __DIR__ . '/../conexao.php';

$estabelecimento = agenday_portal_estabelecimento_requisicao($conexao);
if ($estabelecimento === null) {
    agenday_portal_erro('Estabelecimento não encontrado.', 404);
}
$empresa = (int) $estabelecimento['id_estabelecimento'];

/** Converte a duração TIME do banco em minutos. */
function agenday_cliente_duracao_minutos(string $duracao): int
{
    if (!preg_match('/\A(\d{1,3}):([0-5]\d):([0-5]\d)\z/D', $duracao, $m)) {
        return 0;
    }
    return ((int) $m[1]) * 60 + (int) $m[2];
}

$servicos = [];
$stmt = $conexao->prepare(
    'SELECT id_servicos, nome_servico, duracao_servico, preco_servico, descricao_servico
       FROM servicos
      WHERE id_estabelecimento = ?
      ORDER BY nome_servico'
);
$stmt->bind_param('i', $empresa);
$stmt->execute();
$resultado = $stmt->get_result();
while ($linha = $resultado->fetch_assoc()) {
    $servicos[] = [
        'id' => (int) $linha['id_servicos'],
        'nome' => (string) $linha['nome_servico'],
        'duracao' => agenday_cliente_duracao_minutos((string) $linha['duracao_servico']),
        'preco' => number_format((float) $linha['preco_servico'], 2, ',', '.'),
        'descricao' => trim((string) ($linha['descricao_servico'] ?? '')),
    ];
}
$stmt->close();

$profissionais = [];
$stmt = $conexao->prepare(
    'SELECT f.id_funcionario, f.nome_funcionario, fs.id_servicos
       FROM funcionarios f
       LEFT JOIN funcionario_servico fs
         ON fs.id_estabelecimento = f.id_estabelecimento
        AND fs.id_funcionario = f.id_funcionario
      WHERE f.id_estabelecimento = ?
      ORDER BY f.nome_funcionario'
);
$stmt->bind_param('i', $empresa);
$stmt->execute();
$resultado = $stmt->get_result();
$porId = [];
while ($linha = $resultado->fetch_assoc()) {
    $id = (int) $linha['id_funcionario'];
    if (!isset($porId[$id])) {
        $porId[$id] = ['id' => $id, 'nome' => (string) $linha['nome_funcionario'], 'servicos' => []];
    }
    if ($linha['id_servicos'] !== null) {
        $porId[$id]['servicos'][] = (int) $linha['id_servicos'];
    }
}
$stmt->close();
$profissionais = array_values($porId);

$sessao = agenday_portal_sessao_ler($conexao, $empresa);
$dadosSessao = null;
if ($sessao !== null) {
    agenday_portal_sessao_tocar($conexao, (string) $sessao['id_sessao']);
    $dadosSessao = [
        'csrf' => agenday_portal_csrf_token($sessao),
        'cliente' => [
            'nome' => (string) $sessao['nome_cliente'],
            'telefone' => agenday_portal_telefone_mascarado((string) $sessao['telefone_digits']),
        ],
    ];
}

agenday_portal_json([
    'ok' => true,
    'estabelecimento' => [
        'nome' => (string) $estabelecimento['nome_estabelecimento'],
        'slug' => (string) ($estabelecimento['slug'] ?? ''),
    ],
    'regras' => [
        'antecedencia_minutos' => (int) $estabelecimento['antecedencia_minima_minutos'],
        'janela_dias' => (int) $estabelecimento['janela_maxima_dias'],
        'intervalo_minutos' => (int) $estabelecimento['intervalo_minutos'],
        'horas_cancelamento' => (int) $estabelecimento['horas_cancelamento'],
    ],
    'exigir_codigo' => (int) $estabelecimento['exigir_codigo_whatsapp'] === 1,
    'servicos' => $servicos,
    'profissionais' => $profissionais,
    'sessao' => $dadosSessao,
]);
