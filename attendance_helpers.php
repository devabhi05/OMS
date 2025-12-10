<?php
// Common Time & Attendance helper functions

if (!defined('SHIFT_MINUTES')) {
    // Company standard shift: 8 hours
    define('SHIFT_MINUTES', 8 * 60);
}

/**
 * Check if a given date (Y-m-d) is a company off-day.
 * Rules:
 *  - All Sundays
 *  - 1st and 3rd Saturdays of every month
 */
function is_company_off_day(string $date): bool
{
    $ts  = strtotime($date);
    if ($ts === false) {
        return false;
    }

    $dayOfWeek = (int)date('N', $ts); // 1 = Mon ... 6 = Sat, 7 = Sun
    $dayOfMonth = (int)date('j', $ts);

    // Sundays
    if ($dayOfWeek === 7) {
        return true;
    }

    // 1st and 3rd Saturdays
    if ($dayOfWeek === 6) {
        $nth = intdiv($dayOfMonth - 1, 7) + 1;
        if ($nth === 1 || $nth === 3) {
            return true;
        }
    }

    return false;
}

/**
 * Convert a TIME string (HH:MM:SS or HH:MM) into total minutes.
 */
function time_to_minutes(?string $time): int
{
    if (!$time) {
        return 0;
    }
    $parts = explode(':', $time);
    if (count($parts) < 2) {
        return 0;
    }
    $hours = (int)$parts[0];
    $minutes = (int)$parts[1];
    return $hours * 60 + $minutes;
}

/**
 * Format minutes as HH:MM (zero-padded).
 */
function minutes_to_hhmm(int $minutes): string
{
    if ($minutes < 0) {
        $minutes = 0;
    }
    $hours = intdiv($minutes, 60);
    $mins  = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $mins);
}

/**
 * Ensure there is exactly one attendance row per user per date and
 * record login time (first IN of the day).
 */
function record_attendance_login(mysqli $mysqli, int $userId): void
{
    $today = date('Y-m-d');

    // Admin attendance is not needed
    if (!isset($_SESSION['role']) || $_SESSION['role'] === 'admin') {
        return;
    }

    // Ignore company off-days completely
    if (is_company_off_day($today)) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $defaultBreak = '00:30:00';

    // Check if a row already exists for today
    $stmt = $mysqli->prepare("SELECT id, attendance_status, time_in, break_duration FROM attendance WHERE user_id = ? AND work_date = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('is', $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    if ($row) {
        // Already have a record; ensure it's marked Present and has an IN time.
        $attendanceStatus = $row['attendance_status'] ?: 'P';
        $timeIn           = $row['time_in'];
        $breakDuration    = $row['break_duration'] ?: $defaultBreak;

        if (!$timeIn) {
            $timeIn = $now;
        }

        $update = $mysqli->prepare("UPDATE attendance SET attendance_status = ?, time_in = ?, break_duration = ?, updated_at = NOW() WHERE id = ?");
        if ($update) {
            $update->bind_param('sssi', $attendanceStatus, $timeIn, $breakDuration, $row['id']);
            $update->execute();
            $update->close();
        }
    } else {
        // Insert fresh record for today
        $attendanceStatus = 'P';
        $timeIn           = $now;

        $insert = $mysqli->prepare("
            INSERT INTO attendance
                (user_id, work_date, attendance_status, time_in, break_duration, total_minutes, overtime_minutes, remarks, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, 0, 0, '', NOW(), NOW())
        ");
        if ($insert) {
            $insert->bind_param('issss', $userId, $today, $attendanceStatus, $timeIn, $defaultBreak);
            $insert->execute();
            $insert->close();
        }
    }
}

/**
 * Record logout time (OUT) and recalculate total and overtime hours.
 */
function record_attendance_logout(mysqli $mysqli, int $userId): void
{
    $today = date('Y-m-d');

    // Admin attendance is not needed
    if (!isset($_SESSION['role']) || $_SESSION['role'] === 'admin') {
        return;
    }

    // Ignore logouts on company off-days
    if (is_company_off_day($today)) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $defaultBreak = '00:30:00';

    // Fetch today's record
    $stmt = $mysqli->prepare("SELECT id, time_in, time_out, break_duration FROM attendance WHERE user_id = ? AND work_date = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('is', $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        // No record yet (edge case) – create a minimal one with OUT time only
        $insert = $mysqli->prepare("
            INSERT INTO attendance
                (user_id, work_date, attendance_status, time_in, time_out, break_duration, total_minutes, overtime_minutes, remarks, created_at, updated_at)
            VALUES
                (?, ?, 'P', NULL, ?, ?, 0, 0, '', NOW(), NOW())
        ");
        if ($insert) {
            $breakDuration = $defaultBreak;
            $insert->bind_param('isss', $userId, $today, $now, $breakDuration);
            $insert->execute();
            $insert->close();
        }
        return;
    }

    $timeIn        = $row['time_in'];
    $existingOut   = $row['time_out'];
    $breakDuration = $row['break_duration'] ?: $defaultBreak;

    // Use the *latest* OUT time of the day
    if ($existingOut) {
        $existingTs = strtotime($existingOut);
        $nowTs      = strtotime($now);
        if ($nowTs !== false && $existingTs !== false && $nowTs > $existingTs) {
            $timeOut = $now;
        } else {
            $timeOut = $existingOut;
        }
    } else {
        $timeOut = $now;
    }

    $totalMinutes   = 0;
    $overtimeMinutes = 0;

    if ($timeIn && $timeOut) {
        $inTs  = strtotime($timeIn);
        $outTs = strtotime($timeOut);
        if ($inTs !== false && $outTs !== false && $outTs > $inTs) {
            $diffMinutes   = (int)floor(($outTs - $inTs) / 60);
            $breakMinutes  = time_to_minutes($breakDuration);
            $totalMinutes  = max($diffMinutes - $breakMinutes, 0);
            $overtimeMinutes = max($totalMinutes - SHIFT_MINUTES, 0);
        }
    }

    $update = $mysqli->prepare("
        UPDATE attendance
        SET time_out = ?, break_duration = ?, total_minutes = ?, overtime_minutes = ?, updated_at = NOW()
        WHERE id = ?
    ");
    if ($update) {
        $update->bind_param('ssiii', $timeOut, $breakDuration, $totalMinutes, $overtimeMinutes, $row['id']);
        $update->execute();
        $update->close();
    }
}
?>
