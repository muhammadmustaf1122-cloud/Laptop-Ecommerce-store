<?php
session_start();
include '../admin/db.php';

// Guest-friendly: no forced login. We just detect login state.
$is_logged_in = (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true && ($_SESSION['role'] ?? '') === 'customer');
$username_session = $is_logged_in ? ($_SESSION['username'] ?? 'User') : null;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// HANDLE REVIEW SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (
        empty($_POST['csrf_token']) ||
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid CSRF token.');
    }
    if ($is_logged_in) {
        $user_id = intval($_SESSION['user_id'] ?? 0);
        $rating = intval($_POST['rating']);
        $comment = trim($_POST['comment']);
        
        if ($rating >= 1 && $rating <= 5 && !empty($comment) && $user_id > 0) {
            $stmt = $conn->prepare("INSERT INTO reviews (laptop_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $id, $user_id, $rating, $comment);
            $stmt->execute();
            header("Location: laptop_detail.php?id=$id&review_success=1");
            exit();
        }
    }
}

// Check if user can review
$can_review = false;
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
if ($is_logged_in && $user_id > 0) {
    $check_sql = "SELECT 1 FROM orders o 
                  LEFT JOIN order_items oi ON o.id = oi.order_id 
                  WHERE o.user_id = $user_id AND o.status = 'delivered' 
                  AND (oi.laptop_id = $id OR o.laptop_id = $id) LIMIT 1";
    $check_res = mysqli_query($conn, $check_sql);
    if ($check_res && mysqli_num_rows($check_res) > 0) {
        $can_review = true;
    }
}

// Fetch Reviews
$reviews_query = "SELECT r.*, u.username, u.profile_pic FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.laptop_id = $id ORDER BY r.created_at DESC";
$reviews_result = mysqli_query($conn, $reviews_query);
$reviews = [];
$total_rating = 0;
if ($reviews_result) {
    while ($row = mysqli_fetch_assoc($reviews_result)) {
        $reviews[] = $row;
        $total_rating += $row['rating'];
    }
}
$avg_rating = count($reviews) > 0 ? round($total_rating / count($reviews), 1) : 0;

$query = "SELECT laptop.*, brand.name AS brand_name, categories.name AS category_name 
          FROM laptop 
          LEFT JOIN brand ON laptop.brand_id = brand.id 
          LEFT JOIN categories ON laptop.category_id = categories.id 
          WHERE laptop.id = $id LIMIT 1";

$result = mysqli_query($conn, $query);
$laptop = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;

if (!$laptop) {
    header("Location: index.php");
    exit();
}

// Fetch all gallery images for this specific laptop ID from the database
$gallery_query = "SELECT image_url FROM laptop_images WHERE laptop_id = $id";
$gallery_result = mysqli_query($conn, $gallery_query);
$images = [];

if ($gallery_result && mysqli_num_rows($gallery_result) > 0) {
    while ($img_row = mysqli_fetch_assoc($gallery_result)) {
        $path = $img_row['image_url'];
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $images[] = $path;
        } else {
            $images[] = !empty($path) ? '../uploads/' . $path : '';
        }
    }
}

// Fallback to legacy single image columns if the gallery table is empty for this entry
if (empty($images)) {
    $legacy_img = $laptop['image_url'] ?? $laptop['image'] ?? '';
    if (!empty($legacy_img)) {
        $images[] = filter_var($legacy_img, FILTER_VALIDATE_URL) ? $legacy_img : '../uploads/' . $legacy_img;
    }
}

$display_img = !empty($images) ? $images[0] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($laptop['title'] ?? 'Laptop Detail'); ?> | LAPTOP3D Store</title>
  <meta name="description" content="Inspect <?php echo htmlspecialchars($laptop['title'] ?? ''); ?> in detail — specs, pricing, and interactive 3D models at LAPTOP3D Store.">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <!-- Three.js Library -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

  <style>
    :root {
      --bg-dim: #f0eee9;
      --card-glass: rgba(255, 255, 255, 0.75);
      --card-border: rgba(255, 255, 255, 0.9);
      --accent-blue: #0284c7;
      --accent-hover: #0369a1;
      --glow-cyan: rgba(2, 132, 199, 0.35);
      --text-dark: #1e293b;
      --text-muted: #64748b;
      --shadow-3d: 0 20px 40px rgba(0, 0, 0, 0.08);
      --border-color: #e2e8f0;
      --success: #10b981;
      --danger: #e11d48;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    body {
      background-color: var(--bg-dim);
      color: var(--text-dark);
      overflow-x: hidden;
      min-height: 100vh;
    }

    #webgl-bg {
      position: fixed;
      top: 0; left: 0;
      width: 100vw; height: 100vh;
      z-index: -1;
      pointer-events: none;
    }

    /* ── NAV ─────────────────────────────── */
    .nav-wrapper {
      position: sticky;
      top: 0;
      z-index: 500;
      padding: 0.8rem 2rem;
      background: rgba(240, 238, 233, 0.82);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--card-border);
    }

    nav {
      max-width: 1280px;
      margin: 0 auto;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }

    .brand-logo {
      font-size: 1.25rem;
      font-weight: 900;
      color: var(--text-dark);
      text-decoration: none;
      letter-spacing: -0.5px;
      flex-shrink: 0;
    }
    .brand-logo span { color: var(--accent-blue); }

    .nav-links {
      display: flex;
      gap: 0.8rem;
      align-items: center;
    }

    .nav-links a.nav-item {
      color: var(--text-muted);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.88rem;
      transition: color 0.2s;
      white-space: nowrap;
    }
    .nav-links a.nav-item:hover { color: var(--accent-blue); }

    .btn-nav-login {
      background: var(--text-dark);
      color: #fff !important;
      padding: 0.42rem 1.1rem;
      border-radius: 20px;
      font-size: 0.88rem;
      font-weight: 700;
      text-decoration: none;
      transition: background 0.2s, transform 0.2s;
      white-space: nowrap;
    }
    .btn-nav-login:hover { background: var(--accent-blue); transform: translateY(-1px); }

    .user-badge {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(255,255,255,0.6);
      backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.85);
      padding: 0.35rem 0.85rem;
      border-radius: 30px;
      font-size: 0.85rem;
      color: var(--text-dark);
    }
    .status-dot {
      width: 7px; height: 7px;
      background: var(--success);
      border-radius: 50%;
      box-shadow: 0 0 8px rgba(16,185,129,0.7);
    }
    .user-badge strong { font-weight: 700; }

    .btn-logout {
      background: rgba(225, 29, 72, 0.08);
      color: var(--danger) !important;
      border: 1px solid rgba(225, 29, 72, 0.25);
      padding: 0.35rem 0.9rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.82rem;
      text-decoration: none;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
    }
    .btn-logout:hover {
      background: var(--danger);
      color: #fff !important;
      border-color: var(--danger);
      box-shadow: 0 4px 14px rgba(225,29,72,0.35);
      transform: translateY(-1px);
    }

    /* Cart Icon Button */
    .cart-icon-btn {
      position: relative;
      background: rgba(255,255,255,0.6);
      border: 1px solid var(--card-border);
      border-radius: 50%;
      width: 38px; height: 38px;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: background 0.2s, transform 0.2s;
      flex-shrink: 0;
    }
    .cart-icon-btn:hover { background: rgba(2,132,199,0.1); transform: scale(1.08); }
    .cart-count-badge {
      position: absolute;
      top: -5px; right: -5px;
      background: var(--accent-blue);
      color: #fff;
      font-size: 0.6rem;
      font-weight: 700;
      width: 17px; height: 17px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      border: 2px solid var(--bg-dim);
    }

    /* Profile link */
    .btn-profile {
      display: flex; align-items: center; gap: 0.4rem;
      color: var(--text-dark) !important;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.85rem;
      padding: 0.35rem 0.8rem;
      border-radius: 20px;
      background: rgba(255,255,255,0.5);
      border: 1px solid rgba(255,255,255,0.85);
      transition: all 0.2s;
    }
    .btn-profile:hover { background: rgba(2,132,199,0.1); color: var(--accent-blue) !important; }

    /* ── MAIN LAYOUT ───────────────────────── */
    .detail-wrapper {
      max-width: 1200px;
      margin: 2rem auto 5rem;
      padding: 0 2rem;
    }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      color: var(--text-muted);
      text-decoration: none;
      font-weight: 600;
      font-size: 0.9rem;
      margin-bottom: 1.5rem;
      transition: color 0.2s;
    }
    .back-link:hover { color: var(--accent-blue); }

    .detail-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2.5rem;
      align-items: start;
    }

    @media (max-width: 860px) {
      .detail-grid { grid-template-columns: 1fr; }
      .product-image-col { position: relative; top: 0; }
    }

    /* ── LEFT: IMAGE COLUMN ─────────────────── */
    .product-image-col {
      position: sticky;
      top: 80px;
      align-self: start;
    }

    .image-card {
      background: var(--card-glass);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1.5px solid var(--card-border);
      border-radius: 24px;
      padding: 1.5rem;
      box-shadow: var(--shadow-3d);
      transition: transform 0.4s cubic-bezier(0.25,1,0.5,1);
    }
    .image-card:hover { transform: translateY(-4px); }

    .main-img-wrap {
      width: 100%;
      height: 280px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
    }
    .main-img-wrap img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
      filter: drop-shadow(0 12px 20px rgba(0,0,0,0.12));
      transition: transform 0.5s ease;
    }
    .image-card:hover .main-img-wrap img { transform: scale(1.04); }

    .thumb-strip {
      display: flex;
      gap: 0.5rem;
      overflow-x: auto;
      padding-bottom: 0.3rem;
    }
    .thumb-item {
      flex-shrink: 0;
      width: 56px; height: 56px;
      border-radius: 10px;
      border: 2px solid var(--border-color);
      background: #fff;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      padding: 3px;
      transition: border-color 0.2s, transform 0.2s;
      overflow: hidden;
    }
    .thumb-item img { width: 100%; height: 100%; object-fit: contain; }
    .thumb-item.active, .thumb-item:hover {
      border-color: var(--accent-blue);
      transform: translateY(-2px);
    }

    /* Spec pills below image */
    .spec-pills-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin-top: 1rem;
    }
    .spec-pill {
      background: rgba(2,132,199,0.08);
      border: 1px solid rgba(2,132,199,0.2);
      color: var(--accent-blue);
      padding: 0.25rem 0.7rem;
      border-radius: 20px;
      font-size: 0.78rem;
      font-weight: 600;
    }

    /* ── RIGHT: INFO COLUMN ─────────────────── */
    .product-info-col {
      display: flex;
      flex-direction: column;
      gap: 1.2rem;
    }

    /* Brand Badge + Title */
    .brand-tag {
      display: inline-block;
      background: rgba(2,132,199,0.1);
      color: var(--accent-blue);
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      padding: 0.2rem 0.7rem;
      border-radius: 20px;
      margin-bottom: 0.5rem;
    }

    .product-title {
      font-size: 1.6rem;
      font-weight: 900;
      letter-spacing: -0.5px;
      color: var(--text-dark);
      line-height: 1.3;
    }

    .category-tag {
      display: inline-block;
      background: rgba(30,41,59,0.07);
      color: var(--text-muted);
      font-size: 0.78rem;
      font-weight: 600;
      padding: 0.18rem 0.65rem;
      border-radius: 20px;
      margin-top: 0.4rem;
    }

    /* Price Box */
    .price-box {
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      border: 1.5px solid var(--card-border);
      border-radius: 18px;
      padding: 1.1rem 1.3rem;
      box-shadow: 0 8px 20px rgba(0,0,0,0.04);
      display: flex;
      align-items: baseline;
      gap: 0.9rem;
      flex-wrap: wrap;
    }
    .price-main {
      font-size: 1.9rem;
      font-weight: 900;
      color: var(--accent-blue);
    }
    .price-old {
      font-size: 1rem;
      font-weight: 600;
      color: var(--text-muted);
      text-decoration: line-through;
    }
    .price-save {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--success);
      background: rgba(16,185,129,0.1);
      padding: 0.2rem 0.6rem;
      border-radius: 6px;
    }

    /* Spec Table */
    .specs-card {
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      border: 1.5px solid var(--card-border);
      border-radius: 18px;
      padding: 1.1rem 1.3rem;
      box-shadow: 0 8px 20px rgba(0,0,0,0.04);
    }
    .specs-card h4 {
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--text-muted);
      margin-bottom: 0.8rem;
    }
    .spec-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.55rem 0;
      border-bottom: 1px solid rgba(0,0,0,0.06);
      gap: 1rem;
    }
    .spec-row:last-child { border-bottom: none; }
    .spec-label {
      font-size: 0.83rem;
      font-weight: 600;
      color: var(--text-muted);
      flex-shrink: 0;
      width: 110px;
    }
    .spec-val {
      font-size: 0.87rem;
      font-weight: 700;
      color: var(--text-dark);
      text-align: right;
    }
    .in-stock { color: var(--success); }
    .out-stock { color: var(--danger); }

    /* Guarantee Box */
    .guarantee-box {
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      border: 1.5px solid var(--card-border);
      border-radius: 18px;
      padding: 1rem 1.3rem;
      box-shadow: 0 8px 20px rgba(0,0,0,0.04);
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }
    .guarantee-item {
      display: flex;
      align-items: flex-start;
      gap: 0.7rem;
      font-size: 0.83rem;
      color: var(--text-muted);
    }
    .guarantee-item svg { flex-shrink: 0; margin-top: 2px; color: var(--accent-blue); }
    .guarantee-item strong { color: var(--text-dark); }

    /* Quantity Selector */
    .qty-row {
      display: flex;
      align-items: center;
      gap: 0.6rem;
    }
    .qty-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); }
    .qty-ctrl {
      display: flex;
      align-items: center;
      gap: 0;
      background: var(--card-glass);
      border: 1.5px solid var(--border-color);
      border-radius: 50px;
      overflow: hidden;
    }
    .qty-btn {
      width: 34px; height: 34px;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text-dark);
      transition: background 0.2s;
      display: flex; align-items: center; justify-content: center;
    }
    .qty-btn:hover { background: rgba(2,132,199,0.1); }
    #qtyDisplay {
      min-width: 32px;
      text-align: center;
      font-weight: 700;
      font-size: 0.95rem;
      color: var(--text-dark);
    }

    /* Action Buttons */
    .action-btns {
      display: flex;
      flex-direction: column;
      gap: 0.7rem;
    }

    .btn-add-cart {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.6rem;
      width: 100%;
      padding: 0.85rem 1.5rem;
      border-radius: 16px;
      font-size: 0.95rem;
      font-weight: 800;
      cursor: pointer;
      border: 2px solid var(--accent-blue);
      color: var(--accent-blue);
      background: rgba(2,132,199,0.08);
      transition: all 0.25s cubic-bezier(0.25,1,0.5,1);
      letter-spacing: 0.01em;
    }
    .btn-add-cart:hover {
      background: var(--accent-blue);
      color: #fff;
      box-shadow: 0 8px 24px var(--glow-cyan);
      transform: translateY(-2px);
    }
    .btn-add-cart.added {
      background: var(--success);
      border-color: var(--success);
      color: #fff;
    }

    .btn-buy-now {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.6rem;
      width: 100%;
      padding: 0.85rem 1.5rem;
      border-radius: 16px;
      font-size: 0.95rem;
      font-weight: 800;
      cursor: pointer;
      border: none;
      color: #fff;
      background: linear-gradient(135deg, #1e293b, #334155);
      transition: all 0.25s cubic-bezier(0.25,1,0.5,1);
      text-decoration: none;
      letter-spacing: 0.01em;
    }
    .btn-buy-now:hover {
      background: linear-gradient(135deg, var(--accent-blue), var(--accent-hover));
      box-shadow: 0 8px 24px var(--glow-cyan);
      transform: translateY(-2px);
    }

    /* ── CART SLIDE-OVER DRAWER ─────────────── */
    .cart-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15,23,42,0.4);
      z-index: 1000;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s;
      backdrop-filter: blur(4px);
    }
    .cart-overlay.open {
      opacity: 1;
      pointer-events: all;
    }
    .cart-drawer {
      position: fixed;
      top: 0; right: 0;
      height: 100%;
      width: 380px;
      max-width: 94vw;
      background: rgba(240,238,233,0.95);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border-left: 1px solid var(--card-border);
      box-shadow: -20px 0 50px rgba(0,0,0,0.12);
      transform: translateX(100%);
      transition: transform 0.4s cubic-bezier(0.25,1,0.5,1);
      display: flex;
      flex-direction: column;
      z-index: 1001;
    }
    .cart-overlay.open .cart-drawer {
      transform: translateX(0);
    }
    .cart-drawer-head {
      padding: 1.2rem 1.5rem;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .cart-drawer-head h3 {
      font-size: 1rem;
      font-weight: 800;
      color: var(--text-dark);
    }
    .cart-close-btn {
      width: 32px; height: 32px;
      border-radius: 50%;
      border: none;
      background: rgba(0,0,0,0.06);
      cursor: pointer;
      font-size: 1.2rem;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.2s;
    }
    .cart-close-btn:hover { background: rgba(225,29,72,0.12); color: var(--danger); }
    .cart-body {
      flex: 1;
      overflow-y: auto;
      padding: 1rem 1.5rem;
    }
    .cart-item-row {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      padding: 0.8rem 0;
      border-bottom: 1px solid rgba(0,0,0,0.06);
    }
    .cart-item-row:last-child { border-bottom: none; }
    .cart-item-img {
      width: 54px; height: 54px;
      object-fit: contain;
      border-radius: 8px;
      background: #fff;
      border: 1px solid var(--border-color);
      flex-shrink: 0;
    }
    .cart-item-info { flex: 1; min-width: 0; }
    .cart-item-name {
      font-size: 0.83rem;
      font-weight: 700;
      color: var(--text-dark);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .cart-item-price {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--accent-blue);
      margin-top: 2px;
    }
    .cart-item-qty {
      display: flex;
      align-items: center;
      gap: 0.3rem;
      margin-top: 0.35rem;
    }
    .cqty-btn {
      width: 22px; height: 22px;
      border-radius: 50%;
      border: 1px solid var(--border-color);
      background: #fff;
      cursor: pointer;
      font-weight: 700;
      font-size: 0.8rem;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.15s;
    }
    .cqty-btn:hover { background: rgba(2,132,199,0.1); }
    .cqty-num { font-size: 0.82rem; font-weight: 700; min-width: 20px; text-align: center; }
    .cart-del-btn {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      opacity: 0.5;
      transition: opacity 0.2s;
    }
    .cart-del-btn:hover { opacity: 1; }
    .cart-footer {
      padding: 1.2rem 1.5rem;
      border-top: 1px solid var(--border-color);
      background: rgba(255,255,255,0.5);
    }
    .cart-total-line {
      display: flex;
      justify-content: space-between;
      font-weight: 700;
      font-size: 0.95rem;
      margin-bottom: 0.9rem;
    }
    .cart-total-line span:last-child { color: var(--accent-blue); }
    .btn-checkout-drawer {
      width: 100%;
      padding: 0.85rem;
      border-radius: 14px;
      border: none;
      background: linear-gradient(135deg, #1e293b, #334155);
      color: #fff;
      font-size: 0.9rem;
      font-weight: 800;
      cursor: pointer;
      transition: all 0.25s;
    }
    .btn-checkout-drawer:hover {
      background: linear-gradient(135deg, var(--accent-blue), var(--accent-hover));
      box-shadow: 0 8px 24px var(--glow-cyan);
      transform: translateY(-1px);
    }
    .cart-empty-msg {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--text-muted);
    }
    .cart-empty-msg .empty-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }

    /* ── FOOTER ───────────────────────────── */
    footer {
      background: rgba(240,238,233,0.88);
      border-top: 1px solid var(--card-border);
      backdrop-filter: blur(20px);
      padding: 3rem 2rem 2rem;
    }
    .footer-content {
      max-width: 1200px;
      margin: 0 auto 2rem;
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 2.5rem;
    }
    @media(max-width:720px) { .footer-content { grid-template-columns: 1fr 1fr; } }
    .footer-brand p { color: var(--text-muted); margin-top: 0.5rem; font-size: 0.88rem; line-height: 1.5; }
    .footer-col h4 { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.7rem; }
    .footer-col ul { list-style: none; }
    .footer-col li { margin-bottom: 0.4rem; }
    .footer-col a { color: var(--text-muted); text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
    .footer-col a:hover { color: var(--accent-blue); }
    .footer-bottom {
      max-width: 1200px;
      margin: 0 auto;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(0,0,0,0.06);
      display: flex;
      justify-content: space-between;
      color: var(--text-muted);
      font-size: 0.8rem;
    }

    /* Toast Notification */
    .toast-msg {
      position: fixed;
      bottom: 2rem;
      left: 50%;
      transform: translateX(-50%) translateY(20px);
      background: rgba(30,41,59,0.92);
      color: #fff;
      padding: 0.7rem 1.5rem;
      border-radius: 40px;
      font-size: 0.88rem;
      font-weight: 600;
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.1);
      z-index: 9999;
      opacity: 0;
      pointer-events: none;
      transition: all 0.35s cubic-bezier(0.25,1,0.5,1);
    }
    .toast-msg.show {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
    .toast-msg.success { border-color: rgba(16,185,129,0.4); }
    .toast-msg.error { border-color: rgba(225,29,72,0.4); }

    /* Reviews Section */
    .reviews-section {
      margin-top: 4rem;
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      border: 1.5px solid var(--card-border);
      border-radius: 24px;
      padding: 2rem;
      box-shadow: var(--shadow-3d);
    }
    .reviews-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      border-bottom: 1px solid var(--border-color);
      padding-bottom: 1rem;
    }
    .reviews-header h3 {
      font-size: 1.5rem;
      font-weight: 800;
    }
    .avg-rating-badge {
      background: rgba(2,132,199,0.1);
      color: var(--accent-blue);
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 800;
      font-size: 1.1rem;
      display: flex;
      align-items: center;
      gap: 0.4rem;
    }
    .review-form-card {
      background: #fff;
      border: 1px solid var(--border-color);
      border-radius: 16px;
      padding: 1.5rem;
      margin-bottom: 2rem;
    }
    .review-form-card h4 {
      margin-bottom: 1rem;
      font-size: 1.1rem;
      font-weight: 700;
    }
    .rating-select {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1rem;
    }
    .rating-select label {
      cursor: pointer;
    }
    .rating-select input {
      display: none;
    }
    .rating-star {
      font-size: 1.8rem;
      color: #cbd5e1;
      transition: color 0.2s;
    }
    .rating-select input:checked ~ label .rating-star,
    .rating-select label:hover ~ label .rating-star,
    .rating-select label:hover .rating-star {
      color: #fbbf24;
    }
    .review-textarea {
      width: 100%;
      border: 1px solid var(--border-color);
      border-radius: 12px;
      padding: 1rem;
      font-size: 0.95rem;
      resize: vertical;
      min-height: 100px;
      margin-bottom: 1rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .review-textarea:focus {
      border-color: var(--accent-blue);
    }
    .btn-submit-review {
      background: var(--accent-blue);
      color: #fff;
      border: none;
      padding: 0.7rem 1.5rem;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s;
    }
    .btn-submit-review:hover {
      background: var(--accent-hover);
    }
    .review-item {
      display: flex;
      gap: 1.2rem;
      padding: 1.5rem 0;
      border-bottom: 1px solid var(--border-color);
    }
    .review-item:last-child {
      border-bottom: none;
    }
    .reviewer-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: #e2e8f0;
      object-fit: cover;
      flex-shrink: 0;
    }
    .review-content {
      flex: 1;
    }
    .reviewer-name {
      font-weight: 700;
      font-size: 1.05rem;
      margin-bottom: 0.2rem;
    }
    .review-meta {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.6rem;
    }
    .review-stars {
      color: #fbbf24;
      font-size: 1rem;
      margin-bottom: 0.5rem;
    }
    .review-text {
      font-size: 0.95rem;
      line-height: 1.5;
      color: var(--text-dark);
    }
  </style>
</head>
<body>

  <canvas id="webgl-bg"></canvas>

  <!-- NAV -->
  <div class="nav-wrapper">
    <nav>
      <a href="index.php" class="brand-logo">LAPTOP<span>3D</span></a>

      <div class="nav-links">
        <a href="index.php" class="nav-item">← Showroom</a>

        <?php if ($is_logged_in): ?>
          <a href="profile.php" class="btn-profile">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <?php echo htmlspecialchars($username_session); ?>
          </a>
          <a href="../login/logout.php" class="btn-logout">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
          </a>
        <?php else: ?>
          <a href="../login/login.php" class="nav-item">Sign In</a>
          <a href="../login/signup.php" class="btn-nav-login">Get Started</a>
        <?php endif; ?>

        <!-- Cart Icon -->
        <button class="cart-icon-btn" onclick="toggleCart()" title="View Cart">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
          <span class="cart-count-badge" id="cartCountBadge">0</span>
        </button>
      </div>
    </nav>
  </div>

  <!-- MAIN CONTENT -->
  <div class="detail-wrapper">

    <a href="index.php" class="back-link">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
      Back to Showroom
    </a>

    <div class="detail-grid">

      <!-- LEFT: Image Column -->
      <div class="product-image-col">
        <div class="image-card">
          <div class="main-img-wrap">
            <?php if (!empty($display_img)): ?>
              <img id="mainProductImg" src="<?php echo htmlspecialchars($display_img); ?>" alt="<?php echo htmlspecialchars($laptop['title']); ?>">
            <?php else: ?>
              <svg style="width:140px;" viewBox="0 0 512 512" fill="none"><rect x="64" y="80" width="384" height="256" rx="16" fill="#1e293b"/><rect x="80" y="96" width="352" height="224" rx="8" fill="#0284c7" opacity="0.8"/><path d="M32 352H480L500 392C504 400 496 408 488 408H24C16 408 8 400 12 392L32 352Z" fill="#94a3b8"/></svg>
            <?php endif; ?>
          </div>

          <!-- Thumbnail Strip -->
          <?php if (count($images) > 1): ?>
          <div class="thumb-strip" id="thumbStrip">
            <?php foreach ($images as $idx => $img_src): ?>
              <div class="thumb-item <?php echo $idx === 0 ? 'active' : ''; ?>" onclick="changeMainImg('<?php echo htmlspecialchars($img_src); ?>', this)">
                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="Gallery <?php echo $idx + 1; ?>">
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- Spec Pills -->
          <div class="spec-pills-row">
            <?php if (!empty($laptop['ram'])): ?>
              <span class="spec-pill">💾 <?php echo htmlspecialchars($laptop['ram']); ?></span>
            <?php endif; ?>
            <?php if (!empty($laptop['storage'])): ?>
              <span class="spec-pill">💿 <?php echo htmlspecialchars($laptop['storage']); ?></span>
            <?php endif; ?>
            <?php if (!empty($laptop['processor'])): ?>
              <span class="spec-pill">⚡ <?php echo htmlspecialchars($laptop['processor']); ?></span>
            <?php endif; ?>
            <?php if (!empty($laptop['gpu'])): ?>
              <span class="spec-pill">🎮 <?php echo htmlspecialchars($laptop['gpu']); ?></span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- RIGHT: Info Column -->
      <div class="product-info-col">

        <!-- Brand + Title -->
        <div>
          <?php if (!empty($laptop['brand_name'])): ?>
            <span class="brand-tag"><?php echo htmlspecialchars($laptop['brand_name']); ?></span>
          <?php endif; ?>
          <h1 class="product-title"><?php echo htmlspecialchars($laptop['title']); ?></h1>
          <?php if (!empty($laptop['model'])): ?>
            <span class="category-tag">Model: <?php echo htmlspecialchars($laptop['model']); ?></span>
          <?php endif; ?>
          <?php if (!empty($laptop['category_name'])): ?>
            <span class="category-tag" style="margin-left:0.4rem;"><?php echo htmlspecialchars($laptop['category_name']); ?></span>
          <?php endif; ?>
        </div>

        <!-- Price -->
        <div class="price-box">
          <div class="price-main">$<?php echo number_format($laptop['price'], 2); ?></div>
          <?php if (!empty($laptop['discount_percentage']) && $laptop['discount_percentage'] > 0): ?>
            <?php $old_price = $laptop['price'] / (1 - ($laptop['discount_percentage'] / 100)); ?>
            <div class="price-old">$<?php echo number_format($old_price, 2); ?></div>
            <div class="price-save"><?php echo htmlspecialchars($laptop['discount_percentage']); ?>% OFF</div>
          <?php endif; ?>
        </div>

        <!-- Technical Specs Table -->
        <div class="specs-card">
          <h4>Technical Specifications</h4>

          <div class="spec-row">
            <span class="spec-label">Availability</span>
            <span class="spec-val <?php echo (!empty($laptop['stock']) && $laptop['stock'] > 0) ? 'in-stock' : 'out-stock'; ?>">
              <?php echo (!empty($laptop['stock']) && $laptop['stock'] > 0) ? '✓ In Stock (' . intval($laptop['stock']) . ' units)' : '✗ Out of Stock'; ?>
            </span>
          </div>

          <?php if (!empty($laptop['processor'])): ?>
          <div class="spec-row">
            <span class="spec-label">Processor</span>
            <span class="spec-val"><?php echo htmlspecialchars($laptop['processor']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($laptop['ram'])): ?>
          <div class="spec-row">
            <span class="spec-label">RAM</span>
            <span class="spec-val"><?php echo htmlspecialchars($laptop['ram']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($laptop['storage'])): ?>
          <div class="spec-row">
            <span class="spec-label">Storage</span>
            <span class="spec-val"><?php echo htmlspecialchars($laptop['storage']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($laptop['gpu'])): ?>
          <div class="spec-row">
            <span class="spec-label">Graphics</span>
            <span class="spec-val"><?php echo htmlspecialchars($laptop['gpu']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($laptop['display'])): ?>
          <div class="spec-row">
            <span class="spec-label">Display</span>
            <span class="spec-val"><?php echo htmlspecialchars($laptop['display']); ?></span>
          </div>
          <?php endif; ?>

          <div class="spec-row">
            <span class="spec-label">Brand</span>
            <span class="spec-val"><?php echo htmlspecialchars($laptop['brand_name'] ?? '—'); ?></span>
          </div>

          <div class="spec-row">
            <span class="spec-label">Category</span>
            <span class="spec-val"><?php echo htmlspecialchars($laptop['category_name'] ?? '—'); ?></span>
          </div>

          <div class="spec-row">
            <span class="spec-label">Warranty</span>
            <span class="spec-val">2 Year Official Warranty</span>
          </div>

          <?php if (!empty($laptop['sku'])): ?>
          <div class="spec-row">
            <span class="spec-label">SKU</span>
            <span class="spec-val"><?php echo htmlspecialchars($laptop['sku']); ?></span>
          </div>
          <?php endif; ?>

          <?php if (!empty($laptop['specs'])): ?>
          <div class="spec-row" style="flex-direction:column; align-items:flex-start; gap:0.3rem;">
            <span class="spec-label">Description</span>
            <span class="spec-val" style="text-align:left; font-weight:500; color:var(--text-muted);"><?php echo htmlspecialchars($laptop['specs']); ?></span>
          </div>
          <?php endif; ?>
        </div>

        <!-- Guarantee Box -->
        <div class="guarantee-box">
          <div class="guarantee-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <div><strong>Brand New Guarantee:</strong> 100% authentic sealed products only.</div>
          </div>
          <div class="guarantee-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            <div><strong>Fast Delivery:</strong> 1–3 days nationwide secure shipping.</div>
          </div>
          <div class="guarantee-item">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <div><strong>Store Pickup:</strong> Order online, collect from our showroom.</div>
          </div>
        </div>

        <!-- Quantity + Action Buttons -->
        <div class="qty-row">
          <span class="qty-label">Qty:</span>
          <div class="qty-ctrl">
            <button class="qty-btn" onclick="changeQty(-1)">−</button>
            <span id="qtyDisplay">1</span>
            <button class="qty-btn" onclick="changeQty(1)">+</button>
          </div>
        </div>

        <div class="action-btns">
          <button id="addCartBtn" class="btn-add-cart" onclick="handleAddToCart()">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            Add to Cart
          </button>

          <?php if ($is_logged_in): ?>
            <form action="../create-checkout-session.php" method="POST" id="buyNowForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="laptop_id" value="<?php echo $laptop['id']; ?>">
              <button type="submit" class="btn-buy-now">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                ⚡ Buy Now with Stripe
              </button>
            </form>
          <?php else: ?>
            <a href="../login/login.php?redirect=<?php echo urlencode('user/laptop_detail.php?id=' . $laptop['id']); ?>" class="btn-buy-now">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              Sign In to Buy Now
            </a>
          <?php endif; ?>
        </div>

      </div><!-- end info col -->
    </div><!-- end detail-grid -->

    <!-- Reviews Section -->
    <div class="reviews-section" id="reviews">
      <div class="reviews-header">
        <h3>Customer Reviews</h3>
        <?php if (count($reviews) > 0): ?>
          <div class="avg-rating-badge">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <?php echo number_format($avg_rating, 1); ?> / 5.0 (<?php echo count($reviews); ?> Reviews)
          </div>
        <?php endif; ?>
      </div>

      <?php if (isset($_GET['review_success'])): ?>
        <div style="background: rgba(16,185,129,0.1); color: var(--success); padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; font-weight: 700;">
          ✓ Thank you! Your review has been submitted.
        </div>
      <?php endif; ?>

      <?php if ($can_review): ?>
        <div class="review-form-card">
          <h4>Write a Review</h4>
          <form method="POST" action="laptop_detail.php?id=<?php echo $id; ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="rating-select" style="flex-direction: row-reverse; justify-content: flex-end;">
              <input type="radio" id="star5" name="rating" value="5" required /><label for="star5" title="5 stars"><span class="rating-star">★</span></label>
              <input type="radio" id="star4" name="rating" value="4" /><label for="star4" title="4 stars"><span class="rating-star">★</span></label>
              <input type="radio" id="star3" name="rating" value="3" /><label for="star3" title="3 stars"><span class="rating-star">★</span></label>
              <input type="radio" id="star2" name="rating" value="2" /><label for="star2" title="2 stars"><span class="rating-star">★</span></label>
              <input type="radio" id="star1" name="rating" value="1" /><label for="star1" title="1 star"><span class="rating-star">★</span></label>
            </div>
            <textarea name="comment" class="review-textarea" placeholder="Share your experience with this laptop..." required></textarea>
            <button type="submit" name="submit_review" class="btn-submit-review">Post Review</button>
          </form>
        </div>
      <?php endif; ?>

      <div class="reviews-list">
        <?php if (count($reviews) > 0): ?>
          <?php foreach ($reviews as $rev): ?>
            <div class="review-item">
              <?php if (!empty($rev['profile_pic'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($rev['profile_pic']); ?>" alt="User" class="reviewer-avatar">
              <?php else: ?>
                <div class="reviewer-avatar" style="display:flex;align-items:center;justify-content:center;font-weight:bold;color:#64748b;">
                  <?php echo strtoupper(substr($rev['username'], 0, 1)); ?>
                </div>
              <?php endif; ?>
              <div class="review-content">
                <div class="reviewer-name"><?php echo htmlspecialchars($rev['username']); ?></div>
                <div class="review-meta"><?php echo date('M d, Y', strtotime($rev['created_at'])); ?></div>
                <div class="review-stars">
                  <?php
                    $r = intval($rev['rating']);
                    echo str_repeat('★', $r) . str_repeat('☆', 5 - $r);
                  ?>
                </div>
                <div class="review-text"><?php echo nl2br(htmlspecialchars($rev['comment'])); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="color: var(--text-muted); text-align: center; padding: 2rem 0;">No reviews yet. Be the first to review this laptop after purchasing!</p>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- end detail-wrapper -->

  <!-- CART SLIDE-OVER DRAWER -->
  <div class="cart-overlay" id="cartOverlay" onclick="handleOverlayClick(event)">
    <div class="cart-drawer">
      <div class="cart-drawer-head">
        <h3>🛒 Your Cart</h3>
        <button class="cart-close-btn" onclick="toggleCart()">×</button>
      </div>
      <div class="cart-body" id="cartBody"></div>
      <div class="cart-footer">
        <div class="cart-total-line">
          <span>Subtotal</span>
          <span id="cartTotal">$0.00</span>
        </div>
        <button class="btn-checkout-drawer" onclick="proceedToCheckout()">Proceed to Checkout →</button>
      </div>
    </div>
  </div>

  <!-- Toast Notification -->
  <div class="toast-msg" id="toastMsg"></div>

  <!-- FOOTER -->
  <footer>
    <div class="footer-content">
      <div class="footer-brand">
        <a href="index.php" class="brand-logo">LAPTOP<span>3D</span></a>
        <p>A next-generation 3D interactive hardware showcase and e-commerce platform.</p>
      </div>
      <div class="footer-col">
        <h4>Showroom</h4>
        <ul>
          <li><a href="index.php">All Models</a></li>
          <li><a href="index.php#services">Why Choose Us</a></li>
          <li><a href="index.php#blogs">Tech Insights</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Account</h4>
        <ul>
          <?php if ($is_logged_in): ?>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="orders.php">My Orders</a></li>
            <li><a href="../login/logout.php">Logout</a></li>
          <?php else: ?>
            <li><a href="../login/login.php">Sign In</a></li>
            <li><a href="../login/signup.php">Register</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Legal</h4>
        <ul>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">Stripe Secured</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div>&copy; <?php echo date('Y'); ?> Laptop3D Store. All rights reserved.</div>
      <div>Powered by Three.js &amp; Animated Showcase</div>
    </div>
  </footer>

  <script>
    // ─── PRODUCT DATA ──────────────────────────────
    const PRODUCT_ID    = <?php echo $laptop['id']; ?>;
    const PRODUCT_TITLE = <?php echo json_encode($laptop['title']); ?>;
    const PRODUCT_PRICE = <?php echo floatval($laptop['price']); ?>;
    const PRODUCT_IMG   = <?php echo json_encode($display_img); ?>;
    const PRODUCT_MODEL = <?php echo json_encode($laptop['model'] ?? ''); ?>;
    const IS_LOGGED_IN  = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

    // ─── QUANTITY SELECTOR ─────────────────────────
    let qty = 1;
    function changeQty(delta) {
      qty = Math.max(1, qty + delta);
      document.getElementById('qtyDisplay').textContent = qty;
    }

    // ─── THUMBNAIL SWITCHER ────────────────────────
    function changeMainImg(src, el) {
      const img = document.getElementById('mainProductImg');
      if (img) img.src = src;
      document.querySelectorAll('.thumb-item').forEach(t => t.classList.remove('active'));
      if (el) el.classList.add('active');
    }

    // ─── LOCALSTORAGE CART ─────────────────────────
    const CART_KEY = 'laptopStore_cart';

    function getCart() {
      try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
      catch(e) { return []; }
    }
    function saveCart(cart) {
      localStorage.setItem(CART_KEY, JSON.stringify(cart));
      updateCartBadge();
      renderCartBody();
    }
    function updateCartBadge() {
      const count = getCart().reduce((s, i) => s + i.quantity, 0);
      const badge = document.getElementById('cartCountBadge');
      if (badge) badge.textContent = count;
    }

    function addToCart(id, title, price, image, model, quantity) {
      let cart = getCart();
      const existing = cart.find(i => i.id === id);
      if (existing) { existing.quantity += quantity; }
      else { cart.push({ id, title, price, image, model, quantity }); }
      saveCart(cart);
    }
    function updateQty(id, delta) {
      let cart = getCart();
      const item = cart.find(i => i.id === id);
      if (item) {
        item.quantity += delta;
        if (item.quantity <= 0) cart = cart.filter(i => i.id !== id);
      }
      saveCart(cart);
    }
    function removeItem(id) {
      saveCart(getCart().filter(i => i.id !== id));
    }

    function handleAddToCart() {
      addToCart(PRODUCT_ID, PRODUCT_TITLE, PRODUCT_PRICE, PRODUCT_IMG, PRODUCT_MODEL, qty);
      showToast('✓ Added to cart!', 'success');
      const btn = document.getElementById('addCartBtn');
      if (btn) {
        btn.classList.add('added');
        btn.textContent = '✓ Added to Cart!';
        setTimeout(() => {
          btn.classList.remove('added');
          btn.innerHTML = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg> Add to Cart`;
        }, 1800);
      }
      toggleCart(true);
    }

    // ─── CART DRAWER ───────────────────────────────
    let cartOpen = false;
    function toggleCart(forceOpen = false) {
      const overlay = document.getElementById('cartOverlay');
      if (forceOpen) { overlay.classList.add('open'); cartOpen = true; }
      else {
        cartOpen = !cartOpen;
        overlay.classList.toggle('open', cartOpen);
      }
      renderCartBody();
    }
    function handleOverlayClick(e) {
      if (e.target === document.getElementById('cartOverlay')) toggleCart();
    }

    function renderCartBody() {
      const cart = getCart();
      const body = document.getElementById('cartBody');
      const total = document.getElementById('cartTotal');
      if (!body) return;

      if (cart.length === 0) {
        body.innerHTML = `<div class="cart-empty-msg"><div class="empty-icon">🛒</div><p style="font-weight:700">Your cart is empty</p><p style="font-size:0.82rem;margin-top:0.3rem;">Add laptops while browsing the showroom.</p></div>`;
        if (total) total.textContent = '$0.00';
        return;
      }

      let html = '';
      let subtotal = 0;
      cart.forEach(item => {
        subtotal += item.price * item.quantity;
        html += `<div class="cart-item-row">
          <img src="${item.image || ''}" class="cart-item-img" alt="${item.title}" onerror="this.style.visibility='hidden'">
          <div class="cart-item-info">
            <div class="cart-item-name" title="${item.title}">${item.title}</div>
            <div class="cart-item-price">$${item.price.toFixed(2)}</div>
            <div class="cart-item-qty">
              <button class="cqty-btn" onclick="updateQty(${item.id},-1)">−</button>
              <span class="cqty-num">${item.quantity}</span>
              <button class="cqty-btn" onclick="updateQty(${item.id},1)">+</button>
            </div>
          </div>
          <button class="cart-del-btn" onclick="removeItem(${item.id})" title="Remove">🗑️</button>
        </div>`;
      });

      body.innerHTML = html;
      if (total) total.textContent = `$${subtotal.toFixed(2)}`;
    }

    function proceedToCheckout() {
      const cart = getCart();
      if (cart.length === 0) { showToast('Your cart is empty!', 'error'); return; }
      if (!IS_LOGGED_IN) {
        showToast('Please sign in to checkout', 'error');
        setTimeout(() => { window.location.href = '../login/login.php?redirect=checkout'; }, 1200);
        return;
      }
      const primaryItem = cart[0];
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '../create-checkout-session.php';
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'laptop_id'; input.value = primaryItem.id;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }

    // ─── TOAST ────────────────────────────────────
    let toastTimer;
    function showToast(msg, type='success') {
      const el = document.getElementById('toastMsg');
      el.textContent = msg;
      el.className = `toast-msg show ${type}`;
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => { el.classList.remove('show'); }, 2500);
    }

    // ─── INIT ──────────────────────────────────────
    document.addEventListener('DOMContentLoaded', () => {
      updateCartBadge();
    });

    // ─── THREE.JS AMBIENT BG ───────────────────────
    const canvas = document.querySelector('#webgl-bg');
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    const geo = new THREE.IcosahedronGeometry(1.2, 0);
    const mat = new THREE.MeshBasicMaterial({ color: 0x0284c7, wireframe: true, transparent: true, opacity: 0.1 });
    for (let i = 0; i < 18; i++) {
      const mesh = new THREE.Mesh(geo, mat);
      mesh.position.set((Math.random() - 0.5) * 18, (Math.random() - 0.5) * 16, (Math.random() - 0.5) * 10);
      scene.add(mesh);
    }
    camera.position.z = 5;

    (function animate() {
      requestAnimationFrame(animate);
      scene.children.forEach((m, i) => { m.rotation.x += 0.002 + i * 0.0001; m.rotation.y += 0.0025; });
      renderer.render(scene, camera);
    })();

    window.addEventListener('resize', () => {
      camera.aspect = window.innerWidth / window.innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(window.innerWidth, window.innerHeight);
    });
  </script>
</body>
</html>