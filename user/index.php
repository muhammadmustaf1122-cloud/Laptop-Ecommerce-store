<?php
session_start();
include '../admin/db.php';

// Check if customer is logged in
$is_logged_in = (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true && ($_SESSION['role'] ?? '') === 'customer');
$username_session = $_SESSION['username'] ?? '';
$user_avatar = $_SESSION['profile_pic'] ?? '';

// Fetch Brands for Filter Bar
$brands_query = "SELECT * FROM brand ORDER BY name ASC"; 
$brands_result = mysqli_query($conn, $brands_query);

// Fetch Categories for Filter Bar
$categories_query = "SELECT * FROM categories ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Fetch All Laptops with Brand and Category Details
$laptops_query = "SELECT laptop.*, brand.name AS brand_name, categories.name AS category_name 
                 FROM laptop 
                 LEFT JOIN brand ON laptop.brand_id = brand.id 
                 LEFT JOIN categories ON laptop.category_id = categories.id 
                 ORDER BY RAND()";
$laptops_result = mysqli_query($conn, $laptops_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CyberStore - Next-Gen Laptop Showroom</title>

  <!-- Three.js Library for WebGL Ambient Canvas -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

  <style>
    :root {
      --bg-dim: #f0eee9;
      --card-glass: rgba(255, 255, 255, 0.88);
      --card-border: rgba(255, 255, 255, 0.95);
      --accent-blue: #0284c7;
      --glow-cyan: rgba(2, 132, 199, 0.35);
      --text-dark: #1e293b;
      --text-muted: #64748b;
      --shadow-soft: 0 10px 30px rgba(0, 0, 0, 0.05);
      --shadow-hover: 0 18px 40px rgba(2, 132, 199, 0.15);
      --border-light: rgba(226, 232, 240, 0.8);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }

    body { background-color: var(--bg-dim); color: var(--text-dark); overflow-x: hidden; min-height: 100vh; }

    #webgl-bg { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; pointer-events: none; }

    /* STREAMLINED GLASS NAVBAR */
    nav {
      position: sticky;
      top: 0.75rem;
      z-index: 1000;
      width: calc(100% - 32px);
      max-width: 1250px;
      margin: 0 auto 1.5rem;
      background: rgba(240, 238, 233, 0.92);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--card-border);
      border-radius: 50px;
      padding: 0.5rem 1.2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 8px 25px rgba(0,0,0,0.04);
      box-sizing: border-box;
      transition: border-radius 0.3s ease;
    }

    .brand-logo {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--text-dark);
      text-decoration: none;
      letter-spacing: -0.5px;
      display: flex;
      align-items: center;
      gap: 0.35rem;
      flex: 0 0 auto;
    }
    .brand-logo span { color: var(--accent-blue); }

    .nav-middle-and-controls {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex: 1;
      margin-left: 1.5rem;
    }

    .nav-links { display: flex; gap: 1rem; align-items: center; justify-content: center; flex: 1; }
    .nav-links a.nav-item { color: var(--text-dark); text-decoration: none; font-weight: 700; font-size: 0.85rem; transition: color 0.2s; white-space: nowrap; }
    .nav-links a.nav-item:hover { color: var(--accent-blue); }

    .nav-controls { display: flex; gap: 0.6rem; align-items: center; flex: 0 0 auto; }

    /* Mobile Hamburger Menu Toggle Button */
    .nav-hamburger-btn {
      display: none;
      flex-direction: column;
      justify-content: space-between;
      width: 24px;
      height: 18px;
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 0;
      z-index: 1001;
    }

    .nav-hamburger-btn span {
      width: 100%;
      height: 2.5px;
      background-color: var(--text-dark);
      border-radius: 4px;
      transition: all 0.3s ease;
    }

    /* Small & Mobile Screens Responsive Navbar */
    @media (max-width: 850px) {
      nav {
        border-radius: 20px;
        flex-wrap: wrap;
        padding: 0.65rem 1rem;
      }

      .nav-hamburger-btn {
        display: flex;
      }

      .nav-middle-and-controls {
        display: none;
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
        margin-left: 0;
        padding-top: 0.85rem;
        margin-top: 0.65rem;
        border-top: 1px solid var(--border-light);
      }

      nav.mobile-active .nav-middle-and-controls {
        display: flex;
      }

      nav.mobile-active .nav-hamburger-btn span:nth-child(1) {
        transform: translateY(7.5px) rotate(45deg);
      }
      nav.mobile-active .nav-hamburger-btn span:nth-child(2) {
        opacity: 0;
      }
      nav.mobile-active .nav-hamburger-btn span:nth-child(3) {
        transform: translateY(-7.5px) rotate(-45deg);
      }

      .nav-links {
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        width: 100%;
      }

      .nav-controls {
        flex-direction: column;
        gap: 0.8rem;
        width: 100%;
        align-items: center;
      }

      .nav-controls > * {
        width: 100%;
        justify-content: center;
      }
    }

    /* CART TRIGGER BUTTON */
    .cart-trigger-btn {
      background: rgba(255, 255, 255, 0.85);
      border: 1px solid var(--card-border);
      padding: 0.45rem 1rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.85rem;
      color: var(--text-dark);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.02);
      transition: all 0.2s ease;
    }

    .cart-trigger-btn:hover {
      background: var(--accent-blue);
      color: #ffffff;
      transform: translateY(-1px);
    }

    .cart-badge {
      background: #e11d48;
      color: #ffffff;
      padding: 0.1rem 0.45rem;
      border-radius: 10px;
      font-size: 0.75rem;
      font-weight: 800;
    }

    .user-profile-link {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      font-size: 0.85rem;
      color: var(--text-dark);
      background: rgba(255, 255, 255, 0.6);
      padding: 0.35rem 0.85rem;
      border-radius: 20px;
      border: 1px solid var(--card-border);
      transition: all 0.2s;
    }

    .user-profile-link:hover { border-color: var(--accent-blue); }

    /* Orders pill button in nav */
    a.nav-badge-link {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      text-decoration: none;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--text-dark) !important;
      background: rgba(255, 255, 255, 0.6);
      padding: 0.38rem 0.85rem;
      border-radius: 20px;
      border: 1px solid var(--card-border);
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    a.nav-badge-link:hover {
      background: rgba(2, 132, 199, 0.1);
      border-color: rgba(2, 132, 199, 0.4);
      color: var(--accent-blue) !important;
      transform: translateY(-1px);
    }

    .nav-avatar-img {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      object-fit: cover;
    }

    .btn-auth {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--text-dark);
      color: #ffffff !important;
      padding: 0.45rem 1.1rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.85rem;
      text-decoration: none;
      transition: background 0.2s, transform 0.2s;
    }

    .btn-auth:hover { background: var(--accent-blue); transform: translateY(-1px); }

    .btn-register {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: rgba(2, 132, 199, 0.08);
      color: var(--accent-blue);
      padding: 0.45rem 1.1rem;
      border-radius: 20px;
      border: 1px solid rgba(2, 132, 199, 0.25);
      font-weight: 700;
      font-size: 0.85rem;
      text-decoration: none;
      transition: all 0.2s;
    }

    .btn-register:hover {
      background: var(--accent-blue);
      color: #ffffff;
      transform: translateY(-1px);
    }

    .btn-logout {
      background: rgba(225, 29, 72, 0.08);
      color: #e11d48 !important;
      border: 1px solid rgba(225, 29, 72, 0.2);
      padding: 0.35rem 0.9rem;
      border-radius: 20px;
      font-weight: 700;
      font-size: 0.82rem;
      text-decoration: none;
    }

    .btn-logout:hover { background: #e11d48; color: #ffffff !important; }

    /* Hero Section */
    .hero {
      width: 92%;
      max-width: 1200px;
      margin: 1rem auto 2rem;
      padding: 0 1rem;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      align-items: center;
      gap: 2.5rem;
    }

    .hero-text h1 { font-size: 2.6rem; line-height: 1.15; font-weight: 800; letter-spacing: -1px; margin-bottom: 0.8rem; }
    .hero-text h1 span { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .hero-text p { color: var(--text-muted); font-size: 1rem; margin-bottom: 1.5rem; line-height: 1.6; }

    .hero-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      background: rgba(2, 132, 199, 0.1);
      color: var(--accent-blue);
      border: 1px solid rgba(2, 132, 199, 0.25);
      padding: 0.35rem 0.9rem;
      border-radius: 30px;
      font-weight: 700;
      font-size: 0.8rem;
      margin-bottom: 1rem;
      text-transform: uppercase;
    }

    .hero-3d-stage { perspective: 1000px; width: 100%; height: 280px; display: flex; justify-content: center; align-items: center; }

    .floating-glass-card {
      width: 100%;
      height: 100%;
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      border: 1px solid var(--card-border);
      border-radius: 24px;
      box-shadow: var(--shadow-soft);
      display: flex;
      justify-content: center;
      align-items: center;
      animation: float 6s ease-in-out infinite;
      padding: 1.2rem;
    }

    .hero-hero-image { max-width: 88%; max-height: 88%; object-fit: contain; filter: drop-shadow(0 15px 20px rgba(0,0,0,0.15)); }

    @keyframes float {
      0%, 100% { transform: translateY(0px) rotateX(2deg) rotateY(-2deg); }
      50% { transform: translateY(-10px) rotateX(-2deg) rotateY(2deg); }
    }

    /* Ticker Banner */
    .info-ticker-container { overflow: hidden; background: #1e293b; color: #ffffff; padding: 0.65rem 0; white-space: nowrap; margin: 1.5rem 0 2.5rem; }
    .info-ticker-track { display: inline-block; white-space: nowrap; animation: slideLeft 30s linear infinite; }
    .ticker-item { display: inline-block; padding: 0 2rem; font-size: 0.85rem; font-weight: 500; }
    @keyframes slideLeft { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

    /* Showcase Controls Section */
    .showcase-header { width: 92%; max-width: 1250px; margin: 0 auto 1.5rem; position: relative; z-index: 300; }
    .showcase-title-row { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.2rem; }
    .showcase-title-row h2 { font-size: 1.8rem; font-weight: 800; letter-spacing: -0.5px; }

    .view-switcher { display: flex; background: rgba(255, 255, 255, 0.7); border: 1px solid var(--card-border); padding: 0.25rem; border-radius: 30px; }
    .view-btn { padding: 0.45rem 1.1rem; border-radius: 20px; border: none; background: transparent; font-weight: 700; font-size: 0.82rem; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 0.4rem; }
    .view-btn.active { background: var(--accent-blue); color: #ffffff; }

    /* Live Search & Combined Filter Bar */
    .filter-search-container {
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(16px);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 1rem 1.4rem;
      box-shadow: var(--shadow-soft);
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: center;
      justify-content: space-between;
      position: relative;
      z-index: 350;
    }

    .search-box { flex: 1; min-width: 250px; position: relative; display: flex; align-items: center; }
    .search-box svg { position: absolute; left: 1rem; color: var(--text-muted); pointer-events: none; }
    .search-input { width: 100%; padding: 0.65rem 1rem 0.65rem 2.8rem; background: #ffffff; border: 1px solid var(--border-light); border-radius: 30px; font-size: 0.9rem; color: var(--text-dark); outline: none; }
    .search-input:focus { border-color: var(--accent-blue); }

    .filter-controls { display: flex; align-items: center; gap: 0.7rem; flex-wrap: wrap; }
    .filter-btn-all { background: #ffffff; color: var(--text-dark); border: 1px solid var(--border-light); padding: 0.6rem 1.2rem; border-radius: 30px; font-weight: 700; font-size: 0.85rem; cursor: pointer; }
    .filter-btn-all.active { background: var(--text-dark); color: #ffffff; border-color: var(--text-dark); }

    .fancy-dropdown { position: relative; min-width: 140px; }
    .dropdown-selected { background: #ffffff; border: 1px solid var(--border-light); padding: 0.6rem 1.1rem; border-radius: 30px; font-weight: 700; font-size: 0.85rem; color: var(--text-dark); cursor: pointer; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; }
    .fancy-dropdown.active-filter .dropdown-selected { background: var(--accent-blue); color: #ffffff; border-color: var(--accent-blue); }
    .dropdown-arrow { font-size: 0.7rem; transition: transform 0.2s ease; }
    .fancy-dropdown.open .dropdown-arrow { transform: rotate(180deg); }

    .dropdown-menu {
      position: absolute;
      top: calc(100% + 8px);
      left: 0;
      width: 100%;
      min-width: 160px;
      background: #ffffff;
      border: 1px solid var(--border-light);
      border-radius: 16px;
      box-shadow: 0 15px 35px rgba(15, 23, 42, 0.18);
      padding: 0.4rem;
      z-index: 1000;
      opacity: 0;
      visibility: hidden;
      transform: translateY(-8px);
      transition: all 0.2s ease;
    }

    .fancy-dropdown.open .dropdown-menu { opacity: 1; visibility: visible; transform: translateY(0); }
    .dropdown-item { padding: 0.55rem 0.85rem; border-radius: 10px; font-weight: 600; font-size: 0.83rem; color: var(--text-dark); cursor: pointer; }
    .dropdown-item:hover { background: rgba(2, 132, 199, 0.1); color: var(--accent-blue); }

    .search-counter { width: 92%; max-width: 1250px; margin: 0.8rem auto 0; font-size: 0.85rem; color: var(--text-muted); display: flex; justify-content: space-between; }

    /* 4-COLUMN DESKTOP GRID VIEW */
    .laptop-grid-container {
      width: 92%;
      max-width: 1250px;
      margin: 1.2rem auto 4rem;
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.4rem;
      position: relative;
      z-index: 1;
    }

    @media (max-width: 1150px) { .laptop-grid-container { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 850px) { .laptop-grid-container { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 550px) { .laptop-grid-container { grid-template-columns: 1fr; } }

    .laptop-card {
      background: var(--card-glass);
      backdrop-filter: blur(16px);
      border: 1px solid var(--card-border);
      border-radius: 20px;
      padding: 1.1rem;
      box-shadow: var(--shadow-soft);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
      position: relative;
      z-index: 1;
    }

    .laptop-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-hover); border-color: rgba(2, 132, 199, 0.35); }

    .card-top-badges { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; }
    .badge-brand { background: rgba(2, 132, 199, 0.1); color: var(--accent-blue); padding: 0.2rem 0.6rem; border-radius: 10px; font-weight: 700; font-size: 0.72rem; text-transform: uppercase; }
    .badge-stock { padding: 0.2rem 0.55rem; border-radius: 10px; font-weight: 700; font-size: 0.72rem; }
    .stock-in { background: #dcfce7; color: #15803d; }
    .stock-out { background: #fee2e2; color: #991b1b; }

    .card-img-wrap { width: 100%; height: 135px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.8rem; }
    .card-img-wrap img { max-width: 88%; max-height: 100%; object-fit: contain; filter: drop-shadow(0 8px 10px rgba(0,0,0,0.1)); transition: transform 0.3s ease; }
    .laptop-card:hover .card-img-wrap img { transform: scale(1.05); }

    .card-details { flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between; }
    .card-title { font-size: 1.05rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.2rem; line-height: 1.3; }
    .card-model { font-size: 0.78rem; color: var(--text-muted); margin-bottom: 0.6rem; }

    .specs-grid { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-bottom: 1rem; }
    .spec-pill { background: rgba(255, 255, 255, 0.8); border: 1px solid var(--border-light); padding: 0.2rem 0.5rem; border-radius: 6px; font-size: 0.72rem; font-weight: 600; color: var(--text-dark); }

    .card-bottom-row { display: flex; align-items: center; justify-content: space-between; padding-top: 0.7rem; border-top: 1px solid var(--border-light); gap: 0.4rem; }
    .card-price { font-size: 1.15rem; font-weight: 900; color: var(--text-dark); }

    .btn-add-cart {
      background: rgba(2, 132, 199, 0.1);
      color: var(--accent-blue);
      border: 1px solid rgba(2, 132, 199, 0.25);
      padding: 0.45rem 0.7rem;
      border-radius: 10px;
      font-weight: 700;
      font-size: 0.78rem;
      cursor: pointer;
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .btn-add-cart:hover { background: var(--accent-blue); color: #ffffff; }

    .btn-inspect {
      background: var(--text-dark);
      color: #ffffff;
      text-decoration: none;
      padding: 0.45rem 0.75rem;
      border-radius: 10px;
      font-weight: 700;
      font-size: 0.78rem;
      transition: all 0.2s ease;
      white-space: nowrap;
    }
    .btn-inspect:hover { background: var(--accent-blue); }

    /* SLIDE-OVER GLASS CART DRAWER */
    .cart-drawer-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(15, 23, 42, 0.5);
      backdrop-filter: blur(8px);
      z-index: 2000;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }

    .cart-drawer-overlay.active { opacity: 1; visibility: visible; }

    .cart-drawer {
      position: absolute;
      top: 0;
      right: 0;
      width: 100%;
      max-width: 420px;
      height: 100%;
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(20px);
      box-shadow: -10px 0 40px rgba(0,0,0,0.15);
      display: flex;
      flex-direction: column;
      transform: translateX(100%);
      transition: transform 0.35s cubic-bezier(0.25, 1, 0.5, 1);
    }

    .cart-drawer-overlay.active .cart-drawer { transform: translateX(0); }

    .cart-drawer-header {
      padding: 1.4rem 1.6rem;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .cart-drawer-header h3 { font-size: 1.2rem; font-weight: 800; }
    .cart-drawer-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: var(--text-muted); }

    .cart-drawer-body {
      flex: 1;
      overflow-y: auto;
      padding: 1.4rem;
    }

    .cart-item {
      display: flex;
      gap: 1rem;
      align-items: center;
      padding: 1rem 0;
      border-bottom: 1px solid var(--border-light);
    }

    .cart-item-img { width: 60px; height: 50px; object-fit: contain; }

    .cart-item-info { flex: 1; }
    .cart-item-title { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.2rem; }
    .cart-item-price { font-size: 0.88rem; font-weight: 800; color: var(--accent-blue); }

    .cart-qty-ctrl {
      display: flex;
      align-items: center;
      gap: 0.4rem;
      margin-top: 0.4rem;
    }

    .qty-btn {
      width: 22px;
      height: 22px;
      border-radius: 6px;
      border: 1px solid var(--border-light);
      background: #ffffff;
      font-weight: bold;
      cursor: pointer;
    }

    .cart-drawer-footer {
      padding: 1.4rem 1.6rem;
      border-top: 1px solid var(--border-light);
      background: rgba(240, 238, 233, 0.5);
    }

    .cart-total-row {
      display: flex;
      justify-content: space-between;
      font-size: 1.1rem;
      font-weight: 800;
      margin-bottom: 1rem;
    }

    .btn-checkout {
      display: block;
      width: 100%;
      padding: 0.8rem;
      text-align: center;
      background: var(--text-dark);
      color: #ffffff;
      border-radius: 12px;
      font-weight: 700;
      font-size: 0.92rem;
      text-decoration: none;
      border: none;
      cursor: pointer;
      transition: background 0.2s;
    }

    .btn-checkout:hover { background: var(--accent-blue); }

    /* 3D COVERFLOW SHOWCASE */
    .coverflow-stage {
      position: relative; width: 100%; height: 440px; display: none; align-items: center; justify-content: center; perspective: 1200px; overflow: hidden; margin: 1rem 0 4rem; z-index: 1;
    }

    .coverflow-container { position: relative; width: 280px; height: 380px; display: flex; align-items: center; justify-content: center; transform-style: preserve-3d; }

    .cf-card {
      position: absolute; width: 280px; height: 380px; background: var(--card-glass); backdrop-filter: blur(16px); border: 1.5px solid var(--card-border); border-radius: 24px; padding: 1.4rem; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.5s cubic-bezier(0.25, 1, 0.5, 1), opacity 0.5s ease; cursor: pointer;
    }

    .cf-card.active { border-color: var(--accent-blue); box-shadow: 0 0 30px var(--glow-cyan), 0 20px 40px rgba(0, 0, 0, 0.12); z-index: 50 !important; }
    .cf-img-holder { width: 100%; height: 170px; display: flex; align-items: center; justify-content: center; }
    .cf-img-holder img { max-width: 90%; max-height: 100%; object-fit: contain; }
    .cf-title { font-size: 1.1rem; font-weight: 800; }
    .cf-price { font-size: 1.25rem; font-weight: 900; color: var(--accent-blue); }

    .btn-cf { display: block; width: 100%; padding: 0.7rem; text-align: center; background: var(--text-dark); color: #fff; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 0.85rem; }

    .cf-nav-btn { position: absolute; top: 50%; transform: translateY(-50%); z-index: 60; background: #ffffff; border: 1px solid var(--card-border); width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.1rem; color: var(--text-dark); box-shadow: 0 6px 15px rgba(0,0,0,0.08); }
    .cf-nav-prev { left: 5%; }
    .cf-nav-next { right: 5%; }

    /* SPOTLIGHT SLIDER FOR "WHY CHOOSE US" */
    .services-section { width: 92%; max-width: 850px; margin: 4rem auto 5rem; }
    .services-header { text-align: center; margin-bottom: 2rem; }
    .services-header h2 { font-size: 2rem; font-weight: 800; margin-bottom: 0.4rem; }
    .services-header p { color: var(--text-muted); font-size: 0.95rem; }

    .service-spotlight-frame {
      background: var(--card-glass); backdrop-filter: blur(16px); border: 1px solid var(--card-border); border-radius: 24px; padding: 2.5rem 2rem; box-shadow: var(--shadow-hover); min-height: 240px; display: flex; align-items: center; justify-content: center; position: relative;
    }

    .service-slide { display: none; flex-direction: column; align-items: center; text-align: center; width: 100%; opacity: 0; transition: opacity 0.5s ease-in-out; }
    .service-slide.active { display: flex; opacity: 1; }

    .service-icon-box { width: 60px; height: 60px; background: rgba(2, 132, 199, 0.1); color: var(--accent-blue); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 1rem; }
    .service-slide h3 { font-size: 1.35rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.6rem; }
    .service-slide p { font-size: 0.95rem; color: var(--text-muted); max-width: 600px; line-height: 1.6; }

    .spotlight-controls { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; }
    .spotlight-arrow { background: #ffffff; border: 1px solid var(--border-light); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: bold; color: var(--text-dark); }
    .spotlight-dots { display: flex; gap: 0.6rem; }
    .dot { width: 10px; height: 10px; border-radius: 50%; background: rgba(100, 116, 139, 0.3); cursor: pointer; transition: all 0.3s ease; }
    .dot.active { width: 24px; border-radius: 10px; background: var(--accent-blue); }

    /* TECH INSIGHTS: SIDE-BY-SIDE AUTO-SLIDER */
    .blog-section { width: 92%; max-width: 880px; margin: 4rem auto 6rem; }
    .blog-header { text-align: center; margin-bottom: 2rem; }
    .blog-header h2 { font-size: 2rem; font-weight: 800; margin-bottom: 0.4rem; }
    .blog-header p { color: var(--text-muted); font-size: 0.95rem; }

    .blog-spotlight-container { position: relative; background: var(--card-glass); backdrop-filter: blur(16px); border: 1px solid var(--card-border); border-radius: 24px; overflow: hidden; box-shadow: var(--shadow-hover); min-height: 280px; }
    .blog-horizontal-card { display: none; flex-direction: row; align-items: stretch; width: 100%; min-height: 280px; opacity: 0; transition: opacity 0.5s ease-in-out; cursor: pointer; }
    .blog-horizontal-card.active { display: flex; opacity: 1; }

    .blog-img-col { width: 45%; position: relative; overflow: hidden; min-height: 280px; }
    .blog-img-col img { width: 100%; height: 100%; object-fit: cover; }
    .blog-tag { position: absolute; top: 1rem; left: 1rem; background: rgba(15, 23, 42, 0.85); color: #ffffff; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }

    .blog-text-col { width: 55%; padding: 2rem; display: flex; flex-direction: column; justify-content: space-between; }
    .blog-meta { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.6rem; display: flex; gap: 1rem; }
    .blog-title { font-size: 1.3rem; font-weight: 800; color: var(--text-dark); margin-bottom: 0.6rem; line-height: 1.35; }
    .blog-snippet { font-size: 0.9rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.2rem; flex-grow: 1; }
    .blog-read-more { color: var(--accent-blue); font-weight: 700; font-size: 0.88rem; display: inline-flex; align-items: center; gap: 0.4rem; }

    .blog-dots-nav { display: flex; align-items: center; justify-content: center; gap: 0.6rem; margin-top: 1.5rem; }

    /* Modal Styling */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(10px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 1.5rem; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
    .modal-overlay.active { opacity: 1; visibility: visible; }
    .modal-card { background: #ffffff; border-radius: 24px; max-width: 620px; width: 100%; max-height: 85vh; overflow-y: auto; padding: 2.2rem; box-shadow: 0 25px 50px rgba(0,0,0,0.25); position: relative; }
    #quickViewModal .modal-card { max-width: 850px; overflow: hidden; padding: 3rem 2.5rem; }
    .modal-close { position: absolute; top: 1.2rem; right: 1.2rem; width: 34px; height: 34px; border-radius: 50%; background: var(--bg-dim); border: none; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; }

    /* Footer */
    footer { background: rgba(240, 238, 233, 0.85); border-top: 1px solid var(--card-border); backdrop-filter: blur(20px); padding: 3.5rem 2rem 2rem; }
    .footer-content { max-width: 1200px; margin: 0 auto 2.5rem; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 2.5rem; }
    .footer-brand p { color: var(--text-muted); margin-top: 0.6rem; font-size: 0.9rem; line-height: 1.5; }
    .footer-col h4 { font-size: 0.95rem; font-weight: 700; margin-bottom: 0.8rem; }
    .footer-col ul { list-style: none; }
    .footer-col ul li { margin-bottom: 0.5rem; }
    .footer-col ul li a { color: var(--text-muted); text-decoration: none; font-size: 0.88rem; }
    .footer-bottom { max-width: 1200px; margin: 0 auto; padding-top: 1.8rem; border-top: 1px solid rgba(0,0,0,0.06); display: flex; justify-content: space-between; color: var(--text-muted); font-size: 0.82rem; }

    @media (max-width: 800px) {
      .hero { grid-template-columns: 1fr; text-align: center; }
      .nav-links { gap: 0.6rem; }
      .footer-content { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>

  <!-- WebGL Canvas Background -->
  <canvas id="webgl-bg"></canvas>

  <!-- Streamlined Glass Navbar (Supports Guests & Logged-In Users) -->
  <nav id="mainNavbar">
    <a href="index.php" class="brand-logo">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="2" y1="20" x2="22" y2="20"></line></svg>
      LAPTOP<span>3D</span>
    </a>
    
    <div class="nav-middle-and-controls" id="navMiddleControls">
      <div class="nav-links">
        <a href="#showcase" class="nav-item">Showroom</a>
        <a href="#services" class="nav-item">Services</a>
        <a href="#blogs" class="nav-item">Tech Insights</a>
      </div>

      <div class="nav-controls">
        <!-- CART TRIGGER BUTTON WITH LIVE BADGE -->
        <button class="cart-trigger-btn" onclick="toggleCartDrawer()">
          🛒 Cart <span class="cart-badge" id="cartCountBadge">0</span>
        </button>

        <?php if ($is_logged_in): ?>
          <a href="orders.php" class="nav-badge-link">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
            Orders
          </a>

          <a href="profile.php" class="user-profile-link" title="Manage Profile">
            <?php if (!empty($user_avatar)): ?>
              <img src="../uploads/<?php echo htmlspecialchars($user_avatar); ?>" class="nav-avatar-img" alt="Avatar">
            <?php else: ?>
              <span class="user-status-dot"></span>
            <?php endif; ?>
            <span>Hi, <strong class="user-name"><?php echo htmlspecialchars($username_session); ?></strong></span>
          </a>
          <a href="../login/logout.php" class="btn-logout">Logout</a>
        <?php else: ?>
          <a href="../login/login.php" class="btn-auth">Sign In</a>
          <a href="../login/signup.php" class="btn-register">Register</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mobile Hamburger Toggle Button -->
    <button class="nav-hamburger-btn" id="navHamburgerBtn" onclick="toggleMobileNav()" title="Toggle Navigation">
      <span></span>
      <span></span>
      <span></span>
    </button>
  </nav>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-text">
      <div class="hero-badge-pill">🚀 Next-Gen Showroom</div>
      <h1>Explore Hardware in <span>Dimensional Detail</span></h1>
      <p>Search, compare, and inspect flagship laptops side-by-side with interactive cards and persistent cart cache.</p>
    </div>
    <div class="hero-3d-stage">
      <div class="floating-glass-card" id="heroCard">
        <img src="../uploads/laptop_6a67b60661d207.93708053.png" alt="Laptop showcase" class="hero-hero-image">
      </div>
    </div>
  </section>

  <!-- Ticker Banner -->
  <div class="info-ticker-container">
    <div class="info-ticker-track">
      <div class="ticker-item">🚀 Welcome to LAPTOP3D – Next-Gen Hardware Showroom</div>
      <div class="ticker-item">🛡️ 100% Verified Hardware & Factory Warranties</div>
      <div class="ticker-item">⚡ Instant 2FA Security & Real-Time Order Logistics</div>
      <div class="ticker-item">🌱 Sustainable Tech: Direct Dispatch & Zero Obsolescence</div>
      <!-- Loop Items -->
      <div class="ticker-item">🚀 Welcome to LAPTOP3D – Next-Gen Hardware Showroom</div>
      <div class="ticker-item">🛡️ 100% Verified Hardware & Factory Warranties</div>
      <div class="ticker-item">⚡ Instant 2FA Security & Real-Time Order Logistics</div>
      <div class="ticker-item">🌱 Sustainable Tech: Direct Dispatch & Zero Obsolescence</div>
    </div>
  </div>

  <!-- Showcase Controls Section -->
  <section id="showcase" class="showcase-header">
    <div class="showcase-title-row">
      <div>
        <h2>Flagship Laptop Catalog</h2>
        <p style="color: var(--text-muted); font-size: 0.9rem;">Filter by brand, category, or search text simultaneously.</p>
      </div>

      <!-- View Switcher -->
      <div class="view-switcher">
        <button class="view-btn active" id="btnGridView" onclick="switchView('grid')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
          Grid View
        </button>
        <button class="view-btn" id="btnCoverflowView" onclick="switchView('coverflow')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 17 22 12"></polyline></svg>
          3D Showcase
        </button>
      </div>
    </div>

    <!-- Live Search & Combined Filter Bar -->
    <div class="filter-search-container">
      <div class="search-box">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <input type="text" id="searchInput" class="search-input" placeholder="Search title, processor, RAM, GPU..." oninput="handleSearchAndFilter()">
      </div>

      <div class="filter-controls">
        <button class="filter-btn-all active" id="btnAllLaptops" onclick="filterByAll()">All Laptops</button>

        <div class="fancy-dropdown" id="brandDropdown">
          <div class="dropdown-selected" onclick="toggleDropdown('brandDropdown')">
            <span id="brandLabel">Brand</span>
            <span class="dropdown-arrow">&#9660;</span>
          </div>
          <div class="dropdown-menu">
            <div class="dropdown-item" onclick="selectFilter('brand', 'all', 'All Brands')">All Brands</div>
            <?php 
            if ($brands_result && mysqli_num_rows($brands_result) > 0) {
              mysqli_data_seek($brands_result, 0);
              while($b = mysqli_fetch_assoc($brands_result)) {
                $b_name = htmlspecialchars($b['name']);
                $b_slug = htmlspecialchars(strtolower($b['name']));
                echo '<div class="dropdown-item" onclick="selectFilter(\'brand\', \''.$b_slug.'\', \''.$b_name.'\')">'.$b_name.'</div>';
              }
            }
            ?>
          </div>
        </div>

        <div class="fancy-dropdown" id="categoryDropdown">
          <div class="dropdown-selected" onclick="toggleDropdown('categoryDropdown')">
            <span id="categoryLabel">Category</span>
            <span class="dropdown-arrow">&#9660;</span>
          </div>
          <div class="dropdown-menu">
            <div class="dropdown-item" onclick="selectFilter('cat', 'all', 'All Categories')">All Categories</div>
            <?php 
            if ($categories_result && mysqli_num_rows($categories_result) > 0) {
              mysqli_data_seek($categories_result, 0);
              while($c = mysqli_fetch_assoc($categories_result)) {
                $c_name = htmlspecialchars($c['name']);
                $c_slug = htmlspecialchars(strtolower($c['name']));
                echo '<div class="dropdown-item" onclick="selectFilter(\'cat\', \''.$c_slug.'\', \''.$c_name.'\')">'.$c_name.'</div>';
              }
            }
            ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Live Counter Bar -->
  <div class="search-counter">
    <span id="counterText">Showing all available laptops</span>
    <span style="font-size: 0.78rem;">Live Database Connected</span>
  </div>

  <!-- 4-COLUMN DESKTOP GRID VIEW -->
  <section id="gridShowcase" class="laptop-grid-container">
    <?php
    if ($laptops_result && mysqli_num_rows($laptops_result) > 0):
      mysqli_data_seek($laptops_result, 0);
      $grid_idx = 0;
      while($laptop = mysqli_fetch_assoc($laptops_result)):
        $img_val = $laptop['image_url'] ?? $laptop['image'] ?? '';
        if (filter_var($img_val, FILTER_VALIDATE_URL)) {
            $display_img = $img_val;
        } else {
            $display_img = !empty($img_val) ? '../uploads/' . $img_val : '';
        }

        $brand_slug = strtolower($laptop['brand_name'] ?? '');
        $cat_slug = strtolower($laptop['category_name'] ?? '');
        
        $specData = json_decode($laptop['specs'], true);
        $cpu = $specData['processor'] ?? 'N/A';
        $ram = $specData['ram'] ?? 'N/A';
        $storage = $specData['storage'] ?? 'N/A';
        $gpu = $specData['gpu'] ?? '';

        $search_terms = strtolower($laptop['title'] . ' ' . $laptop['model'] . ' ' . $laptop['brand_name'] . ' ' . $laptop['category_name'] . ' ' . $cpu . ' ' . $ram . ' ' . $storage . ' ' . $gpu);
        $is_in_stock = ($laptop['stock'] > 0);

        $is_initial_visible = ($grid_idx < 15);
        $grid_idx++;
    ?>
      <div class="laptop-card laptop-item" 
           data-brand="<?php echo htmlspecialchars($brand_slug); ?>" 
           data-cat="<?php echo htmlspecialchars($cat_slug); ?>"
           data-search="<?php echo htmlspecialchars($search_terms); ?>"
           data-initial-visible="<?php echo $is_initial_visible ? 'true' : 'false'; ?>"
           style="<?php echo $is_initial_visible ? '' : 'display: none;'; ?> cursor: pointer;"
           onclick="window.location.href='laptop_detail.php?id=<?php echo $laptop['id']; ?>'">
        
        <div class="card-top-badges">
          <span class="badge-brand"><?php echo htmlspecialchars($laptop['brand_name'] ?? 'Flagship'); ?></span>
          <div style="display:flex; gap:0.5rem; align-items:center;">
            <svg onclick="event.stopPropagation(); openQuickView(<?php echo $laptop['id']; ?>, '<?php echo addslashes(htmlspecialchars($laptop['title'])); ?>', <?php echo $laptop['price']; ?>, '<?php echo addslashes(htmlspecialchars($display_img)); ?>', '<?php echo addslashes(htmlspecialchars($laptop['model'])); ?>', '<?php echo addslashes(htmlspecialchars($cpu)); ?>', '<?php echo addslashes(htmlspecialchars($ram)); ?>', '<?php echo addslashes(htmlspecialchars($gpu)); ?>')" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6; cursor: pointer; transition: opacity 0.2s; color: var(--accent-blue);" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.6'" title="Quick View"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            <span class="badge-stock <?php echo $is_in_stock ? 'stock-in' : 'stock-out'; ?>">
              <?php echo $is_in_stock ? $laptop['stock'] . ' In Stock' : 'Out of Stock'; ?>
            </span>
          </div>
        </div>

        <div class="card-img-wrap">
          <?php if (!empty($display_img)): ?>
            <img src="<?php echo htmlspecialchars($display_img); ?>" alt="<?php echo htmlspecialchars($laptop['title']); ?>">
          <?php else: ?>
            <svg width="80" height="55" viewBox="0 0 512 512" fill="none">
              <rect x="64" y="80" width="384" height="256" rx="16" fill="#1e293b"/>
              <rect x="80" y="96" width="352" height="224" rx="8" fill="#0284c7" opacity="0.8"/>
              <path d="M32 352H480L500 392C504 400 496 408 488 408H24C16 408 8 400 12 392L32 352Z" fill="#94a3b8"/>
            </svg>
          <?php endif; ?>
        </div>

        <div class="card-details">
          <div>
            <div class="card-title"><?php echo htmlspecialchars($laptop['title']); ?></div>
            <div class="card-model">Model: <?php echo htmlspecialchars($laptop['model']); ?></div>

            <div class="specs-grid">
              <?php if (!empty($cpu) && $cpu !== 'N/A'): ?>
                <span class="spec-pill">⚡ <?php echo htmlspecialchars($cpu); ?></span>
              <?php endif; ?>
              <?php if (!empty($ram) && $ram !== 'N/A'): ?>
                <span class="spec-pill">🧠 <?php echo htmlspecialchars($ram); ?></span>
              <?php endif; ?>
              <?php if (!empty($gpu)): ?>
                <span class="spec-pill">🎮 <?php echo htmlspecialchars($gpu); ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="card-bottom-row">
            <div class="card-price">$<?php echo number_format($laptop['price'], 2); ?></div>
            
            <button class="btn-add-cart" onclick="event.stopPropagation(); addToCart(<?php echo $laptop['id']; ?>, '<?php echo addslashes(htmlspecialchars($laptop['title'])); ?>', <?php echo $laptop['price']; ?>, '<?php echo addslashes(htmlspecialchars($display_img)); ?>', '<?php echo addslashes(htmlspecialchars($laptop['model'])); ?>')">
              + Cart
            </button>
          </div>
        </div>

      </div>
    <?php 
      endwhile;
    endif;
    ?>
  </section>

  <!-- 3D COVERFLOW SHOWCASE -->
  <div id="coverflowShowcase" class="coverflow-stage">
    <button class="cf-nav-btn cf-nav-prev" onclick="moveCoverflow(-1)">&#10094;</button>

    <div class="coverflow-container" id="mainCoverflow">
      <?php
      if ($laptops_result && mysqli_num_rows($laptops_result) > 0):
        mysqli_data_seek($laptops_result, 0);
        $cf_idx = 0;
        while($laptop = mysqli_fetch_assoc($laptops_result)):
          $img_val = $laptop['image_url'] ?? $laptop['image'] ?? '';
          if (filter_var($img_val, FILTER_VALIDATE_URL)) {
              $display_img = $img_val;
          } else {
              $display_img = !empty($img_val) ? '../uploads/' . $img_val : '';
          }
          $brand_slug = strtolower($laptop['brand_name'] ?? '');
          $cat_slug = strtolower($laptop['category_name'] ?? '');
          
          $is_initial_visible = ($cf_idx < 15);
      ?>
        <div class="cf-card laptop-item" 
             data-brand="<?php echo htmlspecialchars($brand_slug); ?>" 
             data-cat="<?php echo htmlspecialchars($cat_slug); ?>"
             data-search="<?php echo htmlspecialchars(strtolower($laptop['title'] . ' ' . $laptop['model'])); ?>"
             data-initial-visible="<?php echo $is_initial_visible ? 'true' : 'false'; ?>"
             style="<?php echo $is_initial_visible ? '' : 'display: none;'; ?>"
             onclick="handleCardClick(this, <?php echo $cf_idx; ?>, event)">
          <div class="cf-img-holder">
            <?php if (!empty($display_img)): ?>
              <img src="<?php echo htmlspecialchars($display_img); ?>" alt="<?php echo htmlspecialchars($laptop['title']); ?>">
            <?php else: ?>
              <svg width="80" height="55" viewBox="0 0 512 512" fill="none"><rect x="64" y="80" width="384" height="256" rx="16" fill="#1e293b"/><rect x="80" y="96" width="352" height="224" rx="8" fill="#0284c7" opacity="0.8"/><path d="M32 352H480L500 392C504 400 496 408 488 408H24C16 408 8 400 12 392L32 352Z" fill="#94a3b8"/></svg>
            <?php endif; ?>
          </div>
          <div>
            <div class="cf-title"><?php echo htmlspecialchars($laptop['title']); ?></div>
            <div class="cf-price">$<?php echo number_format($laptop['price'], 2); ?></div>
          </div>
          <a href="laptop_detail.php?id=<?php echo $laptop['id']; ?>" class="btn-cf">Inspect Model</a>
        </div>
      <?php 
          $cf_idx++;
        endwhile;
      endif;
      ?>
    </div>

    <button class="cf-nav-btn cf-nav-next" onclick="moveCoverflow(1)">&#10095;</button>
  </div>

  <!-- WHY CHOOSE US SPOTLIGHT SLIDER -->
  <section id="services" class="services-section">
    <div class="services-header">
      <h2>Why Choose LAPTOP3D?</h2>
      <p>Reinventing how flagship hardware is inspected, verified, and delivered.</p>
    </div>

    <div class="service-spotlight-frame" id="serviceSpotlight">
      <div class="service-slide active" data-slide="0">
        <div class="service-icon-box">🌐</div>
        <h3>Interactive 3D Model Inspection</h3>
        <p>Inspect exact laptop models, port layouts, keyboard ergonomics, and visual aesthetics in digital 3D before placing your order.</p>
      </div>
      <div class="service-slide" data-slide="1">
        <div class="service-icon-box">🛡️</div>
        <h3>100% Genuine Factory Warranty</h3>
        <p>Direct manufacturer supply pipelines guarantee sealed hardware, official brand coverage, and authentic verified serial numbers.</p>
      </div>
      <div class="service-slide" data-slide="2">
        <div class="service-icon-box">⚡</div>
        <h3>Instant 2FA & Encrypted Checkout</h3>
        <p>Every account is guarded by dynamic 6-digit runtime security codes and encrypted Stripe checkout sessions for total peace of mind.</p>
      </div>
      <div class="service-slide" data-slide="3">
        <div class="service-icon-box">🌱</div>
        <h3>Eco-Tech Smart Inventory</h3>
        <p>Smart inventory management eliminates warehouse clutter, cuts unnecessary freight emissions, and minimizes electronic obsolescence.</p>
      </div>
    </div>

    <div class="spotlight-controls">
      <button class="spotlight-arrow" onclick="moveServiceSlide(-1)">&#10094;</button>
      <div class="spotlight-dots">
        <span class="dot active" onclick="setServiceSlide(0)"></span>
        <span class="dot" onclick="setServiceSlide(1)"></span>
        <span class="dot" onclick="setServiceSlide(2)"></span>
        <span class="dot" onclick="setServiceSlide(3)"></span>
      </div>
      <button class="spotlight-arrow" onclick="moveServiceSlide(1)">&#10095;</button>
    </div>
  </section>

  <!-- TECH INSIGHTS & ARTICLES -->
  <section id="blogs" class="blog-section">
    <div class="blog-header">
      <h2>Tech Insights & Articles</h2>
      <p>Stay informed with benchmarks, hardware guides, and power optimization tips.</p>
    </div>

    <div class="blog-spotlight-container">
      <article class="blog-horizontal-card active" data-blog-index="0" onclick="openBlogModal('gpu-guide')">
        <div class="blog-img-col">
          <span class="blog-tag">Hardware Guide</span>
          <img src="../uploads/gpu_guide_thumb.jpg" alt="GPU Guide">
        </div>
        <div class="blog-text-col">
          <div>
            <div class="blog-meta"><span>📅 July 2026</span><span>⏱️ 5 min read</span></div>
            <h3 class="blog-title">Choosing the Right Laptop GPU: RTX 40-Series vs Integrated Graphics</h3>
            <p class="blog-snippet">Discover how dedicated Ray Tracing, Tensor cores, and unified memory architectures impact creative workflows, gaming performance, and battery life.</p>
          </div>
          <div class="blog-read-more">Read Full Article &rarr;</div>
        </div>
      </article>

      <article class="blog-horizontal-card" data-blog-index="1" onclick="openBlogModal('cpu-benchmarks')">
        <div class="blog-img-col">
          <span class="blog-tag">Benchmarks</span>
          <img src="../uploads/cpu_benchmarks_thumb.jpg" alt="CPU Benchmarks">
        </div>
        <div class="blog-text-col">
          <div>
            <div class="blog-meta"><span>📅 July 2026</span><span>⏱️ 6 min read</span></div>
            <h3 class="blog-title">Intel Core i9 vs Apple M-Series: 2026 Workstation Shootout</h3>
            <p class="blog-snippet">A deep-dive architectural comparison analyzing single-core clock speeds, thermal throttling, multi-threaded rendering, and efficiency ratios under heavy loads.</p>
          </div>
          <div class="blog-read-more">Read Full Article &rarr;</div>
        </div>
      </article>

      <article class="blog-horizontal-card" data-blog-index="2" onclick="openBlogModal('battery-tech')">
        <div class="blog-img-col">
          <span class="blog-tag">Optimization</span>
          <img src="../uploads/battery_tech_thumb.jpg" alt="Battery Tech">
        </div>
        <div class="blog-text-col">
          <div>
            <div class="blog-meta"><span>📅 July 2026</span><span>⏱️ 4 min read</span></div>
            <h3 class="blog-title">Maximizing Laptop Battery Lifespan: Modern Charge Limits & Care</h3>
            <p class="blog-snippet">Learn how smart charge thresholds, cycle management, dynamic refresh rate scaling, and thermal regulation extend your battery's health by years.</p>
          </div>
          <div class="blog-read-more">Read Full Article &rarr;</div>
        </div>
      </article>
    </div>

    <div class="blog-dots-nav">
      <span class="dot active" onclick="setBlogSlide(0)"></span>
      <span class="dot" onclick="setBlogSlide(1)"></span>
      <span class="dot" onclick="setBlogSlide(2)"></span>
    </div>
  </section>

  <!-- SLIDE-OVER CART DRAWER MODAL -->
  <div class="cart-drawer-overlay" id="cartDrawerOverlay">
    <div class="cart-drawer">
      <div class="cart-drawer-header">
        <h3>🛒 Your Shopping Cart</h3>
        <button class="cart-drawer-close" onclick="toggleCartDrawer()">&times;</button>
      </div>

      <div class="cart-drawer-body" id="cartDrawerBody">
        <!-- Rendered dynamically by JS -->
      </div>

      <div class="cart-drawer-footer">
        <div class="cart-total-row">
          <span>Subtotal Total:</span>
          <span id="cartSubtotal">$0.00</span>
        </div>
        
        <button class="btn-checkout" onclick="proceedToCheckout()">
          Proceed to Checkout &rarr;
        </button>
      </div>
    </div>
  </div>

  <!-- Quick View Modal -->
  <div class="modal-overlay" id="quickViewModal">
    <div class="modal-card">
      <button class="modal-close" onclick="closeQuickView()">&times;</button>
      <div class="modal-body" id="quickViewBody">
        <!-- Rendered by JS -->
      </div>
    </div>
  </div>

  <!-- Interactive Blog Reader Modal -->
  <div class="modal-overlay" id="blogModal">
    <div class="modal-card">
      <button class="modal-close" onclick="closeBlogModal()">&times;</button>
      <div class="modal-body" id="modalBody"></div>
    </div>
  </div>

  <!-- Footer -->
  <footer>
    <div class="footer-content">
      <div class="footer-brand">
        <a href="index.php" class="brand-logo">LAPTOP<span>3D</span></a>
        <p>A next-generation 3D interactive hardware showcase and e-commerce platform.</p>
      </div>
      <div class="footer-col">
        <h4>Showroom</h4>
        <ul>
          <li><a href="#showcase">Flagship Models</a></li>
          <li><a href="#showcase">Gaming Laptops</a></li>
          <li><a href="#showcase">Ultrabooks</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4>Account</h4>
        <ul>
          <?php if ($is_logged_in): ?>
            <li><a href="profile.php">My Account Profile</a></li>
            <li><a href="orders.php">My Orders & Tracking</a></li>
          <?php else: ?>
            <li><a href="../login/login.php">Sign In</a></li>
            <li><a href="../login/signup.php">Register Account</a></li>
          <?php endif; ?>
        </ul>
      </div>
      <div class="footer-col">
        <h4>System & Trust</h4>
        <ul>
          <li><a href="#">Privacy Policy</a></li>
          <li><a href="#">Terms of Service</a></li>
          <li><a href="#">Stripe Secured</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <div>&copy; <?php echo date('Y'); ?> Laptop3D Store. All rights reserved.</div>
      <div>Powered by Three.js & Animated Modern Showcase</div>
    </div>
  </footer>

  <!-- Front-End & Shopping Cart Scripts -->
  <script>
    const isUserLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

    // LOCAL STORAGE PERSISTENT SHOPPING CART SYSTEM
    function getCart() {
      const data = localStorage.getItem('laptopStore_cart');
      return data ? JSON.parse(data) : [];
    }

    function saveCart(cart) {
      localStorage.setItem('laptopStore_cart', JSON.stringify(cart));
      updateCartBadge();
      renderCartDrawer();
    }

    function addToCart(id, title, price, image, model, quantity = 1) {
      let cart = getCart();
      const existing = cart.find(item => item.id === id);
      if (existing) {
        existing.quantity += quantity;
      } else {
        cart.push({ id, title, price, image, model, quantity });
      }
      saveCart(cart);
      toggleCartDrawer(true);
    }

    function updateCartItemQty(id, delta) {
      let cart = getCart();
      const item = cart.find(i => i.id === id);
      if (item) {
        item.quantity += delta;
        if (item.quantity <= 0) {
          cart = cart.filter(i => i.id !== id);
        }
      }
      saveCart(cart);
    }

    function removeCartItem(id) {
      let cart = getCart();
      cart = cart.filter(i => i.id !== id);
      saveCart(cart);
    }

    function updateCartBadge() {
      const cart = getCart();
      const count = cart.reduce((total, item) => total + item.quantity, 0);
      const badge = document.getElementById('cartCountBadge');
      if (badge) badge.innerText = count;
    }

    function renderCartDrawer() {
      const cart = getCart();
      const container = document.getElementById('cartDrawerBody');
      const subtotalEl = document.getElementById('cartSubtotal');

      if (!container) return;

      if (cart.length === 0) {
        container.innerHTML = `<div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
          <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛒</div>
          <p style="font-weight: 700;">Your cart is empty.</p>
          <p style="font-size: 0.85rem; margin-top: 0.3rem;">Add laptops to your cart to inspect & checkout.</p>
        </div>`;
        if (subtotalEl) subtotalEl.innerText = "$0.00";
        return;
      }

      let html = '';
      let subtotal = 0;

      cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        html += `
          <div class="cart-item">
            <img src="${item.image}" class="cart-item-img" alt="${item.title}">
            <div class="cart-item-info">
              <div class="cart-item-title">${item.title}</div>
              <div class="cart-item-price">$${item.price.toFixed(2)}</div>
              <div class="cart-qty-ctrl">
                <button class="qty-btn" onclick="updateCartItemQty(${item.id}, -1)">-</button>
                <span style="font-size: 0.85rem; font-weight: 700;">${item.quantity}</span>
                <button class="qty-btn" onclick="updateCartItemQty(${item.id}, 1)">+</button>
              </div>
            </div>
            <button onclick="removeCartItem(${item.id})" style="background: none; border: none; cursor: pointer; font-size: 1.1rem;" title="Remove">🗑️</button>
          </div>
        `;
      });

      container.innerHTML = html;
      if (subtotalEl) subtotalEl.innerText = `$${subtotal.toFixed(2)}`;
    }

    function toggleCartDrawer(forceOpen = false) {
      const overlay = document.getElementById('cartDrawerOverlay');
      if (forceOpen) {
        overlay.classList.add('active');
      } else {
        overlay.classList.toggle('active');
      }
      renderCartDrawer();
    }

    function proceedToCheckout() {
      const cart = getCart();
      if (cart.length === 0) {
        alert("Your cart is empty! Add a laptop first.");
        return;
      }

      if (!isUserLoggedIn) {
        // Save cart intent & prompt guest to sign in or register
        alert("Please sign in or create an account to complete your checkout.");
        window.location.href = "../login/login.php?redirect=checkout";
        return;
      }

      // If user is logged in, submit checkout session for the first item in cart
      const primaryItem = cart[0];
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '../create-checkout-session.php';

      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'laptop_id';
      input.value = primaryItem.id;
      form.appendChild(input);

      document.body.appendChild(form);
      form.submit();
    }

    document.addEventListener('DOMContentLoaded', () => {
      updateCartBadge();
    });

    // View Switcher Toggle
    let currentViewMode = 'grid';

    function switchView(mode) {
      currentViewMode = mode;
      const gridContainer = document.getElementById('gridShowcase');
      const coverflowContainer = document.getElementById('coverflowShowcase');
      const btnGrid = document.getElementById('btnGridView');
      const btnCoverflow = document.getElementById('btnCoverflowView');

      if (mode === 'grid') {
        gridContainer.style.display = 'grid';
        coverflowContainer.style.display = 'none';
        btnGrid.classList.add('active');
        btnCoverflow.classList.remove('active');
      } else {
        gridContainer.style.display = 'none';
        coverflowContainer.style.display = 'flex';
        btnCoverflow.classList.add('active');
        btnGrid.classList.remove('active');
        updateCardsList();
      }
    }

    // SIMULTANEOUS DUAL FILTERING
    let selectedBrandFilter = 'all';
    let selectedCategoryFilter = 'all';

    function handleSearchAndFilter() {
      const searchVal = document.getElementById('searchInput').value.toLowerCase().trim();
      const allGridCards = document.querySelectorAll('#gridShowcase .laptop-item');
      const allCoverflowCards = document.querySelectorAll('#coverflowShowcase .laptop-item');

      let visibleCount = 0;
      const isSearchOrFilterActive = (searchVal !== '' || selectedBrandFilter !== 'all' || selectedCategoryFilter !== 'all');

      allGridCards.forEach(card => {
        const cardBrand = card.getAttribute('data-brand') || '';
        const cardCat = card.getAttribute('data-cat') || '';
        const cardSearch = card.getAttribute('data-search') || '';
        const isInitiallyVisible = card.getAttribute('data-initial-visible') === 'true';

        const matchesBrand = (selectedBrandFilter === 'all' || cardBrand === selectedBrandFilter);
        const matchesCat = (selectedCategoryFilter === 'all' || cardCat === selectedCategoryFilter);
        const matchesSearch = (searchVal === '' || cardSearch.includes(searchVal));

        if (isSearchOrFilterActive) {
          if (matchesBrand && matchesCat && matchesSearch) {
            card.style.display = 'flex';
            visibleCount++;
          } else {
            card.style.display = 'none';
          }
        } else {
          if (isInitiallyVisible) {
            card.style.display = 'flex';
            visibleCount++;
          } else {
            card.style.display = 'none';
          }
        }
      });

      allCoverflowCards.forEach(card => {
        const cardBrand = card.getAttribute('data-brand') || '';
        const cardCat = card.getAttribute('data-cat') || '';
        const cardSearch = card.getAttribute('data-search') || '';
        const isInitiallyVisible = card.getAttribute('data-initial-visible') === 'true';

        const matchesBrand = (selectedBrandFilter === 'all' || cardBrand === selectedBrandFilter);
        const matchesCat = (selectedCategoryFilter === 'all' || cardCat === selectedCategoryFilter);
        const matchesSearch = (searchVal === '' || cardSearch.includes(searchVal));

        if (isSearchOrFilterActive) {
          if (matchesBrand && matchesCat && matchesSearch) {
            card.style.display = 'flex';
          } else {
            card.style.display = 'none';
          }
        } else {
          if (isInitiallyVisible) {
            card.style.display = 'flex';
          } else {
            card.style.display = 'none';
          }
        }
      });

      const counterText = document.getElementById('counterText');
      if (searchVal !== '') {
        counterText.innerText = `Found ${visibleCount} laptop${visibleCount === 1 ? '' : 's'} matching "${searchVal}"`;
      } else if (selectedBrandFilter !== 'all' || selectedCategoryFilter !== 'all') {
        counterText.innerText = `Filtered ${visibleCount} laptop${visibleCount === 1 ? '' : 's'}`;
      } else {
        counterText.innerText = `Showing all ${visibleCount} available laptops`;
      }

      if (currentViewMode === 'coverflow') {
        activeIndex = 0;
        updateCardsList();
      }
    }

    function toggleDropdown(id) {
      const dropdown = document.getElementById(id);
      const isOpen = dropdown.classList.contains('open');
      document.querySelectorAll('.fancy-dropdown').forEach(d => d.classList.remove('open'));
      if (!isOpen) dropdown.classList.add('open');
    }

    window.addEventListener('click', (e) => {
      if (!e.target.closest('.fancy-dropdown')) {
        document.querySelectorAll('.fancy-dropdown').forEach(d => d.classList.remove('open'));
      }
    });

    function selectFilter(type, value, displayName) {
      document.querySelectorAll('.fancy-dropdown').forEach(d => d.classList.remove('open'));

      if (type === 'brand') {
        selectedBrandFilter = value;
        const brandDrop = document.getElementById('brandDropdown');
        if (value === 'all') {
          brandDrop.classList.remove('active-filter');
          document.getElementById('brandLabel').innerText = 'Brand';
        } else {
          brandDrop.classList.add('active-filter');
          document.getElementById('brandLabel').innerText = displayName;
        }
      } else if (type === 'cat') {
        selectedCategoryFilter = value;
        const catDrop = document.getElementById('categoryDropdown');
        if (value === 'all') {
          catDrop.classList.remove('active-filter');
          document.getElementById('categoryLabel').innerText = 'Category';
        } else {
          catDrop.classList.add('active-filter');
          document.getElementById('categoryLabel').innerText = displayName;
        }
      }

      if (selectedBrandFilter === 'all' && selectedCategoryFilter === 'all') {
        document.getElementById('btnAllLaptops').classList.add('active');
      } else {
        document.getElementById('btnAllLaptops').classList.remove('active');
      }

      handleSearchAndFilter();
    }

    function filterByAll() {
      selectedBrandFilter = 'all';
      selectedCategoryFilter = 'all';
      document.getElementById('searchInput').value = '';
      document.querySelectorAll('.fancy-dropdown').forEach(d => d.classList.remove('open', 'active-filter'));
      document.getElementById('btnAllLaptops').classList.add('active');
      document.getElementById('brandLabel').innerText = 'Brand';
      document.getElementById('categoryLabel').innerText = 'Category';

      handleSearchAndFilter();
    }

    // 3D Coverflow View Logic
    let activeIndex = 0;
    let visibleCoverflowCards = [];

    function updateCardsList() {
      const allCards = Array.from(document.querySelectorAll('#mainCoverflow .cf-card'));
      visibleCoverflowCards = allCards.filter(card => card.style.display !== 'none');
      if (activeIndex >= visibleCoverflowCards.length) activeIndex = Math.max(0, visibleCoverflowCards.length - 1);
      renderCoverflow();
    }

    function renderCoverflow() {
      visibleCoverflowCards.forEach((card, idx) => {
        const offset = idx - activeIndex;
        const absOffset = Math.abs(offset);

        if (offset === 0) {
          card.style.transform = `translateX(0px) translateZ(100px) scale(1.1) rotateY(0deg)`;
          card.style.opacity = '1';
          card.style.zIndex = '30';
          card.classList.add('active');
        } else {
          card.classList.remove('active');
          const translateX = offset * 200; 
          const translateZ = -absOffset * 100;
          const rotateY = offset < 0 ? 25 : -25; 
          const scale = Math.max(0.7, 1 - absOffset * 0.15);
          const opacity = absOffset > 2 ? '0' : (1 - absOffset * 0.25).toString();

          card.style.transform = `translateX(${translateX}px) translateZ(${translateZ}px) scale(${scale}) rotateY(${rotateY}deg)`;
          card.style.opacity = opacity;
          card.style.zIndex = (20 - absOffset).toString();
        }
      });
    }

    function moveCoverflow(dir) {
      const newIdx = activeIndex + dir;
      if (newIdx >= 0 && newIdx < visibleCoverflowCards.length) {
        activeIndex = newIdx;
        renderCoverflow();
      }
    }

    function handleCardClick(cardElement, index, event) {
      const visibleIndex = visibleCoverflowCards.indexOf(cardElement);
      if (visibleIndex !== -1 && visibleIndex !== activeIndex) {
        event.preventDefault();
        activeIndex = visibleIndex;
        renderCoverflow();
      }
    }

    // SPOTLIGHT SLIDER FOR "WHY CHOOSE US"
    let currentServiceIndex = 0;
    const serviceSlides = document.querySelectorAll('.service-slide');
    const serviceDots = document.querySelectorAll('.spotlight-dots .dot');

    function setServiceSlide(index) {
      currentServiceIndex = (index + serviceSlides.length) % serviceSlides.length;
      serviceSlides.forEach((slide, idx) => {
        if (idx === currentServiceIndex) {
          slide.classList.add('active');
        } else {
          slide.classList.remove('active');
        }
      });
      serviceDots.forEach((dot, idx) => {
        if (idx === currentServiceIndex) {
          dot.classList.add('active');
        } else {
          dot.classList.remove('active');
        }
      });
    }

    function moveServiceSlide(dir) {
      setServiceSlide(currentServiceIndex + dir);
    }

    setInterval(() => {
      setServiceSlide(currentServiceIndex + 1);
    }, 3500);

    // SIDE-BY-SIDE AUTO-SLIDER FOR TECH INSIGHTS
    let currentBlogIndex = 0;
    const blogCards = document.querySelectorAll('.blog-horizontal-card');
    const blogDots = document.querySelectorAll('.blog-dots-nav .dot');

    function setBlogSlide(index) {
      currentBlogIndex = (index + blogCards.length) % blogCards.length;
      blogCards.forEach((card, idx) => {
        if (idx === currentBlogIndex) {
          card.classList.add('active');
        } else {
          card.classList.remove('active');
        }
      });
      blogDots.forEach((dot, idx) => {
        if (idx === currentBlogIndex) {
          dot.classList.add('active');
        } else {
          dot.classList.remove('active');
        }
      });
    }

    setInterval(() => {
      setBlogSlide(currentBlogIndex + 1);
    }, 2500);

    // Modal Article Logic
    const blogData = {
      'gpu-guide': {
        title: 'Choosing the Right Laptop GPU: RTX 40-Series vs Integrated Graphics',
        content: `
          <p>Selecting the right Graphics Processing Unit (GPU) is one of the most critical decisions when investing in a high-performance laptop. Whether you are rendering 3D animations, training machine learning models, or playing triple-A gaming titles, understanding GPU architecture ensures you maximize performance per watt.</p>
          <p>NVIDIA's RTX 40-Series laptop GPUs leverage Ada Lovelace architecture, introducing 4th-generation Tensor Cores and Optical Flow Accelerators that power DLSS 3 frame generation. This allows ultrabooks to achieve desktop-class framerates while maintaining manageable thermal output.</p>
          <p>Conversely, modern integrated graphics—such as Intel Arc Graphics and Apple’s unified GPU engines—offer incredible energy efficiency for video editing and everyday tasks without the battery drain of dedicated silicon. Consider your primary workloads before prioritizing raw VRAM vs all-day endurance.</p>
        `
      },
      'cpu-benchmarks': {
        title: 'Intel Core i9 vs Apple M-Series: 2026 Workstation Shootout',
        content: `
          <p>Mobile processors have reached extraordinary milestones in 2026. High-end workstations featuring Intel's Core i9 HX-series processors utilize hybrid performance and efficiency cores to push peak turbo frequencies over 5.4 GHz, ideal for intensive CAD modeling and code compilation.</p>
          <p>Meanwhile, Apple’s M-Series unified memory architecture allows CPU and GPU cores to share a massive high-bandwidth memory pool directly on-chip. This results in zero swap latency during 8K video timelines while consuming a fraction of the thermal energy.</p>
          <p>Our benchmark tests indicate that Intel leads in pure multi-threaded burst workloads, whereas Apple Silicon maintains unmatched performance stability when running unplugged on battery power.</p>
        `
      },
      'battery-tech': {
        title: 'Maximizing Laptop Battery Lifespan: Modern Charge Limits & Care',
        content: `
          <p>Lithium-ion laptop batteries degrade over time due to thermal stress and high voltage retention. Modern laptop firmware includes smart charge thresholds that cap charging at 80% when plugged into wall power for extended periods, drastically reducing degradation.</p>
          <p>To maximize your laptop battery's lifespan, keep ambient operating temperatures cool, avoid letting the charge drop below 15% frequently, and utilize dynamic display refresh rate scaling (e.g. switching between 60Hz for documents and 120Hz/240Hz for motion content).</p>
          <p>Following these simple practices can extend your battery's operational health by up to 300 charge cycles, preserving peak runtime for years to come.</p>
        `
      }
    };

    function openBlogModal(key) {
      const data = blogData[key];
      if (data) {
        document.getElementById('modalBody').innerHTML = `<h3>${data.title}</h3>${data.content}`;
        document.getElementById('blogModal').classList.add('active');
      }
    }

    function closeBlogModal() {
      document.getElementById('blogModal').classList.remove('active');
    }

    document.getElementById('blogModal').addEventListener('click', (e) => {
      if (e.target.id === 'blogModal') closeBlogModal();
    });

    // Quick View Logic
    function openQuickView(id, title, price, image, model, cpu, ram, gpu) {
      const body = document.getElementById('quickViewBody');
      body.innerHTML = `
        <div style="display:flex; flex-wrap: nowrap; gap: 3rem; align-items:center;">
          <div style="flex: 1; max-width: 50%; text-align:center;">
            <img src="${image}" style="max-width:100%; border-radius:16px; max-height:350px; object-fit:contain;">
          </div>
          <div style="flex: 1; max-width: 50%; display: flex; flex-direction: column; justify-content: center; text-align: left;">
            <h3 style="font-size: 1.6rem; font-weight:900; margin-bottom: 0.4rem; color: var(--text-dark); line-height: 1.25;">${title}</h3>
            <p style="color:var(--text-muted); margin-bottom: 1.2rem; font-size: 0.95rem;">Model: ${model}</p>
            <div style="font-size: 1.8rem; font-weight: 900; color: var(--accent-blue); margin-bottom: 1.5rem;">$${price.toFixed(2)}</div>
            <div class="specs-grid" style="margin-bottom: 2rem;">
              ${cpu && cpu !== 'N/A' ? `<span class="spec-pill">⚡ ${cpu}</span>` : ''}
              ${ram && ram !== 'N/A' ? `<span class="spec-pill">🧠 ${ram}</span>` : ''}
              ${gpu && gpu !== 'N/A' ? `<span class="spec-pill">🎮 ${gpu}</span>` : ''}
            </div>
            <button class="btn-add-cart" style="width:100%; margin-bottom: 1rem; padding: 1rem; border:none; background:var(--accent-blue); color:#fff; border-radius:12px; font-weight:800; font-size: 1.05rem; cursor:pointer;" onclick="addToCart(${id}, '${title.replace(/'/g, "\\'")}', ${price}, '${image}', '${model.replace(/'/g, "\\'")}')">+ Add to Cart</button>
            <a href="laptop_detail.php?id=${id}" style="display:block; text-align:center; padding:1rem; background:rgba(0,0,0,0.04); border-radius:12px; font-weight:700; color:var(--text-dark); text-decoration:none;">View Full Details &rarr;</a>
          </div>
        </div>
      `;
      document.getElementById('quickViewModal').classList.add('active');
    }

    function closeQuickView() {
      document.getElementById('quickViewModal').classList.remove('active');
    }
    
    document.getElementById('quickViewModal').addEventListener('click', (e) => {
      if (e.target.id === 'quickViewModal') closeQuickView();
    });

    // Three.js Ambient WebGL Canvas
    const canvas = document.querySelector('#webgl-bg');
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ canvas: canvas, alpha: true, antialias: true });

    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));

    const geometry = new THREE.IcosahedronGeometry(1.2, 0);
    const material = new THREE.MeshBasicMaterial({ color: 0x0284c7, wireframe: true, transparent: true, opacity: 0.12 });

    for (let i = 0; i < 20; i++) {
      const mesh = new THREE.Mesh(geometry, material);
      mesh.position.x = (Math.random() - 0.5) * 18;
      mesh.position.y = (Math.random() - 0.5) * 18;
      mesh.position.z = (Math.random() - 0.5) * 12;
      scene.add(mesh);
    }

    camera.position.z = 5;

    const heroCard = document.getElementById('heroCard');
    window.addEventListener('mousemove', (e) => {
      const mouseX = (e.clientX / window.innerWidth - 0.5);
      const mouseY = (e.clientY / window.innerHeight - 0.5);
      if (heroCard) {
        heroCard.style.transform = `rotateY(${mouseX * 18}deg) rotateX(${-mouseY * 18}deg)`;
      }
    });

    function animate() {
      requestAnimationFrame(animate);
      scene.children.forEach((mesh, index) => {
        mesh.rotation.x += 0.002 + (index * 0.0001);
        mesh.rotation.y += 0.0025;
      });
      renderer.render(scene, camera);
    }
    animate();

    window.addEventListener('resize', () => {
      camera.aspect = window.innerWidth / window.innerHeight;
      camera.updateProjectionMatrix();
      renderer.setSize(window.innerWidth, window.innerHeight);
    });

    // Mobile Navbar Toggle Function
    function toggleMobileNav() {
      const nav = document.getElementById('mainNavbar');
      if (nav) {
        nav.classList.toggle('mobile-active');
      }
    }
  </script>
</body>
</html>