<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

$planId = (int)($_GET['id'] ?? 0);
$plan   = getPlan($pdo, $planId);
if (!$plan) die('Plan not found.');

// Only IQAO can print (per requirements), but allow view for all who can see it
$user = currentUser($pdo);
if (!in_array($user['role'], [ROLE_IQAO, ROLE_PRESIDENT, ROLE_DC, ROLE_PO])) die('Access denied.');

$objectives = getObjectives($pdo, $planId);
$totalBudget = array_sum(array_column($objectives, 'budget'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quality Objectives &mdash; <?= e($plan['reference_no'] ?? '') ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: Arial, sans-serif; font-size: 10pt; background: #fff; }
.print-container { width: 100%; max-width: 297mm; margin: 0 auto; padding: 10mm 15mm; }

.header-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.header-table td { vertical-align: middle; }
.logo-cell { width: 80px; text-align: center; }
.logo-circle { width: 70px; height: 70px; border-radius: 50%; background: #1a3a5c; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: #fff; font-weight: 900; font-size: 18pt; }
.header-text { padding-left: 12px; }
.univ-name { font-size: 14pt; font-weight: bold; text-transform: uppercase; color: #1a3a5c; }
.doc-title { font-size: 13pt; font-weight: bold; text-transform: uppercase; text-align: center; margin: 8px 0 4px; border-top: 2px solid #1a3a5c; border-bottom: 2px solid #1a3a5c; padding: 4px 0; color: #1a3a5c; }
.sub-title { font-size: 11pt; text-align: center; margin-bottom: 8px; color: #555; }

.info-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9.5pt; }
.info-table td { padding: 3px 8px; }
.info-table .label { font-weight: bold; width: 140px; color: #1a3a5c; }
.info-table .value { border-bottom: 1px solid #ccc; }

.objectives-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 9pt; }
.objectives-table th { background: #1a3a5c; color: #fff; padding: 6px 6px; border: 1px solid #333; text-align: center; vertical-align: middle; }
.objectives-table td { border: 1px solid #ccc; padding: 5px 6px; vertical-align: top; }
.objectives-table tr:nth-child(even) { background: #f8f9fc; }
.objectives-table .total-row td { font-weight: bold; background: #e8edf7; }
.quarter-tag { display: inline-block; background: #1a3a5c; color: #fff; padding: 1px 4px; border-radius: 2px; font-size: 7.5pt; margin: 1px; }

.signature-section { margin-top: 20px; }
.sig-row { display: flex; justify-content: space-around; margin-top: 10px; }
.sig-block { text-align: center; width: 200px; }
.sig-image { height: 50px; display: flex; align-items: flex-end; justify-content: center; margin-bottom: 4px; }
.sig-line { border-top: 1px solid #333; padding-top: 3px; font-weight: bold; font-size: 10pt; }
.sig-label { font-size: 8.5pt; color: #555; }
.sig-date { font-size: 8.5pt; color: #555; margin-top: 2px; }

.controlled-stamp { position: fixed; top: 35%; left: 50%; transform: translate(-50%,-50%) rotate(-20deg); font-size: 42pt; font-weight: 900; color: rgba(200,0,0,0.15); border: 8px solid rgba(200,0,0,0.15); padding: 6px 20px; border-radius: 4px; pointer-events: none; white-space: nowrap; z-index: 999; }

.doc-footer { margin-top: 16px; font-size: 8pt; color: #888; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }

@media print {
    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .no-print { display: none; }
}
</style>
</head>
<body>
<?php if ($plan['is_controlled_copy']): ?>
<div class="controlled-stamp">CONTROLLED COPY</div>
<?php endif; ?>

<div class="no-print" style="padding:12px;background:#f0f0f0;text-align:center;border-bottom:1px solid #ddd">
    <button onclick="window.print()" style="background:#1a3a5c;color:#fff;border:none;padding:8px 24px;border-radius:6px;cursor:pointer;font-size:14px">
        &#128438; Print Document
    </button>
    <a href="javascript:history.back()" style="margin-left:12px;color:#1a3a5c">&#8592; Back</a>
    <?php if ($plan['is_controlled_copy']): ?>
    <span style="margin-left:16px;background:#dc3545;color:#fff;padding:4px 12px;border-radius:4px;font-size:12px;font-weight:bold">CONTROLLED COPY</span>
    <?php endif; ?>
</div>

<div class="print-container">
    <!-- Header -->
    <table class="header-table">
        <tr>
            <td class="logo-cell">
                <div class="logo-circle">U</div>
            </td>
            <td class="header-text">
                <div class="univ-name"><?= e(UNIVERSITY_NAME) ?></div>
                <div style="font-size:9pt;color:#666">Internal Quality Assurance Office (IQAO)</div>
            </td>
            <td style="text-align:right;font-size:8pt;color:#666;vertical-align:top">
                Ref. No: <strong><?= e($plan['reference_no'] ?? 'N/A') ?></strong><br>
                AY: <strong><?= e($plan['academic_year']) ?></strong>
            </td>
        </tr>
    </table>

    <div class="doc-title">Quality Objectives / Tactical Plan</div>
    <div class="sub-title">Academic Year <?= e($plan['academic_year']) ?></div>

    <!-- Info -->
    <table class="info-table">
        <tr>
            <td class="label">Department / Office:</td>
            <td class="value"><?= e($plan['dept_name']) ?></td>
            <td class="label" style="padding-left:20px">Division:</td>
            <td class="value"><?= e($plan['division_name'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="label">Prepared By:</td>
            <td class="value"><?= e($plan['created_by_name']) ?></td>
            <td class="label" style="padding-left:20px">Date Prepared:</td>
            <td class="value"><?= $plan['submitted_at'] ? date('F j, Y', strtotime($plan['submitted_at'])) : '&mdash;' ?></td>
        </tr>
    </table>

    <!-- Objectives Table -->
    <table class="objectives-table">
        <thead>
            <tr>
                <th style="width:30px">No.</th>
                <th style="width:22%">Quality Objective / Program Activity</th>
                <th style="width:18%">Success Indicator / KPI</th>
                <th style="width:12%">Target</th>
                <th style="width:10%">Timeline</th>
                <th style="width:14%">Person Responsible</th>
                <th style="width:10%">Budget (₱)</th>
                <th style="width:10%">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($objectives as $i => $obj): ?>
            <tr>
                <td style="text-align:center"><?= $i + 1 ?></td>
                <td><?= nl2br(e($obj['quality_objective'])) ?></td>
                <td><?= nl2br(e($obj['success_indicator'])) ?></td>
                <td><?= e($obj['target']) ?></td>
                <td style="text-align:center">
                    <?php for ($q=1;$q<=4;$q++): if ($obj['timeline_q' . $q]): ?><span class="quarter-tag">Q<?= $q ?></span><?php endif; endfor; ?>
                </td>
                <td><?= e($obj['person_responsible']) ?></td>
                <td style="text-align:right">₱<?= number_format($obj['budget'],2) ?></td>
                <td style="text-align:center">
                    <?php $sl = OBJ_STATUS_LABELS[$obj['status']] ?? ['label'=>'N/A']; echo e($sl['label']); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="6" style="text-align:right">TOTAL BUDGET:</td>
                <td style="text-align:right">₱<?= number_format($totalBudget,2) ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="signature-section">
        <div class="sig-row">
            <div class="sig-block">
                <div class="sig-image"></div>
                <div class="sig-line"><?= e($plan['created_by_name']) ?></div>
                <div class="sig-label">Process Owner / Department Head</div>
                <div class="sig-date"><?= $plan['submitted_at'] ? date('F j, Y', strtotime($plan['submitted_at'])) : '' ?></div>
            </div>
            <div class="sig-block">
                <div class="sig-image"></div>
                <div class="sig-line"><?= $plan['dc_approved_by_name'] ? e($plan['dc_approved_by_name']) : '_______________________' ?></div>
                <div class="sig-label">Division Chief</div>
                <div class="sig-date"><?= $plan['dc_approved_at'] ? date('F j, Y', strtotime($plan['dc_approved_at'])) : '' ?></div>
            </div>
            <div class="sig-block">
                <?php if ($plan['president_signature']): ?>
                <div class="sig-image"><img src="<?= $plan['president_signature'] ?>" alt="Signature" style="max-height:50px"></div>
                <?php else: ?>
                <div class="sig-image"></div>
                <?php endif; ?>
                <div class="sig-line"><?= $plan['signed_by_name'] ? e($plan['signed_by_name']) : '_______________________' ?></div>
                <div class="sig-label">University President</div>
                <div class="sig-date"><?= $plan['signed_at'] ? date('F j, Y', strtotime($plan['signed_at'])) : '' ?></div>
            </div>
        </div>
    </div>

    <div class="doc-footer">
        <?= e(APP_FULL_NAME) ?> &mdash; <?= e(UNIVERSITY_NAME) ?> &mdash; Printed: <?= date('F j, Y g:i A') ?>
        <?php if ($plan['is_controlled_copy']): ?> &mdash; <strong style="color:red">CONTROLLED COPY</strong><?php endif; ?>
    </div>
</div>
</body>
</html>
