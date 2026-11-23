'use strict';

for (const dados of document.querySelectorAll('[data-pessoa-dados]')) {
    const form = dados.closest('form');
    const servicos = form.querySelector('[data-pessoa-servicos]');
    const continuar = form.querySelector('[data-pessoa-continuar]');
    const voltar = form.querySelector('[data-pessoa-voltar]');
    const salvar = form.querySelector('[data-pessoa-salvar]');
    const nome = form.elements.nome;
    const legenda = servicos.querySelector('legend');
    const atualizarSelecao = () => {
        salvar.disabled = !servicos.querySelector('input:checked');
    };
    const mostrar = (segunda, focar = true) => {
        dados.hidden = continuar.hidden = segunda;
        servicos.hidden = voltar.hidden = salvar.hidden = !segunda;
        if (segunda) {
            legenda.textContent = `Quais serviços ${nome.value.trim()} faz?`;
            atualizarSelecao();
        }
        if (focar) (segunda ? legenda : nome).focus();
    };
    continuar.addEventListener('click', () => {
        if (form.reportValidity()) mostrar(true);
    });
    voltar.addEventListener('click', () => mostrar(false));
    servicos.addEventListener('change', atualizarSelecao);
    form.addEventListener('submit', evento => {
        if (servicos.hidden || salvar.disabled) {
            evento.preventDefault();
            if (servicos.hidden) continuar.click();
        }
    });
    form.closest('.modal').addEventListener('hidden.bs.modal', () => {
        form.reset();
        mostrar(false, false);
    });
}
