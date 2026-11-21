<?php
/**
 * AgenDay 2.0 — horários livres para o portal do cliente.
 *
 * GET ?e=slug&servico=ID&data=AAAA-MM-DD[&funcionario=ID]
 *
 * Usa o MESMO motor da agenda administrativa (lib/disponibilidade.php):
 * horário de funcionamento, almoço, bloqueios, agendamentos ativos,
 * antecedência mínima e janela máxima. Nada é recalculado aqui.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/portal-cliente.php';
require_once __DIR__ . '/../lib/disponibilidade.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    agenday_portal_erro('Método não permitido.', 405);
}

$dia = agenday_disp_data_valida($_GET['data'] ?? null);
$idServico = filter_var($_GET['servico'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$funcionarioBruto = $_GET['funcionario'] ?? '';
$idFuncionario = ($funcionarioBruto === '' || $funcionarioBruto === '0' || $funcionarioBruto === null)
    ? null
    : filter_var($funcionarioBruto, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($dia === null || !is_int($idServico) || $idFuncionario === false) {
    agenday_portal_erro('Informe data, serviço e profissional válidos.', 422);
}

require_once __DIR__ . '/../conexao.php';

$estabelecimento = agenday_portal_estabelecimento_requisicao($conexao);
if ($estabelecimento === null) {
    agenday_portal_erro('Estabelecimento não encontrado.', 404);
}
$empresa = (int) $estabelecimento['id_estabelecimento'];

try {
    $disponibilidade = agenday_disponibilidade_dia($conexao, $empresa, $dia, $idServico, $idFuncionario);
} catch (DomainException $e) {
    agenday_portal_erro($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('AgenDay portal: erro ao calcular horários (' . get_class($e) . ').');
    agenday_portal_erro('Não foi possível calcular os horários agora.', 503);
}

if ($disponibilidade['servico'] === null) {
    agenday_portal_erro($disponibilidade['motivo'] ?? 'Serviço não encontrado.', 422);
}

// A página só precisa de hora, fim e disponibilidade; os nomes dos
// profissionais ficam no endpoint de dados.
$slots = [];
foreach ($disponibilidade['slots'] as $slot) {
    $slots[] = [
        'hora' => $slot['hora'],
        'fim' => $slot['fim'],
        'livre' => (bool) $slot['livre'],
        'passado' => (bool) $slot['passado'],
    ];
}

agenday_portal_json([
    'ok' => true,
    'data' => $dia,
    'fechado' => (bool) $disponibilidade['fechado'],
    'motivo' => $disponibilidade['motivo'],
    'servico' => [
        'id' => (int) $disponibilidade['servico']['id'],
        'nome' => (string) $disponibilidade['servico']['nome'],
        'duracao' => (int) $disponibilidade['servico']['duracao'],
        'preco' => (string) $disponibilidade['servico']['preco'],
    ],
    'slots' => $slots,
]);
