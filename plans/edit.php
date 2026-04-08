<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth([ROLE_PO, ROLE_DC, ROLE_IQAO]);

$user   = currentUser($pdo);
$role   = $user['role'];
$planId = (int)($_GET['id'] ?? 0);

$plan = getPlan($pdo, $planId);
if (!$plan) { flash('error', 'Plan not found.', 'danger'); redirect(APP_URL . '/plans/index.php'); }

// Permission check
if (!canEditPlan($plan, $user['id'], $role)) {
    flash('error', 'You do not have permission to edit this plan in its current state.', 'warning');
    redirect(APP_URL . '/plans/view.php?id=' . $planId);
}

$objectives = getObjectives($pdo, $planId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');

    $action = $_POST['form_action'] ?? 'draft';
    $pdo->beginTransaction();
    try {
        // Update objectives - delete existing, re-insert
        $pdo->prepare("DELETE FROM objectives WHERE tactical_plan_id = ?")->execute([$planId]);

        $sortOrder = 0;
        foreach ($_POST['objectives'] ?? [] as $obj) {
            if (empty(trim($obj['quality_objective'] ?? ''))) continue;
            $stmt = $pdo->prepare("
                INSERT INTO objectives (tactical_plan_id, quality_objective, success_indicator, target, timeline_q1, timeline_q2, timeline_q3, timeline_q4, person_responsible, budget, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $planId, trim($obj['quality_objective']), trim($obj['success_indicator'] ?? ''),
                trim($obj['target'] ?? ''),
                !empty($obj['q1']) ? 1 : 0, !empty($obj['q2']) ? 1 : 0,
                !empty($obj['q3']) ? 1 : 0, !empty($obj['q4']) ? 1 : 0,
                trim($obj['person_responsible'] ?? ''), (float)($obj['budget'] ?? 0), $sortOrder++
            ]);
        }

        $newStatus = $plan['status'];
        $submittedAt = $plan['submitted_at'];
        if ($action === 'submit') {
            $newStatus   = 'submitted';
            $submittedAt = date('Y-m-d H:i:s');
        }

        $pdo->prepare("UPDATE tactical_plans SET status = ?, submitted_at = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$newStatus, $submittedAt, $planId]);

        logAudit($pdo, $user['id'], 'EDITED', 'Plan updated.', $planId);

        if ($action === 'submit') {
            $dcStmt = $pdo->prepare("SELECT * FROM users WHERE role = 'division_chief' AND division_id = ? AND is_active = 1 LIMIT 1");
            $dcStmt->execute([$user['division_id']]);
            $dc = $dcStmt->fetch();
            if ($dc) {
                sendNotification($pdo, $dc['id'],
                    'Action Required: Plan Review',
                    $user['name'] . ' has submitted a revised tactical plan for your review.',
                    APP_URL . '/plans/view.php?id=' . $planId
                );
            }
            logAudit($pdo, $user['id'], 'SUBMITTED', 'Submitted to Division Chief.', $planId);
        }

        $pdo->commit();
        flash('success', $action === 'submit' ? 'Plan submitted to Division Chief.' : 'Plan saved.');
        redirect(APP_URL . '/plans/view.php?id=' . $planId);
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Error: ' . $e->getMessage(), 'danger');
    }
}

$pageTitle = 'Edit Tactical Plan';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <div>
            <h4><i class="fas fa-edit me-2 text-primary"></i>Edit Tactical Plan</h4>
            <small class="text-muted"><?= e($plan['reference_no'] ?? 'Draft') ?></small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/plans/view.php?id=<?= $planId ?>" class="btn btn-outline-secondary">
                <i class="fas fa-eye me-1"></i> Preview
            </a>
            <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <?php showFlash(); ?>

    <?php if ($plan['status'] === 'returned_to_po' && $plan['revision_notes']): ?>
    <div class="alert alert-warning d-flex gap-2">
        <i class="fas fa-exclamation-triangle mt-1"></i>
        <div>
            <strong>Revision Required:</strong><br>
            <?= nl2br(e($plan['revision_notes'])) ?>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="planForm">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="form_action" id="formAction" value="draft">
        <input type="hidden" id="objCount" value="<?= count($objectives) ?>">

        <div class="form-card mb-3">
            <div class="form-section-title">Plan Information</div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Department</label>
                    <input type="text" class="form-control" value="<?= e($plan['dept_name']) ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Academic Year</label>
                    <input type="text" class="form-control" value="<?= e($plan['academic_year']) ?>" disabled>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Reference No.</label>
                    <input type="text" class="form-control" value="<?= e($plan['reference_no'] ?? 'TBD') ?>" disabled>
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
                        <?php foreach ($objectives as $i => $obj): ?>
                        <tr id="obj-row-<?= $i ?>">
                            <td><?= $i + 1 ?></td>
                            <td><textarea name="objectives[<?= $i ?>][quality_objective]" class="form-control form-control-sm" rows="3" required><?= e($obj['quality_objective']) ?></textarea></td>
                            <td><textarea name="objectives[<?= $i ?>][success_indicator]" class="form-control form-control-sm" rows="2"><?= e($obj['success_indicator']) ?></textarea></td>
                            <td><input type="text" name="objectives[<?= $i ?>][target]" class="form-control form-control-sm" value="<?= e($obj['target']) ?>"></td>
                            <td>
                                <div class="quarter-check"><input type="checkbox" name="objectives[<?= $i ?>][q1]" value="1" <?= $obj['timeline_q1'] ? 'checked' : '' ?>> Q1</div>
                                <div class="quarter-check"><input type="checkbox" name="objectives[<?= $i ?>][q2]" value="1" <?= $obj['timeline_q2'] ? 'checked' : '' ?>> Q2</div>
                                <div class="quarter-check"><input type="checkbox" name="objectives[<?= $i ?>][q3]" value="1" <?= $obj['timeline_q3'] ? 'checked' : '' ?>> Q3</div>
                                <div class="quarter-check"><input type="checkbox" name="objectives[<?= $i ?>][q4]" value="1" <?= $obj['timeline_q4'] ? 'checked' : '' ?>> Q4</div>
                            </td>
                            <td><input type="text" name="objectives[<?= $i ?>][person_responsible]" class="form-control form-control-sm" value="<?= e($obj['person_responsible']) ?>"></td>
                            <td><input type="number" name="objectives[<?= $i ?>][budget]" class="form-control form-control-sm" step="0.01" min="0" value="<?= e($obj['budget']) ?>"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow('obj-row-<?= $i ?>')"><i class="fas fa-trash"></i></button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="button" class="btn btn-outline-primary" onclick="saveDraft()">
                <i class="fas fa-save me-1"></i> Save Draft
            </button>
            <?php if ($role === ROLE_PO): ?>
            <button type="button" class="btn btn-primary" onclick="submitPlan()">
                <i class="fas fa-paper-plane me-1"></i> Submit to Division Chief
            </button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php $extraJs = '<script>
function saveDraft() {
    document.getElementById("formAction").value = "draft";
    document.getElementById("planForm").submit();
}
async function submitPlan() {
    const r = await TPMS.confirm("Forward to Division Chief?","Are you certain you wish to forward this document to the Division Chief for review?","Yes, Forward","#1a3a5c");
    if (r.isConfirmed) { document.getElementById("formAction").value="submit"; document.getElementById("planForm").submit(); }
}
</script>'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
