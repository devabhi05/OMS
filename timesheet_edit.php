<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

$pageTitle = 'Edit Timesheet Entry';
$errors = [];
$success = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: timesheets.php');
    exit;
}

$isAdmin = in_array($_SESSION['role'], ['admin', 'manager']);

if ($isAdmin) {
    $stmt = $mysqli->prepare("SELECT ts.*, t.title AS task_title, u.full_name AS employee_name
                              FROM timesheets ts
                              JOIN tasks t ON ts.task_id = t.id
                              JOIN users u ON ts.user_id = u.id
                              WHERE ts.id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
} else {
    $stmt = $mysqli->prepare("SELECT ts.*, t.title AS task_title
                              FROM timesheets ts
                              JOIN tasks t ON ts.task_id = t.id
                              WHERE ts.id = ? AND ts.user_id = ? LIMIT 1");
    $stmt->bind_param('ii', $id, $_SESSION['user_id']);
}
$stmt->execute();
$res = $stmt->get_result();
$entry = $res->fetch_assoc();
$stmt->close();

if (!$entry) {
    header('Location: timesheets.php');
    exit;
}

$created_ts = strtotime($entry['created_at']);
$now = time();
$can_edit = $isAdmin ? true : (($now - $created_ts) <= 3600);
if (!$can_edit) {
    $errors[] = 'Edit window (1 hour) has expired for this entry.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_edit) {
    $hours = trim($_POST['hours'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($hours === '' || !is_numeric($hours) || (float)$hours <= 0) {
        $errors[] = 'Please enter a valid number of hours.';
    }

    if (!$errors) {
        $hours_val = (float)$hours;
        if ($isAdmin) {
            $stmt = $mysqli->prepare("UPDATE timesheets SET hours = ?, notes = ? WHERE id = ?");
            $stmt->bind_param('dsi', $hours_val, $notes, $id);
        } else {
            $stmt = $mysqli->prepare("UPDATE timesheets SET hours = ?, notes = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param('dsii', $hours_val, $notes, $id, $_SESSION['user_id']);
        }
        if ($stmt->execute()) {
            $success = 'Entry updated successfully.';
            $stmt->close();
            if ($isAdmin) {
                $stmt = $mysqli->prepare("SELECT ts.*, t.title As task_title, u.full_name AS employee_name
                                          FROM timesheets ts
                                          JOIN tasks t ON ts.task_id = t.id
                                          JOIN users u ON ts.user_id = u.id
                                          WHERE ts.id = ? LIMIT 1");
                $stmt->bind_param('i', $id);
            } else {
                $stmt = $mysqli->prepare("SELECT ts.*, t.title AS task_title
                                          FROM timesheets ts
                                          JOIN tasks t ON ts.task_id = t.id
                                          WHERE ts.id = ? AND ts.user_id = ? LIMIT 1");
                $stmt->bind_param('ii', $id, $_SESSION['user_id']);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $entry = $res->fetch_assoc();
            $stmt->close();
        } else {
            $errors[] = 'Failed to update entry: ' . $stmt->error;
        }
    }
}

include 'header.php';
?>
<h2 class="mb-4">Edit Timesheet Entry</h2>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0">
  <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <dl class="row mb-3">
      <?php if ($isAdmin): ?>
        <dt class="col-sm-3">Employee</dt>
        <dd class="col-sm-9"><?php echo htmlspecialchars($entry['employee_name']); ?></dd>
      <?php endif; ?>
      <dt class="col-sm-3">Task</dt>
      <dd class="col-sm-9"><?php echo htmlspecialchars($entry['task_title']); ?></dd>
      <dt class="col-sm-3">Work Date</dt>
      <dd class="col-sm-9"><?php echo htmlspecialchars($entry['work_date']); ?></dd>
      <dt class="col-sm-3">Created At</dt>
      <dd class="col-sm-9"><?php echo htmlspecialchars($entry['created_at']); ?></dd>
    </dl>

    <?php if ($can_edit): ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Hours</label>
        <input type="number" name="hours" class="form-control" step="0.25" min="0"
               value="<?php echo htmlspecialchars($entry['hours']); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($entry['notes']); ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="timesheets.php" class="btn btn-secondary">Back</a>
    </form>
    <?php else: ?>
      <a href="timesheets.php" class="btn btn-secondary">Back</a>
    <?php endif; ?>
  </div>
</div>

<?php include 'footer.php'; ?>
