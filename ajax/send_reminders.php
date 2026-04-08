<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
requireAuth([ROLE_IQAO]);

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['message' => 'Invalid token']); exit;
}

$year = CURRENT_YEAR;

// Find deadlines
$dlStmt = $pdo->prepare("SELECT * FROM deadlines WHERE academic_year=?");
$dlStmt->execute([$year]);
$deadlines = $dlStmt->fetchAll();
if (empty($deadlines)) {
    echo json_encode(['message' => 'No deadlines set for current year.']); exit;
}

// Find POs who haven't submitted
$pos = $pdo->query("SELECT u.*, d.name AS dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.role='process_owner' AND u.is_active=1")->fetchAll();

$sent = 0;
foreach ($pos as $po) {
    // Check if they have a submitted plan
    $submittedStmt = $pdo->prepare("SELECT id FROM tactical_plans WHERE created_by=? AND academic_year=? AND status NOT IN ('draft','returned_to_po')");
    $submittedStmt->execute([$po['id'], $year]);
    if ($submittedStmt->fetch()) continue; // Already submitted

    foreach ($deadlines as $dl) {
        $daysLeft = (int)((strtotime($dl['deadline_date']) - time()) / 86400);
        if ($daysLeft < 0 || $daysLeft > 14) continue;

        $dlLabels = ['objective_setting'=>'Objective Setting','evidence_upload'=>'Evidence Upload','final_evaluation'=>'Final Evaluation'];
        $label = $dlLabels[$dl['type']] ?? $dl['type'];
        $deadlineFormatted = date('F j, Y', strtotime($dl['deadline_date']));

        $urgency = $daysLeft <= 1 ? '🚨 URGENT: ' : ($daysLeft <= 3 ? '⚠️ ' : '');
        $title = "{$urgency}Deadline Reminder: {$label}";
        $message = "The deadline for {$label} is on {$deadlineFormatted} ({$daysLeft} day(s) remaining). Please complete your submission.";

        sendNotification($pdo, $po['id'], $title, $message, APP_URL . '/plans/index.php');

        $emailBody = emailTemplate($title, "
            <p>Dear {$po['name']},</p>
            <p>This is a reminder that the <strong>{$label}</strong> deadline is approaching.</p>
            <ul>
                <li><strong>Deadline:</strong> {$deadlineFormatted}</li>
                <li><strong>Days Remaining:</strong> {$daysLeft}</li>
            </ul>
            <p>Please log in to the system and complete your submission as soon as possible.</p>
            <a href='" . APP_URL . "/plans/index.php' class='btn'>Go to My Plans</a>
        ");
        sendEmail($po['email'], $po['name'], $title, $emailBody);
        $sent++;
    }
}

logAudit($pdo, $_SESSION['user_id'], 'REMINDERS_SENT', "Sent $sent reminders to process owners.");
echo json_encode(['message' => "Sent {$sent} reminder notification(s) to Process Owners."]);
