<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_role(['manager', 'admin']);

$pageTitle = 'Task Time Report';
$errors = [];
$success = '';

$role = $_SESSION['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $entry_id = isset($_POST['entry_id']) ? (int)$_POST['entry_id'] : 0;

    if ($entry_id > 0) {
        if ($action === 'delete') {
            $stmt = $mysqli->prepare("DELETE FROM timesheets WHERE id = ?");
            $stmt->bind_param('i', $entry_id);
            if ($stmt->execute()) {
                $success = 'Entry deleted successfully.';
            } else {
                $errors[] = 'Failed to delete entry: ' . $stmt->error;
            }
            $stmt->close();
        } elseif ($action === 'approve' && $role === 'manager') {
            $stmt = $mysqli->prepare("UPDATE timesheets SET manager_approved = 1 WHERE id = ?");
            $stmt->bind_param('i', $entry_id);
            if ($stmt->execute()) {
                $success = 'Entry approved successfully.';
            } else {
                $errors[] = 'Failed to approve entry: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$from_date = $_GET['from_date'] ?? '';
$to_date   = $_GET['to_date'] ?? '';

// Sorting for the table
$sort = $_GET['sort'] ?? 'recent';
switch ($sort) {
    case 'id_desc':
        $orderBy = "ts.id DESC";
        break;
    case 'oldest':
        $orderBy = "ts.work_date ASC, ts.created_at ASC, ts.id ASC";
        break;
    case 'recent':
    default:
        $orderBy = "ts.work_date DESC, ts.created_at DESC, ts.id DESC";
        $sort = 'recent';
        break;
}

$where = "1=1";
$params = [];
$types  = '';

if ($from_date !== '') {
    $where    .= " AND ts.work_date >= ?";
    $params[]  = $from_date;
    $types    .= 's';
}
if ($to_date !== '') {
    $where    .= " AND ts.work_date <= ?";
    $params[]  = $to_date;
    $types    .= 's';
}

// Workflow conditions based on role:
// - Manager: see completed entries that are NOT yet approved.
// - Admin: see completed entries that HAVE been approved by a manager.
if ($role === 'manager') {
    // UPDATED: use status column instead of notes
    $where .= " AND ts.manager_approved = 0 AND LOWER(TRIM(ts.status)) = 'completed'";
} elseif ($role === 'admin') {
    // UPDATED: use status column instead of notes
    $where .= " AND ts.manager_approved = 1 AND LOWER(TRIM(ts.status)) = 'completed'";
}

$sql = "SELECT ts.*, 
				t.id AS task_id, 
				t.title AS task_title,
				t.description AS task_description,
				t.status AS task_status, 
				u.full_name AS employee_name
        FROM timesheets ts
        JOIN tasks t ON ts.task_id = t.id
        JOIN users u ON ts.user_id = u.id
        WHERE $where
        ORDER BY $orderBy";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res  = $stmt->get_result();
$rows = [];
while ($row = $res->fetch_assoc()) { $rows[] = $row; }
$stmt->close();

// If CSV export requested, output CSV and exit
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    // CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="task_time_report_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');
    // CSV header row
    fputcsv($output, ['Task', 'Description', 'Person', 'Date Created', 'Date Completed', 'Status']);

    foreach ($rows as $r) {
        $task         = $r['task_title'] ?? '';
		$description  = $r['task_description'] ?? '';
        $person       = $r['employee_name'] ?? '';
        $createdraw   = $r['created_at'] ?? '';   // from timesheets
        $completedraw = $r['work_date'] ?? '';    // work date as completion date

        // Format as date only (Y-m-d)
        $dateCreated   = $createdraw   ? date('Y-m-d', strtotime($createdraw))   : '';
        $dateCompleted = $completedraw ? date('Y-m-d', strtotime($completedraw)) : '';

        if ($role === 'manager') {
            $statusText = 'Completed - Pending Approval';
        } else {
            $statusText = 'Completed';
        }

        fputcsv($output, [
            $task,
			$description,
            $person,
            $dateCreated,
            $dateCompleted,
            $statusText
        ]);
    }

    fclose($output);
    exit;
}

include 'header.php';
?>
<h2 class="mb-4">
  <?php if ($role === 'manager'): ?>
    Manager Task Time Report
  <?php else: ?>
    Admin Task Time Report
  <?php endif; ?>
</h2>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0">
  <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<!-- TOP CARD: only date filters -->
<div class="card mb-3">
  <div class="card-body">
    <form class="row g-3" method="get">
      <div class="col-md-4">
        <label class="form-label">From Date</label>
        <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($from_date); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">To Date</label>
        <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($to_date); ?>">
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <div>
          <button type="submit" class="btn btn-primary me-2">Filter</button>
          <a href="report_tasks.php" class="btn btn-secondary me-2">Reset</a>
          <button type="submit" name="export" value="csv" class="btn btn-outline-success">Export CSV</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- BOTTOM CARD: table + sort filter -->
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>
      <?php if ($role === 'manager'): ?>
        Completed Entries Waiting for Approval
      <?php else: ?>
        Manager-Approved Completed Entries
      <?php endif; ?>
    </span>
    <form method="get" class="row g-2 align-items-center mb-0">
      <!-- preserve date filters when changing sort -->
      <input type="hidden" name="from_date" value="<?php echo htmlspecialchars($from_date); ?>">
      <input type="hidden" name="to_date" value="<?php echo htmlspecialchars($to_date); ?>">
      <div class="col-auto"><label class="form-label mb-0 me-1">Sort by</label></div>
      <div class="col-auto">
        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value="recent" <?php if ($sort === 'recent') echo 'selected'; ?>>Newest (date/time)</option>
          <option value="id_desc" <?php if ($sort === 'id_desc') echo 'selected'; ?>>ID (high to low)</option>
          <option value="oldest" <?php if ($sort === 'oldest') echo 'selected'; ?>>Oldest first</option>
        </select>
      </div>
    </form>
  </div>
  <div class="card-body table-responsive">
    <table id="tasktable" class="table table-bordered table-striped align-middle">
      <thead>
        <tr>
          <th>Task</th>
          <th>Employee</th>
          <th>Work Date</th>
          <th>Hours</th>
          <th>Notes</th>
          <th>Created At</th>
          <th>Updated At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <!-- no rows: keep empty or show a message -->
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?php echo htmlspecialchars($r['task_title']) . ' (ID: ' . (int)$r['task_id'] . ')'; ?></td>
              <td><?php echo htmlspecialchars($r['employee_name']); ?></td>
              <td><?php echo htmlspecialchars($r['work_date']); ?></td>
              <td><?php echo htmlspecialchars($r['hours']); ?></td>
              <td><?php echo nl2br(htmlspecialchars($r['notes'])); ?></td>
              <td><?php echo htmlspecialchars($r['created_at']); ?></td>
              <td><?php echo htmlspecialchars($r['updated_at']); ?></td>
              <td>
                <a href="timesheet_edit.php?id=<?php echo (int)$r['id']; ?>" class="btn btn-sm btn-outline-primary mb-1">Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this entry?');">
                  <input type="hidden" name="entry_id" value="<?php echo (int)$r['id']; ?>">
                  <input type="hidden" name="action" value="delete">
                  <button type="submit" class="btn btn-sm btn-outline-danger mb-1">Delete</button>
                </form>
                <?php if ($role === 'manager'): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Approve this entry for Admin review?');">
                  <input type="hidden" name="entry_id" value="<?php echo (int)$r['id']; ?>">
                  <input type="hidden" name="action" value="approve">
                  <button type="submit" class="btn btn-sm btn-success mb-1">Approve</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
