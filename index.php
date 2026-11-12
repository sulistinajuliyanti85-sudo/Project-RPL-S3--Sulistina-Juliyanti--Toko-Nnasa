<?php
/**
 * Homepage (Beranda)
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/templates/header.php';

$db = getDB();

// 1. Fetch active banners
$banners = $db->query("SELECT * FROM banners WHERE status = 'active' ORDER BY sort_order ASC, id ASC")->fetchAll();

// 2. Fetch popular categories
$categories = $db->query("SELECT * FROM categories WHERE status = 'active' AND parent_id IS NULL ORDER BY id ASC LIMIT 8")->fetchAll();

// 3. Fetch Flash Sale / Promo products (with discount)
$promoProducts = $db->query("
    SELECT p.*, c.name as category_name,
    COALESCE(AVG(r.rating), 5.0) as avg_rating,
    COUNT(r.id) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'approved'
    WHERE p.status = 'active' AND p.discount_price IS NOT NULL AND p.discount_price > 0
    GROUP BY p.id
    ORDER BY p.sales_count DESC
    LIMIT 4
")->fetchAll();

// 4. Fetch Best Seller products
$bestSellers = $db->query("
    SELECT p.*, c.name as category_name,
    COALESCE(AVG(r.rating), 5.0) as avg_rating,
    COUNT(r.id) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'approved'
    WHERE p.status = 'active'
    GROUP BY p.id
    ORDER BY p.sales_count DESC
    LIMIT 8
")->fetchAll();

// 5. Fetch New Arrivals
$newArrivals = $db->query("
    SELECT p.*, c.name as category_name,
    COALESCE(AVG(r.rating), 5.0) as avg_rating,
    COUNT(r.id) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'approved'
    WHERE p.status = 'active'
    GROUP BY p.id
    ORDER BY p.id DESC
    LIMIT 8
")->fetchAll();

// 6. Fetch Featured Reviews
$featuredReviews = $db->query("
    SELECT r.*, u.name as customer_name, p.name as product_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    WHERE r.status = 'approved'
    ORDER BY r.rating DESC, r.id DESC
    LIMIT 3
")->fetchAll();
?>

<!-- Hero Banner Carousel -->
<div class="container mt-3 mb-4">
  <div id="heroCarousel" class="carousel slide hero-banner-carousel" data-bs-ride="carousel">
    <div class="carousel-indicators">
      <?php foreach ($banners as $idx => $b): ?>
        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>" aria-current="<?= $idx === 0 ? 'true' : 'false' ?>"></button>
      <?php endforeach; ?>
    </div>
    <div class="carousel-inner">
      <?php foreach ($banners as $idx => $b): ?>
        <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
          <div class="hero-banner-item" style="background-image: url('<?= get_image_url('banners', $b['image_path'], $b['title']) ?>');">
            <div class="hero-banner-overlay">
              <div class="text-white" style="max-width: 580px;">
                <span class="badge rounded-pill bg-light text-dark px-3 py-2 mb-3 fw-bold shadow-sm">
                  <i class="bi bi-gem text-primary-pink me-1"></i> KOLEKSI TERBARU
                </span>
                <h1 class="brand-font fw-bold display-6 mb-2"><?= htmlspecialchars($b['title']) ?></h1>
                <p class="lead fs-6 text-white-50 mb-4"><?= htmlspecialchars($b['subtitle']) ?></p>
                <a href="<?= BASE_URL . htmlspecialchars($b['target_url']) ?>" class="btn btn-nnasa px-4 py-2">
                  Belanja Sekarang <i class="bi bi-arrow-right ms-2"></i>
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Sebelumnya</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Selanjutnya</span>
    </button>
  </div>
</div>

<!-- Category Circles Section -->
<section class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="h4 brand-font fw-bold mb-1">Kategori Pilihan</h2>
      <p class="text-muted small mb-0">Temukan sentuhan pesona terbaik sesuai gayamu</p>
    </div>
    <a href="<?= BASE_URL ?>products.php" class="text-decoration-none fw-semibold small text-primary-pink">
      Lihat Semua <i class="bi bi-chevron-right"></i>
    </a>
  </div>

  <div class="row row-cols-3 row-cols-sm-4 row-cols-md-6 row-cols-lg-8 g-3 justify-content-center">
    <?php foreach ($categories as $cat): ?>
      <div class="col">
        <a href="<?= BASE_URL ?>products.php?category=<?= urlencode($cat['slug']) ?>" class="cat-circle-card">
          <div class="cat-circle-thumb">
            <img src="<?= get_image_url('categories', $cat['image'], $cat['name']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>" loading="lazy">
          </div>
          <div class="cat-circle-title"><?= htmlspecialchars($cat['name']) ?></div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Flash Sale / Promo Section -->
<?php if (!empty($promoProducts)): ?>
<section class="container my-5">
  <div class="p-4 rounded-4" style="background: linear-gradient(135deg, #fcebeb 0%, #fff7f7 100%); border: 1px solid #f2d8da;">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-danger px-3 py-2 fs-6 rounded-pill"><i class="bi bi-lightning-charge-fill me-1"></i> FLASH SALE</span>
        <h3 class="h4 brand-font fw-bold mb-0 text-dark">Promo Aksesoris Spesial</h3>
      </div>
      <a href="<?= BASE_URL ?>products.php?promo=1" class="btn btn-sm btn-nnasa-outline">
        Semua Promo <i class="bi bi-arrow-right ms-1"></i>
      </a>
    </div>

    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
      <?php foreach ($promoProducts as $p): ?>
        <?php $discountPercent = calculate_discount_percentage((float)$p['price'], (float)$p['discount_price']); ?>
        <div class="col">
          <div class="product-card">
            <?php if ($discountPercent > 0): ?>
              <span class="product-discount-badge">-<?= $discountPercent ?>%</span>
            <?php endif; ?>

            <button type="button" class="product-wishlist-btn btn-wishlist-toggle" data-product-id="<?= $p['id'] ?>" title="Tambah ke Favorit">
              <i class="bi bi-heart"></i>
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
                <span class="current-price"><?= format_rupiah($p['discount_price']) ?></span>
                <span class="original-price"><?= format_rupiah($p['price']) ?></span>
              </div>
              <div class="product-meta">
                <span class="star-rating">
                  <i class="bi bi-star-fill"></i> <?= number_format((float)$p['avg_rating'], 1) ?>
                </span>
                <span>Terjual <?= $p['sales_count'] ?></span>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Best Sellers Grid -->
<section class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="h4 brand-font fw-bold mb-1">Produk Terlaris <i class="bi bi-fire text-danger"></i></h2>
      <p class="text-muted small mb-0">Aksesoris favorit pelanggan dengan ulasan terbaik</p>
    </div>
    <a href="<?= BASE_URL ?>products.php?sort=best_seller" class="text-decoration-none fw-semibold small text-primary-pink">
      Lihat Semua <i class="bi bi-chevron-right"></i>
    </a>
  </div>

  <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4">
    <?php foreach ($bestSellers as $p): ?>
      <?php 
        $hasDiscount = !empty($p['discount_price']) && $p['discount_price'] > 0;
        $discountPercent = $hasDiscount ? calculate_discount_percentage((float)$p['price'], (float)$p['discount_price']) : 0;
      ?>
      <div class="col">
        <div class="product-card">
          <?php if ($discountPercent > 0): ?>
            <span class="product-discount-badge">-<?= $discountPercent ?>%</span>
          <?php endif; ?>

          <button type="button" class="product-wishlist-btn btn-wishlist-toggle" data-product-id="<?= $p['id'] ?>" title="Tambah ke Favorit">
            <i class="bi bi-heart"></i>
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
            <div class="product-meta">
              <span class="star-rating">
                <i class="bi bi-star-fill"></i> <?= number_format((float)$p['avg_rating'], 1) ?>
              </span>
              <span>Terjual <?= $p['sales_count'] ?></span>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Promotional Banner Break: Bundling Gift -->
<section class="container my-5">
  <div class="p-4 p-md-5 rounded-4 text-white position-relative overflow-hidden shadow-sm" style="background: var(--rose-gold-gradient);">
    <div class="row align-items-center position-relative z-1">
      <div class="col-md-8">
        <span class="badge bg-white text-dark px-3 py-2 rounded-pill fw-bold mb-3">EXCLUSIVE HAMPERS</span>
        <h2 class="display-6 brand-font fw-bold mb-2">Paket Bundling Kado Cantik</h2>
        <p class="lead fs-6 text-white-50 mb-4">
          Lengkap dengan kalung, gelang, anting mutiara, kartu ucapan, dan hardbox beludru rose gold mewah. Siap kirim untuk kejutan orang tersayang.
        </p>
        <a href="<?= BASE_URL ?>products.php?category=paket-bundling" class="btn btn-light text-dark fw-bold rounded-pill px-4 py-2">
          Pesan Bundling Box <i class="bi bi-gift-fill text-danger ms-1"></i>
        </a>
      </div>
      <div class="col-md-4 text-center mt-4 mt-md-0 d-none d-md-block">
        <i class="bi bi-gift text-white opacity-25" style="font-size: 8rem;"></i>
      </div>
    </div>
  </div>
</section>

<!-- New Arrivals Grid -->
<section class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="h4 brand-font fw-bold mb-1">Koleksi Terbaru <i class="bi bi-stars text-warning"></i></h2>
      <p class="text-muted small mb-0">Tren perhiasan dan aksesoris fashion terkini</p>
    </div>
    <a href="<?= BASE_URL ?>products.php?sort=newest" class="text-decoration-none fw-semibold small text-primary-pink">
      Lihat Semua <i class="bi bi-chevron-right"></i>
    </a>
  </div>

  <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4">
    <?php foreach ($newArrivals as $p): ?>
      <?php 
        $hasDiscount = !empty($p['discount_price']) && $p['discount_price'] > 0;
        $discountPercent = $hasDiscount ? calculate_discount_percentage((float)$p['price'], (float)$p['discount_price']) : 0;
      ?>
      <div class="col">
        <div class="product-card">
          <?php if ($discountPercent > 0): ?>
            <span class="product-discount-badge">-<?= $discountPercent ?>%</span>
          <?php endif; ?>

          <button type="button" class="product-wishlist-btn btn-wishlist-toggle" data-product-id="<?= $p['id'] ?>" title="Tambah ke Favorit">
            <i class="bi bi-heart"></i>
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
            <div class="product-meta">
              <span class="star-rating">
                <i class="bi bi-star-fill"></i> <?= number_format((float)$p['avg_rating'], 1) ?>
              </span>
              <span>Stok: <?= $p['stock'] ?></span>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- Customer Reviews Testimonials -->
<?php if (!empty($featuredReviews)): ?>
<section class="container my-5">
  <div class="text-center mb-4">
    <h2 class="h4 brand-font fw-bold mb-1">Apa Kata Pelanggan nnasa_ca</h2>
    <p class="text-muted small">Ulasan nyata dari pembeli yang telah mempercayakan aksesorisnya pada kami</p>
  </div>

  <div class="row g-4 justify-content-center">
    <?php foreach ($featuredReviews as $r): ?>
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm rounded-4 p-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <div class="d-flex align-items-center mb-3">
            <div class="me-3">
              <span class="badge rounded-circle p-2 text-white" style="background: var(--primary-pink); width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                <?= strtoupper(substr($r['customer_name'], 0, 1)) ?>
              </span>
            </div>
            <div>
              <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($r['customer_name']) ?></h6>
              <div class="star-rating small">
                <?php for ($s = 1; $s <= 5; $s++): ?>
                  <i class="bi bi-star<?= $s <= $r['rating'] ? '-fill' : '' ?>"></i>
                <?php endfor; ?>
              </div>
            </div>
          </div>
          <p class="text-muted small mb-2 fst-italic">"<?= htmlspecialchars($r['review_text']) ?>"</p>
          <div class="mt-auto pt-2 border-top">
            <small class="text-muted"><i class="bi bi-bag-check me-1 text-primary-pink"></i> <?= htmlspecialchars($r['product_name']) ?></small>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
