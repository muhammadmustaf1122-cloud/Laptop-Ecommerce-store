<?php
session_start();
include '../admin/db.php';

// Auth Guard: Only logged-in customers can access the profile page
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true || ($_SESSION['role'] ?? '') !== 'customer') {
    session_regenerate_id(true);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: ../login/login.php');
    exit();
}

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$username_session = $_SESSION['username'] ?? '';

// Ensure profile_pic column exists in users table
$conn->query("SHOW COLUMNS FROM users LIKE 'profile_pic'");
if ($conn->affected_rows === 0) {
    @$conn->query("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
}

$msg = "";
$msg_type = "success";

// Fetch Current User Details from Database
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? OR username = ? LIMIT 1");
$stmt->bind_param("is", $user_id, $username_session);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header("Location: ../login/logout.php");
    exit();
}

// Handle Profile Updates Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_username = trim($_POST['username'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    
    if ($new_username === '' || $new_email === '' || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid username and email address.";
        $msg_type = "danger";
    } else {
        // Check for duplicate username or email belonging to another user
        $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1");
        $check->bind_param("ssi", $new_username, $new_email, $user['id']);
        $check->execute();
        $check_res = $check->get_result();
        
        if ($check_res && $check_res->num_rows > 0) {
            $msg = "That username or email address is already taken by another account.";
            $msg_type = "danger";
        } else {
            // Handle Profile Picture Upload
            $profile_pic_filename = $user['profile_pic'] ?? null;

            if (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['profile_pic']['tmp_name'];
                $file_name = $_FILES['profile_pic']['name'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                if (in_array($ext, $allowed)) {
                    $upload_dir = __DIR__ . '/../uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                    $new_pic_name = 'profile_' . $user['id'] . '_' . uniqid() . '.' . $ext;
                    $destination = $upload_dir . $new_pic_name;

                    if (move_uploaded_file($file_tmp, $destination)) {
                        $profile_pic_filename = $new_pic_name;
                    }
                }
            }

            // Update Database Record
            if (!empty($new_password)) {
                if (strlen($new_password) < 8) {
                    $msg = "New password must be at least 8 characters long.";
                    $msg_type = "danger";
                } else {
                    $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                    $up_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, profile_pic = ? WHERE id = ?");
                    $up_stmt->bind_param("ssssi", $new_username, $new_email, $hashed_pass, $profile_pic_filename, $user['id']);
                    $up_stmt->execute();
                    $up_stmt->close();
                    $msg = "Profile details and password updated successfully!";
                }
            } else {
                $up_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, profile_pic = ? WHERE id = ?");
                $up_stmt->bind_param("sssi", $new_username, $new_email, $profile_pic_filename, $user['id']);
                $up_stmt->execute();
                $up_stmt->close();
                $msg = "Profile details updated successfully!";
            }

            if ($msg_type === "success") {
                // Update Session variables
                $_SESSION['username'] = $new_username;
                $_SESSION['profile_pic'] = $profile_pic_filename;

                // Refresh user array
                $user['username'] = $new_username;
                $user['email'] = $new_email;
                $user['profile_pic'] = $profile_pic_filename;
            }
        }
        $check->close();
    }
}

// Avatar Display Helper
$avatar_url = '';
if (!empty($user['profile_pic'])) {
    $avatar_url = '../uploads/' . $user['profile_pic'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Account Profile - LAPTOP3D</title>
  <style>
    :root {
      --bg-dim: #f0eee9;
      --card-glass: rgba(255, 255, 255, 0.85);
      --card-border: rgba(255, 255, 255, 0.95);
      --accent-blue: #0284c7;
      --text-dark: #1e293b;
      --text-muted: #64748b;
      --shadow-soft: 0 10px 30px rgba(0, 0, 0, 0.05);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

    body {
      background-color: var(--bg-dim);
      color: var(--text-dark);
      min-height: 100vh;
      padding-bottom: 4rem;
    }

    /* Fixed Glass Navigation */
    nav {
      position: relative;
      top: 1.2rem;
      z-index: 100;
      width: 92%;
      max-width: 1250px;
      margin: 0 auto 3rem;
      background: rgba(240, 238, 233, 0.85);
      backdrop-filter: blur(20px);
      border: 1px solid var(--card-border);
      border-radius: 50px;
      padding: 0.65rem 1.8rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 8px 25px rgba(0,0,0,0.03);
    }

    .brand-logo {
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--text-dark);
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .brand-logo span { color: var(--accent-blue); }

    .nav-links { display: flex; gap: 1.2rem; align-items: center; }
    .nav-links a { color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 0.88rem; transition: color 0.2s; }
    .nav-links a:hover { color: var(--accent-blue); }

    .user-profile-badge {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      background: rgba(255, 255, 255, 0.6);
      padding: 0.3rem 0.8rem;
      border-radius: 20px;
      border: 1px solid var(--card-border);
      font-size: 0.85rem;
    }

    .nav-avatar-img {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      object-fit: cover;
    }

    .btn-logout {
      background: rgba(225, 29, 72, 0.08);
      color: #e11d48 !important;
      border: 1px solid rgba(225, 29, 72, 0.2);
      padding: 0.35rem 0.9rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.82rem;
      text-decoration: none;
    }

    .container {
      width: 92%;
      max-width: 800px;
      margin: 0 auto;
    }

    .profile-card {
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      padding: 2.5rem 2rem;
      box-shadow: var(--shadow-soft);
    }

    .profile-header {
      display: flex;
      align-items: center;
      gap: 1.8rem;
      margin-bottom: 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid rgba(0,0,0,0.06);
    }

    .avatar-wrapper {
      position: relative;
      width: 100px;
      height: 100px;
    }

    .avatar-img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #ffffff;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
      background: #e2e8f0;
    }

    .avatar-placeholder {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: var(--accent-blue);
      color: #ffffff;
      font-size: 2.5rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    }

    .profile-title h1 {
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--text-dark);
      margin-bottom: 0.3rem;
    }

    .profile-title p {
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .alert {
      padding: 0.8rem 1.2rem;
      border-radius: 12px;
      font-size: 0.88rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
    }

    .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1.2rem;
    }

    @media (max-width: 600px) {
      .form-grid { grid-template-columns: 1fr; }
      .profile-header { flex-direction: column; text-align: center; }
    }

    .form-group {
      margin-bottom: 1.2rem;
    }

    .form-group.full-width {
      grid-column: 1 / -1;
    }

    .form-group label {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--text-dark);
      margin-bottom: 0.4rem;
    }

    .form-group input {
      width: 100%;
      padding: 0.75rem 1rem;
      border-radius: 12px;
      border: 1px solid rgba(203, 213, 225, 0.8);
      background: #ffffff;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s;
    }

    .form-group input:focus {
      border-color: var(--accent-blue);
    }

    .file-input-wrapper {
      position: relative;
    }

    .btn-save {
      background: var(--text-dark);
      color: #ffffff;
      padding: 0.75rem 2rem;
      border-radius: 12px;
      font-weight: 700;
      font-size: 0.9rem;
      border: none;
      cursor: pointer;
      transition: background 0.2s, transform 0.2s;
      display: inline-block;
    }

    .btn-save:hover {
      background: var(--accent-blue);
      transform: translateY(-2px);
    }
  </style>
</head>
<body>

  <!-- Fixed Top Glass Navbar -->
  <nav>
    <a href="index.php" class="brand-logo">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>
      LAPTOP<span>3D</span>
    </a>
    
    <div class="nav-links">
      <a href="index.php">Showroom</a>
      <a href="orders.php">Orders & Tracking</a>
      <div class="user-profile-badge">
        <?php if (!empty($avatar_url)): ?>
          <img src="<?php echo htmlspecialchars($avatar_url); ?>" class="nav-avatar-img" alt="Avatar">
        <?php else: ?>
          <span class="user-status-dot"></span>
        <?php endif; ?>
        <span>Hi, <strong class="user-name"><?php echo htmlspecialchars($user['username']); ?></strong></span>
      </div>
      <a href="../login/logout.php" class="btn-logout">Logout</a>
    </div>
  </nav>

  <div class="container">
    <div class="profile-card">
      
      <div class="profile-header">
        <div class="avatar-wrapper">
          <?php if (!empty($avatar_url)): ?>
            <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="Profile Picture" class="avatar-img">
          <?php else: ?>
            <div class="avatar-placeholder">
              <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="profile-title">
          <h1>Customer Account Profile</h1>
          <p>Update your personal details, avatar picture, and password.</p>
        </div>
      </div>

      <?php if (!empty($msg)): ?>
        <div class="alert alert-<?php echo $msg_type; ?>">
          <?php echo htmlspecialchars($msg); ?>
        </div>
      <?php endif; ?>

      <form action="profile.php" method="POST" enctype="multipart/form-data">
        <div class="form-grid">
          
          <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
          </div>

          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
          </div>

          <div class="form-group full-width">
            <label for="profile_pic">Upload New Profile Picture</label>
            <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
          </div>

          <div class="form-group full-width">
            <label for="new_password">Change Password (Leave blank to keep current password)</label>
            <input type="password" id="new_password" name="new_password" placeholder="Minimum 8 characters">
          </div>

          <div class="form-group full-width" style="margin-top: 1rem;">
            <button type="submit" name="update_profile" class="btn-save">Save Profile Changes</button>
            <a href="index.php" style="margin-left: 1rem; color: var(--text-muted); text-decoration: none; font-weight: 600; font-size: 0.9rem;">&larr; Back to Showroom</a>
          </div>

        </div>
      </form>

    </div>
  </div>

</body>
</html>
