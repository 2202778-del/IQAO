<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in
if (isset($_SESSION['user_id'])) {
    redirect(APP_URL . '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare("
            SELECT u.*, d.name AS department_name, d.code AS department_code, dv.name AS division_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN divisions dv ON u.division_id = dv.id
            WHERE u.email = ? AND u.is_active = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']        = $user['id'];
            $_SESSION['user_name']      = $user['name'];
            $_SESSION['user_email']     = $user['email'];
            $_SESSION['user_role']      = $user['role'];
            $_SESSION['user_dept_id']   = $user['department_id'];
            $_SESSION['user_div_id']    = $user['division_id'];
            $_SESSION['login_time']     = time();

            logAudit($pdo, $user['id'], 'LOGIN', 'Successful login');

            $redirect = $_GET['redirect'] ?? APP_URL . '/dashboard.php';
            redirect($redirect);
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    } else {
        $error = 'Please enter both email and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login &mdash; <?= APP_FULL_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --primary:#1a3a5c; --accent:#f0a500; }
body { background: linear-gradient(135deg, #1a3a5c 0%, #254d7a 50%, #1a3a5c 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.login-card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.3); overflow: hidden; max-width: 440px; width: 100%; }
.login-header { background: var(--primary); padding: 2.5rem; text-align: center; color: #fff; }
.login-header .logo-icon { font-size: 3rem; color: var(--accent); margin-bottom: .5rem; }
.login-header h2 { margin: 0; font-size: 1.4rem; font-weight: 700; letter-spacing: .5px; }
.login-header p { margin: .5rem 0 0; opacity: .8; font-size: .9rem; }
.login-body { padding: 2.5rem; }
.form-control { padding: .75rem 1rem; border-radius: 8px; }
.form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 .2rem rgba(26,58,92,.15); }
.btn-login { background: var(--primary); border: none; padding: .85rem; font-size: 1rem; font-weight: 600; border-radius: 8px; width: 100%; transition: .2s; }
.btn-login:hover { background: #254d7a; }
.input-group-text { background: #f8f9fa; border-radius: 0 8px 8px 0 !important; }
.login-footer { text-align: center; padding: 1rem 2.5rem 2rem; font-size: .8rem; color: #888; }
</style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <div class="logo-icon"><i class="fas fa-chart-line"></i></div>
        <h2><?= APP_FULL_NAME ?></h2>
        <p><?= e(UNIVERSITY_NAME) ?> &mdash; Quality Assurance Office</p>
    </div>
    <div class="login-body">
        <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="fas fa-exclamation-circle"></i>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-semibold">University Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="yourname@university.edu"
                           value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                    <input type="password" name="password" id="passwordInput" class="form-control" placeholder="Enter your password" required>
                    <button type="button" class="input-group-text border-start-0" onclick="togglePwd()">
                        <i class="fas fa-eye text-muted" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-login text-white">
                <i class="fas fa-sign-in-alt me-2"></i>Sign In
            </button>
        </form>
    </div>
    <div class="login-footer">
        &copy; <?= date('Y') ?> <?= e(UNIVERSITY_NAME) ?> &mdash; TPMS v1.0<br>
        <small>Access is restricted to authorized university personnel.</small>
    </div>
</div>
<script>
function togglePwd() {
    const inp = document.getElementById('passwordInput');
    const ico = document.getElementById('eyeIcon');
    if (inp.type === 'password') { inp.type = 'text'; ico.className = 'fas fa-eye-slash text-muted'; }
    else { inp.type = 'password'; ico.className = 'fas fa-eye text-muted'; }
}
</script>
</body>
</html>
