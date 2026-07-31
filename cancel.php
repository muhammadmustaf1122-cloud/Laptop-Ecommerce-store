<?php
session_start();
require('admin/db.php');

$performed_by = $_SESSION['username'] ?? 'Guest Customer';
$action = "Cancelled Stripe Payment Checkout";
$status = "Cancelled";

$stmt = $conn->prepare("INSERT INTO activity_logs (action, performed_by, status, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("sss", $action, $performed_by, $status);
$stmt->execute();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Cancelled - CyberStore</title>
  <style>
    :root {
      --bg-dim: #f0eee9;
      --card-glass: rgba(255, 255, 255, 0.75);
      --card-border: rgba(255, 255, 255, 0.9);
      --accent-blue: #0284c7;
      --text-dark: #1e293b;
      --text-muted: #64748b;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body {
      background-color: var(--bg-dim);
      color: var(--text-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 2rem;
    }
    .cancel-card {
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      border: 1.5px solid var(--card-border);
      border-radius: 24px;
      padding: 3rem 2rem;
      max-width: 500px;
      width: 100%;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0,0,0,0.08);
    }
    .cancel-icon {
      width: 70px;
      height: 70px;
      background: rgba(225, 29, 72, 0.1);
      color: #e11d48;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
    }
    h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-dark); }
    p { color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 2rem; }
    .btn-home {
      background: var(--text-dark);
      color: #fff;
      text-decoration: none;
      padding: 0.8rem 2rem;
      border-radius: 12px;
      font-weight: 700;
      transition: background 0.2s, transform 0.2s;
      display: inline-block;
    }
    .btn-home:hover {
      background: var(--accent-blue);
      transform: translateY(-2px);
    }
  </style>
</head>
<body>

  <div class="cancel-card">
    <div class="cancel-icon">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
      </svg>
    </div>
    <h1>Payment Cancelled</h1>
    <p>Your payment session was cancelled and no charges were made. You can try again whenever you're ready.</p>
    <a href="user/index.php" class="btn-home">Return to Showroom</a>
  </div>

</body>
</html>