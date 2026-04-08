<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isset($_SESSION['user_id'])) {
    logAudit($pdo, $_SESSION['user_id'], 'LOGOUT', 'User logged out');
}

session_destroy();
redirect(APP_URL . '/index.php?msg=logged_out');
