<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'error'=>'Not authenticated']); exit; }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false,'error'=>'Invalid token']); exit; }

$planId  = (int)($_POST['plan_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (!$planId || !$comment) {
    echo json_encode(['success'=>false,'error'=>'Missing data']); exit;
}

$pdo->prepare("INSERT INTO comments (tactical_plan_id, user_id, comment) VALUES (?,?,?)")
    ->execute([$planId, $_SESSION['user_id'], $comment]);

logAudit($pdo, $_SESSION['user_id'], 'COMMENT_ADDED', 'Remark posted.', $planId);

echo json_encode(['success'=>true]);
