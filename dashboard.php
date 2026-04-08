<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$user  = currentUser($pdo);
$role  = $user['role'];
$year  = (int)($_GET['year'] ?? CURRENT_YEAR);

// Stats for pie chart
$stats = getDashboardStats($pdo, $user['id'], $role, $user['division_id'], $user['department_id'], $year);
$total = array_sum($stats);

// Plan counts for cards
function planCount(PDO $pdo, $role, $userId, $divId, $_deptId, $statuses) {
    $in = implode(',', array_fill(0, count($statuses), '?'));
    if ($role === ROLE_PO) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tactical_plans WHERE created_by = ? AND status IN ($in)");
        $stmt->execute([$userId, ...$statuses]);
    } elseif ($role === ROLE_DC) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tactical_plans tp JOIN departments d ON tp.department_id = d.id WHERE d.division_id = ? AND tp.status IN ($in)");
        $stmt->execute([$divId, ...$statuses]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tactical_plans WHERE status IN ($in)");
        $stmt->execute([...$statuses]);
    }
    return (int)$stmt->fetchColumn();
}

$draftCount    = planCount($pdo, $role, $user['id'], $user['division_id'], $user['department_id'], ['draft','returned_to_po']);
$pendingCount  = planCount($pdo, $role, $user['id'], $user['division_id'], $user['department_id'], ['submitted','dc_approved','iqao_approved']);
$approvedCount = planCount($pdo, $role, $user['id'], $user['division_id'], $user['department_id'], ['signed']);

// Recent plans
if ($role === ROLE_PO) {
    $stmt = $pdo->prepare("SELECT tp.*, d.name AS dept_name FROM tactical_plans tp JOIN departments d ON tp.department_id = d.id WHERE tp.created_by = ? ORDER BY tp.updated_at DESC LIMIT 8");
    $stmt->execute([$user['id']]);
} elseif ($role === ROLE_DC) {
    $stmt = $pdo->prepare("SELECT tp.*, d.name AS dept_name FROM tactical_plans tp JOIN departments d ON tp.department_id = d.id WHERE d.division_id = ? ORDER BY tp.updated_at DESC LIMIT 8");
    $stmt->execute([$user['division_id']]);
} else {
    $stmt = $pdo->prepare("SELECT tp.*, d.name AS dept_name FROM tactical_plans tp JOIN departments d ON tp.department_id = d.id ORDER BY tp.updated_at DESC LIMIT 8");
    $stmt->execute();
}
$recentPlans = $stmt->fetchAll();

// Deadlines
$deadlineStmt = $pdo->prepare("SELECT * FROM deadlines WHERE academic_year = ? ORDER BY deadline_date");
$deadlineStmt->execute([$year]);
$deadlines = $deadlineStmt->fetchAll();

// President/IQAO: per-department plan status
$deptStatusData = [];
if (in_array($role, [ROLE_PRESIDENT, ROLE_IQAO])) {
    $deptStmt = $pdo->prepare("
        SELECT
            d.id AS dept_id,
            d.name AS dept_name,
            d.code AS dept_code,
            dv.name AS div_name,
            tp.id AS plan_id,
            tp.status AS plan_status,
            tp.reference_no,
            tp.academic_year,
            (SELECT COUNT(*) FROM objectives o WHERE o.tactical_plan_id = tp.id) AS total_obj,
            (SELECT COUNT(*) FROM objectives o WHERE o.tactical_plan_id = tp.id AND o.status = 'accomplished') AS accomplished,
            (SELECT COUNT(*) FROM objectives o WHERE o.tactical_plan_id = tp.id AND o.status = 'ongoing') AS ongoing,
            (SELECT COUNT(*) FROM objectives o WHERE o.tactical_plan_id = tp.id AND o.status = 'not_accomplished') AS not_accomplished
        FROM departments d
        LEFT JOIN divisions dv ON d.division_id = dv.id
        LEFT JOIN tactical_plans tp ON tp.department_id = d.id AND tp.academic_year = ?
        ORDER BY dv.name, d.name
    ");
    $deptStmt->execute([$year]);
    $deptStatusData = $deptStmt->fetchAll();
}

$pageTitle = 'Dashboard';
?>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="content-wrapper">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h4><i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard</h4>
            <small class="text-muted">Welcome back, <?= e($user['name']) ?></small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <label class="mb-0 small fw-semibold text-muted">Academic Year:</label>
            <select class="form-select form-select-sm" onchange="location.href='?year='+this.value" style="width:100px">
                <?php for ($y = CURRENT_YEAR + 1; $y >= CURRENT_YEAR - 3; $y--): ?>
                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <?php if ($_GET['error'] ?? '' === 'access_denied'): ?>
    <div class="alert alert-warning"><i class="fas fa-lock me-2"></i>Access denied. You do not have permission to view that page.</div>
    <?php endif; ?>

    <!-- Stat Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card border-0">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Draft / Returned</div>
                        <div class="stat-number text-secondary"><?= $draftCount ?></div>
                    </div>
                    <i class="fas fa-file stat-icon text-secondary"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card border-0">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">In Progress</div>
                        <div class="stat-number text-warning"><?= $pendingCount ?></div>
                    </div>
                    <i class="fas fa-clock stat-icon text-warning"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card border-0">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Signed / Filed</div>
                        <div class="stat-number text-success"><?= $approvedCount ?></div>
                    </div>
                    <i class="fas fa-check-circle stat-icon text-success"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card border-0">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Objectives</div>
                        <div class="stat-number text-primary"><?= $total ?></div>
                    </div>
                    <i class="fas fa-bullseye stat-icon text-primary"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Recent Plans -->
        <div class="col-12">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0"><i class="fas fa-file-alt me-2 text-primary"></i>Recent Tactical Plans</h6>
                    <a href="<?= APP_URL ?>/plans/index.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Reference</th>
                                    <th>Department</th>
                                    <th>Year</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentPlans)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No plans found.</td></tr>
                                <?php else: ?>
                                <?php foreach ($recentPlans as $plan): ?>
                                <tr>
                                    <td class="small fw-semibold"><?= e($plan['reference_no'] ?? 'N/A') ?></td>
                                    <td class="small"><?= e($plan['dept_name']) ?></td>
                                    <td class="small"><?= e($plan['academic_year']) ?></td>
                                    <td><?= statusBadge($plan['status']) ?></td>
                                    <td>
                                        <a href="<?= APP_URL ?>/plans/view.php?id=<?= $plan['id'] ?>" class="btn btn-xs btn-sm btn-outline-secondary py-0 px-2" style="font-size:.75rem">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deadlines -->
        <?php if (!empty($deadlines)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0">
                    <h6 class="fw-bold mb-0"><i class="fas fa-calendar-alt me-2 text-danger"></i>Academic Year <?= $year ?> Deadlines</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php
                        $dlTypes = ['objective_setting' => ['Objective Setting','primary','file-alt'],
                                   'evidence_upload' => ['Evidence Upload','warning','paperclip'],
                                   'final_evaluation' => ['Final Evaluation','danger','clipboard-check']];
                        foreach ($deadlines as $dl):
                            [$label, $color, $icon] = $dlTypes[$dl['type']];
                            $daysLeft = (int)((strtotime($dl['deadline_date']) - time()) / 86400);
                        ?>
                        <div class="col-md-4">
                            <div class="p-3 rounded border border-<?= $color ?> border-opacity-25 bg-<?= $color ?> bg-opacity-10">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="fas fa-<?= $icon ?> text-<?= $color ?>"></i>
                                    <span class="fw-semibold small"><?= $label ?></span>
                                </div>
                                <div class="fw-bold"><?= date('F j, Y', strtotime($dl['deadline_date'])) ?></div>
                                <small class="<?= $daysLeft <= 3 ? 'text-danger fw-bold' : 'text-muted' ?>">
                                    <?= $daysLeft > 0 ? "{$daysLeft} days remaining" : ($daysLeft == 0 ? 'Due today!' : abs($daysLeft) . ' days overdue') ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- President / IQAO: Per-Department Analytics -->
        <?php if (in_array($role, [ROLE_PRESIDENT, ROLE_IQAO]) && !empty($deptStatusData)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0" style="color:#1a3a5c">
                            <i class="fas fa-chart-pie me-2"></i>Department Objectives Analytics &mdash; AY <?= $year ?>
                        </h5>
                        <small class="text-muted">Objectives status per department (signed plans only)</small>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center" style="font-size:.78rem">
                        <span class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#198754;border-radius:50%;display:inline-block"></span> Accomplished</span>
                        <span class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#0dcaf0;border-radius:50%;display:inline-block"></span> On-going</span>
                        <span class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#dc3545;border-radius:50%;display:inline-block"></span> Not Accomplished</span>
                        <span class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#6c757d;border-radius:50%;display:inline-block"></span> Not Tagged</span>
                        <span class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#ffc107;border-radius:50%;display:inline-block"></span> In Progress</span>
                        <span class="d-flex align-items-center gap-1"><span style="width:12px;height:12px;background:#adb5bd;border-radius:50%;display:inline-block"></span> Not Submitted</span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row g-4">
                        <?php foreach ($deptStatusData as $idx => $row):
                            $hasplan   = !empty($row['plan_id']);
                            $isSigned  = $row['plan_status'] === 'signed';
                            $not_set   = max(0, (int)$row['total_obj'] - (int)$row['accomplished'] - (int)$row['ongoing'] - (int)$row['not_accomplished']);
                            $isProgress= in_array($row['plan_status'] ?? '', ['submitted','dc_approved','iqao_approved']);
                            $isReturn  = in_array($row['plan_status'] ?? '', ['returned_to_po','returned_to_dc','returned_to_iqao','draft']);

                            $topColor  = !$hasplan ? '#adb5bd' : ($isSigned ? '#198754' : ($isProgress ? '#ffc107' : '#dc3545'));
                            $bgGrad    = !$hasplan ? '#f8f9fa' : ($isSigned ? '#f0fdf4' : ($isProgress ? '#fffbeb' : '#fff5f5'));
                        ?>
                        <div class="col-sm-6 col-md-4 col-xl-3">
                            <div class="dept-chart-card h-100" style="background:<?= $bgGrad ?>;border-top:5px solid <?= $topColor ?>;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.07);overflow:hidden">

                                <!-- Card Top: Dept Info -->
                                <div class="px-3 pt-3 pb-1">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="badge fw-semibold px-2" style="background:<?= $topColor ?>;color:<?= $isProgress ? '#333' : '#fff' ?>;font-size:.7rem"><?= e($row['dept_code']) ?></span>
                                        <?php if ($hasplan): ?>
                                        <a href="<?= APP_URL ?>/plans/view.php?id=<?= $row['plan_id'] ?>" class="btn btn-sm py-0 px-2" style="font-size:.7rem;background:#1a3a5c;color:#fff;border-radius:20px">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="fw-bold" style="font-size:.82rem;color:#1a3a5c;line-height:1.3"><?= e($row['dept_name']) ?></div>
                                    <div class="text-muted" style="font-size:.7rem"><?= e($row['div_name'] ?? '') ?></div>
                                </div>

                                <!-- Pie Chart Centered -->
                                <div style="position:relative;width:100%;padding:8px 0 4px;display:flex;justify-content:center;align-items:center">
                                    <canvas id="pie_<?= $idx ?>" width="150" height="150" style="max-width:150px;max-height:150px;display:block"></canvas>
                                </div>

                                <!-- Stats Row -->
                                <div class="px-3 pb-3">
                                    <?php if ($hasplan && $isSigned): ?>
                                    <div class="row g-1 text-center" style="font-size:.7rem">
                                        <div class="col-6">
                                            <div style="background:rgba(25,135,84,.12);border-radius:8px;padding:4px 2px">
                                                <div class="fw-bold fs-6 text-success"><?= $row['accomplished'] ?></div>
                                                <div class="text-muted" style="font-size:.65rem">Accomplished</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div style="background:rgba(13,202,240,.12);border-radius:8px;padding:4px 2px">
                                                <div class="fw-bold fs-6 text-info"><?= $row['ongoing'] ?></div>
                                                <div class="text-muted" style="font-size:.65rem">On-going</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div style="background:rgba(220,53,69,.12);border-radius:8px;padding:4px 2px">
                                                <div class="fw-bold fs-6 text-danger"><?= $row['not_accomplished'] ?></div>
                                                <div class="text-muted" style="font-size:.65rem">Not Accomplished</div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div style="background:rgba(108,117,125,.12);border-radius:8px;padding:4px 2px">
                                                <div class="fw-bold fs-6 text-secondary"><?= $not_set ?></div>
                                                <div class="text-muted" style="font-size:.65rem">Not Tagged</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center mt-2" style="font-size:.68rem;color:#888">
                                        <i class="fas fa-bullseye me-1"></i><?= $row['total_obj'] ?> total objective(s)
                                    </div>
                                    <?php elseif ($hasplan): ?>
                                    <div class="text-center py-1">
                                        <?= statusBadge($row['plan_status']) ?>
                                        <div class="text-muted mt-1" style="font-size:.68rem">Awaiting final approval</div>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center py-1">
                                        <span class="badge bg-secondary">No Plan Submitted</span>
                                    </div>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$deptPieJs = '';
foreach ($deptStatusData as $idx => $row) {
    $isSigned = $row['plan_status'] === 'signed';

    if ($isSigned && (int)$row['total_obj'] > 0) {
        // Show objectives breakdown
        $not_set = max(0, (int)$row['total_obj'] - (int)$row['accomplished'] - (int)$row['ongoing'] - (int)$row['not_accomplished']);
        $labels  = json_encode(['Accomplished','On-going','Not Accomplished','Not Tagged']);
        $data    = json_encode([(int)$row['accomplished'], (int)$row['ongoing'], (int)$row['not_accomplished'], $not_set]);
        $colors  = json_encode(['#198754','#0dcaf0','#dc3545','#6c757d']);
    } elseif ($isSigned) {
        // Signed but no objectives yet
        $labels = json_encode(['No Objectives']);
        $data   = json_encode([1]);
        $colors = json_encode(['#dee2e6']);
    } elseif (!empty($row['plan_id'])) {
        // Has plan but not yet signed — show plan status
        $inProgress = in_array($row['plan_status'], ['submitted','dc_approved','iqao_approved']);
        $color  = $inProgress ? '#ffc107' : '#dc3545';
        $label  = $inProgress ? 'In Progress' : 'Returned / Draft';
        $labels = json_encode([$label]);
        $data   = json_encode([1]);
        $colors = json_encode([$color]);
    } else {
        // No plan submitted
        $labels = json_encode(['Not Submitted']);
        $data   = json_encode([1]);
        $colors = json_encode(['#adb5bd']);
    }

    $deptPieJs .= "
    (function(){
        const ctx = document.getElementById('pie_{$idx}');
        if(!ctx) return;
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: {$labels},
                datasets: [{ data: {$data}, backgroundColor: {$colors}, borderWidth: 2, borderColor: '#fff' }]
            },
            options: {
                responsive: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: c => ' ' + c.label + ': ' + c.raw } }
                }
            }
        });
    })();";
}

$extraJs = "<script>document.addEventListener('DOMContentLoaded', function() { {$deptPieJs} });</script>";
?>
<?php include __DIR__ . '/includes/footer.php'; ?>
