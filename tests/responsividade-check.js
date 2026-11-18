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
    'historico-comandas.php', 'config.php'
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
    verificar(head.includes('href="css/responsivo.css'), pagina + ': sem CSS responsivo');
    verificar(head.indexOf('href="css/design-system.css"') < head.indexOf('href="css/responsivo.css'),
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
// QA-01: estado visível deve neutralizar o deslocamento do offcanvas-start.
// Teste estrutural: a abertura/fechamento real ainda exige regressão visual.
verificar(/\.agenday-app\s+#offcanvasDarkNavbar\.offcanvas\.show:not\(\.hiding\)\s*,\s*\.agenday-app\s+#offcanvasDarkNavbar\.offcanvas\.showing\s*\{[^}]*transform:\s*none\s*!important;/m.test(css),
    'QA-01: menu aberto pode continuar com transform para fora da tela');
verificar(css.includes('.modal-body .row > [class*="col-"]'), 'Campos do modal não responsivos');
verificar(css.includes('#agenda-calendario .agenda-dia-botao'), 'Calendário mobile não ajustado');
verificar((css.match(/\{/g) || []).length === (css.match(/\}/g) || []).length,
    'Chaves CSS desbalanceadas');
verificar(ler('agenda.php').includes('action="salvar-agendamento.php"'),
    'Endpoint de agendamentos alterado sem necessidade');


/* UI-07: smoke test do cabeçalho compartilhado (sem renderização no navegador). */
const paginasAutenticadas = paginas.filter(pagina => pagina !== 'index.php');
const menuConta = ler('lib/menu-conta.php');
verificar(ler('scripts/preparar_publicacao.py').includes('"lib": {".php"}'),
    'Componente PHP fora dos diretórios incluídos no pacote publicado');
verificar(menuConta.includes("$_SESSION['nome_estabelecimento']"),
    'Cabeçalho não utiliza a identificação da sessão autenticada');
verificar(menuConta.includes('htmlspecialchars('), 'Nome da conta não recebe escape HTML');
verificar(menuConta.includes('href="config.php"') && menuConta.includes('href="logout.php"'),
    'Dropdown não contém Configurações e Sair');
verificar(!menuConta.includes('href="perfil.php"') && !menuConta.includes('href="alterar-senha.php"'),
    'Atalhos de Perfil e Alterar senha não devem estar no dropdown');
for (const pagina of paginasAutenticadas) {
    const html = ler(pagina);
    verificar(html.includes('href="css/responsivo.css?v=20261118-ui08"'),
        pagina + ': CSS do cabeçalho sem versão para invalidar cache');
    const inicio = html.indexOf('<header class="navbar');
    const fim = html.indexOf('</header>', inicio);
    const cabecalho = html.slice(inicio, fim);
    verificar(inicio !== -1 && fim > inicio, pagina + ': cabeçalho ausente');
    verificar(cabecalho.includes("require __DIR__ . '/lib/menu-conta.php'"),
        pagina + ': não usa o componente compartilhado');
    verificar(cabecalho.includes('class="ad-header-brand'),
        pagina + ': marca não está na estrutura centralizada');
    verificar(!cabecalho.includes('Barbearia'),
        pagina + ': nome fictício encontrado no cabeçalho');
}
verificar(/@media \(max-width: 767\.98px\)\s*\{[\s\S]*?\.agenday-app > header\.navbar \.ad-account-name\s*\{\s*display:\s*none;/m.test(css),
    'Nome do estabelecimento não é ocultado no cabeçalho mobile');
verificar(css.includes('grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr)'),
    'Cabeçalho não centraliza a marca em três colunas');
verificar(css.includes('.ad-account-dropdown .dropdown-item') && css.includes('min-height: 44px'),
    'Áreas de toque do dropdown não estão documentadas no CSS');

/* UI-08: duas abas, foto segregada, modal e fonte aplicados sem deploy. */
const config = ler('config.php');
const configJS = ler('js/configuracoes.js');
const configCSS = ler('css/configuracoes-v2.css');
const avatar = ler('lib/avatar-estabelecimento.php');
const perfil = ler('perfil.php');
const alterarSenha = ler('alterar-senha.php');
verificar((config.match(/role="tab"/g) || []).length === 2
    && (config.match(/role="tabpanel"/g) || []).length === 2,
    'UI-08: Configurações precisa ter exatamente duas abas exclusivas');
verificar(config.includes('Estabelecimento</button>') && config.includes('Minha Conta</button>'),
    'UI-08: nomes das duas abas incorretos');
verificar(!config.includes('>Empresa</a>') && !config.includes('>Perfil</a>'),
    'UI-08: navegação antiga de três abas reapareceu');
verificar(config.includes('name="nome_estabelecimento"')
    && config.includes('name="contato_estabelecimento"'),
    'UI-08: campos editáveis do estabelecimento ausentes');
verificar(config.includes('type="email"') && config.includes('readonly'),
    'UI-08: e-mail precisa permanecer somente leitura');
verificar(config.includes('enctype="multipart/form-data"')
    && config.includes('name="foto_conta"'),
    'UI-08: envio da foto de conta ausente');
verificar(config.includes('id="modalAlterarSenha"')
    && config.includes('action="alterar-senha.php"')
    && config.includes('data-bs-backdrop="static"')
    && config.includes('data-bs-keyboard="true"'),
    'UI-08: modal e processamento protegido da senha ausentes');
verificar(configJS.includes('aria-selected') && configJS.includes("event.key === 'ArrowRight'")
    && configJS.includes('hide.bs.modal') && configJS.includes('enviando'),
    'UI-08: teclado ou proteção contra fechamento durante POST ausentes');
verificar(configJS.includes('quantidade < 8') && configJS.includes('quantidade > 15')
    && configJS.includes('digitos.length === 11'),
    'UI-08: validação e formatação telefônica ausentes');
verificar(perfil.includes("Location: config.php?aba=conta")
    && alterarSenha.includes("Location: config.php?aba=conta"),
    'UI-08: links legados de Perfil ou Alterar senha perderam compatibilidade');
verificar(alterarSenha.includes("agenday_senha_validar_formulario($_POST)")
    && alterarSenha.includes('password_verify(')
    && alterarSenha.includes('password_hash(')
    && alterarSenha.includes('agenday_valida_csrf()'),
    'UI-08: fluxo seguro de senha foi alterado indevidamente');
verificar(menuConta.includes('agenday_avatar_url(') && avatar.includes("agenday_avatar_caminho")
    && avatar.includes('is_uploaded_file('),
    'UI-08: avatar não tem isolamento por ID ou validação de upload');
verificar(ler('scripts/preparar_publicacao.py').includes('"estabelecimentos"')
    && ler('scripts/publicar_ftp.py').includes('arquivos/estabelecimentos/.htaccess')
    && ler('arquivos/estabelecimentos/.htaccess').includes('Require all denied'),
    'UI-08: armazenamento da foto sem proteção ou vazando uploads no deploy');
verificar(css.includes('Space+Grotesk:wght@500')
    && css.includes('font-family: "Space Grotesk"')
    && css.includes('font-weight: 500 !important;'),
    'UI-08: assinatura AgenDay sem Space Grotesk Medium');
verificar(!configCSS.includes('font-family: "Space Grotesk"'),
    'UI-08: fonte da marca não deve afetar formulários');
verificar(configCSS.includes('@media (max-width: 575.98px)')
    && configCSS.includes('.ag-settings-tabs .nav-link')
    && configCSS.includes('max-height: calc(100dvh - 1.5rem)'),
    'UI-08: configurações e modal sem estilos mobile');

console.log('OK: ' + verificacoes + ' verificações estruturais de responsividade, cabeçalho e Configurações.');
