<?php
/**
 * Teste CLI sem conexão com MySQL:
 * php tests/agendamento-validacao.php
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/agendamento-gravar.php';

$fuso = agenday_agenda_fuso();
$dataFutura = (new DateTimeImmutable('now', $fuso))->modify('+2 days')->setTime(12, 0);
$valido = [
    'id_cliente' => '1',
    'id_servicos' => '2',
    'id_funcionario' => '3',
    'data' => $dataFutura->format('Y-m-d'),
    'hora' => '12:00',
    'observacoes' => 'Corte com tesoura.',
];
$testes = 0;

function verificar(bool $condicao, string $rotulo): void
{
    global $testes;
    $testes++;
    if (!$condicao) {
        throw new RuntimeException('FALHA no teste: ' . $rotulo);
    }
}

$resultado = agenday_agenda_validar_post($valido);
verificar($resultado['cliente'] === 1, 'Cliente válido');
verificar($resultado['servico'] === 2, 'Serviço válido');
verificar($resultado['funcionario'] === 3, 'Funcionário válido');
verificar($resultado['observacoes'] === 'Corte com tesoura.', 'Observações válidas');
verificar($resultado['inicio']->format('Y-m-d H:i') === $valido['data'] . ' 12:00', 'Data válida');

$observacoesOpcionais = $valido;
$observacoesOpcionais['observacoes'] = '  ';
verificar(agenday_agenda_validar_post($observacoesOpcionais)['observacoes'] === null, 'Observações opcionais');

$testesInvalidos = [
    'ID de cliente inválido' => ['id_cliente' => '0'],
    'ID de serviço inválido' => ['id_servicos' => '-1'],
    'ID de funcionário inválido' => ['id_funcionario' => 'nao-existe'],
    'Injeção no ID' => ['id_cliente' => '1 OR 1=1'],
    'Data inexistente' => ['data' => '2026-02-30'],
    'Dia inválido' => ['data' => '2026-13-12'],
    'Hora inválida' => ['hora' => '25:00'],
    'Minuto fora do passo' => ['hora' => '12:07'],
    'Observações longas' => ['observacoes' => str_repeat('a', 1001)],
    'Observações com bytes inválidos' => ['observacoes' => "\xff"],
    'Sem data' => ['data' => []],
    'Horário passado' => ['data' => '2000-01-01'],
    'Muito além de 366 dias' => [
        'data' => (new DateTimeImmutable('now', $fuso))->modify('+370 days')->format('Y-m-d'),
    ],
];

foreach ($testesInvalidos as $rotulo => $alteracoes) {
    $entrada = array_replace($valido, $alteracoes);
    $rejeitou = false;
    try {
        agenday_agenda_validar_post($entrada);
    } catch (InvalidArgumentException $e) {
        $rejeitou = true;
    }
    verificar($rejeitou, $rotulo);
}

echo 'OK: ' . $testes . ' verificações de validação da agenda.' . PHP_EOL;
