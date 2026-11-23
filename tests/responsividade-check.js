// Smoke checks estruturais da responsividade (sem navegador/MySQL).
// Execução: node tests/responsividade-check.js
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const raiz = path.join(__dirname, '..');
const ler = arquivo => fs.readFileSync(path.join(raiz, arquivo), 'utf8');
const paginas = [
    'index.php', 'agenda.php', 'agendar.php', 'cad-cliente.php', 'cad-funcionario.php',
    'cad.servicos.php', 'comandas-abertas.php', 'financeiro.php',
    'historico-comandas.php', 'config.php', 'primeiros-passos.php'
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
verificar(ler('agenda.php').includes('href="agendar.php"'),
    'AGD-01: agenda precisa levar à tela de agendamento');


/* UI-07: smoke test do cabeçalho compartilhado (sem renderização no navegador). */
const paginasAutenticadas = paginas.filter(pagina => pagina !== 'index.php');
verificar(!fs.existsSync(path.join(raiz, 'lib/menu-conta.php')),
    'UI-10: componente exclusivo do dropdown ainda existe');
for (const pagina of paginasAutenticadas) {
    const html = ler(pagina);
    verificar(html.includes('href="css/responsivo.css?v=20261120-del01"'),
        pagina + ': CSS do cabeçalho sem versão para invalidar cache');
    const inicio = html.indexOf('<header class="navbar');
    const fim = html.indexOf('</header>', inicio);
    const cabecalho = html.slice(inicio, fim);
    verificar(inicio !== -1 && fim > inicio, pagina + ': cabeçalho ausente');
    verificar(!cabecalho.includes('menu-conta.php')
        && cabecalho.includes("require __DIR__ . '/lib/menu-lateral.php'"),
        pagina + ': cabeçalho não foi simplificado ou perdeu o hambúrguer');
    verificar(cabecalho.includes('class="ad-header-brand'),
        pagina + ': marca não está na estrutura centralizada');
    verificar(!cabecalho.includes('Barbearia'),
        pagina + ': nome fictício encontrado no cabeçalho');
}
verificar(css.includes('grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr)'),
    'Cabeçalho não centraliza a marca em três colunas');
verificar(!css.includes('ad-account-') && !css.includes('.navbar .dropdown'),
    'UI-10: CSS exclusivo do antigo dropdown ainda existe');

/* UI-08: abas (Estabelecimento, Horários, Minha Conta), foto, modal e fonte. */
const config = ler('config.php');
const configJS = ler('js/configuracoes.js');
const telefoneCelularJs = ler('js/telefone-celular.js');
const configCSS = ler('css/configuracoes-v2.css');
const avatar = ler('lib/avatar-estabelecimento.php');
const perfil = ler('perfil.php');
const alterarSenha = ler('alterar-senha.php');
const whatsappPlataforma = ler('whatsapp-plataforma.php');
verificar((config.match(/role="tab"/g) || []).length === 3
    && (config.match(/role="tabpanel"/g) || []).length === 3,
    'UI-08: Configurações precisa ter exatamente três abas exclusivas');
verificar(config.includes('Estabelecimento</button>') && config.includes('Horários</button>')
    && config.includes('Minha Conta</button>'),
    'UI-08: nomes das abas incorretos');
verificar(!config.includes('name="whatsapp_api_key"') && !config.includes('name="whatsapp_base_url"')
    && !config.includes('WhatsApp</button>'),
    'WA-01: credenciais do WhatsApp ainda aparecem na tela do estabelecimento');
verificar(whatsappPlataforma.includes('name="whatsapp_api_key"')
    && whatsappPlataforma.includes('name="chave_confirmacao"')
    && whatsappPlataforma.includes('hash_equals($chaveAtual, $chaveConfirmada)'),
    'WA-01: configuração da plataforma sem confirmação da chave atual');
verificar(config.includes('name="exigir_codigo"'),
    'WA-01: exigência de código saiu da tela sem ir para o estabelecimento');
verificar(config.includes('name="slug"') && config.includes('id="slugEstabelecimento"'),
    'SLUG-01: endereço do portal editável na tela do estabelecimento');

/* -------------------------------- celular confirmado por código (TEL-01) */
const cadCliente = ler('cad-cliente.php');
const agendarPagina = ler('agendar.php');
const agendarJsTel = ler('js/agendar.js');
const confirmarTelefoneJs = ler('js/confirmar-telefone.js');
verificar(cadCliente.includes('data-confirmar-telefone')
    && cadCliente.includes('data-enviar-codigo')
    && cadCliente.includes('data-codigo-campo')
    && cadCliente.includes('name="sem_whatsapp"'),
    'TEL-01: cadastro de cliente sem confirmação do celular');
verificar(cadCliente.includes('js/confirmar-telefone.js'),
    'TEL-01: cadastro de cliente não carrega o script de confirmação');
verificar(agendarPagina.includes('data-confirmar-telefone')
    && agendarPagina.includes('js/confirmar-telefone.js'),
    'TEL-01: cliente rápido do agendamento sem confirmação do celular');
verificar(agendarJsTel.includes('sem_whatsapp'),
    'TEL-01: cliente rápido não envia a opção sem WhatsApp');
verificar(confirmarTelefoneJs.includes('api/painel-telefone.php')
    && confirmarTelefoneJs.includes('permitir_duplicado'),
    'TEL-01: script de confirmação não trata número repetido');
verificar(cadCliente.includes('js/telefone-celular.js')
    && agendarPagina.includes('js/telefone-celular.js')
    && config.includes('js/telefone-celular.js')
    && ler('index.php').includes('js/telefone-celular.js')
    && ler('cliente.php').includes('js/telefone-celular.js')
    && ler('cad-funcionario.php').includes('js/telefone-celular.js')
    && ler('primeiros-passos.php').includes('js/telefone-celular.js'),
    'TEL-02: telas de telefone sem a máscara de 11 dígitos');
verificar(config.includes('id="formFuncionamento"')
    && config.includes('name="dias[')
    && config.includes('name="regras[intervalo]"')
    && config.includes('name="regras[antecedencia]"'),
    'Horários: formulário de funcionamento ausente');
verificar(config.includes('id="formBloqueio"')
    && config.includes('id="bloqueioInicio"')
    && config.includes('id="bloqueioFim"')
    && config.includes('id="bloqueioMotivo"')
    && config.includes('id="bloqueioFuncionario"'),
    'BLQ-01: formulário de bloqueios ausente');
verificar(config.includes('name="acao" value="criar_bloqueio"')
    && config.includes('name="acao" value="excluir_bloqueio"'),
    'BLQ-01: ações de bloqueio ausentes');
verificar(config.includes("require_once __DIR__ . '/lib/bloqueios.php'")
    && ler('lib/bloqueios.php').includes('function agenday_bloqueio_excluir(')
    && ler('lib/bloqueios.php').includes('WHERE id_estabelecimento = ? AND id_bloqueio = ?'),
    'BLQ-01: exclusão de bloqueio sem filtro por estabelecimento');
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
verificar(telefoneCelularJs.includes('MAX_DIGITOS = 11')
    && telefoneCelularJs.includes('slice(0, MAX_DIGITOS)')
    && telefoneCelularJs.includes('setCustomValidity')
    && config.includes('js/telefone-celular.js'),
    'UI-08: máscara de 11 dígitos ausente');
verificar(perfil.includes("Location: config.php?aba=conta")
    && alterarSenha.includes("Location: config.php?aba=conta"),
    'UI-08: links legados de Perfil ou Alterar senha perderam compatibilidade');
verificar(alterarSenha.includes("agenday_senha_validar_formulario($_POST)")
    && alterarSenha.includes('password_verify(')
    && alterarSenha.includes('password_hash(')
    && alterarSenha.includes('agenday_valida_csrf()'),
    'UI-08: fluxo seguro de senha foi alterado indevidamente');
verificar(avatar.includes('agenday_avatar_url(') && avatar.includes("agenday_avatar_caminho")
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


/* UI-09: um menu hambúrguer único, com identidade aprovada e logout no rodapé. */
const menuLateral = ler('lib/menu-lateral.php');
const linksMenu = [...menuLateral.matchAll(/href="([^"]+)"/g)].map(resultado => resultado[1]);
const rotasEsperadas = [
    'agenda.php', 'cad-cliente.php', 'cad.servicos.php',
    'cad-funcionario.php', 'config.php', 'logout.php'
];
verificar(linksMenu.length === rotasEsperadas.length
    && linksMenu.every((rota, posicao) => rota === rotasEsperadas[posicao]),
    'UI-09: menu deve ter Agenda, cadastros, Configurações e Sair, na ordem aprovada');
verificar(menuLateral.includes('id="ad-sidebar-title">Menu</h5>')
    && !menuLateral.includes('id="ad-sidebar-title">AgenDay</h5>'),
    'UI-09: título interno do menu não é Menu');
verificar(menuLateral.includes('id="offcanvasDarkNavbar"')
    && menuLateral.includes('aria-labelledby="ad-sidebar-title"')
    && menuLateral.indexOf('class="btn-close"') < menuLateral.indexOf('id="ad-sidebar-title"'),
    'UI-09: controle de fechamento e título do menu estão fora de ordem');
verificar(menuLateral.includes('class="ad-sidebar-footer"')
    && menuLateral.indexOf('class="ad-sidebar-footer"') > menuLateral.indexOf('class="offcanvas-body ad-sidebar-body"')
    && menuLateral.includes('class="nav-link ad-side-logout"'),
    'UI-09: Sair não está fixado na seção final do menu');
verificar(menuLateral.includes("$_SERVER['SCRIPT_NAME']")
    && menuLateral.includes("' aria-current=\"page\"'"),
    'UI-09: identificação semântica da página ativa ausente');
verificar(!menuLateral.includes('>Cadastrar</') && !menuLateral.includes('>Comandas</')
    && !menuLateral.includes('>Financeiro</'),
    'UI-09: agrupamentos antigos voltaram ao menu aprovado');
verificar(css.includes('#offcanvasDarkNavbar .ad-sidebar-footer')
    && css.includes('#offcanvasDarkNavbar .ad-side-link')
    && css.includes('env(safe-area-inset-bottom)')
    && css.includes('#offcanvasDarkNavbar .ad-side-link.active'),
    'UI-09: estilos de item ativo/rodapé responsivo ausentes');
for (const pagina of paginasAutenticadas) {
    const html = ler(pagina);
    const headStart = html.indexOf('<header class="navbar');
    const headEnd = html.indexOf('</header>', headStart);
    const header = html.slice(headStart, headEnd);
    verificar(header.includes("require __DIR__ . '/lib/menu-lateral.php'")
        && !header.includes('lib/menu-conta.php'),
        pagina + ': menu lateral ausente ou dropdown da conta reapareceu');
    verificar(!header.includes('id="offcanvasDarkNavbar"')
        && !header.includes('>Cadastrar</') && !header.includes('>Comandas</'),
        pagina + ': menu antigo ainda duplicado na página');
}


/* UI-10: cabeçalho sem menu da conta, com marca centrada e sidebar intacta. */
for (const pagina of paginasAutenticadas) {
    const html = ler(pagina);
    const start = html.indexOf('<header class="navbar');
    const end = html.indexOf('</header>', start);
    const header = html.slice(start, end);
    verificar(start >= 0 && end > start
        && !header.includes('menu-conta.php')
        && !header.includes('ad-header-account')
        && !header.includes('ad-account-avatar')
        && !header.includes('dropdown-toggle')
        && header.includes('class="ad-header-brand')
        && header.includes('data-bs-target="#offcanvasDarkNavbar"'),
        'UI-10: cabeçalho contém dropdown ou perdeu menu/marca em ' + pagina);
    verificar((html.match(/lib\/menu-lateral\.php/g) || []).length === 1,
        'UI-10: menu hambúrguer ausente ou duplicado em ' + pagina);
}
verificar(!fs.existsSync(path.join(raiz, 'lib/menu-conta.php')),
    'UI-10: antigo componente de dropdown foi mantido');
verificar(!css.includes('ad-account-') && !css.includes('header.navbar .dropdown'),
    'UI-10: estilos exclusivos da conta ainda existem');
verificar(css.includes('grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr)')
    && css.includes('grid-column: 2;')
    && css.includes('font-family: "Space Grotesk"'),
    'UI-10: marca perdeu centralização ou tipografia');
verificar(menuLateral.includes('href="config.php"')
    && menuLateral.includes('href="logout.php"'),
    'UI-10: Configurações/Sair não podem desaparecer do hambúrguer');


/* FIX-01: checagens de integração do formulário e teste PHP no CI. */
for (const [pagina, endpoint, tipo] of [
    ['cad-cliente.php', 'cadastro-clientes.php', 'clientes'],
    ['cad-funcionario.php', 'cadastro-funcionario.php', 'funcionarios'],
]) {
    const form = ler(pagina);
    const api = ler(endpoint);
    verificar(form.includes('data-agenday-cadastro')
        && form.includes('name="cadastro_token"')
        && form.includes("agenday_cadastro_token_emitir('" + tipo + "')")
        && form.includes('js/cadastro-envio.js'),
        'FIX-01: formulário sem token único/bloqueio JS: ' + pagina);
    verificar(api.includes('agenday_cadastro_token_consumir(\'' + tipo + '\'')
        && api.indexOf('agenday_cadastro_token_consumir(') < api.indexOf('INSERT INTO'),
        'FIX-01: PHP não consome token antes de inserir: ' + endpoint);
}
const bloqueioEnvio = ler('js/cadastro-envio.js');
verificar(bloqueioEnvio.includes("form.addEventListener('submit'")
    && bloqueioEnvio.includes('evento.preventDefault()')
    && bloqueioEnvio.includes('botao.disabled = true')
    && bloqueioEnvio.includes("'Salvando...'"),
    'FIX-01: bloqueio de duplo clique ausente');

/* ATT-01: comparecimento, faltou e fechamento do dia. */
const comparecimento = ler('lib/comparecimento.php');
const calendarioJs = ler('js/calendario.js');
verificar(comparecimento.includes('function agenday_comparecimento_fechar(')
    && comparecimento.includes('function agenday_comparecimento_marcar(')
    && comparecimento.includes('function agenday_comparecimento_sugere_falta('),
    'ATT-01: funções de comparecimento ausentes');
verificar(comparecimento.includes("'chegou'") && comparecimento.includes("'faltou'")
    && comparecimento.includes("'concluir'") && comparecimento.includes("'em_atendimento'"),
    'ATT-01: ações ou status novos incompletos');
verificar(ler('api/comparecimento.php').includes('agenday_valida_csrf()')
    && ler('api/comparecimento.php').includes("$_SESSION['id_estabelecimento']"),
    'ATT-01: endpoint sem CSRF ou sem isolamento');
verificar(calendarioJs.includes('em_atendimento') && calendarioJs.includes('faltou')
    && calendarioJs.includes('sugere_faltou') && calendarioJs.includes('dropdown-item'),
    'ATT-01: agenda sem os novos status ou sem as ações');
verificar(!/form-novo-agendamento input\[name="csrf_token"\]/.test(calendarioJs),
    'ATT-01: calendário ainda depende do formulário antigo para o CSRF');

/* AGD-01: tela de agendamento com horários livres. */
const agendar = ler('agendar.php');
const agendarJs = ler('js/agendar.js');
verificar(agendar.includes('action="criar-agendamento.php"')
    && agendar.includes('name="csrf_token"')
    && agendar.includes('id="campoClienteId"')
    && agendar.includes('id="camposSessoes"')
    && agendar.includes('id="campoModo"'),
    'AGD-01: formulário de agendamento incompleto');
verificar(agendar.includes('form="formAgendar"'),
    'AGD-01: botão do rodapé precisa estar ligado ao formulário');
verificar(agendarJs.includes("fetch('api/horarios.php")
    && agendarJs.includes("fetch('api/clientes-busca.php")
    && agendarJs.includes('AbortController'),
    'AGD-01: tela não consulta a API de horários com cancelamento');
verificar(ler('lib/disponibilidade.php').includes('function agenday_disponibilidade_dia(')
    && ler('api/horarios.php').includes('agenday_disponibilidade_dia('),
    'AGD-01: cálculo de disponibilidade ausente');
verificar(ler('lib/disponibilidade.php').includes("status IN ('agendado', 'confirmado')"),
    'AGD-01: disponibilidade precisa ignorar agendamentos cancelados');
verificar(ler('criar-agendamento.php').includes('agenday_valida_csrf()')
    && ler('criar-agendamento.php').includes('FOR UPDATE'),
    'AGD-01: gravação sem CSRF ou sem lock por funcionário');

/* DEL-01: exclusão de cliente, funcionário e serviço com confirmação. */
for (const [pagina, endpoint, modal, campoId] of [
    ['cad-cliente.php', 'excluir-cliente.php', '#modalExcluirCliente', 'id_cliente'],
    ['cad-funcionario.php', 'excluir-funcionario.php', '#modalExcluirFuncionario', 'id_funcionario'],
    ['cad.servicos.php', 'excluir-servico.php', '#modalExcluirServico', 'id_servicos'],
]) {
    const lista = ler(pagina);
    const api = ler(endpoint);
    verificar(lista.includes('data-bs-target="' + modal + '"')
        && lista.includes('bi-trash')
        && lista.includes('ag-acoes-registro'),
        'DEL-01: botão de excluir ausente ou sem empilhamento: ' + pagina);
    verificar(lista.includes('data-excluir-id')
        && lista.includes('data-excluir-nome')
        && lista.includes('js/excluir-registro.js'),
        'DEL-01: confirmação de exclusão ausente: ' + pagina);
    verificar(api.includes("($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'")
        && api.includes('agenday_valida_csrf()')
        && api.includes("filter_input(INPUT_POST, '" + campoId + "'")
        && api.includes('DELETE FROM')
        && api.includes('$_SESSION[\'id_estabelecimento\']'),
        'DEL-01: endpoint sem POST/CSRF/isolamento: ' + endpoint);
    verificar(api.includes('possui agendamentos registrados'),
        'DEL-01: endpoint não protege histórico de agendamentos: ' + endpoint);
}

/* CAD-01: toda conta nova nasce com os 7 dias de horário gravados. */
const libHorario = ler('lib/horario-funcionamento.php');
const cadastroNormal = ler('cadastrar.php');
verificar(libHorario.includes('function agenday_horario_criar_padrao(')
    && libHorario.includes('INSERT IGNORE INTO horario_funcionamento'),
    'CAD-01: biblioteca sem a criação do horário padrão');
verificar(cadastroNormal.includes('agenday_horario_criar_padrao($conexao, $novoId, $diasFuncionamento)'),
    'CAD-01: cadastro comum não cria o horário informado');
verificar(cadastroNormal.includes('$diasMarcados'),
    'CAD-01: cadastro comum não lê os dias escolhidos');
verificar(ler('index.php').includes('name="dias[]"')
    && ler('index.php').includes('name="abertura"')
    && ler('index.php').includes('name="fechamento"'),
    'CAD-01: formulário de cadastro sem os campos de horário');
verificar(ler('google-cadastro-concluir.php').includes('agenday_horario_criar_padrao($conexao, $id)'),
    'CAD-01: cadastro via Google não cria o horário padrão');

if (process.env.GITHUB_ACTIONS === 'true') {
    // A runner de CI possui PHP; o Windows remoto não precisa ter PHP instalado.
    const { spawnSync } = require('node:child_process');
    const testePhp = spawnSync('php', ['tests/cadastro-reenvio.php'], {
        cwd: raiz, encoding: 'utf8', timeout: 15000
    });
    verificar(testePhp.status === 0,
        'FIX-01: teste PHP falhou: ' + (testePhp.stderr || testePhp.error?.message || ''));
    console.log((testePhp.stdout || '').trim());
}

console.log('OK: ' + verificacoes + ' verificações estruturais de responsividade, cabeçalho e Configurações.');
