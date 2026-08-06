<?php
session_start();
include '../admin/db.php';

$msg = "";
$prefilled_email = $_GET['email'] ?? '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $msg = '<div style="padding: 10px; background: #fee2e2; color: #991b1b; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem;">Invalid form submission.</div>';
    } else {
        $email = trim($_POST['email'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if (empty($email) || empty($reason) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = '<div style="padding: 10px; background: #fee2e2; color: #991b1b; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem;">Please fill in all fields with a valid email.</div>';
        } else {
            $stmt = $conn->prepare("INSERT INTO user_appeals (email, reason) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("ss", $email, $reason);
                if ($stmt->execute()) {
                    $msg = '<div style="padding: 10px; background: #dcfce7; color: #15803d; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem;">Your appeal has been submitted successfully. The administrator will review it.</div>';
                } else {
                    $msg = '<div style="padding: 10px; background: #fee2e2; color: #991b1b; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem;">Error submitting appeal. Please try again.</div>';
                }
                $stmt->close();
            } else {
                $msg = '<div style="padding: 10px; background: #fee2e2; color: #991b1b; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem;">Database error. Please try again later.</div>';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Appeal - Laptop Store</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 1rem; }
    .appeal-card { background: #ffffff; width: 100%; max-width: 400px; padding: 2.5rem; border-radius: 12px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
    h2 { color: #1e293b; font-size: 1.5rem; margin-bottom: 0.5rem; text-align: center; }
    p { color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem; text-align: center; }
    .form-group { margin-bottom: 1.25rem; }
    label { display: block; font-size: 0.85rem; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; }
    input, textarea { width: 100%; padding: 0.75rem 1rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9rem; outline: none; }
    input:focus, textarea:focus { border-color: #0088cc; }
    button { width: 100%; padding: 0.75rem; background-color: #0088cc; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: background 0.2s; }
    button:hover { background-color: #006699; }
    .auth-footer { margin-top: 1.5rem; text-align: center; font-size: 0.875rem; color: #64748b; }
    .auth-footer a { color: #0088cc; text-decoration: none; font-weight: 600; }
  </style>
</head>
<body>

  <div class="appeal-card">
    <h2>Account Appeal</h2>
    <p>Your account is currently blocked. Submit your details below to request access from the admin.</p>

    <?php echo $msg; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
      <div class="form-group">
        <label>Your Email Address</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($prefilled_email, ENT_QUOTES, 'UTF-8'); ?>" required>
      </div>

      <div class="form-group">
        <label>Reason for Appeal</label>
        <textarea name="reason" rows="4" placeholder="Explain why your account should be unblocked..." required></textarea>
      </div>

      <button type="submit">Submit Appeal</button>
    </form>
    
    <div class="auth-footer">
      <a href="login.php">&larr; Back to Sign In</a>
    </div>
  </div>

</body>
</html>