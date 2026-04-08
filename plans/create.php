<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth([ROLE_PO]);

$user = currentUser($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $year       = (int)($_POST['academic_year'] ?? CURRENT_YEAR);
    $action     = $_POST['form_action'] ?? 'draft'; // 'draft' or 'submit'
    $status     = ($action === 'submit') ? 'submitted' : 'draft';

    // Check for duplicate draft/plan
    $dupCheck = $pdo->prepare("SELECT id FROM tactical_plans WHERE department_id = ? AND academic_year = ? AND created_by = ? AND status NOT IN ('signed')");
    $dupCheck->execute([$user['department_id'], $year, $user['id']]);
    if ($dupCheck->fetch()) {
        flash('error', 'You already have a tactical plan for ' . $year . '. Please edit the existing one.', 'warning');
        redirect(APP_URL . '/plans/index.php');
    }

    $refNo = generateReferenceNo($user['department_code'] ?? 'DEPT', $year);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tactical_plans (reference_no, department_id, created_by, academic_year, status, submitted_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $submittedAt = ($status === 'submitted') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$refNo, $user['department_id'], $user['id'], $year, $status, $submittedAt]);
        $planId = $pdo->lastInsertId();

        // Save objectives
        $objectives = $_POST['objectives'] ?? [];
        $sortOrder  = 0;
        foreach ($objectives as $obj) {
            if (empty(trim($obj['quality_objective'] ?? ''))) continue;
            $stmt2 = $pdo->prepare("
                INSERT INTO objectives (tactical_plan_id, quality_objective, success_indicator, target, timeline_q1, timeline_q2, timeline_q3, timeline_q4, person_responsible, budget, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt2->execute([
                $planId,
                trim($obj['quality_objective']),
                trim($obj['success_indicator'] ?? ''),
                trim($obj['target'] ?? ''),
                !empty($obj['q1']) ? 1 : 0,
                !empty($obj['q2']) ? 1 : 0,
                !empty($obj['q3']) ? 1 : 0,
                !empty($obj['q4']) ? 1 : 0,
                trim($obj['person_responsible'] ?? ''),
                (float)($obj['budget'] ?? 0),
                $sortOrder++,
            ]);
        }

        logAudit($pdo, $user['id'], 'CREATED', 'Tactical plan created. Ref: ' . $refNo, $planId);

        if ($status === 'submitted') {
            // Notify Division Chief
            $dcStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'division_chief' AND division_id = ? AND is_active = 1 LIMIT 1");
            $dcStmt->execute([$user['division_id']]);
            $dc = $dcStmt->fetch();
            if ($dc) {
                sendNotification($pdo, $dc['id'],
                    'Action Required: Plan Review',
                    $user['name'] . ' has submitted a tactical plan from ' . ($user['department_name'] ?? 'their department') . ' for your review.',
                    APP_URL . '/plans/view.php?id=' . $planId
                );
                $emailBody = emailTemplate('Action Required', "
                    <p>Dear {$dc['name']},</p>
                    <p>A Quality Objectives / Tactical Plan requires your review.</p>
                    <ul>
                        <li><strong>Reference:</strong> {$refNo}</li>
                        <li><strong>Department:</strong> {$user['department_name']}</li>
                        <li><strong>Submitted by:</strong> {$user['name']}</li>
                    </ul>
                    <a href='" . APP_URL . "/plans/view.php?id={$planId}' class='btn'>Review Document</a>
                ");
                sendEmail($dc['email'], $dc['name'], "Action Required: TPMS Review - {$user['department_name']}", $emailBody);
            }
            logAudit($pdo, $user['id'], 'SUBMITTED', 'Submitted to Division Chief', $planId);
        }

        $pdo->commit();
        flash('success', $status === 'submitted'
            ? 'Tactical plan submitted to Division Chief successfully.'
            : 'Tactical plan saved as draft.');
        redirect(APP_URL . '/plans/view.php?id=' . $planId);

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Error saving plan: ' . $e->getMessage(), 'danger');
        redirect(APP_URL . '/plans/create.php');
    }
}

$years = range(CURRENT_YEAR + 1, CURRENT_YEAR - 2, -1);
$pageTitle = 'Create Tactical Plan';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <h4><i class="fas fa-plus-circle me-2 text-primary"></i>Create New Tactical Plan</h4>
        <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back
        </a>
    </div>

    <?php showFlash(); ?>

    <form method="POST" id="planForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="form_action" id="formAction" value="draft">
        <input type="hidden" id="objCount" value="0">

        <div class="form-card mb-3">
            <div class="form-section-title">Plan Information</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Department</label>
                    <input type="text" class="form-control" value="<?= e($user['department_name'] ?? '') ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Division</label>
                    <input type="text" class="form-control" value="<?= e($user['division_name'] ?? '') ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                    <select name="academic_year" class="form-select" required>
                        <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $y == CURRENT_YEAR ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Prepared By</label>
                    <input type="text" class="form-control" value="<?= e($user['name']) ?>" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Designation / Position</label>
                    <input type="text" name="position" class="form-control" placeholder="e.g., Department Head">
                </div>
            </div>
        </div>

        <div class="form-card mb-3">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="form-section-title mb-0">Quality Objectives</div>
                <button type="button" class="btn btn-sm btn-accent" onclick="addObjectiveRow()">
                    <i class="fas fa-plus me-1"></i> Add Objective
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle" style="min-width:900px">
                    <thead>
                        <tr style="background:#1a3a5c;color:#fff">
                            <th style="width:40px">#</th>
                            <th>Quality Objective / Program Activity</th>
                            <th>Success Indicator / KPI</th>
                            <th>Target</th>
                            <th>Timeline</th>
                            <th>Person Responsible</th>
                            <th>Budget (₱)</th>
                            <th style="width:50px"></th>
                        </tr>
                    </thead>
                    <tbody id="objectivesTable">
                        <!-- Rows added dynamically -->
                    </tbody>
                </table>
            </div>
            <div class="text-center py-3 text-muted" id="noObjMsg">
                <i class="fas fa-info-circle me-1"></i> Click "Add Objective" to start adding quality objectives.
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="button" class="btn btn-outline-primary" onclick="saveDraft()">
                <i class="fas fa-save me-1"></i> Save as Draft
            </button>
            <button type="button" class="btn btn-primary" onclick="submitPlan()">
                <i class="fas fa-paper-plane me-1"></i> Submit to Division Chief
            </button>
        </div>
    </form>
</div>

<?php $extraJs = '<script>
// Auto-add first row
addObjectiveRow();
document.getElementById("noObjMsg").style.display = "none";

// Override addObjectiveRow to hide the hint
const _orig = addObjectiveRow;
window.addObjectiveRow = function() {
    _orig();
    document.getElementById("noObjMsg").style.display = "none";
};

function saveDraft() {
    document.getElementById("formAction").value = "draft";
    document.getElementById("planForm").submit();
}

async function submitPlan() {
    const result = await TPMS.confirm(
        "Forward to Division Chief?",
        "Are you certain you wish to forward this document to the Division Chief for review?",
        "Yes, Forward",
        "#1a3a5c"
    );
    if (result.isConfirmed) {
        document.getElementById("formAction").value = "submit";
        document.getElementById("planForm").submit();
    }
}
</script>'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
