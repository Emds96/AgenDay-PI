<?php
declare(strict_types=1);

/**
 * PRIMEIROS-PASSOS-01
 *
 * Verifica a regra da tela de primeiro uso sem banco e sem navegador:
 * catálogo por tipo de negócio, conversão de preço/duração e progresso.
 *
 * Execução: php tests/primeiros-passos.php
 */

require_once __DIR__ . '/../lib/primeiros-passos.php';

$aprovados = 0;

function conferir(bool $condicao, string $mensagem): void
{
    global $aprovados;
    if (!$condicao) {
        fwrite(STDERR, 'FAIL: ' . $mensagem . PHP_EOL);
        exit(1);
    }
    $aprovados++;
}

/* Catálogo ----------------------------------------------------------------- */

$segmentos = agenday_primeiros_passos_segmentos();
conferir(is_array($segmentos) && $segmentos !== [], 'catálogo de segmentos vazio');
conferir(
    array_keys($segmentos) === ['odontologia', 'salao', 'barbearia', 'estetica', 'consultorio', 'outro'],
    'ordem ou chaves dos segmentos mudaram'
);

foreach ($segmentos as $chave => $dados) {
    conferir(isset($dados['rotulo'], $dados['descricao'], $dados['servicos']), "segmento {$chave} incompleto");
    conferir(trim((string) $dados['rotulo']) !== '', "segmento {$chave} sem rótulo");
    conferir(trim((string) $dados['descricao']) !== '', "segmento {$chave} sem descrição");
    conferir(is_array($dados['servicos']), "serviços de {$chave} não é lista");

    foreach ($dados['servicos'] as $servico) {
        $nome = (string) ($servico['nome'] ?? '');
        conferir($nome !== '' && mb_strlen($nome) <= 120, "serviço inválido em {$chave}");
        conferir(
            agenday_primeiros_passos_preco((string) ($servico['preco'] ?? '')) !== null,
            "preço inválido no serviço {$nome}"
        );
        conferir(
            agenday_primeiros_passos_duracao((int) ($servico['minutos'] ?? 0)) !== null,
            "duração inválida no serviço {$nome}"
        );
    }
}

conferir(agenday_primeiros_passos_segmentos()['outro']['servicos'] === [], '"outro" não deveria ter sugestões');
conferir(
    (agenday_primeiros_passos_segmentos()['outro']['curto'] ?? '') === 'Outro',
    'rótulo curto da faixa do primeiro serviço mudou'
);

/* Segmento ---------------------------------------------------------------- */

foreach (['odontologia', 'salao', 'barbearia', 'estetica', 'consultorio', 'outro'] as $chave) {
    conferir(agenday_primeiros_passos_segmento_valido($chave), "segmento válido recusado: {$chave}");
}
foreach (['', 'inexistente', 'ODONTOLOGIA', ' odontologia', '<script>'] as $invalido) {
    conferir(!agenday_primeiros_passos_segmento_valido($invalido), "segmento inválido aceito: {$invalido}");
}
conferir(!agenday_primeiros_passos_segmento_valido(null), 'null aceito como segmento');

/* Sugestões --------------------------------------------------------------- */

conferir(count(agenday_primeiros_passos_sugestoes('odontologia')) === 5, 'odontologia deveria ter 5 sugestões');
conferir(count(agenday_primeiros_passos_sugestoes('barbearia')) === 4, 'barbearia deveria ter 4 sugestões');
conferir(agenday_primeiros_passos_sugestoes('outro') === [], '"outro" deveria retornar lista vazia');
conferir(agenday_primeiros_passos_sugestoes('nada') === [], 'segmento inválido deveria retornar lista vazia');

/* Índice da sugestão (valor vindo do navegador) ---------------------------- */

conferir(agenday_primeiros_passos_sugestao('odontologia', '0')['nome'] === 'Avaliação', 'índice 0 errado');
conferir(agenday_primeiros_passos_sugestao('odontologia', '4') !== null, 'último índice deveria existir');
foreach (['5', '-1', '1.5', 'abc', '', null, '0 OR 1=1', ['0']] as $ruim) {
    conferir(
        agenday_primeiros_passos_sugestao('odontologia', $ruim) === null,
        'índice inválido aceito: ' . var_export($ruim, true)
    );
}
conferir(agenday_primeiros_passos_sugestao('outro', '0') === null, '"outro" não deveria resolver índice');

/* Preço ------------------------------------------------------------------- */

$precos = [
    ['150,00', '150.00'],
    ['150.00', '150.00'],
    ['0', '0.00'],
    ['35', '35.00'],
    ['35,9', '35.90'],
    ['35.9', '35.90'],
    ['12345678', '12345678.00'],
];
foreach ($precos as [$entrada, $esperado]) {
    conferir(agenday_primeiros_passos_preco($entrada) === $esperado, "preço {$entrada} -> " . var_export(agenday_primeiros_passos_preco($entrada), true));
}
foreach (['', 'abc', '-5', '1.234', '1,234', '12,345', '1.2.3', '  '] as $invalido) {
    conferir(agenday_primeiros_passos_preco($invalido) === null, "preço inválido aceito: {$invalido}");
}
conferir(agenday_primeiros_passos_preco(null) === null, 'null aceito como preço');

/* Duração ----------------------------------------------------------------- */

$duracoes = [
    [45, '00:45:00'],
    [60, '01:00:00'],
    [90, '01:30:00'],
    [1440, '24:00:00'],
];
foreach ($duracoes as [$minutos, $esperado]) {
    conferir(agenday_primeiros_passos_duracao($minutos) === $esperado, "duração {$minutos} incorreta");
}
foreach ([0, -10, 1441] as $invalida) {
    conferir(agenday_primeiros_passos_duracao($invalida) === null, "duração inválida aceita: {$invalida}");
}

/* Tipo de negócio lembrado no navegador ----------------------------------- */

conferir(agenday_primeiros_passos_cookie_nome(35) === 'agenday_pp_seg_35', 'nome do cookie por estabelecimento');
conferir(agenday_primeiros_passos_cookie_nome(0) === 'agenday_pp_seg_0', 'id zero gera cookie previsível');
conferir(agenday_primeiros_passos_cookie_nome(-7) === 'agenday_pp_seg_0', 'id negativo não gera nome estranho');
conferir(agenday_primeiros_passos_cookie_duracao() === 31536000, 'cookie deveria durar um ano');

conferir(
    agenday_primeiros_passos_segmento_do_cookie(35, ['agenday_pp_seg_35' => 'barbearia']) === 'barbearia',
    'cookie válido deveria devolver o tipo de negócio'
);
conferir(
    agenday_primeiros_passos_segmento_do_cookie(35, ['agenday_pp_seg_35' => 'nao-existe']) === '',
    'chave fora do catálogo deveria ser ignorada'
);
conferir(
    agenday_primeiros_passos_segmento_do_cookie(35, ['agenday_pp_seg_35' => ['barbearia']]) === '',
    'valor que não é texto deveria ser ignorado'
);
conferir(
    agenday_primeiros_passos_segmento_do_cookie(35, []) === '',
    'sem cookie deveria devolver vazio'
);
conferir(
    agenday_primeiros_passos_segmento_do_cookie(35, ['agenday_pp_seg_36' => 'barbearia']) === '',
    'escolha de outro estabelecimento não pode vazar'
);
conferir(
    agenday_primeiros_passos_segmento_do_cookie(35, ['agenday_pp_seg_35' => '']) === '',
    'cookie apagado (vazio) deveria devolver vazio'
);

/* Nome do negócio digitado (tipo "outro") ---------------------------------- */

conferir(
    agenday_primeiros_passos_negocio_cookie_nome(35) === 'agenday_pp_neg_35',
    'cookie do nome do negócio é separado do cookie do tipo'
);
conferir(
    agenday_primeiros_passos_negocio_normalizar('  Pet   shop  ') === 'Pet shop',
    'nome do negócio deveria perder espaços repetidos'
);
conferir(
    agenday_primeiros_passos_negocio_normalizar('<b>Pet</b> shop') === 'Pet shop',
    'nome do negócio não pode carregar tags'
);
foreach (['', '   ', 'A', str_repeat('a', 61), ['Pet'], null, 42] as $invalido) {
    conferir(
        agenday_primeiros_passos_negocio_normalizar($invalido) === '',
        'nome do negócio inválido aceito: ' . json_encode($invalido)
    );
}
conferir(
    agenday_primeiros_passos_negocio_do_cookie(35, ['agenday_pp_neg_35' => 'Pet shop da Ana']) === 'Pet shop da Ana',
    'cookie codificado deveria devolver o nome com acento e espaço'
);
conferir(
    agenday_primeiros_passos_negocio_do_cookie(35, ['agenday_pp_neg_35' => '  ']) === '',
    'cookie só com espaços deveria devolver vazio'
);
conferir(
    agenday_primeiros_passos_negocio_do_cookie(35, ['agenday_pp_neg_36' => 'Pet']) === '',
    'nome de outro estabelecimento não pode vazar'
);
conferir(
    agenday_primeiros_passos_negocio_do_cookie(35, ['agenday_pp_neg_35' => ['Pet']]) === '',
    'nome que não é texto deveria ser ignorado'
);

/* Progresso --------------------------------------------------------------- */
$vazio = agenday_primeiros_passos_progresso([]);
conferir($vazio['total'] === 5, 'o checklist deveria ter 5 passos');
conferir($vazio['concluidos'] === 0 && $vazio['percentual'] === 0, 'estado vazio deveria ser 0%');
conferir($vazio['completo'] === false, 'estado vazio não pode estar completo');
conferir(
    array_column($vazio['itens'], 'chave') === ['segmento', 'servico', 'equipe', 'vinculo', 'horario'],
    'ordem dos passos mudou'
);
conferir(
    array_column($vazio['itens'], 'feito') === [false, false, false, false, false],
    'nenhum passo deveria estar feito no estado vazio'
);

$parcial = agenday_primeiros_passos_progresso([
    'segmento' => 'barbearia',
    'servicos' => 2,
    'funcionarios' => 1,
    'vinculos' => 0,
    'dias_abertos' => 5,
]);
conferir($parcial['concluidos'] === 4 && $parcial['percentual'] === 80, 'progresso parcial incorreto');
conferir($parcial['completo'] === false, 'faltando vínculo não pode estar completo');
conferir($parcial['itens'][3]['feito'] === false, 'vínculo deveria estar pendente');

$completo = agenday_primeiros_passos_progresso([
    'segmento' => 'barbearia',
    'servicos' => 1,
    'funcionarios' => 1,
    'vinculos' => 1,
    'dias_abertos' => 1,
]);
conferir($completo['concluidos'] === 5 && $completo['percentual'] === 100, 'progresso completo incorreto');
conferir($completo['completo'] === true, 'estado completo não reconhecido');

$negativo = agenday_primeiros_passos_progresso([
    'servicos' => -3,
    'funcionarios' => -1,
    'vinculos' => -1,
    'dias_abertos' => -9,
]);
conferir($negativo['concluidos'] === 0, 'valores negativos deveriam ser ignorados');

/* Tipo de negócio é o primeiro passo e só conta quando é uma chave válida. */
conferir(
    agenday_primeiros_passos_progresso(['segmento' => 'nao-existe'])['itens'][0]['feito'] === false,
    'tipo fora do catálogo não pode concluir o passo'
);
conferir(
    agenday_primeiros_passos_progresso(['segmento' => 'outro'])['itens'][0]['feito'] === true,
    '"outro" é um tipo válido e conclui o passo'
);

foreach ($completo['itens'] as $item) {
    conferir(trim((string) $item['titulo']) !== '', 'passo sem título');
    conferir(trim((string) $item['ajuda']) !== '', 'passo sem ajuda');
    if ($item['chave'] !== 'segmento') {
        conferir(trim((string) $item['link']) !== '', 'passo sem link');
    }
}

echo 'Primeiros passos: ' . $aprovados . ' testes aprovados.' . PHP_EOL;
