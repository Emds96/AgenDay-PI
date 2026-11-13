'use strict';

/**
 * Microtarefa 4.4 — calendário dos registros reais.
 * Datas DATETIME do banco representam o fuso local do estabelecimento.
 * Sem inserir HTML com dados de clientes: apenas textContent e nós DOM.
 */
(() => {
    const calendario = document.getElementById('agenda-calendario');
    if (!calendario) return;

    // O token é emitido pelo PHP na página autenticada; nunca é inventado no navegador.
    const tokenInput = document.querySelector('#form-novo-agendamento input[name="csrf_token"]');
    const tokenCancelamento = tokenInput ? tokenInput.value : '';

    const grade = document.getElementById('agenda-dias');
    const tituloMes = document.getElementById('agenda-mes-titulo');
    const tituloDia = document.getElementById('agenda-dia-titulo');
    const resumoDia = document.getElementById('agenda-dia-resumo');
    const listaDia = document.getElementById('agenda-lista-dia');
    const estado = document.getElementById('agenda-estado');
    const tentarNovamente = document.getElementById('agenda-tentar-novamente');
    const anterior = document.getElementById('agenda-mes-anterior');
    const seguinte = document.getElementById('agenda-mes-seguinte');
    const voltarHoje = document.getElementById('agenda-voltar-hoje');

    if (!grade || !tituloMes || !tituloDia || !resumoDia || !listaDia || !estado
        || !tentarNovamente || !anterior || !seguinte || !voltarHoje) return;

    const nomesMeses = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];
    const nomesStatus = {
        agendado: 'Agendado',
        confirmado: 'Confirmado',
        concluido: 'Concluído',
        cancelado: 'Cancelado'
    };
    const pad = numero => String(numero).padStart(2, '0');
    const formatoData = (ano, mes, dia) => String(ano) + '-' + pad(mes + 1) + '-' + pad(dia);

    // O backend grava DATETIME no fuso America/Manaus por padrão.
    // AGENDAY_TIMEZONE pode ser configurado no servidor para outro fuso.
    function hojeNoFuso() {
        const agora = new Date();
        const fuso = calendario.dataset.fuso || 'America/Manaus';
        try {
            const partes = new Intl.DateTimeFormat('en-US', {
                timeZone: fuso, year: 'numeric', month: '2-digit', day: '2-digit'
            }).formatToParts(agora);
            const valores = Object.fromEntries(partes.map(p => [p.type, p.value]));
            return valores.year + '-' + valores.month + '-' + valores.day;
        } catch (_) {
            return formatoData(agora.getFullYear(), agora.getMonth(), agora.getDate());
        }
    }

    const hoje = hojeNoFuso();
    const sugestao = calendario.dataset.diaInicial || '';
    let diaEscolhido = hoje;
    if (/^\d{4}-\d{2}-\d{2}$/.test(sugestao)) {
        const anoSugerido = Number(sugestao.slice(0, 4));
        const mesSugerido = Number(sugestao.slice(5, 7)) - 1;
        const diaSugerido = Number(sugestao.slice(8, 10));
        const dataVerificada = new Date(Date.UTC(anoSugerido, mesSugerido, diaSugerido));
        if (anoSugerido >= 2000 && anoSugerido <= 2100
            && formatoData(dataVerificada.getUTCFullYear(), dataVerificada.getUTCMonth(),
                dataVerificada.getUTCDate()) === sugestao) {
            diaEscolhido = sugestao;
        }
    }
    let ano = Number(diaEscolhido.slice(0, 4));
    let mes = Number(diaEscolhido.slice(5, 7)) - 1;
    let eventos = [];
    let carregando = false;
    let erroConsulta = '';
    let proximaRequisicao = 0;
    let abortador = null;

    function diaSeguinte(chave) {
        const partes = chave.split('-').map(Number);
        const data = new Date(Date.UTC(partes[0], partes[1] - 1, partes[2] + 1));
        return data.toISOString().slice(0, 10);
    }

    function eventoNoDia(evento, dia) {
        const de = dia + ' 00:00:00';
        const ate = diaSeguinte(dia) + ' 00:00:00';
        // A API devolve DATETIME local em formato YYYY-MM-DD HH:MM:SS.
        // Intervalos [inicio, fim) incluem atendimentos que cruzam a meia-noite.
        return evento.data_inicio < ate && evento.data_fim > de;
    }

    function registrosNoDia(dia) {
        return eventos.filter(evento => eventoNoDia(evento, dia));
    }

    function criarElemento(tag, classe, texto) {
        const elemento = document.createElement(tag);
        if (classe) elemento.className = classe;
        if (texto !== undefined) elemento.textContent = texto;
        return elemento;
    }

    function formatarDia(dia) {
        const partes = dia.split('-').map(Number);
        const data = new Date(Date.UTC(partes[0], partes[1] - 1, partes[2], 12));
        return new Intl.DateTimeFormat('pt-BR', {
            day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC'
        }).format(data);
    }

    function formatarHorario(dataHora) {
        return dataHora.slice(8, 10) + '/' + dataHora.slice(5, 7)
            + ' às ' + dataHora.slice(11, 16);
    }

    function renderizarGrade() {
        tituloMes.textContent = nomesMeses[mes] + ' de ' + ano;
        anterior.disabled = ano === 2000 && mes === 0;
        seguinte.disabled = ano === 2100 && mes === 11;

        const fragmento = document.createDocumentFragment();
        const primeiroDia = new Date(Date.UTC(ano, mes, 1)).getUTCDay();
        const diasNoMes = new Date(Date.UTC(ano, mes + 1, 0)).getUTCDate();

        for (let pos = 0; pos < primeiroDia; pos++) {
            const espaco = criarElemento('span', 'agenda-dia-vazio');
            espaco.setAttribute('aria-hidden', 'true');
            fragmento.appendChild(espaco);
        }

        for (let dia = 1; dia <= diasNoMes; dia++) {
            const chave = formatoData(ano, mes, dia);
            const quantidade = carregando || erroConsulta ? 0 : registrosNoDia(chave).length;
            const botao = criarElemento('button', 'agenda-dia-botao');
            botao.type = 'button';
            if (chave === hoje) botao.classList.add('agenda-dia-hoje');
            if (chave === diaEscolhido) botao.classList.add('agenda-dia-selecionado');
            if (quantidade > 0) botao.classList.add('agenda-dia-com-registros');
            botao.setAttribute('aria-pressed', chave === diaEscolhido ? 'true' : 'false');
            botao.setAttribute('aria-label', formatarDia(chave) + ', '
                + (carregando || erroConsulta ? 'registros ainda não disponíveis'
                    : quantidade + (quantidade === 1 ? ' registro' : ' registros')));
            botao.appendChild(criarElemento('span', 'agenda-dia-numero', String(dia)));

            if (quantidade > 0) {
                botao.appendChild(criarElemento('small', 'agenda-dia-contagem', String(quantidade)));
            }
            botao.addEventListener('click', () => {
                diaEscolhido = chave;
                renderizarGrade();
                renderizarDetalhes();
            });
            fragmento.appendChild(botao);
        }
        grade.replaceChildren(fragmento);
    }

    function renderizarDetalhes() {
        tituloDia.textContent = 'Atendimentos em ' + formatarDia(diaEscolhido);
        listaDia.replaceChildren();

        if (carregando) {
            resumoDia.textContent = 'Consultando o banco de dados...';
            listaDia.appendChild(criarElemento('p', 'text-muted', 'Carregando agendamentos...'));
            return;
        }
        if (erroConsulta) {
            resumoDia.textContent = 'Não foi possível confirmar os registros desta data.';
            listaDia.appendChild(criarElemento('p', 'alert alert-warning', erroConsulta));
            return;
        }

        const registros = registrosNoDia(diaEscolhido)
            .sort((a, b) => a.data_inicio.localeCompare(b.data_inicio));
        const ativos = registros.filter(registro =>
            registro.status === 'agendado' || registro.status === 'confirmado').length;
        resumoDia.textContent = registros.length + (registros.length === 1
            ? ' registro neste dia' : ' registros neste dia')
            + ' · ' + ativos + (ativos === 1 ? ' atendimento ativo.' : ' atendimentos ativos.');

        if (registros.length === 0) {
            listaDia.appendChild(criarElemento('p', 'agenda-dia-vazio-texto',
                'Nenhum agendamento registrado nesta data.'));
            return;
        }

        const fragmento = document.createDocumentFragment();
        for (const registro of registros) {
            const cartao = criarElemento('article', 'agenda-atendimento');
            if (registro.status === 'cancelado') cartao.classList.add('agenda-atendimento-cancelado');

            const topo = criarElemento('div', 'agenda-atendimento-topo');
            topo.appendChild(criarElemento('strong', 'agenda-atendimento-horario',
                formatarHorario(registro.data_inicio) + ' — ' + formatarHorario(registro.data_fim)));

            const nomeSituacao = nomesStatus[registro.status] || 'Status desconhecido';
            const classe = Object.prototype.hasOwnProperty.call(nomesStatus, registro.status)
                ? 'agenda-situacao-' + registro.status : 'agenda-situacao-outro';
            topo.appendChild(criarElemento('span', 'agenda-situacao ' + classe, nomeSituacao));
            cartao.appendChild(topo);

            cartao.appendChild(criarElemento('h3', 'agenda-atendimento-cliente',
                String(registro.nome_cliente || 'Cliente não informado')));
            cartao.appendChild(criarElemento('p', 'agenda-atendimento-detalhe',
                'Serviço: ' + String(registro.nome_servico || 'Não informado')));
            cartao.appendChild(criarElemento('p', 'agenda-atendimento-detalhe',
                'Funcionário: ' + String(registro.nome_funcionario || 'Não informado')));

            if (typeof registro.observacoes === 'string' && registro.observacoes.trim()) {
                cartao.appendChild(criarElemento('p', 'agenda-atendimento-observacoes',
                    'Observações: ' + registro.observacoes));
            }

            // Botão somente para registros ativos. O servidor volta a validar tudo.
            const ativo = registro.status === 'agendado' || registro.status === 'confirmado';
            if (ativo && Number.isSafeInteger(registro.id_agendamento)
                && registro.id_agendamento > 0 && tokenCancelamento) {
                const formCancelamento = criarElemento('form', 'agenda-cancelar-form');
                formCancelamento.method = 'post';
                formCancelamento.action = 'cancelar-agendamento.php';

                const campoId = criarElemento('input');
                campoId.type = 'hidden';
                campoId.name = 'id_agendamento';
                campoId.value = String(registro.id_agendamento);
                formCancelamento.appendChild(campoId);

                const campoCsrf = criarElemento('input');
                campoCsrf.type = 'hidden';
                campoCsrf.name = 'csrf_token';
                campoCsrf.value = tokenCancelamento;
                formCancelamento.appendChild(campoCsrf);

                const botaoCancelar = criarElemento('button', 'btn btn-sm btn-outline-danger',
                    'Cancelar agendamento');
                botaoCancelar.type = 'submit';
                formCancelamento.appendChild(botaoCancelar);
                formCancelamento.addEventListener('submit', evento => {
                    const confirmou = window.confirm(
                        'Deseja cancelar este agendamento? Esta ação libera o horário e mantém o histórico.'
                    );
                    if (!confirmou) {
                        evento.preventDefault();
                        return;
                    }
                    botaoCancelar.disabled = true;
                    botaoCancelar.textContent = 'Cancelando...';
                });
                cartao.appendChild(formCancelamento);
            }
            fragmento.appendChild(cartao);
        }
        listaDia.appendChild(fragmento);
    }

    function renderizar() {
        renderizarGrade();
        renderizarDetalhes();
        tentarNovamente.hidden = !erroConsulta;
        if (carregando) {
            estado.textContent = 'Consultando agendamentos de ' + nomesMeses[mes] + '...';
        } else if (erroConsulta) {
            estado.textContent = 'Falha ao consultar os agendamentos. Os dados não foram carregados.';
        } else {
            estado.textContent = eventos.length === 0
                ? 'Nenhum agendamento registrado neste mês.'
                : eventos.length + (eventos.length === 1
                    ? ' registro encontrado neste mês.' : ' registros encontrados neste mês.');
        }
    }

    async function carregarMes() {
        const requisicao = ++proximaRequisicao;
        if (abortador) abortador.abort();
        abortador = new AbortController();
        eventos = [];
        erroConsulta = '';
        carregando = true;
        renderizar();

        const inicio = formatoData(ano, mes, 1);
        const dataFim = new Date(Date.UTC(ano, mes + 1, 1));
        const fim = dataFim.toISOString().slice(0, 10);
        const url = 'api/agendamentos.php?inicio=' + encodeURIComponent(inicio)
            + '&fim=' + encodeURIComponent(fim);

        try {
            const resposta = await fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
                signal: abortador.signal
            });
            const corpo = await resposta.json();
            if (requisicao !== proximaRequisicao) return;
            if (resposta.status === 401) {
                throw new Error('Sua sessão expirou. Faça login novamente.');
            }
            if (!resposta.ok || corpo.ok !== true || !Array.isArray(corpo.agendamentos)) {
                throw new Error(typeof corpo.erro === 'string'
                    ? corpo.erro : 'Não foi possível consultar os agendamentos.');
            }
            const dataHoraValida = /^\d{4}-\d{2}-\d{2} [0-2]\d:[0-5]\d:[0-5]\d$/;
            eventos = corpo.agendamentos.filter(registro =>
                registro && typeof registro === 'object'
                && typeof registro.data_inicio === 'string'
                && typeof registro.data_fim === 'string'
                && dataHoraValida.test(registro.data_inicio)
                && dataHoraValida.test(registro.data_fim)
                && registro.data_fim > registro.data_inicio);
            erroConsulta = '';
        } catch (erro) {
            if (requisicao !== proximaRequisicao || (erro && erro.name === 'AbortError')) return;
            eventos = [];
            erroConsulta = erro && typeof erro.message === 'string'
                ? erro.message : 'Não foi possível consultar a agenda.';
        } finally {
            if (requisicao === proximaRequisicao) {
                carregando = false;
                renderizar();
            }
        }
    }

    function navegarMes(diferenca) {
        const proximo = new Date(Date.UTC(ano, mes + diferenca, 1));
        const novoAno = proximo.getUTCFullYear();
        if (novoAno < 2000 || novoAno > 2100) return;
        ano = novoAno;
        mes = proximo.getUTCMonth();
        diaEscolhido = formatoData(ano, mes, 1);
        carregarMes();
    }

    anterior.addEventListener('click', () => navegarMes(-1));
    seguinte.addEventListener('click', () => navegarMes(1));
    voltarHoje.addEventListener('click', () => {
        const atual = hojeNoFuso();
        const novoAno = Number(atual.slice(0, 4));
        const novoMes = Number(atual.slice(5, 7)) - 1;
        diaEscolhido = atual;
        if (ano === novoAno && mes === novoMes && !erroConsulta) {
            renderizar();
        } else {
            ano = novoAno;
            mes = novoMes;
            carregarMes();
        }
    });
    tentarNovamente.addEventListener('click', carregarMes);
    carregarMes();
})();
