<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth([ROLE_IQAO]);

$year = (int)($_GET['year'] ?? CURRENT_YEAR);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $types = ['objective_setting', 'evidence_upload', 'final_evaluation'];
    foreach ($types as $type) {
        $date = trim($_POST['deadline_' . $type] ?? '');
        if ($date) {
            $pdo->prepare("INSERT INTO deadlines (type, deadline_date, academic_year, set_by) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE deadline_date=?, set_by=?")
                ->execute([$type, $date, $year, $_SESSION['user_id'], $date, $_SESSION['user_id']]);
        }
    }
    logAudit($pdo, $_SESSION['user_id'], 'DEADLINES_SET', "Deadlines set for AY $year.");
    flash('success', 'Deadlines saved successfully.');
    redirect(APP_URL . '/admin/settings.php?year=' . $year);
}

// Load current deadlines
$dlStmt = $pdo->prepare("SELECT * FROM deadlines WHERE academic_year=?");
$dlStmt->execute([$year]);
$deadlineMap = [];
foreach ($dlStmt->fetchAll() as $dl) {
    $deadlineMap[$dl['type']] = $dl['deadline_date'];
}

// Division & Department management
$divisions   = $pdo->query("SELECT * FROM divisions ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT d.*, dv.name AS div_name FROM departments d LEFT JOIN divisions dv ON d.division_id = dv.id ORDER BY d.name")->fetchAll();

$pageTitle = 'System Settings';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <h4><i class="fas fa-cog me-2 text-primary"></i>System Settings</h4>
    </div>

    <?php showFlash(); ?>

    <div class="row g-3">
        <!-- Deadlines -->
        <div class="col-lg-6">
            <div class="form-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="form-section-title mb-0">Academic Year Deadlines</div>
                    <select class="form-select form-select-sm" onchange="location.href='?year='+this.value" style="width:100px">
                        <?php for ($y = CURRENT_YEAR + 1; $y >= CURRENT_YEAR - 2; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-file-alt text-primary me-1"></i> Objective Setting Deadline
                        </label>
                        <input type="date" name="deadline_objective_setting" class="form-control"
                               value="<?= e($deadlineMap['objective_setting'] ?? '') ?>">
                        <small class="text-muted">Deadline for Process Owners to submit tactical plans.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-paperclip text-warning me-1"></i> Evidence Upload Deadline
                        </label>
                        <input type="date" name="deadline_evidence_upload" class="form-control"
                               value="<?= e($deadlineMap['evidence_upload'] ?? '') ?>">
                        <small class="text-muted">Deadline for uploading evidence of accomplished objectives.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            <i class="fas fa-clipboard-check text-success me-1"></i> Final Evaluation Deadline
                        </label>
                        <input type="date" name="deadline_final_evaluation" class="form-control"
                               value="<?= e($deadlineMap['final_evaluation'] ?? '') ?>">
                        <small class="text-muted">Deadline for submitting end-of-year evaluations.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Deadlines
                    </button>
                </form>
            </div>
        </div>

        <!-- Reminder Settings Info -->
        <div class="col-lg-6">
            <div class="form-card">
                <div class="form-section-title">Automated Reminder Schedule</div>
                <p class="text-muted small">The system automatically sends notifications and emails at the following intervals before each deadline:</p>
                <div class="d-flex flex-column gap-2 mt-3">
                    <div class="p-3 rounded bg-light d-flex align-items-center gap-3">
                        <div class="badge bg-danger" style="font-size:.9rem">7 days</div>
                        <div>
                            <div class="fw-semibold">First Reminder</div>
                            <div class="small text-muted">In-app notification + email sent to Process Owners</div>
                        </div>
                    </div>
                    <div class="p-3 rounded bg-light d-flex align-items-center gap-3">
                        <div class="badge bg-warning text-dark" style="font-size:.9rem">3 days</div>
                        <div>
                            <div class="fw-semibold">Second Reminder</div>
                            <div class="small text-muted">In-app notification + email sent to Process Owners</div>
                        </div>
                    </div>
                    <div class="p-3 rounded bg-light d-flex align-items-center gap-3">
                        <div class="badge bg-danger" style="font-size:.9rem">1 day</div>
                        <div>
                            <div class="fw-semibold">Final Reminder</div>
                            <div class="small text-muted">Urgent notification sent to Process Owners</div>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info mt-3 small">
                    <i class="fas fa-info-circle me-1"></i>
                    Reminders automatically stop for a Process Owner once they have submitted their required document.
                </div>
            </div>

            <!-- Send Test Reminder -->
            <div class="form-card mt-3">
                <div class="form-section-title">Send Deadline Reminders Now</div>
                <p class="text-muted small">Manually trigger deadline reminder notifications to all Process Owners who have not yet submitted.</p>
                <button class="btn btn-outline-primary" onclick="sendReminders()">
                    <i class="fas fa-bell me-1"></i> Send Reminders Now
                </button>
            </div>
        </div>

        <!-- Divisions -->
        <div class="col-lg-6">
            <div class="form-card">
                <div class="form-section-title">Divisions</div>
                <table class="table table-sm">
                    <thead><tr><th>Division Name</th><th>Departments</th></tr></thead>
                    <tbody>
                        <?php foreach ($divisions as $div): ?>
                        <tr>
                            <td><?= e($div['name']) ?></td>
                            <td><span class="badge bg-secondary"><?= count(array_filter($departments, fn($d) => $d['division_id'] == $div['id'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Departments -->
        <div class="col-lg-6">
            <div class="form-card">
                <div class="form-section-title">Departments</div>
                <table class="table table-sm">
                    <thead><tr><th>Department</th><th>Code</th><th>Division</th></tr></thead>
                    <tbody>
                        <?php foreach ($departments as $d): ?>
                        <tr>
                            <td><?= e($d['name']) ?></td>
                            <td><span class="badge bg-light text-dark"><?= e($d['code']) ?></span></td>
                            <td class="small text-muted"><?= e($d['div_name'] ?? '&mdash;') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $extraJs = '<script>
async function sendReminders() {
    const r = await TPMS.confirm("Send Reminders?", "This will send notifications to all Process Owners with pending submissions.", "Yes, Send", "#1a3a5c");
    if (!r.isConfirmed) return;
    const resp = await fetch("' . APP_URL . '/ajax/send_reminders.php", { method:"POST", body: new URLSearchParams({csrf_token:"' . csrfToken() . '"}) });
    const data = await resp.json();
    TPMS.toast(data.message || "Reminders sent!", "success");
}
</script>'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
