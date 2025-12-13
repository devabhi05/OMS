<?php
require_once 'config.php';
require_once 'auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$userId = (int)($_SESSION['user_id'] ?? 0);
$role   = (string)($_SESSION['role'] ?? '');

if ($userId <= 0 || $role !== 'employee') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden.']);
    exit;
}

$action = strtolower(trim((string)($_POST['action'] ?? '')));

$actions = [
    'checkin'  => ['col' => 'time_in',   'ok' => 'Checked in Successfully.',   'already' => 'Already checked in.'],
    'lunchin'  => ['col' => 'lunch_in',  'ok' => 'Lunch In Successfully.',     'already' => 'Lunch In already marked.'],
    'lunchout' => ['col' => 'lunch_out', 'ok' => 'Lunch Out Successfully.',    'already' => 'Lunch Out already marked.'],
    'checkout' => ['col' => 'time_out',  'ok' => 'Checked out Successfully.',  'already' => 'Already checked out.'],
];

if (!isset($actions[$action])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

$col   = $actions[$action]['col'];
$today = date('Y-m-d');

$mysqli->begin_transaction();

try {
    // Ensure today's attendance row exists (one row per user per day)
    // NOTE: your attendance table already has UNIQUE(user_id, work_date) as `uniq_user_date`.
    $ensureSql = "
        INSERT INTO attendance (user_id, work_date, attendance_status, created_at, updated_at)
        VALUES (?, ?, 'P', NOW(), NOW())
        ON DUPLICATE KEY UPDATE updated_at = updated_at
    ";
    $ensure = $mysqli->prepare($ensureSql);
    if (!$ensure) {
        throw new Exception('Prepare failed (ensure).');
    }
    $ensure->bind_param('is', $userId, $today);
    $ensure->execute();
    $ensure->close();

    // Prevent overwrite: only set if the column is still NULL
    $updateSql = "UPDATE attendance
                  SET {$col} = NOW(), updated_at = NOW()
                  WHERE user_id = ? AND work_date = ? AND {$col} IS NULL";
    $upd = $mysqli->prepare($updateSql);
    if (!$upd) {
        throw new Exception('Prepare failed (update).');
    }
    $upd->bind_param('is', $userId, $today);
    $upd->execute();
    $affected = $upd->affected_rows;
    $upd->close();

    if ($affected === 1) {
        $mysqli->commit();
        echo json_encode(['success' => true, 'message' => $actions[$action]['ok']]);
        exit;
    }

    // If not updated, it is likely already set. Confirm and return a clean message.
    $selectSql = "SELECT {$col} AS v FROM attendance WHERE user_id = ? AND work_date = ? LIMIT 1";
    $sel = $mysqli->prepare($selectSql);
    if (!$sel) {
        throw new Exception('Prepare failed (select).');
    }
    $sel->bind_param('is', $userId, $today);
    $sel->execute();
    $res = $sel->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $sel->close();

    $mysqli->commit();

    if ($row && !empty($row['v'])) {
        echo json_encode(['success' => false, 'message' => $actions[$action]['already']]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unable to update attendance. Please try again.']);
    exit;

} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
    exit;
}
