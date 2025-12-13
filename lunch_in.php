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
        'message' => 'Today is an off day. Lunch In is disabled.'
    ]);
    exit;
}

$now = date('Y-m-d H:i:s');
$defaultBreak = '00:30:00';

// Find today's attendance row
$stmt = $mysqli->prepare("SELECT id, lunch_in FROM attendance WHERE user_id = ? AND work_date = ? LIMIT 1");
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

if ($row) {
    // Already exists — only set lunch_in once
    if (!empty($row['lunch_in'])) {
        echo json_encode([
            'ok' => false,
            'code' => 'already_marked',
            'message' => 'Lunch In already marked at ' . date('h:i A', strtotime($row['lunch_in'])) . '.',
            'lunch_in' => $row['lunch_in']
        ]);
        exit;
    }

    $upd = $mysqli->prepare("UPDATE attendance SET lunch_in = ?, updated_at = NOW() WHERE id = ?");
    if (!$upd) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'DB error (prepare failed)']);
        exit;
    }
    $upd->bind_param('si', $now, $row['id']);
    $upd->execute();
    $upd->close();
} else {
    // No row yet — create a minimal one for today and set lunch_in
    $ins = $mysqli->prepare("
        INSERT INTO attendance
            (user_id, work_date, attendance_status, time_in, lunch_in, break_duration, total_minutes, overtime_minutes, remarks, created_at, updated_at)
        VALUES
            (?, ?, 'P', NULL, ?, ?, 0, 0, '', NOW(), NOW())
    ");
    if (!$ins) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'DB error (prepare failed)']);
        exit;
    }
    $ins->bind_param('isss', $userId, $today, $now, $defaultBreak);
    $ins->execute();
    $ins->close();
}

echo json_encode([
    'ok' => true,
    'message' => 'Lunch In marked successfully.',
    'lunch_in' => $now
]);
exit;
