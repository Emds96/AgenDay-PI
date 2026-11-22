<?php
/**
 * AgenDay 2.0 — agendamentos do cliente autenticado no portal.
 *
 * POST ?e=slug  (cabeçalho X-CSRF-Token obrigatório)
 *   acao=criar      {servico, funcionario?, data, hora, observacoes?}
 *   acao=listar     {}
 *   acao=cancelar   {id}
 *   acao=remarcar   {id, data, hora, funcionario?}
 *
 * Toda operação é limitada ao cliente da sessão e ao estabelecimento resolvido
 * no servidor. O id do cliente nunca vem do navegador.
 */
declare(strict_types=1);

require_once __DIR__ . '/../lib/portal-cliente.php';
require_once __DIR__ . '/../lib/whatsapp.php';
require_once __DIR__ . '/../lib/disponibilidade.php';
require_once __DIR__ . '/../lib/agendamento-gravar.php';
require_once __DIR__ . '/../lib/agendamento-cancelar.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    agenday_portal_erro('Método não permitido.', 405);
}
agenday_portal_exigir_origem();

$corpo = agenday_portal_corpo();
$acao = $_GET['acao'] ?? ($corpo['acao'] ?? '');
if (!is_string($acao) || $acao === '') {
    agenday_portal_erro('Ação não informada.', 400);
}

require_once __DIR__ . '/../conexao.php';

$estabelecimento = agenday_portal_estabelecimento_requisicao($conexao);
if ($estabelecimento === null) {
    agenday_portal_erro('Estabelecimento não encontrado.', 404);
}
$empresa = (int) $estabelecimento['id_estabelecimento'];
$nomeEstabelecimento = trim((string) ($estabelecimento['nome_estabelecimento'] ?? '')) ?: 'AgenDay';

$sessao = agenday_portal_sessao_ler($conexao, $empresa);
if ($sessao === null) {
    agenday_portal_erro('Entre com seu celular para continuar.', 401);
}
$tokenCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($corpo['csrf_token'] ?? null);
if (!agenday_portal_csrf_valido($sessao, is_string($tokenCsrf) ? $tokenCsrf : null)) {
    agenday_portal_erro('Sessão do formulário expirada. Recarregue a página.', 403);
}
$idCliente = (int) $sessao['id_cliente'];
$telefone = (string) $sessao['telefone_digits'];

/** Lê um identificador positivo do corpo JSON. */
function agenday_cliente_id($valor): ?int
{
    if (is_int($valor)) {
        return $valor > 0 ? $valor : null;
    }
    if (is_string($valor) && preg_match('/\A[1-9][0-9]{0,9}\z/D', $valor)) {
        return (int) $valor;
    }
    return null;
}

/** Busca um agendamento do próprio cliente, com nomes de serviço e profissional. */
function agenday_cliente_buscar(mysqli $conexao, int $empresa, int $idCliente, int $id): ?array
{
    $stmt = $conexao->prepare(
        'SELECT a.id_agendamento, a.id_servicos, a.id_funcionario, a.data_inicio, a.data_fim,
                a.status, a.token_publico, s.nome_servico, f.nome_funcionario
           FROM agendamentos a
           INNER JOIN servicos s
              ON s.id_estabelecimento = a.id_estabelecimento AND s.id_servicos = a.id_servicos
           INNER JOIN funcionarios f
              ON f.id_estabelecimento = a.id_estabelecimento AND f.id_funcionario = a.id_funcionario
          WHERE a.id_estabelecimento = ? AND a.id_cliente = ? AND a.id_agendamento = ?
          LIMIT 1'
    );
    $stmt->bind_param('iii', $empresa, $idCliente, $id);
    $stmt->execute();
    $linha = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $linha ?: null;
}

/** Monta o item devolvido à interface. */
function agenday_cliente_item(array $linha, int $horasCancelamento): array
{
    $ativo = $linha['status'] === 'agendado' || $linha['status'] === 'confirmado';
    $janela = agenday_portal_pode_cancelar($horasCancelamento, (string) $linha['data_inicio']);
    return [
        'id' => (int) $linha['id_agendamento'],
        'data' => substr((string) $linha['data_inicio'], 0, 10),
        'hora' => substr((string) $linha['data_inicio'], 11, 5),
        'fim' => substr((string) $linha['data_fim'], 11, 5),
        'servico' => (string) $linha['nome_servico'],
        'servico_id' => (int) $linha['id_servicos'],
        'profissional' => (string) $linha['nome_funcionario'],
        'profissional_id' => (int) $linha['id_funcionario'],
        'status' => (string) $linha['status'],
        'pode_cancelar' => $ativo && $janela['pode'],
        'motivo_bloqueio' => $ativo && !$janela['pode'] ? $janela['motivo'] : '',
    ];
}

/* ---------------------------------------------------------------------------
 * criar
 * ------------------------------------------------------------------------- */
if ($acao === 'criar') {
    $idServico = agenday_cliente_id($corpo['servico'] ?? null);
    $idFuncionario = agenday_cliente_id($corpo['funcionario'] ?? null);
    $dia = agenday_disp_data_valida($corpo['data'] ?? null);
    $hora = agenday_disp_hora_valida($corpo['hora'] ?? null);
    if ($idServico === null || $dia === null || $hora === null) {
        agenday_portal_erro('Escolha o serviço, a data e o horário.', 422);
    }

    $servico = agenday_disp_servico($conexao, $empresa, $idServico);
    if ($servico === null) {
        agenday_portal_erro('Serviço não encontrado neste estabelecimento.', 422);
    }

    $escolhido = agenday_disponibilidade_escolher($conexao, $empresa, $dia, $hora, $idServico, $idFuncionario);
    if ($escolhido === null) {
        agenday_portal_json([
            'ok' => false,
            'conflito' => true,
            'erro' => 'Este horário não está mais livre. Escolha outro.',
        ], 409);
    }

    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dia . ' ' . $hora, agenday_portal_fuso());
    if ($inicio === false) {
        agenday_portal_erro('Data ou horário inválidos.', 422);
    }

    $observacoes = isset($corpo['observacoes']) && is_string($corpo['observacoes'])
        ? trim($corpo['observacoes'])
        : '';
    if (mb_strlen($observacoes) > 500) {
        agenday_portal_erro('As observações podem ter até 500 caracteres.', 422);
    }
    $observacoes = $observacoes !== '' ? $observacoes : null;

    $tokenPublico = agenday_portal_token_gerar();
    try {
        $idAgendamento = agenday_agenda_inserir(
            $conexao,
            $empresa,
            $idCliente,
            $idServico,
            $escolhido,
            $inicio,
            $observacoes,
            $tokenPublico
        );
    } catch (DomainException $e) {
        agenday_portal_json(['ok' => false, 'conflito' => true, 'erro' => $e->getMessage()], 409);
    } catch (Throwable $e) {
        error_log('AgenDay portal: falha ao criar agendamento (' . get_class($e) . ').');
        agenday_portal_erro('Não foi possível salvar o agendamento agora. Tente novamente.', 503);
    }

    $stmt = $conexao->prepare(
        'SELECT nome_funcionario FROM funcionarios
          WHERE id_estabelecimento = ? AND id_funcionario = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $empresa, $escolhido);
    $stmt->execute();
    $stmt->bind_result($nomeProfissional);
    $stmt->fetch();
    $stmt->close();

    $texto = agenday_whatsapp_texto('confirmacao', [
        'estabelecimento' => $nomeEstabelecimento,
        'nome' => (string) $sessao['nome_cliente'],
        'servico' => $servico['nome'],
        'profissional' => (string) $nomeProfissional,
        'data_inicio' => $inicio->format('Y-m-d H:i:s'),
        'link_portal' => agenday_portal_link($estabelecimento),
        'link_cancelar' => agenday_portal_link_cancelamento($estabelecimento, $tokenPublico),
    ]);
    $idMensagem = agenday_whatsapp_enfileirar($conexao, $empresa, $idAgendamento, $telefone, 'confirmacao', $texto);
    try {
        agenday_whatsapp_enviar_agora($conexao, $idMensagem);
    } catch (Throwable $e) {
        error_log('AgenDay portal: fila do WhatsApp falhou (' . get_class($e) . ').');
    }

    // Confirmação na hora e lembrete 24h antes: dois avisos, dois momentos.
    try {
        agenday_whatsapp_agendar_lembrete($conexao, $empresa, $idAgendamento);
    } catch (Throwable $e) {
        error_log('AgenDay portal: lembrete não agendado (' . get_class($e) . ').');
    }

    agenday_portal_json([
        'ok' => true,
        'agendamento' => [
            'id' => $idAgendamento,
            'data' => $dia,
            'hora' => $hora,
            'fim' => $inicio->modify('+' . (int) $servico['duracao'] . ' minutes')->format('H:i'),
            'duracao' => (int) $servico['duracao'],
            'servico' => $servico['nome'],
            'profissional' => (string) $nomeProfissional,
            'link_cancelar' => agenday_portal_link_cancelamento($estabelecimento, $tokenPublico),
        ],
    ], 201);
}

/* ---------------------------------------------------------------------------
 * listar
 * ------------------------------------------------------------------------- */
if ($acao === 'listar') {
    $stmt = $conexao->prepare(
        'SELECT a.id_agendamento, a.id_servicos, a.id_funcionario, a.data_inicio, a.data_fim,
                a.status, a.token_publico, s.nome_servico, f.nome_funcionario
           FROM agendamentos a
           INNER JOIN servicos s
              ON s.id_estabelecimento = a.id_estabelecimento AND s.id_servicos = a.id_servicos
           INNER JOIN funcionarios f
              ON f.id_estabelecimento = a.id_estabelecimento AND f.id_funcionario = a.id_funcionario
          WHERE a.id_estabelecimento = ? AND a.id_cliente = ?
            AND a.data_inicio >= DATE_SUB(NOW(), INTERVAL 90 DAY)
          ORDER BY a.data_inicio DESC
          LIMIT 100'
    );
    $stmt->bind_param('ii', $empresa, $idCliente);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $horas = (int) $estabelecimento['horas_cancelamento'];
    $proximos = [];
    $historico = [];
    $agora = (new DateTimeImmutable('now', agenday_portal_fuso()))->format('Y-m-d H:i:s');
    while ($linha = $resultado->fetch_assoc()) {
        $item = agenday_cliente_item($linha, $horas);
        $ativo = $linha['status'] === 'agendado' || $linha['status'] === 'confirmado';
        if ($ativo && (string) $linha['data_inicio'] >= $agora) {
            $proximos[] = $item;
        } else {
            $historico[] = $item;
        }
    }
    $stmt->close();

    // Próximos em ordem crescente (o mais perto primeiro).
    usort($proximos, static fn(array $a, array $b): int => strcmp($a['data'] . $a['hora'], $b['data'] . $b['hora']));

    agenday_portal_json([
        'ok' => true,
        'cliente' => [
            'nome' => (string) $sessao['nome_cliente'],
            'telefone' => agenday_portal_telefone_mascarado($telefone),
        ],
        'proximos' => $proximos,
        'historico' => $historico,
    ]);
}

/* ---------------------------------------------------------------------------
 * cancelar
 * ------------------------------------------------------------------------- */
if ($acao === 'cancelar') {
    $id = agenday_cliente_id($corpo['id'] ?? null);
    if ($id === null) {
        agenday_portal_erro('Informe o agendamento que deseja cancelar.', 422);
    }
    $linha = agenday_cliente_buscar($conexao, $empresa, $idCliente, $id);
    if ($linha === null) {
        agenday_portal_erro('Agendamento não encontrado.', 404);
    }
    if ($linha['status'] !== 'agendado' && $linha['status'] !== 'confirmado') {
        agenday_portal_erro('Este agendamento não pode mais ser cancelado.', 409);
    }
    $janela = agenday_portal_pode_cancelar((int) $estabelecimento['horas_cancelamento'], (string) $linha['data_inicio']);
    if (!$janela['pode']) {
        agenday_portal_json([
            'ok' => false,
            'fora_do_prazo' => true,
            'erro' => $janela['motivo'] . ' Fale com o estabelecimento.',
        ], 409);
    }

    try {
        agenday_agenda_cancelar_para($conexao, $empresa, $id);
    } catch (DomainException $e) {
        agenday_portal_erro($e->getMessage(), 409);
    } catch (Throwable $e) {
        error_log('AgenDay portal: falha ao cancelar (' . get_class($e) . ').');
        agenday_portal_erro('Não foi possível cancelar agora. Tente novamente.', 503);
    }

    $token = agenday_portal_garantir_token($conexao, $empresa, $id);
    $texto = agenday_whatsapp_texto('cancelamento', [
        'estabelecimento' => $nomeEstabelecimento,
        'nome' => (string) $sessao['nome_cliente'],
        'data_inicio' => (string) $linha['data_inicio'],
        'link_agendar' => agenday_portal_link($estabelecimento),
    ]);
    $idMensagemCancelamento = agenday_whatsapp_enfileirar($conexao, $empresa, $id, $telefone, 'cancelamento', $texto);
    try {
        agenday_whatsapp_enviar_agora($conexao, $idMensagemCancelamento);
    } catch (Throwable $e) {
        error_log('AgenDay portal: fila do WhatsApp falhou (' . get_class($e) . ').');
    }

    agenday_portal_json([
        'ok' => true,
        'mensagem' => 'Agendamento cancelado. O horário foi liberado.',
        'token' => $token,
    ]);
}

/* ---------------------------------------------------------------------------
 * remarcar
 * ------------------------------------------------------------------------- */
if ($acao === 'remarcar') {
    $id = agenday_cliente_id($corpo['id'] ?? null);
    $dia = agenday_disp_data_valida($corpo['data'] ?? null);
    $hora = agenday_disp_hora_valida($corpo['hora'] ?? null);
    $idFuncionario = agenday_cliente_id($corpo['funcionario'] ?? null);
    if ($id === null || $dia === null || $hora === null) {
        agenday_portal_erro('Escolha o agendamento, a nova data e o novo horário.', 422);
    }

    $linha = agenday_cliente_buscar($conexao, $empresa, $idCliente, $id);
    if ($linha === null) {
        agenday_portal_erro('Agendamento não encontrado.', 404);
    }
    if ($linha['status'] !== 'agendado' && $linha['status'] !== 'confirmado') {
        agenday_portal_erro('Este agendamento não pode mais ser remarcado.', 409);
    }
    $janela = agenday_portal_pode_cancelar((int) $estabelecimento['horas_cancelamento'], (string) $linha['data_inicio']);
    if (!$janela['pode']) {
        agenday_portal_json([
            'ok' => false,
            'fora_do_prazo' => true,
            'erro' => $janela['motivo'] . ' Fale com o estabelecimento.',
        ], 409);
    }

    $idServico = (int) $linha['id_servicos'];
    $funcionarioAtual = (int) $linha['id_funcionario'];
    if ($idFuncionario !== null) {
        $escolhido = agenday_disponibilidade_escolher($conexao, $empresa, $dia, $hora, $idServico, $idFuncionario);
    } else {
        // Mantém o mesmo profissional quando possível; senão, qualquer um livre.
        $escolhido = agenday_disponibilidade_escolher($conexao, $empresa, $dia, $hora, $idServico, $funcionarioAtual);
        if ($escolhido === null) {
            $escolhido = agenday_disponibilidade_escolher($conexao, $empresa, $dia, $hora, $idServico, null);
        }
    }
    if ($escolhido === null) {
        agenday_portal_json([
            'ok' => false,
            'conflito' => true,
            'erro' => 'Este horário não está mais livre. Escolha outro.',
        ], 409);
    }

    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dia . ' ' . $hora, agenday_portal_fuso());
    if ($inicio === false) {
        agenday_portal_erro('Data ou horário inválidos.', 422);
    }

    try {
        agenday_agenda_remarcar($conexao, $empresa, $id, $idCliente, $escolhido, $inicio);
    } catch (DomainException $e) {
        agenday_portal_json(['ok' => false, 'conflito' => true, 'erro' => $e->getMessage()], 409);
    } catch (Throwable $e) {
        error_log('AgenDay portal: falha ao remarcar (' . get_class($e) . ').');
        agenday_portal_erro('Não foi possível remarcar agora. Tente novamente.', 503);
    }

    $stmt = $conexao->prepare(
        'SELECT nome_funcionario FROM funcionarios
          WHERE id_estabelecimento = ? AND id_funcionario = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $empresa, $escolhido);
    $stmt->execute();
    $stmt->bind_result($nomeProfissional);
    $stmt->fetch();
    $stmt->close();

    $token = agenday_portal_garantir_token($conexao, $empresa, $id);
    $texto = agenday_whatsapp_texto('remarcacao', [
        'estabelecimento' => $nomeEstabelecimento,
        'nome' => (string) $sessao['nome_cliente'],
        'servico' => (string) $linha['nome_servico'],
        'profissional' => (string) $nomeProfissional,
        'data_inicio' => $inicio->format('Y-m-d H:i:s'),
        'link_cancelar' => agenday_portal_link_cancelamento($estabelecimento, $token),
    ]);
    $idMensagemRemarcacao = agenday_whatsapp_enfileirar($conexao, $empresa, $id, $telefone, 'remarcacao', $texto);
    try {
        agenday_whatsapp_enviar_agora($conexao, $idMensagemRemarcacao);
    } catch (Throwable $e) {
        error_log('AgenDay portal: fila do WhatsApp falhou (' . get_class($e) . ').');
    }

    // O lembrete acompanha o novo horário (o do horário antigo é descartado).
    try {
        agenday_whatsapp_agendar_lembrete($conexao, $empresa, $id);
    } catch (Throwable $e) {
        error_log('AgenDay portal: lembrete não agendado (' . get_class($e) . ').');
    }

    agenday_portal_json([
        'ok' => true,
        'mensagem' => 'Agendamento remarcado.',
        'agendamento' => [
            'id' => $id,
            'data' => $dia,
            'hora' => $hora,
            'servico' => (string) $linha['nome_servico'],
            'profissional' => (string) $nomeProfissional,
        ],
    ]);
}

agenday_portal_erro('Ação desconhecida.', 400);
