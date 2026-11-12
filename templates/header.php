<?php
/**
 * Main Customer Header Template
 * Store: nnasa_ca Accessories Marketplace
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

$pageTitle = $pageTitle ?? 'Toko Aksesoris Wanita Elegan & Kekinian';
$currentUser = current_user();
$cartCount = get_user_cart_count();
$wishlistCount = get_user_wishlist_count();

// Get active categories for navigation
$db = getDB();
$navCategories = $db->query("SELECT id, name, slug FROM categories WHERE status = 'active' ORDER BY parent_id ASC, id ASC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
  <meta name="description" content="<?= htmlspecialchars(get_setting('site_description', 'Toko online aksesoris wanita terlengkap: jepit rambut mutiara, kalung rose gold, gelang kristal, dan aksesoris fashion estetik.')) ?>">
  
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>public/assets/images/logo-icon.svg">

  <!-- Google Fonts: Plus Jakarta Sans & Playfair Display -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Bootstrap 5.3 & Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Custom Styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css?v=<?= time() ?>">

  <script>
    window.BASE_URL = '<?= BASE_URL ?>';
    // Anti-FOUC (Flash of Unstyled Content): Set theme immediately before render
    (function() {
      const savedTheme = localStorage.getItem('nnasa_theme') || 
        (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-theme', savedTheme);
      document.documentElement.setAttribute('data-bs-theme', savedTheme);
    })();
  </script>
</head>
<body>

  <!-- Top Notice Bar -->
  <div class="top-notice-bar py-1 px-3 text-center d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center">
      <span><i class="bi bi-sparkles me-1"></i> Promo Spesial: Gunakan voucher <strong>NNASANEW</strong> untuk diskon 15%!</span>
      <div>
        <a href="<?= BASE_URL ?>tracking.php" class="text-white text-decoration-none me-3"><i class="bi bi-geo-alt me-1"></i> Lacak Pesanan</a>
        <a href="<?= BASE_URL ?>help.php" class="text-white text-decoration-none me-3"><i class="bi bi-question-circle me-1"></i> Pusat Bantuan</a>
        <?php if (is_admin()): ?>
          <a href="<?= ADMIN_URL ?>" class="badge bg-white text-dark text-decoration-none"><i class="bi bi-speedometer2 me-1"></i> Panel Admin</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Main Navigation Header -->
  <header class="main-navbar sticky-top">
    <div class="container py-2">
      <div class="row align-items-center gy-2">
        <!-- Logo -->
        <div class="col-6 col-md-3 col-lg-2">
          <a class="navbar-brand d-inline-flex align-items-center text-decoration-none" href="<?= BASE_URL ?>index.php">
            <span class="brand-logo-text">nnasa<span class="brand-dot">_ca</span></span>
          </a>
        </div>

        <!-- Search Bar -->
        <div class="col-12 col-md-5 col-lg-6 order-3 order-md-2">
          <form action="<?= BASE_URL ?>products.php" method="GET" class="search-input-group d-flex align-items-center">
            <input type="text" name="q" class="form-control" placeholder="Cari aksesoris rambut, kalung, cincin, gelang..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" aria-label="Cari produk">
            <button type="submit" class="btn-search">
              <i class="bi bi-search me-1"></i> Cari
            </button>
          </form>
        </div>

        <!-- Right Quick Actions -->
        <div class="col-6 col-md-4 col-lg-4 order-2 order-md-3 text-end d-flex align-items-center justify-content-end gap-2">
          
          <!-- Theme Mode Toggle (Sun/Moon) -->
          <button type="button" 
                  id="themeToggleBtn" 
                  class="theme-toggle-btn" 
                  aria-label="Ganti Mode Tampilan" 
                  title="Ganti Mode Gelap / Terang">
            <!-- Icon Sun (shown in dark mode) -->
            <svg class="theme-icon icon-sun" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="5"></circle>
              <line x1="12" y1="1" x2="12" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="23"></line>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
              <line x1="1" y1="12" x2="3" y2="12"></line>
              <line x1="21" y1="12" x2="23" y2="12"></line>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
            <!-- Icon Crescent Moon (shown in light mode) -->
            <svg class="theme-icon icon-moon" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
          </button>

          <!-- Wishlist Icon -->
          <a href="<?= BASE_URL ?>wishlist.php" class="nav-icon-link" title="Favorit Saya" data-bs-toggle="tooltip">
            <i class="bi bi-heart"></i>
            <span class="nav-icon-badge wishlist-counter-badge"><?= $wishlistCount ?></span>
          </a>

          <!-- Cart Icon -->
          <a href="<?= BASE_URL ?>cart.php" class="nav-icon-link" title="Keranjang Belanja" data-bs-toggle="tooltip">
            <i class="bi bi-bag"></i>
            <span class="nav-icon-badge cart-counter-badge"><?= $cartCount ?></span>
          </a>

          <!-- Account Dropdown -->
          <?php if ($currentUser): ?>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3 py-2 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: var(--border-subtle); color: var(--text-dark);">
                <i class="bi bi-person-circle fs-6 text-primary-pink"></i>
                <span class="d-none d-lg-inline fw-semibold"><?= htmlspecialchars(explode(' ', $currentUser['name'])[0]) ?></span>
              </button>
              <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3">
                <li class="px-3 py-2 border-bottom">
                  <div class="fw-bold text-dark"><?= htmlspecialchars($currentUser['name']) ?></div>
                  <small class="text-muted"><?= htmlspecialchars($currentUser['email']) ?></small>
                  <?php if (is_admin()): ?>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle d-inline-block mt-1" style="font-size:0.65rem;">Administrator</span>
                  <?php elseif (is_staff()): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle d-inline-block mt-1" style="font-size:0.65rem;">Staf Toko</span>
                  <?php endif; ?>
                </li>

                <?php if (is_admin() || is_staff()): ?>
                  <!-- Menu Khusus Admin (Hapus item belanja yang tidak relevan) -->
                  <li><a class="dropdown-item py-2 text-danger fw-semibold" href="<?= ADMIN_URL ?>index.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard Admin</a></li>
                  <li><a class="dropdown-item py-2" href="<?= ADMIN_URL ?>products.php"><i class="bi bi-box-seam me-2 text-muted"></i>Manajemen Produk</a></li>
                  <li><a class="dropdown-item py-2" href="<?= ADMIN_URL ?>orders.php"><i class="bi bi-receipt me-2 text-muted"></i>Manajemen Pesanan</a></li>
                  <li><a class="dropdown-item py-2" href="<?= ADMIN_URL ?>users.php"><i class="bi bi-people me-2 text-muted"></i>Manajemen Pengguna</a></li>
                  <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>profile.php"><i class="bi bi-person-gear me-2 text-muted"></i>Profil &amp; Sandi</a></li>
                <?php else: ?>
                  <!-- Menu Khusus Pelanggan / Customer -->
                  <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>orders.php"><i class="bi bi-box-seam me-2 text-muted"></i>Pesanan Saya</a></li>
                  <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>tracking.php"><i class="bi bi-truck me-2 text-muted"></i>Lacak Pengiriman</a></li>
                  <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>wishlist.php"><i class="bi bi-heart me-2 text-muted"></i>Favorit Saya</a></li>
                  <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>addresses.php"><i class="bi bi-geo-alt me-2 text-muted"></i>Alamat Pengiriman</a></li>
                  <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>profile.php"><i class="bi bi-person me-2 text-muted"></i>Profil Saya</a></li>
                <?php endif; ?>

                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-2 text-danger" href="<?= BASE_URL ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
              </ul>
            </div>
          <?php else: ?>
            <div class="d-flex align-items-center gap-2">
              <a href="<?= BASE_URL ?>login.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-2 fw-semibold" style="border-color: var(--border-subtle);">Masuk</a>
              <a href="<?= BASE_URL ?>register.php" class="btn btn-sm btn-nnasa py-2 px-3 fw-semibold d-none d-sm-inline-block">Daftar</a>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>

    <!-- Secondary Category Navigation Strip -->
    <div class="category-strip">
      <div class="container d-flex align-items-center">
        <a href="<?= BASE_URL ?>products.php" class="category-strip-link <?= empty($_GET['category']) && empty($_GET['promo']) ? 'active' : '' ?>">
          <i class="bi bi-grid-fill me-1"></i> Semua Produk
        </a>
        <a href="<?= BASE_URL ?>products.php?promo=1" class="category-strip-link <?= !empty($_GET['promo']) ? 'active' : '' ?> text-danger">
          <i class="bi bi-tag-fill me-1"></i> Promo & Diskon
        </a>
        <a href="<?= BASE_URL ?>products.php?sort=best_seller" class="category-strip-link">
          <i class="bi bi-fire me-1"></i> Terlaris
        </a>
        <a href="<?= BASE_URL ?>products.php?sort=newest" class="category-strip-link">
          <i class="bi bi-stars me-1"></i> Terbaru
        </a>
        <?php foreach ($navCategories as $navCat): ?>
          <a href="<?= BASE_URL ?>products.php?category=<?= urlencode($navCat['slug']) ?>" class="category-strip-link <?= (isset($_GET['category']) && $_GET['category'] === $navCat['slug']) ? 'active' : '' ?>">
            <?= htmlspecialchars($navCat['name']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </header>

  <!-- Flash Message Container -->
  <div class="container mt-3">
    <?= display_flash() ?>
  </div>
