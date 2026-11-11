<?php
/**
 * Gravação segura de agendamentos no BD V2 (microtarefa 4.3).
 * Chamado somente após autenticação e validação CSRF no endpoint.
 */
declare(strict_types=1);
require_once __DIR__ . '/agendamentos.php';

/** O projeto usa Manaus até existir fuso individual no cadastro do estabelecimento. */
function agenday_agenda_fuso(): DateTimeZone
{
    return new DateTimeZone(defined('AGENDAY_TIMEZONE') ? AGENDAY_TIMEZONE : 'America/Manaus');
}

function agenday_agenda_id_post(array $post, string $chave): int
{
    $valor = $post[$chave] ?? null;
    if (!is_string($valor) || !preg_match('/\A[1-9][0-9]{0,9}\z/D', $valor)) {
        throw new InvalidArgumentException('Selecione cliente, serviço e funcionário válidos.');
    }
    $numero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($numero === false) {
        throw new InvalidArgumentException('Selecione cliente, serviço e funcionário válidos.');
    }
    return $numero;
}

/**
 * Validar no servidor: nunca confiar em duração, fim, status nem empresa enviados pelo navegador.
 * @return array{cliente:int,servico:int,funcionario:int,inicio:DateTimeImmutable,observacoes:?string}
 */
function agenday_agenda_validar_post(array $post): array
{
    $cliente = agenday_agenda_id_post($post, 'id_cliente');
    $servico = agenday_agenda_id_post($post, 'id_servicos');
    $funcionario = agenday_agenda_id_post($post, 'id_funcionario');

    $dia = $post['data'] ?? null;
    $hora = $post['hora'] ?? null;
    $observacoes = $post['observacoes'] ?? '';
    if (!is_string($dia) || !is_string($hora) || !is_string($observacoes)
        || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D', $dia)
        || !preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/D', $hora)) {
        throw new InvalidArgumentException('Informe uma data e um horário válidos.');
    }

    // A interface usa passos de 15 minutos, com o mesmo critério no servidor.
    if (((int) substr($hora, 3, 2)) % 15 !== 0) {
        throw new InvalidArgumentException('Selecione um horário em intervalos de 15 minutos.');
    }

    $fuso = agenday_agenda_fuso();
    $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $dia . ' ' . $hora, $fuso);
    if ($inicio === false || $inicio->format('Y-m-d H:i') !== $dia . ' ' . $hora) {
        throw new InvalidArgumentException('A data ou o horário informado não existe.');
    }

    $agora = new DateTimeImmutable('now', $fuso);
    if ($inicio <= $agora) {
        throw new InvalidArgumentException('Escolha uma data e um horário futuros.');
    }
    if ($inicio > $agora->modify('+366 days')) {
        throw new InvalidArgumentException('Agendamentos podem ser marcados até 366 dias adiante.');
    }

    if (strlen($observacoes) > 4000 || !preg_match('//u', $observacoes)) {
        throw new InvalidArgumentException('As observações precisam ter até 1.000 caracteres.');
    }
    $quantidade = preg_match_all('/./us', $observacoes);
    if ($quantidade === false || $quantidade > 1000) {
        throw new InvalidArgumentException('As observações precisam ter até 1.000 caracteres.');
    }
    $observacoes = trim($observacoes);

    return [
        'cliente' => $cliente,
        'servico' => $servico,
        'funcionario' => $funcionario,
        'inicio' => $inicio,
        'observacoes' => $observacoes === '' ? null : $observacoes,
    ];
}

/**
 * Insere um único agendamento com isolamento por estabelecimento e prevenção de colisões.
 *
 * O SELECT ... FOR UPDATE na linha de funcionário serializa marcações concorrentes
 * para a mesma pessoa. Todos os endpoints futuros de agendamento devem usar o mesmo lock.
 * @return int ID do novo agendamento
 */
function agenday_agenda_criar(mysqli $conexao, array $post): int
{
    $dados = agenday_agenda_validar_post($post);
    $empresa = agenday_agenda_estabelecimento();
    $cliente = $dados['cliente'];
    $funcionario = $dados['funcionario'];
    $servico = $dados['servico'];
    $inicio = $dados['inicio'];

    $conexao->begin_transaction();
    try {
        // Lock transacional determinístico por funcionário e estabelecimento.
        $stmt = $conexao->prepare(
            'SELECT id_funcionario FROM funcionarios
             WHERE id_estabelecimento = ? AND id_funcionario = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $empresa, $funcionario);
        $stmt->execute();
        $stmt->store_result();
        $existeFuncionario = $stmt->num_rows === 1;
        $stmt->close();
        if (!$existeFuncionario) {
            throw new DomainException('O funcionário selecionado não pertence a este estabelecimento.');
        }

        $stmt = $conexao->prepare(
            'SELECT id_cliente FROM clientes WHERE id_estabelecimento = ? AND id_cliente = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $empresa, $cliente);
        $stmt->execute();
        $stmt->store_result();
        $existeCliente = $stmt->num_rows === 1;
        $stmt->close();
        if (!$existeCliente) {
            throw new DomainException('O cliente selecionado não pertence a este estabelecimento.');
        }

        // Verifica a relação N:N; a chave estrangeira composta reforça esta validação.
        $stmt = $conexao->prepare(
            'SELECT s.duracao_servico FROM funcionario_servico fs
             INNER JOIN servicos s ON s.id_estabelecimento = fs.id_estabelecimento
                                  AND s.id_servicos = fs.id_servicos
             WHERE fs.id_estabelecimento = ? AND fs.id_funcionario = ?
               AND fs.id_servicos = ? LIMIT 1'
        );
        $stmt->bind_param('iii', $empresa, $funcionario, $servico);
        $stmt->execute();
        $stmt->bind_result($duracaoBanco);
        $existeVinculo = $stmt->fetch();
        $stmt->close();
        if (!$existeVinculo || !is_string($duracaoBanco)) {
            throw new DomainException('Este funcionário não está vinculado ao serviço selecionado.');
        }

        // TIME vem do banco, nunca do formulário.
        if (!preg_match('/\A(\d{1,3}):([0-5]\d):([0-5]\d)\z/D', $duracaoBanco, $grupos)) {
            throw new DomainException('A duração cadastrada para este serviço é inválida.');
        }
        $segundos = (int) $grupos[1] * 3600 + (int) $grupos[2] * 60 + (int) $grupos[3];
        if ($segundos < 60 || $segundos > 86400) {
            throw new DomainException('O serviço precisa ter duração entre 1 e 1440 minutos.');
        }
        $fim = $inicio->modify('+' . $segundos . ' seconds');
        $inicioBanco = $inicio->format('Y-m-d H:i:s');
        $fimBanco = $fim->format('Y-m-d H:i:s');

        // Mesmo usando lock por funcionário, a consulta bloqueante evita leitura defasada.
        // Intervalos semiabertos [início, fim): agendamentos consecutivos são válidos.
        $stmt = $conexao->prepare(
            "SELECT id_agendamento FROM agendamentos
             WHERE id_estabelecimento = ? AND id_funcionario = ?
               AND status IN ('agendado', 'confirmado')
               AND data_inicio < ? AND data_fim > ?
             LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('iiss', $empresa, $funcionario, $fimBanco, $inicioBanco);
        $stmt->execute();
        $stmt->store_result();
        $emConflito = $stmt->num_rows > 0;
        $stmt->close();
        if ($emConflito) {
            throw new DomainException('Este funcionário já possui um atendimento nesse horário.');
        }

        $stmt = $conexao->prepare(
            "INSERT INTO agendamentos
             (id_estabelecimento, id_cliente, id_funcionario, id_servicos,
              data_inicio, data_fim, status, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, 'agendado', ?)"
        );
        $observacoes = $dados['observacoes'];
        $stmt->bind_param(
            'iiiisss', $empresa, $cliente, $funcionario, $servico,
            $inicioBanco, $fimBanco, $observacoes
        );
        $stmt->execute();
        $idNovo = (int) $conexao->insert_id;
        $stmt->close();
        $conexao->commit();
        return $idNovo;
    } catch (Throwable $e) {
        try {
            $conexao->rollback();
        } catch (Throwable $falhaRollback) {
            error_log('AgenDay: falha no rollback do agendamento.');
        }
        throw $e;
    }
}
