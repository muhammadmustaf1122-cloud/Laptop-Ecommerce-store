<?php
include '../login/auth.php';
include 'db.php';

$message = "";
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle User Actions (Delete or Block/Unblock)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $target_id = intval($_GET['id']);
    $action = $_GET['action'];

    $current_admin_user  = $_SESSION['admin_user']  ?? $_SESSION['username'] ?? $_SESSION['user'] ?? '';
    $current_admin_email = $_SESSION['admin_email'] ?? $_SESSION['email'] ?? '';

    $check_target = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $check_target->bind_param("i", $target_id);
    $check_target->execute();
    $target_data = $check_target->get_result()->fetch_assoc();
    $check_target->close();

    if ($target_data) {
        $is_current_user = (
            (!empty($current_admin_user)  && $target_data['username'] === $current_admin_user) ||
            (!empty($current_admin_email) && $target_data['email']    === $current_admin_email) ||
            ($target_data['username'] === 'admin') ||
            ($target_data['email']    === 'admin@laptop.com')
        );

        if ($is_current_user) {
            $_SESSION['flash_msg'] = '<div id="flash-alert" class="flash-error">Action denied: Cannot modify your own active admin account.</div>';
        } else {
            if ($action === 'delete') {
                $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del_stmt->bind_param("i", $target_id);
                if ($del_stmt->execute()) {
                    $_SESSION['flash_msg'] = '<div id="flash-alert" class="flash-success">User account deleted.</div>';
                }
                $del_stmt->close();
            } elseif ($action === 'block') {
                $block_stmt = $conn->prepare("UPDATE users SET is_verified = 0 WHERE id = ?");
                $block_stmt->bind_param("i", $target_id);
                if ($block_stmt->execute()) {
                    $_SESSION['flash_msg'] = '<div id="flash-alert" class="flash-warn">User account blocked.</div>';
                }
                $block_stmt->close();
            } elseif ($action === 'unblock') {
                $unblock_stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                $unblock_stmt->bind_param("i", $target_id);
                if ($unblock_stmt->execute()) {
                    $_SESSION['flash_msg'] = '<div id="flash-alert" class="flash-success">User account unblocked.</div>';
                }
                $unblock_stmt->close();
            }
        }
    }
    header("Location: users.php");
    exit();
}

// Pull flash messages from session
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}

// Handle Adding New User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $message = '<div id="flash-alert" class="flash-error">Invalid form submission.</div>';
    } else {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role'] ?? 'customer';

        if ($username === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $message = '<div id="flash-alert" class="flash-error">Please provide a valid username, email, and password (min 8 characters).</div>';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $is_verified = 1;
            $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, is_verified) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssi', $username, $email, $hashed_password, $role, $is_verified);

            if ($stmt->execute()) {
                $log_action   = 'Added user profile "' . $username . '"';
                $performed_by = $_SESSION['admin_user'] ?? 'Admin';
                $log_stmt = $conn->prepare("INSERT INTO activity_logs (action, performed_by, status) VALUES (?, ?, 'Completed')");
                if ($log_stmt) {
                    $log_stmt->bind_param('ss', $log_action, $performed_by);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
                $message = '<div id="flash-alert" class="flash-success">User created successfully!</div>';
            } else {
                $message = '<div id="flash-alert" class="flash-error">Error: ' . htmlspecialchars($conn->error) . '</div>';
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
  <title>User Management - Laptop Admin</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .flash-success { padding:10px 14px; background:#dcfce7; color:#15803d; border-radius:8px; margin-bottom:1rem; font-weight:600; font-size:0.88rem; }
    .flash-error   { padding:10px 14px; background:#fee2e2; color:#b91c1c; border-radius:8px; margin-bottom:1rem; font-weight:600; font-size:0.88rem; }
    .flash-warn    { padding:10px 14px; background:#fef08a; color:#854d0e; border-radius:8px; margin-bottom:1rem; font-weight:600; font-size:0.88rem; }

    /* Modal */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.45);
      z-index: 900;
      backdrop-filter: blur(4px);
      align-items: center;
      justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: #f8fafc;
      border-radius: 16px;
      border: 1px solid #e2e8f0;
      width: 92%;
      max-width: 560px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.15);
      animation: slideUp 0.25s ease;
    }
    @keyframes slideUp {
      from { opacity:0; transform:translateY(20px); }
      to   { opacity:1; transform:translateY(0); }
    }
    .modal-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.1rem 1.4rem;
      border-bottom: 1px solid #e2e8f0;
      background: #fff;
      border-radius: 16px 16px 0 0;
    }
    .modal-head h3 { font-size: 1rem; font-weight: 700; color: #0f172a; }
    .modal-close {
      background: none; border: none; font-size: 1.4rem;
      cursor: pointer; color: #64748b; line-height:1; padding:0 0.3rem;
    }
    .modal-close:hover { color: #b91c1c; }
    .modal-body { padding: 1.4rem; }

    .btn-open-modal {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: var(--accent-blue); color: #fff; border: none;
      padding: 0.55rem 1.1rem; border-radius: 8px;
      font-size: 0.88rem; font-weight: 700; cursor: pointer; transition: background 0.2s;
    }
    .btn-open-modal:hover { background: var(--accent-blue-hover); }

    /* Users table — strict column widths, no overflow */
    .users-table-wrap {
      width: 100%;
      overflow-x: hidden;
    }
    #usersTable {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
    }
    #usersTable th, #usersTable td {
      padding: 0.5rem 0.6rem;
      border-bottom: 1px solid var(--border-color);
      font-size: 0.75rem;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    #usersTable th {
      background: #cbd5e1;
      color: var(--text-main);
      font-weight: 700;
      font-size: 0.65rem;
      text-transform: uppercase;
      letter-spacing: 0.2px;
    }
    #usersTable tr:hover { background: var(--bg-hover); }
    #usersTable tr:last-child td { border-bottom: none; }

    #usersTable th:nth-child(1), #usersTable td:nth-child(1) { width: 8%;  }
    #usersTable th:nth-child(2), #usersTable td:nth-child(2) { width: 16%; }
    #usersTable th:nth-child(3), #usersTable td:nth-child(3) { width: 24%; }
    #usersTable th:nth-child(4), #usersTable td:nth-child(4) { width: 10%; }
    #usersTable th:nth-child(5), #usersTable td:nth-child(5) { width: 10%; }
    #usersTable th:nth-child(6), #usersTable td:nth-child(6) { width: 14%; }
    #usersTable th:nth-child(7), #usersTable td:nth-child(7) { width: 18%; }

    .badge-role-admin    { background:#dcfce7; color:#15803d; padding:0.15rem 0.45rem; border-radius:4px; font-size:0.72rem; font-weight:700; }
    .badge-role-customer { background:#f1f5f9; color:#475569; padding:0.15rem 0.45rem; border-radius:4px; font-size:0.72rem; font-weight:700; }
    .badge-active  { background:#dcfce7; color:#15803d; padding:0.15rem 0.45rem; border-radius:4px; font-size:0.72rem; font-weight:700; }
    .badge-blocked { background:#fee2e2; color:#991b1b; padding:0.15rem 0.45rem; border-radius:4px; font-size:0.72rem; font-weight:700; }

    .act-btn {
      padding:0.25rem 0.48rem; border-radius:4px; font-size:0.7rem;
      font-weight:600; text-decoration:none; display:inline-block; margin-right:2px;
    }
    .act-unblock { background:#dcfce7; color:#15803d; }
    .act-block   { background:#fef08a; color:#854d0e; }
    .act-del     { background:#fee2e2; color:#991b1b; }

    /* modal input styling */
    .modal-body input[type="text"],
    .modal-body input[type="email"],
    .modal-body input[type="password"],
    .modal-body select {
      width: 100%; padding: 0.6rem 0.8rem;
      background: #fff; color: #0f172a;
      border: 1px solid #cbd5e1; border-radius: 6px;
      font-size: 0.9rem; outline: none; transition: border-color 0.2s;
    }
    .modal-body input:focus, .modal-body select:focus {
      border-color: var(--accent-blue);
      box-shadow: 0 0 0 3px rgba(2,132,199,0.12);
    }
    .modal-body label {
      display: block; font-size: 0.82rem; font-weight: 600;
      color: var(--text-muted); margin-bottom: 0.35rem;
    }
    .modal-body .form-group { margin-bottom: 1rem; }
  </style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main-content">
    <header class="header">
      <h1>Registered Users</h1>
      <div style="display:flex;align-items:center;gap:0.6rem;">
        <span class="badge badge-success">User Management</span>
        <button class="btn-open-modal" onclick="openModal('addUserModal')">
          + Add User
        </button>
      </div>
    </header>

    <?php echo $message; ?>

    <!-- Users Table -->
    <section class="table-container">
      <div style="padding:1rem 1.5rem;border-bottom:1px solid var(--border-color);display:flex;justify-content:space-between;align-items:center;">
        <h3>System Accounts</h3>
        <span style="font-size:0.8rem;color:var(--text-muted);">Total: <?php
          $cnt = $conn->query("SELECT COUNT(*) AS c FROM users");
          echo $cnt ? $cnt->fetch_assoc()['c'] : 0;
        ?> users</span>
      </div>

      <div class="users-table-wrap">
        <table id="usersTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Status</th>
              <th>Registered</th>
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $users_query = $conn->query("SELECT * FROM users ORDER BY id DESC");
            if ($users_query && $users_query->num_rows > 0):
              while ($user = $users_query->fetch_assoc()):
                $is_blocked = (isset($user['is_verified']) && $user['is_verified'] == 0);
                $is_protected = ($user['username'] === 'admin' || $user['email'] === 'admin@laptop.com');
            ?>
              <tr>
                <td style="color:var(--text-muted);">#<?php echo $user['id']; ?></td>
                <td style="font-weight:600;"><?php echo htmlspecialchars($user['username']); ?></td>
                <td style="color:var(--text-muted);"><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                  <?php if ($user['role'] === 'admin'): ?>
                    <span class="badge-role-admin">Admin</span>
                  <?php else: ?>
                    <span class="badge-role-customer">Customer</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($is_blocked): ?>
                    <span class="badge-blocked">Blocked</span>
                  <?php else: ?>
                    <span class="badge-active">Active</span>
                  <?php endif; ?>
                </td>
                <td style="color:var(--text-muted);"><?php echo isset($user['created_at']) ? date("M d, Y", strtotime($user['created_at'])) : 'N/A'; ?></td>
                <td style="text-align:right;white-space:nowrap;">
                  <?php if (!$is_protected): ?>
                    <?php if ($is_blocked): ?>
                      <a href="users.php?action=unblock&id=<?php echo $user['id']; ?>" class="act-btn act-unblock">Unblock</a>
                    <?php else: ?>
                      <a href="users.php?action=block&id=<?php echo $user['id']; ?>" class="act-btn act-block">Block</a>
                    <?php endif; ?>
                    <a href="users.php?action=delete&id=<?php echo $user['id']; ?>" class="act-btn act-del" onclick="return confirm('Delete this user account permanently?')">Delete</a>
                  <?php else: ?>
                    <span style="font-size:0.72rem;color:#94a3b8;font-style:italic;">Protected</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php 
              endwhile;
            else:
            ?>
              <tr>
                <td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">No users found. Click <strong>+ Add User</strong> to create one.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <!-- ADD USER MODAL -->
  <div class="modal-overlay" id="addUserModal">
    <div class="modal-box">
      <div class="modal-head">
        <h3>👤 Add New User Account</h3>
        <button class="modal-close" onclick="closeModal('addUserModal')">×</button>
      </div>
      <div class="modal-body">
        <form action="users.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label>Username</label>
              <input type="text" name="username" placeholder="e.g. johndoe" required>
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" name="email" placeholder="john@example.com" required>
            </div>
            <div class="form-group">
              <label>Password</label>
              <input type="password" name="password" placeholder="Minimum 8 characters" required>
            </div>
            <div class="form-group">
              <label>Role</label>
              <select name="role" required>
                <option value="customer">Customer</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>

          <div style="display:flex;gap:0.7rem;margin-top:0.5rem;">
            <button type="submit" name="add_user" class="btn btn-primary" style="flex:1;padding:0.65rem;">Create Account</button>
            <button type="button" class="btn" style="flex:0 0 auto;padding:0.65rem 1rem;background:var(--bg-hover);" onclick="closeModal('addUserModal')">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function openModal(id)  { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
      });
    });

    // Auto-dismiss flash
    setTimeout(function() {
      const el = document.getElementById('flash-alert');
      if (el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }
    }, 3000);

    // If form was submitted with errors, reopen modal
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user']) && !empty($message) && strpos($message,'flash-error') !== false): ?>
    openModal('addUserModal');
    <?php endif; ?>
  </script>

</body>
</html>