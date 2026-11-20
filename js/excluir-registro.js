'use strict';

// Confirmação de exclusão: preenche o modal com o nome e o id do registro
// escolhido na lista. O envio real é um POST com CSRF para o endpoint.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.ag-modal-excluir').forEach(modal => {
        modal.addEventListener('show.bs.modal', evento => {
            const botao = evento.relatedTarget;
            if (!botao) return;

            const campoId = modal.querySelector('[data-excluir-id]');
            const alvoNome = modal.querySelector('[data-excluir-nome]');
            if (campoId) {
                campoId.value = botao.getAttribute('data-id') || '';
            }
            if (alvoNome) {
                alvoNome.textContent = botao.getAttribute('data-nome') || 'este registro';
            }
        });

        modal.addEventListener('shown.bs.modal', () => {
            const confirmar = modal.querySelector('[data-excluir-confirmar]');
            if (confirmar) confirmar.focus();
        });
    });
});
