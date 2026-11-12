<?php
/**
 * Customer Wishlist Page (Favorit Saya)
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';

require_login();
$user = current_user();
$userId = $user['id'];
$db = getDB();

// Fetch wishlist products
$stmt = $db->prepare("
    SELECT w.id as wishlist_id, p.*, c.name as category_name
    FROM wishlists w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE w.user_id = ?
    ORDER BY w.id DESC
");
$stmt->execute([$userId]);
$wishlistProducts = $stmt->fetchAll();

$pageTitle = 'Favorit Saya';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Favorit Saya</li>
    </ol>
  </nav>

  <h1 class="h3 fw-bold text-dark mb-4 brand-font">Produk Favorit Saya 💕</h1>

  <?php if (empty($wishlistProducts)): ?>
    <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm">
      <i class="bi bi-heart text-muted opacity-50" style="font-size: 3.5rem;"></i>
      <h5 class="fw-bold mt-3">Belum Ada Produk Favorit</h5>
      <p class="text-muted small">Klik ikon hati pada produk yang kamu sukai untuk menyimpannya di sini.</p>
      <a href="<?= BASE_URL ?>products.php" class="btn btn-sm btn-nnasa">Jelajahi Produk</a>
    </div>
  <?php else: ?>
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4">
      <?php foreach ($wishlistProducts as $p): ?>
        <?php 
          $hasDiscount = !empty($p['discount_price']) && $p['discount_price'] > 0;
          $discountPercent = $hasDiscount ? calculate_discount_percentage((float)$p['price'], (float)$p['discount_price']) : 0;
        ?>
        <div class="col" id="wishlist-item-<?= $p['id'] ?>">
          <div class="product-card">
            <?php if ($discountPercent > 0): ?>
              <span class="product-discount-badge">-<?= $discountPercent ?>%</span>
            <?php endif; ?>

            <button type="button" class="product-wishlist-btn active btn-wishlist-toggle" data-product-id="<?= $p['id'] ?>" title="Hapus dari Favorit">
              <i class="bi bi-heart-fill"></i>
            </button>

            <a href="<?= BASE_URL ?>product-detail.php?id=<?= $p['id'] ?>" class="product-image-wrapper">
              <img src="<?= get_image_url('products', $p['main_image'], $p['name']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
            </a>

            <div class="product-body">
              <div class="product-category"><?= htmlspecialchars($p['category_name'] ?? 'Aksesoris') ?></div>
              <a href="<?= BASE_URL ?>product-detail.php?id=<?= $p['id'] ?>" class="product-title">
                <?= htmlspecialchars($p['name']) ?>
              </a>
              <div class="product-price-wrapper">
                <?php if ($hasDiscount): ?>
                  <span class="current-price"><?= format_rupiah($p['discount_price']) ?></span>
                  <span class="original-price"><?= format_rupiah($p['price']) ?></span>
                <?php else: ?>
                  <span class="current-price"><?= format_rupiah($p['price']) ?></span>
                <?php endif; ?>
              </div>

              <div class="mt-3 pt-2 border-top">
                <button type="button" class="btn btn-sm btn-nnasa-outline w-100 rounded-pill btn-add-to-cart" data-product-id="<?= $p['id'] ?>">
                  <i class="bi bi-bag-plus me-1"></i> + Keranjang
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
