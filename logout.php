<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'attendance_helpers.php';

if (is_logged_in()) {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0 && ($_SESSION['role'] ?? '') !== 'admin') {
        // Record logout time and recalculate totals for today
        record_attendance_logout($mysqli, $userId);
    }
}



// Clear the session and redirect to login
session_unset();
session_destroy();
header('Location: index.php');
exit;
?>
