<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$user = currentUser($pdo);
$role = $user['role'];

// Build query based on role
if ($role === ROLE_PO) {
    $stmt = $pdo->prepare("
        SELECT tp.*, d.name AS dept_name, u.name AS creator_name
        FROM tactical_plans tp
        JOIN departments d ON tp.department_id = d.id
        JOIN users u ON tp.created_by = u.id
        WHERE tp.created_by = ?
        ORDER BY tp.updated_at DESC
    ");
    $stmt->execute([$user['id']]);
} elseif ($role === ROLE_DC) {
    $stmt = $pdo->prepare("
        SELECT tp.*, d.name AS dept_name, u.name AS creator_name
        FROM tactical_plans tp
        JOIN departments d ON tp.department_id = d.id
        JOIN users u ON tp.created_by = u.id
        WHERE d.division_id = ?
        ORDER BY tp.updated_at DESC
    ");
    $stmt->execute([$user['division_id']]);
} else {
    $stmt = $pdo->prepare("
        SELECT tp.*, d.name AS dept_name, u.name AS creator_name
        FROM tactical_plans tp
        JOIN departments d ON tp.department_id = d.id
        JOIN users u ON tp.created_by = u.id
        ORDER BY tp.updated_at DESC
    ");
    $stmt->execute();
}
$plans = $stmt->fetchAll();

// Count actions needed
$actionNeeded = 0;
foreach ($plans as $p) {
    if ($role === ROLE_DC && $p['status'] === 'submitted') $actionNeeded++;
    if ($role === ROLE_IQAO && $p['status'] === 'dc_approved') $actionNeeded++;
    if ($role === ROLE_PRESIDENT && $p['status'] === 'iqao_approved') $actionNeeded++;
    if ($role === ROLE_PO && $p['status'] === 'returned_to_po') $actionNeeded++;
}

$pageTitle = 'Tactical Plans';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <div>
            <h4><i class="fas fa-file-alt me-2 text-primary"></i>Tactical Plans</h4>
            <?php if ($actionNeeded > 0): ?>
            <span class="badge bg-danger"><?= $actionNeeded ?> action(s) required</span>
            <?php endif; ?>
        </div>
        <?php if ($role === ROLE_PO): ?>
        <a href="<?= APP_URL ?>/plans/create.php" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> New Plan
        </a>
        <?php endif; ?>
    </div>

    <?php showFlash(); ?>

    <!-- Filter tabs -->
    <ul class="nav nav-tabs mb-3" id="planTabs">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#all">All <span class="badge bg-secondary"><?= count($plans) ?></span></a></li>
        <?php if ($role === ROLE_DC): ?>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#action">Action Required <span class="badge bg-danger"><?= $actionNeeded ?></span></a></li>
        <?php endif; ?>
        <?php if ($role === ROLE_IQAO): ?>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#action">Action Required <span class="badge bg-danger"><?= $actionNeeded ?></span></a></li>
        <?php endif; ?>
        <?php if ($role === ROLE_PRESIDENT): ?>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#action">Awaiting Signature <span class="badge bg-danger"><?= $actionNeeded ?></span></a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#signed">Signed/Filed</a></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="all">
            <?= renderPlanTable($plans, $role, false) ?>
        </div>
        <?php if ($role !== ROLE_PO): ?>
        <div class="tab-pane fade" id="action">
            <?php
            $actionStatuses = ['submitted','returned_to_po','dc_approved','returned_to_dc','iqao_approved'];
            $actionPlans = array_filter($plans, fn($p) =>
                ($role === ROLE_DC && $p['status'] === 'submitted') ||
                ($role === ROLE_IQAO && $p['status'] === 'dc_approved') ||
                ($role === ROLE_PRESIDENT && $p['status'] === 'iqao_approved') ||
                ($role === ROLE_PO && $p['status'] === 'returned_to_po')
            );
            echo renderPlanTable(array_values($actionPlans), $role, false);
            ?>
        </div>
        <?php endif; ?>
        <div class="tab-pane fade" id="signed">
            <?= renderPlanTable(array_values(array_filter($plans, fn($p) => $p['status'] === 'signed')), $role, true) ?>
        </div>
    </div>
</div>

<?php
function renderPlanTable($plans, $role, $signed) {
    ob_start(); ?>
    <div class="table-card">
        <div class="table-responsive">
            <table class="table datatable">
                <thead>
                    <tr>
                        <th>Reference No.</th>
                        <th>Department</th>
                        <th>Year</th>
                        <th>Prepared By</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($plans)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No plans found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($plans as $p): ?>
                    <tr>
                        <td><span class="fw-semibold"><?= e($p['reference_no'] ?? 'N/A') ?></span></td>
                        <td><?= e($p['dept_name']) ?></td>
                        <td><?= e($p['academic_year']) ?></td>
                        <td><?= e($p['creator_name']) ?></td>
                        <td><?= statusBadge($p['status']) ?>
                            <?php if ($p['status'] === 'returned_to_po' || $p['status'] === 'returned_to_dc' || $p['status'] === 'returned_to_iqao'): ?>
                            <i class="fas fa-exclamation-circle text-warning ms-1" title="<?= e($p['revision_notes'] ?? '') ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= date('M j, Y', strtotime($p['updated_at'])) ?></td>
                        <td>
                            <a href="<?= APP_URL ?>/plans/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if ($role === ROLE_PO && in_array($p['status'], ['draft','returned_to_po'])): ?>
                            <a href="<?= APP_URL ?>/plans/edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($role === ROLE_IQAO && $p['status'] === 'signed'): ?>
                            <a href="<?= APP_URL ?>/plans/print.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-dark" target="_blank">
                                <i class="fas fa-print"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
