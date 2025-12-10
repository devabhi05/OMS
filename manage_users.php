<?php
require_once 'config.php';
require_once 'auth.php';

require_login();
require_role(['admin']);

$pageTitle = 'Manage Users';

// Optional success/error message
$successMsg = isset($_GET['msg']) ? $_GET['msg'] : '';

$users = [];
$stmt = $mysqli->prepare('SELECT id, full_name, username, role, created_at FROM users ORDER BY id ASC');
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $stmt->close();
}

include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mt-3 mb-3">
  <h3 class="mb-0">Manage Users</h3>
  <a href="add_user.php" class="btn btn-primary btn-sm">Add User</a>
</div>

<?php if ($successMsg): ?>
  <div class="alert alert-info"><?php echo htmlspecialchars($successMsg); ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-striped table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width: 5%;">ID</th>
            <th style="width: 25%;">Full Name</th>
            <th style="width: 20%;">Username</th>
            <th style="width: 10%;">Role</th>
            <th style="width: 20%;">Created At</th>
            <th style="width: 20%;">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$users): ?>
          <tr>
            <td colspan="6" class="text-center text-muted py-3">No users found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td><?php echo (int)$u['id']; ?></td>
              <td><?php echo htmlspecialchars($u['full_name']); ?></td>
              <td><?php echo htmlspecialchars($u['username']); ?></td>
              <td class="text-capitalize"><?php echo htmlspecialchars($u['role']); ?></td>
              <td><?php echo htmlspecialchars($u['created_at']); ?></td>
              <td>
				  <?php if ($u['role'] === 'admin'): ?>
					<!-- SIMPLE LAYOUT FOR ADMIN ROW ONLY -->
					<a href="edit_user.php?id=<?php echo (int)$u['id']; ?>"
					   class="btn btn-outline-secondary btn-sm me-1">
					  Edit
					</a>

					<!-- If this is the logged-in admin, show "You" -->
					<?php if ((int)$u['id'] === (int)($_SESSION['user_id'] ?? 0)): ?>
								<span class="badge rounded-pill bg-secondary align-middle">
								  admin
								</span>
					<?php endif; ?>

				  <?php else: ?>
					<!-- JOINED BUTTONS FOR ALL NON-ADMIN USERS -->
					<div class="btn-group btn-group-sm" role="group">
					  <!-- Edit -->
					  <a href="edit_user.php?id=<?php echo (int)$u['id']; ?>"
						 class="btn btn-outline-secondary"
						style="border-right:none;" >
						Edit
					  </a>

					  <!-- View Attendance (non-admin rows only anyway) -->
					  <a href="attendance.php?user_id=<?php echo (int)$u['id']; ?>"
						 class="btn btn-outline-dark">
						View Attendance
					  </a>

					  <!-- Delete (no delete button for the logged-in user, just in case) -->
					  <?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
						<button type="submit"
								form="delete-user-<?php echo (int)$u['id']; ?>"
								class="btn btn-outline-danger"
								style="border-left:none;"
								onclick="return confirm('Are you sure you want to delete this user?');">
						  Delete
						</button>
					  <?php else: ?>
						<!-- Fallback: if ever viewing your own row and not admin -->
						<span class="btn btn-outline-secondary disabled">
						  You
						</span>
					  <?php endif; ?>
					</div>

					<!-- Hidden delete form (outside btn-group so buttons can stay joined) -->
					<?php if ((int)$u['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
					  <form id="delete-user-<?php echo (int)$u['id']; ?>"
							method="post"
							action="delete_user.php"
							class="d-none">
						<input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
					  </form>
					<?php endif; ?>

				  <?php endif; ?>
				</td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
