<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(APP_URL . '/plans/index.php');
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('error', 'Invalid security token.', 'danger');
    redirect(APP_URL . '/plans/index.php');
}

$user   = currentUser($pdo);
$role   = $user['role'];
$planId = (int)($_POST['plan_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$plan   = getPlan($pdo, $planId);

if (!$plan) {
    flash('error', 'Plan not found.', 'danger');
    redirect(APP_URL . '/plans/index.php');
}

$viewUrl = APP_URL . '/plans/view.php?id=' . $planId;

try {
    $pdo->beginTransaction();

    switch ($action) {

        // ---- Process Owner submits to DC ----
        case 'submit':
            if ($role !== ROLE_PO || $plan['created_by'] != $user['id'] || !in_array($plan['status'], ['draft','returned_to_po'])) {
                throw new Exception('Unauthorized action.');
            }
            $pdo->prepare("UPDATE tactical_plans SET status='submitted', submitted_at=NOW(), revision_notes=NULL, updated_at=NOW() WHERE id=?")
                ->execute([$planId]);
            logAudit($pdo, $user['id'], 'SUBMITTED', 'Submitted to Division Chief.', $planId);

            // Notify DC
            $dc = getDivisionChief($pdo, $user['division_id']);
            if ($dc) {
                sendNotification($pdo, $dc['id'],
                    'Action Required: Plan Review',
                    "{$user['name']} submitted a tactical plan from {$user['department_name']} for review.",
                    $viewUrl
                );
                sendEmail($dc['email'], $dc['name'],
                    "Action Required: TPMS Review - {$user['department_name']}",
                    emailTemplate('Review Required', "<p>Dear {$dc['name']},</p><p>{$user['name']} ({$user['department_name']}) submitted a Quality Objectives document for your review.</p><p>Reference: <strong>{$plan['reference_no']}</strong></p><a href='{$viewUrl}' class='btn'>Review Now</a>")
                );
            }
            flash('success', 'Plan submitted to Division Chief successfully.');
            break;

        // ---- DC approves → IQAO ----
        case 'dc_approve':
            if ($role !== ROLE_DC || $plan['status'] !== 'submitted') throw new Exception('Unauthorized.');
            $comment = trim($_POST['comment'] ?? '');

            $pdo->prepare("UPDATE tactical_plans SET status='dc_approved', dc_approved_by=?, dc_approved_at=NOW(), revision_notes=NULL, updated_at=NOW() WHERE id=?")
                ->execute([$user['id'], $planId]);
            if ($comment) saveComment($pdo, $planId, $user['id'], $comment);
            logAudit($pdo, $user['id'], 'DC_APPROVED', 'Approved by Division Chief. Forwarded to IQAO.', $planId);

            // Notify IQAO
            $iqao = getIQAO($pdo);
            if ($iqao) {
                sendNotification($pdo, $iqao['id'],
                    'Action Required: IQAO Review',
                    "{$plan['dept_name']} tactical plan approved by DC and forwarded for your review.",
                    $viewUrl
                );
                sendEmail($iqao['email'], $iqao['name'],
                    "Action Required: TPMS IQAO Review - {$plan['dept_name']}",
                    emailTemplate('IQAO Review Required', "<p>Dear {$iqao['name']},</p><p>A tactical plan from <strong>{$plan['dept_name']}</strong> has been approved by the Division Chief and requires your final review.</p><p>Reference: <strong>{$plan['reference_no']}</strong></p><a href='{$viewUrl}' class='btn'>Review Now</a>")
                );
            }
            flash('success', 'Plan approved and forwarded to IQAO.');
            break;

        // ---- IQAO approves → President ----
        case 'iqao_approve':
            if ($role !== ROLE_IQAO || $plan['status'] !== 'dc_approved') throw new Exception('Unauthorized.');
            $comment = trim($_POST['comment'] ?? '');

            $pdo->prepare("UPDATE tactical_plans SET status='iqao_approved', iqao_approved_by=?, iqao_approved_at=NOW(), revision_notes=NULL, updated_at=NOW() WHERE id=?")
                ->execute([$user['id'], $planId]);
            if ($comment) saveComment($pdo, $planId, $user['id'], $comment);
            logAudit($pdo, $user['id'], 'IQAO_APPROVED', 'Approved by IQAO. Forwarded to President.', $planId);

            // Notify President
            $pres = getPresident($pdo);
            if ($pres) {
                sendNotification($pdo, $pres['id'],
                    'Action Required: Presidential Signature',
                    "{$plan['dept_name']} Quality Objectives requires your signature.",
                    $viewUrl
                );
                sendEmail($pres['email'], $pres['name'],
                    "Action Required: TPMS Signature - {$plan['dept_name']}",
                    emailTemplate('Signature Required', "<p>Dear {$pres['name']},</p><p>A Quality Objectives document from <strong>{$plan['dept_name']}</strong> requires your digital signature to be officially filed.</p><p>Reference: <strong>{$plan['reference_no']}</strong></p><a href='{$viewUrl}' class='btn'>Sign Document</a>")
                );
            }
            flash('success', 'Plan forwarded to the Office of the President.');
            break;

        // ---- President signs ----
        case 'sign':
            if ($role !== ROLE_PRESIDENT || $plan['status'] !== 'iqao_approved') throw new Exception('Unauthorized.');
            $signature = $_POST['signature'] ?? '';
            if (empty($signature) || !str_starts_with($signature, 'data:image/')) {
                throw new Exception('Signature is required.');
            }

            $pdo->prepare("UPDATE tactical_plans SET status='signed', signed_by=?, signed_at=NOW(), president_signature=?, is_controlled_copy=1, updated_at=NOW() WHERE id=?")
                ->execute([$user['id'], $signature, $planId]);
            logAudit($pdo, $user['id'], 'SIGNED', 'Digitally signed by the President. Document filed as Controlled Copy.', $planId);

            // Notify PO and IQAO
            $po = getUserById($pdo, $plan['created_by']);
            $iqao = getIQAO($pdo);
            foreach (array_filter([$po, $iqao]) as $recipient) {
                sendNotification($pdo, $recipient['id'],
                    'Document Signed & Filed',
                    "{$plan['dept_name']} Quality Objectives has been signed by the President and filed as a Controlled Copy.",
                    $viewUrl
                );
            }
            flash('success', 'Document signed and filed as a Controlled Copy.');
            break;

        // ---- Return for revision ----
        case 'return':
            $notes = trim($_POST['revision_notes'] ?? '');
            if (empty($notes)) throw new Exception('Revision notes are required.');

            $validReturns = [
                ROLE_DC       => ['from' => 'submitted',    'to' => 'returned_to_po',   'notifyField' => 'created_by'],
                ROLE_IQAO     => ['from' => 'dc_approved',  'to' => 'returned_to_dc',   'notifyField' => 'dc_returned'],
                ROLE_PRESIDENT=> ['from' => 'iqao_approved','to' => 'returned_to_iqao', 'notifyField' => 'iqao_returned'],
            ];
            if (!isset($validReturns[$role]) || $plan['status'] !== $validReturns[$role]['from']) {
                throw new Exception('Cannot return at this stage.');
            }

            $newStatus = $validReturns[$role]['to'];
            $pdo->prepare("UPDATE tactical_plans SET status=?, revision_notes=?, returned_by=?, updated_at=NOW() WHERE id=?")
                ->execute([$newStatus, $notes, $user['id'], $planId]);
            logAudit($pdo, $user['id'], 'RETURNED', "Returned for revision: $notes", $planId);

            // Notify the appropriate recipient
            $notifyUserId = null;
            if ($role === ROLE_DC) {
                $notifyUserId = $plan['created_by'];
            } elseif ($role === ROLE_IQAO) {
                // Find DC
                $dc = getDivisionChief($pdo, null, $planId);
                $notifyUserId = $dc ? $dc['id'] : null;
            } elseif ($role === ROLE_PRESIDENT) {
                $notifyUserId = null;
                $iqao = getIQAO($pdo);
                if ($iqao) sendNotification($pdo, $iqao['id'], 'Document Returned', "President returned {$plan['dept_name']} plan for revision: $notes", $viewUrl);
            }
            if ($notifyUserId) {
                sendNotification($pdo, $notifyUserId,
                    'Document Returned for Revision',
                    "{$user['name']} returned the {$plan['dept_name']} tactical plan. Notes: $notes",
                    $viewUrl
                );
            }
            flash('warning', 'Document returned for revision.');
            break;

        default:
            throw new Exception('Invalid action.');
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    flash('error', $e->getMessage(), 'danger');
}

redirect($viewUrl);

// ---- Helper functions ----

function saveComment(PDO $pdo, $planId, $userId, $comment) {
    $pdo->prepare("INSERT INTO comments (tactical_plan_id, user_id, comment) VALUES (?,?,?)")
        ->execute([$planId, $userId, $comment]);
}

function getUserById(PDO $pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getDivisionChief(PDO $pdo, $divisionId, $planId = null) {
    if ($planId && !$divisionId) {
        $row = $pdo->prepare("SELECT d.division_id FROM tactical_plans tp JOIN departments d ON tp.department_id = d.id WHERE tp.id = ?");
        $row->execute([$planId]);
        $r = $row->fetch();
        $divisionId = $r['division_id'] ?? null;
    }
    if (!$divisionId) return null;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role='division_chief' AND division_id=? AND is_active=1 LIMIT 1");
    $stmt->execute([$divisionId]);
    return $stmt->fetch() ?: null;
}

function getIQAO(PDO $pdo) {
    return $pdo->query("SELECT * FROM users WHERE role='iqao' AND is_active=1 LIMIT 1")->fetch() ?: null;
}

function getPresident(PDO $pdo) {
    return $pdo->query("SELECT * FROM users WHERE role='president' AND is_active=1 LIMIT 1")->fetch() ?: null;
}
