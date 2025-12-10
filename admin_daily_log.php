<?php
// admin_daily_log.php
require_once 'config.php';
require_once 'auth.php';

require_login();
require_role(['admin']);

$pageTitle = 'Admin Daily Work Log';

// ---------------------------------------------------------
// Load filter options (projects, tasks, employees)
// ---------------------------------------------------------

// Projects
$projects = [];
$projRes = $mysqli->query("SELECT id, name FROM projects ORDER BY name");
if ($projRes) {
    while ($row = $projRes->fetch_assoc()) {
        $projects[] = $row;
    }
}

// Tasks (with project name)
$tasks = [];
$taskSql = "
    SELECT t.id, t.title, p.name AS project_name
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    ORDER BY p.name, t.title
";
$taskRes = $mysqli->query($taskSql);
if ($taskRes) {
    while ($row = $taskRes->fetch_assoc()) {
        $tasks[] = $row;
    }
}

// Employees
$employees = [];
$empRes = $mysqli->query("SELECT id, full_name FROM users WHERE role = 'employee' ORDER BY full_name");
if ($empRes) {
    while ($row = $empRes->fetch_assoc()) {
        $employees[] = $row;
    }
}

// ---------------------------------------------------------
// Read filters from GET
// ---------------------------------------------------------
$filterProjectId  = isset($_GET['project_id'])  ? (int)$_GET['project_id']  : 0;
$filterTaskId     = isset($_GET['task_id'])     ? (int)$_GET['task_id']     : 0;
$filterEmployeeId = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

// Date range
$today        = date('Y-m-d');
$firstOfMonth = date('Y-m-01');

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

if ($dateFrom === '' && $dateTo === '') {
    // default to current month
	// $dateFrom = $firstOfMonth; was set
    $dateFrom = $today;
    $dateTo   = $today;
}

// Simple sanity: if to < from, swap
if ($dateFrom !== '' && $dateTo !== '' && $dateTo < $dateFrom) {
    $tmp = $dateFrom;
    $dateFrom = $dateTo;
    $dateTo = $tmp;
}

// Pagination
$perPage = 50;
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset  = ($page - 1) * $perPage;

// ---------------------------------------------------------
// Build WHERE clause and parameters
// ---------------------------------------------------------
$whereClauses = [];
$params = [];
$types  = '';

if ($filterProjectId > 0) {
    $whereClauses[] = 't.project_id = ?';
    $params[] = $filterProjectId;
    $types   .= 'i';
}

if ($filterTaskId > 0) {
    $whereClauses[] = 'ts.task_id = ?';
    $params[] = $filterTaskId;
    $types   .= 'i';
}

if ($filterEmployeeId > 0) {
    $whereClauses[] = 'ts.user_id = ?';
    $params[] = $filterEmployeeId;
    $types   .= 'i';
}

if ($dateFrom !== '' && $dateTo !== '') {
    $whereClauses[] = 'ts.work_date BETWEEN ? AND ?';
    $params[] = $dateFrom;
    $params[] = $dateTo;
    $types   .= 'ss';
} elseif ($dateFrom !== '') {
    $whereClauses[] = 'ts.work_date >= ?';
    $params[] = $dateFrom;
    $types   .= 's';
} elseif ($dateTo !== '') {
    $whereClauses[] = 'ts.work_date <= ?';
    $params[] = $dateTo;
    $types   .= 's';
}

$whereSql = $whereClauses ? implode(' AND ', $whereClauses) : '1=1';

// ---------------------------------------------------------
// Count total rows for pagination
// ---------------------------------------------------------
$countSql = "
    SELECT COUNT(*) AS total
    FROM timesheets ts
    JOIN tasks t ON ts.task_id = t.id
    LEFT JOIN projects p ON t.project_id = p.id
    JOIN users u ON ts.user_id = u.id
    WHERE $whereSql
";

$stmtCount = $mysqli->prepare($countSql);
if (!$stmtCount) {
    die('Database error (count).');
}
if ($params) {
    $stmtCount->bind_param($types, ...$params);
}
$stmtCount->execute();
$countRes = $stmtCount->get_result();
$totalRows = 0;
if ($countRes) {
    $row = $countRes->fetch_assoc();
    if ($row) {
        $totalRows = (int)$row['total'];
    }
}
$stmtCount->close();

$totalPages = max(1, (int)ceil($totalRows / $perPage));

// ---------------------------------------------------------
// Fetch paginated result set
// ---------------------------------------------------------
$dataSql = "
    SELECT
        p.name       AS project_name,
        t.title      AS task_name,
        t.status     AS task_status,
        u.full_name  AS employee_name,
        ts.work_date,
        ts.hours,
        ts.notes
    FROM timesheets ts
    JOIN tasks t     ON ts.task_id = t.id
    LEFT JOIN projects p ON t.project_id = p.id
    JOIN users u     ON ts.user_id = u.id
    WHERE $whereSql
    ORDER BY ts.work_date DESC, project_name, task_name, employee_name
    LIMIT $perPage OFFSET $offset
";

$stmtData = $mysqli->prepare($dataSql);
if (!$stmtData) {
    die('Database error (data).');
}
if ($params) {
    $stmtData->bind_param($types, ...$params);
}
$stmtData->execute();
$resData = $stmtData->get_result();

$rows = [];
$totalHoursOnPage = 0.0;

while ($r = $resData->fetch_assoc()) {
    $rows[] = $r;
    $totalHoursOnPage += (float)$r['hours'];
}
$stmtData->close();

include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mt-3 mb-3">
  <div>
    <h3 class="mb-0">Admin Daily Work Log</h3>
    <small class="text-muted">
      Overview of timesheet entries (<?php echo htmlspecialchars($totalRows); ?> record<?php echo $totalRows === 1 ? '' : 's'; ?>)
    </small>
  </div>
</div>

<form method="get" class="row g-2 mb-3 align-items-end">
  <div class="col-md-3">
    <label class="form-label mb-1" for="project_id">Project</label>
    <select name="project_id" id="project_id" class="form-select form-select-sm">
      <option value="0">All projects</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?php echo (int)$p['id']; ?>" <?php if ($filterProjectId == $p['id']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($p['name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-3">
    <label class="form-label mb-1" for="task_id">Task</label>
    <select name="task_id" id="task_id" class="form-select form-select-sm">
      <option value="0">All tasks</option>
      <?php foreach ($tasks as $t): ?>
        <option value="<?php echo (int)$t['id']; ?>" <?php if ($filterTaskId == $t['id']) echo 'selected'; ?>>
          <?php
            $label = $t['title'];
            if (!empty($t['project_name'])) {
                $label = $t['project_name'] . ' – ' . $t['title'];
            }
            echo htmlspecialchars($label);
          ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-3">
    <label class="form-label mb-1" for="employee_id">Employee</label>
    <select name="employee_id" id="employee_id" class="form-select form-select-sm">
      <option value="0">All employees</option>
      <?php foreach ($employees as $e): ?>
        <option value="<?php echo (int)$e['id']; ?>" <?php if ($filterEmployeeId == $e['id']) echo 'selected'; ?>>
          <?php echo htmlspecialchars($e['full_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="col-md-3"></div>

  <div class="col-md-3">
    <label class="form-label mb-1" for="date_from">Date from</label>
    <input type="date" name="date_from" id="date_from"
           value="<?php echo htmlspecialchars($dateFrom); ?>"
           class="form-control form-control-sm">
  </div>
  <div class="col-md-3">
    <label class="form-label mb-1" for="date_to">Date to</label>
    <input type="date" name="date_to" id="date_to"
           value="<?php echo htmlspecialchars($dateTo); ?>"
           class="form-control form-control-sm">
  </div>

  <div class="col-md-3">
    <button type="submit" class="btn btn-primary btn-sm mt-2">Filter</button>
    <a href="admin_daily_log.php" class="btn btn-outline-secondary btn-sm mt-2">Reset</a>
  </div>
</form>

<div class="mb-2">
  <span class="badge bg-info text-dark">
    Total hours on this page: <?php echo number_format($totalHoursOnPage, 2); ?>
  </span>
</div>

<div class="table-responsive">
  <table class="table table-bordered table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th style="width: 18%;">Project</th>
        <th style="width: 18%;">Task</th>
        <th style="width: 18%;">Employee</th>
        <th style="width: 12%;">Work Date</th>
        <th style="width: 10%;">Hours</th>
        <th style="width: 12%;">Status</th>
        <th>Notes</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr>
        <td colspan="7" class="text-center text-muted">No work logs found for the selected filters.</td>
      </tr>
    <?php else: ?>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['project_name'] ?? '—'); ?></td>
          <td><?php echo htmlspecialchars($r['task_name']); ?></td>
          <td><?php echo htmlspecialchars($r['employee_name']); ?></td>
          <td><?php echo htmlspecialchars(date('d-M-Y', strtotime($r['work_date']))); ?></td>
          <td><?php echo htmlspecialchars(number_format((float)$r['hours'], 2)); ?></td>
          <td><?php echo htmlspecialchars($r['task_status']); ?></td>
          <td><?php echo htmlspecialchars($r['notes']); ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($totalPages > 1): ?>
<nav aria-label="Daily log pagination">
  <ul class="pagination pagination-sm justify-content-center">
    <?php
    // keep existing filters in pagination links
    $baseQuery = $_GET;
    ?>
    <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
      <?php
      $baseQuery['page'] = max(1, $page - 1);
      ?>
      <a class="page-link" href="?<?php echo http_build_query($baseQuery); ?>">&laquo; Prev</a>
    </li>

    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <?php $baseQuery['page'] = $p; ?>
      <li class="page-item <?php if ($p == $page) echo 'active'; ?>">
        <a class="page-link" href="?<?php echo http_build_query($baseQuery); ?>"><?php echo $p; ?></a>
      </li>
    <?php endfor; ?>

    <li class="page-item <?php if ($page >= $totalPages) echo 'disabled'; ?>">
      <?php
      $baseQuery['page'] = min($totalPages, $page + 1);
      ?>
      <a class="page-link" href="?<?php echo http_build_query($baseQuery); ?>">Next &raquo;</a>
    </li>
  </ul>
</nav>
<?php endif; ?>

<?php include 'footer.php'; ?>
