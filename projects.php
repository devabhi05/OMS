<?php
require_once 'config.php';
require_once 'auth.php';
require_login();
require_role(['admin']);

$pageTitle = 'Projects';
$errors = [];
$success = '';

$editingProject = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($name === '') {
            $errors[] = 'Project name is required.';
        }

        if (!$errors) {
            $stmt = $mysqli->prepare("INSERT INTO projects (name, description, created_by) VALUES (?, ?, ?)");
            $created_by = $_SESSION['user_id'];
            $stmt->bind_param('ssi', $name, $description, $created_by);
            if ($stmt->execute()) {
                $success = 'Project created successfully.';
            } else {
                $errors[] = 'Failed to create project: ' . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($id <= 0) {
            $errors[] = 'Invalid project.';
        }
        if ($name === '') {
            $errors[] = 'Project name is required.';
        }

        if (!$errors) {
            $stmt = $mysqli->prepare("UPDATE projects SET name = ?, description = ? WHERE id = ?");
            $stmt->bind_param('ssi', $name, $description, $id);
            if ($stmt->execute()) {
                $success = 'Project updated successfully.';
            } else {
                $errors[] = 'Failed to update project: ' . $stmt->error;
            }
            $stmt->close();
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid project.';
        } else {
            // Check that all tasks in this project are completed
            $stmt = $mysqli->prepare("SELECT COUNT(*) AS c FROM tasks WHERE project_id = ? AND status <> 'completed'");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if ($row && (int)$row['c'] > 0) {
                $errors[] = 'Cannot delete project while it still has non-completed tasks.';
            } else {
                $stmt = $mysqli->prepare("DELETE FROM projects WHERE id = ?");
                $stmt->bind_param('i', $id);
                if ($stmt->execute()) {
                    $success = 'Project deleted successfully.';
                } else {
                    $errors[] = 'Failed to delete project: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Editing?
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    if ($edit_id > 0) {
        $stmt = $mysqli->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $editingProject = $res->fetch_assoc();
        $stmt->close();
    }
}

// Load all projects
$projects = [];
$res = $mysqli->query("SELECT p.*, u.full_name AS created_by_name
                       FROM projects p
                       LEFT JOIN users u ON p.created_by = u.id
                       ORDER BY p.created_at DESC, p.id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $projects[] = $row;
    }
}

include 'header.php';
?>
<h2 class="mb-4">Projects</h2>

<?php if ($errors): ?>
<div class="alert alert-danger"><ul class="mb-0">
  <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
</ul></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-header">Create Project</div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="action" value="create">
      <div class="mb-3">
        <label class="form-label">Project Name</label>
        <input type="text" name="name" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Create Project</button>
    </form>
  </div>
</div>

<?php if ($editingProject): ?>
<div class="card mb-4">
  <div class="card-header">Edit Project #<?php echo (int)$editingProject['id']; ?></div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?php echo (int)$editingProject['id']; ?>">
      <div class="mb-3">
        <label class="form-label">Project Name</label>
        <input type="text" name="name" class="form-control"
               value="<?php echo htmlspecialchars($editingProject['name']); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?php
          echo htmlspecialchars($editingProject['description']);
        ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save Changes</button>
      <a href="projects.php" class="btn btn-secondary">Cancel</a>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">Existing Projects</div>
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Description</th>
          <th>Created By</th>
          <th>Created At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$projects): ?>
          <tr><td colspan="6" class="text-center">No projects yet.</td></tr>
        <?php else: ?>
          <?php foreach ($projects as $p): ?>
            <tr>
              <td><?php echo (int)$p['id']; ?></td>
              <td><?php echo htmlspecialchars($p['name']); ?></td>
              <td><?php echo nl2br(htmlspecialchars($p['description'])); ?></td>
              <td><?php echo htmlspecialchars($p['created_by_name']); ?></td>
              <td><?php echo htmlspecialchars($p['created_at']); ?></td>
              <td class="d-flex flex-wrap gap-1">
                <a href="projects.php?edit=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this project?');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'footer.php'; ?>
