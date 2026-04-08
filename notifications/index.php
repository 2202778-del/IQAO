<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

// Mark all read on page visit
$pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

$pageTitle = 'Notifications';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <h4><i class="fas fa-bell me-2 text-primary"></i>Notifications</h4>
        <?php if (!empty($notifications)): ?>
        <button class="btn btn-outline-danger btn-sm" onclick="clearAll()">
            <i class="fas fa-trash me-1"></i> Clear All
        </button>
        <?php endif; ?>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if (empty($notifications)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-bell-slash fa-3x mb-3 opacity-25"></i>
                    <p>You have no notifications.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="card border-0 shadow-sm">
                <?php foreach ($notifications as $n): ?>
                <div class="p-3 border-bottom d-flex gap-3 <?= $n['is_read'] ? '' : 'bg-light' ?>">
                    <div class="flex-shrink-0 mt-1">
                        <div style="width:40px;height:40px;border-radius:50%;background:#e8edf7;display:flex;align-items:center;justify-content:center">
                            <i class="fas fa-bell text-primary"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold"><?= e($n['title']) ?></div>
                        <div class="text-muted"><?= e($n['message']) ?></div>
                        <div class="d-flex align-items-center gap-3 mt-1">
                            <small class="text-muted"><i class="fas fa-clock me-1"></i><?= date('F j, Y g:i A', strtotime($n['created_at'])) ?></small>
                            <?php if ($n['link']): ?>
                            <a href="<?= e($n['link']) ?>" class="small text-primary">View &rarr;</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!$n['is_read']): ?>
                    <div class="flex-shrink-0">
                        <span class="badge bg-primary rounded-pill">New</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $extraJs = '<script>
async function clearAll() {
    const r = await TPMS.confirm("Clear All?", "This will remove all your notifications.", "Yes, Clear", "#dc3545");
    if (!r.isConfirmed) return;
    const resp = await fetch("' . APP_URL . '/ajax/clear_notifications.php", {
        method:"POST",
        body: new URLSearchParams({csrf_token:"' . csrfToken() . '"})
    });
    const data = await resp.json();
    if (data.success) { TPMS.toast("Notifications cleared."); setTimeout(() => location.reload(), 800); }
}
</script>'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
