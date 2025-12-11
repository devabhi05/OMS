<?php
require_once 'config.php';
require_once 'auth.php';
require_login();

$pageTitle = 'Timesheets';
$errors    = [];
$success   = '';

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];

// Restrict Timesheets page to employees only
if ($role === 'admin' || $role === 'manager') {
    header('Location: dashboard.php');
    exit;
}


// Handle new entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $task_id   = (int)($_POST['task_id'] ?? 0);
    $work_date = $_POST['work_date'] ?? date('Y-m-d');
    $hours     = trim($_POST['hours'] ?? '');
    // NEW: separate status + notes
    $status    = trim($_POST['status'] ?? 'In Progress');
    $notes     = trim($_POST['notes'] ?? '');

    if ($task_id <= 0) {
        $errors[] = 'Please select a task.';
    }

    if ($work_date === '') {
        $errors[] = 'Work date is required.';
    }

    if ($hours === '' || !is_numeric($hours) || (float)$hours <= 0) {
        $errors[] = 'Please enter a valid number of hours (decimal allowed).';
    }

    // Validate status
    $allowedStatuses = ['In Progress', 'Completed'];
    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = 'Invalid status selected.';
    }

    if (!$errors) {
        $hours_val = (float)$hours;

        // UPDATED: insert into status + notes separately
        $stmt = $mysqli->prepare(
            "INSERT INTO timesheets (user_id, task_id, work_date, hours, status, notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $errors[] = 'Failed to prepare statement: ' . $mysqli->error;
        } else {
            $stmt->bind_param('iisdss', $user_id, $task_id, $work_date, $hours_val, $status, $notes);

            if ($stmt->execute()) {
                $success = 'Timesheet entry added.';
            } else {
                $errors[] = 'Failed to add entry: ' . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// Tasks for dropdown
$tasks = [];
if ($role === 'employee') {
    // UPDATED: use status column instead of notes to check completed tasks
    $sqlTasks = "
        SELECT id, title, description
        FROM tasks
        WHERE status <> 'completed'
          AND id NOT IN (
              SELECT task_id FROM timesheets
              WHERE user_id = ? AND LOWER(TRIM(status)) = 'completed'
          )
        ORDER BY title
    ";
    $stmt = $mysqli->prepare($sqlTasks);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $tasks[] = $row;
    }

    $stmt->close();
} else {
    // Admin / Manager: same as before (only non-completed tasks globally)
    $res = $mysqli->query("SELECT id, title FROM tasks WHERE status <> 'completed' ORDER BY title");
    while ($row = $res->fetch_assoc()) {
        $tasks[] = $row;
    }
}

// Sorting for entries table
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
        $sort    = 'recent';
        break;
}

// Fetch entries
$entries = [];
if ($role === 'admin') {
    $sql  = "SELECT ts.*, t.title AS task_title, u.full_name AS employee_name
             FROM timesheets ts
             JOIN tasks t ON ts.task_id = t.id
             JOIN users u ON ts.user_id = u.id
             ORDER BY $orderBy
             LIMIT 200";
    $stmt = $mysqli->prepare($sql);
} else {
    $sql  = "SELECT ts.*, t.title AS task_title
             FROM timesheets ts
             JOIN tasks t ON ts.task_id = t.id
             WHERE ts.user_id = ?
             ORDER BY $orderBy
             LIMIT 100";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    if ($role === 'admin') {
        $row['can_edit'] = true;
    } else {
        $created_ts      = strtotime($row['created_at']);
        $now             = time();
        $row['can_edit'] = ($now - $created_ts) <= 3600;
    }
    $entries[] = $row;
}

$stmt->close();

include 'header.php';
?>
<style>
.task-info-box {
    background-color: #f8f8f8;                  /* your gray */
    border-left: 4px solid #adb5bd;		/* keep blue accent, or change later */
    border-radius: 0.75rem;
    box-shadow:
        0 2px 4px rgba(0, 0, 0, 0.04),
        0 8px 18px rgba(0, 0, 0, 0.06);         /* soft gray shadow */
    transition: transform 0.15s ease,
                box-shadow 0.15s ease,
                opacity 0.15s ease;
    opacity: 0;
    transform: translateY(4px);
}


  .task-info-box--visible {
    opacity: 1;
    transform: translateY(0);
  }
  .task-info-icon {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(13, 110, 253, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0d6efd;
    flex-shrink: 0;
  }
  .task-info-title {
    font-size: 0.9rem;
    letter-spacing: 0.08em;
    font-weight: 700;
  }
  .task-info-body {
    font-size: 0.9rem;
    max-height: 110px;
    overflow-y: auto;
    color: #343a40; /* darker text */
  }
  #task-info-placeholder {
    font-style: italic;
    color: #6c757d;
  }
  #task-info-text {
    display: inline-block;
    margin-top: 2px;
  }
</style>

<h2 class="mb-4">Timesheets</h2>

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
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($role !== 'admin'): ?>
    <div class="card mb-4">
        <div class="card-header">Log Work Hours</div>
        <div class="card-body">
            <?php if (!$tasks): ?>
                <p class="text-muted">No active tasks available yet. Please contact your manager.</p>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Task</label>
                            <select name="task_id" class="form-select" required>
                                <option value="">-- Select Task --</option>
                                <?php foreach ($tasks as $t): ?>
                                    <option
                                        value="<?php echo $t['id']; ?>"
                                        data-description="<?php echo htmlspecialchars($t['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <?php echo htmlspecialchars($t['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <!-- Task Info Box -->
                            <div id="task-info-box" class="mt-3 task-info-box p-3" style="display: none;">
                                <div class="d-flex align-items-start">
                                    <div class="task-info-icon me-3">
                                        <i class="fas fa-info"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="task-info-title text-uppercase fw-semibold">
                                                Task Details
                                            </div>
                                            <small class="text-muted" id="task-info-taskname"></small>
                                        </div>
                                        <div class="task-info-body text-secondary">
                                            <span id="task-info-placeholder">No description available for this task.</span>
                                            <span id="task-info-text"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Work Date</label>
                            <input
                                type="date"
                                name="work_date"
                                value="<?php echo date('Y-m-d'); ?>"
                                class="form-control"
                                required
                            >
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Hours</label>
                            <input
                                type="number"
                                name="hours"
                                class="form-control"
                                step="0.25"
                                min="0"
                                required
                            >
                        </div>
                    </div>

                    <div class="row mb-3">
                        <?php if ($role === 'employee'): ?>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <!-- UPDATED: add name="status" so it is posted -->
                                <select id="status_select" name="status" class="form-select">
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <label class="form-label">Notes (optional)</label>
                                <textarea
                                    name="notes"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Add details about your work..."
                                ></textarea>
                            </div>
                        <?php else: ?>
                            <div class="col-12">
                                <label class="form-label">Notes (optional)</label>
                                <textarea
                                    name="notes"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Add details about this entry..."
                                ></textarea>
                            </div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Entry</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <?php if ($role === 'admin'): ?>
                All Timesheet Entries (Admin can edit any time)
            <?php else: ?>
                Your Recent Entries (Editable for 1 hour after creation)
            <?php endif; ?>
        </span>
        <form method="get" class="row g-2 align-items-center mb-0">
            <div class="col-auto">
                <label class="form-label mb-0 me-1">Sort by</label>
            </div>
            <div class="col-auto">
                <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="recent" <?php if ($sort === 'recent') echo 'selected'; ?>>
                        Newest (date/time)
                    </option>
                    <option value="id_desc" <?php if ($sort === 'id_desc') echo 'selected'; ?>>
                        ID (high to low)
                    </option>
                    <option value="oldest" <?php if ($sort === 'oldest') echo 'selected'; ?>>
                        Oldest first
                    </option>
                </select>
            </div>
        </form>
    </div>

    <div class="card-body table-responsive">
        <table id="timesheetTable" class="table table-striped table-bordered align-middle">
            <thead>
                <tr>
                    <?php if ($role === 'admin'): ?>
                        <th>Employee</th>
                    <?php endif; ?>
                    <th>Task</th>
                    <th>Work Date</th>
                    <th>Hours</th>
                    <!-- UPDATED: split into Status + Notes -->
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$entries): ?>
                    <tr>
                        <td
                            colspan="<?php echo ($role === 'admin') ? 9 : 8; ?>"
                            class="text-center"
                        >
                            No entries yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $e): ?>
                        <tr>
                            <?php if ($role === 'admin'): ?>
                                <td><?php echo htmlspecialchars($e['employee_name']); ?></td>
                            <?php endif; ?>
                            <td><?php echo htmlspecialchars($e['task_title']); ?></td>
                            <td><?php echo htmlspecialchars($e['work_date']); ?></td>
                            <td><?php echo htmlspecialchars($e['hours']); ?></td>
                            <!-- NEW: status column -->
                            <td><?php echo htmlspecialchars($e['status']); ?></td>
                            <!-- NEW: notes column -->
                            <td><?php echo nl2br(htmlspecialchars($e['notes'])); ?></td>
                            <td><?php echo htmlspecialchars($e['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($e['updated_at']); ?></td>
                            <td>
                                <?php if ($e['can_edit']): ?>
                                    <a
                                        href="timesheet_edit.php?id=<?php echo $e['id']; ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Edit
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">Edit window expired</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if ($role === 'admin'): ?>
                        <link
                            rel="stylesheet"
                            href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css"
                        >
                        <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
                        <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
                        <script>
                            $(function () {
                                var $table = $('#timesheetTable');
                                if ($table.length) {
                                    $table.DataTable({
                                        pageLength: 25,
                                        order: []
                                    });
                                }
                            });
                        </script>
                    <?php endif; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// OLD JS THAT AUTO-COPIED STATUS -> NOTES HAS BEEN REMOVED ON PURPOSE
// so Status and Notes are independent now.
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var taskSelect  = document.querySelector('select[name="task_id"]');
    var infoBox     = document.getElementById('task-info-box');
    var infoText    = document.getElementById('task-info-text');
    var placeholder = document.getElementById('task-info-placeholder');
    var taskNameEl  = document.getElementById('task-info-taskname');

    if (!taskSelect || !infoBox || !infoText || !placeholder || !taskNameEl) {
        return; // safety check
    }

    function updateTaskInfo() {
        var selected = taskSelect.options[taskSelect.selectedIndex];
        var desc     = selected ? selected.getAttribute('data-description') : '';
        var value    = selected ? selected.value : '';
        var label    = (value !== '' && selected) ? selected.textContent.trim() : '';

        // If no real task is selected → hide the entire box (no gap)
        if (!value) {
            infoBox.style.display = 'none';
            infoBox.classList.remove('task-info-box--visible');
            taskNameEl.textContent      = '';
            placeholder.style.display   = 'none';
            infoText.textContent        = '';
            return;
        }

        // We have a valid task → show box and fill content
        infoBox.style.display = 'block';
        infoBox.classList.add('task-info-box--visible');

        taskNameEl.textContent = label || '';

        if (desc && desc.trim() !== '') {
            placeholder.style.display = 'none';
            infoText.textContent      = desc;
        } else {
            placeholder.style.display = 'inline';
            infoText.textContent      = '';
        }
    }

    taskSelect.addEventListener('change', updateTaskInfo);

    // Initialize once on load (so if a task was selected after validation error, it shows)
    updateTaskInfo();
});
</script>

<?php include 'footer.php'; ?>
