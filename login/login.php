<?php
session_start();
include '../admin/db.php';

$error = "";
$step = 1; // Step 1: Credentials, Step 2: OTP Verification

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle Step 1: Username & Password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_credentials'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter your username and password.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param('ss', $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user['password']) || $password === $user['password']) {
                        
                        // Check if the user account is blocked (is_verified == 0)
                        if (isset($user['is_verified']) && intval($user['is_verified']) === 0) {
                            $error = 'Your account has been blocked by the admin. <a href="appeal.php?email=' . urlencode($user['email']) . '" style="color: #006699; text-decoration: underline; font-weight: bold;">Click here to submit an appeal</a>';
                        } else {
                            // Admins and dummy accounts bypass runtime 2FA/OTP completely
                            if ($user['role'] === 'admin') {
                                session_regenerate_id(true);
                                $_SESSION['user_id'] = (int) $user['id'];
                                $_SESSION['username'] = $user['username'];
                                $_SESSION['role'] = $user['role'];
                                $_SESSION['admin_logged_in'] = true;
                                $_SESSION['admin_user'] = $user['username'];
                                header('Location: /laptop/admin/index.php');
                                exit();
                            }

                            // Generate a secure 6-digit runtime OTP code valid for 5 minutes
                            $otp = rand(100000, 999999);
                            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                            $update_otp = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
                            $update_otp->bind_param('ssi', $otp, $expires, $user['id']);
                            $update_otp->execute();
                            $update_otp->close();

                            // Store user ID temporarily in session for OTP verification step
                            $_SESSION['temp_user_id'] = $user['id'];
                            $step = 2; // Move to OTP step
                            
                            // Local Testing Helper: Store code in a variable to display on screen safely
                            $debug_otp_notice = "Runtime 2FA Code generated: <strong>$otp</strong> (Valid for 5 mins)";
                        }
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } else {
                    $error = 'Account not found.';
                }
                $stmt->close();
            }
        }
    }
}

// Handle Step 2: Runtime OTP Verification submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $step = 2; // Keep on step 2 if validation fails
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $entered_otp = trim($_POST['otp_code'] ?? '');
        $temp_user_id = $_SESSION['temp_user_id'] ?? 0;

        if ($entered_otp === '' || !$temp_user_id) {
            $error = 'Please enter the verification code.';
        } else {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND otp_code = ? AND otp_expires_at > NOW() LIMIT 1");
            $stmt->bind_param('is', $temp_user_id, $entered_otp);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Double check if user became blocked during session
                if (isset($user['is_verified']) && intval($user['is_verified']) === 0) {
                    $error = 'Your account has been blocked by the admin. <a href="appeal.php?email=' . urlencode($user['email']) . '" style="color: #006699; text-decoration: underline; font-weight: bold;">Click here to submit an appeal</a>';
                    $step = 1;
                } else {
                    // Clear OTP data so it cannot be reused
                    $clear_otp = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL, is_verified = 1 WHERE id = ?");
                    $clear_otp->bind_param('i', $user['id']);
                    $clear_otp->execute();
                    $clear_otp->close();

                    // Complete login session
                    session_regenerate_id(true);
                    $_SESSION['user_id']           = (int) $user['id'];
                    $_SESSION['username']          = $user['username'];
                    $_SESSION['role']              = $user['role'];
                    $_SESSION['customer_logged_in']= true;
                    $_SESSION['profile_pic']       = $user['profile_pic'] ?? '';
                    unset($_SESSION['temp_user_id']);

                    header('Location: /laptop/user/index.php');
                    exit();
                }
            } else {
                $error = 'Invalid or expired verification code.';
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In - Laptop Store</title>
  <style>
    :root {
      --bg-main: #f0eee9;
      --card-bg: rgba(255, 255, 255, 0.95);
      --primary: #0284c7;
      --primary-hover: #0369a1;
      --text-dark: #1e293b;
      --text-muted: #64748b;
      --border-color: rgba(226, 232, 240, 0.9);
      --danger-bg: #fee2e2;
      --danger-text: #991b1b;
      --info-bg: #e0f2fe;
      --info-text: #0369a1;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

    body {
      background-color: var(--bg-main);
      color: var(--text-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      min-height: 100dvh;
      padding: 1.25rem 1rem;
    }

    .login-card {
      background: var(--card-bg);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      width: 100%;
      max-width: 420px;
      padding: clamp(1.5rem, 5vw, 2.5rem);
      border-radius: 20px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.06);
      border: 1px solid var(--border-color);
      transition: transform 0.3s ease;
    }

    .login-header { text-align: center; margin-bottom: 1.75rem; }
    .login-header h2 { color: var(--text-dark); font-size: clamp(1.25rem, 4vw, 1.5rem); font-weight: 800; margin-bottom: 0.4rem; }
    .login-header p { color: var(--text-muted); font-size: clamp(0.8rem, 3vw, 0.875rem); }

    .alert-error {
      background-color: var(--danger-bg);
      color: var(--danger-text);
      padding: 0.75rem 1rem;
      border-radius: 10px;
      font-size: 0.85rem;
      margin-bottom: 1.25rem;
      line-height: 1.4;
      word-break: break-word;
    }

    .alert-info {
      background-color: var(--info-bg);
      color: var(--info-text);
      padding: 0.75rem 1rem;
      border-radius: 10px;
      font-size: 0.85rem;
      margin-bottom: 1.25rem;
      text-align: center;
      word-break: break-word;
    }

    .form-group { margin-bottom: 1.15rem; }
    .form-group label { display: block; font-size: 0.825rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.4rem; }
    .form-group input {
      width: 100%;
      padding: 0.8rem 1rem;
      border: 1px solid var(--border-color);
      border-radius: 10px;
      font-size: 0.95rem;
      outline: none;
      background: #ffffff;
      transition: all 0.2s ease;
    }
    .form-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15); }
    .form-group input#otp_code { text-align: center; letter-spacing: 4px; font-weight: bold; font-size: 1.1rem; }

    .btn-submit {
      width: 100%;
      padding: 0.85rem;
      background-color: var(--primary);
      color: #fff;
      border: none;
      border-radius: 12px;
      font-weight: 700;
      font-size: 0.95rem;
      cursor: pointer;
      transition: background 0.2s ease, transform 0.1s ease;
    }
    .btn-submit:hover { background-color: var(--primary-hover); transform: translateY(-1px); }
    .btn-submit:active { transform: translateY(0); }

    .auth-footer { margin-top: 1.5rem; text-align: center; font-size: 0.875rem; color: var(--text-muted); }
    .auth-footer a { color: var(--primary); text-decoration: none; font-weight: 700; }

    @media (max-width: 480px) {
      body { padding: 0.75rem; }
      .login-card { padding: 1.5rem 1.2rem; border-radius: 16px; }
    }
  </style>
</head>
<body>

  <div class="login-card">
    <div class="login-header">
      <h2>💻 Laptop Store Sign In</h2>
      <p><?php echo $step === 1 ? 'Enter your credentials to continue' : 'Enter your 6-digit security code'; ?></p>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert-error"><?php echo $error; // using raw echo so the HTML anchor tag renders properly ?></div>
    <?php endif; ?>

    <?php if (isset($debug_otp_notice)): ?>
      <div class="alert-info"><?php echo $debug_otp_notice; ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <!-- Step 1 Form -->
      <form action="login.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="form-group">
          <label for="username">Username or Email</label>
          <input type="text" id="username" name="username" placeholder="e.g. johndoe" required autofocus>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="••••••••" required>
        </div>

        <button type="submit" name="login_credentials" class="btn-submit">Sign In</button>
      </form>

      <div class="auth-footer">
        Don't have an account? <a href="signup.php">Create Account</a>
      </div>

    <?php else: ?>
      <!-- Step 2 OTP Verification Form -->
      <form action="login.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <div class="form-group">
          <label for="otp_code">6-Digit Runtime Code</label>
          <input type="text" id="otp_code" name="otp_code" placeholder="123456" maxlength="6" required autofocus>
        </div>

        <button type="submit" name="verify_otp" class="btn-submit">Verify & Login</button>
      </form>

      <div class="auth-footer">
        <a href="login.php">&larr; Back to Sign In</a>
      </div>
    <?php endif; ?>

  </div>

</body>
</html>