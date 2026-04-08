<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== ROLE_PO) {
    echo json_encode(['success'=>false,'error'=>'Only Process Owners can upload evidence.']); exit;
}

$objId = (int)($_POST['objective_id'] ?? 0);
if (!$objId) { echo json_encode(['success'=>false,'error'=>'Objective ID required.']); exit; }

// Verify the objective belongs to this user
$stmt = $pdo->prepare("SELECT o.*, tp.created_by, tp.status AS plan_status FROM objectives o JOIN tactical_plans tp ON o.tactical_plan_id = tp.id WHERE o.id=?");
$stmt->execute([$objId]);
$obj = $stmt->fetch();

if (!$obj || $obj['created_by'] != $_SESSION['user_id']) {
    echo json_encode(['success'=>false,'error'=>'Access denied.']); exit;
}

if (!isset($_FILES['evidence_files'])) {
    echo json_encode(['success'=>false,'error'=>'No files uploaded.']); exit;
}

$files    = $_FILES['evidence_files'];
$uploaded = [];
$errors   = [];

for ($i = 0; $i < count($files['name']); $i++) {
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $errors[] = "Error uploading {$files['name'][$i]}";
        continue;
    }
    if ($files['size'][$i] > MAX_FILE_SIZE) {
        $errors[] = "{$files['name'][$i]} exceeds 50MB limit.";
        continue;
    }

    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_TYPES)) {
        $errors[] = "{$files['name'][$i]}: file type not allowed.";
        continue;
    }

    $safeFilename = uniqid('ev_') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $files['name'][$i]);
    $destPath     = UPLOAD_PATH . $safeFilename;

    if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);

    if (move_uploaded_file($files['tmp_name'][$i], $destPath)) {
        $stmt = $pdo->prepare("INSERT INTO evidence (objective_id, uploaded_by, original_name, file_path, file_type, file_size) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $objId, $_SESSION['user_id'],
            $files['name'][$i], $safeFilename,
            $files['type'][$i], $files['size'][$i]
        ]);
        $uploaded[] = ['name' => $files['name'][$i], 'size' => formatFileSize($files['size'][$i])];
        logAudit($pdo, $_SESSION['user_id'], 'EVIDENCE_UPLOADED', "File: {$files['name'][$i]}", $obj['tactical_plan_id']);
    } else {
        $errors[] = "Could not save {$files['name'][$i]}.";
    }
}

echo json_encode(['success' => !empty($uploaded), 'uploaded' => $uploaded, 'errors' => $errors]);
