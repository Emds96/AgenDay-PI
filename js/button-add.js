// Compatibilidade com páginas que possam usar um campo de contato adicional.
// A tela atual de configurações usa apenas um contato e dispensa este script.
document.addEventListener('DOMContentLoaded', function () {
    const botao = document.getElementById('action-btn');
    const campoExtra = document.getElementById('inputContato2');

    if (!botao || !campoExtra) {
        return;
    }

    botao.addEventListener('click', function () {
        campoExtra.classList.toggle('d-none');
        botao.setAttribute('aria-expanded', String(!campoExtra.classList.contains('d-none')));
    });
});
