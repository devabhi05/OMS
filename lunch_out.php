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

// Keep same logic as attendance: don’t record on company off-days
if (is_company_off_day($today)) {
    echo json_encode([
        'ok' => false,
        'code' => 'off_day',
        'message' => 'Today is an off day. Lunch Out is disabled.'
    ]);
    exit;
}

$now = date('Y-m-d H:i:s');

// Find today's attendance row
$stmt = $mysqli->prepare("SELECT id, lunch_in, lunch_out FROM attendance WHERE user_id = ? AND work_date = ? LIMIT 1");
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
        'message' => 'Attendance record for today was not found. Please mark Lunch In first.'
    ]);
    exit;
}

if (empty($row['lunch_in'])) {
    echo json_encode([
        'ok' => false,
        'code' => 'missing_lunch_in',
        'message' => 'Please mark Lunch In first.'
    ]);
    exit;
}

if (!empty($row['lunch_out'])) {
    echo json_encode([
        'ok' => false,
        'code' => 'already_marked',
        'message' => 'Lunch Out already marked at ' . date('h:i A', strtotime($row['lunch_out'])) . '.',
        'lunch_out' => $row['lunch_out']
    ]);
    exit;
}

// Update lunch_out, and compute break_duration from lunch_in -> now
$upd = $mysqli->prepare("
    UPDATE attendance
    SET
        lunch_out = ?,
        break_duration = SEC_TO_TIME(TIMESTAMPDIFF(SECOND, lunch_in, ?)),
        updated_at = NOW()
    WHERE id = ? AND lunch_out IS NULL
");
if (!$upd) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'DB error (prepare failed)']);
    exit;
}
$upd->bind_param('ssi', $now, $now, $row['id']);
$upd->execute();
$upd->close();

// Return updated values
$stmt = $mysqli->prepare("SELECT lunch_out, break_duration FROM attendance WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $row['id']);
$stmt->execute();
$res = $stmt->get_result();
$updated = $res->fetch_assoc();
$stmt->close();

echo json_encode([
    'ok' => true,
    'message' => 'Lunch Out marked successfully.',
    'lunch_out' => $updated['lunch_out'] ?? $now,
    'break_duration' => $updated['break_duration'] ?? null
]);
exit;
