/**
 * AgenDay 2.0 — confirmação do celular por código no WhatsApp.
 *
 * Usado no cadastro de cliente e no cliente rápido do agendamento. O HTML só
 * precisa do bloco com data-confirmar-telefone; este arquivo cuida do envio,
 * da conferência do código e do aviso de número repetido.
 */
(function () {
    'use strict';

    var ENDPOINT = 'api/painel-telefone.php';

    function csrfDo(form) {
        var campo = form ? form.querySelector('input[name="csrf_token"]') : null;
        return campo ? campo.value : '';
    }

    function iniciar(bloco) {
        if (bloco.dataset.confirmarTelefonePronto === '1') {
            return;
        }
        bloco.dataset.confirmarTelefonePronto = '1';

        var form = bloco.closest('form');
        var campoTelefone = bloco.querySelector('[data-telefone-campo]');
        var botaoEnviar = bloco.querySelector('[data-enviar-codigo]');
        var areaCodigo = bloco.querySelector('[data-codigo-area]');
        var campoCodigo = bloco.querySelector('[data-codigo-campo]');
        var botaoConfirmar = bloco.querySelector('[data-confirmar-codigo]');
        var status = bloco.querySelector('[data-status]');
        var semWhatsapp = bloco.querySelector('[data-sem-whatsapp]');

        if (!campoTelefone || !botaoEnviar || !areaCodigo || !campoCodigo || !botaoConfirmar || !status) {
            return;
        }

        function avisar(texto, tipo) {
            status.textContent = texto;
            status.className = 'form-text ' + (tipo === 'ok' ? 'text-success' : (tipo === 'erro' ? 'text-danger' : ''));
        }

        function limparConfirmacao() {
            bloco.dataset.confirmado = '';
            areaCodigo.hidden = true;
            campoCodigo.value = '';
        }

        async function enviar(permitirDuplicado) {
            var dados = new FormData();
            dados.append('csrf_token', csrfDo(form));
            dados.append('acao', 'enviar');
            dados.append('telefone', campoTelefone.value.trim());
            if (permitirDuplicado) {
                dados.append('permitir_duplicado', '1');
            }

            botaoEnviar.disabled = true;
            avisar('Enviando código…');

            try {
                var resposta = await fetch(ENDPOINT, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    body: dados
                });
                var json = await resposta.json();

                if (json.duplicado && !permitirDuplicado) {
                    var continuar = window.confirm(
                        'Esse celular já está cadastrado para ' + json.nome + '.\n\n'
                        + 'Cadastrar outro cliente com o mesmo número?'
                    );
                    if (continuar) {
                        botaoEnviar.disabled = false;
                        await enviar(true);
                        return;
                    }
                    avisar('Número não confirmado: já pertence a outro cliente.', 'erro');
                    return;
                }

                if (!json.ok) {
                    avisar(json.erro || 'Não foi possível enviar o código.', 'erro');
                    return;
                }

                areaCodigo.hidden = false;
                campoCodigo.value = '';
                campoCodigo.focus();
                avisar('Código enviado para ' + json.mascarado + '. Vale por ' + json.minutos + ' minutos.', 'ok');
            } catch (_) {
                avisar('Não foi possível enviar o código agora.', 'erro');
            } finally {
                botaoEnviar.disabled = false;
            }
        }

        async function confirmar() {
            var dados = new FormData();
            dados.append('csrf_token', csrfDo(form));
            dados.append('acao', 'confirmar');
            dados.append('telefone', campoTelefone.value.trim());
            dados.append('codigo', campoCodigo.value.trim());

            botaoConfirmar.disabled = true;
            avisar('Conferindo…');

            try {
                var resposta = await fetch(ENDPOINT, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                    body: dados
                });
                var json = await resposta.json();

                if (!json.ok) {
                    avisar(json.erro || 'Código incorreto.', 'erro');
                    return;
                }

                bloco.dataset.confirmado = '1';
                areaCodigo.hidden = true;
                avisar('Número confirmado: ' + json.mascarado + '.', 'ok');
            } catch (_) {
                avisar('Não foi possível conferir o código agora.', 'erro');
            } finally {
                botaoConfirmar.disabled = false;
            }
        }

        botaoEnviar.addEventListener('click', function () {
            limparConfirmacao();
            enviar(false);
        });

        botaoConfirmar.addEventListener('click', confirmar);

        campoCodigo.addEventListener('keydown', function (evento) {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                confirmar();
            }
        });

        campoTelefone.addEventListener('input', function () {
            limparConfirmacao();
            avisar('');
        });

        if (semWhatsapp) {
            semWhatsapp.addEventListener('change', function () {
                var marcado = semWhatsapp.checked;
                campoTelefone.required = !marcado;
                botaoEnviar.disabled = marcado;
                limparConfirmacao();
                avisar(marcado
                    ? 'Sem WhatsApp: o cliente será salvo sem número para mensagens.'
                    : '');
            });
        }
    }

    function iniciarTodos() {
        document.querySelectorAll('[data-confirmar-telefone]').forEach(iniciar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarTodos);
    } else {
        iniciarTodos();
    }

    // O agendamento usa isto antes de salvar o cliente rápido.
    window.AgendayTelefone = {
        confirmado: function (bloco) {
            return !!bloco && bloco.dataset.confirmado === '1';
        },
        semWhatsapp: function (bloco) {
            var caixa = bloco ? bloco.querySelector('[data-sem-whatsapp]') : null;
            return !!caixa && caixa.checked;
        }
    };
})();
