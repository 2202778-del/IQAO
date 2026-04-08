<?php
// ============================================================
// Application Configuration
// ============================================================

define('APP_NAME', 'TPMS');
define('APP_FULL_NAME', 'Tactical Planning Monitoring System');
define('APP_URL', 'http://localhost/IQAO');
define('UNIVERSITY_NAME', 'University');
define('CURRENT_YEAR', (int)date('Y'));

// Upload settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/evidence/');
define('UPLOAD_URL', APP_URL . '/uploads/evidence/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_TYPES', ['pdf','doc','docx','xls','xlsx','ppt','pptx',
                          'jpg','jpeg','png','gif','bmp','webp',
                          'mp4','avi','mov','wmv','mkv','zip','rar']);

// Email settings (configure for your SMTP)
define('MAIL_FROM', 'noreply@university.edu');
define('MAIL_FROM_NAME', APP_FULL_NAME);
define('MAIL_ENABLED', false); // Set true when mail is configured
// For SMTP, install PHPMailer: composer require phpmailer/phpmailer
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');

// Session
define('SESSION_TIMEOUT', 7200); // 2 hours

// Roles
define('ROLE_PO', 'process_owner');
define('ROLE_DC', 'division_chief');
define('ROLE_IQAO', 'iqao');
define('ROLE_PRESIDENT', 'president');

// Document status labels
define('STATUS_LABELS', [
    'draft'            => ['label' => 'Draft',                  'class' => 'secondary'],
    'submitted'        => ['label' => 'Submitted to DC',        'class' => 'info'],
    'returned_to_po'   => ['label' => 'Returned to PO',         'class' => 'warning'],
    'dc_approved'      => ['label' => 'Forwarded to IQAO',      'class' => 'primary'],
    'returned_to_dc'   => ['label' => 'Returned to DC',         'class' => 'warning'],
    'iqao_approved'    => ['label' => 'Forwarded to President',  'class' => 'primary'],
    'returned_to_iqao' => ['label' => 'Returned to IQAO',       'class' => 'warning'],
    'signed'           => ['label' => 'Signed / Controlled Copy','class' => 'success'],
]);

// Objective status labels
define('OBJ_STATUS_LABELS', [
    'not_set'          => ['label' => 'Not Tagged',       'class' => 'secondary'],
    'accomplished'     => ['label' => 'Accomplished',     'class' => 'success'],
    'ongoing'          => ['label' => 'On-going',         'class' => 'info'],
    'not_accomplished' => ['label' => 'Not Accomplished', 'class' => 'danger'],
]);
