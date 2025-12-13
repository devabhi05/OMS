<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'attendance_helpers.php';

require_login();
require_role(['employee']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$today  = date('Y-m-d');

if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit;
}

if (is_company_off_day($today)) {
    echo json_encode([
        'ok' => false,
        'code' => 'off_day',
        'message' => 'Today is an off day. Check Out is disabled.'
    ]);
    exit;
}

$now = date('Y-m-d H:i:s');

// Read today's attendance row
$stmt = $mysqli->prepare("SELECT id, check_in, check_out FROM attendance WHERE user_id = ? AND work_date = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'DB error (prepare failed)']);
    exit;
}
$stmt->bind_param('is', $userId, $today);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    echo json_encode([
        'ok' => false,
        'code' => 'no_attendance_row',
        'message' => 'Attendance record for today was not found. Please Check In first.'
    ]);
    exit;
}

if (empty($row['check_in'])) {
    echo json_encode([
        'ok' => false,
        'code' => 'missing_check_in',
        'message' => 'Please Check In first.'
    ]);
    exit;
}

if (!empty($row['check_out'])) {
    echo json_encode([
        'ok' => false,
        'code' => 'already_marked',
        'message' => 'Check Out already marked at ' . date('h:i A', strtotime($row['check_out'])) . '.',
        'check_out' => $row['check_out']
    ]);
    exit;
}

// Mark check_out once per day
$upd = $mysqli->prepare("
    UPDATE attendance
    SET check_out = ?, updated_at = NOW()
    WHERE id = ? AND check_out IS NULL
");
if (!$upd) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'DB error (prepare failed)']);
    exit;
}
$upd->bind_param('si', $now, $row['id']);
$upd->execute();
$upd->close();

echo json_encode([
    'ok' => true,
    'message' => 'Check Out marked successfully.',
    'check_out' => $now
]);
exit;
