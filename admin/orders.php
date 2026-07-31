<?php
include '../login/auth.php';
include 'db.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['new_status'])) {
    $order_id  = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $conn->query("UPDATE orders SET status = '$new_status' WHERE id = $order_id");
    header("Location: orders.php?success=1");
    exit();
}

// Fetch all orders with customer info (user_id → users, fallback on username)
$orders_query = "
    SELECT o.*,
           COALESCE(u.username, o.username) AS customer_name,
           u.email AS customer_email
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.created_at DESC
";
$orders_result = mysqli_query($conn, $orders_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Fulfillment & Tracking - Laptop Admin</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .status-select {
      padding: 0.35rem 0.6rem; border-radius: 8px;
      border: 1px solid var(--border-color); background: #fff;
      font-weight: 600; font-size: 0.82rem; cursor: pointer;
    }
    .badge-paid     { background: rgba(16,185,129,0.12); color: #047857; }
    .badge-packed   { background: rgba(2,132,199,0.1);   color: #0284c7; }
    .badge-shipped  { background: rgba(245,158,11,0.1);  color: #d97706; }
    .badge-delivered{ background: rgba(16,185,129,0.1);  color: #10b981; }
    .badge-cancelled{ background: rgba(225,29,72,0.1);   color: #e11d48; }

    /* Order items inside each row — expandable */
    .items-cell { font-size: 0.72rem; color: var(--text-muted); }
    .items-cell span { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Column widths for this 7-col orders table */
    #ordersTable th:nth-child(1), #ordersTable td:nth-child(1) { width: 8%;  }
    #ordersTable th:nth-child(2), #ordersTable td:nth-child(2) { width: 14%; }
    #ordersTable th:nth-child(3), #ordersTable td:nth-child(3) { width: 24%; }
    #ordersTable th:nth-child(4), #ordersTable td:nth-child(4) { width: 10%; }
    #ordersTable th:nth-child(5), #ordersTable td:nth-child(5) { width: 14%; }
    #ordersTable th:nth-child(6), #ordersTable td:nth-child(6) { width: 14%; }
    #ordersTable th:nth-child(7), #ordersTable td:nth-child(7) { width: 16%; }
  </style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main-content">
    <header class="header">
      <h1>Order Tracking & Fulfillment</h1>
      <span class="badge badge-success">Live Logistics</span>
    </header>

    <?php if (isset($_GET['success'])): ?>
      <div style="padding:10px 14px;background:#dcfce7;color:#15803d;border-radius:8px;margin-bottom:1rem;font-weight:600;font-size:0.88rem;">
        Order status updated successfully.
      </div>
    <?php endif; ?>

    <section class="table-container">
      <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
        <h3>Customer Orders & Tracking Pipeline</h3>
        <span style="font-size:0.8rem;color:var(--text-muted);"><?php
          $cnt = $conn->query("SELECT COUNT(*) AS c FROM orders");
          echo $cnt ? $cnt->fetch_assoc()['c'] . ' orders total' : '';
        ?></span>
      </div>

      <table id="ordersTable">
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Total</th>
            <th>Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($orders_result && mysqli_num_rows($orders_result) > 0): ?>
            <?php while ($order = mysqli_fetch_assoc($orders_result)): ?>
              <?php
                // Fetch order items
                $items_sql = "SELECT oi.quantity, oi.unit_price, l.title
                              FROM order_items oi
                              LEFT JOIN laptop l ON oi.laptop_id = l.id
                              WHERE oi.order_id = " . intval($order['id']);
                $items_r = mysqli_query($conn, $items_sql);
                $item_labels = [];
                if ($items_r && mysqli_num_rows($items_r) > 0) {
                    while ($it = mysqli_fetch_assoc($items_r)) {
                        $item_labels[] = htmlspecialchars($it['title'] ?? 'Laptop') . ' ×' . intval($it['quantity']);
                    }
                } else {
                    // Fallback for old single-laptop orders
                    $fall = mysqli_query($conn, "SELECT title FROM laptop WHERE id = " . intval($order['laptop_id'] ?? 0) . " LIMIT 1");
                    if ($fall && mysqli_num_rows($fall) > 0) {
                        $frow = mysqli_fetch_assoc($fall);
                        $item_labels[] = htmlspecialchars($frow['title']) . ' ×1';
                    }
                }

                $st = $order['status'] ?? '';
                $badge_class = 'badge-paid';
                if (strpos($st, 'Packed') !== false)   $badge_class = 'badge-packed';
                elseif ($st === 'Shipped')              $badge_class = 'badge-shipped';
                elseif ($st === 'Delivered')            $badge_class = 'badge-delivered';
                elseif ($st === 'Cancelled')            $badge_class = 'badge-cancelled';
              ?>
              <tr>
                <td>#<?php echo $order['id']; ?></td>
                <td>
                  <strong><?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?></strong>
                  <?php if (!empty($order['customer_email'])): ?>
                    <br><small style="color:var(--text-muted);font-size:0.68rem;"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                  <?php endif; ?>
                </td>
                <td class="items-cell">
                  <?php if (empty($item_labels)): ?>
                    <span style="color:var(--text-muted);">—</span>
                  <?php else: ?>
                    <?php foreach ($item_labels as $lbl): ?>
                      <span><?php echo $lbl; ?></span>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
                <td><strong>$<?php echo number_format(floatval($order['total_amount'] ?? $order['amount'] ?? 0), 2); ?></strong></td>
                <td><?php echo date("M d, Y H:i", strtotime($order['created_at'])); ?></td>
                <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                <td>
                  <form action="orders.php" method="POST" style="display:flex;gap:0.4rem;align-items:center;">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <select name="new_status" class="status-select">
                      <option value="Paid - To Be Packed"   <?php echo ($st === 'Paid - To Be Packed')   ? 'selected' : ''; ?>>To Be Packed</option>
                      <option value="Packed - Ready to Ship" <?php echo ($st === 'Packed - Ready to Ship') ? 'selected' : ''; ?>>Ready to Ship</option>
                      <option value="Shipped"               <?php echo ($st === 'Shipped')               ? 'selected' : ''; ?>>Shipped</option>
                      <option value="Delivered"             <?php echo ($st === 'Delivered')             ? 'selected' : ''; ?>>Delivered</option>
                      <option value="Cancelled"             <?php echo ($st === 'Cancelled')             ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <button type="submit" style="padding:0.32rem 0.6rem;font-size:0.75rem;border-radius:6px;border:none;cursor:pointer;background:var(--accent-blue);color:#fff;font-weight:600;">Save</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align:center;color:var(--text-muted);padding:2rem;">No orders recorded yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>

</body>
</html>