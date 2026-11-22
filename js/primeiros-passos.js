/*
 * PRIMEIROS-PASSOS-01
 * Único comportamento de script da tela: copiar o link do portal.
 * Sem isso o botão existe mas não faz nada; o link continua visível para
 * copiar à mão em navegadores sem permissão de área de transferência.
 */
'use strict';

const botaoCopiar = document.getElementById('copiar-portal-link');

if (botaoCopiar) {
    const textoOriginal = botaoCopiar.textContent;
    const avisar = texto => {
        botaoCopiar.textContent = texto;
        window.setTimeout(() => { botaoCopiar.textContent = textoOriginal; }, 2000);
    };

    botaoCopiar.addEventListener('click', () => {
        const link = botaoCopiar.dataset.link || '';
        if (!link) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link)
                .then(() => avisar('Copiado!'))
                .catch(() => avisar('Copie o campo ao lado'));
            return;
        }

        const campo = document.getElementById('pp-portal-link');
        if (campo) {
            campo.select();
            avisar('Copie o campo ao lado');
        }
    });
}
