<?php
require_once 'config.php';
require_once 'auth.php';

require_login();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: manage_users.php');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    header('Location: manage_users.php?msg=' . urlencode('Invalid user ID.'));
    exit;
}

if ($id === 1) {
    header('Location: manage_users.php?msg=' . urlencode('Main admin cannot be deleted.'));
    exit;
}

if ($id === (int)$_SESSION['user_id']) {
    header('Location: manage_users.php?msg=' . urlencode('You cannot delete your own account.'));
    exit;
}

$stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
if (!$stmt) {
    $msg = urlencode('Delete failed: ' . $mysqli->error);
    header("Location: manage_users.php?msg={$msg}");
    exit;
}

$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    $stmt->close();
    $msg = urlencode('User deleted successfully.');
    header("Location: manage_users.php?msg={$msg}");
    exit;
} else {
    $error = $stmt->error;
    $stmt->close();
    $msg = urlencode('Failed to delete user: ' . $error);
    header("Location: manage_users.php?msg={$msg}");
    exit;
}
