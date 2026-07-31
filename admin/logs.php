<?php
include '../login/auth.php';
include 'db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Activity Logs - Laptop Admin</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Keep table columns tight so the "View" button fits without horizontal scroll */
    .logs-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .logs-table th, .logs-table td { padding: 0.4rem 0.55rem; font-size: 0.75rem; text-align: left; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .logs-table th:nth-child(1), .logs-table td:nth-child(1) { width: 13%; }  /* Timestamp */
    .logs-table th:nth-child(2), .logs-table td:nth-child(2) { width: 47%; }  /* Action */
    .logs-table th:nth-child(3), .logs-table td:nth-child(3) { width: 14%; }  /* Performed By */
    .logs-table th:nth-child(4), .logs-table td:nth-child(4) { width: 13%; }  /* Status */
    .logs-table th:nth-child(5), .logs-table td:nth-child(5) { width: 13%; overflow: visible; }  /* Actions */
    .logs-table td strong { font-size: 0.75rem; }

    /* View button */
    .btn-view-log {
      display: inline-flex; align-items: center; gap: 3px;
      padding: 0.22rem 0.55rem; border-radius: 5px; font-size: 0.68rem; font-weight: 700;
      background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; cursor: pointer; white-space: nowrap;
    }
    .btn-view-log:hover { background: #1d4ed8; color: #fff; }

    /* Detail Modal */
    .log-modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(15,23,42,0.5); z-index: 999;
      backdrop-filter: blur(4px); align-items: center; justify-content: center;
    }
    .log-modal-overlay.open { display: flex; }
    .log-modal-box {
      background: #fff; border-radius: 16px; border: 1px solid #e2e8f0;
      width: 92%; max-width: 680px; max-height: 88vh; overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.18); animation: slideUp 0.22s ease;
    }
    @keyframes slideUp { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
    .log-modal-head {
      display: flex; justify-content: space-between; align-items: center;
      padding: 1rem 1.3rem; border-bottom: 1px solid #e2e8f0;
      background: #f8fafc; border-radius: 16px 16px 0 0;
    }
    .log-modal-head h3 { font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0; }
    .log-modal-close { background: none; border: none; font-size: 1.3rem; cursor: pointer; color: #64748b; padding: 0 0.3rem; line-height: 1; }
    .log-modal-close:hover { color: #b91c1c; }
    .log-modal-body { padding: 1.3rem; }

    /* Detail rows */
    .detail-row { display: flex; gap: 0; border-bottom: 1px solid #f1f5f9; }
    .detail-row:last-child { border-bottom: none; }
    .detail-label {
      flex: 0 0 160px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.05em; color: #64748b; padding: 0.6rem 0.8rem 0.6rem 0;
    }
    .detail-value {
      flex: 1; font-size: 0.82rem; color: #0f172a; padding: 0.6rem 0;
      word-break: break-all; white-space: pre-wrap;
    }
    .detail-value.mono { font-family: 'Courier New', monospace; font-size: 0.75rem; background: #f8fafc; padding: 0.4rem 0.6rem; border-radius: 6px; border: 1px solid #e2e8f0; }
    .detail-badge-success { display: inline-block; padding: 0.2rem 0.6rem; background: #dcfce7; color: #15803d; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }
    .detail-badge-fail    { display: inline-block; padding: 0.2rem 0.6rem; background: #fee2e2; color: #b91c1c; border-radius: 20px; font-size: 0.72rem; font-weight: 700; }

    .section-divider { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; margin: 1rem 0 0.4rem; padding-bottom: 0.3rem; border-bottom: 1px solid #f1f5f9; }
  </style>
</head>
<body>

  <!-- Smart Sidebar Nav -->
  <?php include 'sidebar.php'; ?>

  <!-- Main Content Area -->
  <main class="main-content">
    <header class="header">
      <h1>System Activity Logs</h1>
      <span class="badge badge-success">Live Auditing</span>
    </header>

    <!-- Activity Log Table -->
    <section class="table-container">
      <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color);">
        <h3>Audit Trail</h3>
      </div>

      <table class="logs-table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Action Details</th>
            <th>Performed By</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $logs_query = $conn->query("SELECT * FROM activity_logs ORDER BY id DESC");
          if ($logs_query && $logs_query->num_rows > 0):
            while ($log = $logs_query->fetch_assoc()):
              $is_payment = stripos($log['action'], 'stripe') !== false || stripos($log['action'], 'payment') !== false || stripos($log['action'], 'checkout') !== false;
              // Try to parse JSON metadata if stored in action or a details column
              $meta = [];
              if (!empty($log['details'])) {
                  $parsed = json_decode($log['details'], true);
                  if (is_array($parsed)) $meta = $parsed;
              }
          ?>
            <tr>
              <td><?php echo date("Y-m-d H:i", strtotime($log['created_at'])); ?></td>
              <td><strong title="<?php echo htmlspecialchars($log['action']); ?>"><?php echo htmlspecialchars($log['action']); ?></strong></td>
              <td><?php echo htmlspecialchars($log['performed_by']); ?></td>
              <td>
                <span class="badge <?php echo ($log['status'] === 'Completed') ? 'badge-success' : 'badge-danger'; ?>">
                  <?php echo $log['status']; ?>
                </span>
              </td>
              <td>
                <button class="btn-view-log" onclick='openLogDetail(<?php echo htmlspecialchars(json_encode($log), ENT_QUOTES); ?>)'>
                  🔍 View
                </button>
              </td>
            </tr>
          <?php
            endwhile;
          else:
          ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No logs recorded yet.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>
  </main>

  <!-- Transaction Detail Modal -->
  <div class="log-modal-overlay" id="logDetailModal">
    <div class="log-modal-box">
      <div class="log-modal-head">
        <h3>📋 Transaction / Activity Detail</h3>
        <button class="log-modal-close" onclick="closeLogDetail()">×</button>
      </div>
      <div class="log-modal-body" id="logDetailBody"></div>
    </div>
  </div>

  <script>
    function openLogDetail(log) {
      const body = document.getElementById('logDetailBody');
      const isPayment = /stripe|payment|checkout/i.test(log.action);

      // Parse any JSON details if available
      let meta = {};
      if (log.details) {
        try { meta = JSON.parse(log.details); } catch(e) {}
      }

      // Try to extract known keys from action text
      const action = log.action || '';
      const sessionMatch = action.match(/cs_[a-zA-Z0-9_]+/);
      const orderMatch   = action.match(/Order\s*#?(\d+)/i) || action.match(/order_id[=:\s]+(\d+)/i);
      const amountMatch  = action.match(/\$?([\d,.]+)\s*USD?/i) || action.match(/amount[=:\s]+\$?([\d,.]+)/i);
      const emailMatch   = action.match(/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/);

      let html = '';

      // ── Core Info ──
      html += '<div class="section-divider">Core Information</div>';
      html += row('Log ID', '#' + (log.id || '—'));
      html += row('Timestamp', formatDate(log.created_at));
      html += row('Performed By', log.performed_by || '—');
      html += row('Status', log.status === 'Completed'
        ? '<span class="detail-badge-success">✓ Completed</span>'
        : '<span class="detail-badge-fail">✗ ' + (log.status || 'Unknown') + '</span>');

      // ── Full Action Text ──
      html += '<div class="section-divider">Full Action Description</div>';
      html += rowMono('Action', action);

      // ── Payment Details (if applicable) ──
      if (isPayment) {
        html += '<div class="section-divider">💳 Payment / Stripe Details</div>';
        if (sessionMatch) html += rowMono('Session ID', sessionMatch[0]);
        if (orderMatch)   html += row('Order Reference', '#' + orderMatch[1]);
        if (amountMatch)  html += row('Amount', '$' + amountMatch[1] + ' USD');
        if (emailMatch)   html += row('Customer Email', emailMatch[0]);
        html += row('Payment Gateway', 'Stripe Checkout');
        html += row('Method', 'Stripe Hosted Page');
      }

      // ── Extra metadata from details column ──
      if (Object.keys(meta).length > 0) {
        html += '<div class="section-divider">Extra Metadata</div>';
        for (const [k, v] of Object.entries(meta)) {
          html += rowMono(k, typeof v === 'object' ? JSON.stringify(v, null, 2) : String(v));
        }
      }

      // ── Raw Log Data ──
      html += '<div class="section-divider">Raw Record</div>';
      const raw = Object.entries(log).map(([k,v]) => k.padEnd(16) + ': ' + (v||'null')).join('\n');
      html += rowMono('All Fields', raw);

      body.innerHTML = html;
      document.getElementById('logDetailModal').classList.add('open');
    }

    function closeLogDetail() {
      document.getElementById('logDetailModal').classList.remove('open');
    }

    document.getElementById('logDetailModal').addEventListener('click', function(e) {
      if (e.target === this) closeLogDetail();
    });

    function row(label, value) {
      return `<div class="detail-row"><div class="detail-label">${label}</div><div class="detail-value">${value}</div></div>`;
    }
    function rowMono(label, value) {
      return `<div class="detail-row"><div class="detail-label">${label}</div><div class="detail-value mono">${escHtml(value)}</div></div>`;
    }
    function escHtml(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
    function formatDate(dt) {
      if (!dt) return '—';
      const d = new Date(dt.replace(' ', 'T'));
      return d.toLocaleString('en-US', { dateStyle:'medium', timeStyle:'medium' });
    }
  </script>

</body>
</html>