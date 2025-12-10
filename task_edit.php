<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_role(['manager', 'admin']);

$pageTitle = 'Edit Task';
$errors = [];
$success = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: tasks.php');
    exit;
}

$stmt = $mysqli->prepare("SELECT * FROM tasks WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$task = $res->fetch_assoc();
$stmt->close();

if (!$task) {
    header('Location: tasks.php');
    exit;
}

// Load projects for dropdown
$projects = [];
$resProj = $mysqli->query("SELECT id, name FROM projects ORDER BY name");
if ($resProj) {
    while ($row = $resProj->fetch_assoc()) {
        $projects[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'pending';

    if ($project_id <= 0) {
        $errors[] = 'Project is required.';
    }
    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    if (!$errors) {
        $stmt = $mysqli->prepare(
            "UPDATE tasks SET project_id = ?, title = ?, description = ?, status = ? WHERE id = ?"
        );
        $stmt->bind_param('isssi', $project_id, $title, $description, $status, $id);
        if ($stmt->execute()) {
            $success = 'Task updated successfully.';
            $stmt->close();
            $stmt = $mysqli->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $task = $res->fetch_assoc();
            $stmt->close();
        } else {
            $errors[] = 'Failed to update task: ' . $stmt->error;
        }
    }

}

include 'header.php';
?>
<h2 class="mb-4">Edit Task</h2>

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
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Project</label>
        <select name="project_id" class="form-select" required>
          <option value="">-- Select Project --</option>
          <?php foreach ($projects as $p): ?>
            <option value="<?php echo $p['id']; ?>" <?php if ((int)$task['project_id'] === (int)$p['id']) echo 'selected'; ?>>
              <?php echo htmlspecialchars($p['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($task['title']); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="4"><?php echo htmlspecialchars($task['description']); ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="pending" <?php if ($task['status'] === 'pending') echo 'selected'; ?>>Pending</option>
          <option value="in_progress" <?php if ($task['status'] === 'in_progress') echo 'selected'; ?>>In Progress</option>
          <option value="completed" <?php if ($task['status'] === 'completed') echo 'selected'; ?>>Completed</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="tasks.php" class="btn btn-secondary">Back to Tasks</a>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
