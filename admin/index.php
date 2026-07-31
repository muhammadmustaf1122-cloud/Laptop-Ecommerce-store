<?php
include '../login/auth.php';
include 'db.php';

// Fetch dynamic counts from database
$total_laptops    = $conn->query("SELECT COUNT(*) AS total FROM laptop")->fetch_assoc()['total'];
$active_brands    = $conn->query("SELECT COUNT(*) AS total FROM brand")->fetch_assoc()['total'];
$total_categories = $conn->query("SELECT COUNT(*) AS total FROM categories")->fetch_assoc()['total'];

$total_users      = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Laptop Admin</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <!-- Smart Sidebar -->
  <?php include 'sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content">
    <header class="header">
      <div style="display: flex; align-items: center;">
        <button class="mobile-toggle-btn" id="mobileToggleSidebar" title="Toggle Sidebar">☰</button>
        <h1>Overview Dashboard</h1>
      </div>
      <span class="badge badge-success">Admin Connected</span>
    </header>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="card">
        <div class="title">Total Laptops</div>
        <div class="value"><?php echo $total_laptops; ?></div>
      </div>
      <div class="card">
        <div class="title">Active Brands</div>
        <div class="value"><?php echo $active_brands; ?></div>
      </div>
      <div class="card">
        <div class="title">Categories</div>
        <div class="value"><?php echo $total_categories; ?></div>
      </div>
    </div>

    <!-- Recent System Activity Table -->
    <section class="table-container">
      <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color);">
        <h3>Recent Catalog Activity</h3>
      </div>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Title</th>
            <th>Price</th>
            <th>Stock Status</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $latest_laptops = $conn->query("SELECT * FROM laptop ORDER BY id DESC LIMIT 5");
          if ($latest_laptops && $latest_laptops->num_rows > 0):
            while ($row = $latest_laptops->fetch_assoc()):
          ?>
            <tr>
              <td>#LP-<?php echo $row['id']; ?></td>
              <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
              <td>$<?php echo number_format($row['price'], 2); ?></td>
              <td>
                <span class="badge <?php echo ($row['stock'] > 0) ? 'badge-success' : 'badge-danger'; ?>">
                  <?php echo ($row['stock'] > 0) ? $row['stock'] . ' In Stock' : 'Out of Stock'; ?>
                </span>
              </td>
            </tr>
          <?php 
            endwhile;
          else:
          ?>
            <tr>
              <td colspan="4" style="text-align: center; color: var(--text-muted);">No activity recorded yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>

</body>
</html>