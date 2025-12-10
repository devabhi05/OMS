<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

$pageTitle = 'Dashboard';
include 'header.php';

$isAdmin    = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isEmployee = isset($_SESSION['role']) && $_SESSION['role'] === 'employee';

// Only load these if admin
$latestUsers      = [];
$latestTimesheets = [];
$overdueTasks     = [];

if ($isAdmin) {
    /* -------------------------------
     * 1) Latest 5 users
     *    (users table: full_name, role, created_at)
     * ----------------------------- */
    $sqlUsers = "
        SELECT id, full_name, role, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 5
    ";
    if ($stmt = $mysqli->prepare($sqlUsers)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $latestUsers[] = $row;
        }
        $stmt->close();
    }

    /* -------------------------------
     * 2) Latest 5 timesheet entries
     *    (timesheets: work_date, hours; join users.full_name, tasks.title)
     * ----------------------------- */
		$sqlTimesheets = "
			SELECT 
				ts.id,
				ts.work_date,
				ts.hours,
				u.full_name AS employee_name,
				t.title     AS task_title
			FROM timesheets ts
			INNER JOIN users u ON ts.user_id = u.id
			INNER JOIN tasks t ON ts.task_id = t.id
			WHERE ts.work_date = CURDATE()
			ORDER BY ts.work_date DESC, ts.id DESC
		";

    if ($stmt = $mysqli->prepare($sqlTimesheets)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $latestTimesheets[] = $row;
        }
        $stmt->close();
    }

    /* -------------------------------
     * 3) Tasks taking too long (up to 5)
     *    tasks: id, title, description, created_by, status, created_at
     *    We'll treat as "taking too long" when:
     *      status != 'completed' AND created_at < (today - 7 days)
     * ----------------------------- */
    $sqlOverdue = "
        SELECT
            t.id,
            t.title,
            t.created_at,
            t.status,
            u.full_name AS created_by_name
        FROM tasks t
        LEFT JOIN users u ON t.created_by = u.id
        WHERE 
            t.status <> 'completed'
            AND t.created_at < DATE_SUB(CURDATE(), INTERVAL 2 DAY)
        ORDER BY t.created_at ASC
        LIMIT 5
    ";
    if ($stmt = $mysqli->prepare($sqlOverdue)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $overdueTasks[] = $row;
        }
        $stmt->close();
    }
}

// Basic global stats
$stats = ['users' => 0, 'tasks' => 0, 'hours' => 0];

$res = $mysqli->query("SELECT COUNT(*) AS c FROM users");
if ($res) {
    $row = $res->fetch_assoc();
    $stats['users'] = (int)$row['c'];
}

$res = $mysqli->query("SELECT COUNT(*) AS c FROM tasks");
if ($res) {
    $row = $res->fetch_assoc();
    $stats['tasks'] = (int)$row['c'];
}

$res = $mysqli->query("SELECT COALESCE(SUM(hours),0) AS h FROM timesheets");
if ($res) {
    $row = $res->fetch_assoc();
    $stats['hours'] = (float)$row['h'];
}

// Employee-specific data for personal summary
$employeeCompleted = 0;
$todayTasks        = [];
$weekDaily = [
    'Mon' => 0.0,
    'Tue' => 0.0,
    'Wed' => 0.0,
    'Thu' => 0.0,
    'Fri' => 0.0,
    'Sat' => 0.0,
    'Sun' => 0.0,
];
$hoursWeek       = 0.0;
$hoursMonth      = 0.0;
$weeklyTarget    = 40.0; // you can change this if needed
$progressPercent = 0;
$inProgressTasks = [];

if ($isEmployee) {
    $userId = (int)$_SESSION['user_id'];

    // Number of completed tasks for this employee - manager-approved 'completed' entries
    $stmt = $mysqli->prepare("
        SELECT COUNT(DISTINCT ts.task_id) AS c
        FROM timesheets ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.user_id = ?
          AND ts.manager_approved = 1
          AND LOWER(TRIM(ts.status)) = 'completed'
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $employeeCompleted = (int)$row['c'];
        }
        $stmt->close();
    }

    // Today's tasks & logged hours (grouped by task)
    $stmt = $mysqli->prepare("
        SELECT t.id, t.title, COALESCE(SUM(ts.hours),0) AS total_hours
        FROM timesheets ts
        JOIN tasks t ON ts.task_id = t.id
        WHERE ts.user_id = ? AND ts.work_date = CURDATE()
        GROUP BY t.id, t.title
        ORDER BY t.title
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $todayTasks[] = $row;
        }
        $stmt->close();
    }

    // Weekly per-day hours (Mon–Sun) and total hours this week
    $stmt = $mysqli->prepare("
        SELECT ts.work_date, COALESCE(SUM(ts.hours),0) AS h
        FROM timesheets ts
        WHERE ts.user_id = ?
          AND YEARWEEK(ts.work_date, 1) = YEARWEEK(CURDATE(), 1)
        GROUP BY ts.work_date
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $date = $row['work_date'];
            $h    = (float)$row['h'];
            $dayIndex = (int)date('N', strtotime($date)); // 1 (Mon) to 7 (Sun)
            $map = [
                1 => 'Mon',
                2 => 'Tue',
                3 => 'Wed',
                4 => 'Thu',
                5 => 'Fri',
                6 => 'Sat',
                7 => 'Sun',
            ];
            if (isset($map[$dayIndex])) {
                $weekDaily[$map[$dayIndex]] = $h;
            }
        }
        $stmt->close();
    }

    $hoursWeek = array_sum($weekDaily);
    if ($weeklyTarget > 0) {
        $progressPercent = (int)round(min(100, ($hoursWeek / $weeklyTarget) * 100));
    }

    // Total hours this month
    $stmt = $mysqli->prepare("
        SELECT COALESCE(SUM(ts.hours),0) AS h
        FROM timesheets ts
        WHERE ts.user_id = ?
          AND YEAR(ts.work_date) = YEAR(CURDATE())
          AND MONTH(ts.work_date) = MONTH(CURDATE())
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $hoursMonth = (float)$row['h'];
        }
        $stmt->close();
    }

    // In-progress tasks for this employee
    $stmt = $mysqli->prepare("
        SELECT DISTINCT t.id, t.title
        FROM tasks t
        JOIN timesheets ts ON ts.task_id = t.id
        WHERE ts.user_id = ? AND t.status = 'in_progress'
        ORDER BY t.title
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $inProgressTasks[] = $row;
        }
        $stmt->close();
    }
}
?>

<div class="row mb-4">
  <div class="col">
    <h1 class="h3">
      <?php if ($_SESSION['role'] === 'admin'): ?>
        Admin Dashboard
      <?php elseif ($_SESSION['role'] === 'manager'): ?>
        Manager Dashboard
      <?php else: ?>
        Employee Dashboard
      <?php endif; ?>
    </h1>
    <p>You are logged in as <strong><?php echo htmlspecialchars($_SESSION['role']); ?></strong>.</p>
  </div>
</div>

<!-- Top summary cards -->
<div class="row g-3 mb-4">
  <!-- Card 1: Total Users / Completed Tasks -->
  <div class="col-md-4">
    <div class="card border-primary">
      <?php if ($isAdmin): ?>
        <a href="manage_users.php" class="text-decoration-none text-reset">
      <?php endif; ?>
        <div class="card-body">
          <h5 class="card-title">
            <?php echo $isEmployee ? 'Completed Tasks' : 'Total Users'; ?>
          </h5>
          <p class="display-6 mb-0">
            <?php echo $isEmployee ? $employeeCompleted : $stats['users']; ?>
          </p>
        </div>
      <?php if ($isAdmin): ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Card 2: Total Tasks -->
  <div class="col-md-4">
    <div class="card border-success">
      <?php if ($isAdmin): ?>
        <a href="tasks.php" class="text-decoration-none text-reset">
      <?php endif; ?>
        <div class="card-body">
          <h5 class="card-title">Total Tasks</h5>
          <p class="display-6 mb-0"><?php echo $stats['tasks']; ?></p>
        </div>
      <?php if ($isAdmin): ?>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Card 3: Hours Logged -->
  <div class="col-md-4">
    <div class="card border-warning">
      <?php if ($isAdmin): ?>
        <a href="admin_daily_log.php" class="text-decoration-none text-reset">
      <?php endif; ?>
        <div class="card-body">
          <h5 class="card-title">Hours Logged</h5>
          <p class="display-6 mb-0"><?php echo number_format($stats['hours'], 2); ?></p>
        </div>
      <?php if ($isAdmin): ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($isAdmin): ?>
  <!-- Quick Actions for Admin -->
  <div class="card mb-4">
    <div class="card-header">
      <strong>Quick Actions</strong>
    </div>
    <div class="card-body d-flex flex-wrap gap-2">
      <a href="add_user.php" class="btn btn-primary btn-sm me-2 mb-2">
        + Add New User
      </a>
      <a href="report_tasks.php" class="btn btn-outline-success btn-sm me-2 mb-2">
        Task Time Report
      </a>
      <a href="attendance.php" class="btn btn-outline-dark btn-sm me-2 mb-2">
        Attendance
      </a>
      <a href="admin_daily_log.php" class="btn btn-outline-dark btn-sm me-2 mb-2">
        Admin Daily Log
      </a>
	  <a href="view_profiles.php" class="btn btn-outline-dark btn-sm me-2 mb-2">
        View Employee Profiles
      </a>
    </div>
  </div>
<?php endif; ?>

<?php if ($isAdmin): ?>
    <!-- My Quick Overview (Admin only) - 3 separate cards -->
    <div class="row mb-4 g-3">
        <!-- Card 1: Latest Users -->
        <div class="col-12 col-lg-4">
            <div class="card h-100">
				<div class="card-header d-flex justify-content-between align-items-center">
					<strong>Latest Users</strong>
					<span class="badge bg-secondary">
						<?php echo count($latestUsers); ?>
					</span>
				</div>
                <div class="card-body">
                    <?php if (!empty($latestUsers)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">User Name</th>
                                        <th scope="col">Role</th>
                                        <th scope="col">Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($latestUsers as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $created = !empty($user['created_at'])
                                                ? date('d M Y', strtotime($user['created_at']))
                                                : '-';
                                            echo htmlspecialchars($created);
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No users found yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card 2: Latest Timesheet Entries -->
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
					<strong>Today's Timesheet Entries</strong>
					<span class="badge bg-primary">
						<?php echo count($latestTimesheets); ?>
					</span>
				</div>

                <div class="card-body">
                    <?php if (!empty($latestTimesheets)): ?>
                    <div id="latestTimesheetScroll" class="table-responsive timesheet-scroll">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Employee</th>
                                        <th scope="col">Task</th>
                                        <th scope="col">Date</th>
                                        <th scope="col">Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($latestTimesheets as $ts): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ts['employee_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars(mb_strimwidth($ts['task_title'], 0, 18, '…')); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $tsDate = !empty($ts['work_date'])
                                                ? date('d M', strtotime($ts['work_date']))
                                                : '-';
                                            echo htmlspecialchars($tsDate);
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars(number_format($ts['hours'], 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No timesheet entries yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Card 3: Tasks Taking Too Long -->
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <strong>Tasks Taking Too Long</strong>
                </div>
                <div class="card-body">
                    <?php if (!empty($overdueTasks)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Task</th>
                                        <th scope="col">Created By</th>
                                        <th scope="col">Created On</th>
                                        <th scope="col">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($overdueTasks as $task): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars(mb_strimwidth($task['title'], 0, 18, '…')); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($task['created_by_name'] ?? 'Unknown'); ?>
                                        </td>
                                        <td>
                                            <?php
                                            $createdOn = !empty($task['created_at'])
                                                ? date('d M Y', strtotime($task['created_at']))
                                                : '-';
                                            echo htmlspecialchars($createdOn);
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo htmlspecialchars(ucfirst($task['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">No long-running tasks. 🎉</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($isEmployee): ?>
<!-- Employee personal summary panel -->
<div class="row mb-4">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">
        <strong>My Summary</strong>
      </div>
      <div class="card-body">
        <!-- Today's Tasks & Logged Hours -->
        <div class="mb-4">
          <h5 class="card-title mb-2">Today&rsquo;s Tasks &amp; Logged Hours</h5>
          <?php if (!$todayTasks): ?>
            <p class="text-muted mb-0">No hours logged yet for today.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($todayTasks as $t): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                  <span><?php echo htmlspecialchars($t['title']); ?></span>
                  <span class="fw-semibold">
                    <?php echo number_format((float)$t['total_hours'], 2); ?> h
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <!-- Weekly View (Mon–Sun) with per-day hours + progress bar -->
        <div class="mb-4">
          <h5 class="card-title mb-2">Weekly View (Mon&ndash;Sun)</h5>

          <?php
          $dayLabels = [
              'Mon' => 'Mon',
              'Tue' => 'Tue',
              'Wed' => 'Wed',
              'Thu' => 'Thu',
              'Fri' => 'Fri',
              'Sat' => 'Sat',
              'Sun' => 'Sun',
          ];
          ?>

          <div class="row row-cols-7 g-1 text-center small">
            <?php foreach ($dayLabels as $key => $label): ?>
              <div class="col">
                <div class="border rounded p-1">
                  <div class="text-muted"><?php echo $label; ?></div>
                  <div class="fw-semibold">
                    <?php echo number_format((float)($weekDaily[$key] ?? 0), 2); ?>h
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-3">
            <?php $progressLabel = number_format($hoursWeek, 2) . ' / ' . number_format($weeklyTarget, 2) . ' hours this week'; ?>
            <div class="d-flex justify-content-between mb-1">
              <span class="small text-muted">Weekly Progress</span>
              <span class="small fw-semibold"><?php echo $progressLabel; ?></span>
            </div>
            <div class="progress" style="height: .75rem;">
              <div class="progress-bar" role="progressbar"
                   style="width: <?php echo $progressPercent; ?>%;"
                   aria-valuenow="<?php echo $progressPercent; ?>"
                   aria-valuemin="0" aria-valuemax="100">
              </div>
            </div>
          </div>
        </div>

        <!-- Total Hours This Month -->
        <div class="mb-4">
          <h5 class="card-title mb-2">Total Hours This Month</h5>
          <div class="border rounded p-3">
            <div class="fs-5 fw-semibold mb-0">
              <?php echo number_format((float)$hoursMonth, 2); ?> h
            </div>
          </div>
        </div>

        <!-- In-Progress Tasks -->
        <div class="mb-3">
          <h5 class="card-title mb-2">In-Progress Tasks</h5>
          <?php if (!$inProgressTasks): ?>
            <p class="text-muted mb-0">You have no in-progress tasks right now.</p>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($inProgressTasks as $t): ?>
                <li class="list-group-item px-0">
                  <?php echo htmlspecialchars($t['title']); ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <!-- Log Time Shortcut -->
        <div class="mt-3">
          <a href="timesheets.php" class="btn btn-primary">Log Time</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Role-specific info banner -->
<?php if ($_SESSION['role'] === 'admin'): ?>
  <div class="alert alert-info">
    <strong>Admin:</strong> You can edit any timesheet and manage all tasks and reports.
  </div>
<?php elseif ($_SESSION['role'] === 'manager'): ?>
  <div class="alert alert-info">
    <strong>Manager:</strong> Use Tasks and Task Report to manage team work.
  </div>
<?php else: ?>
  <div class="alert alert-info">
    <strong>Employee:</strong> Use Timesheets to log your work on tasks.
  </div>
<?php endif; ?>

<?php include 'footer.php'; ?>
