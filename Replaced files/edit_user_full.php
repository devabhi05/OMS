<?php
require_once 'config.php';
require_once 'auth.php';

require_login();
require_role(['admin']);

$pageTitle = 'Edit User (Full Details)';

// -----------------------------
// 1. Get user id & fetch record
// -----------------------------
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($user_id <= 0) {
    header('Location: manage_users.php');
    exit;
}

$stmt = $mysqli->prepare("
    SELECT 
        id,
        full_name,
        first_name,
        last_name,
        username,
        password,
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
        profile_image
    FROM users
    WHERE id = ?
");
if (!$stmt) {
    die('Database error: ' . $mysqli->error);
}
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res  = $stmt->get_result();
$user = $res->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: manage_users.php');
    exit;
}

// -----------------------------
// 2. Default form values (from DB)
// -----------------------------
$first_name        = $user['first_name'] ?? '';
$last_name         = $user['last_name'] ?? '';
$username          = $user['username'] ?? '';
$role              = $user['role'] ?? 'employee';

$email             = $user['email'] ?? '';
$mobile            = $user['mobile'] ?? '';
$phone             = $user['phone'] ?? '';
$address           = $user['address'] ?? '';

$social_facebook   = $user['social_facebook'] ?? '';
$social_linkedin   = $user['social_linkedin'] ?? '';
$social_instagram  = $user['social_instagram'] ?? '';
$social_twitter    = $user['social_twitter'] ?? '';
$social_other      = $user['social_other'] ?? '';

$existingPassword  = $user['password'] ?? '';
$password          = ''; // we do NOT prefill password field

$errors            = [];
$success           = '';

// Existing profile image path (may be null/empty)
$profile_image_path  = $user['profile_image'] ?: null;

// Temp vars for upload handling
$profileImageTmpPath = null;
$profileImageExt     = null;

// -----------------------------
// 3. Handle POST (update)
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 3.1 Read & trim inputs
    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $username         = trim($_POST['username'] ?? '');
    $password         = trim($_POST['password'] ?? '');  // optional for edit
    $role             = trim($_POST['role'] ?? 'employee');

    $email            = trim($_POST['email'] ?? '');
    $mobile           = trim($_POST['mobile'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $address          = trim($_POST['address'] ?? '');

    $social_facebook  = trim($_POST['social_facebook'] ?? '');
    $social_linkedin  = trim($_POST['social_linkedin'] ?? '');
    $social_instagram = trim($_POST['social_instagram'] ?? '');
    $social_twitter   = trim($_POST['social_twitter'] ?? '');
    $social_other     = trim($_POST['social_other'] ?? '');

    // 3.2 Basic validations (similar to registration)
    if ($first_name === '') {
        $errors[] = 'First name is required.';
    }
    if ($last_name === '') {
        $errors[] = 'Last name is required.';
    }

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    // Username: only lowercase letters a-z
    if ($username !== '' && !preg_match('/^[a-z]+$/', $username)) {
        $errors[] = 'Username must contain only lowercase letters (a-z).';
    }

    // For EDIT: password is OPTIONAL. Only validate if provided.
    // (If blank, we keep the existing password.)
    if ($password !== '') {
        // Add any extra password rules here if you want
        // e.g. minimum length
        if (strlen($password) < 3) {
            $errors[] = 'If you change the password, it must be at least 3 characters.';
        }
    }

    $validRoles = ['admin', 'manager', 'employee'];
    if (!in_array($role, $validRoles, true)) {
        $errors[] = 'Invalid role selected.';
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    // Mobile: digits only, length 8–15
    if ($mobile !== '') {
        $mobile_digits = preg_replace('/\D+/', '', $mobile);
        if (!preg_match('/^[0-9]{8,15}$/', $mobile_digits)) {
            $errors[] = 'Mobile number should contain 8 to 15 digits (numbers only).';
        } else {
            $mobile = $mobile_digits;
        }
    }

    // Phone: optional, digits only 6–15
    if ($phone !== '') {
        $phone_digits = preg_replace('/\D+/', '', $phone);
        if (!preg_match('/^[0-9]{6,15}$/', $phone_digits)) {
            $errors[] = 'Phone number should contain 6 to 15 digits (numbers only).';
        } else {
            $phone = $phone_digits;
        }
    }

    // 3.3 Profile image validation (if any new file uploaded)
    if (isset($_FILES['profile_image']) && is_array($_FILES['profile_image'])) {
        $fileError = $_FILES['profile_image']['error'];

        if ($fileError !== UPLOAD_ERR_NO_FILE) {
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = 'Error during profile image upload. Please try again.';
            } else {
                $tmpPath  = $_FILES['profile_image']['tmp_name'];
                $fileSize = (int)($_FILES['profile_image']['size'] ?? 0);
                $origName = $_FILES['profile_image']['name'] ?? '';

                $maxSize = 2 * 1024 * 1024; // 2 MB
                if ($fileSize <= 0 || $fileSize > $maxSize) {
                    $errors[] = 'Profile image must be less than 2 MB.';
                }

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($ext, $allowedExts, true)) {
                    $errors[] = 'Profile image must be a JPG, JPEG, PNG, or GIF file.';
                }

                $mime = '';
                if (function_exists('mime_content_type')) {
                    $mime = mime_content_type($tmpPath);
                } elseif (!empty($_FILES['profile_image']['type'])) {
                    $mime = $_FILES['profile_image']['type'];
                }
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];
                if ($mime && !in_array($mime, $allowedMimes, true)) {
                    $errors[] = 'Profile image file type is not allowed.';
                }

                if (empty($errors)) {
                    $profileImageTmpPath = $tmpPath;
                    $profileImageExt     = $ext;
                }
            }
        }
    }

    // 3.4 Username uniqueness check (excluding current user)
    if (empty($errors) && $username !== '') {
        $check = $mysqli->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ? AND id <> ?');
        if ($check) {
            $check->bind_param('si', $username, $user_id);
            if ($check->execute()) {
                $res_check = $check->get_result();
                if ($res_check) {
                    $row = $res_check->fetch_assoc();
                    if ((int)$row['c'] > 0) {
                        $errors[] = 'Username is already taken. Please choose another one.';
                    }
                }
            } else {
                $errors[] = 'Failed to validate username uniqueness.';
            }
            $check->close();
        } else {
            $errors[] = 'Database error while preparing username check.';
        }
    }

    // 3.5 If OK so far, handle image move + DB update
    if (empty($errors)) {
        // Handle image upload if a new one was selected
        if ($profileImageTmpPath && $profileImageExt) {
            $uploadDir = __DIR__ . '/uploads/profile_images';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            if (!is_writable($uploadDir)) {
                $errors[] = 'Profile image directory is not writable. Please check folder permissions.';
            } else {
                try {
                    $random = bin2hex(random_bytes(8));
                } catch (Exception $e) {
                    $random = bin2hex(uniqid('', true));
                }

                $newFilename = time() . '_' . $random . '.' . $profileImageExt;
                $destPath    = $uploadDir . '/' . $newFilename;

                if (!move_uploaded_file($profileImageTmpPath, $destPath)) {
                    $errors[] = 'Failed to save the uploaded profile image.';
                } else {
                    // Set new profile image path for DB
                    $profile_image_path = 'uploads/profile_images/' . $newFilename;
                    // Optionally: you could unlink() the old file here if you want.
                }
            }
        }
    }

    if (empty($errors)) {
        $full_name = trim($first_name . ' ' . $last_name);

        // If password field empty, keep old; else use new one
        $passwordToSave = $password !== '' ? $password : $existingPassword;

        $sql = 'UPDATE users SET
                    full_name        = ?,
                    first_name       = ?,
                    last_name        = ?,
                    username         = ?,
                    password         = ?,
                    role             = ?,
                    email            = ?,
                    mobile           = ?,
                    phone            = ?,
                    address          = ?,
                    social_facebook  = ?,
                    social_linkedin  = ?,
                    social_instagram = ?,
                    social_twitter   = ?,
                    social_other     = ?,
                    profile_image    = ?
                WHERE id = ?';

        $stmt = $mysqli->prepare($sql);
        if (!$stmt) {
            $errors[] = 'Database error while preparing update: ' . $mysqli->error;
        } else {
            $stmt->bind_param(
                'ssssssssssssssssi',
                $full_name,
                $first_name,
                $last_name,
                $username,
                $passwordToSave,      // still plain text as in registration
                $role,
                $email,
                $mobile,
                $phone,
                $address,
                $social_facebook,
                $social_linkedin,
                $social_instagram,
                $social_twitter,
                $social_other,
                $profile_image_path,
                $user_id
            );

            if ($stmt->execute()) {
                $success = 'User details updated successfully.';

                // Refresh "existing" values for further edits without reloading page
                $existingPassword           = $passwordToSave;
                $user['profile_image']      = $profile_image_path;
                $user['first_name']         = $first_name;
                $user['last_name']          = $last_name;
                $user['username']           = $username;
                $user['role']               = $role;
                $user['email']              = $email;
                $user['mobile']             = $mobile;
                $user['phone']              = $phone;
                $user['address']            = $address;
                $user['social_facebook']    = $social_facebook;
                $user['social_linkedin']    = $social_linkedin;
                $user['social_instagram']   = $social_instagram;
                $user['social_twitter']     = $social_twitter;
                $user['social_other']       = $social_other;
            } else {
                $errors[] = 'Failed to update user: ' . $stmt->error;
            }

            $stmt->close();
        }
    }
}

include 'header.php';
?>

<style>
  body {
    background-color: #e5e8e8;
  }
  .reg-page-bg {
    min-height: calc(100vh - 120px); /* leave space for header/footer */
  }
  .reg-card {
    max-width: 720px;
    width: 100%;
    background: #ffffff;
    border-radius: 4px;
    overflow: hidden;
  }
  .reg-card-header {
    background: #248aa2;
    padding: 24px 32px;
  }
  .reg-card-header h2 {
    font-size: 26px;
    letter-spacing: 1px;
  }
  .reg-card-body {
    padding: 32px 40px 40px;
  }
  .reg-section-title {
    font-size: 0.95rem;
    font-weight: 500;
    color: #248aa2;
    margin-bottom: 1rem;
  }
  .reg-label {
    font-size: 0.85rem;
    color: #777;
    margin-bottom: 0.2rem;
  }
  .reg-input {
    font-size: 0.85rem;
    border: none;
    border-bottom: 1px solid #ccc;
    border-radius: 0;
    padding-left: 0;
    padding-right: 0;
    padding-top: 0.15rem;
    padding-bottom: 0.15rem;
    background: transparent;
    box-shadow: none !important;
  }
  .reg-input:focus {
    border-bottom-color: #248aa2;
    outline: 0;
    box-shadow: none;
  }
  .reg-textarea {
    border: none;
    border-bottom: 1px solid #ccc;
    border-radius: 0;
    padding-left: 0;
    padding-right: 0;
    background: transparent;
    resize: vertical;
    min-height: 60px;
    box-shadow: none !important;
  }
  .reg-textarea:focus {
    border-bottom-color: #248aa2;
    outline: 0;
    box-shadow: none;
  }
</style>

<div class="reg-page-bg d-flex justify-content-center align-items-center py-4">
  <div class="reg-card shadow-sm">
    <div class="reg-card-header text-center text-white">
      <h2 class="mb-0">EDIT USER DETAILS</h2>
    </div>

    <div class="reg-card-body">
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <h6 class="alert-heading mb-2">Please fix the following:</h6>
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?>
              <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="alert alert-success">
          <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" autocomplete="off">
        <!-- Block 1: Name + Login -->
        <div class="mb-4">
          <div class="reg-section-title">Basic Details</div>

          <div class="row">
            <div class="col-md-6 mb-4">
              <div class="reg-label">First Name <span class="text-danger">*</span></div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="first_name"
                name="first_name"
                value="<?php echo htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8'); ?>"
                required
              >
            </div>
            <div class="col-md-6 mb-4">
              <div class="reg-label">Last Name <span class="text-danger">*</span></div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="last_name"
                name="last_name"
                value="<?php echo htmlspecialchars($last_name, ENT_QUOTES, 'UTF-8'); ?>"
                required
              >
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-4">
              <div class="reg-label">Username <span class="text-danger">*</span></div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="username"
                name="username"
                value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                required
                pattern="[a-z]+"
                title="Only lowercase letters (a-z) are allowed"
              >
            </div>
            <div class="col-md-6 mb-4">
              <div class="reg-label">
                Password
                <small class="text-muted">(leave blank to keep current)</small>
              </div>
              <input
                type="password"
                class="form-control form-control-sm reg-input"
                id="password"
                name="password"
                value=""
                placeholder="Enter new password only if changing"
              >
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-4">
              <div class="reg-label">Role <span class="text-danger">*</span></div>
              <select class="form-select form-select-sm reg-input" id="role" name="role" required>
                <option value="">-- Select Role --</option>
                <option value="admin"   <?php if ($role === 'admin')   echo 'selected'; ?>>Admin</option>
                <option value="manager" <?php if ($role === 'manager') echo 'selected'; ?>>Manager</option>
                <option value="employee"<?php if ($role === 'employee') echo 'selected'; ?>>Employee</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Block 2: Contact Info -->
        <div class="mb-4">
          <div class="reg-section-title">Contact Information</div>

          <div class="row">
            <div class="col-md-6 mb-4">
              <div class="reg-label">Email</div>
              <input
                type="email"
                class="form-control form-control-sm reg-input"
                id="email"
                name="email"
                value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>
            <div class="col-md-3 mb-4">
              <div class="reg-label">Mobile</div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="mobile"
                name="mobile"
                value="<?php echo htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>

            <div class="col-md-3 mb-4">
              <div class="reg-label">Phone</div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="phone"
                name="phone"
                value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>
          </div>

          <div class="mb-4">
            <div class="reg-label">Address</div>
            <textarea
              class="form-control form-control-sm reg-textarea"
              id="address"
              name="address"
              rows="2"
            ><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>
        </div>

        <!-- Block 3: Social Media IDs -->
        <div class="mb-4">
          <div class="reg-section-title">Social Media IDs (optional)</div>

          <div class="row">
            <div class="col-md-6 mb-4">
              <div class="reg-label">Facebook</div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="social_facebook"
                name="social_facebook"
                value="<?php echo htmlspecialchars($social_facebook, ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>
            <div class="col-md-6 mb-4">
              <div class="reg-label">LinkedIn</div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="social_linkedin"
                name="social_linkedin"
                value="<?php echo htmlspecialchars($social_linkedin, ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-4">
              <div class="reg-label">Instagram</div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="social_instagram"
                name="social_instagram"
                value="<?php echo htmlspecialchars($social_instagram, ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>
            <div class="col-md-6 mb-4">
              <div class="reg-label">Twitter / X</div>
              <input
                type="text"
                class="form-control form-control-sm reg-input"
                id="social_twitter"
                name="social_twitter"
                value="<?php echo htmlspecialchars($social_twitter, ENT_QUOTES, 'UTF-8'); ?>"
              >
            </div>
          </div>

          <div class="mb-4">
            <div class="reg-label">Other (GitHub, etc.)</div>
            <input
              type="text"
              class="form-control form-control-sm reg-input"
              id="social_other"
              name="social_other"
              value="<?php echo htmlspecialchars($social_other, ENT_QUOTES, 'UTF-8'); ?>"
            >
          </div>
        </div>

        <!-- Block 4: Profile Image -->
        <div class="mb-4">
          <div class="reg-section-title">Profile Image</div>

          <?php if (!empty($profile_image_path)): ?>
            <div class="mb-3 text-center">
              <div class="reg-label mb-1">Current Profile Image</div>
              <img
                src="<?php echo htmlspecialchars($profile_image_path, ENT_QUOTES, 'UTF-8'); ?>"
                alt="Profile Image"
                class="rounded-circle"
                style="width: 100px; height: 100px; object-fit: cover;"
              >
            </div>
          <?php endif; ?>

          <div class="mb-3">
            <div class="reg-label">Upload New Profile Image (optional)</div>
            <input
              type="file"
              class="form-control form-control-sm reg-input"
              id="profile_image"
              name="profile_image"
              accept="image/*"
            >
            <div class="form-text">
              Allowed types: JPG, JPEG, PNG, GIF. Max size: 2 MB.
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-3">
          <a href="manage_users.php" class="btn btn-outline-secondary btn-sm">Back</a>
          <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function setupPrefix91(inputId) {
    var input = document.getElementById(inputId);
    if (!input) return;

    input.addEventListener('focus', function () {
      if (input.value.trim() === '') {
        input.value = '+91 ';
      }
    });

    input.addEventListener('blur', function () {
      if (input.value.trim() === '+91') {
        input.value = '';
      }
    });
  }

  // Apply to both mobile and phone
  setupPrefix91('mobile');
  setupPrefix91('phone');
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var emailInput = document.getElementById('email');
  var defaultSuffix = '@gmail.com';

  if (!emailInput) return;

  emailInput.addEventListener('blur', function () {
    var val = emailInput.value.trim();

    if (val === '') return;
    if (val.indexOf('@') !== -1) return;

    emailInput.value = val + defaultSuffix;
  });
});
</script>

<?php include 'footer.php'; ?>
