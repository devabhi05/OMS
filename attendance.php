<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'attendance_helpers.php';

require_login();
require_role(['admin']);

$loggedInRole = $_SESSION['role'] ?? '';
$isAdmin      = ($loggedInRole === 'admin');

// Which user's attendance are we viewing?
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (int)($_POST['user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: manage_users.php');
    exit;
}

// 1) Fetch user info (including profile_image)
$stmt = $mysqli->prepare('SELECT id, full_name, role, profile_image FROM users WHERE id = ?');
if (!$stmt) {
    die('Database error: ' . $mysqli->error);
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$userResult = $stmt->get_result();
$user = $userResult->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found.');
}

// If the *target* user is an admin, don't show attendance (admins not tracked)
if ($user['role'] === 'admin') {
    $pageTitle = 'Attendance (Admins not tracked)';
    include 'header.php';
    echo '<div class="alert alert-info mt-4">Attendance is not tracked for administrators.</div>';
    include 'footer.php';
    exit;
}

// 2) Prepare profile image URL for heading
if (!empty($user['profile_image'])) {
    $profileImageUrl = htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8');
} else {
    // fallback avatar (WEB path, not filesystem path)
    $profileImageUrl = 'images/user.png';
}

// 3) Handle updates (break & remarks) for a single attendance row
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance_id'])) {
    $attendanceId = (int)$_POST['attendance_id'];
    $breakInput   = trim($_POST['break_duration'] ?? '');
    $remarksInput = trim($_POST['remarks'] ?? '');

    // Normalise break duration to HH:MM:SS (default 00:30:00)
    $breakDuration = '00:30:00';
    if ($breakInput !== '') {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $breakInput, $m)) {
            $hh = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $breakDuration = $hh . ':' . $mm . ':00';
        }
    }

    // Fetch existing row (for IN/OUT)
    $stmt = $mysqli->prepare('SELECT time_in, time_out FROM attendance WHERE id = ? AND user_id = ?');
    if ($stmt) {
        $stmt->bind_param('ii', $attendanceId, $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if ($row) {
            $timeIn  = $row['time_in'];
            $timeOut = $row['time_out'];

            $totalMinutes = 0;
            $overtimeMinutes = 0;

            if ($timeIn && $timeOut) {
                $inTs  = strtotime($timeIn);
                $outTs = strtotime($timeOut);
                if ($inTs !== false && $outTs !== false && $outTs > $inTs) {
                    $diffMinutes  = (int)floor(($outTs - $inTs) / 60);
                    $breakMinutes = time_to_minutes($breakDuration);
                    $totalMinutes = max($diffMinutes - $breakMinutes, 0);
                    $overtimeMinutes = max($totalMinutes - SHIFT_MINUTES, 0);
                }
            }

            $stmt2 = $mysqli->prepare('UPDATE attendance SET break_duration = ?, remarks = ?, total_minutes = ?, overtime_minutes = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
            if ($stmt2) {
                $stmt2->bind_param('ssiiii', $breakDuration, $remarksInput, $totalMinutes, $overtimeMinutes, $attendanceId, $userId);
                $stmt2->execute();
                $stmt2->close();
            }
        }
    }

    // Redirect back to GET view (to avoid resubmission)
    $year  = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
    $month = isset($_POST['month']) ? (int)$_POST['month'] : (int)date('m');
    header('Location: attendance.php?user_id=' . $userId . '&year=' . $year . '&month=' . $month);
    exit;
}

// 4) Month selection
$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

if ($month < 1 || $month > 12) {
    $month = (int)date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = date('Y-m-t', strtotime($startDate));

// 5) Load attendance rows for this user & month
$attendanceByDate = [];
$totalMinutesMonth = 0;
$totalOvertimeMinutesMonth = 0;

$stmt = $mysqli->prepare('SELECT * FROM attendance WHERE user_id = ? AND work_date BETWEEN ? AND ? ORDER BY work_date ASC');
if ($stmt) {
    $stmt->bind_param('iss', $userId, $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $attendanceByDate[$row['work_date']] = $row;

        if (!empty($row['total_minutes'])) {
            $totalMinutesMonth += (int)$row['total_minutes'];
        }
        if (!empty($row['overtime_minutes'])) {
            $totalOvertimeMinutesMonth += (int)$row['overtime_minutes'];
        }
    }
    $stmt->close();
}

// --- summary calculations like the Excel footer ---
$daysInMonth = (int)date('t', strtotime($startDate));
$today = date('Y-m-d');

// Working days (Mon–Fri, excluding holidays/off)
$workingDays = 0;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $ts      = strtotime($dateStr);
    if ($ts === false) {
        continue;
    }

    $dow = (int)date('N', $ts); // 1=Mon..7=Sun

    // Skip weekends
    if ($dow >= 6) {
        continue;
    }

    $status = '';
    if (isset($attendanceByDate[$dateStr])) {
        $status = $attendanceByDate[$dateStr]['attendance_status'] ?? '';
    }

    // Skip holidays / Off days
    if ($status === 'Holiday' || $status === 'Off') {
        continue;
    }

    $workingDays++;
}

$expectedMinutes = $workingDays * SHIFT_MINUTES;
$diffMinutes     = $totalMinutesMonth - $expectedMinutes;

if ($diffMinutes === 0) {
    $diffDisplay = '0:00';
} else {
    $sign        = $diffMinutes < 0 ? '-' : '+';
    $absMinutes  = abs($diffMinutes);
    $diffDisplay = $sign . minutes_to_hhmm($absMinutes);
}

$pageTitle = 'Attendance – ' . htmlspecialchars($user['full_name']);
include 'header.php';
?>
<style>
  /* Make attendance tables a little shorter */
  .table-attendance-compact > thead > tr > th,
  .table-attendance-compact > tbody > tr > td {
      padding-top: 0.12rem;
      padding-bottom: 0.12rem;
  }
</style>

<div class="d-flex justify-content-between align-items-center mt-3 mb-3">
  <!-- LEFT: avatar + heading + month text -->
  <div>
    <div class="d-flex align-items-end mb-1">
      <img
        src="<?php echo $profileImageUrl; ?>"
        alt="User avatar"
        class="rounded-circle me-2"
        style="width: 50px; height: 50px; object-fit: cover;"
      >
      <h3 class="mb-0">
        Attendance – <?php echo htmlspecialchars($user['full_name']); ?>
        (<?php echo htmlspecialchars($user['role']); ?>)
      </h3>
    </div>
    <small class="text-muted">
      Month view for <?php echo date('F Y', strtotime($startDate)); ?>
    </small>
  </div>

  <!-- RIGHT: View Profile (admin) + month navigation -->
  <div class="d-flex align-items-center">
    <?php if ($isAdmin && $userId > 0): ?>
      <a href="profile.php?id=<?php echo (int)$userId; ?>"
         class="btn btn-sm btn-outline-primary me-2">
        View Profile
      </a>
    <?php endif; ?>

    <div class="btn-group" role="group">
      <?php
      $currentTs = strtotime($startDate);
      $prevTs = strtotime('-1 month', $currentTs);
      $nextTs = strtotime('+1 month', $currentTs);

      $prevYear  = (int)date('Y', $prevTs);
      $prevMonth = (int)date('m', $prevTs);
      $nextYear  = (int)date('Y', $nextTs);
      $nextMonth = (int)date('m', $nextTs);
      ?>
      <a class="btn btn-outline-secondary" href="attendance.php?user_id=<?php echo $userId; ?>&year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>">&laquo; Prev</a>
      <span class="btn btn-outline-secondary disabled"><?php echo date('M Y', strtotime($startDate)); ?></span>
      <a class="btn btn-outline-secondary" href="attendance.php?user_id=<?php echo $userId; ?>&year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>">Next &raquo;</a>
    </div>
  </div>
</div>

<div class="alert alert-info py-2">
  <strong>Note:</strong> Off days (Sundays, 1st &amp; 3rd Saturdays) are marked as <span class="badge bg-secondary">Off</span> and shaded.
  Attendance is automatically recorded on login/logout for this user.
</div>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle table-attendance-compact">
    <thead class="table-light">
      <tr>
        <th style="width: 12%; padding-top: 10px; padding-bottom: 10px;">Date</th>
        <th style="width: 10%; padding-top: 10px; padding-bottom: 10px;">Attendance</th>
        <th style="width: 10%; padding-top: 10px; padding-bottom: 10px;">In</th>
        <th style="width: 10%; padding-top: 10px; padding-bottom: 10px;">Out</th>
        <th style="width: 10%; padding-top: 10px; padding-bottom: 10px;">Break (HH:MM)</th>
        <th style="width: 12%; padding-top: 10px; padding-bottom: 10px;">Total Hours</th>
        <th style="width: 12%; padding-top: 10px; padding-bottom: 10px;">Overtime</th>
        <th style="padding-top: 10px; padding-bottom: 10px;">Remarks</th>
        <th style="width: 8%; padding-top: 10px; padding-bottom: 10px;">Action</th>
      </tr>
    </thead>
    <tbody>
<?php
for ($day = 1; $day <= $daysInMonth; $day++):
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $timestamp = strtotime($dateStr);
    $displayDate = date('D, d-M', $timestamp);

    $isOffDay = is_company_off_day($dateStr);

    $attendanceRow = $attendanceByDate[$dateStr] ?? null;

    $attendanceStatus = '';
    $timeInDisplay = '';
    $timeOutDisplay = '';
    $breakDisplay = '00:30';
    $totalDisplay = '';
    $overtimeDisplay = '';
    $remarks = '';
    $attendanceId = null;

    if ($attendanceRow) {
        $attendanceId = (int)$attendanceRow['id'];
        $attendanceStatus = $attendanceRow['attendance_status'] ?: '';

        if ($attendanceRow['time_in']) {
            $timeInDisplay = date('H:i', strtotime($attendanceRow['time_in']));
        }
        if ($attendanceRow['time_out']) {
            $timeOutDisplay = date('H:i', strtotime($attendanceRow['time_out']));
        }

        if ($attendanceRow['break_duration']) {
            $breakDisplay = substr($attendanceRow['break_duration'], 0, 5);
        }

        if ((int)$attendanceRow['total_minutes'] > 0) {
            $totalDisplay = minutes_to_hhmm((int)$attendanceRow['total_minutes']);
        }

        if ((int)$attendanceRow['overtime_minutes'] > 0) {
            $overtimeDisplay = minutes_to_hhmm((int)$attendanceRow['overtime_minutes']);
        }

        $remarks = $attendanceRow['remarks'] ?? '';
    } else {
        if ($isOffDay) {
            $attendanceStatus = 'Off';
        } elseif ($dateStr <= $today) {
            $attendanceStatus = 'A';
        } else {
            $attendanceStatus = '';
        }
    }

    $rowClass = '';
    if ($isOffDay) {
        $rowClass = 'table-secondary';
    } elseif ($attendanceStatus === 'A') {
        $rowClass = 'table-danger';
    } elseif ($attendanceStatus === 'P') {
        $rowClass = 'table-success';
    }
?>
      <tr class="<?php echo $rowClass; ?>">
        <td><?php echo htmlspecialchars($displayDate); ?></td>
        <td><?php echo htmlspecialchars($attendanceStatus); ?></td>
        <td><?php echo htmlspecialchars($timeInDisplay); ?></td>
        <td><?php echo htmlspecialchars($timeOutDisplay); ?></td>
        <td>
          <?php if ($attendanceId): ?>
            <form method="post" action="attendance.php" class="d-flex align-items-center">
              <input type="hidden" name="attendance_id" value="<?php echo $attendanceId; ?>">
              <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
              <input type="hidden" name="year" value="<?php echo $year; ?>">
              <input type="hidden" name="month" value="<?php echo $month; ?>">
              <input type="text" name="break_duration" value="<?php echo htmlspecialchars($breakDisplay); ?>" class="form-control form-control-sm" style="max-width: 80px;">
          <?php else: ?>
            <?php echo htmlspecialchars($breakDisplay); ?>
          <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($totalDisplay); ?></td>
        <td><?php echo htmlspecialchars($overtimeDisplay); ?></td>
        <td>
          <?php if ($attendanceId): ?>
              <input type="text" name="remarks" value="<?php echo htmlspecialchars($remarks); ?>" class="form-control form-control-sm">
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
        <td>
          <?php if ($attendanceId): ?>
              <button type="submit" class="btn btn-sm btn-primary">Save</button>
            </form>
          <?php else: ?>
            -
          <?php endif; ?>
        </td>
      </tr>
<?php endfor; ?>
    </tbody>
  </table>
</div>

<!-- Excel-style summary block -->
<div class="row mt-3">
  <div class="col-md-6 ms-auto">
    <table class="table table-bordered table-sm mb-0 table-attendance-compact">
      <tbody>
        <tr class="table-success">
          <th>Totals</th>
          <td class="text-end"><?php echo minutes_to_hhmm($totalMinutesMonth); ?></td>
          <td class="text-end"><?php echo minutes_to_hhmm($totalOvertimeMinutesMonth); ?></td>
        </tr>
        <tr>
          <th>Working Days (excl. weekends &amp; Holidays)</th>
          <td class="text-end" colspan="2"><?php echo (int)$workingDays; ?></td>
        </tr>
        <tr>
          <th>Expected Hours</th>
          <td class="text-end" colspan="2"><?php echo minutes_to_hhmm($expectedMinutes); ?></td>
        </tr>
        <tr>
          <th>Difference (Total &ndash; Expected)</th>
          <td class="text-end" colspan="2"><?php echo htmlspecialchars($diffDisplay); ?></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
