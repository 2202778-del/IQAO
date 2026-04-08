<?php
$currentPath = $_SERVER['PHP_SELF'];
function navItem($url, $icon, $label, $currentPath) {
    $active = (strpos($currentPath, $url) !== false) ? 'active' : '';
    return '<li class="nav-item">
        <a class="nav-link ' . $active . '" href="' . APP_URL . $url . '">
            <i class="fas fa-' . $icon . ' me-2"></i>' . $label . '
        </a>
    </li>';
}
$role = $_SESSION['user_role'];
?>
<nav id="sidebar" class="tpms-sidebar">
    <div class="sidebar-header">
        <div class="small text-muted text-uppercase fw-semibold px-3 py-2">Main Menu</div>
    </div>
    <ul class="nav flex-column">
        <?= navItem('/dashboard.php', 'tachometer-alt', 'Dashboard', $currentPath) ?>
        <?= navItem('/plans/index.php', 'file-alt', 'Tactical Plans', $currentPath) ?>

        <?php if ($role === ROLE_PO): ?>
            <?= navItem('/plans/create.php', 'plus-circle', 'Create New Plan', $currentPath) ?>
            <?= navItem('/monitoring/evidence.php', 'paperclip', 'Evidence Upload', $currentPath) ?>
            <?= navItem('/evaluation/index.php', 'clipboard-check', 'Evaluations', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === ROLE_IQAO): ?>
            <li class="nav-item mt-2">
                <div class="small text-muted text-uppercase fw-semibold px-3 py-1">Administration</div>
            </li>
            <?= navItem('/admin/users.php', 'users', 'User Management', $currentPath) ?>
            <?= navItem('/admin/settings.php', 'cog', 'System Settings', $currentPath) ?>
            <?= navItem('/monitoring/evidence.php', 'paperclip', 'Evidence Review', $currentPath) ?>
            <?= navItem('/evaluation/index.php', 'clipboard-check', 'Evaluations', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === ROLE_DC): ?>
            <?= navItem('/evaluation/index.php', 'clipboard-check', 'Evaluations', $currentPath) ?>
        <?php endif; ?>

        <?php if ($role === ROLE_PRESIDENT): ?>
            <?= navItem('/evaluation/index.php', 'clipboard-check', 'Evaluations', $currentPath) ?>
        <?php endif; ?>

        <li class="nav-item mt-2">
            <div class="small text-muted text-uppercase fw-semibold px-3 py-1">Account</div>
        </li>
        <?= navItem('/notifications/index.php', 'bell', 'Notifications', $currentPath) ?>
        <li class="nav-item">
            <a class="nav-link" href="<?= APP_URL ?>/logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </li>
    </ul>
</nav>
