<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false]); exit; }
if (!verifyCsrf($_POST['csrf_token'] ?? '')) { echo json_encode(['success'=>false]); exit; }

$pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$_SESSION['user_id']]);
echo json_encode(['success'=>true]);
