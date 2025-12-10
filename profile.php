<?php
require_once 'config.php';
require_once 'auth.php';

require_login();
$pageTitle = 'User Profile';

// Decide which user to show
$viewUserId = $_SESSION['user_id'] ?? null;

// If admin and ?id=N is passed, allow viewing that user
if (function_exists('current_user_role')
    && current_user_role() === 'admin'
    && isset($_GET['id']) && ctype_digit($_GET['id'])) {
    $viewUserId = (int)$_GET['id'];
}

if (!$viewUserId) {
    die('No user selected.');
}

// Fetch user from DB
$sql = "SELECT 
            id,
            full_name,
            first_name,
            last_name,
            username,
            role,
            email,
            mobile,
            phone,
            address,
            social_facebook,
            social_linkedin,
            social_instagram,
            social_twitter,
            social_other,
            profile_image,
            created_at
        FROM users
        WHERE id = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    die('DB error: ' . $mysqli->error);
}
$stmt->bind_param('i', $viewUserId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die('User not found.');
}

// Prepare display values
$displayName = $user['full_name'];
if (!$displayName) {
    $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}
if ($displayName === '') {
    $displayName = $user['username'];
}

$roleLabel = ucfirst($user['role'] ?? '');

// Profile image: use uploaded path if available, otherwise fallback avatar
if (!empty($user['profile_image'])) {
    $profileImageUrl = htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8');
} else {
    $profileImageUrl = '\sarvjeet3\images\user.png';
}

// Safe output helper
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

include 'header.php';
?>
<style>
  body {
    background-color: #e5e8e8;
  }
  
  .card {
    padding: 1rem;
  }
  
  hr {
    margin-top: 0.25rem;
  }

  /* Make the top profile cards visually same height */
  .profile-main-card {
    min-height: 340px; /* adjust this number if you want taller/shorter */
  }

  /* Optional: on large screens you can bump it slightly */
  @media (min-width: 992px) {
    .profile-main-card {
      min-height: 380px;
    }
  }
</style>

<section>
  <div class="container py-5">
    <div class="row">
      <!-- LEFT COLUMN: Avatar + basic info + socials -->
      <div class="col-lg-3">
        <div class="card mb-4 profile-main-card">
          <div class="card-body text-center">
            <img src="<?php echo $profileImageUrl; ?>" alt="avatar"
              class="rounded-circle img-fluid"
              style="width: 125px; height: 125px; object-fit: cover;">
            <h5 class="my-3"><?php echo e($displayName); ?></h5>
            <p class="text-muted mb-1"><?php echo e($roleLabel); ?></p>
            <p class="text-muted mb-4 w-75 mx-auto">
              <?php echo $user['address'] ? e($user['address']) : 'No address provided'; ?>
            </p>
            <div class="d-flex justify-content-center mb-2">
              <!-- Edit button -->
				<a href="edit_user.php?id=<?php echo (int)$user['id']; ?>"
				   class="btn btn-primary btn-sm fw-bold px-3">
				  Edit
				</a>

              <a href="manage_users.php"
                 class="btn btn-outline-primary btn-sm ms-1"
>
                Back
              </a>
            </div>
          </div>
        </div>

        <div class="card mb-4 mb-lg-0">
          <div class="card-body p-0">
            <ul class="list-group list-group-flush rounded-3">
              <?php if (!empty($user['social_other'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fas fa-globe fa-lg text-warning"></i>
                  <p class="mb-0"><?php echo e($user['social_other']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (!empty($user['social_facebook'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fab fa-facebook-f fa-lg" style="color: #3b5998;"></i>
                  <p class="mb-0"><?php echo e($user['social_facebook']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (!empty($user['social_instagram'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fab fa-instagram fa-lg" style="color: #ac2bac;"></i>
                  <p class="mb-0"><?php echo e($user['social_instagram']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (!empty($user['social_twitter'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fab fa-twitter fa-lg" style="color: #55acee;"></i>
                  <p class="mb-0"><?php echo e($user['social_twitter']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (!empty($user['social_linkedin'])): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                  <i class="fab fa-linkedin fa-lg" style="color: #0a66c2;"></i>
                  <p class="mb-0"><?php echo e($user['social_linkedin']); ?></p>
                </li>
              <?php endif; ?>

              <?php if (
                empty($user['social_facebook']) &&
                empty($user['social_instagram']) &&
                empty($user['social_twitter']) &&
                empty($user['social_linkedin']) &&
                empty($user['social_other'])
              ): ?>
                <li class="list-group-item d-flex justify-content-center align-items-center p-3">
                  <p class="mb-0 text-muted small">No social links saved.</p>
                </li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>

      <!-- RIGHT COLUMN: Detailed profile info + bottom cards -->
      <div class="col-lg-9">
        <div class="card mb-4 profile-main-card">
          <div class="card-body">
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Full Name</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0"><?php echo e($displayName); ?></p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Username</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0"><?php echo e($user['username']); ?></p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Role</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0"><?php echo e($roleLabel); ?></p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Email</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo $user['email'] ? e($user['email']) : 'Not set'; ?>
                </p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Phone</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo $user['phone'] ? e($user['phone']) : 'Not set'; ?>
                </p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Mobile</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo $user['mobile'] ? e($user['mobile']) : 'Not set'; ?>
                </p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Address</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo $user['address'] ? nl2br(e($user['address'])) : 'Not set'; ?>
                </p>
              </div>
            </div>
            <hr>
            <div class="row">
              <div class="col-sm-3">
                <p class="mb-0">Created At</p>
              </div>
              <div class="col-sm-9">
                <p class="text-muted mb-0">
                  <?php echo e($user['created_at']); ?>
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom two cards (same as your original template, placeholder data) -->
        <div class="row">
          <div class="col-md-6">
            <div class="card mb-4 mb-md-0">
              <div class="card-body">
                <p class="mb-4">
                  <span class="text-primary font-italic me-1">assignment</span> Project Status
                </p>
                <p class="mb-1" style="font-size: .77rem;">Web Design</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 80%" aria-valuenow="80"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-4 mb-1" style="font-size: .77rem;">Website Markup</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 72%" aria-valuenow="72"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-4 mb-1" style="font-size: .77rem;">One Page</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 89%" aria-valuenow="89"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-4 mb-1" style="font-size: .77rem;">Mobile Template</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 55%" aria-valuenow="55"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-4 mb-1" style="font-size: .77rem;">Backend API</p>
                <div class="progress rounded mb-2" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 66%" aria-valuenow="66"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card mb-4 mb-md-0">
              <div class="card-body">
                <p class="mb-4">
                  <span class="text-primary font-italic me-1">assignment</span> Project Status
                </p>
                <p class="mb-1" style="font-size: .77rem;">Web Design</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 80%" aria-valuenow="80"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-4 mb-1" style="font-size: .77rem;">Website Markup</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 72%" aria-valuenow="72"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-4 mb-1" style="font-size: .77rem;">One Page</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 89%" aria-valuenow="89"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-4 mb-1" style="font-size: .77rem;">Mobile Template</p>
                <div class="progress rounded" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 55%" aria-valuenow="55"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-4 mb-1" style="font-size: .77rem;">Backend API</p>
                <div class="progress rounded mb-2" style="height: 5px;">
                  <div class="progress-bar" role="progressbar" style="width: 66%" aria-valuenow="66"
                    aria-valuemin="0" aria-valuemax="100"></div>
                </div>
              </div>
            </div>
          </div>
        </div> <!-- /row bottom cards -->
      </div>
    </div>
  </div>
</section>

<?php include 'footer.php'; ?>
