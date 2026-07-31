<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar" id="sidebar">
  <div class="sidebar-header">
    <div class="brand">💻 <span>Laptop Admin</span></div>
    <button class="toggle-btn" id="toggleSidebar" title="Toggle Sidebar">☰</button>
  </div>
  <nav>
    <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
      📊 <span>Dashboard</span>
    </a>
    <a href="inventory.php" class="<?php echo ($current_page == 'inventory.php') ? 'active' : ''; ?>">
      📦 <span>Inventory</span>
    </a>
    <a href="users.php" class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
      👥 <span>Users</span>
    </a>
   <a href="appeals.php" class="sidebar-link">
  <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align: middle; margin-right: 8px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  Appeals
</a>
    <a href="orders.php" class="sidebar-item">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
  Orders & Tracking
</a>
    <a href="logs.php" class="<?php echo ($current_page == 'logs.php') ? 'active' : ''; ?>">
      📋 <span>Activity Logs</span>
    </a>
  </nav>

  <!-- Logout Section -->
  <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-color);">
    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; overflow: hidden; text-overflow: ellipsis;">
      Logged in as: <strong><?php echo htmlspecialchars($_SESSION['admin_user'] ?? 'Admin'); ?></strong>
    </div>
    <a href="../login/logout.php" style="color: var(--accent-red); background: #fee2e2; padding: 0.5rem 0.75rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: block; text-align: center;">
      🔒 Logout
    </a>
  </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeMobileSidebar()"></div>

<script>
  function closeMobileSidebar() {
    document.body.classList.remove('sidebar-mobile-open');
  }

  document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('toggleSidebar');
    const mobileToggleBtns = document.querySelectorAll('.mobile-toggle-btn');
    const body = document.body;

    if (localStorage.getItem('sidebar-collapsed') === 'true' && window.innerWidth > 768) {
      body.classList.add('sidebar-collapsed');
    }

    if (toggleBtn) {
      toggleBtn.addEventListener('click', () => {
        if (window.innerWidth <= 768) {
          body.classList.toggle('sidebar-mobile-open');
        } else {
          body.classList.toggle('sidebar-collapsed');
          localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed'));
        }
      });
    }

    mobileToggleBtns.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        body.classList.toggle('sidebar-mobile-open');
      });
    });
  });
</script>