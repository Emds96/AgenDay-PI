// Smoke checks estruturais da responsividade (sem navegador/MySQL).
// Execução: node tests/responsividade-check.js
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const raiz = path.join(__dirname, '..');
const ler = arquivo => fs.readFileSync(path.join(raiz, arquivo), 'utf8');
const paginas = [
    'index.php', 'agenda.php', 'cad-cliente.php', 'cad-funcionario.php',
    'cad.servicos.php', 'comandas-abertas.php', 'financeiro.php',
    'historico-comandas.php', 'perfil.php', 'config.php', 'alterar-senha.php'
];
let verificacoes = 0;
const verificar = (condicao, mensagem) => {
    assert.ok(condicao, mensagem);
    verificacoes++;
};

for (const pagina of paginas) {
    const html = ler(pagina);
    const head = html.slice(html.indexOf('<head'), html.indexOf('</head>'));
    verificar(head.includes('href="css/design-system.css"'), pagina + ': sem Design System');
    verificar(head.includes('href="css/responsivo.css"'), pagina + ': sem CSS responsivo');
    verificar(head.indexOf('href="css/design-system.css"') < head.indexOf('href="css/responsivo.css"'),
        pagina + ': ordem incorreta de CSS');
    verificar((html.match(/css\/responsivo\.css/g) || []).length === 1,
        pagina + ': CSS responsivo deve aparecer uma vez');
    verificar(html.includes('name="viewport"'), pagina + ': sem meta viewport');
}

const login = ler('index.php');
const loginJs = ler('js/login.js');
const css = ler('css/responsivo.css');
verificar(login.includes('id="auth-ir-login"') && login.includes('id="auth-ir-cadastro"'),
    'Faltam opções de login/cadastro para mobile');
verificar(login.includes('action="login.php"') && login.includes('action="cadastrar.php"'),
    'Endpoints da autenticação devem continuar os mesmos');
verificar(login.includes('name="csrf_token"'), 'Token CSRF da autenticação deve continuar presente');
verificar(loginJs.includes("alternarCadastro(true)") && loginJs.includes("alternarCadastro(false)"),
    'Alternância JS do login ausente');
verificar(loginJs.includes("aria-pressed"), 'Estados de acessibilidade do login ausentes');
verificar(css.includes('@media (max-width: 991.98px)') &&
    css.includes('@media (max-width: 767.98px)') &&
    css.includes('@media (max-width: 390px)'), 'Breakpoints esperados ausentes');
verificar(css.includes('#offcanvasDarkNavbar.offcanvas') &&
    css.includes('width: min(340px, 88vw) !important'), 'Menu mobile não ajustado');
verificar(css.includes('.modal-body .row > [class*="col-"]'), 'Campos do modal não responsivos');
verificar(css.includes('#agenda-calendario .agenda-dia-botao'), 'Calendário mobile não ajustado');
verificar((css.match(/\{/g) || []).length === (css.match(/\}/g) || []).length,
    'Chaves CSS desbalanceadas');
verificar(ler('agenda.php').includes('action="salvar-agendamento.php"'),
    'Endpoint de agendamentos alterado sem necessidade');

console.log('OK: ' + verificacoes + ' verificações estruturais da microtarefa 5.2.');
