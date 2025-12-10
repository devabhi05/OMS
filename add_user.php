<?php
require_once 'config.php';
require_once 'auth.php';

require_login();
require_role(['admin']);

$pageTitle = 'Add User';

// Default form values
$first_name        = '';
$last_name         = '';
$username          = '';
$password          = '';
$role              = '';

$email             = '';
$mobile            = '';
$phone             = '';
$address           = '';

$social_facebook   = '';
$social_linkedin   = '';
$social_instagram  = '';
$social_twitter    = '';
$social_other      = '';

$errors            = [];

// Will hold final stored path/filename if upload succeeds
$profile_image_path   = null;

// Temp vars for upload handling (so we only move file if everything else is valid)
$profileImageTmpPath  = null;
$profileImageExt      = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ----------------------
    // 1. Read & trim inputs
    // ----------------------
    $first_name       = trim($_POST['first_name'] ?? '');
    $last_name        = trim($_POST['last_name'] ?? '');
    $username         = trim($_POST['username'] ?? '');
    $password         = trim($_POST['password'] ?? '');
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

    // ----------------------
    // 2. Basic validations
    // ----------------------

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


    if ($password === '') {
        $errors[] = 'Password is required.';
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
    // Remove everything that is not a digit (so "+91 9876543210" -> "919876543210")
    $mobile_digits = preg_replace('/\D+/', '', $mobile);

    if (!preg_match('/^[0-9]{8,15}$/', $mobile_digits)) {
        $errors[] = 'Mobile number should contain 8 to 15 digits (numbers only).';
    } else {
        // Store normalized version in DB
        $mobile = $mobile_digits;
    }
}


    // Phone: digits, spaces, +, -, ()
    // Phone: optional, strip non-digits (so "+91 2222334455" -> "912222334455")
	if ($phone !== '') {
		$phone_digits = preg_replace('/\D+/', '', $phone);

		if (!preg_match('/^[0-9]{6,15}$/', $phone_digits)) {
			$errors[] = 'Phone number should contain 6 to 15 digits (numbers only).';
		} else {
			// Store normalized version
			$phone = $phone_digits;
		}
	}


    // ---------------------------------
    // 3. Profile image validation (if any)
    // ---------------------------------
    if (isset($_FILES['profile_image']) && is_array($_FILES['profile_image'])) {
        $fileError = $_FILES['profile_image']['error'];

        if ($fileError !== UPLOAD_ERR_NO_FILE) {
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors[] = 'Error during profile image upload. Please try again.';
            } else {
                $tmpPath  = $_FILES['profile_image']['tmp_name'];
                $fileSize = (int)($_FILES['profile_image']['size'] ?? 0);
                $origName = $_FILES['profile_image']['name'] ?? '';

                $maxSize = 2 * 1024 * 1024;
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

    // ---------------------------------
    // 4. Username uniqueness check
    // ---------------------------------
    if (empty($errors) && $username !== '') {
        $check = $mysqli->prepare('SELECT COUNT(*) AS c FROM users WHERE username = ?');
        if ($check) {
            $check->bind_param('s', $username);
            if ($check->execute()) {
                $res = $check->get_result();
                if ($res) {
                    $row = $res->fetch_assoc();
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

    // ---------------------------------
    // 5. If everything is still OK, handle image move + DB insert
    // ---------------------------------
    if (empty($errors)) {
        if ($profileImageTmpPath && $profileImageExt) {
            $uploadDir = __DIR__ . '/uploads/profile_images';

            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            try {
                $random = bin2hex(random_bytes(8));
            } catch (Exception $e) {
                $random = bin2hex(uniqid('', true));
            }

            $newFilename = time() . '_' . $random . '.' . $profileImageExt;
            $destPath    = $uploadDir . '/' . $newFilename;

            if (!is_writable($uploadDir)) {
                $errors[] = 'Profile image directory is not writable. Please check folder permissions.';
            } else {
                if (!move_uploaded_file($profileImageTmpPath, $destPath)) {
                    $errors[] = 'Failed to save the uploaded profile image.';
                } else {
                    $profile_image_path = 'uploads/profile_images/' . $newFilename;
                }
            }
        }

        if (empty($errors)) {
            $full_name = trim($first_name . ' ' . $last_name);

            $sql = 'INSERT INTO users (
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
                        profile_image,
                        created_at
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                    )';

            $stmt = $mysqli->prepare($sql);
            if (!$stmt) {
                $errors[] = 'Database error while preparing insert: ' . $mysqli->error;
            } else {
                $stmt->bind_param(
                    'ssssssssssssssss',
                    $full_name,
                    $first_name,
                    $last_name,
                    $username,
                    $password,      // plain text (you’ll hash later)
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
                    $profile_image_path
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    $msg = urlencode('User created successfully.');
                    header("Location: manage_users.php?msg={$msg}");
                    exit;
                } else {
                    $errors[] = 'Failed to create user: ' . $stmt->error;
                    $stmt->close();
                }
            }
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
      <h2 class="mb-0">USER REGISTRATION</h2>
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
              <div class="reg-label">Password <span class="text-danger">*</span></div>
              <input
                type="password"
                class="form-control form-control-sm reg-input"
                id="password"
                name="password"
                value="<?php echo htmlspecialchars($password, ENT_QUOTES, 'UTF-8'); ?>"
                required
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
				placeholder=""
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
				placeholder=""
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
          <div class="reg-section-title">Profile Image (optional)</div>

          <div class="mb-3">
            <div class="reg-label">Upload Profile Image</div>
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
          <button type="submit" class="btn btn-primary btn-sm">Create User</button>
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

    // When user focuses the field
    input.addEventListener('focus', function () {
      if (input.value.trim() === '') {
        input.value = '+91 ';
        // cursor will already be at the end
      }
    });

    // When user leaves the field
    input.addEventListener('blur', function () {
      // If they didn't type anything except the prefix, clear it again
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

    // If empty, do nothing
    if (val === '') return;

    // If user already typed an @something.com, don't change it
    if (val.indexOf('@') !== -1) return;

    // Otherwise, user typed just the name -> append @gmail.com
    emailInput.value = val + defaultSuffix;
  });
});
</script>





<?php include 'footer.php'; ?>
