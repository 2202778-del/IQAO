<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth([ROLE_IQAO]);

// Handle quick actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($action === 'toggle_active' && $userId) {
        $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=?")->execute([$userId]);
        logAudit($pdo, $_SESSION['user_id'], 'USER_TOGGLED', "User #{$userId} active status toggled.");
        flash('success', 'User status updated.');
    }
    redirect(APP_URL . '/admin/users.php');
}

$users = $pdo->query("
    SELECT u.*, d.name AS dept_name, dv.name AS div_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    LEFT JOIN divisions dv ON u.division_id = dv.id
    ORDER BY u.role, u.name
")->fetchAll();

$roleLabels = [ROLE_PO=>'Process Owner',ROLE_DC=>'Division Chief',ROLE_IQAO=>'IQAO',ROLE_PRESIDENT=>'President'];

$pageTitle = 'User Management';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <h4><i class="fas fa-users me-2 text-primary"></i>User Management</h4>
        <a href="<?= APP_URL ?>/admin/user_form.php" class="btn btn-primary">
            <i class="fas fa-user-plus me-1"></i> Add User
        </a>
    </div>

    <?php showFlash(); ?>

    <div class="table-card">
        <div class="table-responsive">
            <table class="table datatable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Division</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar-circle" style="width:30px;height:30px;font-size:.75rem"><?= strtoupper(substr($u['name'],0,1)) ?></div>
                                <span class="fw-semibold"><?= e($u['name']) ?></span>
                            </div>
                        </td>
                        <td class="small"><?= e($u['email']) ?></td>
                        <td><span class="badge bg-primary"><?= e($roleLabels[$u['role']] ?? $u['role']) ?></span></td>
                        <td class="small"><?= e($u['dept_name'] ?? '&mdash;') ?></td>
                        <td class="small"><?= e($u['div_name'] ?? '&mdash;') ?></td>
                        <td>
                            <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= APP_URL ?>/admin/user_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary me-1">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                <input type="hidden" name="action" value="toggle_active">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                    onclick="return confirm('<?= $u['is_active'] ? 'Disable' : 'Enable' ?> this user?')">
                                    <i class="fas <?= $u['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
