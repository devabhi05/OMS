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
$today = date('Y-m-d'); // uses timezone from config.php

// Helper: convert HH:MM:SS to minutes
function _time_to_minutes(?string $time): int {
    if (!$time) return 0;
    $parts = explode(':', $time);
    if (count($parts) < 2) return 0;
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    return $h * 60 + $m;
}

// Company shift minutes (8 hours)
if (!defined('SHIFT_MINUTES')) {
    define('SHIFT_MINUTES', 8 * 60);
}

$mysqli->begin_transaction();

try {
    // Ensure row exists for today (one record per user per day)
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

    // Update selected column ONLY if currently NULL (prevents overwriting)
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
        // If checkout was set, recalculate totals (keeps reports/pages consistent)
        if ($action === 'checkout') {
            $sel = $mysqli->prepare("SELECT time_in, time_out, break_duration FROM attendance WHERE user_id = ? AND work_date = ? LIMIT 1");
            if ($sel) {
                $sel->bind_param('is', $userId, $today);
                $sel->execute();
                $res = $sel->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $sel->close();

                if ($row && !empty($row['time_in']) && !empty($row['time_out'])) {
                    $inTs  = strtotime($row['time_in']);
                    $outTs = strtotime($row['time_out']);
                    if ($inTs !== false && $outTs !== false && $outTs > $inTs) {
                        $diffMinutes  = (int)floor(($outTs - $inTs) / 60);
                        $breakMinutes = _time_to_minutes($row['break_duration'] ?? '00:30:00');
                        $totalMinutes = max($diffMinutes - $breakMinutes, 0);
                        $overtime     = max($totalMinutes - SHIFT_MINUTES, 0);

                        $u2 = $mysqli->prepare("UPDATE attendance SET total_minutes = ?, overtime_minutes = ?, updated_at = NOW() WHERE user_id = ? AND work_date = ? LIMIT 1");
                        if ($u2) {
                            $u2->bind_param('iiis', $totalMinutes, $overtime, $userId, $today);
                            $u2->execute();
                            $u2->close();
                        }
                    }
                }
            }
        }

        $mysqli->commit();
        echo json_encode(['success' => true, 'message' => $actions[$action]['ok']]);
        exit;
    }

    // If not updated, likely already set. Confirm and return friendly message.
    $selSql = "SELECT {$col} AS v FROM attendance WHERE user_id = ? AND work_date = ? LIMIT 1";
    $sel = $mysqli->prepare($selSql);
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

    echo json_encode(['success' => false, 'message' => 'Unable to update attendance.']);
    exit;

} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error.']);
    exit;
}
