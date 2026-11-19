<?php
// Um único menu hambúrguer para as páginas administrativas autenticadas.
// As rotas de navegação são fixas; a página ativa depende apenas do script atual.
$paginaMenuAtual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$menuPaginaAtiva = static fn(string $arquivo): bool => $paginaMenuAtual === $arquivo;
?>
<div class="offcanvas offcanvas-start ad-sidebar" tabindex="-1" id="offcanvasDarkNavbar"
     aria-labelledby="ad-sidebar-title">
    <div class="offcanvas-header ad-sidebar-header">
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="Fechar menu"></button>
        <h5 class="offcanvas-title" id="ad-sidebar-title">Menu</h5>
    </div>

    <div class="offcanvas-body ad-sidebar-body">
        <nav class="ad-sidebar-nav" aria-label="Menu principal">
            <a class="nav-link ad-side-link<?= $menuPaginaAtiva('agenda.php') ? ' active' : '' ?>"
               href="agenda.php"<?= $menuPaginaAtiva('agenda.php') ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                    <path d="M7 3v4M17 3v4M3 10h18M7.5 14h2M14.5 14h2M7.5 18h2"></path>
                </svg>
                <span>Agenda</span>
            </a>
            <a class="nav-link ad-side-link<?= $menuPaginaAtiva('cad-cliente.php') ? ' active' : '' ?>"
               href="cad-cliente.php"<?= $menuPaginaAtiva('cad-cliente.php') ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <circle cx="9" cy="8" r="3"></circle>
                    <path d="M3 20v-2a6 6 0 0 1 12 0v2H3Z"></path>
                    <path d="M17 5a3 3 0 0 1 0 6M18 15a5 5 0 0 1 3 5"></path>
                </svg>
                <span>Clientes</span>
            </a>
            <a class="nav-link ad-side-link<?= $menuPaginaAtiva('cad.servicos.php') ? ' active' : '' ?>"
               href="cad.servicos.php"<?= $menuPaginaAtiva('cad.servicos.php') ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <rect x="3" y="5" width="18" height="14" rx="2"></rect>
                    <circle cx="8" cy="10" r="1.3"></circle>
                    <path d="M12 9.5h6M12 13h6M6 16h12"></path>
                </svg>
                <span>Serviços</span>
            </a>
            <a class="nav-link ad-side-link<?= $menuPaginaAtiva('cad-funcionario.php') ? ' active' : '' ?>"
               href="cad-funcionario.php"<?= $menuPaginaAtiva('cad-funcionario.php') ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <rect x="4" y="3" width="16" height="18" rx="2"></rect>
                    <circle cx="12" cy="10" r="3"></circle>
                    <path d="M7.5 18a4.5 4.5 0 0 1 9 0"></path>
                </svg>
                <span>Funcionários</span>
            </a>
            <a class="nav-link ad-side-link<?= $menuPaginaAtiva('config.php') ? ' active' : '' ?>"
               href="config.php"<?= $menuPaginaAtiva('config.php') ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path d="M10 2h4l.6 2.4a8 8 0 0 1 1.8.8l2.1-1.3 2.8 2.8L20 8.8a8 8 0 0 1 .8 1.8L23 11v4l-2.2.4a8 8 0 0 1-.8 1.8l1.3 2.1-2.8 2.8-2.1-1.3a8 8 0 0 1-1.8.8L14 24h-4l-.6-2.4a8 8 0 0 1-1.8-.8l-2.1 1.3-2.8-2.8L4 17.2a8 8 0 0 1-.8-1.8L1 15v-4l2.2-.4A8 8 0 0 1 4 8.8L2.7 6.7l2.8-2.8 2.1 1.3a8 8 0 0 1 1.8-.8L10 2Z"
                          transform="translate(1 1) scale(.88)"></path>
                    <circle cx="12" cy="12" r="3.2"></circle>
                </svg>
                <span>Configurações</span>
            </a>
        </nav>
    </div>

    <div class="ad-sidebar-footer">
        <a class="nav-link ad-side-logout" href="logout.php">
            <img src="img/icon-sair.svg" alt="" aria-hidden="true" width="24" height="24">
            <span>Sair</span>
        </a>
    </div>
</div>
