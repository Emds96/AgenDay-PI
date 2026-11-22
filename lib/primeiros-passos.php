<?php
declare(strict_types=1);

/**
 * PRIMEIROS-PASSOS-01
 *
 * Um estabelecimento novo só consegue marcar o primeiro horário depois de
 * quatro coisas, nesta ordem: serviço -> quem atende -> vínculo entre os dois
 * -> horário de funcionamento. Antes disso a agenda não libera nenhum horário
 * e a tela de agendamento vira um beco sem saída.
 *
 * Este arquivo concentra a regra (catálogo de sugestões e cálculo do
 * progresso) para que a tela e os testes usem a mesma fonte.
 */

/**
 * Tipos de negócio e os serviços mais comuns de cada um.
 *
 * O preço e a duração são apenas ponto de partida: o estabelecimento pode
 * editar depois em Serviços. Nada aqui é gravado sem o clique do responsável.
 *
 * @return array<string, array{rotulo: string, descricao: string, servicos: array<int, array{nome: string, minutos: int, preco: string}>}>
 */
function agenday_primeiros_passos_segmentos(): array
{
    return [
        'odontologia' => [
            'rotulo' => 'Clínica odontológica',
            'descricao' => 'Consultas, limpeza, restauração e estética dental.',
            'servicos' => [
                ['nome' => 'Avaliação', 'minutos' => 30, 'preco' => '0,00'],
                ['nome' => 'Limpeza (profilaxia)', 'minutos' => 45, 'preco' => '150,00'],
                ['nome' => 'Restauração', 'minutos' => 60, 'preco' => '220,00'],
                ['nome' => 'Clareamento', 'minutos' => 60, 'preco' => '600,00'],
                ['nome' => 'Extração', 'minutos' => 45, 'preco' => '250,00'],
            ],
        ],
        'salao' => [
            'rotulo' => 'Salão de beleza',
            'descricao' => 'Cabelo, unhas e sobrancelha.',
            'servicos' => [
                ['nome' => 'Corte feminino', 'minutos' => 45, 'preco' => '60,00'],
                ['nome' => 'Escova', 'minutos' => 40, 'preco' => '45,00'],
                ['nome' => 'Coloração', 'minutos' => 90, 'preco' => '180,00'],
                ['nome' => 'Manicure', 'minutos' => 40, 'preco' => '35,00'],
                ['nome' => 'Design de sobrancelha', 'minutos' => 30, 'preco' => '30,00'],
            ],
        ],
        'barbearia' => [
            'rotulo' => 'Barbearia',
            'descricao' => 'Corte, barba e acabamento.',
            'servicos' => [
                ['nome' => 'Corte masculino', 'minutos' => 30, 'preco' => '40,00'],
                ['nome' => 'Barba', 'minutos' => 30, 'preco' => '30,00'],
                ['nome' => 'Corte + barba', 'minutos' => 60, 'preco' => '65,00'],
                ['nome' => 'Pigmentação', 'minutos' => 30, 'preco' => '35,00'],
            ],
        ],
        'estetica' => [
            'rotulo' => 'Estética e depilação',
            'descricao' => 'Pele, depilação e massagens.',
            'servicos' => [
                ['nome' => 'Limpeza de pele', 'minutos' => 60, 'preco' => '120,00'],
                ['nome' => 'Depilação com cera', 'minutos' => 30, 'preco' => '50,00'],
                ['nome' => 'Design de sobrancelha', 'minutos' => 30, 'preco' => '30,00'],
                ['nome' => 'Massagem relaxante', 'minutos' => 60, 'preco' => '130,00'],
            ],
        ],
        'consultorio' => [
            'rotulo' => 'Consultório / saúde',
            'descricao' => 'Consultas, retornos e procedimentos.',
            'servicos' => [
                ['nome' => 'Consulta', 'minutos' => 30, 'preco' => '0,00'],
                ['nome' => 'Retorno', 'minutos' => 20, 'preco' => '0,00'],
                ['nome' => 'Avaliação', 'minutos' => 40, 'preco' => '150,00'],
                ['nome' => 'Procedimento', 'minutos' => 60, 'preco' => '200,00'],
            ],
        ],
        'outro' => [
            'rotulo' => 'Outro tipo de negócio',
            'descricao' => 'Sem sugestões prontas: cadastre os seus serviços.',
            'servicos' => [],
        ],
    ];
}

/** Confere se a chave existe no catálogo (o valor vem do navegador). */
function agenday_primeiros_passos_segmento_valido(?string $chave): bool
{
    return is_string($chave) && $chave !== '' && array_key_exists($chave, agenday_primeiros_passos_segmentos());
}

/**
 * Serviços sugeridos para o tipo de negócio.
 *
 * @return array<int, array{nome: string, minutos: int, preco: string}>
 */
function agenday_primeiros_passos_sugestoes(string $chave): array
{
    if (!agenday_primeiros_passos_segmento_valido($chave)) {
        return [];
    }

    return agenday_primeiros_passos_segmentos()[$chave]['servicos'];
}

/**
 * Sugestão pelo índice informado pelo navegador. Nunca confia no nome nem no
 * preço enviados: só o índice escolhe dentro do catálogo do próprio sistema.
 *
 * @return array{nome: string, minutos: int, preco: string}|null
 */
function agenday_primeiros_passos_sugestao(string $chave, $indice): ?array
{
    if (!is_string($indice) || !preg_match('/\A[0-9]{1,2}\z/D', $indice)) {
        return null;
    }

    $lista = agenday_primeiros_passos_sugestoes($chave);
    $posicao = (int) $indice;

    return $lista[$posicao] ?? null;
}

/**
 * Converte "150,00" / "150.00" / "0" para o DECIMAL(10,2) do banco.
 * Mesma regra usada em cadastro-servicos.php.
 */
function agenday_primeiros_passos_preco(?string $preco): ?string
{
    if (!is_string($preco)) {
        return null;
    }

    $normalizado = str_replace(',', '.', trim($preco));
    if (!preg_match('/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/D', $normalizado)) {
        return null;
    }

    $partes = explode('.', $normalizado, 2);

    return $partes[0] . '.' . str_pad($partes[1] ?? '', 2, '0');
}

/** Minutos inteiros -> TIME (HH:MM:SS), como a coluna duracao_servico espera. */
function agenday_primeiros_passos_duracao(int $minutos): ?string
{
    if ($minutos < 1 || $minutos > 1440) {
        return null;
    }

    return sprintf('%02d:%02d:00', intdiv($minutos, 60), $minutos % 60);
}

/**
 * Monta o checklist a partir dos números já apurados. Função pura: serve à
 * tela e aos testes sem tocar no banco.
 *
 * @param array{servicos: int, funcionarios: int, vinculos: int, dias_abertos: int} $dados
 * @return array{total: int, concluidos: int, percentual: int, completo: bool, itens: array<int, array{chave: string, titulo: string, ajuda: string, feito: bool, link: string, acao: string}>}
 */
function agenday_primeiros_passos_progresso(array $dados): array
{
    $servicos = max(0, (int) ($dados['servicos'] ?? 0));
    $funcionarios = max(0, (int) ($dados['funcionarios'] ?? 0));
    $vinculos = max(0, (int) ($dados['vinculos'] ?? 0));
    $diasAbertos = max(0, (int) ($dados['dias_abertos'] ?? 0));

    $itens = [
        [
            'chave' => 'servico',
            'titulo' => 'Cadastre seus serviços',
            'ajuda' => 'É o que o cliente escolhe na hora de agendar.',
            'feito' => $servicos > 0,
            'link' => 'cad.servicos.php',
            'acao' => 'Cadastrar serviço',
        ],
        [
            'chave' => 'equipe',
            'titulo' => 'Cadastre quem atende',
            'ajuda' => 'Se for você mesmo, use o atalho "Sou eu que atendo".',
            'feito' => $funcionarios > 0,
            'link' => 'cad-funcionario.php',
            'acao' => 'Cadastrar profissional',
        ],
        [
            'chave' => 'vinculo',
            'titulo' => 'Vincule serviço e profissional',
            'ajuda' => 'Sem esse vínculo a agenda não libera nenhum horário.',
            'feito' => $vinculos > 0,
            'link' => 'cad-funcionario.php',
            'acao' => 'Vincular',
        ],
        [
            'chave' => 'horario',
            'titulo' => 'Confira o horário de funcionamento',
            'ajuda' => 'Dias e horas em que o estabelecimento atende.',
            'feito' => $diasAbertos > 0,
            'link' => 'config.php?aba=funcionamento',
            'acao' => 'Revisar horário',
        ],
    ];

    $concluidos = 0;
    foreach ($itens as $item) {
        if ($item['feito']) {
            $concluidos++;
        }
    }

    $total = count($itens);

    return [
        'total' => $total,
        'concluidos' => $concluidos,
        'percentual' => (int) round($concluidos / $total * 100),
        'completo' => $concluidos === $total,
        'itens' => $itens,
    ];
}

/**
 * Conta o estado real do estabelecimento da sessão.
 *
 * @return array{servicos: int, funcionarios: int, vinculos: int, dias_abertos: int}
 */
function agenday_primeiros_passos_contar(mysqli $conexao, int $idEstabelecimento): array
{
    $contar = static function (mysqli $conexao, string $sql, int $id) : int {
        $stmt = $conexao->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();

        return (int) $total;
    };

    return [
        'servicos' => $contar($conexao, 'SELECT COUNT(*) FROM servicos WHERE id_estabelecimento = ?', $idEstabelecimento),
        'funcionarios' => $contar($conexao, 'SELECT COUNT(*) FROM funcionarios WHERE id_estabelecimento = ?', $idEstabelecimento),
        'vinculos' => $contar($conexao, 'SELECT COUNT(*) FROM funcionario_servico WHERE id_estabelecimento = ?', $idEstabelecimento),
        'dias_abertos' => $contar(
            $conexao,
            'SELECT COUNT(*) FROM horario_funcionamento WHERE id_estabelecimento = ? AND aberto = 1',
            $idEstabelecimento
        ),
    ];
}

/**
 * Atalho usado pela agenda: nunca deixa a página falhar por causa do checklist.
 *
 * @return array{total: int, concluidos: int, percentual: int, completo: bool, itens: array<int, array<string, mixed>>}|null
 */
function agenday_primeiros_passos_estado(mysqli $conexao, int $idEstabelecimento): ?array
{
    try {
        return agenday_primeiros_passos_progresso(
            agenday_primeiros_passos_contar($conexao, $idEstabelecimento)
        );
    } catch (Throwable $e) {
        error_log('AgenDay primeiros passos: ' . get_class($e));

        return null;
    }
}
