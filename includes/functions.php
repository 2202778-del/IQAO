<?php
// ============================================================
// Core Helper Functions
// ============================================================

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function flash($key, $message = null, $type = 'success') {
    if ($message !== null) {
        $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
    } elseif (isset($_SESSION['flash'][$key])) {
        $f = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $f;
    }
    return null;
}

function showFlash() {
    if (!empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $key => $f) {
            echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">
                ' . e($f['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>';
            unset($_SESSION['flash'][$key]);
        }
    }
}

function requireAuth($roles = []) {
    if (!isset($_SESSION['user_id'])) {
        redirect(APP_URL . '/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    }
    if (!empty($roles) && !in_array($_SESSION['user_role'], $roles)) {
        redirect(APP_URL . '/dashboard.php?error=access_denied');
    }
}

function isRole(...$roles) {
    return in_array($_SESSION['user_role'] ?? '', $roles);
}

function currentUser(PDO $pdo) {
    static $user = null;
    if ($user === null && isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("
            SELECT u.*, d.name AS department_name, d.code AS department_code, dv.name AS division_name
            FROM users u
            LEFT JOIN departments d ON u.department_id = d.id
            LEFT JOIN divisions dv ON u.division_id = dv.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    return $user;
}

function generateReferenceNo($departmentCode, $year) {
    global $pdo;
    $count = $pdo->query("SELECT COUNT(*) FROM tactical_plans WHERE academic_year = $year")->fetchColumn();
    return "TPMS-{$departmentCode}-{$year}-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

// ---- Notifications ----

function sendNotification(PDO $pdo, $userId, $title, $message, $link = '') {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $link]);
}

function getUnreadCount(PDO $pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// ---- Audit Log ----

function logAudit(PDO $pdo, $userId, $action, $details = '', $planId = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (tactical_plan_id, user_id, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$planId, $userId, $action, $details, $ip]);
}

// ---- Email (basic PHP mail wrapper) ----

function sendEmail($to, $toName, $subject, $bodyHtml) {
    if (!MAIL_ENABLED) return true;

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . MAIL_FROM . "\r\n";

    return mail($to, $subject, $bodyHtml, $headers);
}

function emailTemplate($subject, $bodyContent) {
    return '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
        body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
        .container{max-width:600px;margin:30px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1)}
        .header{background:#1a3a5c;color:#fff;padding:24px 32px}
        .header h2{margin:0;font-size:20px}
        .body{padding:32px}
        .btn{display:inline-block;background:#1a3a5c;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;margin-top:16px}
        .footer{background:#f0f0f0;text-align:center;padding:16px;font-size:12px;color:#888}
    </style></head><body>
    <div class="container">
        <div class="header"><h2>' . APP_FULL_NAME . '</h2></div>
        <div class="body">' . $bodyContent . '</div>
        <div class="footer">This is an automated message. Please do not reply directly to this email.</div>
    </div></body></html>';
}

// ---- Plan helpers ----

function getPlan(PDO $pdo, $planId) {
    $stmt = $pdo->prepare("
        SELECT tp.*, d.name AS dept_name, d.code AS dept_code,
               dv.name AS division_name,
               u.name AS created_by_name,
               dc_u.name AS dc_approved_by_name,
               iq_u.name AS iqao_approved_by_name,
               pr_u.name AS signed_by_name
        FROM tactical_plans tp
        LEFT JOIN departments d ON tp.department_id = d.id
        LEFT JOIN divisions dv ON d.division_id = dv.id
        LEFT JOIN users u ON tp.created_by = u.id
        LEFT JOIN users dc_u ON tp.dc_approved_by = dc_u.id
        LEFT JOIN users iq_u ON tp.iqao_approved_by = iq_u.id
        LEFT JOIN users pr_u ON tp.signed_by = pr_u.id
        WHERE tp.id = ?
    ");
    $stmt->execute([$planId]);
    return $stmt->fetch();
}

function getObjectives(PDO $pdo, $planId) {
    $stmt = $pdo->prepare("SELECT * FROM objectives WHERE tactical_plan_id = ? ORDER BY sort_order, id");
    $stmt->execute([$planId]);
    return $stmt->fetchAll();
}

function canEditPlan($plan, $userId, $role) {
    if ($role === ROLE_PO && $plan['created_by'] == $userId &&
        in_array($plan['status'], ['draft', 'returned_to_po'])) return true;
    if ($role === ROLE_DC && $plan['status'] === 'submitted') return true;
    if ($role === ROLE_IQAO && $plan['status'] === 'dc_approved') return true;
    return false;
}

function getPlanAccessible($plan, $userId, $userRole, $userDivId) {
    switch ($userRole) {
        case ROLE_PO:
            return (int)$plan['created_by'] === (int)$userId;
        case ROLE_DC:
            return (int)$plan['division_id_check'] === (int)$userDivId;
        case ROLE_IQAO:
        case ROLE_PRESIDENT:
            return true;
        default:
            return false;
    }
}

function statusBadge($status) {
    $labels = STATUS_LABELS;
    if (!isset($labels[$status])) return '<span class="badge bg-secondary">Unknown</span>';
    return '<span class="badge bg-' . $labels[$status]['class'] . '">' . e($labels[$status]['label']) . '</span>';
}

function objStatusBadge($status) {
    $labels = OBJ_STATUS_LABELS;
    if (!isset($labels[$status])) return '<span class="badge bg-secondary">Unknown</span>';
    return '<span class="badge bg-' . $labels[$status]['class'] . '">' . e($labels[$status]['label']) . '</span>';
}

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getDashboardStats(PDO $pdo, $userId, $role, $divisionId, $departmentId, $year) {
    $stats = ['accomplished' => 0, 'ongoing' => 0, 'not_accomplished' => 0, 'not_set' => 0];

    if ($role === ROLE_PO) {
        $stmt = $pdo->prepare("
            SELECT o.status, COUNT(*) as cnt
            FROM objectives o
            JOIN tactical_plans tp ON o.tactical_plan_id = tp.id
            WHERE tp.department_id = ? AND tp.academic_year = ? AND tp.status = 'signed'
            GROUP BY o.status
        ");
        $stmt->execute([$departmentId, $year]);
    } elseif ($role === ROLE_DC) {
        $stmt = $pdo->prepare("
            SELECT o.status, COUNT(*) as cnt
            FROM objectives o
            JOIN tactical_plans tp ON o.tactical_plan_id = tp.id
            JOIN departments d ON tp.department_id = d.id
            WHERE d.division_id = ? AND tp.academic_year = ? AND tp.status = 'signed'
            GROUP BY o.status
        ");
        $stmt->execute([$divisionId, $year]);
    } else {
        $stmt = $pdo->prepare("
            SELECT o.status, COUNT(*) as cnt
            FROM objectives o
            JOIN tactical_plans tp ON o.tactical_plan_id = tp.id
            WHERE tp.academic_year = ? AND tp.status = 'signed'
            GROUP BY o.status
        ");
        $stmt->execute([$year]);
    }

    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['status']] = (int)$row['cnt'];
    }
    return $stats;
}
