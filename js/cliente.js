/*
 * AgenDay 2.0 — portal do cliente.
 * Sem framework e sem build: apenas DOM + fetch.
 *
 * Toda escrita envia X-CSRF-Token (da sessão do cliente). Toda leitura usa os
 * endpoints públicos api/cliente-*.php. O id do cliente nunca sai daqui.
 */
(function () {
    'use strict';

    var corpo = document.body;
    if (!corpo || !corpo.classList.contains('agenday-portal')) {
        return;
    }

    var slug = corpo.dataset.slug || '';
    var S = {
        dados: null,
        csrf: corpo.dataset.csrf || '',
        logado: corpo.dataset.logado === '1',
        exigirCodigo: corpo.dataset.exigirCodigo === '1',
        telefone: '',
        nome: corpo.dataset.cliente || '',
        servico: null,
        profissional: null,
        data: '',
        hora: '',
        ultimo: null,
        meusCarregado: false
    };

    var ROTULOS = {
        agendado: 'Agendado',
        confirmado: 'Confirmado',
        em_atendimento: 'Em atendimento',
        concluido: 'Concluído',
        faltou: 'Faltou',
        cancelado: 'Cancelado'
    };

    function $(seletor, raiz) {
        return (raiz || document).querySelector(seletor);
    }

    function $$(seletor, raiz) {
        return Array.prototype.slice.call((raiz || document).querySelectorAll(seletor));
    }

    function vazio(texto) {
        var p = document.createElement('p');
        p.className = 'cliente-vazio';
        p.textContent = texto;
        return p;
    }

    function alerta(mensagem, tipo) {
        var caixa = $('#cliente-alerta');
        if (!caixa) {
            return;
        }
        caixa.className = 'alert alert-' + (tipo || 'danger');
        caixa.textContent = mensagem;
        caixa.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function limparAlerta() {
        var caixa = $('#cliente-alerta');
        if (caixa) {
            caixa.className = 'alert d-none';
            caixa.textContent = '';
        }
    }

    function ocupado(botao, ativo) {
        if (!botao) {
            return;
        }
        botao.disabled = ativo;
        var texto = $('.cliente-botao-texto', botao);
        var spinner = $('[data-carregando]', botao);
        if (texto) {
            texto.classList.toggle('invisible', ativo);
        }
        if (spinner) {
            spinner.classList.toggle('d-none', !ativo);
        }
    }

    function urlApi(arquivo, acao) {
        var q = new URLSearchParams();
        if (slug) {
            q.set('e', slug);
        }
        if (acao) {
            q.set('acao', acao);
        }
        var consulta = q.toString();
        return 'api/' + arquivo + (consulta ? '?' + consulta : '');
    }

    function api(arquivo, acao, opcoes) {
        opcoes = opcoes || {};
        var init = {
            method: opcoes.corpo === undefined ? 'GET' : 'POST',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        };
        if (opcoes.corpo !== undefined) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opcoes.corpo);
        }
        if (opcoes.csrf) {
            init.headers['X-CSRF-Token'] = opcoes.csrf;
        }
        return fetch(urlApi(arquivo, acao), init).then(function (resposta) {
            return resposta.json().catch(function () {
                return null;
            }).then(function (json) {
                if (!resposta.ok || !json || json.ok === false) {
                    var erro = new Error((json && json.erro) || 'Não foi possível concluir. Tente novamente.');
                    erro.dados = json || {};
                    erro.status = resposta.status;
                    throw erro;
                }
                return json;
            });
        });
    }

    function tratarErro(erro) {
        if (erro && erro.status === 401) {
            S.logado = false;
            S.csrf = '';
            corpo.dataset.logado = '0';
            var caixaSessao = $('#cliente-sessao');
            if (caixaSessao) {
                caixaSessao.hidden = true;
            }
            mostrar('etapa-entrar');
            alerta('Sua sessão expirou. Entre novamente com o celular.', 'warning');
            return;
        }
        alerta((erro && erro.message) || 'Não foi possível concluir. Tente novamente.');
    }

    function mostrar(etapa) {
        ['etapa-entrar', 'etapa-codigo', 'etapa-agendar'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.hidden = id !== etapa;
            }
        });
    }

    function atualizarSessao(nome) {
        S.logado = true;
        if (nome) {
            S.nome = nome;
        }
        corpo.dataset.logado = '1';
        var caixa = $('#cliente-sessao');
        if (caixa) {
            caixa.hidden = false;
        }
        var rotulo = $('[data-cliente-nome]');
        if (rotulo) {
            rotulo.textContent = S.nome;
        }
    }

    function isoData(data) {
        var p = function (n) {
            return String(n).padStart(2, '0');
        };
        return data.getFullYear() + '-' + p(data.getMonth() + 1) + '-' + p(data.getDate());
    }

    function formatarData(iso) {
        var partes = String(iso).split('-');
        if (partes.length !== 3) {
            return iso;
        }
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    function nomeProfissional() {
        if (S.profissional === null) {
            return 'Qualquer profissional';
        }
        if (!S.dados) {
            return '';
        }
        for (var i = 0; i < S.dados.profissionais.length; i++) {
            if (S.dados.profissionais[i].id === S.profissional) {
                return S.dados.profissionais[i].nome;
            }
        }
        return '';
    }

    function definirResumo(alvo, pares) {
        alvo.textContent = '';
        pares.forEach(function (par) {
            if (!par[1]) {
                return;
            }
            var dt = document.createElement('dt');
            dt.textContent = par[0];
            var dd = document.createElement('dd');
            dd.textContent = par[1];
            alvo.appendChild(dt);
            alvo.appendChild(dd);
        });
    }

    /* ------------------------------------------------------------ serviços */
    function renderServicos() {
        var alvo = $('#lista-servicos');
        if (!alvo) {
            return;
        }
        alvo.textContent = '';
        if (!S.dados || !S.dados.servicos.length) {
            alvo.appendChild(vazio('Nenhum serviço disponível no momento.'));
            return;
        }
        S.dados.servicos.forEach(function (servico) {
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'cliente-servico';
            botao.dataset.servico = String(servico.id);
            botao.setAttribute('aria-pressed', String(!!S.servico && S.servico.id === servico.id));

            var nome = document.createElement('span');
            nome.className = 'cliente-servico-nome';
            nome.textContent = servico.nome;

            var meta = document.createElement('span');
            meta.className = 'cliente-servico-meta';
            meta.textContent = servico.duracao + ' min' + (servico.descricao ? ' · ' + servico.descricao : '');

            var preco = document.createElement('span');
            preco.className = 'cliente-servico-preco';
            preco.textContent = 'R$ ' + servico.preco;

            botao.appendChild(nome);
            botao.appendChild(meta);
            botao.appendChild(preco);
            botao.addEventListener('click', function () {
                escolherServico(servico);
            });
            alvo.appendChild(botao);
        });
    }

    function escolherServico(servico) {
        S.servico = servico;
        S.profissional = null;
        S.data = '';
        S.hora = '';
        limparAlerta();
        renderServicos();
        renderProfissionais();

        ['bloco-data', 'bloco-horarios', 'bloco-confirmar', 'bloco-sucesso'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.hidden = true;
            }
        });
        var bloco = $('#bloco-profissional');
        if (bloco) {
            bloco.hidden = false;
            bloco.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /* -------------------------------------------------------- profissionais */
    function renderProfissionais() {
        var alvo = $('#lista-profissionais');
        if (!alvo || !S.servico || !S.dados) {
            return;
        }
        alvo.textContent = '';
        var opcoes = [{ id: null, nome: 'Qualquer profissional' }];
        S.dados.profissionais.forEach(function (p) {
            if (p.servicos.indexOf(S.servico.id) !== -1) {
                opcoes.push(p);
            }
        });
        opcoes.forEach(function (opcao) {
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.className = 'cliente-pessoa';
            botao.textContent = opcao.nome;
            botao.setAttribute('aria-pressed', String(S.profissional === opcao.id));
            botao.addEventListener('click', function () {
                escolherProfissional(opcao.id);
            });
            alvo.appendChild(botao);
        });
    }

    function escolherProfissional(id) {
        S.profissional = id;
        S.data = '';
        S.hora = '';
        renderProfissionais();
        var campo = $('#campo-data');
        if (campo) {
            campo.value = '';
        }
        ['bloco-horarios', 'bloco-confirmar'].forEach(function (blocoId) {
            var el = document.getElementById(blocoId);
            if (el) {
                el.hidden = true;
            }
        });
        var bloco = $('#bloco-data');
        if (bloco) {
            bloco.hidden = false;
            bloco.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    /* --------------------------------------------------------------- dados */
    function carregarDados() {
        return api('cliente-dados.php', '').then(function (dados) {
            S.dados = dados;
            if (dados.sessao) {
                S.csrf = dados.sessao.csrf;
                atualizarSessao(dados.sessao.cliente.nome);
            }
            renderServicos();
            var dica = $('#dica-data');
            if (dica) {
                dica.textContent = 'Agendamentos até ' + dados.regras.janela_dias + ' dias à frente.';
            }
            var campo = $('#campo-data');
            if (campo) {
                var hoje = new Date();
                var limite = new Date();
                limite.setDate(limite.getDate() + dados.regras.janela_dias);
                campo.min = isoData(hoje);
                campo.max = isoData(limite);
            }
        });
    }

    /* ------------------------------------------------------------ horários */
    function carregarHorarios() {
        if (!S.servico || !S.data) {
            return Promise.resolve();
        }
        var alvo = $('#lista-horarios');
        if (!alvo) {
            return Promise.resolve();
        }
        alvo.textContent = '';
        alvo.appendChild(vazio('Carregando horários…'));
        var bloco = $('#bloco-horarios');
        if (bloco) {
            bloco.hidden = false;
        }

        var q = new URLSearchParams();
        if (slug) {
            q.set('e', slug);
        }
        q.set('servico', String(S.servico.id));
        q.set('data', S.data);
        if (S.profissional) {
            q.set('funcionario', String(S.profissional));
        }

        return fetch('api/cliente-horarios.php?' + q.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin'
        }).then(function (resposta) {
            return resposta.json().catch(function () {
                return null;
            }).then(function (json) {
                alvo.textContent = '';
                if (!resposta.ok || !json || json.ok === false) {
                    alvo.appendChild(vazio((json && json.erro) || 'Não foi possível carregar os horários.'));
                    return;
                }
                var livres = (json.slots || []).filter(function (slot) {
                    return slot.livre;
                });
                if (!livres.length) {
                    alvo.appendChild(vazio(json.motivo || 'Nenhum horário livre neste dia. Tente outra data.'));
                    return;
                }
                livres.forEach(function (slot) {
                    var botao = document.createElement('button');
                    botao.type = 'button';
                    botao.className = 'cliente-hora';
                    botao.textContent = slot.hora;
                    botao.setAttribute('aria-pressed', String(S.hora === slot.hora));
                    botao.addEventListener('click', function () {
                        S.hora = slot.hora;
                        $$('.cliente-hora', alvo).forEach(function (outro) {
                            outro.setAttribute('aria-pressed', String(outro === botao));
                        });
                        var confirmar = $('#bloco-confirmar');
                        if (confirmar) {
                            confirmar.hidden = false;
                        }
                        definirResumo($('#resumo-agendamento'), [
                            ['Serviço', S.servico.nome],
                            ['Profissional', nomeProfissional()],
                            ['Data', formatarData(S.data)],
                            ['Horário', S.hora + (slot.fim ? ' às ' + slot.fim : '')],
                            ['Duração', S.servico.duracao + ' min'],
                            ['Valor', 'R$ ' + S.servico.preco]
                        ]);
                        if (confirmar) {
                            confirmar.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    });
                    alvo.appendChild(botao);
                });
            });
        }).catch(function () {
            alvo.textContent = '';
            alvo.appendChild(vazio('Não foi possível carregar os horários agora.'));
        });
    }

    /* ------------------------------------------------------------- confirmar */
    function confirmarAgendamento(botao) {
        if (!S.servico || !S.data || !S.hora) {
            alerta('Escolha serviço, dia e horário antes de confirmar.');
            return;
        }
        var observacoes = $('#campo-observacoes');
        ocupado(botao, true);
        limparAlerta();
        api('cliente-agendamentos.php', 'criar', {
            csrf: S.csrf,
            corpo: {
                servico: S.servico.id,
                funcionario: S.profissional,
                data: S.data,
                hora: S.hora,
                observacoes: observacoes ? observacoes.value : ''
            }
        }).then(function (resposta) {
            S.ultimo = resposta.agendamento;
            S.meusCarregado = false;
            mostrarSucesso(resposta.agendamento);
        }).catch(function (erro) {
            if (erro.dados && erro.dados.conflito) {
                alerta(erro.message, 'warning');
                carregarHorarios();
                return;
            }
            tratarErro(erro);
        }).finally(function () {
            ocupado(botao, false);
        });
    }

    function mostrarSucesso(agendamento) {
        definirResumo($('#resumo-sucesso'), [
            ['Serviço', agendamento.servico],
            ['Profissional', agendamento.profissional],
            ['Data', formatarData(agendamento.data)],
            ['Horário', agendamento.hora + (agendamento.fim ? ' às ' + agendamento.fim : '')]
        ]);
        ['bloco-servico', 'bloco-profissional', 'bloco-data', 'bloco-horarios', 'bloco-confirmar'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.hidden = true;
            }
        });
        var sucesso = $('#bloco-sucesso');
        if (sucesso) {
            sucesso.hidden = false;
            sucesso.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        S.servico = null;
        S.profissional = null;
        S.data = '';
        S.hora = '';
        var obs = $('#campo-observacoes');
        if (obs) {
            obs.value = '';
        }
        var campo = $('#campo-data');
        if (campo) {
            campo.value = '';
        }
    }

    function novoAgendamento() {
        var sucesso = $('#bloco-sucesso');
        if (sucesso) {
            sucesso.hidden = true;
        }
        var bloco = $('#bloco-servico');
        if (bloco) {
            bloco.hidden = false;
            bloco.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        renderServicos();
    }

    /* ------------------------------------------------------------------ ICS */
    function baixarIcs() {
        var ag = S.ultimo;
        if (!ag) {
            return;
        }
        var inicio = new Date(ag.data + 'T' + ag.hora + ':00-04:00');
        var fim = new Date(ag.data + 'T' + (ag.fim || ag.hora) + ':00-04:00');
        if (isNaN(inicio.getTime()) || isNaN(fim.getTime()) || fim <= inicio) {
            fim = new Date(inicio.getTime() + 30 * 60000);
        }
        var p = function (n) {
            return String(n).padStart(2, '0');
        };
        var utc = function (d) {
            return d.getUTCFullYear() + p(d.getUTCMonth() + 1) + p(d.getUTCDate()) + 'T'
                + p(d.getUTCHours()) + p(d.getUTCMinutes()) + p(d.getUTCSeconds()) + 'Z';
        };
        var limpar = function (texto) {
            return String(texto || '').replace(/\\/g, '\\\\').replace(/\n/g, '\\n').replace(/,/g, '\\,').replace(/;/g, '\\;');
        };
        var nomeEstabelecimento = (S.dados && S.dados.estabelecimento && S.dados.estabelecimento.nome) || 'Agendamento';
        var linhas = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//AgenDay//Portal do Cliente//PT-BR',
            'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:agenday-' + ag.id + '-' + (slug || 'portal') + '@agenday',
            'DTSTAMP:' + utc(new Date()),
            'DTSTART:' + utc(inicio),
            'DTEND:' + utc(fim),
            'SUMMARY:' + limpar(ag.servico + ' - ' + nomeEstabelecimento),
            'LOCATION:' + limpar(nomeEstabelecimento),
            'DESCRIPTION:' + limpar('Profissional: ' + ag.profissional),
            'END:VEVENT',
            'END:VCALENDAR'
        ];
        var blob = new Blob([linhas.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'agendamento-' + ag.id + '.ics';
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.setTimeout(function () {
            URL.revokeObjectURL(link.href);
        }, 1500);
    }

    /* --------------------------------------------------- meus agendamentos */
    function renderLista(alvo, itens, comAcoes) {
        if (!alvo) {
            return;
        }
        alvo.textContent = '';
        if (!itens.length) {
            alvo.appendChild(vazio(comAcoes ? 'Nenhum agendamento futuro.' : 'Nenhum atendimento nos últimos 90 dias.'));
            return;
        }
        itens.forEach(function (item) {
            var cartao = document.createElement('article');
            cartao.className = 'cliente-item';

            var topo = document.createElement('div');
            topo.className = 'cliente-item-topo';

            var bloco = document.createElement('div');
            var quando = document.createElement('p');
            quando.className = 'cliente-item-quando';
            quando.textContent = formatarData(item.data) + ' às ' + item.hora;
            var detalhe = document.createElement('p');
            detalhe.className = 'cliente-item-detalhe';
            detalhe.textContent = item.servico + ' · ' + item.profissional;
            bloco.appendChild(quando);
            bloco.appendChild(detalhe);

            var status = document.createElement('span');
            status.className = 'cliente-status';
            status.dataset.status = item.status;
            status.textContent = ROTULOS[item.status] || item.status;

            topo.appendChild(bloco);
            topo.appendChild(status);
            cartao.appendChild(topo);

            if (comAcoes && item.pode_cancelar) {
                var acoes = document.createElement('div');
                acoes.className = 'cliente-item-acoes';

                var remarcar = document.createElement('button');
                remarcar.type = 'button';
                remarcar.className = 'btn btn-sm btn-outline-primary';
                remarcar.innerHTML = '<i class="bi bi-calendar-week" aria-hidden="true"></i> Remarcar';
                remarcar.addEventListener('click', function () {
                    abrirRemarcar(cartao, item);
                });

                var cancelar = document.createElement('button');
                cancelar.type = 'button';
                cancelar.className = 'btn btn-sm btn-outline-danger';
                cancelar.innerHTML = '<i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar';
                cancelar.addEventListener('click', function () {
                    cancelarAgendamento(item, cancelar);
                });

                acoes.appendChild(remarcar);
                acoes.appendChild(cancelar);
                cartao.appendChild(acoes);
            } else if (comAcoes && item.motivo_bloqueio) {
                var aviso = document.createElement('p');
                aviso.className = 'cliente-item-detalhe';
                aviso.textContent = item.motivo_bloqueio;
                cartao.appendChild(aviso);
            }

            alvo.appendChild(cartao);
        });
    }

    function carregarMeus() {
        var proximos = $('#lista-proximos');
        var historico = $('#lista-historico');
        if (proximos) {
            proximos.textContent = '';
            proximos.appendChild(vazio('Carregando…'));
        }
        if (historico) {
            historico.textContent = '';
            historico.appendChild(vazio('Carregando…'));
        }
        return api('cliente-agendamentos.php', 'listar', { csrf: S.csrf, corpo: {} }).then(function (resposta) {
            renderLista(proximos, resposta.proximos, true);
            renderLista(historico, resposta.historico, false);
            S.meusCarregado = true;
        }).catch(function (erro) {
            if (proximos) {
                proximos.textContent = '';
                proximos.appendChild(vazio('Não foi possível carregar seus agendamentos.'));
            }
            tratarErro(erro);
        });
    }

    function cancelarAgendamento(item, botao) {
        if (!window.confirm('Cancelar o agendamento de ' + formatarData(item.data) + ' às ' + item.hora + '?')) {
            return;
        }
        ocupado(botao, true);
        api('cliente-agendamentos.php', 'cancelar', { csrf: S.csrf, corpo: { id: item.id } }).then(function () {
            alerta('Agendamento cancelado. O horário foi liberado.', 'success');
            carregarMeus();
        }).catch(tratarErro).finally(function () {
            ocupado(botao, false);
        });
    }

    function abrirRemarcar(cartao, item) {
        var existente = $('.cliente-remarcar', cartao);
        if (existente) {
            existente.remove();
            return;
        }
        $$('.cliente-remarcar').forEach(function (outro) {
            outro.remove();
        });

        var painel = document.createElement('div');
        painel.className = 'cliente-remarcar';

        var rotulo = document.createElement('label');
        rotulo.className = 'form-label';
        rotulo.textContent = 'Novo dia para ' + item.servico;
        var idCampo = 'remarcar-data-' + item.id;
        rotulo.setAttribute('for', idCampo);

        var campo = document.createElement('input');
        campo.type = 'date';
        campo.className = 'form-control';
        campo.id = idCampo;
        var hoje = new Date();
        var limite = new Date();
        var janela = (S.dados && S.dados.regras && S.dados.regras.janela_dias) || 60;
        limite.setDate(limite.getDate() + janela);
        campo.min = isoData(hoje);
        campo.max = isoData(limite);

        var lista = document.createElement('div');
        lista.className = 'cliente-horarios';
        lista.style.marginTop = '.6rem';

        campo.addEventListener('change', function () {
            if (!campo.value) {
                return;
            }
            lista.textContent = '';
            lista.appendChild(vazio('Carregando horários…'));
            var q = new URLSearchParams();
            if (slug) {
                q.set('e', slug);
            }
            q.set('servico', String(item.servico_id));
            q.set('data', campo.value);
            fetch('api/cliente-horarios.php?' + q.toString(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            }).then(function (resposta) {
                return resposta.json().catch(function () {
                    return null;
                });
            }).then(function (json) {
                lista.textContent = '';
                if (!json || !json.ok) {
                    lista.appendChild(vazio((json && json.erro) || 'Não foi possível carregar os horários.'));
                    return;
                }
                var livres = (json.slots || []).filter(function (slot) {
                    return slot.livre;
                });
                if (!livres.length) {
                    lista.appendChild(vazio('Nenhum horário livre neste dia. Tente outra data.'));
                    return;
                }
                livres.forEach(function (slot) {
                    var botao = document.createElement('button');
                    botao.type = 'button';
                    botao.className = 'cliente-hora';
                    botao.textContent = slot.hora;
                    botao.addEventListener('click', function () {
                        ocupado(botao, true);
                        api('cliente-agendamentos.php', 'remarcar', {
                            csrf: S.csrf,
                            corpo: { id: item.id, data: campo.value, hora: slot.hora }
                        }).then(function () {
                            alerta('Agendamento remarcado para ' + formatarData(campo.value) + ' às ' + slot.hora + '.', 'success');
                            carregarMeus();
                        }).catch(function (erro) {
                            ocupado(botao, false);
                            tratarErro(erro);
                        });
                    });
                    lista.appendChild(botao);
                });
            }).catch(function () {
                lista.textContent = '';
                lista.appendChild(vazio('Não foi possível carregar os horários agora.'));
            });
        });

        painel.appendChild(rotulo);
        painel.appendChild(campo);
        painel.appendChild(lista);
        cartao.appendChild(painel);
        campo.focus();
    }

    /* --------------------------------------------------------------- login */
    function iniciarLogin() {
        var formEntrar = $('#form-entrar');
        if (formEntrar) {
            formEntrar.addEventListener('submit', function (evento) {
                evento.preventDefault();
                limparAlerta();
                var botao = formEntrar.querySelector('button[type="submit"]');
                var telefone = $('#campo-telefone').value.trim();
                var nome = $('#campo-nome').value.trim();
                if (!telefone) {
                    alerta('Informe seu celular com DDD.');
                    $('#campo-telefone').focus();
                    return;
                }
                ocupado(botao, true);
                api('cliente-acesso.php', 'entrar', { corpo: { telefone: telefone, nome: nome } }).then(function (resposta) {
                    S.telefone = telefone;
                    S.nome = nome || S.nome;
                    if (resposta.sem_codigo) {
                        S.csrf = resposta.csrf || '';
                        atualizarSessao(resposta.cliente && resposta.cliente.nome);
                        return carregarDados().then(function () {
                            mostrar('etapa-agendar');
                            alerta('Você entrou com seu celular.', 'success');
                        });
                    }
                    var mascarado = $('[data-telefone-mascarado]');
                    if (mascarado) {
                        mascarado.textContent = resposta.telefone || '';
                    }
                    var minutos = $('[data-minutos]');
                    if (minutos) {
                        minutos.textContent = String(resposta.expira_minutos || 10);
                    }
                    var campoCodigo = $('#campo-codigo');
                    if (campoCodigo) {
                        campoCodigo.value = '';
                    }
                    mostrar('etapa-codigo');
                    if (campoCodigo) {
                        campoCodigo.focus();
                    }
                }).catch(function (erro) {
                    if (erro.dados && erro.dados.precisa_nome) {
                        var campoNome = $('#campo-nome');
                        if (campoNome) {
                            campoNome.classList.add('is-invalid');
                            campoNome.focus();
                        }
                    }
                    alerta(erro.message);
                }).finally(function () {
                    ocupado(botao, false);
                });
            });
        }

        var formCodigo = $('#form-codigo');
        if (formCodigo) {
            formCodigo.addEventListener('submit', function (evento) {
                evento.preventDefault();
                limparAlerta();
                var botao = formCodigo.querySelector('button[type="submit"]');
                var codigo = $('#campo-codigo').value.replace(/\D+/g, '');
                if (codigo.length !== 6) {
                    alerta('Digite os 6 números do código.');
                    $('#campo-codigo').focus();
                    return;
                }
                ocupado(botao, true);
                api('cliente-acesso.php', 'verificar', {
                    corpo: { telefone: S.telefone, codigo: codigo }
                }).then(function (resposta) {
                    S.csrf = resposta.csrf || '';
                    atualizarSessao(resposta.cliente && resposta.cliente.nome);
                    return carregarDados().then(function () {
                        mostrar('etapa-agendar');
                        alerta('Tudo certo! Agora escolha o serviço.', 'success');
                    });
                }).catch(function (erro) {
                    if (erro.dados && erro.dados.codigo_expirado) {
                        alerta(erro.message, 'warning');
                        return;
                    }
                    alerta(erro.message);
                }).finally(function () {
                    ocupado(botao, false);
                });
            });
        }

        var campoNome = $('#campo-nome');
        if (campoNome) {
            campoNome.addEventListener('input', function () {
                campoNome.classList.remove('is-invalid');
            });
        }
    }

    /* -------------------------------------------------------------- eventos */
    function iniciarEventos() {
        var campoData = $('#campo-data');
        if (campoData) {
            campoData.addEventListener('change', function () {
                S.data = campoData.value;
                S.hora = '';
                var confirmar = $('#bloco-confirmar');
                if (confirmar) {
                    confirmar.hidden = true;
                }
                carregarHorarios();
            });
        }

        $$('.cliente-aba').forEach(function (aba) {
            aba.addEventListener('click', function () {
                var qual = aba.dataset.aba;
                $$('.cliente-aba').forEach(function (outra) {
                    var ativa = outra === aba;
                    outra.classList.toggle('is-ativa', ativa);
                    outra.setAttribute('aria-selected', String(ativa));
                });
                var painelAgendar = $('#painel-agendar');
                var painelMeus = $('#painel-meus');
                if (painelAgendar) {
                    painelAgendar.hidden = qual !== 'agendar';
                }
                if (painelMeus) {
                    painelMeus.hidden = qual !== 'meus';
                }
                if (qual === 'meus' && !S.meusCarregado) {
                    carregarMeus();
                }
            });
        });

        document.addEventListener('click', function (evento) {
            var alvo = evento.target.closest('[data-acao]');
            if (!alvo) {
                return;
            }
            var acao = alvo.dataset.acao;
            if (acao === 'confirmar') {
                confirmarAgendamento(alvo);
            } else if (acao === 'novo-agendamento') {
                novoAgendamento();
            } else if (acao === 'baixar-ics') {
                baixarIcs();
            } else if (acao === 'sair') {
                api('cliente-acesso.php', 'sair', { csrf: S.csrf, corpo: {} }).catch(function () {
                    return null;
                }).finally(function () {
                    window.location.reload();
                });
            } else if (acao === 'reenviar-codigo') {
                if (!S.telefone) {
                    mostrar('etapa-entrar');
                    return;
                }
                ocupado(alvo, true);
                api('cliente-acesso.php', 'entrar', {
                    corpo: { telefone: S.telefone, nome: S.nome }
                }).then(function (resposta) {
                    var mascarado = $('[data-telefone-mascarado]');
                    if (mascarado && resposta.telefone) {
                        mascarado.textContent = resposta.telefone;
                    }
                    alerta('Novo código enviado para o seu WhatsApp.', 'success');
                }).catch(function (erro) {
                    alerta(erro.message);
                }).finally(function () {
                    ocupado(alvo, false);
                });
            } else if (acao === 'trocar-telefone') {
                mostrar('etapa-entrar');
                limparAlerta();
                var campo = $('#campo-telefone');
                if (campo) {
                    campo.focus();
                }
            }
        });
    }

    function iniciar() {
        iniciarLogin();
        iniciarEventos();
        if (S.logado) {
            carregarDados().then(function () {
                mostrar('etapa-agendar');
            }).catch(function (erro) {
                mostrar('etapa-entrar');
                tratarErro(erro);
            });
        } else {
            mostrar('etapa-entrar');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
