'use strict';

// O servidor só inclui o modal quando existe identidade Google previamente validada.
// Nunca abrir o modal por parâmetro GET ou valor do navegador.
(() => {
    const dialog = document.getElementById('ad-google-onboarding');
    if (!dialog) return;

    const senha = document.getElementById('ad-google-senha');
    const confirmar = document.getElementById('ad-google-confirmar');
    const wrapper = document.getElementById('ad-google-confirmar-wrapper');
    const semSenha = dialog.querySelector('[name="sem_senha"]');

    function atualizarConfirmacao() {
        if (!senha || !confirmar || !wrapper) return;
        const ativa = senha.value.length > 0;
        wrapper.hidden = !ativa;
        confirmar.required = ativa;
        if (!ativa) confirmar.value = '';
    }

    if (senha) senha.addEventListener('input', atualizarConfirmacao);
    atualizarConfirmacao();

    if (semSenha) semSenha.addEventListener('click', () => {
        if (senha) senha.value = '';
        atualizarConfirmacao();
    });

    if (typeof dialog.showModal === 'function' && !dialog.open) {
        dialog.showModal();
    } else if (!dialog.open) {
        dialog.setAttribute('open', '');
    }
})();
