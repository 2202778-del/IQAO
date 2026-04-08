<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= APP_FULL_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php $user = currentUser($pdo); $unread = getUnreadCount($pdo, $_SESSION['user_id']); ?>

<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark tpms-navbar fixed-top">
    <div class="container-fluid">
        <button class="btn btn-sm text-white me-2" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/dashboard.php">
            <i class="fas fa-chart-line me-1"></i><?= APP_NAME ?>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <!-- Notifications Bell -->
            <div class="dropdown">
                <a href="#" class="text-white position-relative" data-bs-toggle="dropdown" id="notifBell">
                    <i class="fas fa-bell fa-lg"></i>
                    <span class="notif-badge d-none" id="notifCount">0</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown p-0" style="width:360px;min-height:80px;">
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                        <strong>Notifications</strong>
                        <a href="#" class="small text-muted" id="markAllRead">Mark all read</a>
                    </div>
                    <div id="notifList" style="max-height:320px;overflow-y:auto;">
                        <div class="text-center py-4 text-muted small">Loading...</div>
                    </div>
                    <div class="text-center py-2 border-top">
                        <a href="<?= APP_URL ?>/notifications/index.php" class="small">View all</a>
                    </div>
                </div>
            </div>
            <!-- User Menu -->
            <div class="dropdown">
                <a href="#" class="text-white d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
                    <div class="avatar-circle"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
                    <span class="d-none d-md-block"><?= e($user['name']) ?></span>
                    <i class="fas fa-chevron-down small"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header"><?= e(ucwords(str_replace('_', ' ', $user['role']))) ?></h6></li>
                    <?php if ($user['department_name']): ?>
                    <li><span class="dropdown-item-text small text-muted"><?= e($user['department_name']) ?></span></li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= APP_URL ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="wrapper">
