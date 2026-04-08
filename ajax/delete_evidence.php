<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_PO) {
    echo json_encode(['success'=>false,'error'=>'Not authorized']); exit;
}
if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success'=>false,'error'=>'Invalid token']); exit;
}

$evId = (int)($_POST['evidence_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM evidence WHERE id=? AND uploaded_by=?");
$stmt->execute([$evId, $_SESSION['user_id']]);
$ev = $stmt->fetch();

if (!$ev) { echo json_encode(['success'=>false,'error'=>'Not found']); exit; }

$filePath = UPLOAD_PATH . $ev['file_path'];
if (file_exists($filePath)) unlink($filePath);

$pdo->prepare("DELETE FROM evidence WHERE id=?")->execute([$evId]);

echo json_encode(['success'=>true]);
