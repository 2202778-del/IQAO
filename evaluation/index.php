<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$user = currentUser($pdo);
$role = $user['role'];
$year = (int)($_GET['year'] ?? CURRENT_YEAR);

// Get objectives with evaluations based on role
if ($role === ROLE_PO) {
    $stmt = $pdo->prepare("
        SELECT o.*, tp.reference_no, tp.academic_year, d.name AS dept_name, tp.id AS plan_id,
               ev.id AS eval_id, ev.type AS eval_type, ev.impact_benefits, ev.reason
        FROM objectives o
        JOIN tactical_plans tp ON o.tactical_plan_id = tp.id
        JOIN departments d ON tp.department_id = d.id
        LEFT JOIN evaluations ev ON ev.objective_id = o.id
        WHERE tp.created_by = ? AND tp.academic_year = ? AND tp.status = 'signed'
          AND o.status IN ('accomplished','not_accomplished')
        ORDER BY o.status, o.sort_order
    ");
    $stmt->execute([$user['id'], $year]);
} elseif ($role === ROLE_DC) {
    $stmt = $pdo->prepare("
        SELECT o.*, tp.reference_no, tp.academic_year, d.name AS dept_name, tp.id AS plan_id,
               ev.id AS eval_id, ev.type AS eval_type, ev.impact_benefits, ev.reason
        FROM objectives o
        JOIN tactical_plans tp ON o.tactical_plan_id = tp.id
        JOIN departments d ON tp.department_id = d.id
        LEFT JOIN evaluations ev ON ev.objective_id = o.id
        WHERE d.division_id = ? AND tp.academic_year = ? AND tp.status = 'signed'
          AND o.status IN ('accomplished','not_accomplished')
        ORDER BY d.name, o.status, o.sort_order
    ");
    $stmt->execute([$user['division_id'], $year]);
} else {
    $stmt = $pdo->prepare("
        SELECT o.*, tp.reference_no, tp.academic_year, d.name AS dept_name, tp.id AS plan_id,
               ev.id AS eval_id, ev.type AS eval_type, ev.impact_benefits, ev.reason
        FROM objectives o
        JOIN tactical_plans tp ON o.tactical_plan_id = tp.id
        JOIN departments d ON tp.department_id = d.id
        LEFT JOIN evaluations ev ON ev.objective_id = o.id
        WHERE tp.academic_year = ? AND tp.status = 'signed'
          AND o.status IN ('accomplished','not_accomplished')
        ORDER BY d.name, o.status, o.sort_order
    ");
    $stmt->execute([$year]);
}
$objectives = $stmt->fetchAll();

// Group by type
$accomplished    = array_filter($objectives, fn($o) => $o['status'] === 'accomplished');
$notAccomplished = array_filter($objectives, fn($o) => $o['status'] === 'not_accomplished');

// Handle save evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $objId       = (int)($_POST['objective_id'] ?? 0);
    $evalType    = trim($_POST['eval_type'] ?? '');
    $impactBen   = trim($_POST['impact_benefits'] ?? '');
    $reason      = trim($_POST['reason'] ?? '');

    if ($objId && in_array($evalType, ['accomplished','not_accomplished'])) {
        $pdo->prepare("INSERT INTO evaluations (objective_id, type, impact_benefits, reason, prepared_by)
            VALUES (?,?,?,?,?)
            ON DUPLICATE KEY UPDATE type=?, impact_benefits=?, reason=?, prepared_by=?")
            ->execute([$objId, $evalType, $impactBen, $reason, $user['id'],
                       $evalType, $impactBen, $reason, $user['id']]);
        flash('success', 'Evaluation saved.');
        redirect(APP_URL . '/evaluation/index.php?year=' . $year);
    }
}

$pageTitle = 'Evaluations';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header flex-wrap gap-2">
        <div>
            <h4><i class="fas fa-clipboard-check me-2 text-primary"></i>End-of-Year Evaluations</h4>
            <small class="text-muted">Academic Year <?= $year ?></small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <select class="form-select form-select-sm" onchange="location.href='?year='+this.value" style="width:100px">
                <?php for ($y = CURRENT_YEAR; $y >= CURRENT_YEAR - 3; $y--): ?>
                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <?php if ($role === ROLE_IQAO): ?>
            <a href="<?= APP_URL ?>/evaluation/print.php?year=<?= $year ?>&type=accomplished" class="btn btn-sm btn-outline-success" target="_blank">
                <i class="fas fa-print me-1"></i> Print Accomplished
            </a>
            <a href="<?= APP_URL ?>/evaluation/print.php?year=<?= $year ?>&type=not_accomplished" class="btn btn-sm btn-outline-danger" target="_blank">
                <i class="fas fa-print me-1"></i> Print Not Accomplished
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php showFlash(); ?>

    <?php if (empty($objectives)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-clipboard fa-3x mb-3 opacity-25"></i>
            <p>No tagged objectives found for AY <?= $year ?>. Objectives must be on signed plans and tagged by IQAO.</p>
        </div>
    </div>
    <?php else: ?>

    <!-- Accomplished Tab -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#acc">
            <i class="fas fa-check-circle text-success me-1"></i>Accomplished
            <span class="badge bg-success"><?= count($accomplished) ?></span>
        </a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#nacc">
            <i class="fas fa-times-circle text-danger me-1"></i>Not Accomplished
            <span class="badge bg-danger"><?= count($notAccomplished) ?></span>
        </a></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="acc">
            <?php if (empty($accomplished)): ?>
            <div class="alert alert-light text-muted">No accomplished objectives for AY <?= $year ?>.</div>
            <?php else: ?>
            <?php foreach ($accomplished as $obj): ?>
            <?= renderEvalCard($obj, 'accomplished', $role, $user['id'], $year) ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="tab-pane fade" id="nacc">
            <?php if (empty($notAccomplished)): ?>
            <div class="alert alert-light text-muted">No unaccomplished objectives for AY <?= $year ?>.</div>
            <?php else: ?>
            <?php foreach ($notAccomplished as $obj): ?>
            <?= renderEvalCard($obj, 'not_accomplished', $role, $user['id'], $year) ?>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php
function renderEvalCard($obj, $type, $role, $userId, $year) {
    ob_start();
    $canEdit = in_array($role, [ROLE_PO, ROLE_IQAO]);
    $hasSaved = !empty($obj['eval_id']);
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0 d-flex justify-content-between">
            <div>
                <div class="small text-muted"><?= e($obj['dept_name']) ?> &mdash; <?= e($obj['reference_no']) ?></div>
                <div class="fw-semibold"><?= e(substr($obj['quality_objective'], 0, 100)) ?><?= strlen($obj['quality_objective']) > 100 ? '...' : '' ?></div>
            </div>
            <span class="badge <?= $type === 'accomplished' ? 'bg-success' : 'bg-danger' ?>"><?= $type === 'accomplished' ? 'Accomplished' : 'Not Accomplished' ?></span>
        </div>
        <div class="card-body">
            <?php if ($canEdit): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="objective_id" value="<?= $obj['id'] ?>">
                <input type="hidden" name="eval_type" value="<?= $type ?>">
                <div class="row g-3">
                    <?php if ($type === 'accomplished'): ?>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Impact / Benefits Achieved</label>
                        <textarea name="impact_benefits" class="form-control" rows="3" placeholder="Describe the impact and benefits of this accomplished objective..."><?= e($obj['impact_benefits'] ?? '') ?></textarea>
                    </div>
                    <?php else: ?>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Reason for Non-Accomplishment</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Explain the reason(s) why this objective was not accomplished..."><?= e($obj['reason'] ?? '') ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i> <?= $hasSaved ? 'Update' : 'Save' ?> Evaluation
                    </button>
                    <?php if ($hasSaved): ?><span class="text-success small ms-2"><i class="fas fa-check-circle"></i> Saved</span><?php endif; ?>
                </div>
            </form>
            <?php else: ?>
            <?php if ($hasSaved): ?>
            <?php if ($type === 'accomplished'): ?>
            <div><strong>Impact / Benefits:</strong> <?= nl2br(e($obj['impact_benefits'])) ?></div>
            <?php else: ?>
            <div><strong>Reason:</strong> <?= nl2br(e($obj['reason'])) ?></div>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-muted small">Evaluation not yet filled in.</div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
