<?php
include '../login/auth.php';
include 'db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle Unblocking or Deleting Appeal Action
if (isset($_GET['action']) && isset($_GET['id'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'] ?? '')) {
        $_SESSION['flash_msg'] = '<div style="padding: 10px; background: #fee2e2; color: #991b1b; border-radius: 6px; margin-bottom: 1rem;">CSRF validation failed.</div>';
    } else {
        $appeal_id = intval($_GET['id']);
        $action = $_GET['action'];

        // Fetch email associated with the appeal
        $stmt = $conn->prepare("SELECT email FROM user_appeals WHERE id = ?");
        $stmt->bind_param("i", $appeal_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
            $target_email = $res['email'];

            if ($action === 'unblock') {
                // Unblock user in users table
                $unblock = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
                $unblock->bind_param("s", $target_email);
                $unblock->execute();
                $unblock->close();

                // Mark appeal as resolved/deleted
                $del = $conn->prepare("DELETE FROM user_appeals WHERE id = ?");
                $del->bind_param("i", $appeal_id);
                $del->execute();
                $del->close();
                
                $_SESSION['flash_msg'] = '<div style="padding: 10px; background: #dcfce7; color: #15803d; border-radius: 6px; margin-bottom: 1rem;">User unblocked and appeal resolved successfully.</div>';
            } elseif ($action === 'dismiss') {
                // Just delete the appeal
                $del = $conn->prepare("DELETE FROM user_appeals WHERE id = ?");
                $del->bind_param("i", $appeal_id);
                $del->execute();
                $del->close();

                $_SESSION['flash_msg'] = '<div style="padding: 10px; background: #fef08a; color: #854d0e; border-radius: 6px; margin-bottom: 1rem;">Appeal dismissed.</div>';
            }
        }
    }
    header("Location: appeals.php");
    exit();
}

$flash = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_msg']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Appeals - Laptop Admin</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main-content">
    <header class="header">
      <h1>Account Appeals</h1>
      <span class="badge badge-success">Review Requests</span>
    </header>

    <?php echo $flash; ?>

    <section style="background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); overflow: hidden;">
      <div style="padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0;">
        <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;">Pending Appeals</h3>
      </div>

      <div style="display: grid; grid-template-columns: 80px 2fr 3fr 1.2fr 160px; background: #f4f6f9; color: #64748b; font-weight: 600; font-size: 0.85rem; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; align-items: center;">
        <div>ID</div>
        <div>EMAIL</div>
        <div>REASON</div>
        <div>DATE</div>
        <div style="text-align: right;">ACTIONS</div>
      </div>

      <div>
        <?php
        $query = $conn->query("SELECT * FROM user_appeals ORDER BY id DESC");
        if ($query && $query->num_rows > 0):
          while ($row = $query->fetch_assoc()):
        ?>
          <div style="display: grid; grid-template-columns: 80px 2fr 3fr 1.2fr 160px; font-size: 0.85rem; padding: 0.75rem 1rem; border-bottom: 1px solid #e2e8f0; align-items: center;">
            <div style="color: #64748b;">#APL-<?php echo $row['id']; ?></div>
            <div style="font-weight: 600; color: #1e293b; word-break: break-all;"><?php echo htmlspecialchars($row['email']); ?></div>
            <div style="color: #475569; word-break: break-word;"><?php echo htmlspecialchars($row['reason']); ?></div>
            <div style="color: #64748b;"><?php echo date("M d, Y", strtotime($row['created_at'])); ?></div>
            <div style="text-align: right; white-space: nowrap;">
              <a href="appeals.php?action=unblock&id=<?php echo $row['id']; ?>&csrf_token=<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" style="padding: 0.3rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-decoration: none; background: #dcfce7; color: #15803d; margin-right: 2px; display: inline-block;">Unblock</a>
              <a href="appeals.php?action=dismiss&id=<?php echo $row['id']; ?>&csrf_token=<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>" style="padding: 0.3rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; text-decoration: none; background: #fee2e2; color: #991b1b; display: inline-block;" onclick="return confirm('Dismiss this appeal?');">Dismiss</a>
            </div>
          </div>
        <?php 
          endwhile;
        else:
        ?>
          <div style="text-align: center; padding: 2rem; color: #64748b;">No pending appeals found.</div>
        <?php endif; ?>
      </div>
    </section>
  </main>

</body>
</html>