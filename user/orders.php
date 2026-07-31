<?php
session_start();
include '../admin/db.php';

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

$user_id  = intval($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? '';

// Fetch orders for this user — join on user_id, fall back to username for old rows
$orders_query = "SELECT o.*
                 FROM orders o
                 WHERE o.user_id = $user_id OR o.username = '" . mysqli_real_escape_string($conn, $username) . "'
                 ORDER BY o.id DESC";
$orders_result = mysqli_query($conn, $orders_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders & Tracking - LAPTOP3D</title>
  <style>
    :root {
      --bg-dim: #f0eee9;
      --card-glass: rgba(255, 255, 255, 0.75);
      --card-border: rgba(255, 255, 255, 0.9);
      --accent-blue: #0284c7;
      --text-dark: #1e293b;
      --text-muted: #64748b;
      --shadow-3d: 0 20px 40px rgba(0, 0, 0, 0.08);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
    body { background-color: var(--bg-dim); color: var(--text-dark); min-height: 100vh; padding-bottom: 4rem; }

    nav {
      position: relative; top: 1.5rem; z-index: 100;
      width: 90%; max-width: 1250px; margin: 0 auto 3rem;
      background: rgba(240, 238, 233, 0.75);
      backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--card-border); border-radius: 50px;
      padding: 0.8rem 2rem;
      display: flex; justify-content: space-between; align-items: center;
      box-shadow: 0 10px 30px rgba(0,0,0,0.04);
    }
    .brand-logo { font-size: 1.3rem; font-weight: 800; color: var(--text-dark); text-decoration: none; letter-spacing: -0.5px; }
    .brand-logo span { color: var(--accent-blue); }
    .nav-links { display: flex; gap: 1rem; align-items: center; }
    .nav-links a { color: var(--text-dark); text-decoration: none; font-weight: 600; font-size: 0.9rem; }
    .nav-links a:hover { color: var(--accent-blue); }

    .container { width: 90%; max-width: 1000px; margin: 0 auto; }
    h1 { font-size: 2.2rem; font-weight: 900; margin-bottom: 2rem; letter-spacing: -1px; }

    .order-card {
      background: var(--card-glass); backdrop-filter: blur(16px);
      border: 1.5px solid var(--card-border); border-radius: 24px;
      padding: 2rem; box-shadow: var(--shadow-3d); margin-bottom: 2rem;
    }
    .order-header {
      display: flex; justify-content: space-between; align-items: center;
      border-bottom: 1px solid rgba(0,0,0,0.06);
      padding-bottom: 1rem; margin-bottom: 1.5rem;
      font-size: 0.95rem; color: var(--text-muted);
      flex-wrap: wrap; gap: 0.5rem;
    }
    .order-header strong { color: var(--text-dark); }

    /* Items list in order */
    .order-items-list { margin-bottom: 1.5rem; }
    .order-item-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 0.8rem 0; border-bottom: 1px solid rgba(0,0,0,0.05);
      gap: 1rem;
    }
    .order-item-row:last-child { border-bottom: none; }
    .item-thumb {
      width: 52px; height: 40px; object-fit: cover;
      border-radius: 8px; flex-shrink: 0;
      background: #e2e8f0;
    }
    .item-info h3 { font-size: 1rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.2rem; }
    .item-meta { font-size: 0.85rem; color: var(--text-muted); }
    .item-price { font-size: 1rem; font-weight: 800; color: var(--text-dark); white-space: nowrap; }
    .view-product-link {
      color: var(--accent-blue); text-decoration: none;
      font-weight: 700; font-size: 0.88rem;
      white-space: nowrap; flex-shrink: 0;
    }
    .view-product-link:hover { text-decoration: underline; }

    /* Pipeline */
    .pipeline-title { font-size: 0.82rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 1rem; }
    .pipeline-steps {
      display: flex; justify-content: space-between; align-items: center;
      position: relative; margin-top: 1.5rem; padding: 0 1rem;
    }
    .pipeline-steps::before {
      content: ''; position: absolute; top: 50%; left: 30px; right: 30px;
      height: 3px; background: #cbd5e1; transform: translateY(-50%); z-index: 1;
    }
    .step { position: relative; z-index: 2; display: flex; flex-direction: column; align-items: center; background: var(--bg-dim); padding: 0 10px; }
    .step-circle {
      width: 32px; height: 32px; border-radius: 50%; background: #cbd5e1;
      color: #fff; display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 0.85rem; margin-bottom: 0.4rem;
    }
    .step.completed .step-circle { background: #10b981; }
    .step.active .step-circle { background: var(--accent-blue); box-shadow: 0 0 12px rgba(2,132,199,0.4); }
    .step-label { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-align: center; }
    .step.completed .step-label, .step.active .step-label { color: var(--text-dark); }

    .order-total { text-align: right; font-size: 1rem; color: var(--text-muted); margin-top: 0.5rem; }
    .order-total strong { color: var(--text-dark); font-size: 1.15rem; }
    .no-orders { text-align: center; padding: 3rem; color: var(--text-muted); font-size: 1.1rem; }
  </style>
</head>
<body>
  <nav>
    <a href="index.php" class="brand-logo">LAPTOP<span>3D</span></a>
    <div class="nav-links">
      <a href="index.php">Showroom</a>
      <a href="orders.php" style="color: var(--accent-blue);">My Orders & Tracking</a>
      <a href="../login/logout.php">Logout</a>
    </div>
  </nav>

  <div class="container">
    <h1>My Order History & Tracking</h1>

    <?php 
    if ($orders_result && mysqli_num_rows($orders_result) > 0) {
        while ($order = mysqli_fetch_assoc($orders_result)) {
            $secure_order_ref = 'ORD-' . strtoupper(substr(md5($order['id'] . 'laptop3d_salt'), 0, 8));
            $status      = strtolower($order['status'] ?? 'paid');
            $is_paid      = true;
            $is_packed    = in_array($status, ['packed - ready to ship', 'shipped', 'delivered']);
            $is_shipped   = in_array($status, ['shipped', 'delivered']);
            $is_delivered = ($status === 'delivered');

            // Fetch items for this order from order_items
            $items_sql = "SELECT oi.*, l.title, l.image_url,
                                 (SELECT image_url FROM laptop_images WHERE laptop_id = l.id LIMIT 1) as gallery_img
                          FROM order_items oi
                          LEFT JOIN laptop l ON oi.laptop_id = l.id
                          WHERE oi.order_id = " . intval($order['id']);
            $items_result = mysqli_query($conn, $items_sql);
            $order_items  = [];
            if ($items_result) {
                while ($item = mysqli_fetch_assoc($items_result)) {
                    $order_items[] = $item;
                }
            }

            // If no order_items rows yet (old orders), fall back to single laptop_id
            if (empty($order_items) && !empty($order['laptop_id'])) {
                $fall = mysqli_query($conn, "SELECT l.*, 
                    (SELECT image_url FROM laptop_images WHERE laptop_id = l.id LIMIT 1) as gallery_img
                    FROM laptop l WHERE l.id = " . intval($order['laptop_id']) . " LIMIT 1");
                if ($fall && mysqli_num_rows($fall) > 0) {
                    $frow = mysqli_fetch_assoc($fall);
                    $order_items[] = [
                        'title'      => $frow['title'],
                        'image_url'  => $frow['image_url'],
                        'gallery_img'=> $frow['gallery_img'],
                        'laptop_id'  => $frow['id'],
                        'quantity'   => $order['quantity'] ?? 1,
                        'unit_price' => $order['amount'] ?? 0,
                    ];
                }
            }
    ?>
    <div class="order-card">
      <div class="order-header">
        <span>Tracking ID: <strong><?php echo $secure_order_ref; ?></strong></span>
        <span>Placed: <strong><?php echo date('M d, Y  H:i', strtotime($order['created_at'] ?? 'now')); ?></strong></span>
        <span>Status: <strong style="color:var(--accent-blue);text-transform:capitalize;"><?php echo htmlspecialchars($order['status'] ?? 'Paid'); ?></strong></span>
      </div>

      <!-- Items list -->
      <div class="order-items-list">
        <?php foreach ($order_items as $item): 
            $raw_img = $item['gallery_img'] ?? $item['image_url'] ?? '';
            $thumb   = !empty($raw_img) ? (filter_var($raw_img, FILTER_VALIDATE_URL) ? $raw_img : '../uploads/' . $raw_img) : '';
        ?>
        <div class="order-item-row">
          <?php if ($thumb): ?>
            <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Laptop" class="item-thumb">
          <?php else: ?>
            <div class="item-thumb" style="display:flex;align-items:center;justify-content:center;font-size:0.7rem;color:#94a3b8;">No img</div>
          <?php endif; ?>
          <div class="item-info" style="flex:1;">
            <h3><?php echo htmlspecialchars($item['title'] ?? 'Laptop Model'); ?></h3>
            <div class="item-meta">Qty: <?php echo intval($item['quantity'] ?? 1); ?></div>
          </div>
          <div class="item-price">$<?php echo number_format(floatval($item['unit_price'] ?? 0), 2); ?></div>
          <div style="display:flex; gap: 0.8rem; align-items: center;">
            <?php if ($is_delivered): ?>
              <a href="laptop_detail.php?id=<?php echo intval($item['laptop_id'] ?? 0); ?>#reviews" class="view-product-link" style="color: #fbbf24; font-weight: 800;">★ Leave a Review</a>
            <?php endif; ?>
            <a href="laptop_detail.php?id=<?php echo intval($item['laptop_id'] ?? 0); ?>" class="view-product-link">View &rarr;</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="order-total">Total Paid: <strong>$<?php echo number_format(floatval($order['total_amount'] ?? $order['amount'] ?? 0), 2); ?></strong></div>

      <!-- Logistics Pipeline -->
      <div class="pipeline-title" style="margin-top:1.5rem;">Live Logistics Pipeline</div>
      <div class="pipeline-steps">
        <div class="step <?php echo $is_paid ? 'completed' : ''; ?>">
          <div class="step-circle">&#10003;</div>
          <div class="step-label">Paid</div>
        </div>
        <div class="step <?php echo $is_packed ? 'completed' : (strpos($status,'packed') !== false ? 'active' : ''); ?>">
          <div class="step-circle"><?php echo $is_packed ? '&#10003;' : '2'; ?></div>
          <div class="step-label">Packed</div>
        </div>
        <div class="step <?php echo $is_shipped ? 'completed' : ($status === 'shipped' ? 'active' : ''); ?>">
          <div class="step-circle"><?php echo $is_shipped ? '&#10003;' : '3'; ?></div>
          <div class="step-label">Shipped</div>
        </div>
        <div class="step <?php echo $is_delivered ? 'completed' : ($status === 'delivered' ? 'active' : ''); ?>">
          <div class="step-circle"><?php echo $is_delivered ? '&#10003;' : '4'; ?></div>
          <div class="step-label">Delivered</div>
        </div>
      </div>
    </div>
    <?php 
        }
    } else {
        echo '<div class="no-orders">You haven\'t placed any orders yet.<br><a href="index.php" style="color:var(--accent-blue);font-weight:700;">Browse Showroom &rarr;</a></div>';
    }
    ?>
  </div>
</body>
</html>