<?php
require_once 'config.php';
require_once 'auth.php';

require_login();
require_role(['admin']);

$pageTitle = 'Employee Profiles';

// Fetch all non-admin users with their completed task count
$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.first_name,
        u.last_name,
        u.username,
        u.role,
        u.email,
        u.mobile,
        u.profile_image,
        u.created_at,
        COALESCE(ct.c_completed, 0) AS completed_tasks
    FROM users u
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(DISTINCT task_id) AS c_completed
        FROM timesheets
        WHERE LOWER(TRIM(status)) = 'completed'
          AND manager_approved = 1
        GROUP BY user_id
    ) ct ON ct.user_id = u.id
    WHERE u.role <> 'admin'
    ORDER BY u.full_name ASC, u.username ASC
";

$result = $mysqli->query($sql);
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

function e($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

include 'header.php';
?>

<style>

body {
	background-color: #e5e8e8;
}

</style>

<section class="w-100 px-4 py-5">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">Employee Profiles</h2>
      <a href="dashboard.php" class="btn btn-outline-dark btn-sm">
        Back
      </a>
    </div>

    <?php if (!$users): ?>
      <div class="alert alert-info">
        No employees found.
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($users as $u): ?>
          <?php
            // Display name
            $displayName = $u['full_name'];
            if (!$displayName) {
                $displayName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            }
            if ($displayName === '') {
                $displayName = $u['username'];
            }

            $roleLabel = ucfirst($u['role'] ?? '');

            // Profile image or fallback
            if (!empty($u['profile_image'])) {
                $img = e($u['profile_image']);
            } else {
                $img = 'images/user.png';
            }

            $username       = $u['username'];
            $mobile         = $u['mobile'] ?: 'Not set';
            $completedTasks = (int)$u['completed_tasks'];
          ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="card shadow-sm h-100" style="border-radius: 15px;">
              <div class="card-body p-4">
                <div class="d-flex">
                  <div class="flex-shrink-0">
                    <img src="<?php echo $img; ?>"
                         alt="Profile image"
                         class="img-fluid"
                         style="width: 120px; height: 120px; border-radius: 10px; object-fit: cover;">
                  </div>
                  <div class="flex-grow-1 ms-3">
                    <h5 class="mb-1"><?php echo e($displayName); ?></h5>
                    <p class="mb-2 pb-1 text-muted"><?php echo e($roleLabel); ?></p>

                    <!-- Stats strip: Username / Completed Tasks / Mobile -->
                    <div class="d-flex justify-content-start rounded-3 p-2 mb-2 bg-body-tertiary">
                      <div>
                        <p class="mb-1">Username</p>
                        <p class="small text-muted mb-0"><?php echo e($username); ?></p>
                      </div>
                      <div class="px-3">
                        <p class="mb-1">Tasks</p>
                        <p class="small text-muted mb-0"><?php echo $completedTasks; ?></p>
                      </div>
                      
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex pt-1">
                      <a href="profile.php?id=<?php echo (int)$u['id']; ?>"
                         class="btn btn-primary btn-sm me-1 flex-grow-1">
                        View Profile
                      </a>
                      <a href="attendance.php?user_id=<?php echo (int)$u['id']; ?>"
                         class="btn btn-outline-secondary btn-sm flex-grow-1">
                        Attendance
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'footer.php'; ?>
