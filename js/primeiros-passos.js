/*
 * PRIMEIROS-PASSOS-02 / PRIMEIROS-PASSOS-07
 *
 * Comportamentos da tela:
 * 1. prévia ao vivo do link do portal — a pessoa digita o nome e vê embaixo
 *    como o link fica;
 * 2. copiar — só libera quando o endereço exibido é o que está salvo, para
 *    ninguém enviar ao cliente um link que ainda não existe;
 * 3. ver a página — o botão de abrir a página do cliente segue a mesma regra:
 *    fica desativado enquanto o nome digitado não foi salvo;
 * 4. pop-ups de cadastro — se um deles voltou com erro, reabre sozinho.
 *
 * A normalização espelha agenday_portal_slug_normalizar() (lib/portal-cliente.php):
 * minúsculas, sem acento, [a-z0-9] e hífen simples, até 60 caracteres.
 */
'use strict';

const campoEndereco = document.getElementById('pp-endereco');
const previaLink = document.getElementById('pp-portal-preview');
const avisoPrevia = document.getElementById('pp-preview-aviso');
const botaoCopiar = document.getElementById('copiar-portal-link');
const botaoVerPagina = document.getElementById('abrir-portal-link');

const normalizarEndereco = texto => {
    const semAcento = String(texto || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
    return semAcento
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 60)
        .replace(/-+$/g, '');
};

if (campoEndereco && previaLink && avisoPrevia && botaoCopiar) {
    const atualizarPrevia = () => {
        const prefixo = previaLink.dataset.prefixo || '';
        const salvo = previaLink.dataset.slug || '';
        const digitado = normalizarEndereco(campoEndereco.value);
        const efetivo = digitado || normalizarEndereco(previaLink.dataset.nome || '');
        const link = prefixo + efetivo;
        const igualAoSalvo = efetivo !== '' && efetivo === salvo;

        previaLink.textContent = link;
        botaoCopiar.dataset.link = igualAoSalvo ? link : '';
        botaoCopiar.disabled = !igualAoSalvo;

        if (botaoVerPagina) {
            if (igualAoSalvo) {
                botaoVerPagina.setAttribute('href', link);
                botaoVerPagina.classList.remove('disabled');
                botaoVerPagina.removeAttribute('aria-disabled');
                botaoVerPagina.removeAttribute('tabindex');
            } else {
                botaoVerPagina.removeAttribute('href');
                botaoVerPagina.classList.add('disabled');
                botaoVerPagina.setAttribute('aria-disabled', 'true');
                botaoVerPagina.setAttribute('tabindex', '-1');
            }
        }

        if (igualAoSalvo) {
            avisoPrevia.hidden = true;
        } else {
            avisoPrevia.hidden = false;
            avisoPrevia.textContent = efetivo === ''
                ? 'Digite um nome para ver o link.'
                : 'Salve o endereço para ativar este link.';
        }
    };

    campoEndereco.addEventListener('input', atualizarPrevia);
    atualizarPrevia();
}

for (const botao of [botaoCopiar, document.getElementById('pp-copiar-final')].filter(Boolean)) {
    const textoOriginal = botao.textContent;
    const avisar = texto => {
        botao.textContent = texto;
        window.setTimeout(() => { botao.textContent = textoOriginal; }, 2000);
    };

    botao.addEventListener('click', () => {
        const link = botao.dataset.link || '';
        if (!link) {
            return;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link)
                .then(() => avisar('Copiado!'))
                .catch(() => avisar('Selecione e copie o endereço'));
            return;
        }

        avisar('Selecione e copie o endereço');
    });
}

/*
 * Pop-ups do primeiro uso: se um cadastro voltou com erro, o pop-up certo
 * reabre sozinho para a pessoa corrigir sem procurar onde clicar de novo.
 * Só um pop-up fica marcado com data-reabrir-erro="true" por vez.
 */
if (typeof bootstrap !== 'undefined') {
    document.querySelectorAll('[data-reabrir-erro="true"]').forEach(elemento => {
        bootstrap.Modal.getOrCreateInstance(elemento).show();
    });
}
