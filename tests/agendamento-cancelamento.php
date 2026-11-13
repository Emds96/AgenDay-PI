<?php
/**
 * Testes unitários da validação de ID de cancelamento e das regras de conflito.
 * Execução: php tests/agendamento-cancelamento.php
 * Não exige conexão MySQL. Não substitui testes transacionais reais.
 */
declare(strict_types=1);
require_once __DIR__ . '/../lib/agendamento-cancelar.php';

$contagem = 0;
function verificar_cancelamento(bool $ok, string $descricao): void
{
    global $contagem;
    $contagem++;
    if (!$ok) {
        throw new RuntimeException('Teste falhou: ' . $descricao);
    }
}

foreach (['1' => 1, '42' => 42, '4294967295' => 4294967295] as $entrada => $esperado) {
    verificar_cancelamento(
        agenday_agenda_validar_id_cancelamento((string) $entrada) === $esperado,
        'ID válido ' . $entrada
    );
}

foreach ([null, 1, [], '', '0', '-1', '01', '1 OR 1=1', '2.0', '4294967296', str_repeat('9', 20)] as $entrada) {
    $rejeitado = false;
    try {
        agenday_agenda_validar_id_cancelamento($entrada);
    } catch (InvalidArgumentException $e) {
        $rejeitado = true;
    }
    verificar_cancelamento($rejeitado, 'ID inválido');
}

// Espelha exatamente os predicados da consulta SQL da etapa 4.3.
// A integração/serialização efetiva será verificada com MySQL após publicação segura.
function ocupado_no_modelo(string $status, string $de, string $ate, string $novoDe, string $novoAte): bool
{
    return in_array($status, ['agendado', 'confirmado'], true)
        && $de < $novoAte && $ate > $novoDe;
}

$inicio = '2026-12-01 10:00:00';
$fim = '2026-12-01 11:00:00';

verificar_cancelamento(ocupado_no_modelo('agendado', $inicio, $fim, '2026-12-01 10:30:00', '2026-12-01 11:30:00'), 'Agendado colidente');
verificar_cancelamento(ocupado_no_modelo('confirmado', $inicio, $fim, '2026-12-01 09:30:00', '2026-12-01 10:30:00'), 'Confirmado colidente');
verificar_cancelamento(!ocupado_no_modelo('agendado', $inicio, $fim, '2026-12-01 11:00:00', '2026-12-01 12:00:00'), 'Atendimentos consecutivos');
verificar_cancelamento(!ocupado_no_modelo('agendado', $inicio, $fim, '2026-12-01 09:00:00', '2026-12-01 10:00:00'), 'Atendimentos sem sobreposição');
verificar_cancelamento(!ocupado_no_modelo('cancelado', $inicio, $fim, '2026-12-01 10:30:00', '2026-12-01 11:30:00'), 'Cancelado libera intervalo');
verificar_cancelamento(!ocupado_no_modelo('concluido', $inicio, $fim, '2026-12-01 10:30:00', '2026-12-01 11:30:00'), 'Concluído não bloqueia');

echo 'OK: ' . $contagem . ' verificações unitárias de cancelamento e intervalos.' . PHP_EOL;
