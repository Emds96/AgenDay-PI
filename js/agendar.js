'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const dados = JSON.parse(document.getElementById('dados-agendar').textContent || '{}');
    const pares = Array.isArray(dados.pares) ? dados.pares : [];
    const csrf = document.body.dataset.csrf || '';
    const hoje = document.body.dataset.hoje || '';

    const el = {
        form: document.getElementById('formAgendar'),
        busca: document.getElementById('buscaCliente'),
        sugestoes: document.getElementById('sugestoesClientes'),
        clienteEscolhido: document.getElementById('clienteEscolhido'),
        clienteId: document.getElementById('campoClienteId'),
        servico: document.getElementById('servico'),
        servicoAjuda: document.getElementById('servicoAjuda'),
        profissional: document.getElementById('profissional'),
        profAjuda: document.getElementById('profAjuda'),
        repeticao: document.getElementById('repeticao'),
        repeticaoAjuda: document.getElementById('repeticaoAjuda'),
        campoIntervalo: document.getElementById('campoIntervalo'),
        intervalo: document.getElementById('intervalo'),
        campoRepeticoes: document.getElementById('campoRepeticoes'),
        repeticoes: document.getElementById('repeticoes'),
        repeticoesAjuda: document.getElementById('repeticoesAjuda'),
        mapa: document.getElementById('mapa'),
        listaSessoes: document.getElementById('listaSessoes'),
        camposSessoes: document.getElementById('camposSessoes'),
        campoModo: document.getElementById('campoModo'),
        campoFuncionario: document.getElementById('campoFuncionario'),
        data: document.getElementById('data'),
        diaTitulo: document.getElementById('diaTitulo'),
        diaResumo: document.getElementById('diaResumo'),
        lista: document.getElementById('listaHorarios'),
        anterior: document.getElementById('diaAnterior'),
        seguinte: document.getElementById('diaSeguinte'),
        botaoHoje: document.getElementById('botaoHoje'),
        resumoTitulo: document.getElementById('resumoTitulo'),
        resumoDetalhe: document.getElementById('resumoDetalhe'),
        confirmar: document.getElementById('confirmar'),
        novoCliente: document.getElementById('novoCliente'),
        abrirNovo: document.getElementById('abrirNovoCliente'),
        cancelarNovo: document.getElementById('cancelarNovoCliente'),
        salvarNovo: document.getElementById('salvarNovoCliente'),
        novoNome: document.getElementById('novoNome'),
        novoTel: document.getElementById('novoTel'),
        avisoNovo: document.getElementById('avisoNovoCliente')
    };

    const estado = {
        clienteId: '',
        clienteNome: '',
        servicoId: '',
        servicoNome: '',
        duracao: 0,
        preco: 0,
        profissional: '',
        modo: 'unico',
        intervalo: 30,
        repeticoes: 3,
        ativo: 0,
        sessoes: [{ data: el.data.value || hoje, hora: '' }],
        slots: [],
        enviando: false
    };

    const dois = n => String(n).padStart(2, '0');
    const dataDe = iso => new Date(iso + 'T12:00:00');
    const deslocar = (iso, dias) => {
        const d = dataDe(iso);
        d.setDate(d.getDate() + dias);
        return d.toISOString().slice(0, 10);
    };
    const somarIntervalo = (iso, vezes) => deslocar(iso, Number(estado.intervalo) * vezes);
    const fimHora = (hora, minutos) => {
        const [h, m] = hora.split(':').map(Number);
        const total = h * 60 + m + minutos;
        return dois(Math.floor(total / 60)) + ':' + dois(total % 60);
    };
    const dataCurta = iso => {
        const d = dataDe(iso);
        const dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
        return dois(d.getDate()) + '/' + dois(d.getMonth() + 1) + ' (' + dias[d.getDay()] + ')';
    };
    const dataLonga = iso => {
        const d = dataDe(iso);
        const dias = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado'];
        const meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return dias[d.getDay()] + ', ' + d.getDate() + ' de ' + meses[d.getMonth()];
    };
    const totalSessoes = () => estado.modo === 'unico' ? 1 : estado.repeticoes;
    const sessaoAtiva = () => estado.sessoes[estado.ativo] || estado.sessoes[0];

    function garantirSessoes() {
        const alvo = totalSessoes();
        if (estado.sessoes.length === alvo) return;
        const base = estado.sessoes[0] ? estado.sessoes[0].data : (el.data.value || hoje);
        const novas = [];
        for (let i = 0; i < alvo; i++) {
            novas.push(estado.sessoes[i]
                ? { data: estado.sessoes[i].data, hora: estado.sessoes[i].hora || '' }
                : { data: somarIntervalo(base, i), hora: '' });
        }
        estado.sessoes = novas;
        if (estado.ativo >= alvo) estado.ativo = 0;
    }

    function renderCamposOcultos() {
        el.camposSessoes.innerHTML = '';
        estado.sessoes.forEach((sessao, i) => {
            const data = document.createElement('input');
            data.type = 'hidden';
            data.name = 'sessoes[' + i + '][data]';
            data.value = sessao.data;
            const hora = document.createElement('input');
            hora.type = 'hidden';
            hora.name = 'sessoes[' + i + '][hora]';
            hora.value = sessao.hora || '';
            el.camposSessoes.appendChild(data);
            el.camposSessoes.appendChild(hora);
        });
        el.campoModo.value = estado.modo;
        el.campoFuncionario.value = estado.profissional;
        el.clienteId.value = estado.clienteId;
    }

    function renderRepeticao() {
        const multi = estado.modo !== 'unico';
        el.campoIntervalo.hidden = !multi;
        el.campoRepeticoes.hidden = !multi;
        el.mapa.hidden = !multi;
        el.listaSessoes.hidden = !multi;

        if (estado.modo === 'unico') {
            el.repeticaoAjuda.textContent = 'Um atendimento só.';
            return;
        }
        if (estado.modo === 'pacote') {
            el.repeticaoAjuda.textContent = 'Todas as sessões são criadas juntas.';
            [...el.repeticoes.options].forEach(o => { o.disabled = o.value === '1'; });
            if (el.repeticoes.value === '1') el.repeticoes.value = '3';
            el.repeticoesAjuda.textContent = 'Serão criados ' + estado.repeticoes + ' agendamentos de uma vez.';
            return;
        }
        el.repeticaoAjuda.textContent = 'O próximo retorno é combinado no fim do atendimento.';
        if (![...el.repeticoes.options].some(o => o.value === '1')) {
            el.repeticoes.add(new Option('1 (só a próxima)', '1'), el.repeticoes.options[0]);
        }
        [...el.repeticoes.options].forEach(o => { o.disabled = false; });
        el.repeticoesAjuda.textContent = estado.repeticoes === 1
            ? 'Próximo retorno sugerido em ' + estado.intervalo + ' dias: '
                + dataCurta(somarIntervalo(estado.sessoes[0].data, 1)) + '.'
            : 'Serão criados ' + estado.repeticoes + ' agendamentos, um a cada ' + estado.intervalo + ' dias.';
    }

    function renderMapa() {
        if (el.mapa.hidden) { el.mapa.innerHTML = ''; return; }
        el.mapa.innerHTML = '';
        estado.sessoes.forEach((sessao, i) => {
            const no = document.createElement('button');
            no.type = 'button';
            no.className = 'mapa-no';
            no.dataset.feita = sessao.hora ? 'true' : 'false';
            if (dataDe(sessao.data).getDay() === 0) no.classList.add('fechado');
            no.setAttribute('aria-current', i === estado.ativo ? 'true' : 'false');
            no.setAttribute('aria-label', 'Sessão ' + (i + 1) + ', ' + dataCurta(sessao.data)
                + (sessao.hora ? ', ' + sessao.hora : ', sem horário'));
            no.innerHTML = '<span class="mapa-ponto"></span><span class="mapa-rotulo">'
                + dataCurta(sessao.data).split(' ')[0] + '</span>';
            no.addEventListener('click', () => selecionarSessao(i));
            el.mapa.appendChild(no);
        });
        if (estado.modo === 'retorno' && estado.repeticoes === 1) {
            const fantasma = document.createElement('div');
            fantasma.className = 'mapa-no fechado';
            fantasma.setAttribute('aria-hidden', 'true');
            fantasma.innerHTML = '<span class="mapa-ponto" style="border-style:dashed;background:transparent;"></span>'
                + '<span class="mapa-rotulo" style="color:var(--ad-text-muted);">próximo</span>';
            el.mapa.appendChild(fantasma);
        }
    }

    function renderListaSessoes() {
        if (el.listaSessoes.hidden) { el.listaSessoes.innerHTML = ''; return; }
        el.listaSessoes.innerHTML = '';
        estado.sessoes.forEach((sessao, i) => {
            const fechado = dataDe(sessao.data).getDay() === 0;
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'sessao-item' + (sessao.hora ? '' : ' falta') + (fechado ? ' fechado' : '');
            botao.setAttribute('aria-current', i === estado.ativo ? 'true' : 'false');
            const direita = fechado
                ? '<span style="color:var(--ad-danger);font-weight:700;">fechado</span>'
                : (sessao.hora
                    ? sessao.hora + '–' + fimHora(sessao.hora, estado.duracao)
                        + '<br><small>qualquer profissional</small>'
                    : 'escolher horário');
            botao.innerHTML = '<span class="sessao-num">' + (i + 1) + '</span>'
                + '<span class="sessao-data">' + dataCurta(sessao.data) + '</span>'
                + '<span class="sessao-hora' + (sessao.hora ? ' ok' : '') + '">' + direita + '</span>';
            botao.addEventListener('click', () => selecionarSessao(i));
            el.listaSessoes.appendChild(botao);
        });
    }

    function atualizarCabecalho() {
        const sessao = sessaoAtiva();
        if (estado.modo === 'unico') {
            el.diaTitulo.textContent = dataLonga(sessao.data);
        } else {
            el.diaTitulo.textContent = 'Sessão ' + (estado.ativo + 1) + ' de ' + estado.sessoes.length;
        }
        el.data.value = sessao.data;
        el.botaoHoje.hidden = sessao.data !== hoje;
    }

    function atualizarResumo() {
        const partes = [];
        const n = estado.sessoes.length;
        const marcadas = estado.sessoes.filter(s => s.hora).length;

        if (estado.modo === 'unico') {
            const sessao = estado.sessoes[0];
            if (sessao.hora) {
                partes.push(sessao.hora + '–' + fimHora(sessao.hora, estado.duracao));
                partes.push(estado.profissional
                    ? el.profissional.options[el.profissional.selectedIndex].textContent.trim()
                    : 'qualquer profissional');
            }
            if (estado.clienteNome) partes.push(estado.clienteNome);
            el.resumoDetalhe.textContent = partes.length
                ? partes.join(' · ')
                : (estado.servicoNome ? estado.servicoNome + ' · ' + estado.duracao + ' min' : 'Nenhum serviço escolhido');
            el.resumoTitulo.textContent = (estado.clienteId && sessao.hora)
                ? 'Pronto para agendar'
                : (sessao.hora ? 'Escolha o cliente' : 'Escolha cliente e horário');
            el.confirmar.disabled = !(estado.clienteId && sessao.hora && estado.servicoId);
            el.confirmar.textContent = sessao.hora ? 'Agendar ' + sessao.hora : 'Agendar horário';
            return;
        }

        el.resumoDetalhe.textContent = n + ' × ' + (estado.servicoNome || 'serviço')
            + (estado.preco ? ' · R$ ' + (estado.preco * n).toFixed(2).replace('.', ',') : '')
            + (estado.clienteNome ? ' · ' + estado.clienteNome : '');
        el.resumoTitulo.textContent = marcadas === n ? 'Pronto para agendar' : marcadas + ' de ' + n + ' marcadas';
        el.confirmar.disabled = !(estado.clienteId && estado.servicoId && marcadas === n);
        el.confirmar.textContent = estado.modo === 'retorno' && n === 1
            ? 'Agendar e programar retorno'
            : (n === 1 ? 'Agendar horário' : 'Agendar ' + n + ' sessões');
    }

    function atualizarTudo() {
        renderRepeticao();
        renderMapa();
        renderListaSessoes();
        atualizarCabecalho();
        renderCamposOcultos();
        atualizarResumo();
    }

    function mostrarAviso(texto) {
        el.lista.innerHTML = '';
        const aviso = document.createElement('p');
        aviso.className = 'aviso-horarios';
        aviso.textContent = texto;
        el.lista.appendChild(aviso);
    }

    function renderizarSlots() {
        el.lista.innerHTML = '';
        if (!estado.servicoId) {
            mostrarAviso('Escolha um serviço para ver os horários.');
            el.diaResumo.textContent = 'Escolha um serviço para ver os horários.';
            return;
        }
        const disponiveis = estado.slots.filter(s => !s.passado);
        if (disponiveis.length === 0) {
            mostrarAviso(estado.slots.length
                ? 'Não há mais horários livres hoje. Escolha outro dia.'
                : 'Nenhum horário de atendimento neste dia.');
            return;
        }
        const sessao = sessaoAtiva();
        const manha = disponiveis.filter(s => Number(s.hora.slice(0, 2)) < 12);
        const tarde = disponiveis.filter(s => Number(s.hora.slice(0, 2)) >= 12);

        const criarGrupo = (titulo, lista) => {
            if (!lista.length) return;
            const secao = document.createElement('section');
            secao.className = 'periodo';
            const h = document.createElement('h3');
            h.className = 'periodo-titulo';
            h.textContent = titulo;
            const grade = document.createElement('div');
            grade.className = 'grade-horarios';
            lista.forEach(slot => {
                const botao = document.createElement('button');
                botao.type = 'button';
                botao.className = 'hora ' + (slot.livre ? 'hora-livre' : 'hora-ocupada');
                botao.dataset.hora = slot.hora;
                botao.setAttribute('aria-pressed', sessao.hora === slot.hora ? 'true' : 'false');
                const nota = slot.livre
                    ? (slot.profissionais.length === 1 ? '1 livre' : slot.profissionais.length + ' livres')
                    : 'cheio';
                botao.innerHTML = '<span class="hora-tempo">' + slot.hora + '</span>'
                    + '<span class="hora-nota">' + nota + '</span>';
                botao.setAttribute('aria-label', 'Horário ' + slot.hora + ', ' + (slot.livre ? nota : 'sem vaga'));
                if (slot.livre) {
                    botao.addEventListener('click', () => {
                        sessaoAtiva().hora = slot.hora;
                        renderizarSlots();
                        atualizarTudo();
                    });
                } else {
                    botao.disabled = true;
                }
                grade.appendChild(botao);
            });
            secao.appendChild(h);
            secao.appendChild(grade);
            el.lista.appendChild(secao);
        };

        criarGrupo('Manhã', manha);
        criarGrupo('Tarde', tarde);
    }

    let controlador = null;
    async function carregarHorarios() {
        const sessao = sessaoAtiva();
        el.data.value = sessao.data;
        el.botaoHoje.hidden = sessao.data !== hoje;
        el.diaTitulo.textContent = estado.modo === 'unico'
            ? dataLonga(sessao.data)
            : 'Sessão ' + (estado.ativo + 1) + ' de ' + estado.sessoes.length + ' · ' + dataCurta(sessao.data);

        if (!estado.servicoId) {
            estado.slots = [];
            renderizarSlots();
            return;
        }

        el.diaResumo.textContent = 'Carregando horários…';
        if (controlador) controlador.abort();
        controlador = new AbortController();

        const params = new URLSearchParams({
            data: sessao.data,
            servico: estado.servicoId,
            funcionario: estado.profissional
        });

        try {
            const resposta = await fetch('api/horarios.php?' + params.toString(), {
                credentials: 'same-origin',
                signal: controlador.signal,
                headers: { Accept: 'application/json' }
            });
            const json = await resposta.json();
            if (!json.ok) {
                estado.slots = [];
                mostrarAviso(json.erro || 'Não foi possível carregar os horários.');
                el.diaResumo.textContent = json.erro || 'Sem horários.';
                return;
            }
            estado.slots = json.slots || [];
            if (json.fechado || json.motivo) {
                mostrarAviso(json.motivo || 'O estabelecimento não atende neste dia.');
                el.diaResumo.textContent = json.motivo || 'Fechado';
                return;
            }
            const livres = estado.slots.filter(s => s.livre && !s.passado).length;
            const totalProf = json.profissionais ? json.profissionais.length : 0;
            el.diaResumo.textContent = livres + (livres === 1 ? ' horário livre' : ' horários livres')
                + (totalProf === 1 ? ' · 1 profissional' : ' · ' + totalProf + ' profissionais');
            if (sessao.hora && !estado.slots.some(s => s.hora === sessao.hora && s.livre)) {
                sessao.hora = '';
            }
            renderizarSlots();
            atualizarResumo();
        } catch (erro) {
            if (erro.name === 'AbortError') return;
            estado.slots = [];
            mostrarAviso('Não foi possível carregar os horários. Tente novamente.');
            el.diaResumo.textContent = 'Erro ao carregar.';
        }
    }

    function aplicarServico() {
        const opcao = el.servico.options[el.servico.selectedIndex];
        estado.servicoId = el.servico.value;
        estado.servicoNome = estado.servicoId ? opcao.textContent.split('·')[0].trim() : '';
        estado.duracao = Number(opcao.dataset.duracao || 0);
        estado.preco = Number(opcao.dataset.preco || 0);
        estado.sessoes.forEach(s => { s.hora = ''; });

        const permitidos = pares
            .filter(p => String(p.id_servicos) === String(estado.servicoId))
            .filter((p, i, arr) => arr.findIndex(x => x.id_funcionario === p.id_funcionario) === i);

        const atual = estado.profissional;
        el.profissional.innerHTML = '<option value="">Qualquer um (o sistema escolhe)</option>';
        permitidos.forEach(p => {
            const o = document.createElement('option');
            o.value = String(p.id_funcionario);
            o.textContent = p.nome_funcionario;
            el.profissional.appendChild(o);
        });
        estado.profissional = permitidos.some(p => String(p.id_funcionario) === atual) ? atual : '';
        el.profissional.value = estado.profissional;
        el.profissional.disabled = !estado.servicoId || permitidos.length === 0;

        el.servicoAjuda.textContent = estado.servicoId
            ? 'Duração de ' + estado.duracao + ' minutos, definida no cadastro do serviço.'
            : 'Duração definida no cadastro do serviço.';

        atualizarTudo();
        carregarHorarios();
    }

    function selecionarSessao(i) {
        estado.ativo = i;
        atualizarTudo();
        carregarHorarios();
    }

    // Cliente
    let temporizador = null;
    let controladorClientes = null;

    function mostrarSugestoes(lista) {
        el.sugestoes.innerHTML = '';
        if (!lista.length) {
            const li = document.createElement('li');
            li.className = 'sugestoes-vazio';
            li.textContent = 'Nenhum cliente encontrado.';
            el.sugestoes.appendChild(li);
            el.sugestoes.hidden = false;
            return;
        }
        lista.forEach(cliente => {
            const li = document.createElement('li');
            const botao = document.createElement('button');
            botao.type = 'button';
            botao.innerHTML = '<strong>' + cliente.nome + '</strong>'
                + (cliente.contato ? ' <small>· ' + cliente.contato + '</small>' : '');
            botao.addEventListener('click', () => {
                estado.clienteId = String(cliente.id);
                estado.clienteNome = cliente.nome;
                el.busca.value = cliente.nome;
                el.clienteEscolhido.textContent = 'Cliente: ' + cliente.nome;
                el.sugestoes.hidden = true;
                renderCamposOcultos();
                atualizarResumo();
            });
            li.appendChild(botao);
            el.sugestoes.appendChild(li);
        });
        el.sugestoes.hidden = false;
    }

    async function buscarClientes(termo) {
        if (controladorClientes) controladorClientes.abort();
        controladorClientes = new AbortController();
        try {
            const resposta = await fetch('api/clientes-busca.php?q=' + encodeURIComponent(termo), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controladorClientes.signal
            });
            const json = await resposta.json();
            if (!json.ok) { el.sugestoes.hidden = true; return; }
            mostrarSugestoes(json.clientes || []);
        } catch (erro) {
            if (erro.name === 'AbortError') return;
            el.sugestoes.hidden = true;
        }
    }

    el.busca.addEventListener('focus', () => {
        if (!estado.clienteId) buscarClientes(el.busca.value.trim());
    });
    el.busca.addEventListener('input', () => {
        estado.clienteId = '';
        estado.clienteNome = '';
        el.clienteEscolhido.textContent = 'Nenhum cliente escolhido.';
        renderCamposOcultos();
        atualizarResumo();
        clearTimeout(temporizador);
        temporizador = setTimeout(() => buscarClientes(el.busca.value.trim()), 200);
    });

    // Cliente novo
    el.abrirNovo.addEventListener('click', () => {
        el.novoCliente.hidden = false;
        if (!estado.clienteId && el.busca.value.trim() !== '') {
            el.novoNome.value = el.busca.value.trim();
        }
        el.novoNome.focus();
    });
    el.cancelarNovo.addEventListener('click', () => {
        el.novoCliente.hidden = true;
        el.avisoNovo.textContent = '';
    });
    el.salvarNovo.addEventListener('click', async () => {
        const nome = el.novoNome.value.trim();
        if (nome.length < 2) {
            el.avisoNovo.textContent = 'Informe o nome do cliente.';
            el.novoNome.focus();
            return;
        }
        el.salvarNovo.disabled = true;
        el.avisoNovo.textContent = 'Salvando…';
        try {
            const corpo = new URLSearchParams({
                csrf_token: csrf,
                nome: nome,
                telefone: el.novoTel.value.trim()
            });
            const blocoTelefone = document.getElementById('confirmarTelefoneNovo');
            if (window.AgendayTelefone && window.AgendayTelefone.semWhatsapp(blocoTelefone)) {
                corpo.append('sem_whatsapp', '1');
            }
            const resposta = await fetch('criar-cliente-rapido.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
                body: corpo.toString()
            });
            const json = await resposta.json();
            if (!json.ok) {
                el.avisoNovo.textContent = json.erro || 'Não foi possível salvar.';
                return;
            }
            estado.clienteId = String(json.cliente.id);
            estado.clienteNome = json.cliente.nome;
            el.busca.value = json.cliente.nome;
            el.clienteEscolhido.textContent = 'Cliente: ' + json.cliente.nome
                + (json.reaproveitado ? ' (cadastro existente)' : '');
            el.novoCliente.hidden = true;
            el.novoNome.value = '';
            el.novoTel.value = '';
            el.avisoNovo.textContent = '';
            if (blocoTelefone) {
                blocoTelefone.dataset.confirmado = '';
                const caixaSem = blocoTelefone.querySelector('[data-sem-whatsapp]');
                if (caixaSem) {
                    caixaSem.checked = false;
                }
                const statusTelefone = blocoTelefone.querySelector('[data-status]');
                if (statusTelefone) {
                    statusTelefone.textContent = '';
                }
                const areaCodigo = blocoTelefone.querySelector('[data-codigo-area]');
                if (areaCodigo) {
                    areaCodigo.hidden = true;
                }
            }
            renderCamposOcultos();
            atualizarResumo();
        } catch (_) {
            el.avisoNovo.textContent = 'Não foi possível salvar agora.';
        } finally {
            el.salvarNovo.disabled = false;
        }
    });

    el.servico.addEventListener('change', aplicarServico);
    el.profissional.addEventListener('change', () => {
        estado.profissional = el.profissional.value;
        estado.sessoes.forEach(s => { s.hora = ''; });
        el.profAjuda.textContent = estado.profissional
            ? 'Somente ' + el.profissional.options[el.profissional.selectedIndex].textContent.trim() + ' será considerada(o).'
            : 'O sistema escolhe um profissional livre no horário.';
        renderCamposOcultos();
        carregarHorarios();
        atualizarResumo();
    });

    el.repeticao.addEventListener('change', () => {
        estado.modo = el.repeticao.value;
        estado.ativo = 0;
        if (estado.modo === 'retorno') {
            estado.intervalo = 20;
            el.intervalo.value = '20';
            estado.repeticoes = 1;
            el.repeticoes.value = '1';
        }
        garantirSessoes();
        atualizarTudo();
        carregarHorarios();
    });

    el.intervalo.addEventListener('change', () => {
        estado.intervalo = Number(el.intervalo.value);
        const base = estado.sessoes[0].data;
        estado.sessoes.forEach((s, i) => {
            if (i > 0) { s.data = somarIntervalo(base, i); s.hora = ''; }
        });
        estado.ativo = 0;
        atualizarTudo();
        carregarHorarios();
    });

    el.repeticoes.addEventListener('change', () => {
        estado.repeticoes = Number(el.repeticoes.value);
        estado.ativo = 0;
        garantirSessoes();
        atualizarTudo();
        carregarHorarios();
    });

    el.data.addEventListener('change', () => {
        sessaoAtiva().data = el.data.value;
        sessaoAtiva().hora = '';
        atualizarTudo();
        carregarHorarios();
    });
    el.anterior.addEventListener('click', () => {
        const sessao = sessaoAtiva();
        sessao.data = deslocar(sessao.data, -1);
        sessao.hora = '';
        atualizarTudo();
        carregarHorarios();
    });
    el.seguinte.addEventListener('click', () => {
        const sessao = sessaoAtiva();
        sessao.data = deslocar(sessao.data, 1);
        sessao.hora = '';
        atualizarTudo();
        carregarHorarios();
    });
    el.botaoHoje.addEventListener('click', () => {
        const sessao = sessaoAtiva();
        sessao.data = hoje;
        sessao.hora = '';
        atualizarTudo();
        carregarHorarios();
    });

    el.form.addEventListener('submit', evento => {
        if (estado.enviando || el.confirmar.disabled) {
            evento.preventDefault();
            return;
        }
        estado.enviando = true;
        el.confirmar.disabled = true;
        el.confirmar.textContent = 'Salvando…';
    });

    // Inicialização
    if (el.servico.value) {
        aplicarServico();
    } else {
        atualizarTudo();
        renderizarSlots();
    }
});
