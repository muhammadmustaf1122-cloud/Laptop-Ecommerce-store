<?php
session_start();
require('admin/db.php');

$user_id       = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
$performed_by  = $_SESSION['username'] ?? 'Guest Customer';
$session_id    = isset($_GET['session_id']) ? mysqli_real_escape_string($conn, $_GET['session_id']) : '';

// Support single laptop_id (legacy) OR cart JSON for multi-item orders
$laptop_id = isset($_GET['laptop_id']) ? intval($_GET['laptop_id']) : 0;

// Cart items passed as JSON in session (set by checkout page before redirecting to Stripe)
$cart_items = $_SESSION['checkout_cart'] ?? [];

// Prevent duplicate order if user refreshes the success page
$already_logged = false;
if (!empty($session_id)) {
    $check_dup = mysqli_query($conn, "SELECT id FROM activity_logs WHERE action LIKE '%$session_id%' LIMIT 1");
    if ($check_dup && mysqli_num_rows($check_dup) > 0) {
        $already_logged = true;
    }
}

if (!$already_logged) {
    $total_amount = 0.00;

    // ------------------------------------------------------------------
    // Build the item list from cart session OR single laptop_id fallback
    // ------------------------------------------------------------------
    $items_to_insert = [];

    if (!empty($cart_items) && is_array($cart_items)) {
        // Multi-item cart checkout
        foreach ($cart_items as $item) {
            $lid   = intval($item['id'] ?? 0);
            $qty   = max(1, intval($item['qty'] ?? 1));
            $price = floatval($item['price'] ?? 0);
            if ($lid > 0 && $price > 0) {
                $items_to_insert[]  = ['laptop_id' => $lid, 'qty' => $qty, 'unit_price' => $price];
                $total_amount      += $price * $qty;
            }
        }
    } elseif ($laptop_id > 0) {
        // Single-laptop checkout (legacy path)
        $price_row = mysqli_query($conn, "SELECT price FROM laptop WHERE id = $laptop_id LIMIT 1");
        if ($price_row && mysqli_num_rows($price_row) > 0) {
            $laptop_data            = mysqli_fetch_assoc($price_row);
            $unit_price             = floatval($laptop_data['price']);
            $items_to_insert[]      = ['laptop_id' => $laptop_id, 'qty' => 1, 'unit_price' => $unit_price];
            $total_amount           = $unit_price;
        }
    }

    if (!empty($items_to_insert)) {
        // 1. Insert the parent order
        $order_stmt = $conn->prepare(
            "INSERT INTO orders (user_id, username, total_amount, amount, status, created_at)
             VALUES (?, ?, ?, ?, 'Paid - To Be Packed', NOW())"
        );
        $null_uid = $user_id ?: null;
        $order_stmt->bind_param('isdd', $null_uid, $performed_by, $total_amount, $total_amount);
        $order_stmt->execute();
        $order_id = $order_stmt->insert_id;
        $order_stmt->close();

        // 2. Insert order_items rows
        foreach ($items_to_insert as $item) {
            $oi_stmt = $conn->prepare(
                "INSERT INTO order_items (order_id, laptop_id, quantity, unit_price) VALUES (?, ?, ?, ?)"
            );
            $oi_stmt->bind_param('iiid', $order_id, $item['laptop_id'], $item['qty'], $item['unit_price']);
            $oi_stmt->execute();
            $oi_stmt->close();
        }

        // 3. Activity log
        $laptop_label = count($items_to_insert) === 1
            ? 'Laptop ID: ' . $items_to_insert[0]['laptop_id']
            : count($items_to_insert) . ' items';
        $action = "Completed Stripe Payment – Order #$order_id ($laptop_label)" . (!empty($session_id) ? " [Session: $session_id]" : "");
        $status = "Completed";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (action, performed_by, status, created_at) VALUES (?, ?, ?, NOW())");
        $log_stmt->bind_param("sss", $action, $performed_by, $status);
        $log_stmt->execute();
        $log_stmt->close();
    }

    // Clear the cart from session after successful checkout
    unset($_SESSION['checkout_cart']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Successful - CyberStore</title>
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
    .success-card {
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
    .success-icon {
      width: 70px; height: 70px;
      background: rgba(16, 185, 129, 0.1);
      color: #10b981;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
    }
    h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.5rem; color: var(--text-dark); }
    p { color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin-bottom: 2rem; }
    .btn-home {
      background: var(--text-dark); color: #fff;
      text-decoration: none; padding: 0.8rem 2rem;
      border-radius: 12px; font-weight: 700;
      transition: background 0.2s, transform 0.2s;
      display: inline-block; margin: 0.4rem;
    }
    .btn-orders {
      background: var(--accent-blue); color: #fff;
      text-decoration: none; padding: 0.8rem 2rem;
      border-radius: 12px; font-weight: 700;
      transition: background 0.2s, transform 0.2s;
      display: inline-block; margin: 0.4rem;
    }
    .btn-home:hover, .btn-orders:hover { transform: translateY(-2px); opacity: 0.9; }
  </style>
</head>
<body>
  <div class="success-card">
    <div class="success-icon">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="20 6 9 17 4 12"></polyline>
      </svg>
    </div>
    <h1>Payment Successful!</h1>
    <p>Thank you for your purchase. Your payment was processed successfully via Stripe, and your order is now confirmed. You can track your order status below.</p>
    <a href="user/orders.php" class="btn-orders">Track My Orders</a>
    <a href="user/index.php" class="btn-home">Return to Showroom</a>
  </div>
</body>
</html>