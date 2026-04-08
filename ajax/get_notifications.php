<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['count'=>0,'notifications'=>[]]); exit; }

$userId = $_SESSION['user_id'];

// Mark all read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'mark_all_read') {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
    echo json_encode(['success'=>true]); exit;
}

// Mark single read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_POST['id'], $userId]);
    echo json_encode(['success'=>true]); exit;
}

$count = getUnreadCount($pdo, $userId);

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$userId]);
$notifs = $stmt->fetchAll();

echo json_encode(['count' => $count, 'notifications' => $notifs]);
