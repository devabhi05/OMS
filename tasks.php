<?php
require_once 'config.php';
require_once 'auth.php';

require_login();
require_role(['admin', 'manager']);

$pageTitle = 'Tasks';

$errors  = [];
$success = '';

// Read success/error messages from query string (after redirect)
if (!empty($_GET['success'])) {
    $success = $_GET['success'];
}
if (!empty($_GET['error'])) {
    $errors[] = $_GET['error'];
}

// Simple role helpers
$isAdmin   = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isManager = isset($_SESSION['role']) && $_SESSION['role'] === 'manager';

// Load projects for dropdown
$projects = [];
$projectRes = $mysqli->query("SELECT id, name FROM projects ORDER BY name");
if ($projectRes) {
    while ($row = $projectRes->fetch_assoc()) {
        $projects[] = $row;
    }
}

// Handle actions
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'create') {
        // Create a new task
        $project_id  = (int)($_POST['project_id'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status      = trim($_POST['status'] ?? 'pending');

        if ($project_id <= 0) {
            $errors[] = 'Please select a project.';
        }
        if ($title === '') {
            $errors[] = 'Title is required.';
        }
        if (!in_array($status, ['pending', 'in_progress', 'completed'], true)) {
            $status = 'pending';
        }

        if (!$errors) {
            $stmt = $mysqli->prepare("
                INSERT INTO tasks (project_id, title, description, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            if ($stmt) {
                $created_by = (int)$_SESSION['user_id'];
                $stmt->bind_param('isssi', $project_id, $title, $description, $status, $created_by);
                if ($stmt->execute()) {
                    $success = 'Task created successfully.';
                } else {
                    $errors[] = 'Failed to create task: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Failed to prepare insert: ' . $mysqli->error;
            }
        }

    } elseif ($action === 'delete') {
        // Delete a task
        $task_id = (int)($_POST['task_id'] ?? 0);
        if ($task_id <= 0) {
            $errors[] = 'Invalid task ID.';
        } else {
            $stmt = $mysqli->prepare("DELETE FROM tasks WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $task_id);
                if ($stmt->execute()) {
                    $success = 'Task deleted successfully.';
                } else {
                    $errors[] = 'Failed to delete task: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Failed to prepare delete: ' . $mysqli->error;
            }
        }

    } elseif ($action === 'mark_completed_from_approved') {
        // Mark task as completed if it has manager-approved COMPLETED timesheet entries
        $task_id = (int)($_POST['task_id'] ?? 0);

        if ($task_id <= 0) {
            $errors[] = 'Invalid task ID.';
        } else {
            // UPDATED: Require at least one manager-approved timesheet
            // where status is 'Completed' (case-insensitive)
            $sql = "
                UPDATE tasks t
                SET t.status = 'completed'
                WHERE t.id = ?
                  AND t.status = 'pending'
                  AND EXISTS (
                        SELECT 1
                        FROM timesheets ts
                        WHERE ts.task_id = t.id
                          AND ts.manager_approved = 1
                          AND LOWER(TRIM(ts.status)) = 'completed'
                  )
            ";
            $stmt = $mysqli->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $task_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $success = 'Task marked as completed based on manager-approved entries.';
                    } else {
                        $errors[] = 'Task cannot be marked as completed (no approved completed entries or already completed).';
                    }
                } else {
                    $errors[] = 'Failed to update task: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = 'Failed to prepare update: ' . $mysqli->error;
            }
        }
    }

    // 🔁 POST → Redirect → GET (avoid duplicate form submission on refresh)
    $params = [];
    if ($success !== '') {
        $params['success'] = $success;
    }
    if (!empty($errors)) {
        // For simplicity, join all errors into one string
        $params['error'] = implode(' ', $errors);
    }

    $redirectUrl = 'tasks.php';
    if (!empty($params)) {
        $redirectUrl .= '?' . http_build_query($params);
    }

    header('Location: ' . $redirectUrl);
    exit;
}

// Sorting
$allowedSorts = [
    'id_asc'          => 't.id ASC',
    'id_desc'         => 't.id DESC',
    'title_asc'       => 't.title ASC',
    'title_desc'      => 't.title DESC',
    'created_at_desc' => 't.created_at DESC',
    'created_at_asc'  => 't.created_at ASC',
    'status_asc'      => 't.status ASC',
    'status_desc'     => 't.status DESC',
];

$sortKey = $_GET['sort'] ?? 'created_at_desc';
if (!isset($allowedSorts[$sortKey])) {
    $sortKey = 'created_at_desc';
}
$orderBy = $allowedSorts[$sortKey];

// Load tasks with project, creator, and flag if there is any manager-approved COMPLETED timesheet
$query = "
    SELECT 
        t.*,
        u.full_name AS created_by_name,
        p.name      AS project_name,
        EXISTS(
            SELECT 1 
            FROM timesheets ts
            WHERE ts.task_id = t.id
              AND ts.manager_approved = 1
              AND LOWER(TRIM(ts.status)) = 'completed'
        ) AS has_approved
    FROM tasks t
    LEFT JOIN users    u ON t.created_by  = u.id
    LEFT JOIN projects p ON t.project_id = p.id
    ORDER BY {$orderBy}
";

$result = $mysqli->query($query);
$tasks  = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
}

include 'header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h2 class="mb-0">Tasks</h2>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
  <ul class="mb-0">
    <?php foreach ($errors as $e): ?>
      <li><?php echo htmlspecialchars($e); ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
  <?php echo htmlspecialchars($success); ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Create Task -->
<div class="card mb-4">
  <div class="card-header">Create Task</div>
  <div class="card-body">
    <?php if (!$projects): ?>
      <p class="text-muted mb-0">No projects available yet. Please create a project first.</p>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="action" value="create">

      <div class="mb-3">
        <label class="form-label">Project</label>
        <select name="project_id" class="form-select" required>
          <option value="">-- Select Project --</option>
          <?php foreach ($projects as $p): ?>
            <option value="<?php echo (int)$p['id']; ?>">
              <?php echo htmlspecialchars($p['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="pending">Pending</option>
          <option value="in_progress">In Progress</option>
          <option value="completed">Completed</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary">Create Task</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<!-- Task list -->
<div class="card">
  <div class="card-header">
    <strong>All Tasks</strong>
  </div>
  <div class="card-body table-responsive">
    <table class="table table-striped table-bordered align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Project</th>
          <th>Title</th>
          <th>Description</th>
          <th>Created By</th>
          <th>Status</th>
          <th>Created At</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$tasks): ?>
          <tr>
            <td colspan="8" class="text-center text-muted">No tasks yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($tasks as $t): ?>
            <tr>
              <td><?php echo (int)$t['id']; ?></td>
              <td><?php echo htmlspecialchars($t['project_name'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($t['title']); ?></td>
              <td><?php echo nl2br(htmlspecialchars($t['description'])); ?></td>
              <td><?php echo htmlspecialchars($t['created_by_name'] ?? ''); ?></td>
              <td>
                <span class="badge 
                  <?php echo $t['status'] === 'completed' ? 'bg-success' : 
                               ($t['status'] === 'in_progress' ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                  <?php echo htmlspecialchars(ucfirst($t['status'])); ?>
                </span>
              </td>
              <td>
                <?php
                $createdAt = !empty($t['created_at'])
                    ? date('d M Y H:i', strtotime($t['created_at']))
                    : '-';
                echo htmlspecialchars($createdAt);
                ?>
              </td>
              <td>
                <!-- Edit button -->
                <a href="task_edit.php?id=<?php echo (int)$t['id']; ?>" class="btn btn-sm btn-outline-primary">
                  Edit
                </a>

                <!-- Delete button -->
                <form method="post" class="d-inline"
                      onsubmit="return confirm('Are you sure you want to delete this task? This may also remove related timesheet entries.');">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="task_id" value="<?php echo (int)$t['id']; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger">
                    Delete
                  </button>
                </form>

				<!-- Mark as Completed (only if there are manager-approved COMPLETED timesheets AND status is pending) -->
				<?php if (
						$isAdmin &&                           // 👈 only admins see this
						!empty($t['has_approved']) &&
						(int)$t['has_approved'] === 1 &&
						$t['status'] === 'pending'
					): ?>
				  <form method="post" class="d-inline ms-1"
						onsubmit="return confirm('Mark this task as Completed based on manager-approved work logs?');">
					<input type="hidden" name="action" value="mark_completed_from_approved">
					<input type="hidden" name="task_id" value="<?php echo (int)$t['id']; ?>">
					<button type="submit" class="btn btn-sm btn-primary">
					  View Approved &amp; Complete
					</button>
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
