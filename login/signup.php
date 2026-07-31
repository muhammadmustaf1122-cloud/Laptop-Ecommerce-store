<?php
session_start();
include '../admin/db.php';

$message = "";
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    // Simple Rate Limiting check against spam bots (minimum 3 seconds between requests)
    if (isset($_SESSION['last_signup_time']) && (time() - $_SESSION['last_signup_time'] < 3)) {
        $message = '<div class="alert alert-danger">Please wait a few seconds before trying again.</div>';
    } elseif (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid form submission.</div>';
    } else {
        $_SESSION['last_signup_time'] = time();
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $email === '' || $password === '') {
            $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Please enter a valid email address.</div>';
        } elseif (strlen($password) < 8) {
            $message = '<div class="alert alert-danger">Password must be at least 8 characters long.</div>';
        } else {
            $check_stmt = $conn->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $check_stmt->bind_param('ss', $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows > 0) {
                $message = '<div class="alert alert-danger">Username or Email is already registered!</div>';
            } else {
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $role = 'customer';
                $is_verified = 0; // Requires email validation to prevent fake account spam
                $verification_token = bin2hex(random_bytes(32));

                $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssssis', $username, $email, $hashed_pass, $role, $is_verified, $verification_token);

                if ($stmt->execute()) {
                    $log_action = 'New customer signed up: "' . $username . '"';
                    $log_stmt = $conn->prepare("INSERT INTO activity_logs (action, performed_by, status) VALUES (?, ?, 'Completed')");
                    if ($log_stmt) {
                        $performed_by = 'System';
                        $log_stmt->bind_param('ss', $log_action, $performed_by);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }

                  $activation_link = "http://localhost/laptop/login/verify.php?token=" . $verification_token;
$message = '<div class="alert alert-success">Account created! <br><strong>Local Testing Link:</strong> <a href="' . $activation_link . '">Click here to verify</a></div>';
                } else {
                    $message = '<div class="alert alert-danger">Error creating account: ' . htmlspecialchars($conn->error) . '</div>';
                }
                $stmt->close();
            }
            $check_stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up - Laptop Store</title>
  <style>
    :root {
      --bg-main: #f4f6f9;
      --card-bg: #ffffff;
      --primary: #0088cc;
      --primary-hover: #006699;
      --text-dark: #1e293b;
      --text-muted: #64748b;
      --border-color: #e2e8f0;
      --success-bg: #dcfce7;
      --success-text: #15803d;
      --danger-bg: #fee2e2;
      --danger-text: #991b1b;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

    body {
      background-color: var(--bg-main);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 1rem;
    }

    .auth-card {
      background: var(--card-bg);
      width: 100%;
      max-width: 420px;
      padding: 2.5rem;
      border-radius: 12px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--border-color);
    }

    .auth-header { text-align: center; margin-bottom: 1.5rem; }
    .auth-header h2 { color: var(--text-dark); font-size: 1.5rem; margin-bottom: 0.5rem; }
    .auth-header p { color: var(--text-muted); font-size: 0.875rem; }

    .alert { padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1.25rem; }
    .alert-success { background: var(--success-bg); color: var(--success-text); }
    .alert-danger { background: var(--danger-bg); color: var(--danger-text); }

    .form-group { margin-bottom: 1.25rem; }
    .form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-dark); margin-bottom: 0.4rem; }
    .form-group input { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.9rem; outline: none; }
    .form-group input:focus { border-color: var(--primary); }

    .btn-submit {
      width: 100%;
      padding: 0.75rem;
      background-color: var(--primary);
      color: #fff;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-submit:hover { background-color: var(--primary-hover); }

    .auth-footer { margin-top: 1.5rem; text-align: center; font-size: 0.875rem; color: var(--text-muted); }
    .auth-footer a { color: var(--primary); text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>

  <div class="auth-card">
    <div class="auth-header">
      <h2>🛒 Join Laptop Store</h2>
      <p>Create an account to start shopping</p>
    </div>

    <?php echo $message; ?>

    <form action="signup.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="johndoe" required autofocus>
      </div>

      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" placeholder="john@example.com" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" required>
      </div>

      <button type="submit" name="signup" class="btn-submit">Create Account</button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="login.php">Sign In</a>
    </div>
  </div>

</body>
</html>