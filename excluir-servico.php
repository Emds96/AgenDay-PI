<?php
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

function agenday_erro_exclusao_servico(string $mensagem): void
{
    $_SESSION['erro_servico'] = $mensagem;
    header('Location: cad.servicos.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_erro_exclusao_servico('A sessão do formulário expirou. Atualize a página e tente novamente.');
}

$idServico = filter_input(INPUT_POST, 'id_servicos', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($idServico)) {
    agenday_erro_exclusao_servico('Serviço inválido.');
}

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    $consulta = $conexao->prepare(
        'SELECT id_servicos FROM servicos WHERE id_servicos = ? AND id_estabelecimento = ?'
    );
    $consulta->bind_param('ii', $idServico, $idEstabelecimento);
    $consulta->execute();
    $consulta->store_result();
    $existe = $consulta->num_rows === 1;
    $consulta->free_result();
    $consulta->close();

    if (!$existe) {
        agenday_erro_exclusao_servico('Serviço não encontrado.');
    }

    $consulta = $conexao->prepare(
        'SELECT 1 FROM agendamentos WHERE id_estabelecimento = ? AND id_servicos = ? LIMIT 1'
    );
    $consulta->bind_param('ii', $idEstabelecimento, $idServico);
    $consulta->execute();
    $consulta->store_result();
    $temAgendamentos = $consulta->num_rows > 0;
    $consulta->free_result();
    $consulta->close();

    if ($temAgendamentos) {
        agenday_erro_exclusao_servico(
            'Não é possível excluir: este serviço possui agendamentos registrados. '
            . 'Cancele ou conclua os atendimentos antes de excluir.'
        );
    }

    // O vínculo do serviço com funcionários sai junto; histórico permanece.
    $conexao->begin_transaction();
    try {
        $limpeza = $conexao->prepare(
            'DELETE FROM funcionario_servico WHERE id_estabelecimento = ? AND id_servicos = ?'
        );
        $limpeza->bind_param('ii', $idEstabelecimento, $idServico);
        $limpeza->execute();
        $limpeza->close();

        $exclusao = $conexao->prepare(
            'DELETE FROM servicos WHERE id_servicos = ? AND id_estabelecimento = ?'
        );
        $exclusao->bind_param('ii', $idServico, $idEstabelecimento);
        $exclusao->execute();
        $excluido = $exclusao->affected_rows === 1;
        $exclusao->close();

        if (!$excluido) {
            throw new RuntimeException('Serviço não removido.');
        }
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }

    $_SESSION['sucesso_servico'] = 'Serviço excluído com sucesso!';
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1451) {
        agenday_erro_exclusao_servico('Não é possível excluir: existem registros vinculados a este serviço.');
    }
    error_log('AgenDay: erro SQL ao excluir serviço: código ' . $e->getCode());
    agenday_erro_exclusao_servico('Não foi possível excluir agora. Tente novamente.');
} catch (Throwable $e) {
    error_log('AgenDay: falha ao excluir serviço (' . get_class($e) . ').');
    agenday_erro_exclusao_servico('Não foi possível excluir agora. Tente novamente.');
}

header('Location: cad.servicos.php', true, 303);
exit;
