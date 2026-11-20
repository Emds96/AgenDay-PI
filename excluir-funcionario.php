<?php
require_once __DIR__ . '/verifica_login.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Método não permitido.');
}

function agenday_erro_exclusao_funcionario(string $mensagem): void
{
    $_SESSION['erro_funcionario'] = $mensagem;
    header('Location: cad-funcionario.php', true, 303);
    exit;
}

if (!agenday_valida_csrf()) {
    agenday_erro_exclusao_funcionario('A sessão do formulário expirou. Atualize a página e tente novamente.');
}

$idFuncionario = filter_input(INPUT_POST, 'id_funcionario', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($idFuncionario)) {
    agenday_erro_exclusao_funcionario('Funcionário inválido.');
}

try {
    require_once __DIR__ . '/conexao.php';
    $idEstabelecimento = (int) $_SESSION['id_estabelecimento'];

    $consulta = $conexao->prepare(
        'SELECT id_funcionario FROM funcionarios WHERE id_funcionario = ? AND id_estabelecimento = ?'
    );
    $consulta->bind_param('ii', $idFuncionario, $idEstabelecimento);
    $consulta->execute();
    $consulta->store_result();
    $existe = $consulta->num_rows === 1;
    $consulta->free_result();
    $consulta->close();

    if (!$existe) {
        agenday_erro_exclusao_funcionario('Funcionário não encontrado.');
    }

    $consulta = $conexao->prepare(
        'SELECT 1 FROM agendamentos WHERE id_estabelecimento = ? AND id_funcionario = ? LIMIT 1'
    );
    $consulta->bind_param('ii', $idEstabelecimento, $idFuncionario);
    $consulta->execute();
    $consulta->store_result();
    $temAgendamentos = $consulta->num_rows > 0;
    $consulta->free_result();
    $consulta->close();

    if ($temAgendamentos) {
        agenday_erro_exclusao_funcionario(
            'Não é possível excluir: este funcionário possui agendamentos registrados. '
            . 'Cancele ou conclua os atendimentos antes de excluir.'
        );
    }

    // Vínculos com serviços e bloqueios do funcionário saem junto com o cadastro.
    $conexao->begin_transaction();
    try {
        $limpeza = $conexao->prepare(
            'DELETE FROM funcionario_servico WHERE id_estabelecimento = ? AND id_funcionario = ?'
        );
        $limpeza->bind_param('ii', $idEstabelecimento, $idFuncionario);
        $limpeza->execute();
        $limpeza->close();

        $exclusao = $conexao->prepare(
            'DELETE FROM funcionarios WHERE id_funcionario = ? AND id_estabelecimento = ?'
        );
        $exclusao->bind_param('ii', $idFuncionario, $idEstabelecimento);
        $exclusao->execute();
        $excluido = $exclusao->affected_rows === 1;
        $exclusao->close();

        if (!$excluido) {
            throw new RuntimeException('Funcionário não removido.');
        }
        $conexao->commit();
    } catch (Throwable $e) {
        $conexao->rollback();
        throw $e;
    }

    $_SESSION['sucesso_funcionario'] = 'Funcionário excluído com sucesso!';
} catch (mysqli_sql_exception $e) {
    if ($e->getCode() === 1451) {
        agenday_erro_exclusao_funcionario('Não é possível excluir: existem registros vinculados a este funcionário.');
    }
    error_log('AgenDay: erro SQL ao excluir funcionário: código ' . $e->getCode());
    agenday_erro_exclusao_funcionario('Não foi possível excluir agora. Tente novamente.');
} catch (Throwable $e) {
    error_log('AgenDay: falha ao excluir funcionário (' . get_class($e) . ').');
    agenday_erro_exclusao_funcionario('Não foi possível excluir agora. Tente novamente.');
}

header('Location: cad-funcionario.php', true, 303);
exit;
