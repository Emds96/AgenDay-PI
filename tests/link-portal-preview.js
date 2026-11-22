/*
 * PRIMEIROS-PASSOS-02 — prévia do link do portal (js/primeiros-passos.js).
 *
 * Carrega o arquivo real com um DOM mínimo de mentira e confere duas coisas:
 * 1. a normalização do nome digitado é a mesma de agenday_portal_slug_normalizar()
 *    (lib/portal-cliente.php) — se as duas divergirem, a prévia mostra um link
 *    diferente do que o servidor salva;
 * 2. o botão Copiar só libera quando a prévia é exatamente o endereço salvo.
 *
 * Execução: node tests/link-portal-preview.js
 */
'use strict';

const fs = require('fs');
const path = require('path');

const arquivo = path.join(__dirname, '..', 'js', 'primeiros-passos.js');
const codigo = fs.readFileSync(arquivo, 'utf8');

/* Saídas conferidas com agenday_portal_slug_normalizar() no PHP. */
const normalizacao = [
    ['Clínica Sorriso Prime', 'clinica-sorriso-prime'],
    ['Barbearia do João', 'barbearia-do-joao'],
    ['  Ação & Cia!! ', 'acao-cia'],
    ['Studio  Bella   Vita', 'studio-bella-vita'],
    ['ÁÉÍÓÚ Ção', 'aeiou-cao'],
    ['abc___---def', 'abc-def'],
    ['x', 'x'],
    ['já-existe-2', 'ja-existe-2'],
    ['A' + 'b'.repeat(70), 'a' + 'b'.repeat(59)],
];

let aprovados = 0;

function conferir(condicao, mensagem) {
    if (!condicao) {
        console.error('FAIL: ' + mensagem);
        process.exit(1);
    }
    aprovados++;
}

/**
 * Monta os elementos que o script usa e roda o arquivo real.
 * O DOM é falso de propósito: aqui interessa a regra, não o navegador.
 */
function montar(slugSalvo, nomeEstabelecimento, hash) {
    const campo = { value: '', addEventListener: (tipo, fn) => { campo.aoDigitar = fn; } };
    const previa = {
        dataset: {
            prefixo: 'https://portal.test/?e=',
            slug: slugSalvo,
            nome: nomeEstabelecimento === undefined ? 'Clínica Sorriso Prime' : nomeEstabelecimento,
        },
        textContent: '',
    };
    const aviso = { hidden: true, textContent: '' };
    const botao = { dataset: { link: '' }, disabled: false, textContent: 'Copiar', addEventListener: () => {} };

    /* <a> de abrir a página: guarda classList e atributos como o navegador faria. */
    const classes = new Set();
    const atributos = {};
    const verPagina = {
        dataset: { link: '' },
        href: '',
        classList: {
            add: c => classes.add(c),
            remove: c => classes.delete(c),
            contains: c => classes.has(c),
        },
        setAttribute(nome, valor) {
            atributos[nome] = String(valor);
            if (nome === 'href') { verPagina.href = String(valor); }
        },
        removeAttribute(nome) {
            delete atributos[nome];
            if (nome === 'href') { verPagina.href = ''; }
        },
        temAtributo: nome => Object.prototype.hasOwnProperty.call(atributos, nome),
    };

    const mapa = {
        'pp-endereco': campo,
        'pp-portal-preview': previa,
        'pp-preview-aviso': aviso,
        'copiar-portal-link': botao,
        'abrir-portal-link': verPagina,
    };

    /* Campo do atalho "Sou eu que atendo": só registra se recebeu o foco. */
    const campoAtalho = { focado: false, focus: () => { campoAtalho.focado = true; } };

    const documento = {
        getElementById: id => mapa[id] || null,
        querySelector: seletor => (seletor === '[data-focar-ao-chegar="sou-eu-que-atendo"]' ? campoAtalho : null),
    };
    const janela = {
        setTimeout: () => {},
        location: { hash: hash || '' },
        addEventListener: (tipo, fn) => { if (tipo === 'hashchange') { janela.aoMudarHash = fn; } },
    };
    new Function('document', 'window', codigo)(documento, janela);

    return { campo, previa, aviso, botao, verPagina, campoAtalho, janela };
}

const digitar = (tela, valor) => {
    tela.campo.value = valor;
    tela.campo.aoDigitar();
};

/* Endereço já salvo: prévia pronta e copiar liberado. */
const salvo = montar('clinica-sorriso-prime');
digitar(salvo, 'clinica-sorriso-prime');
conferir(salvo.previa.textContent === 'https://portal.test/?e=clinica-sorriso-prime', 'prévia monta o link completo');
conferir(salvo.botao.disabled === false, 'copiar liberado quando a prévia é o endereço salvo');
conferir(salvo.botao.dataset.link === 'https://portal.test/?e=clinica-sorriso-prime', 'copiar aponta para o link salvo');
conferir(salvo.aviso.hidden === true, 'sem aviso quando está salvo');
conferir(salvo.verPagina.href === 'https://portal.test/?e=clinica-sorriso-prime', 'botão de ver a página abre o link salvo');
conferir(salvo.verPagina.classList.contains('disabled') === false, 'botão de ver a página fica ativo quando está salvo');
conferir(salvo.verPagina.temAtributo('aria-disabled') === false, 'sem aria-disabled no botão ativo');

/* Nome novo: prévia acompanha e copiar fica bloqueado até salvar. */
const editando = montar('clinica-sorriso-prime');
digitar(editando, 'Novo Nome');
conferir(editando.previa.textContent === 'https://portal.test/?e=novo-nome', 'prévia acompanha o nome digitado');
conferir(editando.botao.disabled === true, 'copiar bloqueado enquanto não salva');
conferir(editando.botao.dataset.link === '', 'nada para copiar antes de salvar');
conferir(
    editando.aviso.hidden === false && editando.aviso.textContent === 'Salve o endereço para ativar este link.',
    'aviso pede para salvar'
);
conferir(editando.verPagina.href === '', 'ver a página fica sem href enquanto não salva');
conferir(editando.verPagina.classList.contains('disabled') === true, 'ver a página fica desativado enquanto não salva');
conferir(
    editando.verPagina.temAtributo('aria-disabled') === true && editando.verPagina.temAtributo('tabindex'),
    'ver a página desativado sai da navegação por teclado'
);

/* Acento e maiúscula no nome digitado. */
digitar(editando, 'Clínica Sorriso Prime');
conferir(editando.previa.textContent === 'https://portal.test/?e=clinica-sorriso-prime', 'acento e maiúscula são convertidos');
conferir(editando.botao.disabled === false, 'nome que normaliza para o salvo libera o copiar');
conferir(editando.verPagina.href === 'https://portal.test/?e=clinica-sorriso-prime', 'nome que normaliza para o salvo reativa ver a página');

/* Campo vazio: o servidor usa o nome do estabelecimento. */
digitar(editando, '');
conferir(editando.previa.textContent === 'https://portal.test/?e=clinica-sorriso-prime', 'campo vazio prevê o endereço pelo nome');
conferir(editando.botao.disabled === false, 'campo vazio volta ao endereço salvo');
conferir(
    editando.botao.dataset.link === 'https://portal.test/?e=clinica-sorriso-prime',
    'copiar volta a apontar para o link salvo'
);

/* Estabelecimento sem nome útil e conta sem endereço salvo. */
const semNome = montar('', '!!!');
digitar(semNome, '');
conferir(semNome.aviso.textContent === 'Digite um nome para ver o link.', 'aviso quando o nome não gera endereço');
conferir(semNome.previa.textContent === 'https://portal.test/?e=', 'prévia mostra só o prefixo nesse caso');
conferir(semNome.botao.disabled === true, 'sem endereço não há o que copiar');
conferir(semNome.verPagina.classList.contains('disabled') === true, 'sem endereço o ver a página fica desativado');

const semSlug = montar('');
digitar(semSlug, '');
conferir(semSlug.previa.textContent === 'https://portal.test/?e=clinica-sorriso-prime', 'prévia funciona sem endereço salvo');
conferir(semSlug.botao.disabled === true, 'sem endereço salvo o copiar fica bloqueado');
conferir(semSlug.verPagina.href === '', 'conta sem endereço salvo não tem página para abrir');

/* Normalização idêntica ao PHP. */
for (const [entrada, esperado] of normalizacao) {
    const tela = montar('nao-importa');
    digitar(tela, entrada);
    const obtido = tela.previa.textContent.replace('https://portal.test/?e=', '');
    conferir(obtido === esperado, `normaliza ${JSON.stringify(entrada)} -> ${obtido} (esperado ${esperado})`);
}

/* Atalho "Sou eu que atendo": foco no campo do nome ao chegar pelo link. */
const semHash = montar('clinica-sorriso-prime');
conferir(semHash.campoAtalho.focado === false, 'sem hash o campo do atalho não recebe foco');

const comHash = montar('clinica-sorriso-prime', undefined, '#sou-eu-que-atendo');
conferir(comHash.campoAtalho.focado === true, 'chegando por #sou-eu-que-atendo o campo do nome recebe foco');

const outroHash = montar('clinica-sorriso-prime', undefined, '#outra-coisa');
conferir(outroHash.campoAtalho.focado === false, 'outro hash não rouba o foco');

const mudouHash = montar('clinica-sorriso-prime');
conferir(typeof mudouHash.janela.aoMudarHash === 'function', 'script escuta mudança de hash');
mudouHash.janela.location.hash = '#sou-eu-que-atendo';
mudouHash.janela.aoMudarHash();
conferir(mudouHash.campoAtalho.focado === true, 'clicar no atalho já na página foca o campo');

console.log('Prévia do link do portal: ' + aprovados + ' testes aprovados.');
