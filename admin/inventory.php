<?php
include '../login/auth.php';
include 'db.php';

$message = "";
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    $conn->query("DELETE FROM laptop_images WHERE laptop_id = $del_id");
    $conn->query("DELETE FROM laptop WHERE id = $del_id");
    $_SESSION['flash_msg'] = '<div id="flash-alert" class="flash-success">Laptop removed from catalog.</div>';
    header("Location: inventory.php");
    exit();
}

// Handle Edit (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_laptop'])) {
    $eid       = intval($_POST['edit_id']);
    $title     = trim($_POST['title'] ?? '');
    $model     = trim($_POST['model'] ?? '');
    $brand_id  = intval($_POST['brand_id']);
    $cat_id    = intval($_POST['category_id']);
    $price     = floatval($_POST['price']);
    $stock     = intval($_POST['stock']);
    $processor = trim($_POST['processor'] ?? '');
    $ram       = trim($_POST['ram'] ?? '');
    $storage   = trim($_POST['storage'] ?? '');
    $gpu       = trim($_POST['gpu'] ?? '');
    $display   = trim($_POST['display'] ?? '');

    $conn->query("UPDATE laptop SET 
        title='".$conn->real_escape_string($title)."',
        model='".$conn->real_escape_string($model)."',
        brand_id=$brand_id,
        category_id=$cat_id,
        price=$price,
        stock=$stock,
        processor='".$conn->real_escape_string($processor)."',
        ram='".$conn->real_escape_string($ram)."',
        storage='".$conn->real_escape_string($storage)."',
        gpu='".$conn->real_escape_string($gpu)."',
        display='".$conn->real_escape_string($display)."'
        WHERE id=$eid");

    // Handle image deletions
    if (!empty($_POST['delete_image_ids']) && is_array($_POST['delete_image_ids'])) {
        foreach ($_POST['delete_image_ids'] as $del_img_id) {
            $del_img_id = intval($del_img_id);
            $img_row = $conn->query("SELECT image_url FROM laptop_images WHERE id=$del_img_id AND laptop_id=$eid");
            if ($img_row && $img_row->num_rows > 0) {
                $img_data = $img_row->fetch_assoc();
                $img_file = __DIR__ . '/../uploads/' . $img_data['image_url'];
                if (file_exists($img_file) && !filter_var($img_data['image_url'], FILTER_VALIDATE_URL)) {
                    @unlink($img_file);
                }
                $conn->query("DELETE FROM laptop_images WHERE id=$del_img_id AND laptop_id=$eid");
            }
        }
    }

    // Handle new image uploads
    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
    // Enforce max 4 images per laptop
    $count_res = $conn->query("SELECT COUNT(*) as cnt FROM laptop_images WHERE laptop_id = $eid");
    $current_img_count = (int)($count_res->fetch_assoc()['cnt'] ?? 0);
    $slots_available = max(0, 4 - $current_img_count);

    if ($slots_available > 0 && !empty($_FILES['new_gallery_images']['name']) && is_array($_FILES['new_gallery_images']['name'])) {
        $uploaded = 0;
        foreach ($_FILES['new_gallery_images']['name'] as $key => $filename) {
            if ($uploaded >= $slots_available) break;
            if (!empty($filename) && ($_FILES['new_gallery_images']['error'][$key] ?? 1) === 0) {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $new_name = uniqid('laptop_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['new_gallery_images']['tmp_name'][$key], $upload_dir . $new_name)) {
                    $img_stmt = $conn->prepare('INSERT INTO laptop_images (laptop_id, image_url) VALUES (?, ?)');
                    $img_stmt->bind_param('is', $eid, $new_name);
                    $img_stmt->execute();
                    $img_stmt->close();
                    $uploaded++;
                    $slots_available--;
                }
            }
        }
    }
    // Handle new image URLs (respecting remaining slots)
    if ($slots_available > 0 && !empty($_POST['new_gallery_urls'])) {
        $urls = preg_split('/\r\n|\r|\n/', trim($_POST['new_gallery_urls']));
        foreach ($urls as $url) {
            if ($slots_available <= 0) break;
            $clean_url = trim($url);
            if ($clean_url !== '') {
                $img_stmt = $conn->prepare('INSERT INTO laptop_images (laptop_id, image_url) VALUES (?, ?)');
                $img_stmt->bind_param('is', $eid, $clean_url);
                $img_stmt->execute();
                $img_stmt->close();
                $slots_available--;
            }
        }
    }

    $_SESSION['flash_msg'] = '<div id="flash-alert" class="flash-success">Catalog entry updated successfully.</div>';
    header("Location: inventory.php");
    exit();
}

// Check URL messages
if (isset($_SESSION['flash_msg'])) {
    $message = $_SESSION['flash_msg'];
    unset($_SESSION['flash_msg']);
}
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = '<div id="flash-alert" class="flash-success">Laptop removed successfully!</div>';
} elseif (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = '<div id="flash-alert" class="flash-success">Laptop added successfully!</div>';
}

// Fetch Brands and Categories for Dropdowns
$brands_result     = $conn->query("SELECT * FROM brand ORDER BY name ASC");
$categories_result = $conn->query("SELECT * FROM categories ORDER BY name ASC");

// Pre-fetch for edit modal reuse
$brands_all = [];
$tmp = $conn->query("SELECT * FROM brand ORDER BY name ASC");
while ($b = $tmp->fetch_assoc()) $brands_all[] = $b;

$cats_all = [];
$tmp2 = $conn->query("SELECT * FROM categories ORDER BY name ASC");
while ($c = $tmp2->fetch_assoc()) $cats_all[] = $c;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory Management - Laptop Admin</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Flash alerts */
    .flash-success { padding: 10px 14px; background: #dcfce7; color: #15803d; border-radius: 8px; margin-bottom: 1rem; font-weight: 600; font-size: 0.88rem; }
    .flash-error   { padding: 10px 14px; background: #fee2e2; color: #b91c1c; border-radius: 8px; margin-bottom: 1rem; font-weight: 600; font-size: 0.88rem; }

    /* Header row with Add button */
    .page-header-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }

    /* Modal overlay */
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
      max-width: 780px;
      max-height: 90vh;
      overflow-y: auto;
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
      background: none;
      border: none;
      font-size: 1.4rem;
      cursor: pointer;
      color: #64748b;
      line-height: 1;
      padding: 0 0.3rem;
    }
    .modal-close:hover { color: #b91c1c; }
    .modal-body { padding: 1.4rem; }

    /* Open button */
    .btn-open-modal {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      background: var(--accent-blue);
      color: #fff;
      border: none;
      padding: 0.55rem 1.1rem;
      border-radius: 8px;
      font-size: 0.88rem;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-open-modal:hover { background: var(--accent-blue-hover); }

    /* Action buttons in table */
    .btn-edit {
      padding: 0.28rem 0.55rem;
      border-radius: 5px;
      font-size: 0.7rem;
      font-weight: 600;
      text-decoration: none;
      background: #dbeafe;
      color: #1d4ed8;
      border: none;
      cursor: pointer;
      display: inline-block;
      margin-right: 2px;
      white-space: nowrap;
    }
    .btn-edit:hover { background: #1d4ed8; color: #fff; }
    .btn-del {
      padding: 0.28rem 0.55rem;
      border-radius: 5px;
      font-size: 0.7rem;
      font-weight: 600;
      text-decoration: none;
      background: #fee2e2;
      color: #b91c1c;
      border: none;
      cursor: pointer;
      display: inline-block;
      white-space: nowrap;
    }
    .btn-del:hover { background: #b91c1c; color: #fff; }

    /* Inventory table: fixed 100% width with reduced font/padding to fit content */
    .table-container { overflow-x: hidden; }
    /* force this table to use a fixed layout so it stays within container width */
    #inventoryTable { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: unset; }
    /* make table text smaller and tighter so long values fit at 100% width */
    #inventoryTable th, #inventoryTable td { padding: 0.35rem 0.45rem; text-align: left; vertical-align: middle; font-size: 0.68rem; }
    /* redistributed column widths that sum to 100% */
    #inventoryTable th:nth-child(1), #inventoryTable td:nth-child(1) { width: 5%; }
    #inventoryTable th:nth-child(2), #inventoryTable td:nth-child(2) { width: 5%; }
    #inventoryTable th:nth-child(3), #inventoryTable td:nth-child(3) { width: 38%; }
    #inventoryTable th:nth-child(4), #inventoryTable td:nth-child(4) { width: 8%; }
    #inventoryTable th:nth-child(5), #inventoryTable td:nth-child(5) { width: 10%; }
    /* Price must remain on one line and be slightly larger */
    #inventoryTable th:nth-child(6), #inventoryTable td:nth-child(6) { width: 12%; white-space: nowrap; }
    #inventoryTable th:nth-child(7), #inventoryTable td:nth-child(7) { width: 6%; }
    #inventoryTable th:nth-child(8), #inventoryTable td:nth-child(8) { width: 8%; }
    #inventoryTable th:nth-child(9), #inventoryTable td:nth-child(9) { width: 11%; }

    /* make title/model slightly smaller to help fit */
    #inventoryTable td strong { display:block; font-size:0.85rem; }
    #inventoryTable td small { display:block; font-size:0.65rem; color:var(--text-muted); }

    /* shrink action buttons so they fit in Actions column */
    .btn-edit, .btn-del { padding: 0.18rem 0.36rem; font-size:0.62rem; border-radius:4px; }

    /* ensure action cell shows buttons fully (don't rely on global ellipsis) */
    #inventoryTable td:last-child { overflow: visible; }

    /* keep price bold and clear */
    #inventoryTable td:nth-child(6) { font-weight:700; }
  </style>
</head>
<body>

  <?php include 'sidebar.php'; ?>

  <main class="main-content">
    <header class="header">
      <h1>Inventory Management</h1>
      <div style="display:flex;align-items:center;gap:0.6rem;">
        <span class="badge badge-success">Live Catalog</span>
        <button class="btn-open-modal" onclick="openModal('addModal')">
          + Add Catalog
        </button>
      </div>
    </header>

    <?php echo $message; ?>

    <!-- Laptop Catalog Table -->
    <section class="table-container">
      <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3>Laptop Catalog</h3>
        <span style="font-size:0.8rem;color:var(--text-muted);">Click <strong>Edit</strong> to update any entry</span>
      </div>

      <table id="inventoryTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Image</th>
            <th>Title / Model</th>
            <th>Brand</th>
            <th>Category</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Specs</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          // Handle Pagination
          $limit = 10;
          $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
          $offset = ($page - 1) * $limit;

          // Get total rows
          $count_res = $conn->query("SELECT COUNT(*) as total FROM laptop");
          $total_rows = $count_res->fetch_assoc()['total'];
          $total_pages = ceil($total_rows / $limit);

          $sql = "SELECT l.*, b.name as brand_name, c.name as category_name,
                         (SELECT image_url FROM laptop_images WHERE laptop_id = l.id LIMIT 1) as first_img
                  FROM laptop l 
                  LEFT JOIN brand b ON l.brand_id = b.id 
                  LEFT JOIN categories c ON l.category_id = c.id 
                  ORDER BY l.id DESC
                  LIMIT $limit OFFSET $offset";
          
          $laptops = $conn->query($sql);

          if ($laptops && $laptops->num_rows > 0):
            while ($row = $laptops->fetch_assoc()):
              $img_val = $row['first_img'] ?? '';
              if (filter_var($img_val, FILTER_VALIDATE_URL)) {
                  $display_img = $img_val;
              } else {
                  $display_img = !empty($img_val) ? '../uploads/' . $img_val : '';
              }
              $proc = $row['processor'] ?? '';
              $ram  = $row['ram']  ?? '';
              $stor = $row['storage'] ?? '';
              $specs_short = trim($proc . ($ram  ? ' · ' . $ram  : '') . ($stor ? ' · ' . $stor : ''), ' · ');
          ?>
            <tr>
              <td>#<?php echo $row['id']; ?></td>
              <td>
                <?php if (!empty($display_img)): ?>
                  <img src="<?php echo htmlspecialchars($display_img); ?>" alt="Laptop" style="width:42px;height:30px;object-fit:cover;border-radius:4px;">
                <?php else: ?>
                  <span style="font-size:0.7rem;color:var(--text-muted);">No img</span>
                <?php endif; ?>
              </td>
              <td>
                <strong style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($row['title']); ?></strong>
                <small style="color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;"><?php echo htmlspecialchars($row['model']); ?></small>
              </td>
              <td><span class="badge badge-success"><?php echo htmlspecialchars($row['brand_name'] ?? 'N/A'); ?></span></td>
              <td><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></td>
              <td><strong>$<?php echo number_format($row['price'], 2); ?></strong></td>
              <td><?php echo intval($row['stock']); ?></td>
              <td style="font-size:0.7rem;color:var(--text-muted);"><?php echo htmlspecialchars($specs_short ?: 'N/A'); ?></td>
              <td style="white-space:nowrap;">
                <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                <a href="inventory.php?action=delete&id=<?php echo $row['id']; ?>" 
                   class="btn-del"
                   onclick="return confirm('Delete this laptop from catalog?')">Delete</a>
              </td>
            </tr>
          <?php 
            endwhile;
          else: 
          ?>
            <tr>
              <td colspan="9" style="text-align:center;color:var(--text-muted);padding:2rem;">No laptops in catalog yet. Click <strong>+ Add Catalog</strong> to add one.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <!-- Pagination Links -->
      <?php if (isset($total_pages) && $total_pages > 1): ?>
      <div style="padding: 1.5rem; display: flex; justify-content: center; gap: 0.5rem; align-items: center;">
        <?php if ($page > 1): ?>
          <a href="inventory.php?page=<?php echo $page - 1; ?>" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600;">&laquo; Prev</a>
        <?php endif; ?>
        
        <span style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted);">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>

        <?php if ($page < $total_pages): ?>
          <a href="inventory.php?page=<?php echo $page + 1; ?>" style="padding: 0.5rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; text-decoration: none; color: var(--text-dark); font-weight: 600;">Next &raquo;</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </section>
  </main>

  <!-- ADD CATALOG MODAL -->
  <div class="modal-overlay" id="addModal">
    <div class="modal-box">
      <div class="modal-head">
        <h3>➕ Add New Laptop to Catalog</h3>
        <button class="modal-close" onclick="closeModal('addModal')">×</button>
      </div>
      <div class="modal-body">
        <form action="add_laptop_process.php" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label>Laptop Title</label>
              <input type="text" name="title" placeholder="e.g. Legion Pro 5" required>
            </div>
            <div class="form-group">
              <label>Model</label>
              <input type="text" name="model" placeholder="e.g. 16IRX8" required>
            </div>
            <div class="form-group">
              <label>Brand</label>
              <select name="brand_id" required>
                <option value="">Select Brand</option>
                <?php foreach ($brands_all as $b): ?>
                  <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Category</label>
              <select name="category_id" required>
                <option value="">Select Category</option>
                <?php foreach ($cats_all as $c): ?>
                  <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Price ($)</label>
              <input type="number" step="0.01" name="price" placeholder="1299.99" required>
            </div>
            <div class="form-group">
              <label>Stock Quantity</label>
              <input type="number" name="stock" placeholder="10" required>
            </div>
            <div class="form-group">
              <label>Gallery Images (Multiple)</label>
              <input type="file" name="gallery_images[]" accept="image/*" multiple>
            </div>
            <div class="form-group">
              <label>Or Image URLs (one per line)</label>
              <textarea name="gallery_urls" rows="2" placeholder="https://example.com/img.jpg"></textarea>
            </div>
          </div>

          <div style="margin:0.5rem 0 0.8rem;font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;">Technical Specs</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label>Processor (CPU)</label>
              <input type="text" name="processor" placeholder="i7-13700HX">
            </div>
            <div class="form-group">
              <label>RAM</label>
              <input type="text" name="ram" placeholder="16GB DDR5">
            </div>
            <div class="form-group">
              <label>Storage</label>
              <input type="text" name="storage" placeholder="1TB NVMe SSD">
            </div>
            <div class="form-group">
              <label>Graphics (GPU)</label>
              <input type="text" name="gpu" placeholder="RTX 4060 8GB">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label>Display</label>
              <input type="text" name="display" placeholder="15.6-inch FHD 144Hz IPS">
            </div>
          </div>

          <div style="display:flex;gap:0.7rem;margin-top:0.5rem;">
            <button type="submit" name="add_laptop" class="btn btn-primary" style="flex:1;padding:0.65rem;">Save to Catalog</button>
            <button type="button" class="btn" style="flex:0 0 auto;padding:0.65rem 1rem;background:var(--bg-hover);" onclick="closeModal('addModal')">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- EDIT CATALOG MODAL -->
  <div class="modal-overlay" id="editModal">
    <div class="modal-box" style="max-width:860px;">
      <div class="modal-head">
        <h3>✏️ Edit Catalog Entry</h3>
        <button class="modal-close" onclick="closeModal('editModal')">×</button>
      </div>
      <div class="modal-body">
        <form action="inventory.php" method="POST" enctype="multipart/form-data" id="editForm">
          <input type="hidden" name="edit_laptop" value="1">
          <input type="hidden" name="edit_id" id="editId">

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label>Laptop Title</label>
              <input type="text" name="title" id="editTitle" required>
            </div>
            <div class="form-group">
              <label>Model</label>
              <input type="text" name="model" id="editModel" required>
            </div>
            <div class="form-group">
              <label>Brand</label>
              <select name="brand_id" id="editBrand" required>
                <?php foreach ($brands_all as $b): ?>
                  <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Category</label>
              <select name="category_id" id="editCat" required>
                <?php foreach ($cats_all as $c): ?>
                  <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Price ($)</label>
              <input type="number" step="0.01" name="price" id="editPrice" required>
            </div>
            <div class="form-group">
              <label>Stock Quantity</label>
              <input type="number" name="stock" id="editStock" required>
            </div>
          </div>

          <div style="margin:0.5rem 0 0.8rem;font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;">Technical Specs</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label>Processor</label>
              <input type="text" name="processor" id="editCPU">
            </div>
            <div class="form-group">
              <label>RAM</label>
              <input type="text" name="ram" id="editRAM">
            </div>
            <div class="form-group">
              <label>Storage</label>
              <input type="text" name="storage" id="editStorage">
            </div>
            <div class="form-group">
              <label>GPU</label>
              <input type="text" name="gpu" id="editGPU">
            </div>
            <div class="form-group" style="grid-column:1/-1;">
              <label>Display</label>
              <input type="text" name="display" id="editDisplay">
            </div>
          </div>

          <!-- IMAGE MANAGEMENT SECTION -->
          <div style="margin:1.2rem 0 0.6rem;">
            <div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:0.5rem;">📷 Current Images <span id="imgCountLabel" style="text-transform:none;font-weight:600;color:#64748b;"></span></div>
            <div style="font-size:0.72rem;color:#64748b;margin-bottom:0.7rem;">Each laptop can have up to <strong>4 images</strong>. Click the red 🗑 button on any image to delete it instantly. Then upload replacements below.</div>
            <div id="editImageGallery" style="display:flex;flex-wrap:wrap;gap:0.8rem;min-height:80px;background:#f8fafc;border-radius:12px;padding:1rem;border:1px dashed #cbd5e1;align-items:flex-start;">
              <span style="color:#94a3b8;font-size:0.8rem;align-self:center;">Loading images...</span>
            </div>
          </div>

          <div style="margin:1rem 0 0.5rem;font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.04em;">➕ Add New Image(s)</div>
          <div id="uploadLimitWarning" style="display:none;background:#fef3c7;color:#92400e;border-radius:8px;padding:0.5rem 0.8rem;font-size:0.78rem;font-weight:600;margin-bottom:0.7rem;">⚠️ This laptop already has 4 images. Delete one first before uploading a new one.</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
              <label>Upload Image Files</label>
              <input type="file" name="new_gallery_images[]" id="newImagesInput" accept="image/*" multiple onchange="validateImageCount(this)">
              <small style="color:#64748b;font-size:0.7rem;">Select up to 4 images total (including existing ones)</small>
            </div>
            <div class="form-group">
              <label>Or Paste Image URLs (one per line)</label>
              <textarea name="new_gallery_urls" rows="3" placeholder="https://example.com/image.jpg" style="width:100%;resize:vertical;"></textarea>
            </div>
          </div>

          <div style="display:flex;gap:0.7rem;margin-top:0.5rem;">
            <button type="submit" class="btn btn-primary" style="flex:1;padding:0.65rem;">Update Entry</button>
            <button type="button" class="btn" style="flex:0 0 auto;padding:0.65rem 1rem;background:var(--bg-hover);" onclick="closeModal('editModal')">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function openModal(id) {
      document.getElementById(id).classList.add('open');
    }
    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
    }

    // Close on backdrop click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('open');
      });
    });

    let currentLaptopId = 0;
    let currentImageCount = 0;
    const MAX_IMAGES = 4;

    function openEditModal(row) {
      currentLaptopId = row.id;
      document.getElementById('editId').value      = row.id;
      document.getElementById('editTitle').value   = row.title || '';
      document.getElementById('editModel').value   = row.model || '';
      document.getElementById('editPrice').value   = row.price || '';
      document.getElementById('editStock').value   = row.stock || '';
      document.getElementById('editCPU').value     = row.processor || '';
      document.getElementById('editRAM').value     = row.ram || '';
      document.getElementById('editStorage').value = row.storage || '';
      document.getElementById('editGPU').value     = row.gpu || '';
      document.getElementById('editDisplay').value = row.display || '';
      setSelectValue('editBrand', row.brand_id);
      setSelectValue('editCat', row.category_id);

      loadImageGallery(row.id);
      openModal('editModal');
    }

    function loadImageGallery(laptopId) {
      const gallery = document.getElementById('editImageGallery');
      gallery.innerHTML = '<span style="color:#94a3b8;font-size:0.8rem;">Loading...</span>';
      document.getElementById('uploadLimitWarning').style.display = 'none';

      fetch('get_laptop_images.php?id=' + laptopId)
        .then(r => r.json())
        .then(images => {
          currentImageCount = images.length;
          document.getElementById('imgCountLabel').textContent = '(' + images.length + ' / ' + MAX_IMAGES + ')';
          updateUploadLimit();

          if (images.length === 0) {
            gallery.innerHTML = '<span style="color:#94a3b8;font-size:0.8rem;">No images yet — upload some below.</span>';
            return;
          }
          gallery.innerHTML = '';
          images.forEach(img => {
            const src = img.image_url.startsWith('http') ? img.image_url : '../uploads/' + img.image_url;
            const card = document.createElement('div');
            card.id = 'imgcard-' + img.id;
            card.style.cssText = 'position:relative;width:110px;text-align:center;';
            card.innerHTML = `
              <img src="${src}" style="width:110px;height:80px;object-fit:cover;border-radius:10px;border:2px solid #e2e8f0;display:block;">
              <button type="button"
                onclick="deleteImage(${img.id}, ${laptopId})"
                style="position:absolute;top:4px;right:4px;width:24px;height:24px;border-radius:50%;background:#dc2626;color:#fff;border:none;cursor:pointer;font-size:0.8rem;display:flex;align-items:center;justify-content:center;line-height:1;box-shadow:0 2px 6px rgba(0,0,0,0.3);"
                title="Delete this image permanently">
                🗑
              </button>
              <div style="font-size:0.62rem;color:#94a3b8;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:110px;" title="${escAttr(img.image_url)}">ID: ${img.id}</div>
            `;
            gallery.appendChild(card);
          });
        })
        .catch(() => {
          gallery.innerHTML = '<span style="color:#ef4444;font-size:0.8rem;">Could not load images. Check server.</span>';
        });
    }

    function deleteImage(imgId, laptopId) {
      if (!confirm('Delete this image permanently? This cannot be undone.')) return;
      const card = document.getElementById('imgcard-' + imgId);
      if (card) card.style.opacity = '0.4';

      const fd = new FormData();
      fd.append('img_id', imgId);
      fd.append('laptop_id', laptopId);

      fetch('delete_laptop_image.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
          if (res.success) {
            if (card) card.remove();
            currentImageCount = Math.max(0, currentImageCount - 1);
            document.getElementById('imgCountLabel').textContent = '(' + currentImageCount + ' / ' + MAX_IMAGES + ')';
            updateUploadLimit();
            if (currentImageCount === 0) {
              document.getElementById('editImageGallery').innerHTML = '<span style="color:#94a3b8;font-size:0.8rem;">No images — upload some below.</span>';
            }
          } else {
            if (card) card.style.opacity = '1';
            alert('Error: ' + (res.error || 'Could not delete image'));
          }
        })
        .catch(() => {
          if (card) card.style.opacity = '1';
          alert('Network error. Please try again.');
        });
    }

    function updateUploadLimit() {
      const warn = document.getElementById('uploadLimitWarning');
      const input = document.getElementById('newImagesInput');
      if (currentImageCount >= MAX_IMAGES) {
        warn.style.display = 'block';
        input.disabled = true;
      } else {
        warn.style.display = 'none';
        input.disabled = false;
      }
    }

    function validateImageCount(input) {
      const selected = input.files.length;
      const available = MAX_IMAGES - currentImageCount;
      if (selected > available) {
        alert(`You can only add ${available} more image(s). This laptop currently has ${currentImageCount} image(s) and the maximum is ${MAX_IMAGES}. Please select fewer files.`);
        input.value = '';
      }
    }

    function escAttr(s) {
      return String(s).replace(/"/g, '&quot;');
    }

    function setSelectValue(selectId, val) {
      const sel = document.getElementById(selectId);
      for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value == val) { sel.selectedIndex = i; break; }
      }
    }

    // Auto-dismiss flash alert
    setTimeout(function() {
      const el = document.getElementById('flash-alert');
      if (el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }
    }, 3000);

    // Open add modal if redirected after success/failure
    <?php if (isset($_GET['open']) && $_GET['open'] === 'add'): ?>
    openModal('addModal');
    <?php endif; ?>
  </script>
</body>
</html>