<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_IQAO) {
    echo json_encode(['success'=>false,'error'=>'Only IQAO can tag objective status.']); exit;
}
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'error'=>'Invalid token']); exit;
}

$objId  = (int)($_POST['objective_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$valid  = ['accomplished','ongoing','not_accomplished'];

if (!$objId || !in_array($status, $valid)) {
    echo json_encode(['success'=>false,'error'=>'Invalid data']); exit;
}

// Verify objective exists and plan is signed
$stmt = $pdo->prepare("SELECT o.*, tp.status AS plan_status FROM objectives o JOIN tactical_plans tp ON o.tactical_plan_id = tp.id WHERE o.id = ?");
$stmt->execute([$objId]);
$obj = $stmt->fetch();

if (!$obj || $obj['plan_status'] !== 'signed') {
    echo json_encode(['success'=>false,'error'=>'Can only tag objectives on signed plans.']); exit;
}

$pdo->prepare("UPDATE objectives SET status=?, tagged_by=?, tagged_at=NOW() WHERE id=?")
    ->execute([$status, $_SESSION['user_id'], $objId]);

logAudit($pdo, $_SESSION['user_id'], 'STATUS_TAGGED', "Objective #{$objId} tagged as: {$status}", $obj['tactical_plan_id']);

echo json_encode(['success'=>true,'status'=>$status]);
