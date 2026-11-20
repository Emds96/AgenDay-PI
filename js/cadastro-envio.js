// Impede reenvio acidental enquanto o navegador aguarda a resposta do PHP.
// A proteção principal também é aplicada no servidor, por token de uso único.
'use strict';

document.querySelectorAll('form[data-agenday-cadastro]').forEach((form) => {
    const botao = form.querySelector('button[type="submit"]');
    if (!botao) return;

    const textoOriginal = botao.textContent;
    const restaurar = () => {
        delete form.dataset.enviando;
        botao.disabled = false;
        botao.textContent = textoOriginal;
        form.removeAttribute('aria-busy');
    };

    form.addEventListener('submit', (evento) => {
        if (form.dataset.enviando === 'sim') {
            evento.preventDefault();
            return;
        }
        form.dataset.enviando = 'sim';
        form.setAttribute('aria-busy', 'true');
        botao.disabled = true;
        botao.textContent = 'Salvando...';
    });

    // Se o usuário voltar pelo histórico/BFCache, o formulário volta a funcionar.
    window.addEventListener('pageshow', (evento) => {
        if (evento.persisted) restaurar();
    });
});
