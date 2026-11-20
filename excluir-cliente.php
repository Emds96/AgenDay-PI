<?php
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

function agenday_erro_exclusao_cliente(string $mensagem): void
{
    $_SESSION['erro_cliente'] = $mensagem;
    header('Location: cad-cliente.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_erro_exclusao_cliente('A sessão do formulário expirou. Atualize a página e tente novamente.');
}

$idCliente = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($idCliente)) {
    agenday_erro_exclusao_cliente('Cliente inválido.');
}

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    // O cliente precisa pertencer ao estabelecimento da sessão.
    $consulta = $conexao->prepare(
        'SELECT id_cliente FROM clientes WHERE id_cliente = ? AND id_estabelecimento = ?'
    );
    $consulta->bind_param('ii', $idCliente, $idEstabelecimento);
    $consulta->execute();
    $consulta->store_result();
    $existe = $consulta->num_rows === 1;
    $consulta->free_result();
    $consulta->close();

    if (!$existe) {
        agenday_erro_exclusao_cliente('Cliente não encontrado.');
    }

    // Histórico de atendimento não pode ser apagado junto com o cadastro.
    $consulta = $conexao->prepare(
        'SELECT 1 FROM agendamentos WHERE id_estabelecimento = ? AND id_cliente = ? LIMIT 1'
    );
    $consulta->bind_param('ii', $idEstabelecimento, $idCliente);
    $consulta->execute();
    $consulta->store_result();
    $temAgendamentos = $consulta->num_rows > 0;
    $consulta->free_result();
    $consulta->close();

    if ($temAgendamentos) {
        agenday_erro_exclusao_cliente(
            'Não é possível excluir: este cliente possui agendamentos registrados. '
            . 'Cancele ou conclua os atendimentos antes de excluir.'
        );
    }

    $exclusao = $conexao->prepare(
        'DELETE FROM clientes WHERE id_cliente = ? AND id_estabelecimento = ?'
    );
    $exclusao->bind_param('ii', $idCliente, $idEstabelecimento);
    $exclusao->execute();
    $excluido = $exclusao->affected_rows === 1;
    $exclusao->close();

    if (!$excluido) {
        agenday_erro_exclusao_cliente('Não foi possível excluir o cliente. Recarregue e tente novamente.');
    }

    $_SESSION['sucesso_cliente'] = 'Cliente excluído com sucesso!';
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1451) {
        agenday_erro_exclusao_cliente('Não é possível excluir: existem registros vinculados a este cliente.');
    }
    error_log('AgenDay: erro SQL ao excluir cliente: código ' . $e->getCode());
    agenday_erro_exclusao_cliente('Não foi possível excluir agora. Tente novamente.');
} catch (Throwable $e) {
    error_log('AgenDay: falha ao excluir cliente (' . get_class($e) . ').');
    agenday_erro_exclusao_cliente('Não foi possível excluir agora. Tente novamente.');
}

header('Location: cad-cliente.php', true, 303);
exit;
