<?php
/**
 * Admin Panel Header & Sidebar Template
 * Store: nnasa_ca Accessories Marketplace
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

require_admin();
$adminUser = current_user();
$currentScript = basename($_SERVER['PHP_SELF']);
$adminPageTitle = $adminPageTitle ?? 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($adminPageTitle) ?> — Panel Admin <?= APP_NAME ?></title>
  
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>public/assets/images/logo-icon.svg">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap 5.3 & Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <!-- Chart.js CDN for Analytics -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- Admin & Global Styles -->
  <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/admin.css?v=<?= time() ?>">
</head>
<body class="admin-body">

  <!-- Collapsible Sidebar -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="admin-sidebar-brand">
      <a href="<?= ADMIN_URL ?>index.php">nnasa<span>_ca</span> <small style="font-size:0.6rem; text-transform:uppercase; letter-spacing:1px; color:#c27d86;">Admin</small></a>
      <button class="btn btn-sm btn-link text-white d-lg-none" type="button" onclick="toggleAdminSidebar()"><i class="bi bi-x-lg"></i></button>
    </div>

    <!-- 18 Sidebar Menu Items -->
    <div class="py-2">
      <div class="admin-nav-section-title">Ringkasan</div>
      <a href="<?= ADMIN_URL ?>index.php" class="admin-nav-item <?= $currentScript === 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>

      <div class="admin-nav-section-title">Katalog & Produk</div>
      <a href="<?= ADMIN_URL ?>products.php" class="admin-nav-item <?= in_array($currentScript, ['products.php', 'product-form.php']) ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Manajemen Produk
      </a>
      <a href="<?= ADMIN_URL ?>categories.php" class="admin-nav-item <?= $currentScript === 'categories.php' ? 'active' : '' ?>">
        <i class="bi bi-tags"></i> Kategori Produk
      </a>
      <a href="<?= ADMIN_URL ?>variants.php" class="admin-nav-item <?= $currentScript === 'variants.php' ? 'active' : '' ?>">
        <i class="bi bi-layers"></i> Varian Produk
      </a>
      <a href="<?= ADMIN_URL ?>stock.php" class="admin-nav-item <?= $currentScript === 'stock.php' ? 'active' : '' ?>">
        <i class="bi bi-boxes"></i> Manajemen Stok
      </a>

      <div class="admin-nav-section-title">Pesanan & Transaksi</div>
      <a href="<?= ADMIN_URL ?>orders.php" class="admin-nav-item <?= in_array($currentScript, ['orders.php', 'order-detail.php']) ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i> Manajemen Pesanan
      </a>
      <a href="<?= ADMIN_URL ?>payments.php" class="admin-nav-item <?= $currentScript === 'payments.php' ? 'active' : '' ?>">
        <i class="bi bi-credit-card"></i> Pembayaran
      </a>
      <a href="<?= ADMIN_URL ?>shipments.php" class="admin-nav-item <?= $currentScript === 'shipments.php' ? 'active' : '' ?>">
        <i class="bi bi-truck"></i> Pengiriman & Resi
      </a>

      <div class="admin-nav-section-title">Pelanggan & Konten</div>
      <a href="<?= ADMIN_URL ?>customers.php" class="admin-nav-item <?= $currentScript === 'customers.php' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Pelanggan
      </a>
      <a href="<?= ADMIN_URL ?>reviews.php" class="admin-nav-item <?= $currentScript === 'reviews.php' ? 'active' : '' ?>">
        <i class="bi bi-chat-heart"></i> Ulasan Produk
      </a>
      <a href="<?= ADMIN_URL ?>coupons.php" class="admin-nav-item <?= $currentScript === 'coupons.php' ? 'active' : '' ?>">
        <i class="bi bi-ticket-perforated"></i> Voucher & Promo
      </a>
      <a href="<?= ADMIN_URL ?>banners.php" class="admin-nav-item <?= $currentScript === 'banners.php' ? 'active' : '' ?>">
        <i class="bi bi-images"></i> Banner Beranda
      </a>

      <div class="admin-nav-section-title">Analitik & Laporan</div>
      <a href="<?= ADMIN_URL ?>reports.php" class="admin-nav-item <?= $currentScript === 'reports.php' ? 'active' : '' ?>">
        <i class="bi bi-graph-up-arrow"></i> Laporan Penjualan
      </a>

      <div class="admin-nav-section-title">Modul AI & Keamanan</div>
      <a href="<?= ADMIN_URL ?>ai-config.php" class="admin-nav-item <?= $currentScript === 'ai-config.php' ? 'active' : '' ?>">
        <i class="bi bi-robot"></i> Konfigurasi AI
      </a>
      <a href="<?= ADMIN_URL ?>whatsapp-otp.php" class="admin-nav-item <?= $currentScript === 'whatsapp-otp.php' ? 'active' : '' ?>">
        <i class="bi bi-whatsapp"></i> Konfigurasi OTP WhatsApp
      </a>
      <a href="<?= ADMIN_URL ?>otp-config.php" class="admin-nav-item <?= $currentScript === 'otp-config.php' ? 'active' : '' ?>">
        <i class="bi bi-shield-lock"></i> Konfigurasi OTP
      </a>

      <div class="admin-nav-section-title">Sistem & Pengaturan</div>
      <a href="<?= ADMIN_URL ?>settings.php" class="admin-nav-item <?= $currentScript === 'settings.php' ? 'active' : '' ?>">
        <i class="bi bi-gear"></i> Pengaturan Toko
      </a>
      <a href="<?= ADMIN_URL ?>users.php" class="admin-nav-item <?= $currentScript === 'users.php' ? 'active' : '' ?>">
        <i class="bi bi-people-fill"></i> Manajemen Pengguna (User)
      </a>
      <a href="<?= ADMIN_URL ?>activity-logs.php" class="admin-nav-item <?= $currentScript === 'activity-logs.php' ? 'active' : '' ?>">
        <i class="bi bi-journal-text"></i> Log Aktivitas
      </a>

      <div class="admin-nav-section-title">Keluar</div>
      <a href="<?= ADMIN_URL ?>logout.php" class="admin-nav-item text-danger">
        <i class="bi bi-box-arrow-left text-danger"></i> Logout
      </a>
    </div>
  </aside>

  <!-- Main Content Wrap -->
  <div class="admin-main">
    
    <!-- Topbar -->
    <header class="admin-topbar">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-light border d-lg-none" type="button" onclick="toggleAdminSidebar()">
          <i class="bi bi-list fs-5"></i>
        </button>
        <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($adminPageTitle) ?></span>
      </div>

      <div class="d-flex align-items-center gap-3">
        <a href="<?= BASE_URL ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3" title="Kunjungi Website Toko">
          <i class="bi bi-shop me-1"></i> <span class="d-none d-sm-inline">Lihat Toko</span>
        </a>

        <div class="dropdown">
          <button class="btn btn-sm btn-light border rounded-pill px-3 py-1 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle text-primary-pink"></i>
            <span class="fw-semibold small"><?= htmlspecialchars($adminUser['name']) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-3 small">
            <li><a class="dropdown-item py-2" href="<?= ADMIN_URL ?>settings.php"><i class="bi bi-gear me-2 text-muted"></i>Pengaturan</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item py-2 text-danger" href="<?= ADMIN_URL ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
          </ul>
        </div>
      </div>
    </header>

    <!-- Page Content Container -->
    <main class="admin-content">
      <?= display_flash() ?>
