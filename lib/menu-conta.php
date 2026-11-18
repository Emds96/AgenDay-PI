<?php
require_once __DIR__ . '/../verifica_login.php';
require_once __DIR__ . '/avatar-estabelecimento.php';
// Componente de conta para páginas que já passaram por verifica_login.php.
// O login e a edição de configurações mantêm o nome real na sessão da conta.
$nomeConta = $_SESSION['nome_estabelecimento'] ?? null;
$nomeConta = is_string($nomeConta) ? trim($nomeConta) : '';
$avatarContaUrl = agenday_avatar_url((int) $_SESSION['id_estabelecimento']);
$nomeContaSeguro = htmlspecialchars(
    $nomeConta !== '' ? $nomeConta : 'Nome indisponível',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);
?>
<div class="dropdown ad-header-account">
    <button class="btn dropdown-toggle ad-account-trigger" type="button"
            id="agendayAccountMenuButton" data-bs-toggle="dropdown"
            aria-expanded="false" aria-label="Opções da conta: <?= $nomeContaSeguro ?>">
        <span class="ad-account-avatar" aria-hidden="true">
            <?php if ($avatarContaUrl !== null): ?>
                <img src="<?= htmlspecialchars($avatarContaUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
                     alt="" width="30" height="30">
            <?php else: ?>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" focusable="false">
                    <circle cx="16" cy="16" r="15" fill="#E7F5F2" />
                    <circle cx="16" cy="12" r="5" fill="currentColor" />
                    <path d="M6.5 25c1.5-5.2 5-7.4 9.5-7.4s8 2.2 9.5 7.4" fill="currentColor" />
                </svg>
            <?php endif; ?>
        </span>
        <span class="ad-account-name"><?= $nomeContaSeguro ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end ad-account-dropdown"
        aria-labelledby="agendayAccountMenuButton">
        <li class="ad-account-identity">
            <span class="ad-account-identity-name"><?= $nomeContaSeguro ?></span>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item fw-bold" href="config.php">Configurações</a></li>
        <li><a class="dropdown-item fw-bold" href="logout.php">Sair</a></li>
    </ul>
</div>
