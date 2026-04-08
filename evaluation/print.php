<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth([ROLE_IQAO, ROLE_DC, ROLE_PO]);

$year = (int)($_GET['year'] ?? CURRENT_YEAR);
$type = $_GET['type'] ?? 'accomplished'; // 'accomplished' or 'not_accomplished'
if (!in_array($type, ['accomplished','not_accomplished'])) $type = 'accomplished';

$user = currentUser($pdo);
$role = $user['role'];

// Fetch data
if ($role === ROLE_PO) {
    $stmt = $pdo->prepare("
        SELECT o.*, tp.reference_no, tp.academic_year, d.name AS dept_name, d.code AS dept_code,
               dv.name AS div_name, u.name AS prepared_by,
               ev.impact_benefits, ev.reason, ev.type AS eval_type
        FROM objectives o
        JOIN tactical_plans tp ON o.tactical_plan_id = tp.id
        JOIN departments d ON tp.department_id = d.id
        JOIN divisions dv ON d.division_id = dv.id
        JOIN users u ON tp.created_by = u.id
        LEFT JOIN evaluations ev ON ev.objective_id = o.id
        WHERE tp.created_by=? AND tp.academic_year=? AND tp.status='signed' AND o.status=?
        ORDER BY o.sort_order
    ");
    $stmt->execute([$user['id'], $year, $type]);
} else {
    $stmt = $pdo->prepare("
        SELECT o.*, tp.reference_no, tp.academic_year, d.name AS dept_name, d.code AS dept_code,
               dv.name AS div_name, u.name AS prepared_by,
               ev.impact_benefits, ev.reason, ev.type AS eval_type
        FROM objectives o
        JOIN tactical_plans tp ON o.tactical_plan_id = tp.id
        JOIN departments d ON tp.department_id = d.id
        JOIN divisions dv ON d.division_id = dv.id
        JOIN users u ON tp.created_by = u.id
        LEFT JOIN evaluations ev ON ev.objective_id = o.id
        WHERE tp.academic_year=? AND tp.status='signed' AND o.status=?
        ORDER BY d.name, o.sort_order
    ");
    $stmt->execute([$year, $type]);
}
$objectives = $stmt->fetchAll();

// Group by department
$byDept = [];
foreach ($objectives as $obj) {
    $byDept[$obj['dept_name']][] = $obj;
}

$docTitle = $type === 'accomplished'
    ? 'Evaluation of Accomplished Quality Objectives'
    : 'Evaluation of Not Accomplished Quality Objectives';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $docTitle ?> &mdash; AY <?= $year ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 10pt; background: #fff; }
.print-container { width: 100%; max-width: 297mm; margin: 0 auto; padding: 10mm 15mm; }

.header-row { display: flex; align-items: center; margin-bottom: 8px; }
.logo-circle { width: 65px; height: 65px; border-radius: 50%; background: #1a3a5c; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 900; font-size: 16pt; margin-right: 12px; flex-shrink: 0; }
.univ-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; color: #1a3a5c; }
.doc-title { font-size: 13pt; font-weight: bold; text-transform: uppercase; text-align: center; margin: 8px 0 4px; border-top: 2px solid #1a3a5c; border-bottom: 2px solid #1a3a5c; padding: 4px 0; color: #1a3a5c; }

.dept-header { background: #1a3a5c; color: #fff; padding: 5px 10px; margin: 12px 0 0; font-weight: bold; font-size: 10.5pt; }
.eval-table { width: 100%; border-collapse: collapse; font-size: 9.5pt; margin-bottom: 8px; }
.eval-table th { background: #2c5282; color: #fff; padding: 6px 8px; border: 1px solid #999; text-align: center; }
.eval-table td { border: 1px solid #ccc; padding: 5px 8px; vertical-align: top; }
.eval-table tr:nth-child(even) { background: #f8f9fc; }

.summary-box { border: 1px solid #1a3a5c; padding: 8px 12px; margin: 10px 0; background: #f0f4ff; font-size: 9pt; }
.signature-section { margin-top: 20px; display: flex; justify-content: space-around; }
.sig-block { text-align: center; width: 200px; }
.sig-line { border-top: 1px solid #333; padding-top: 3px; font-weight: bold; font-size: 10pt; margin-top: 30px; }
.sig-label { font-size: 8.5pt; color: #555; }

.doc-footer { margin-top: 16px; font-size: 8pt; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }
.page-break { page-break-before: always; }

@media print {
    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .no-print { display: none !important; }
}
</style>
</head>
<body>
<div class="no-print" style="padding:12px;background:#f0f0f0;text-align:center;border-bottom:1px solid #ddd">
    <button onclick="window.print()" style="background:#1a3a5c;color:#fff;border:none;padding:8px 24px;border-radius:6px;cursor:pointer">
        &#128438; Print Document
    </button>
    <a href="javascript:history.back()" style="margin-left:12px;color:#1a3a5c">&#8592; Back</a>
    <span style="margin-left:16px;color:#666">Showing: <?= htmlspecialchars($docTitle) ?> &mdash; AY <?= $year ?></span>
</div>

<div class="print-container">
    <div class="header-row">
        <div class="logo-circle">U</div>
        <div>
            <div class="univ-name"><?= e(UNIVERSITY_NAME) ?></div>
            <div style="font-size:9pt;color:#666">Internal Quality Assurance Office (IQAO)</div>
        </div>
    </div>
    <div class="doc-title"><?= htmlspecialchars($docTitle) ?></div>
    <div style="text-align:center;margin-bottom:10px;font-size:10pt">Academic Year <?= $year ?></div>

    <?php if (empty($objectives)): ?>
    <p style="text-align:center;color:#999;padding:20px">No <?= $type === 'accomplished' ? 'accomplished' : 'not accomplished' ?> objectives found for AY <?= $year ?>.</p>
    <?php else: ?>

    <div class="summary-box">
        <strong>Summary:</strong> <?= count($objectives) ?> objective(s) tagged as "<?= $type === 'accomplished' ? 'Accomplished' : 'Not Accomplished' ?>"
        across <?= count($byDept) ?> department(s) for Academic Year <?= $year ?>.
    </div>

    <?php $deptCount = 0; foreach ($byDept as $deptName => $deptObjs): ?>
    <?php if ($deptCount > 0): ?><div class="page-break"></div><?php endif; ?>

    <div class="dept-header"><?= e($deptName) ?></div>

    <table class="eval-table">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th style="width:30%">Quality Objective</th>
                <th style="width:15%">Success Indicator</th>
                <th style="width:10%">Target</th>
                <th style="width:<?= $type==='accomplished'?'35%':'35%' ?>">
                    <?= $type === 'accomplished' ? 'Impact / Benefits Achieved' : 'Reason for Non-Accomplishment' ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deptObjs as $i => $obj): ?>
            <tr>
                <td style="text-align:center"><?= $i + 1 ?></td>
                <td><?= nl2br(e($obj['quality_objective'])) ?></td>
                <td><?= nl2br(e($obj['success_indicator'])) ?></td>
                <td><?= e($obj['target']) ?></td>
                <td>
                    <?php if ($type === 'accomplished'): ?>
                        <?= nl2br(e($obj['impact_benefits'] ?? '')) ?: '<em style="color:#aaa">Not filled in</em>' ?>
                    <?php else: ?>
                        <?= nl2br(e($obj['reason'] ?? '')) ?: '<em style="color:#aaa">Not filled in</em>' ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php $deptCount++; endforeach; ?>

    <!-- Signature Block -->
    <div class="signature-section" style="margin-top:30px">
        <div class="sig-block">
            <div class="sig-line">_________________________</div>
            <div class="sig-label">Process Owner</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">_________________________</div>
            <div class="sig-label">Division Chief</div>
        </div>
        <div class="sig-block">
            <div class="sig-line">_________________________</div>
            <div class="sig-label">IQAO Representative</div>
        </div>
    </div>

    <?php endif; ?>

    <div class="doc-footer">
        <?= e(APP_FULL_NAME) ?> &mdash; <?= e(UNIVERSITY_NAME) ?> &mdash; Printed: <?= date('F j, Y g:i A') ?>
    </div>
</div>
</body>
</html>
