<?php
declare(strict_types=1);

/**
 * Bloqueios de agenda: feriado, folga, curso ou "fecha mais cedo".
 * O estabelecimento sempre vem da sessão; um bloqueio sem funcionário vale
 * para a conta inteira.
 */

/** Converte o valor de datetime-local para DATETIME, sem aceitar datas ambíguas. */
function agenday_bloqueio_datetime($valor): ?string
{
    if (!is_string($valor)) {
        return null;
    }

    $partes = [];
    if (!preg_match(
        '/\A(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?\z/D',
        trim($valor),
        $partes
    )) {
        return null;
    }

    $ano = (int) $partes[1];
    $mes = (int) $partes[2];
    $dia = (int) $partes[3];
    $hora = (int) $partes[4];
    $minuto = (int) $partes[5];
    $segundo = isset($partes[6]) ? (int) $partes[6] : 0;

    if (!checkdate($mes, $dia, $ano) || $hora > 23 || $minuto > 59 || $segundo > 59) {
        return null;
    }

    return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $ano, $mes, $dia, $hora, $minuto, $segundo);
}

/**
 * @return array{0: array{inicio: ?string, fim: ?string, motivo: string, id_funcionario: ?int}, 1: string[]}
 */
function agenday_bloqueio_validar(array $post): array
{
    $inicio = agenday_bloqueio_datetime($post['inicio'] ?? null);
    $fim = agenday_bloqueio_datetime($post['fim'] ?? null);
    $motivo = isset($post['motivo']) && is_string($post['motivo']) ? trim($post['motivo']) : '';
    $idFuncionario = null;
    $erros = [];

    $funcionarioBruto = $post['id_funcionario'] ?? '';
    if ($funcionarioBruto !== '' && $funcionarioBruto !== null) {
        $idFuncionario = filter_var($funcionarioBruto, FILTER_VALIDATE_INT);
        if ($idFuncionario === false || $idFuncionario < 1) {
            $erros[] = 'Escolha um funcionário válido.';
            $idFuncionario = null;
        }
    }

    if ($inicio === null || $fim === null) {
        $erros[] = 'Informe o início e o fim do bloqueio.';
    } elseif ($inicio >= $fim) {
        $erros[] = 'O fim do bloqueio precisa ser depois do início.';
    }

    if ($motivo === '') {
        $erros[] = 'Informe o motivo do bloqueio.';
    } elseif (mb_strlen($motivo) > 120) {
        $erros[] = 'O motivo deve ter no máximo 120 caracteres.';
    }

    return [
        [
            'inicio' => $inicio,
            'fim' => $fim,
            'motivo' => $motivo,
            'id_funcionario' => $idFuncionario,
        ],
        array_values(array_unique($erros)),
    ];
}

function agenday_bloqueio_funcionarios(mysqli $conexao, int $idEstabelecimento): array
{
    $consulta = $conexao->prepare(
        'SELECT id_funcionario, nome_funcionario
         FROM funcionarios WHERE id_estabelecimento = ? ORDER BY nome_funcionario'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $funcionarios = $resultado->fetch_all(MYSQLI_ASSOC);
    $consulta->close();
    return $funcionarios;
}

function agenday_bloqueio_listar(mysqli $conexao, int $idEstabelecimento): array
{
    $consulta = $conexao->prepare(
        'SELECT b.id_bloqueio, b.id_funcionario, b.inicio, b.fim, b.motivo,
                f.nome_funcionario
         FROM bloqueio b
         LEFT JOIN funcionarios f
           ON f.id_estabelecimento = b.id_estabelecimento
          AND f.id_funcionario = b.id_funcionario
         WHERE b.id_estabelecimento = ?
         ORDER BY b.inicio, b.id_bloqueio'
    );
    $consulta->bind_param('i', $idEstabelecimento);
    $consulta->execute();
    $resultado = $consulta->get_result();
    $bloqueios = $resultado->fetch_all(MYSQLI_ASSOC);
    $consulta->close();
    return $bloqueios;
}

function agenday_bloqueio_criar(mysqli $conexao, int $idEstabelecimento, array $dados): void
{
    $idFuncionario = $dados['id_funcionario'];
    if ($idFuncionario !== null) {
        $consulta = $conexao->prepare(
            'SELECT 1 FROM funcionarios
             WHERE id_estabelecimento = ? AND id_funcionario = ? LIMIT 1'
        );
        $consulta->bind_param('ii', $idEstabelecimento, $idFuncionario);
        $consulta->execute();
        $consulta->store_result();
        $existe = $consulta->num_rows === 1;
        $consulta->close();
        if (!$existe) {
            throw new DomainException('Funcionário não encontrado neste estabelecimento.');
        }
    }

    $insercao = $conexao->prepare(
        'INSERT INTO bloqueio (id_estabelecimento, id_funcionario, inicio, fim, motivo)
         VALUES (?, ?, ?, ?, ?)'
    );
    $insercao->bind_param(
        'iisss',
        $idEstabelecimento,
        $idFuncionario,
        $dados['inicio'],
        $dados['fim'],
        $dados['motivo']
    );
    $insercao->execute();
    $insercao->close();
}

function agenday_bloqueio_excluir(mysqli $conexao, int $idEstabelecimento, int $idBloqueio): bool
{
    $exclusao = $conexao->prepare(
        'DELETE FROM bloqueio WHERE id_estabelecimento = ? AND id_bloqueio = ?'
    );
    $exclusao->bind_param('ii', $idEstabelecimento, $idBloqueio);
    $exclusao->execute();
    $excluido = $exclusao->affected_rows === 1;
    $exclusao->close();
    return $excluido;
}
