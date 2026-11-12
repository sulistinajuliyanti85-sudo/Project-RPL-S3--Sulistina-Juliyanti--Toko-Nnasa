<?php
/**
 * Main Customer Footer Template
 * Store: nnasa_ca Accessories Marketplace
 */
$whatsapp = get_setting('contact_whatsapp', '081298765432');
$email = get_setting('contact_email', 'support@nnasaca.com');
$address = get_setting('contact_address', 'Grand Boutique Center, Jakarta Utara');
?>
  <!-- Trust Badges Section -->
  <section class="py-5 bg-white border-top border-bottom mt-5">
    <div class="container">
      <div class="row g-4">
        <div class="col-6 col-md-3">
          <div class="trust-feature-box h-100">
            <div class="trust-icon"><i class="bi bi-gem"></i></div>
            <h6 class="fw-bold mb-1">Aksesoris Premium</h6>
            <p class="text-muted small mb-0">Bahan alloy hypoallergenic tahan karat dan kilau mutiara awet.</p>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="trust-feature-box h-100">
            <div class="trust-icon"><i class="bi bi-box2-heart"></i></div>
            <h6 class="fw-bold mb-1">Kemasan Aman & Cantik</h6>
            <p class="text-muted small mb-0">Setiap pesanan dikemas dengan bubble wrap tebal dan box estetik.</p>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="trust-feature-box h-100">
            <div class="trust-icon"><i class="bi bi-truck"></i></div>
            <h6 class="fw-bold mb-1">Pengiriman Cepat</h6>
            <p class="text-muted small mb-0">Bekerjasama dengan SiCepat, JNE, dan J&T Express ke seluruh Indonesia.</p>
          </div>
        </div>
        <div class="col-6 col-md-3">
          <div class="trust-feature-box h-100">
            <div class="trust-icon"><i class="bi bi-shield-check"></i></div>
            <h6 class="fw-bold mb-1">Garansi Kepuasan</h6>
            <p class="text-muted small mb-0">Layanan komplain ramah dan penggantian jika barang diterima cacat.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Main Footer -->
  <footer class="main-footer pt-5 pb-4">
    <div class="container">
      <div class="row gy-4 mb-4">
        <!-- Brand & Description -->
        <div class="col-12 col-md-4">
          <div class="footer-brand mb-2">nnasa<span style="color: var(--dusty-rose);">_ca</span></div>
          <p class="text-secondary small mb-3">
            Toko online khusus aksesoris wanita pilihan: jepit rambut korea, scrunchie sutra, kalung rose gold, gelang serut kristal, dan paket bundling hadiah elegan.
          </p>
          <div class="d-flex gap-3 fs-5">
            <a href="https://instagram.com" target="_blank" class="text-secondary" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
            <a href="https://tiktok.com" target="_blank" class="text-secondary" aria-label="TikTok"><i class="bi bi-tiktok"></i></a>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsapp) ?>" target="_blank" class="text-secondary" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a>
          </div>
        </div>

        <!-- Quick Links -->
        <div class="col-6 col-md-2">
          <h6 class="text-white fw-bold mb-3">Menu Belanja</h6>
          <ul class="list-unstyled small">
            <li class="mb-2"><a href="<?= BASE_URL ?>products.php">Semua Produk</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>products.php?promo=1">Promo & Diskon</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>products.php?sort=best_seller">Produk Terlaris</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>products.php?sort=newest">Produk Terbaru</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>products.php?category=paket-bundling">Paket Bundling</a></li>
          </ul>
        </div>

        <!-- Customer Service -->
        <div class="col-6 col-md-3">
          <h6 class="text-white fw-bold mb-3">Bantuan & Akun</h6>
          <ul class="list-unstyled small">
            <li class="mb-2"><a href="<?= BASE_URL ?>tracking.php">Lacak Pesanan</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>orders.php">Pesanan Saya</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>wishlist.php">Favorit Saya</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>help.php">Pusat Bantuan & FAQ</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>about.php">Tentang nnasa_ca</a></li>
            <li class="mb-2"><a href="<?= BASE_URL ?>contact.php">Hubungi Kami</a></li>
          </ul>
        </div>

        <!-- Contact & Address -->
        <div class="col-12 col-md-3">
          <h6 class="text-white fw-bold mb-3">Hubungi Kami</h6>
          <p class="small text-secondary mb-2">
            <i class="bi bi-geo-alt-fill text-danger me-2"></i><?= htmlspecialchars($address) ?>
          </p>
          <p class="small text-secondary mb-2">
            <i class="bi bi-whatsapp text-success me-2"></i>WhatsApp: +62 <?= htmlspecialchars($whatsapp) ?>
          </p>
          <p class="small text-secondary mb-2">
            <i class="bi bi-envelope-fill me-2" style="color: var(--dusty-rose);"></i><?= htmlspecialchars($email) ?>
          </p>
          <div class="mt-3">
            <span class="badge bg-dark border border-secondary text-secondary me-1">BCA</span>
            <span class="badge bg-dark border border-secondary text-secondary me-1">Mandiri</span>
            <span class="badge bg-dark border border-secondary text-secondary me-1">QRIS</span>
            <span class="badge bg-dark border border-secondary text-secondary">GoPay</span>
          </div>
        </div>
      </div>

      <hr class="border-secondary my-3 opacity-25">

      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center small text-secondary">
        <div>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong>. Seluruh Hak Cipta Dilindungi.</div>
        <div class="mt-2 mt-sm-0">
          <span class="text-muted">Aksesoris Mewah &bull; Desain Orisinal &bull; Kualitas Terjamin</span>
        </div>
      </div>
    </div>
  </footer>

  <!-- Mobile Bottom Navigation (Shopee-like convenient thumb access) -->
  <nav class="mobile-bottom-nav">
    <a href="<?= BASE_URL ?>index.php" class="mobile-nav-item <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">
      <i class="bi bi-house-door<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? '-fill' : '' ?>"></i>
      <span>Beranda</span>
    </a>
    <a href="<?= BASE_URL ?>products.php" class="mobile-nav-item <?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">
      <i class="bi bi-grid<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? '-fill' : '' ?>"></i>
      <span>Katalog</span>
    </a>
    <a href="<?= BASE_URL ?>wishlist.php" class="mobile-nav-item <?= basename($_SERVER['PHP_SELF']) === 'wishlist.php' ? 'active' : '' ?>">
      <i class="bi bi-heart<?= basename($_SERVER['PHP_SELF']) === 'wishlist.php' ? '-fill' : '' ?>"></i>
      <span>Favorit</span>
      <?php if ($wishlistCount > 0): ?>
        <span class="mobile-nav-badge wishlist-counter-badge"><?= $wishlistCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>cart.php" class="mobile-nav-item <?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? 'active' : '' ?>">
      <i class="bi bi-bag<?= basename($_SERVER['PHP_SELF']) === 'cart.php' ? '-fill' : '' ?>"></i>
      <span>Keranjang</span>
      <?php if ($cartCount > 0): ?>
        <span class="mobile-nav-badge cart-counter-badge"><?= $cartCount ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= is_logged_in() ? BASE_URL . 'profile.php' : BASE_URL . 'login.php' ?>" class="mobile-nav-item <?= in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'login.php', 'orders.php']) ? 'active' : '' ?>">
      <i class="bi bi-person<?= in_array(basename($_SERVER['PHP_SELF']), ['profile.php', 'login.php']) ? '-fill' : '' ?>"></i>
      <span><?= is_logged_in() ? 'Akun' : 'Masuk' ?></span>
    </a>
  </nav>

  <!-- NNasa AI Assistant Widget (Grounded Chatbot) -->
  <?php require_once __DIR__ . '/ai_chat.php'; ?>

  <!-- Bootstrap 5.3 JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Custom App JS -->
  <script src="<?= BASE_URL ?>public/assets/js/app.js?v=<?= time() ?>"></script>

  <!-- NNasa AI Assistant JS -->
  <script src="<?= BASE_URL ?>public/assets/js/ai-chat.js?v=<?= time() ?>"></script>
</body>
</html>
