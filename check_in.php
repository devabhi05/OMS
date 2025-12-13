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

// Keep consistent with your attendance rules (Sundays + 1st/3rd Saturdays)
if (is_company_off_day($today)) {
    echo json_encode([
        'ok' => false,
        'code' => 'off_day',
        'message' => 'Today is an off day. Check In is disabled.'
    ]);
    exit;
}

// Ensure attendance row exists (creates it with P + time_in if missing)
record_attendance_login($mysqli, $userId);

$now = date('Y-m-d H:i:s');

// Mark check_in once per day
$upd = $mysqli->prepare("
    UPDATE attendance
    SET check_in = ?, updated_at = NOW()
    WHERE user_id = ? AND work_date = ? AND check_in IS NULL
");
if (!$upd) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'DB error (prepare failed)']);
    exit;
}
$upd->bind_param('sis', $now, $userId, $today);
$upd->execute();
$changed = ($upd->affected_rows > 0);
$upd->close();

if (!$changed) {
    // Already marked (send existing time back)
    $stmt = $mysqli->prepare("SELECT check_in FROM attendance WHERE user_id = ? AND work_date = ? LIMIT 1");
    $stmt->bind_param('is', $userId, $today);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'ok' => false,
        'code' => 'already_marked',
        'message' => 'Check In already marked' . (!empty($row['check_in']) ? (' at ' . date('h:i A', strtotime($row['check_in']))) : '') . '.',
        'check_in' => $row['check_in'] ?? null
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Check In marked successfully.',
    'check_in' => $now
]);
exit;
