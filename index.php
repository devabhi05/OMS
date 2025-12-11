<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'attendance_helpers.php';

if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $mysqli->prepare('SELECT id, full_name, username, password, role FROM users WHERE username = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && hash_equals($user['password'], $password)) {
                // Successful login: set session
                $_SESSION['user_id']   = (int)$user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['username']  = $user['username'];
                $_SESSION['role']      = $user['role'];

                // Record Time & Attendance for non-admins
                if ($user['role'] !== 'admin') {
                    record_attendance_login($mysqli, (int)$user['id']);
                }

                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Database error. Please try again later.';
        }
    }
}

$pageTitle = 'Login';
include 'header.php';
?>

<div class="row justify-content-center mt-5">
  <div class="col-md-4">
    <h2 class="mb-4 text-center">Office Management System</h2>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" action="index.php" class="card card-body shadow-sm">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" name="username" id="username" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" name="password" id="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>

    <div class="mt-3">

    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
