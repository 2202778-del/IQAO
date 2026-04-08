<?php
/**
 * TPMS Setup & Verification Page
 * Visit this once to import the database schema.
 * DELETE THIS FILE after setup is complete.
 */

// Security: block if TPMS is already installed
if (file_exists(__DIR__ . '/config/database.php')) {
    // Try connecting to see if DB exists
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tpms_db');

$messages = [];
$errors   = [];
$step     = (int)($_GET['step'] ?? 1);

if ($step === 2) {
    try {
        // Connect without DB to create it
        $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $sql = file_get_contents(__DIR__ . '/database.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt) && !str_starts_with($stmt, '--')) {
                $pdo->exec($stmt);
            }
        }
        $messages[] = '✅ Database created and schema imported successfully.';

        // Create uploads directory
        if (!is_dir(__DIR__ . '/uploads/evidence')) {
            mkdir(__DIR__ . '/uploads/evidence', 0755, true);
            $messages[] = '✅ Upload directory created.';
        }

        $messages[] = '✅ Setup complete! <strong><a href="/IQAO/index.php">Click here to login</a></strong>';
        $messages[] = '⚠️ <strong>Delete this setup.php file immediately!</strong>';

    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    } catch (Exception $e) {
        $errors[] = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>TPMS Setup</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh}</style>
</head>
<body>
<div class="card shadow" style="max-width:600px;width:100%">
    <div class="card-header text-white" style="background:#1a3a5c">
        <h4 class="mb-0">TPMS Setup Wizard</h4>
    </div>
    <div class="card-body p-4">
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <?php if (!empty($messages)): ?>
        <div class="alert alert-success"><ul class="mb-0"><?php foreach ($messages as $m): ?><li><?= $m ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <h5>Before You Begin</h5>
        <p>This wizard will:</p>
        <ul>
            <li>Create the <strong>tpms_db</strong> MySQL database</li>
            <li>Import all tables and sample data</li>
            <li>Create the uploads directory</li>
        </ul>
        <div class="alert alert-warning">
            <strong>Requirements:</strong><br>
            • XAMPP must be running (Apache + MySQL)<br>
            • MySQL credentials: root / (empty password)<br>
            • Update <code>config/database.php</code> if different
        </div>
        <h6>Sample Login Accounts (after setup):</h6>
        <table class="table table-sm table-bordered">
            <tr><th>Role</th><th>Email</th><th>Password</th></tr>
            <tr><td>IQAO (Admin)</td><td>iqao@university.edu</td><td>password</td></tr>
            <tr><td>President</td><td>president@university.edu</td><td>password</td></tr>
            <tr><td>Division Chief</td><td>dc.academic@university.edu</td><td>password</td></tr>
            <tr><td>Process Owner</td><td>po.cict@university.edu</td><td>password</td></tr>
        </table>
        <a href="?step=2" class="btn btn-primary">Run Setup</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
