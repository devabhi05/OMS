<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'attendance_helpers.php';

require_login();
$pageTitle = 'User Profile';

// Decide which user to show
$viewUserId = $_SESSION['user_id'] ?? null;

// If admin and ?id=N is passed, allow viewing that user
if (function_exists('current_user_role')
    && current_user_role() === 'admin'
    && isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $viewUserId = (int)$_GET['id'];
}

if (!$viewUserId) {
    die('No user selected.');
}

// Fetch user from DB
$sql = "SELECT 
            id,
            full_name,
            first_name,
            last_name,
            username,
            role,
            email,
            mobile,
            phone,
            address,
            social_facebook,
            social_linkedin,
            social_instagram,
            social_twitter,
            social_other,
            profile_image,
            created_at
        FROM users
        WHERE id = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    die('DB error: ' . $mysqli->error);
}
$stmt->bind_param('i', $viewUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found.');
}

// Prepare display values
$displayName = $user['full_name'];
if (!$displayName) {
    $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = $user['username'];
}

$roleLabel = ucfirst($user['role'] ?? '');

// Profile image: use uploaded path if available, otherwise fallback avatar
if (!empty($user['profile_image'])) {
    $profileImageUrl = htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8');
} else {
    $profileImageUrl = '\sarvjeet3\images\user.png';
}

// Safe output helper
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}


// --- KPI calculations for profile bottom cards ---

// Current month range (inclusive)
$startOfMonth = date('Y-m-01');
$endOfMonth   = date('Y-m-t');

// Initialize all KPI values with safe defaults
$kpi_projects_active        = 0;
$kpi_projects_total         = 0;
$kpi_projects_percent       = 0;

$kpi_tasks_total_month      = 0;
$kpi_tasks_completed_month  = 0;
$kpi_tasks_completed_percent = 0;

$kpi_hours_month            = 0.0;
$kpi_target_hours           = 0.0;
$kpi_hours_percent          = 0;

$kpi_days_present           = 0;
$kpi_working_days           = 0;
$kpi_presence_percent       = 0;

$kpi_total_minutes_month    = 0;
$kpi_expected_minutes       = 0;
$kpi_utilization_percent    = 0;

// Card 2
$kpi_on_time_total          = 0;
$kpi_on_time_count          = 0;
$kpi_on_time_percent        = 0;

$kpi_overdue_related        = 0;
$kpi_tasks_related          = 0;
$kpi_overdue_health_percent = 0;

$kpi_tasks_closed           = 0;
$kpi_tasks_with_work        = 0;
$kpi_tasks_closed_percent   = 0;

$kpi_avg_days_to_complete   = null;
$kpi_speed_percent          = 0;

// Helper: clamp percentage between 0 and 100
function profile_clamp_percent($value) {
    $v = (float)$value;
    if ($v < 0)   { $v = 0; }
    if ($v > 100) { $v = 100; }
    return (int)round($v);
}

// ------------------------
// Card 1 – Work Summary
// ------------------------

// 1) Projects Active this month vs all-time
$stmt = $mysqli->prepare("
    SELECT COUNT(DISTINCT t.project_id) AS projects_active
    FROM timesheets ts
    JOIN tasks t ON ts.task_id = t.id
    WHERE ts.user_id = ?
      AND ts.work_date BETWEEN ? AND ?
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $kpi_projects_active = (int)$row['projects_active'];
    }
    $stmt->close();
}

$stmt = $mysqli->prepare("
    SELECT COUNT(DISTINCT t.project_id) AS projects_total
    FROM timesheets ts
    JOIN tasks t ON ts.task_id = t.id
    WHERE ts.user_id = ?
");
if ($stmt) {
    $stmt->bind_param('i', $viewUserId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $kpi_projects_total = (int)$row['projects_total'];
    }
    $stmt->close();
}
if ($kpi_projects_total > 0) {
    $kpi_projects_percent = profile_clamp_percent(($kpi_projects_active / $kpi_projects_total) * 100);
}

// 2) Tasks Completed this month vs tasks worked this month
$stmt = $mysqli->prepare("
    SELECT COUNT(DISTINCT ts.task_id) AS c
    FROM timesheets ts
    WHERE ts.user_id = ?
      AND ts.work_date BETWEEN ? AND ?
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $kpi_tasks_total_month = (int)$row['c'];
    }
    $stmt->close();
}

$stmt = $mysqli->prepare("
    SELECT COUNT(DISTINCT ts.task_id) AS c
    FROM timesheets ts
    WHERE ts.user_id = ?
      AND ts.work_date BETWEEN ? AND ?
      AND ts.manager_approved = 1
      AND LOWER(TRIM(ts.status)) = 'completed'
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $kpi_tasks_completed_month = (int)$row['c'];
    }
    $stmt->close();
}
if ($kpi_tasks_total_month > 0) {
    $kpi_tasks_completed_percent = profile_clamp_percent(($kpi_tasks_completed_month / $kpi_tasks_total_month) * 100);
}

// 3) Hours Logged this month vs target hours (based on working days)
$stmt = $mysqli->prepare("
    SELECT COALESCE(SUM(ts.hours),0) AS h
    FROM timesheets ts
    WHERE ts.user_id = ?
      AND ts.work_date BETWEEN ? AND ?
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $kpi_hours_month = (float)$row['h'];
    }
    $stmt->close();
}

// Attendance-based working days & expected minutes (similar to attendance.php)
$attendanceByDate      = [];
$totalMinutesMonth_att = 0;
$totalOvertimeMinutesMonth_att = 0;

$stmt = $mysqli->prepare("
    SELECT * 
    FROM attendance 
    WHERE user_id = ? 
      AND work_date BETWEEN ? AND ?
    ORDER BY work_date ASC
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dateKey = $row['work_date'];
        $attendanceByDate[$dateKey] = $row;

        if (!empty($row['total_minutes'])) {
            $totalMinutesMonth_att += (int)$row['total_minutes'];
        }
        if (!empty($row['overtime_minutes'])) {
            $totalOvertimeMinutesMonth_att += (int)$row['overtime_minutes'];
        }
    }
    $stmt->close();
}

// Compute working days in this month (Mon–Fri, excluding Off/Holiday)
$year  = (int)date('Y', strtotime($startOfMonth));
$month = (int)date('m', strtotime($startOfMonth));
$daysInMonth = (int)date('t', strtotime($startOfMonth));

$kpi_working_days = 0;
for ($d = 1; $d <= $daysInMonth; $d++) {
    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
    $tsDate  = strtotime($dateStr);
    if ($tsDate === false) {
        continue;
    }

    $dow = (int)date('N', $tsDate); // 1=Mon..7=Sun

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

    $kpi_working_days++;
}

$kpi_total_minutes_month = $totalMinutesMonth_att;
$kpi_expected_minutes    = $kpi_working_days * SHIFT_MINUTES;
$kpi_target_hours        = $kpi_expected_minutes > 0 ? ($kpi_expected_minutes / 60.0) : 0.0;

if ($kpi_target_hours > 0) {
    $kpi_hours_percent = profile_clamp_percent(($kpi_hours_month / $kpi_target_hours) * 100);
}

// 4) Working days present (P or Half) vs working days
$kpi_days_present = 0;
foreach ($attendanceByDate as $dateStr => $row) {
    $status = $row['attendance_status'] ?? '';
    if ($status === 'P' || $status === 'Half') {
        $kpi_days_present++;
    }
}
if ($kpi_working_days > 0) {
    $kpi_presence_percent = profile_clamp_percent(($kpi_days_present / $kpi_working_days) * 100);
}

// 5) Utilization based on attendance (actual vs expected minutes)
if ($kpi_expected_minutes > 0) {
    $kpi_utilization_percent = profile_clamp_percent(($kpi_total_minutes_month / $kpi_expected_minutes) * 100);
}

// -----------------------------
// Card 2 – Quality & Timeliness
// -----------------------------

// 1) On-time task completion (completed this month with due date)
$stmt = $mysqli->prepare("
    SELECT
        COUNT(DISTINCT t.id) AS total_completed_with_due,
        COUNT(DISTINCT CASE
            WHEN ts.work_date <= t.due_date THEN t.id
        END) AS on_time
    FROM timesheets ts
    JOIN tasks t ON ts.task_id = t.id
    WHERE ts.user_id = ?
      AND ts.manager_approved = 1
      AND LOWER(TRIM(ts.status)) = 'completed'
      AND ts.work_date BETWEEN ? AND ?
      AND t.due_date IS NOT NULL
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $kpi_on_time_total = (int)$row['total_completed_with_due'];
        $kpi_on_time_count = (int)$row['on_time'];
    }
    $stmt->close();
}
if ($kpi_on_time_total > 0) {
    $kpi_on_time_percent = profile_clamp_percent(($kpi_on_time_count / $kpi_on_time_total) * 100);
}

// 2) Overdue tasks among tasks touched this month
$stmt = $mysqli->prepare("
    SELECT
        COUNT(DISTINCT t.id) AS total_related,
        COUNT(DISTINCT CASE
            WHEN t.due_date IS NOT NULL
             AND t.due_date < CURDATE()
             AND t.status <> 'completed'
            THEN t.id
        END) AS overdue_related
    FROM timesheets ts
    JOIN tasks t ON ts.task_id = t.id
    WHERE ts.user_id = ?
      AND ts.work_date BETWEEN ? AND ?
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $kpi_tasks_related   = (int)$row['total_related'];
        $kpi_overdue_related = (int)$row['overdue_related'];
    }
    $stmt->close();
}
if ($kpi_tasks_related > 0) {
    $overdue_ratio              = $kpi_overdue_related / $kpi_tasks_related;
    $kpi_overdue_health_percent = profile_clamp_percent((1 - $overdue_ratio) * 100);
} else {
    $kpi_overdue_health_percent = 100; // no tasks => no overdue
}

// 3) Manager-approved completions ratio (reusing monthly completed vs worked)
$kpi_manager_approved_percent = 0;
if ($kpi_tasks_total_month > 0) {
    $kpi_manager_approved_percent = profile_clamp_percent(($kpi_tasks_completed_month / $kpi_tasks_total_month) * 100);
}

// 4) Tasks closed (global status completed) among tasks touched this month
$stmt = $mysqli->prepare("
    SELECT
        COUNT(DISTINCT ts.task_id) AS tasks_with_work,
        COUNT(DISTINCT CASE
            WHEN t.status = 'completed' THEN ts.task_id
        END) AS tasks_closed
    FROM timesheets ts
    JOIN tasks t ON ts.task_id = t.id
    WHERE ts.user_id = ?
      AND ts.work_date BETWEEN ? AND ?
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $kpi_tasks_with_work = (int)$row['tasks_with_work'];
        $kpi_tasks_closed    = (int)$row['tasks_closed'];
    }
    $stmt->close();
}
if ($kpi_tasks_with_work > 0) {
    $kpi_tasks_closed_percent = profile_clamp_percent(($kpi_tasks_closed / $kpi_tasks_with_work) * 100);
}

// 5) Average completion time for manager-approved completed tasks this month
$stmt = $mysqli->prepare("
    SELECT AVG(DATEDIFF(ts.work_date, DATE(t.created_at))) AS avg_days_to_complete
    FROM timesheets ts
    JOIN tasks t ON ts.task_id = t.id
    WHERE ts.user_id = ?
      AND ts.manager_approved = 1
      AND LOWER(TRIM(ts.status)) = 'completed'
      AND ts.work_date BETWEEN ? AND ?
");
if ($stmt) {
    $stmt->bind_param('iss', $viewUserId, $startOfMonth, $endOfMonth);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        // avg_days_to_complete can be NULL
        $kpi_avg_days_to_complete = $row['avg_days_to_complete'] !== null
            ? (float)$row['avg_days_to_complete']
            : null;
    }
    $stmt->close();
}

// Map average days to a 0–100 speed score (target 2 days)
$targetDays = 2.0;
if ($kpi_avg_days_to_complete !== null && $kpi_avg_days_to_complete > 0) {
    if ($kpi_avg_days_to_complete <= $targetDays) {
        $kpi_speed_percent = 100;
    } else {
        $kpi_speed_percent = profile_clamp_percent(($targetDays / $kpi_avg_days_to_complete) * 100);
    }
}

include 'header.php';
?>
<style>
  body {
    background-color: #e5e8e8;
  }
  
  .card {
    padding: 1rem;
  }
  
  hr {
    margin-top: 0.25rem;
  }

  /* Make the top profile cards visually same height */
  .profile-main-card {
    min-height: 340px; /* adjust this number if you want taller/shorter */
  }

  /* Optional: on large screens you can bump it slightly */
  @media (min-width: 992px) {
    .profile-main-card {
      min-height: 380px;
    }
  }
</style>

<section>
  <div class="container py-5">
    <div class="row">
      <!-- LEFT COLUMN: Avatar + basic info + socials -->
      <div class="col-lg-3">
        <div class="card mb-4 profile-main-card">
          <div class="card-body text-center">
            <img src="<?php echo $profileImageUrl; ?>" alt="avatar"
              class="rounded-circle img-fluid"
              style="width: 125px; height: 125px; object-fit: cover;">
            <h5 class="my-3"><?php echo e($displayName); ?></h5>
            <p class="text-muted mb-1"><?php echo e($roleLabel); ?></p>
            <p class="text-muted mb-4 w-75 mx-auto">
              <?php echo $user['address'] ? e($user['address']) : 'No address provided'; ?>
            </p>
            <div class="d-flex justify-content-center mb-2">
              <!-- Edit button -->
				<a href="edit_user.php?id=<?php echo (int)$user['id']; ?>"
				   class="btn btn-primary btn-sm fw-bold px-3">
				  Edit
				</a>

              <a href="manage_users.php"
                 class="btn btn-outline-primary btn-sm ms-1"
>
                Back
              </a>
            </div>
          </div>
        </div>

        <div class="card mb-4 mb-lg-0">
          <div class="card-body p-0">
            <ul class="list-group list-group-flush rounded-3">
              <?php if (!empty($user['social_other'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fas fa-globe fa-lg text-warning"></i>
                  <p class="mb-0"><?php echo e($user['social_other']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (!empty($user['social_facebook'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fab fa-facebook-f fa-lg" style="color: #3b5998;"></i>
                  <p class="mb-0"><?php echo e($user['social_facebook']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (!empty($user['social_instagram'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fab fa-instagram fa-lg" style="color: #ac2bac;"></i>
                  <p class="mb-0"><?php echo e($user['social_instagram']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (!empty($user['social_twitter'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fab fa-twitter fa-lg" style="color: #55acee;"></i>
                  <p class="mb-0"><?php echo e($user['social_twitter']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (!empty($user['social_linkedin'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fab fa-linkedin fa-lg" style="color: #0a66c2;"></i>
                  <p class="mb-0"><?php echo e($user['social_linkedin']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (
                empty($user['social_facebook']) &&
                empty($user['social_instagram']) &&
                empty($user['social_twitter']) &&
                empty($user['social_linkedin']) &&
                empty($user['social_other'])
              ): ?>
                <li class="list-group-item d-flex justify-content-center align-items-center p-3">
                  <p class="mb-0 text-muted small">No social links saved.</p>
                </li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Detailed profile info + bottom cards -->
      <div class="col-lg-9">
        <div class="card mb-4 profile-main-card">
          <div class="card-body">
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Full Name</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0"><?php echo e($displayName); ?></p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Username</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0"><?php echo e($user['username']); ?></p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Role</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0"><?php echo e($roleLabel); ?></p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Email</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo $user['email'] ? e($user['email']) : 'Not set'; ?>
                </p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Phone</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo $user['phone'] ? e($user['phone']) : 'Not set'; ?>
                </p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Mobile</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo $user['mobile'] ? e($user['mobile']) : 'Not set'; ?>
                </p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Address</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo $user['address'] ? nl2br(e($user['address'])) : 'Not set'; ?>
                </p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Created At</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo e($user['created_at']); ?>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom two cards: dynamic KPIs -->
        <div class="row">
          <!-- Card 1: Work Summary (This Month) -->
          <div class="col-md-6">
            <div class="card mb-4 mb-md-0">
              <div class="card-body">
                <p class="mb-4">
                  <span class="text-primary font-italic me-1">assignment</span> Work Summary (This Month)
                </p>

                <!-- Projects Active -->
                <p class="mb-1" style="font-size: .77rem;">Projects Active</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_projects_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_projects_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mt-1" style="font-size: .72rem;">
                  <?php echo e($kpi_projects_active); ?> / <?php echo e($kpi_projects_total); ?> projects this month
                </small>

                <!-- Tasks Completed -->
                <p class="mt-4 mb-1" style="font-size: .77rem;">Tasks Completed</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_tasks_completed_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_tasks_completed_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mt-1" style="font-size: .72rem;">
                  <?php echo e($kpi_tasks_completed_month); ?> / <?php echo e($kpi_tasks_total_month); ?> tasks completed
                </small>

                <!-- Hours Logged -->
                <p class="mt-4 mb-1" style="font-size: .77rem;">Hours Logged</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_hours_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_hours_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mt-1" style="font-size: .72rem;">
                  <?php echo e(number_format($kpi_hours_month, 1)); ?> h
                  /
                  <?php echo e(number_format($kpi_target_hours, 1)); ?> h target
                </small>

                <!-- Working Days -->
                <p class="mt-4 mb-1" style="font-size: .77rem;">Working Days</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_presence_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_presence_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mt-1" style="font-size: .72rem;">
                  <?php echo e($kpi_days_present); ?> / <?php echo e($kpi_working_days); ?> working days present
                </small>

                <!-- Utilization -->
    <!--            <p class="mt-4 mb-1" style="font-size: .77rem;">Utilization</p>
                <div class="progress rounded mb-2" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_utilization_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_utilization_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block" style="font-size: .72rem;">
                  <?php
                    $utilHoursActual = $kpi_total_minutes_month > 0 ? ($kpi_total_minutes_month / 60.0) : 0;
                    $utilHoursTarget = $kpi_expected_minutes > 0 ? ($kpi_expected_minutes / 60.0) : 0;
                  ?>
                  <?php echo e(number_format($utilHoursActual, 1)); ?> h logged vs
                  <?php echo e(number_format($utilHoursTarget, 1)); ?> h expected
                </small>		-->
              </div>
            </div>
          </div>

          <!-- Card 2: Quality & Timeliness -->
          <div class="col-md-6">
            <div class="card mb-4 mb-md-0">
              <div class="card-body">
                <p class="mb-4">
                  <span class="text-primary font-italic me-1">assignment</span> Quality &amp; Timeliness
                </p>

                <!-- On-time Completion -->
                <p class="mb-1" style="font-size: .77rem;">On-time Completion</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_on_time_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_on_time_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mt-1" style="font-size: .72rem;">
                  <?php echo e($kpi_on_time_count); ?> / <?php echo e($kpi_on_time_total); ?> tasks completed on or before due date
                </small>

                <!-- Overdue Tasks Health -->
                <p class="mt-4 mb-1" style="font-size: .77rem;">Overdue Tasks</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_overdue_health_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_overdue_health_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mt-1" style="font-size: .72rem;">
                  <?php echo e($kpi_overdue_related); ?> overdue out of <?php echo e($kpi_tasks_related); ?> tracked tasks
                </small>

                <!-- Manager-approved Completions -->
                <p class="mt-4 mb-1" style="font-size: .77rem;">Manager-approved Completions</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_manager_approved_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_manager_approved_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mt-1" style="font-size: .72rem;">
                  <?php echo e($kpi_tasks_completed_month); ?> / <?php echo e($kpi_tasks_total_month); ?> tasks with manager-approved completion
                </small>

                <!-- Tasks Closed -->
                <p class="mt-4 mb-1" style="font-size: .77rem;">Tasks Closed (This Month)</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_tasks_closed_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_tasks_closed_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block mt-1" style="font-size: .72rem;">
                  <?php echo e($kpi_tasks_closed); ?> / <?php echo e($kpi_tasks_with_work); ?> tasks globally closed
                </small>

                <!-- Average Completion Time -->
               <!-- <p class="mt-4 mb-1" style="font-size: .77rem;">Avg. Completion Time</p>
                <div class="progress rounded mb-2" style="height: 5px;">
                  <div class="progress-bar" role="progressbar"
                       style="width: <?php echo (int)$kpi_speed_percent; ?>%"
                       aria-valuenow="<?php echo (int)$kpi_speed_percent; ?>"
                       aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <small class="text-muted d-block" style="font-size: .72rem;">
                  <?php if ($kpi_avg_days_to_complete !== null): ?>
                    Avg <?php echo e(number_format($kpi_avg_days_to_complete, 1)); ?> days per task
                    (target <?php echo e(number_format($targetDays, 1)); ?> days)
                  <?php else: ?>
                    Not enough completed tasks this month to calculate.
                  <?php endif; ?>
                </small> -->
              </div>
            </div>
          </div>
        </div> <!-- /row bottom cards -->
      </div>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>
