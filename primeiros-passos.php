<?php
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/lib/primeiros-passos.php';
require_once __DIR__ . '/lib/portal-cliente.php';
require_once __DIR__ . '/lib/configuracao-estabelecimento.php';
require_once __DIR__ . '/lib/horario-funcionamento.php';

/**
 * PRIMEIROS-PASSOS-01
 *
 * Tela única do primeiro uso: mostra o que falta para a agenda funcionar,
 * sugere serviços pelo tipo de negócio e resolve o caso mais comum ("sou eu
 * que atendo") em um clique.
 */

$idEstabelecimento = (int) $_SESSION['id_estabelecimento'];
$chaveEtapa = 'pp_etapa_' . $idEstabelecimento;

$conexaoOk = false;
try {
    require_once __DIR__ . '/conexao.php';
    $conexaoOk = isset($conexao) && $conexao instanceof mysqli;
} catch (Throwable $e) {
    error_log('AgenDay primeiros passos: banco indisponível (' . get_class($e) . ').');
    $conexaoOk = false;
}

$segmento = isset($_SESSION['agenday_primeiros_passos_segmento'])
    && is_string($_SESSION['agenday_primeiros_passos_segmento'])
    ? $_SESSION['agenday_primeiros_passos_segmento']
    : '';
if (!agenday_primeiros_passos_segmento_valido($segmento)) {
    // Sem sessão (navegador reaberto), o cookie do navegador lembra a escolha.
    $segmento = agenday_primeiros_passos_segmento_do_cookie($idEstabelecimento);
}
if (!agenday_primeiros_passos_segmento_valido($segmento) && $conexaoOk) {
    // Outro navegador ou aba anônima: a conta também guarda o tipo escolhido.
    $segmento = agenday_primeiros_passos_segmento_da_conta($conexao, $idEstabelecimento);
}

/* -------------------------------------------------------------------------
 * Dados do estabelecimento (usados pelas ações e pela tela)
 * ------------------------------------------------------------------------- */
$nomeEstabelecimento = (string) ($_SESSION['nome_estabelecimento'] ?? 'seu estabelecimento');
$contatoEstabelecimento = '';
$portalDisponivel = false;
$portalSlug = '';
$portalDominio = '';

if ($conexaoOk) {
    try {
        $stmt = $conexao->prepare(
            'SELECT nome_estabelecimento, contato_estabelecimento, slug, dominio
               FROM estabelecimento WHERE id_estabelecimento = ? LIMIT 1'
        );
        $stmt->bind_param('i', $idEstabelecimento);
        $stmt->execute();
        $linha = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($linha !== null) {
            $nomeEstabelecimento = (string) ($linha['nome_estabelecimento'] ?? $nomeEstabelecimento);
            $contatoEstabelecimento = (string) ($linha['contato_estabelecimento'] ?? '');
            $portalSlug = (string) ($linha['slug'] ?? '');
            $portalDominio = (string) ($linha['dominio'] ?? '');
            $portalDisponivel = true;
        }
    } catch (Throwable $e) {
        error_log('AgenDay primeiros passos: dados do estabelecimento indisponíveis (' . get_class($e) . ').');
    }
}

/* -------------------------------------------------------------------------
 * Ações do primeiro uso (POST + redirecionamento, sem reenvio ao atualizar)
 * ------------------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $acao = isset($_POST['acao']) && is_string($_POST['acao']) ? $_POST['acao'] : '';

    if (!$conexaoOk) {
        $_SESSION['pp_erro'] = 'O banco de dados não respondeu. Tente novamente em instantes.';
    } elseif (!agenday_valida_csrf()) {
        $_SESSION['pp_erro'] = 'A sessão do formulário expirou. Atualize a página e tente novamente.';
    } elseif ($acao === 'trocar') {
        unset($_SESSION['agenday_primeiros_passos_segmento']);
        agenday_primeiros_passos_segmento_gravar($conexao, $idEstabelecimento, null);
        setcookie(agenday_primeiros_passos_cookie_nome($idEstabelecimento), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie(agenday_primeiros_passos_negocio_cookie_nome($idEstabelecimento), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_SESSION['pp_aviso'] = 'Escolha outro tipo de negócio.';
    } elseif ($acao === 'segmento') {
        $escolhido = isset($_POST['segmento']) && is_string($_POST['segmento']) ? $_POST['segmento'] : '';
        // Só o "outro" pede o nome: os demais tipos já vêm com sugestões.
        $negocioDigitado = $escolhido === 'outro'
            ? agenday_primeiros_passos_negocio_normalizar($_POST['negocio'] ?? null)
            : '';

        if (!agenday_primeiros_passos_segmento_valido($escolhido)) {
            $_SESSION['pp_erro'] = 'Escolha um tipo de negócio da lista.';
        } elseif ($escolhido === 'outro' && $negocioDigitado === '') {
            $_SESSION['pp_erro'] = 'Digite o nome do seu negócio para continuar (2 a 60 letras).';
            $_SESSION['pp_reabrir_negocio'] = true;
            // Devolve o que a pessoa digitou para ela corrigir, não recomeçar.
            $_SESSION['pp_negocio_tentativa'] = is_string($_POST['negocio'] ?? null)
                ? mb_substr(trim($_POST['negocio']), 0, 60)
                : '';
        } else {
            $_SESSION['agenday_primeiros_passos_segmento'] = $escolhido;
            agenday_primeiros_passos_segmento_gravar($conexao, $idEstabelecimento, $escolhido);
            $_SESSION[$chaveEtapa] = 2;
            // Guarda também no navegador: a sessão expira, a escolha não precisa.
            setcookie(agenday_primeiros_passos_cookie_nome($idEstabelecimento), $escolhido, [
                'expires' => time() + agenday_primeiros_passos_cookie_duracao(),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            // O nome digitado vale só para o "outro"; nos outros tipos é apagado
            // para não reaparecer velho numa próxima escolha.
            setcookie(
                agenday_primeiros_passos_negocio_cookie_nome($idEstabelecimento),
                $negocioDigitado,
                [
                    'expires' => $negocioDigitado === ''
                        ? time() - 3600
                        : time() + agenday_primeiros_passos_cookie_duracao(),
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
            $_SESSION['pp_abrir_sugestoes'] = agenday_primeiros_passos_sugestoes($escolhido) !== [];
            $_SESSION['pp_aviso'] = $escolhido === 'outro'
                ? 'Tudo certo, ' . $negocioDigitado . '. Agora cadastre o seu primeiro serviço.'
                : 'Sugestões atualizadas para o seu tipo de negócio.';
        }
    } elseif ($acao === 'servico') {
        $_SESSION['pp_abrir_sugestoes'] = true;
        $sugestao = agenday_primeiros_passos_sugestao($segmento, $_POST['sugestao'] ?? null);
        $duracao = $sugestao === null ? null : agenday_primeiros_passos_duracao((int) $sugestao['minutos']);
        $preco = $sugestao === null ? null : agenday_primeiros_passos_preco($sugestao['preco']);

        if ($sugestao === null || $duracao === null || $preco === null) {
            $_SESSION['pp_erro'] = 'Escolha um dos serviços sugeridos na lista.';
        } else {
            try {
                $stmt = $conexao->prepare(
                    'INSERT INTO servicos
                        (id_estabelecimento, nome_servico, duracao_servico, preco_servico)
                     VALUES (?, ?, ?, ?)'
                );
                $nomeServico = $sugestao['nome'];
                $stmt->bind_param('isss', $idEstabelecimento, $nomeServico, $duracao, $preco);
                $stmt->execute();
                $stmt->close();
                $_SESSION['pp_aviso'] = 'Serviço "' . $nomeServico . '" adicionado. Você pode editar o preço em Serviços.';
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $_SESSION['pp_aviso'] = 'Esse serviço já está cadastrado.';
                } else {
                    error_log('AgenDay primeiros passos: erro ao criar serviço (' . $e->getCode() . ').');
                    $_SESSION['pp_erro'] = 'Não foi possível adicionar o serviço agora.';
                }
            }
        }
    } elseif ($acao === 'profissional') {
        $nomeProfissional = isset($_POST['nome']) && is_string($_POST['nome']) ? trim($_POST['nome']) : '';
        $contatoProfissional = isset($_POST['contato']) && is_string($_POST['contato']) ? trim($_POST['contato']) : '';
        $selecionados = $_POST['servicos'] ?? [];
        $idsServicos = [];
        if (is_array($selecionados)) {
            foreach ($selecionados as $valor) {
                $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($id === false) {
                    $idsServicos = [];
                    break;
                }
                $idsServicos[$id] = $id;
            }
        }
        // Os dois pop-ups ("Sou eu que atendo" e "Tenho equipe") usam o mesmo
        // cadastro. O campo "origem" só diz qual pop-up reabrir quando dá erro.
        $origemProfissional = isset($_POST['origem']) && is_string($_POST['origem'])
            ? $_POST['origem']
            : '';
        if (!in_array($origemProfissional, ['eu', 'equipe'], true)) {
            $origemProfissional = '';
        }
        $letras = $nomeProfissional !== '' && preg_match('//u', $nomeProfissional)
            ? preg_match_all('/./us', $nomeProfissional)
            : 0;

        if ($letras < 2 || $letras > 120) {
            $_SESSION['pp_erro'] = 'Informe o nome de quem atende (2 a 120 caracteres).';
            if ($origemProfissional !== '') {
                $_SESSION['pp_reabrir_profissional'] = $origemProfissional;
            }
        } elseif (!agenday_telefone_estabelecimento_valido($contatoProfissional, true)) {
            $_SESSION['pp_erro'] = 'Informe um telefone com DDD (11 dígitos).';
            if ($origemProfissional !== '') {
                $_SESSION['pp_reabrir_profissional'] = $origemProfissional;
            }
        } elseif (!$idsServicos || $origemProfissional === '') {
            $_SESSION['pp_erro'] = 'Escolha pelo menos um serviço para essa pessoa.';
        } else {
            $transacao = false;
            try {
                $conexao->begin_transaction();
                $transacao = true;

                $stmt = $conexao->prepare(
                    'INSERT INTO funcionarios (id_estabelecimento, nome_funcionario, contato_funcionario)
                     VALUES (?, ?, ?)'
                );
                $stmt->bind_param('iss', $idEstabelecimento, $nomeProfissional, $contatoProfissional);
                $stmt->execute();
                $idFuncionario = (int) $conexao->insert_id;
                $stmt->close();

                $stmt = $conexao->prepare(
                    'INSERT INTO funcionario_servico (id_estabelecimento, id_funcionario, id_servicos)
                     SELECT id_estabelecimento, ?, id_servicos FROM servicos
                      WHERE id_estabelecimento = ? AND id_servicos = ?'
                );
                $stmt->bind_param('iii', $idFuncionario, $idEstabelecimento, $idServico);
                foreach ($idsServicos as $idServico) {
                    $stmt->execute();
                    if ($stmt->affected_rows !== 1) {
                        throw new UnexpectedValueException('Serviço indisponível');
                    }
                }
                $stmt->close();

                $conexao->commit();
                $transacao = false;
                $_SESSION['pp_aviso'] = $nomeProfissional . ' já pode atender.';
            } catch (Throwable $e) {
                if ($transacao) {
                    try {
                        $conexao->rollback();
                    } catch (Throwable $erroRollback) {
                        error_log('AgenDay primeiros passos: falha ao reverter.');
                    }
                }
                error_log('AgenDay primeiros passos: erro ao criar profissional (' . get_class($e) . ').');
                $_SESSION['pp_erro'] = 'Não foi possível cadastrar quem atende agora.';
            }
        }
        if (isset($_SESSION['pp_erro']) && $origemProfissional !== '') {
            $_SESSION['pp_reabrir_profissional'] = $origemProfissional;
            $_SESSION['pp_pessoa_tentativa'] = [
                'nome' => mb_substr($nomeProfissional, 0, 120),
                'contato' => mb_substr($contatoProfissional, 0, 20),
                'servicos' => $idsServicos,
            ];
        }
    } elseif ($acao === 'vinculo') {
        // O pop-up mostra todos os profissionais e serviços de uma vez: aqui
        // cada profissional tem a sua lista refeita por inteiro.
        $marcados = isset($_POST['vinculos']) && is_array($_POST['vinculos']) ? $_POST['vinculos'] : [];
        $erroVinculo = null;
        $transacao = false;

        try {
            // Só valem profissionais e serviços deste estabelecimento.
            $funcionariosValidos = [];
            $stmt = $conexao->prepare('SELECT id_funcionario FROM funcionarios WHERE id_estabelecimento = ?');
            $stmt->bind_param('i', $idEstabelecimento);
            $stmt->execute();
            $resultado = $stmt->get_result();
            while ($linha = $resultado->fetch_assoc()) {
                $funcionariosValidos[(int) $linha['id_funcionario']] = true;
            }
            $stmt->close();

            $servicosValidos = [];
            $stmt = $conexao->prepare('SELECT id_servicos FROM servicos WHERE id_estabelecimento = ?');
            $stmt->bind_param('i', $idEstabelecimento);
            $stmt->execute();
            $resultado = $stmt->get_result();
            while ($linha = $resultado->fetch_assoc()) {
                $servicosValidos[(int) $linha['id_servicos']] = true;
            }
            $stmt->close();

            if ($funcionariosValidos === [] || $servicosValidos === []) {
                $erroVinculo = 'Cadastre um serviço e quem atende antes de vincular.';
            } else {
                $conexao->begin_transaction();
                $transacao = true;

                $apagar = $conexao->prepare(
                    'DELETE FROM funcionario_servico WHERE id_estabelecimento = ? AND id_funcionario = ?'
                );
                $inserir = $conexao->prepare(
                    'INSERT IGNORE INTO funcionario_servico (id_estabelecimento, id_funcionario, id_servicos)
                     VALUES (?, ?, ?)'
                );
                $totalVinculos = 0;

                foreach (array_keys($funcionariosValidos) as $idFuncionario) {
                    $apagar->bind_param('ii', $idEstabelecimento, $idFuncionario);
                    $apagar->execute();

                    $escolhidos = isset($marcados[$idFuncionario]) && is_array($marcados[$idFuncionario])
                        ? $marcados[$idFuncionario]
                        : [];
                    foreach ($escolhidos as $idServico) {
                        $idServico = (int) $idServico;
                        if (!isset($servicosValidos[$idServico])) {
                            continue;
                        }
                        $inserir->bind_param('iii', $idEstabelecimento, $idFuncionario, $idServico);
                        $inserir->execute();
                        $totalVinculos++;
                    }
                }

                $apagar->close();
                $inserir->close();
                $conexao->commit();
                $transacao = false;

                $_SESSION['pp_aviso'] = $totalVinculos === 1
                    ? '1 vínculo salvo.'
                    : $totalVinculos . ' vínculos salvos.';
                if ($totalVinculos > 0) {
                    $_SESSION[$chaveEtapa] = 5;
                }
            }
        } catch (Throwable $e) {
            if ($transacao) {
                try {
                    $conexao->rollback();
                } catch (Throwable $erroRollback) {
                    error_log('AgenDay primeiros passos: falha ao reverter vínculos.');
                }
            }
            error_log('AgenDay primeiros passos: erro ao salvar vínculos (' . get_class($e) . ').');
            $erroVinculo = 'Não foi possível salvar os vínculos agora.';
        }

        if ($erroVinculo !== null) {
            $_SESSION['pp_erro'] = $erroVinculo;
            $_SESSION['pp_reabrir_vinculo'] = true;
        }
    } elseif ($acao === 'horario') {
        // Reaproveita a validação e a gravação de Configurações. As regras
        // (intervalo entre horários, antecedência) são preservadas como estão.
        [$diasValidados, $errosHorario] = agenday_horario_validar($_POST);

        if ($errosHorario !== []) {
            $_SESSION['pp_erro'] = implode(' ', array_unique($errosHorario));
            $_SESSION['pp_reabrir_horario'] = true;
        } else {
            try {
                $regrasAtuais = agenday_regras_carregar($conexao, $idEstabelecimento);
                agenday_horario_salvar($conexao, $idEstabelecimento, $diasValidados, $regrasAtuais);
                $_SESSION['pp_aviso'] = 'Horário de funcionamento atualizado.';
                $_SESSION[$chaveEtapa] = 6;
            } catch (Throwable $e) {
                error_log('AgenDay primeiros passos: erro ao salvar horário (' . get_class($e) . ').');
                $_SESSION['pp_erro'] = 'Não foi possível salvar o horário agora.';
                $_SESSION['pp_reabrir_horario'] = true;
            }
        }
    } elseif ($acao === 'portal') {
        // Escolha do endereço público: é o nome que aparece no link do cliente.
        $endereco = isset($_POST['endereco']) && is_string($_POST['endereco']) ? trim($_POST['endereco']) : '';
        $slugNovo = $portalSlug;
        $erroSlug = null;

        $estadoFinal = agenday_primeiros_passos_estado($conexao, $idEstabelecimento, $segmento);
        if (empty($estadoFinal['completo'])) {
            $erroSlug = 'Confira as etapas anteriores antes de concluir.';
        } elseif (!$portalDisponivel) {
            $erroSlug = 'O endereço do portal não está disponível neste banco.';
        } elseif ($endereco === '') {
            try {
                $slugNovo = agenday_portal_slug_disponivel($conexao, $nomeEstabelecimento, $idEstabelecimento);
            } catch (Throwable $e) {
                error_log('AgenDay primeiros passos: falha ao gerar o endereço do portal.');
                $erroSlug = 'Não foi possível gerar o endereço agora. Tente novamente.';
            }
        } else {
            [$slugNovo, $erroSlug] = agenday_portal_slug_analisar($endereco);

            if ($erroSlug === null && $slugNovo !== $portalSlug) {
                try {
                    if (agenday_portal_slug_ocupado($conexao, $slugNovo, $idEstabelecimento)) {
                        $erroSlug = 'Esse endereço já está em uso. Tente outro nome.';
                    }
                } catch (Throwable $e) {
                    error_log('AgenDay primeiros passos: falha ao conferir o endereço do portal.');
                    $erroSlug = 'Não foi possível conferir o endereço agora. Tente novamente.';
                }
            }
        }

        if ($erroSlug !== null) {
            $_SESSION['pp_erro'] = $erroSlug;
            $_SESSION['pp_endereco'] = $endereco;
        } else {
            try {
                $stmt = $conexao->prepare('UPDATE estabelecimento SET slug = ? WHERE id_estabelecimento = ?');
                $stmt->bind_param('si', $slugNovo, $idEstabelecimento);
                $stmt->execute();
                $stmt->close();
                $_SESSION['pp_aviso'] = 'Endereço salvo. Seu link agora é ' . $slugNovo . '.';
                $_SESSION['pp_link_concluido'] = $idEstabelecimento;
                setcookie('agenday_pp_pronto_' . $idEstabelecimento, '1', [
                    'expires' => time() + agenday_primeiros_passos_cookie_duracao(),
                    'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
                ]);
                $_SESSION[$chaveEtapa] = 6;
            } catch (mysqli_sql_exception $e) {
                $_SESSION['pp_endereco'] = $endereco;
                if ($e->getCode() === 1062) {
                    $_SESSION['pp_erro'] = 'Esse endereço acabou de ser escolhido por outra pessoa. Tente outro nome.';
                } else {
                    error_log('AgenDay primeiros passos: erro ao salvar o endereço (' . $e->getCode() . ').');
                    $_SESSION['pp_erro'] = 'Não foi possível salvar o endereço agora.';
                }
            }
        }
    } else {
        $_SESSION['pp_erro'] = 'Ação não reconhecida.';
    }

    header('Location: primeiros-passos.php', true, 303);
    exit;
}

/* -------------------------------------------------------------------------
 * Dados da tela
 * ------------------------------------------------------------------------- */
$estado = null;
$servicosExistentes = [];
$listaServicos = [];
$listaFuncionarios = [];
$diasFuncionamento = agenday_horario_padrao();
$nomesDias = agenday_dias_semana();
$funcionamentoDisponivel = false;

if ($conexaoOk) {
    $estado = agenday_primeiros_passos_estado($conexao, $idEstabelecimento, $segmento);

    try {
        $stmt = $conexao->prepare('SELECT nome_servico FROM servicos WHERE id_estabelecimento = ?');
        $stmt->bind_param('i', $idEstabelecimento);
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($servico = $resultado->fetch_assoc()) {
            $servicosExistentes[mb_strtolower((string) $servico['nome_servico'])] = true;
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('AgenDay primeiros passos: serviços indisponíveis (' . get_class($e) . ').');
    }

    // Listas dos pop-ups: vínculo (profissional × serviço) e horário.
    try {
        $stmt = $conexao->prepare(
            'SELECT id_servicos, nome_servico FROM servicos
              WHERE id_estabelecimento = ? ORDER BY nome_servico'
        );
        $stmt->bind_param('i', $idEstabelecimento);
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($servico = $resultado->fetch_assoc()) {
            $listaServicos[(int) $servico['id_servicos']] = (string) $servico['nome_servico'];
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('AgenDay primeiros passos: lista de serviços indisponível (' . get_class($e) . ').');
    }

    try {
        $stmt = $conexao->prepare(
            'SELECT id_funcionario, nome_funcionario FROM funcionarios
              WHERE id_estabelecimento = ? ORDER BY nome_funcionario'
        );
        $stmt->bind_param('i', $idEstabelecimento);
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($funcionario = $resultado->fetch_assoc()) {
            $listaFuncionarios[(int) $funcionario['id_funcionario']] = [
                'nome' => (string) $funcionario['nome_funcionario'],
                'servicos' => [],
            ];
        }
        $stmt->close();

        $stmt = $conexao->prepare(
            'SELECT id_funcionario, id_servicos FROM funcionario_servico WHERE id_estabelecimento = ?'
        );
        $stmt->bind_param('i', $idEstabelecimento);
        $stmt->execute();
        $resultado = $stmt->get_result();
        while ($vinculo = $resultado->fetch_assoc()) {
            $idFuncionario = (int) $vinculo['id_funcionario'];
            if (isset($listaFuncionarios[$idFuncionario])) {
                $listaFuncionarios[$idFuncionario]['servicos'][(int) $vinculo['id_servicos']] = true;
            }
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log('AgenDay primeiros passos: lista de profissionais indisponível (' . get_class($e) . ').');
    }

    try {
        $diasFuncionamento = agenday_horario_carregar($conexao, $idEstabelecimento);
        $funcionamentoDisponivel = true;
    } catch (Throwable $e) {
        error_log('AgenDay primeiros passos: horário de funcionamento indisponível (' . get_class($e) . ').');
    }
}

$portalLink = $portalDisponivel && $portalSlug !== ''
    ? agenday_portal_base_url(['slug' => $portalSlug, 'dominio' => $portalDominio])
    : '';
$portalPrefixo = $portalDisponivel ? agenday_portal_url_base($portalDominio) : '';
$enderecoDigitado = isset($_SESSION['pp_endereco']) && is_string($_SESSION['pp_endereco'])
    ? $_SESSION['pp_endereco']
    : $portalSlug;

$progresso = $estado ?? agenday_primeiros_passos_progresso(['segmento' => $segmento]);
$limite = 6;
foreach ($progresso['itens'] as $indice => $passo) {
    if (!$passo['feito']) {
        $limite = $indice + 1;
        break;
    }
}
$solicitada = filter_input(INPUT_GET, 'etapa', FILTER_VALIDATE_INT);
$etapa = max(1, min($limite, $solicitada ?: ($_SESSION[$chaveEtapa] ?? $limite)));
$_SESSION[$chaveEtapa] = $etapa;
$perguntas = [
    1 => 'Qual é o seu tipo de negócio?',
    2 => 'Quais serviços você oferece?',
    3 => 'Quem vai atender seus clientes?',
    4 => 'Quem atende cada serviço?',
    5 => 'Em quais horários você atende?',
    6 => 'Qual link você quer enviar aos clientes?',
];
// Conta já configurada: a tela deixa de falar em "etapa" e de prometer 5 minutos.
$tudoPronto = !empty($progresso['completo']);
$perguntaAtual = $perguntas[$etapa];
$ajudaEtapa = $etapa < 6 ? (string) $progresso['itens'][$etapa - 1]['ajuda'] : '';
if ($etapa === 2 && $listaServicos !== []) {
    // Quem já tem serviços revisa, não "escolhe do zero".
    $perguntaAtual = 'Seus serviços';
    $ajudaEtapa = 'Estes são os serviços que o cliente escolhe no portal. Para incluir outro, use o botão abaixo.';
}
$celebrar = $etapa === 6 && $portalLink !== '' && ($_SESSION['pp_link_concluido'] ?? null) === $idEstabelecimento;
unset($_SESSION['pp_link_concluido']);
$sugestoes = agenday_primeiros_passos_sugestoes($segmento);
$segmentos = agenday_primeiros_passos_segmentos();
// Rótulo curto para o passo de serviço ("Outro", em vez de "Outro tipo de
// negócio" repetindo a palavra "tipo de negócio").
$segmentoCurto = $segmento !== '' && isset($segmentos[$segmento])
    ? (string) ($segmentos[$segmento]['curto'] ?? $segmentos[$segmento]['rotulo'])
    : '';
// Para o tipo "outro", o nome que a pessoa digitou vale mais que o rótulo.
$negocioDigitado = $segmento === 'outro'
    ? agenday_primeiros_passos_negocio_do_cookie($idEstabelecimento)
    : '';
if ($negocioDigitado !== '') {
    $segmentoCurto = $negocioDigitado;
}
// Texto que voltou com erro no pop-up do nome do negócio (para corrigir, não recomeçar).
$negocioTentativa = isset($_SESSION['pp_negocio_tentativa']) && is_string($_SESSION['pp_negocio_tentativa'])
    ? $_SESSION['pp_negocio_tentativa']
    : '';
$aviso = $_SESSION['pp_aviso'] ?? null;
$erro = $_SESSION['pp_erro'] ?? null;

// Qual pop-up precisa reabrir depois de um erro (um só por vez).
$reabrirServico = !empty($_SESSION['pp_reabrir_servico']);
$abrirSugestoes = !empty($_SESSION['pp_abrir_sugestoes']);
$reabrirVinculo = !empty($_SESSION['pp_reabrir_vinculo']);
$reabrirHorario = !empty($_SESSION['pp_reabrir_horario']);
$reabrirNegocio = !empty($_SESSION['pp_reabrir_negocio']);
$reabrirProfissional = isset($_SESSION['pp_reabrir_profissional'])
    && is_string($_SESSION['pp_reabrir_profissional'])
    && in_array($_SESSION['pp_reabrir_profissional'], ['eu', 'equipe'], true)
    ? $_SESSION['pp_reabrir_profissional']
    : '';

unset(
    $_SESSION['pp_abrir_sugestoes'],
    $_SESSION['pp_aviso'],
    $_SESSION['pp_erro'],
    $_SESSION['pp_endereco'],
    $_SESSION['pp_reabrir_servico'],
    $_SESSION['pp_reabrir_vinculo'],
    $_SESSION['pp_reabrir_horario'],
    $_SESSION['pp_reabrir_negocio'],
    $_SESSION['pp_negocio_tentativa'],
    $_SESSION['pp_reabrir_profissional']
);
$csrfToken = agenday_token_csrf();
$tentativaPessoa = $_SESSION['pp_pessoa_tentativa'] ?? [];
unset($_SESSION['pp_pessoa_tentativa']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Primeiros passos | AgenDay</title>
    <link rel="icon" href="img/icon-agenda.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi"
          crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.9.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/agenda.css">
    <link rel="stylesheet" href="css/body.css">
    <link rel="stylesheet" href="css/design-system.css">
    <link rel="stylesheet" href="css/responsivo.css?v=20261120-del01">
    <link rel="stylesheet" href="css/configuracoes-v2.css?v=20261120-ui16">
    <link rel="stylesheet" href="css/primeiros-passos.css?v=20261122-confetes2">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-OERcA2EqjJCMA+/3y+gxIOqMEjwtxJY7qPCqsdltbNJuaOe923+mo//f6V8Qbsw3"
            crossorigin="anonymous"></script>
</head>

<body class="agenday-app">
<header class="navbar navbar-light bg-light">
    <div class="container-fluid">
        <button class="navbar-toggler bg-light" type="button" data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar"
                aria-label="Abrir menu principal">
            <span class="navbar-toggler-icon"></span>
        </button>
        <h3 class="ad-header-brand fw-bold text-center mb-0">AgenDay</h3>
        <?php require __DIR__ . '/lib/menu-lateral.php'; ?>
    </div>
</header>

<main class="container ag-settings">
    <div class="ag-settings-heading">
        <h1 class="fw-bold">Primeiros passos</h1>
        <p><?= $tudoPronto
            ? 'Sua agenda está pronta. Revise o que quiser: nada aqui apaga o que já foi salvo.'
            : ($segmento === ''
                ? 'Escolha o seu tipo de negócio: o AgenDay já sugere os serviços. Leva uns 5 minutos.'
                : 'Escolha os serviços, diga quem atende e confira os horários. Leva uns 5 minutos.') ?></p>
    </div>

    <?php if ($aviso !== null): ?>
        <div class="alert alert-success" role="status" aria-live="polite">
            <?= agenday_config_html((string) $aviso) ?>
        </div>
    <?php endif; ?>
    <?php if ($erro !== null): ?>
        <div class="alert alert-danger" role="alert" aria-live="polite">
            <?= agenday_config_html((string) $erro) ?>
        </div>
    <?php endif; ?>
    <?php if (!$conexaoOk): ?>
        <div class="alert alert-warning" role="alert">
            Não foi possível carregar o seu progresso agora. Atualize a página em instantes.
        </div>
    <?php endif; ?>

    <?php if ($segmento !== '' && $sugestoes !== []): ?>
        <div class="modal fade" id="pp-modal-sugestoes" tabindex="-1" aria-hidden="true"
             aria-labelledby="pp-titulo-sugestoes" data-reabrir-erro="<?= $abrirSugestoes ? 'true' : 'false' ?>">
          <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="pp-titulo-sugestoes">Escolha os seus serviços</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <?php if ($abrirSugestoes && $erro !== null): ?>
                        <p class="alert alert-danger" role="alert"><?= agenday_config_html((string) $erro) ?></p>
                    <?php endif; ?>
                    <p>
                        Um clique cria o serviço com duração e preço de exemplo.
                        Ajuste os valores depois, na tela de Serviços.
                    </p>

                <form method="post" action="primeiros-passos.php" class="pp-sugestoes">
                    <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                    <input type="hidden" name="acao" value="servico">
                    <?php foreach ($sugestoes as $indice => $sugestao): ?>
                        <?php $jaExiste = isset($servicosExistentes[mb_strtolower($sugestao['nome'])]); ?>
                        <button type="submit" class="pp-sugestao" name="sugestao" value="<?= (int) $indice ?>"
                            <?= $jaExiste ? 'disabled' : '' ?>>
                            <span class="pp-sugestao-nome"><?= agenday_config_html($sugestao['nome']) ?></span>
                            <span class="pp-sugestao-meta">
                                <?= (int) $sugestao['minutos'] ?> min · R$ <?= agenday_config_html($sugestao['preco']) ?>
                            </span>
                            <span class="pp-sugestao-aco">
                                <?= $jaExiste ? 'Já cadastrado' : '+ Adicionar' ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </form>
                <p class="pp-outro-servico">
                    Não está na lista?
                    <button type="button" class="pp-link-botao"
                            data-bs-toggle="modal" data-bs-target="#pp-modal-servico">
                        Cadastre o seu serviço
                    </button>.
                </p>
                </div>
                <div class="modal-footer">
                    <?php if ($listaServicos !== []): ?>
                        <a class="btn btn-primary" href="primeiros-passos.php?etapa=3">Continuar</a>
                    <?php else: ?>
                        <span class="form-text">Adicione pelo menos um serviço para continuar.</span>
                    <?php endif; ?>
                </div>
            </div>
          </div>
        </div>
    <?php endif; ?>

    <section class="ag-settings-panel pp-painel" aria-labelledby="pp-titulo-progresso">
        <div class="ag-settings-body">
            <div class="ag-settings-section-title">
                <p><?= $tudoPronto ? 'Tudo pronto' : 'Etapa ' . $etapa . ' de 6' ?></p>
                <h2 id="pp-titulo-progresso"><?= agenday_config_html($perguntaAtual) ?></h2>
            </div>

            <div class="pp-progresso">
                <div class="progress" role="progressbar"
                     aria-valuenow="<?= $tudoPronto ? 6 : $etapa ?>" aria-valuemin="1" aria-valuemax="6"
                     aria-label="Etapa atual">
                    <div class="progress-bar" style="width: <?= $tudoPronto ? 100 : round($etapa / 6 * 100) ?>%"></div>
                </div>
            </div>

            <?php if ($etapa < 6): ?>
                <?php $item = $progresso['itens'][$etapa - 1]; ?>
                    <div class="pp-etapa">
                        <div class="pp-passo-texto">
                            <p><?= agenday_config_html($ajudaEtapa) ?></p>
                            <?php if ($etapa === 2 && $listaServicos): ?>
                                <p class="mt-2">Serviços cadastrados: <?= agenday_config_html(implode(', ', $listaServicos)) ?>.</p>
                            <?php elseif ($etapa === 3 && $listaFuncionarios): ?>
                                <p class="mt-2">Quem atende: <?= agenday_config_html(implode(', ', array_column($listaFuncionarios, 'nome'))) ?>.</p>
                            <?php elseif ($etapa === 4 && $listaFuncionarios): ?>
                                <ul class="mt-2">
                                    <?php foreach ($listaFuncionarios as $pessoa): ?>
                                        <?php $nomes = array_intersect_key($listaServicos, $pessoa['servicos']); ?>
                                        <li><?= agenday_config_html($pessoa['nome']) ?>:
                                            <?= agenday_config_html($nomes ? implode(', ', $nomes) : 'sem serviço vinculado') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif ($etapa === 5 && $funcionamentoDisponivel): ?>
                                <ul class="mt-2">
                                    <?php foreach ($diasFuncionamento as $numero => $dia): ?>
                                        <?php if (empty($dia['aberto'])): ?>
                                            <?php continue; ?>
                                        <?php endif; ?>
                                        <?php
                                        // Com almoço, o dia tem duas janelas: "09:00 – 12:00 e 13:00 – 19:00".
                                        $janelasDia = array_map(
                                            static fn (array $janela): string => $janela['inicio'] . ' – ' . $janela['fim'],
                                            agenday_horario_janelas($dia)
                                        );
                                        ?>
                                        <li><?= agenday_config_html($nomesDias[$numero] . ': ' . implode(' e ', $janelasDia)) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ($item['chave'] === 'segmento' && $segmento !== ''): ?>
                                <div class="pp-trocar pp-tipo-passo">
                                    <span>Tipo de negócio: <strong><?= agenday_config_html($segmentoCurto) ?></strong></span>
                                    <form method="post" action="primeiros-passos.php" class="pp-trocar-form">
                                        <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                                        <input type="hidden" name="acao" value="trocar">
                                        <button type="submit" class="link-acao">Trocar</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($item['chave'] === 'segmento' && $segmento === ''): ?>
                            <form method="post" action="primeiros-passos.php" class="pp-segmentos">
                                <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                                <input type="hidden" name="acao" value="segmento">
                                <?php foreach ($segmentos as $chave => $dadosSegmento): ?>
                                    <?php if ($chave === 'outro'): ?>
                                        <?php
                                        // "Outro" não leva direto para as sugestões: abre o
                                        // pop-up que pergunta o nome do negócio.
                                        ?>
                                        <button type="button" class="pp-chip"
                                                data-bs-toggle="modal" data-bs-target="#pp-modal-negocio">
                                            <strong><?= agenday_config_html($dadosSegmento['rotulo']) ?></strong>
                                            <span><?= agenday_config_html($dadosSegmento['descricao']) ?></span>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="pp-chip" name="segmento" value="<?= agenday_config_html($chave) ?>">
                                            <strong><?= agenday_config_html($dadosSegmento['rotulo']) ?></strong>
                                            <span><?= agenday_config_html($dadosSegmento['descricao']) ?></span>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </form>
                        <?php endif; ?>
                        <?php if ($item['chave'] === 'segmento'): ?>
                            <?php /* As opções de tipo estão no próprio passo, acima. */ ?>
                        <?php elseif ($item['chave'] === 'equipe' && $conexaoOk): ?>
                            <?php if ($item['feito']): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm pp-passo-acao"
                                        data-bs-toggle="modal" data-bs-target="#pp-modal-equipe">
                                    Adicionar pessoa
                                </button>
                            <?php else: ?>
                                <div class="pp-passo-acoes">
                                    <button type="button" class="btn btn-primary btn-sm pp-passo-acao"
                                            data-bs-toggle="modal" data-bs-target="#pp-modal-eu">
                                        Sou eu que atendo
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm pp-passo-acao"
                                            data-bs-toggle="modal" data-bs-target="#pp-modal-equipe">
                                        Tenho equipe
                                    </button>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($item['chave'] === 'servico' && $conexaoOk): ?>
                            <button type="button"
                                    class="btn <?= $item['feito'] ? 'btn-outline-secondary' : 'btn-primary' ?> btn-sm pp-passo-acao"
                                    data-bs-toggle="modal" data-bs-target="<?= $sugestoes !== [] ? '#pp-modal-sugestoes' : '#pp-modal-servico' ?>">
                                <?= $item['feito'] ? 'Adicionar outro' : ($sugestoes !== [] ? 'Escolher serviços' : 'Cadastrar serviço') ?>
                            </button>
                        <?php elseif ($item['chave'] === 'vinculo' && $conexaoOk): ?>
                            <button type="button"
                                    class="btn <?= $item['feito'] ? 'btn-outline-secondary' : 'btn-primary' ?> btn-sm pp-passo-acao"
                                    data-bs-toggle="modal" data-bs-target="#pp-modal-vinculo">
                                <?= $item['feito'] ? 'Revisar vínculos' : 'Vincular' ?>
                            </button>
                        <?php elseif ($item['chave'] === 'horario' && $conexaoOk): ?>
                            <button type="button"
                                    class="btn <?= $item['feito'] ? 'btn-outline-secondary' : 'btn-primary' ?> btn-sm pp-passo-acao"
                                    data-bs-toggle="modal" data-bs-target="#pp-modal-horario">
                                <?= $item['feito'] ? 'Revisar horário' : 'Configurar horário' ?>
                            </button>
                        <?php else: ?>
                            <a class="btn <?= $item['feito'] ? 'btn-outline-secondary' : 'btn-primary' ?> btn-sm pp-passo-acao"
                               href="<?= agenday_config_html($item['link']) ?>">
                                <?= agenday_config_html($item['feito'] ? 'Revisar' : $item['acao']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php if ($item['feito']): ?>
                    <a class="btn btn-primary mt-3" href="primeiros-passos.php?etapa=<?= $etapa + 1 ?>">Continuar</a>
                <?php endif; ?>

            <noscript>
                <p class="pp-sem-js">
                    Sem JavaScript, use as páginas:
                    <a href="cad.servicos.php">Serviços</a>,
                    <a href="cad-funcionario.php">Funcionários</a> e
                    <a href="config.php?aba=funcionamento">Horários</a>.
                </p>
            </noscript>

            <?php else: ?>
            <div class="ag-settings-section-title">
                <p>Escolha o nome do seu link. É o endereço que você envia no WhatsApp, no Instagram ou coloca na bio.</p>
            </div>

            <?php if (!$portalDisponivel): ?>
                <p class="form-text mb-0">
                    O endereço público não está disponível neste banco. Use
                    <a href="config.php">Configurações</a> para conferir.
                </p>
            <?php else: ?>
                <form method="post" action="primeiros-passos.php" class="pp-link-form">
                    <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                    <input type="hidden" name="acao" value="portal">
                    <div class="pp-campo">
                        <label for="pp-endereco">Nome do seu link</label>
                        <input type="text" id="pp-endereco" name="endereco" class="form-control"
                               maxlength="60" placeholder="clinica-sorriso"
                               autocapitalize="none" spellcheck="false" autocomplete="off"
                               aria-describedby="pp-endereco-ajuda"
                               value="<?= agenday_config_html($enderecoDigitado) ?>">
                        <p class="form-text" id="pp-endereco-ajuda">
                            Letras, números e hífen. Maiúsculas e acentos são convertidos.
                            Deixe em branco para usar o nome do estabelecimento.
                        </p>
                    </div>

                    <div class="pp-preview">
                        <span class="pp-preview-rotulo">Seu link ficará assim</span>
                        <div class="pp-preview-linha">
                            <code id="pp-portal-preview"
                                  data-prefixo="<?= agenday_config_html($portalPrefixo) ?>"
                                  data-slug="<?= agenday_config_html($portalSlug) ?>"
                                  data-nome="<?= agenday_config_html($nomeEstabelecimento) ?>"><?= agenday_config_html($portalLink !== '' ? $portalLink : $portalPrefixo) ?></code>
                            <button type="button" class="btn btn-outline-secondary" id="copiar-portal-link"
                                    data-link="<?= agenday_config_html($portalLink) ?>"
                                    <?= $portalLink === '' ? 'disabled' : '' ?>>Copiar</button>
                        </div>
                        <p class="pp-preview-aviso" id="pp-preview-aviso" aria-live="polite"<?= $portalLink !== '' ? ' hidden' : '' ?>>
                            Salve o endereço para ativar o link.
                        </p>
                    </div>

                    <div class="pp-link-acoes">
                        <button type="submit" class="btn btn-primary">Salvar e concluir</button>
                        <?php
                        // O botão fica sempre visível: sem endereço salvo ele aparece
                        // desativado, em vez de sumir e deixar a pessoa sem saber que existe.
                        $atributosVerPagina = $portalLink !== ''
                            ? 'href="' . agenday_config_html($portalLink) . '"'
                            : 'aria-disabled="true" tabindex="-1"';
                        ?>
                        <a class="btn btn-outline-secondary pp-ver-pagina<?= $portalLink === '' ? ' disabled' : '' ?>"
                           id="abrir-portal-link"
                           data-link="<?= agenday_config_html($portalLink) ?>"
                           target="_blank" rel="noopener"
                           <?= $atributosVerPagina ?>>
                            <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Ver a página de agendamentos do seu cliente
                        </a>
                    </div>
                </form>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($etapa > 1): ?>
                <a class="btn btn-link mt-3" href="primeiros-passos.php?etapa=<?= $etapa - 1 ?>">Voltar</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($celebrar): ?>
        <div class="modal fade" id="pp-modal-pronto" tabindex="-1" aria-hidden="true"
             aria-labelledby="pp-pronto-titulo" data-reabrir-erro="true">
            <div class="pp-confetes" aria-hidden="true">
                <?php for ($i = 0; $i < 42; $i++): ?>
                    <span style="left: <?= ($i * 37) % 100 ?>%; --atraso: <?= ($i * 73) % 440 ?>ms; --desvio: <?= ($i * 19) % 81 - 40 ?>px"></span>
                <?php endfor; ?>
            </div>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="pp-pronto-titulo">Parabéns! Sua agenda está pronta.</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p>Envie este link para seus clientes escolherem um serviço e um horário.</p>
                        <code class="text-break"><?= agenday_config_html($portalLink) ?></code>
                        <p class="form-text mt-3 mb-0">Você pode alterar esse link depois em <strong>Configurações</strong>, no campo “Endereço do portal”.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" id="pp-copiar-final" class="btn btn-outline-secondary"
                                data-link="<?= agenday_config_html($portalLink) ?>" aria-live="polite">Copiar meu link</button>
                        <a class="btn btn-primary" href="agenda.php">Ir para a agenda</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="pp-modal-negocio" tabindex="-1"
         aria-labelledby="pp-modal-negocio-titulo" aria-hidden="true"
         data-reabrir-erro="<?= $reabrirNegocio ? 'true' : 'false' ?>">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="primeiros-passos.php">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="pp-modal-negocio-titulo">Qual é o seu negócio?</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                        <input type="hidden" name="acao" value="segmento">
                        <input type="hidden" name="segmento" value="outro">
                        <p class="form-text mt-0">
                            Como o seu negócio não está na lista, o AgenDay não tem sugestões prontas.
                            Digite o nome dele: a tela passa a falar do seu negócio, e você cadastra
                            o primeiro serviço em seguida.
                        </p>
                        <div class="pp-campo">
                            <label for="pp-negocio-nome">Nome do negócio</label>
                            <input type="text" id="pp-negocio-nome" name="negocio" class="form-control"
                                   maxlength="60" required
                                   placeholder="Ex.: Pet shop, estúdio de tatuagem"
                                   value="<?= agenday_config_html($negocioDigitado !== '' ? $negocioDigitado : $negocioTentativa) ?>">
                            <p class="form-text">Fica guardado neste navegador, só para esta conta.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Continuar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($conexaoOk): ?>
        <div class="modal fade" id="pp-modal-servico" tabindex="-1"
             aria-labelledby="pp-modal-servico-titulo" aria-hidden="true"
             data-reabrir-erro="<?= $reabrirServico ? 'true' : 'false' ?>">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="cadastro-servicos.php">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="pp-modal-servico-titulo">Novo serviço</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                            <input type="hidden" name="origem" value="primeiros-passos">
                            <div class="pp-campo">
                                <label for="pp-servico-nome">Nome do serviço</label>
                                <input type="text" id="pp-servico-nome" name="nome" class="form-control"
                                       maxlength="120" required placeholder="Ex.: Corte masculino">
                            </div>
                            <div class="pp-campo-duplo">
                                <div class="pp-campo">
                                    <label for="pp-servico-duracao">Duração (minutos)</label>
                                    <input type="number" id="pp-servico-duracao" name="duracao_minutos"
                                           class="form-control" min="1" max="1440" step="1" value="30" required>
                                </div>
                                <div class="pp-campo">
                                    <label for="pp-servico-preco">Preço (R$)</label>
                                    <input type="text" id="pp-servico-preco" name="preco" class="form-control"
                                           inputmode="decimal" maxlength="11" required placeholder="35,00">
                                </div>
                            </div>
                            <div class="pp-campo">
                                <label for="pp-servico-descricao">Descrição (opcional)</label>
                                <textarea id="pp-servico-descricao" name="descricao" class="form-control"
                                          rows="2" maxlength="2000"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Adicionar serviço
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php
        // Os dois caminhos de "quem atende" usam o mesmo cadastro; só mudam
        // o texto e qual pop-up reabre quando o formulário volta com erro.
        $modaisProfissional = [
            'eu' => [
                'id' => 'pp-modal-eu',
                'titulo' => 'Sou eu que atendo',
                'ajuda' => 'Informe seus dados. Depois, confirme quais serviços você atende.',
                'exemplo' => 'Ex.: Dra. Ana Souza',
                'icone' => 'bi-person-check',
                'botao' => 'Cadastrar e vincular',
            ],
            'equipe' => [
                'id' => 'pp-modal-equipe',
                'titulo' => 'Quem atende na sua equipe?',
                'ajuda' => 'Informe os dados da pessoa. Depois, escolha os serviços que ela atende.',
                'exemplo' => 'Ex.: Bia',
                'icone' => 'bi-person-plus',
                'botao' => 'Adicionar à equipe',
            ],
        ];
        foreach ($modaisProfissional as $origemModal => $modalProfissional):
            $rascunho = $reabrirProfissional === $origemModal ? $tentativaPessoa : [];
        ?>
            <div class="modal fade" id="<?= agenday_config_html($modalProfissional['id']) ?>" tabindex="-1"
                 aria-labelledby="<?= agenday_config_html($modalProfissional['id']) ?>-titulo" aria-hidden="true"
                 data-reabrir-erro="<?= $reabrirProfissional === $origemModal ? 'true' : 'false' ?>">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="post" action="primeiros-passos.php">
                            <div class="modal-header">
                                <h2 class="modal-title fs-5" id="<?= agenday_config_html($modalProfissional['id']) ?>-titulo">
                                    <?= agenday_config_html($modalProfissional['titulo']) ?>
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                                <input type="hidden" name="acao" value="profissional">
                                <input type="hidden" name="origem" value="<?= agenday_config_html($origemModal) ?>">
                                <?php if ($rascunho && $erro): ?>
                                    <p class="alert alert-danger" role="alert"><?= agenday_config_html($erro) ?></p>
                                <?php endif; ?>
                                <div data-pessoa-dados>
                                <p class="form-text mt-0"><?= agenday_config_html($modalProfissional['ajuda']) ?></p>
                                <div class="pp-campo">
                                    <label for="<?= agenday_config_html($modalProfissional['id']) ?>-nome">Nome de quem atende</label>
                                    <input type="text" id="<?= agenday_config_html($modalProfissional['id']) ?>-nome"
                                           name="nome" class="form-control"
                                           maxlength="120" required
                                           value="<?= agenday_config_html($rascunho['nome'] ?? '') ?>"
                                           placeholder="<?= agenday_config_html($modalProfissional['exemplo']) ?>">
                                </div>
                                <div class="pp-campo">
                                    <label for="<?= agenday_config_html($modalProfissional['id']) ?>-contato">Telefone com DDD</label>
                                    <input type="tel" id="<?= agenday_config_html($modalProfissional['id']) ?>-contato"
                                           name="contato" class="form-control"
                                           maxlength="20" inputmode="tel" required
                                           value="<?= agenday_config_html($rascunho['contato'] ?? $contatoEstabelecimento) ?>">
                                    <p class="form-text">Já preenchemos com o telefone do estabelecimento.</p>
                                </div>
                                </div>
                                <fieldset data-pessoa-servicos hidden>
                                    <legend class="h6" tabindex="-1">Quais serviços essa pessoa faz?</legend>
                                    <?php foreach ($listaServicos as $idServico => $nomeServico): ?>
                                        <label class="d-flex align-items-center gap-2 py-2">
                                            <input class="form-check-input m-0" type="checkbox" name="servicos[]" value="<?= (int) $idServico ?>"
                                                <?= ($rascunho ? isset($rascunho['servicos'][$idServico]) : $origemModal === 'eu') ? 'checked' : '' ?>>
                                            <?= agenday_config_html($nomeServico) ?>
                                        </label>
                                    <?php endforeach; ?>
                                    <p class="form-text">Selecione pelo menos um serviço para salvar.</p>
                                </fieldset>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-outline-secondary" data-pessoa-voltar hidden>Voltar</button>
                                <button type="button" class="btn btn-primary" data-pessoa-continuar>Continuar</button>
                                <button type="submit" class="btn btn-primary" data-pessoa-salvar hidden>Salvar pessoa e serviços</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="modal fade" id="pp-modal-vinculo" tabindex="-1"
             aria-labelledby="pp-modal-vinculo-titulo" aria-hidden="true"
             data-reabrir-erro="<?= $reabrirVinculo ? 'true' : 'false' ?>">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="primeiros-passos.php">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="pp-modal-vinculo-titulo">Quem atende o quê</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                            <input type="hidden" name="acao" value="vinculo">
                            <?php if ($listaFuncionarios === [] || $listaServicos === []): ?>
                                <p class="pp-modal-vazio">
                                    Cadastre pelo menos um serviço e uma pessoa que atende.
                                    Depois volte aqui para marcar quem faz o quê.
                                </p>
                            <?php else: ?>
                                <p class="form-text mt-0">Marque os serviços que cada pessoa atende.</p>
                                <?php foreach ($listaFuncionarios as $idFuncionario => $funcionario): ?>
                                    <fieldset class="pp-vinculo-grupo">
                                        <legend class="pp-vinculo-nome">
                                            <i class="bi bi-person me-1" aria-hidden="true"></i><?= agenday_config_html($funcionario['nome']) ?>
                                        </legend>
                                        <?php foreach ($listaServicos as $idServico => $nomeServico): ?>
                                            <?php $idCaixa = 'vinc-' . (int) $idFuncionario . '-' . (int) $idServico; ?>
                                            <div class="form-check pp-check">
                                                <input class="form-check-input" type="checkbox"
                                                       id="<?= $idCaixa ?>"
                                                       name="vinculos[<?= (int) $idFuncionario ?>][]"
                                                       value="<?= (int) $idServico ?>"
                                                       <?= isset($funcionario['servicos'][$idServico]) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="<?= $idCaixa ?>">
                                                    <?= agenday_config_html($nomeServico) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </fieldset>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary"
                                <?= ($listaFuncionarios === [] || $listaServicos === []) ? 'disabled' : '' ?>>
                                Salvar vínculos
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="pp-modal-horario" tabindex="-1"
             aria-labelledby="pp-modal-horario-titulo" aria-hidden="true"
             data-reabrir-erro="<?= $reabrirHorario ? 'true' : 'false' ?>">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="post" action="primeiros-passos.php">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="pp-modal-horario-titulo">Horário de funcionamento</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="csrf_token" value="<?= agenday_config_html($csrfToken) ?>">
                            <input type="hidden" name="acao" value="horario">
                            <?php if (!$funcionamentoDisponivel): ?>
                                <p class="pp-modal-vazio">
                                    O horário não está disponível neste banco. Use
                                    <a href="config.php?aba=funcionamento">Configurações</a> para conferir.
                                </p>
                            <?php else: ?>
                                <p class="form-text mt-0">
                                    Marque os dias em que o estabelecimento atende e ajuste os horários.
                                </p>
                                <div class="pp-horarios">
                                    <?php foreach ($diasFuncionamento as $numeroDia => $dia): ?>
                                        <?php $idDia = 'pp-dia-' . (int) $numeroDia; ?>
                                        <div class="pp-horario-linha">
                                            <div class="form-check pp-check">
                                                <input class="form-check-input" type="checkbox" id="<?= $idDia ?>"
                                                       name="dias[<?= (int) $numeroDia ?>][aberto]" value="1"
                                                       <?= !empty($dia['aberto']) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="<?= $idDia ?>">
                                                    <?= agenday_config_html($nomesDias[$numeroDia] ?? '') ?>
                                                </label>
                                            </div>
                                            <input type="time" class="form-control form-control-sm"
                                                   name="dias[<?= (int) $numeroDia ?>][abertura]"
                                                   value="<?= agenday_config_html((string) ($dia['abertura'] ?? '')) ?>"
                                                   aria-label="Abertura de <?= agenday_config_html($nomesDias[$numeroDia] ?? '') ?>">
                                            <span class="pp-horario-ate">até</span>
                                            <input type="time" class="form-control form-control-sm"
                                                   name="dias[<?= (int) $numeroDia ?>][fechamento]"
                                                   value="<?= agenday_config_html((string) ($dia['fechamento'] ?? '')) ?>"
                                                   aria-label="Fechamento de <?= agenday_config_html($nomesDias[$numeroDia] ?? '') ?>">
                                            <input type="hidden"
                                                   name="dias[<?= (int) $numeroDia ?>][almoco_inicio]"
                                                   value="<?= agenday_config_html((string) ($dia['almoco_inicio'] ?? '')) ?>">
                                            <input type="hidden"
                                                   name="dias[<?= (int) $numeroDia ?>][almoco_fim]"
                                                   value="<?= agenday_config_html((string) ($dia['almoco_fim'] ?? '')) ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" <?= !$funcionamentoDisponivel ? 'disabled' : '' ?>>
                                Salvar horário
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

</main>

<script defer src="js/telefone-celular.js?v=20261123-ddd"></script>
<script defer src="js/primeiros-passos.js?v=20261122-quiz"></script>
<script defer src="js/pessoa-servicos.js?v=20261122-1"></script>
</body>

</html>
