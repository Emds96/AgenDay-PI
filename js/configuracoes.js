'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const tabs = [...document.querySelectorAll('#abasConfiguracao [role="tab"]')];
    const panes = [...document.querySelectorAll('#conteudoConfiguracao [role="tabpanel"]')];

    const selecionarAba = tab => {
        if (window.bootstrap && window.bootstrap.Tab) {
            window.bootstrap.Tab.getOrCreateInstance(tab).show();
            return;
        }
        // Fallback acessível quando o bundle não estiver disponível.
        tabs.forEach(item => {
            const ativo = item === tab;
            item.classList.toggle('active', ativo);
            item.setAttribute('aria-selected', String(ativo));
            item.tabIndex = ativo ? 0 : -1;
        });
        panes.forEach(pane => {
            const ativo = '#' + pane.id === tab.getAttribute('data-bs-target');
            pane.classList.toggle('active', ativo);
            pane.classList.toggle('show', ativo);
        });
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selecionarAba(tab));
        tab.addEventListener('shown.bs.tab', () => {
            try {
                const url = new URL(window.location.href);
                const mapa = {
                    'aba-estabelecimento': 'estabelecimento',
                    'aba-horarios': 'funcionamento',
                    'aba-whatsapp': 'whatsapp',
                    'aba-minha-conta': 'conta'
                };
                url.searchParams.set('aba', mapa[tab.id] || 'estabelecimento');
                window.history.replaceState(null, '', url);
            } catch (_) {
                // Aba continua operante sem History API.
            }
        });
        tab.addEventListener('keydown', event => {
            let destino = null;
            if (event.key === 'ArrowRight') destino = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') destino = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') destino = 0;
            if (event.key === 'End') destino = tabs.length - 1;
            if (destino === null) return;
            event.preventDefault();
            selecionarAba(tabs[destino]);
            tabs[destino].focus();
        });
    });

    const telefone = document.getElementById('contatoEstabelecimento');
    if (telefone) {
        const atualizarValidade = () => {
            const quantidade = telefone.value.replace(/\D/g, '').length;
            telefone.setCustomValidity(quantidade !== 0 && (quantidade < 8 || quantidade > 15)
                ? 'Informe de 8 a 15 dígitos ou deixe o campo vazio.' : '');
        };
        const formatarTelefone = () => {
            const digitos = telefone.value.replace(/\D/g, '');
            // Máscara brasileira apenas para número com DDD (10/11 dígitos).
            if (digitos.length === 10 || digitos.length === 11) {
                const tamanhoLocal = digitos.length - 2;
                const prefixo = tamanhoLocal === 9 ? 5 : 4;
                telefone.value = '(' + digitos.slice(0, 2) + ') '
                    + digitos.slice(2, 2 + prefixo) + '-' + digitos.slice(2 + prefixo);
            }
            atualizarValidade();
        };
        telefone.addEventListener('input', atualizarValidade);
        telefone.addEventListener('blur', formatarTelefone);
        atualizarValidade();
    }

    // Aba Horários: dia fechado desabilita os campos de hora daquela linha.
    const linhasHorario = [...document.querySelectorAll('#formFuncionamento .ag-horario-linha')];
    linhasHorario.forEach(linha => {
        const caixa = linha.querySelector('.ag-horario-aberto');
        if (!caixa) return;
        const campos = [...linha.querySelectorAll('.ag-horario-campo')];
        const sincronizar = () => {
            campos.forEach(campo => {
                campo.disabled = !caixa.checked;
            });
            linha.classList.toggle('ag-horario-fechado', !caixa.checked);
        };
        caixa.addEventListener('change', sincronizar);
        sincronizar();
    });

    // Aba Horários: ao escolher o início do bloqueio, sugere o mesmo dia às
    // 23:59 no fim (caso mais comum: feriado ou folga do dia inteiro).
    const bloqueioInicio = document.getElementById('bloqueioInicio');
    const bloqueioFim = document.getElementById('bloqueioFim');
    if (bloqueioInicio && bloqueioFim) {
        bloqueioInicio.addEventListener('change', () => {
            if (bloqueioFim.value !== '' || bloqueioInicio.value === '') return;
            bloqueioFim.value = bloqueioInicio.value.slice(0, 10) + 'T23:59';
        });
        const sincronizarBloqueio = () => {
            if (bloqueioInicio.value !== '' && bloqueioFim.value !== ''
                && bloqueioFim.value <= bloqueioInicio.value) {
                bloqueioFim.setCustomValidity('O fim do bloqueio precisa ser depois do início.');
            } else {
                bloqueioFim.setCustomValidity('');
            }
        };
        bloqueioInicio.addEventListener('input', sincronizarBloqueio);
        bloqueioFim.addEventListener('input', sincronizarBloqueio);
        sincronizarBloqueio();
    }

    // Aba WhatsApp: copiar o link público do portal.
    const botaoCopiar = document.getElementById('copiar-portal-link');
    if (botaoCopiar) {
        const textoOriginal = botaoCopiar.textContent;
        const avisar = texto => {
            botaoCopiar.textContent = texto;
            window.setTimeout(() => { botaoCopiar.textContent = textoOriginal; }, 2000);
        };
        botaoCopiar.addEventListener('click', () => {
            const link = botaoCopiar.dataset.link || '';
            if (!link) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(link)
                    .then(() => avisar('Copiado!'))
                    .catch(() => avisar('Copie manualmente'));
                return;
            }
            const campo = document.getElementById('portal-link');
            if (campo) {
                campo.select();
                try {
                    document.execCommand('copy');
                    avisar('Copiado!');
                } catch (_) {
                    avisar('Copie manualmente');
                }
            }
        });
    }

    const campoFoto = document.getElementById('fotoConta');
    if (campoFoto) {
        campoFoto.addEventListener('change', () => {            campoFoto.setCustomValidity('');
            const arquivo = campoFoto.files && campoFoto.files[0];
            if (!arquivo) return;
            if (arquivo.size > 2 * 1024 * 1024
                || !['image/jpeg', 'image/png'].includes(arquivo.type)) {
                campoFoto.setCustomValidity('Escolha uma imagem JPG ou PNG de até 2 MB.');
                campoFoto.reportValidity();
            }
        });
    }

    const modal = document.getElementById('modalAlterarSenha');
    const form = document.getElementById('formAlterarSenha');
    if (!modal || !form) return;

    const nova = document.getElementById('nova-senha');
    const confirmar = document.getElementById('confirmar-nova-senha');
    const atual = document.getElementById('senha-atual');
    const salvar = document.getElementById('salvarNovaSenha');
    const botoesFechar = [...modal.querySelectorAll('.ag-modal-dismiss')];
    let enviando = false;

    const validarSenha = () => {
        nova.setCustomValidity('');
        confirmar.setCustomValidity('');
        const bytes = new TextEncoder().encode(nova.value).length;
        if (nova.value && (bytes < 8 || bytes > 72)) {
            nova.setCustomValidity('A nova senha deve ter de 8 a 72 bytes.');
        } else if (atual.value && nova.value && atual.value === nova.value) {
            nova.setCustomValidity('A nova senha precisa ser diferente da atual.');
        }
        if (confirmar.value && nova.value !== confirmar.value) {
            confirmar.setCustomValidity('A confirmação não corresponde à nova senha.');
        }
    };

    [atual, nova, confirmar].forEach(campo => {
        campo.addEventListener('input', validarSenha);
    });

    modal.addEventListener('show.bs.modal', () => {
        if (!enviando) {
            form.removeAttribute('aria-busy');
            salvar.disabled = false;
            botoesFechar.forEach(botao => { botao.disabled = false; });
        }
    });
    modal.addEventListener('shown.bs.modal', () => atual.focus());
    modal.addEventListener('hide.bs.modal', event => {
        if (enviando) event.preventDefault();
    });
    modal.addEventListener('hidden.bs.modal', () => {
        form.reset();
        nova.setCustomValidity('');
        confirmar.setCustomValidity('');
    });

    form.addEventListener('submit', event => {
        validarSenha();
        if (enviando || !form.reportValidity()) {
            event.preventDefault();
            return;
        }
        enviando = true;
        form.setAttribute('aria-busy', 'true');
        salvar.disabled = true;
        botoesFechar.forEach(botao => { botao.disabled = true; });
        // O navegador envia o POST e o servidor retorna por PRG. Enquanto isso,
        // não é possível dispensar o diálogo nem reenviar a solicitação.
    });

    if (modal.dataset.reabrirErro === 'true' && window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }
});
