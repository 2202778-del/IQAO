<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth([ROLE_IQAO]);

$userId = (int)($_GET['id'] ?? 0);
$isEdit = $userId > 0;
$errors = [];

$editUser = null;
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$userId]);
    $editUser = $stmt->fetch();
    if (!$editUser) { flash('error', 'User not found.', 'danger'); redirect(APP_URL . '/admin/users.php'); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $role     = trim($_POST['role'] ?? '');
    $deptId   = (int)($_POST['department_id'] ?? 0) ?: null;
    $divId    = (int)($_POST['division_id'] ?? 0) ?: null;
    $password = trim($_POST['password'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (!$name) $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!in_array($role, [ROLE_PO, ROLE_DC, ROLE_IQAO, ROLE_PRESIDENT])) $errors[] = 'Invalid role.';
    if (!$isEdit && !$password) $errors[] = 'Password is required for new users.';
    if ($password && strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';

    // Check email uniqueness
    $dupStmt = $pdo->prepare("SELECT id FROM users WHERE email=? AND id != ?");
    $dupStmt->execute([$email, $userId ?: 0]);
    if ($dupStmt->fetch()) $errors[] = 'Email already exists.';

    if (empty($errors)) {
        if ($isEdit) {
            $params = [$name, $email, $role, $deptId, $divId, $isActive];
            $sql = "UPDATE users SET name=?, email=?, role=?, department_id=?, division_id=?, is_active=?";
            if ($password) { $sql .= ", password=?"; $params[] = password_hash($password, PASSWORD_DEFAULT); }
            $sql .= " WHERE id=?"; $params[] = $userId;
            $pdo->prepare($sql)->execute($params);
            logAudit($pdo, $_SESSION['user_id'], 'USER_UPDATED', "User #{$userId} updated.");
            flash('success', 'User updated successfully.');
        } else {
            $pdo->prepare("INSERT INTO users (name, email, password, role, department_id, division_id, is_active) VALUES (?,?,?,?,?,?,?)")
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $deptId, $divId, $isActive]);
            logAudit($pdo, $_SESSION['user_id'], 'USER_CREATED', "New user created: $email");
            flash('success', 'User created successfully.');
        }
        redirect(APP_URL . '/admin/users.php');
    }
}

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$divisions   = $pdo->query("SELECT * FROM divisions ORDER BY name")->fetchAll();

$pageTitle = $isEdit ? 'Edit User' : 'Add User';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <h4><i class="fas fa-user-<?= $isEdit ? 'edit' : 'plus' ?> me-2 text-primary"></i><?= $isEdit ? 'Edit User' : 'Add New User' ?></h4>
        <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div class="form-card" style="max-width:700px">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="form-section-title">Account Information</div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($editUser['name'] ?? $_POST['name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">University Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= e($editUser['email'] ?? $_POST['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Password <?= $isEdit ? '(leave blank to keep)' : '*' ?></label>
                    <input type="password" name="password" class="form-control" <?= !$isEdit ? 'required' : '' ?> minlength="8"
                           placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Minimum 8 characters' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                    <select name="role" class="form-select" id="roleSelect" required onchange="updateFields()">
                        <option value="">Select role...</option>
                        <option value="process_owner" <?= ($editUser['role']??'')===ROLE_PO?'selected':'' ?>>Process Owner</option>
                        <option value="division_chief" <?= ($editUser['role']??'')===ROLE_DC?'selected':'' ?>>Division Chief</option>
                        <option value="iqao" <?= ($editUser['role']??'')===ROLE_IQAO?'selected':'' ?>>IQAO (Admin)</option>
                        <option value="president" <?= ($editUser['role']??'')===ROLE_PRESIDENT?'selected':'' ?>>President</option>
                    </select>
                </div>
                <div class="col-md-6" id="deptField">
                    <label class="form-label fw-semibold">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" <?= ($editUser['department_id']??0)==$d['id']?'selected':'' ?>><?= e($d['name']) ?> (<?= e($d['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="divField">
                    <label class="form-label fw-semibold">Division</label>
                    <select name="division_id" class="form-select">
                        <option value="">None</option>
                        <?php foreach ($divisions as $dv): ?>
                        <option value="<?= $dv['id'] ?>" <?= ($editUser['division_id']??0)==$dv['id']?'selected':'' ?>><?= e($dv['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" id="isActive" class="form-check-input" value="1"
                               <?= ($editUser['is_active']??1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Active Account</label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> <?= $isEdit ? 'Update User' : 'Create User' ?>
                </button>
                <a href="<?= APP_URL ?>/admin/users.php" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php $extraJs = '<script>
function updateFields() {
    const role = document.getElementById("roleSelect").value;
    const dept = document.getElementById("deptField");
    const div = document.getElementById("divField");
    if (role === "process_owner") { dept.style.display="block"; div.style.display="block"; }
    else if (role === "division_chief") { dept.style.display="none"; div.style.display="block"; }
    else { dept.style.display="none"; div.style.display="none"; }
}
updateFields();
</script>'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
