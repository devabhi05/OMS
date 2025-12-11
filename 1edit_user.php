<?php
require_once 'config.php';
require_once 'auth.php';

require_login();
require_role(['admin']);

$pageTitle = 'Edit User';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: manage_users.php?msg=' . urlencode('Invalid user ID.'));
    exit;
}

$errors = [];

$stmt = $mysqli->prepare("SELECT id, full_name, username, role FROM users WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: manage_users.php?msg=' . urlencode('User not found.'));
    exit;
}

$full_name = $user['full_name'];
$username  = $user['username'];
$role      = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $role      = trim($_POST['role'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    $validRoles = ['admin', 'manager', 'employee'];

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if (!in_array($role, $validRoles, true)) {
        $errors[] = 'Invalid role selected.';
    }

    if (!$errors) {
        if ($password === '') {
            $stmt = $mysqli->prepare(
                "UPDATE users SET full_name = ?, username = ?, role = ? WHERE id = ?"
            );
            if (!$stmt) {
                $errors[] = 'Prepare failed: ' . $mysqli->error;
            } else {
                $stmt->bind_param('sssi', $full_name, $username, $role, $id);
            }
        } else {
            $stmt = $mysqli->prepare(
                "UPDATE users SET full_name = ?, username = ?, role = ?, password = ? WHERE id = ?"
            );
            if (!$stmt) {
                $errors[] = 'Prepare failed: ' . $mysqli->error;
            } else {
                $stmt->bind_param('ssssi', $full_name, $username, $role, $password, $id);
            }
        }

        if (!$errors) {
            if ($stmt->execute()) {
                $stmt->close();
                $msg = urlencode('User updated successfully.');
                header("Location: manage_users.php?msg={$msg}");
                exit;
            } else {
                if ($mysqli->errno === 1062) {
                    $errors[] = 'Username already exists.';
                } else {
                    $errors[] = 'Failed to update user: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

include 'header.php';
?>
<div class="mb-4">
  <h2>Edit User #<?php echo (int)$id; ?></h2>
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

<div class="card">
  <div class="card-header">
    <strong>User Details</strong>
  </div>
  <div class="card-body">
    <form method="post" autocomplete="off">
      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control"
               value="<?php echo htmlspecialchars($full_name); ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control"
               value="<?php echo htmlspecialchars($username); ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select" required>
          <option value="admin"   <?php if ($role === 'admin') echo 'selected'; ?>>Admin</option>
          <option value="manager" <?php if ($role === 'manager') echo 'selected'; ?>>Manager</option>
          <option value="employee"<?php if ($role === 'employee') echo 'selected'; ?>>Employee</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="text" name="password" class="form-control" placeholder="Leave blank to keep current password">
        <div class="form-text">Only fill this if you want to change the password.</div>
      </div>

      <div class="d-flex justify-content-between">
        <a href="manage_users.php" class="btn btn-outline-secondary">Back</a>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php include 'footer.php'; ?>
