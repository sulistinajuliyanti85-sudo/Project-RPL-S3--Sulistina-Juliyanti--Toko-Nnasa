<?php
/**
 * Product Detail Page
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';

$productId = (int)($_GET['id'] ?? 0);
$db = getDB();

// Increment views_count
$db->prepare("UPDATE products SET views_count = views_count + 1 WHERE id = ?")->execute([$productId]);

// Fetch product with category
$stmt = $db->prepare("
    SELECT p.*, c.name as category_name, c.slug as category_slug
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.status = 'active'
    LIMIT 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('danger', 'Produk tidak ditemukan atau telah dinonaktifkan.');
    header('Location: ' . BASE_URL . 'products.php');
    exit;
}

$pageTitle = $product['name'];
require_once __DIR__ . '/templates/header.php';

// Fetch gallery images
$stmtImgs = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC");
$stmtImgs->execute([$productId]);
$galleryImages = $stmtImgs->fetchAll(PDO::FETCH_COLUMN);

// Fetch variants
$stmtVars = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? AND status = 'active' ORDER BY id ASC");
$stmtVars->execute([$productId]);
$variants = $stmtVars->fetchAll();

// Fetch reviews
$stmtRev = $db->prepare("
    SELECT r.*, u.name as customer_name
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ? AND r.status = 'approved'
    ORDER BY r.id DESC
");
$stmtRev->execute([$productId]);
$reviews = $stmtRev->fetchAll();

$avgRating = 5.0;
if (count($reviews) > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($sum / count($reviews), 1);
}

// Fetch related products
$stmtRel = $db->prepare("
    SELECT * FROM products 
    WHERE category_id = ? AND id != ? AND status = 'active' 
    ORDER BY sales_count DESC 
    LIMIT 4
");
$stmtRel->execute([$product['category_id'], $productId]);
$relatedProducts = $stmtRel->fetchAll();

$hasDiscount = !empty($product['discount_price']) && $product['discount_price'] > 0;
$currentPrice = $hasDiscount ? (float)$product['discount_price'] : (float)$product['price'];
$discountPercent = $hasDiscount ? calculate_discount_percentage((float)$product['price'], (float)$product['discount_price']) : 0;
?>

<div class="container my-4">
  <!-- Breadcrumbs -->
  <nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>products.php" class="text-decoration-none text-muted">Katalog</a></li>
      <?php if (!empty($product['category_name'])): ?>
        <li class="breadcrumb-item"><a href="<?= BASE_URL ?>products.php?category=<?= urlencode($product['category_slug']) ?>" class="text-decoration-none text-muted"><?= htmlspecialchars($product['category_name']) ?></a></li>
      <?php endif; ?>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
    </ol>
  </nav>

  <div class="row g-4 g-lg-5">
    <!-- Image Gallery -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden p-2" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <!-- Main Big Image -->
        <div class="position-relative text-center bg-light rounded-3 overflow-hidden" style="min-height: 420px; display: flex; align-items: center; justify-content: center;">
          <img id="mainDetailImage" src="<?= get_image_url('products', $product['main_image'], $product['name']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-fluid" style="max-height: 480px; width: 100%; object-fit: contain;">
          
          <?php if ($discountPercent > 0): ?>
            <span class="product-discount-badge fs-6 px-3 py-2">-<?= $discountPercent ?>%</span>
          <?php endif; ?>

          <button type="button" class="product-wishlist-btn btn-wishlist-toggle" data-product-id="<?= $product['id'] ?>" title="Tambah ke Favorit">
            <i class="bi bi-heart"></i>
          </button>
        </div>

        <!-- Thumbnails -->
        <?php if (!empty($galleryImages)): ?>
          <div class="d-flex gap-2 mt-3 px-2 overflow-auto py-1">
            <div class="thumb-box border rounded-3 p-1 active" style="width: 70px; height: 70px; cursor: pointer; border-color: var(--primary-pink) !important;" onclick="switchMainImage('<?= get_image_url('products', $product['main_image'], $product['name']) ?>', this)">
              <img src="<?= get_image_url('products', $product['main_image'], $product['name']) ?>" class="w-100 h-100 object-fit-cover rounded-2">
            </div>
            <?php foreach ($galleryImages as $img): ?>
              <div class="thumb-box border rounded-3 p-1" style="width: 70px; height: 70px; cursor: pointer;" onclick="switchMainImage('<?= get_image_url('products', $img, $product['name']) ?>', this)">
                <img src="<?= get_image_url('products', $img, $product['name']) ?>" class="w-100 h-100 object-fit-cover rounded-2">
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Product Info & Actions -->
    <div class="col-lg-6">
      <div class="ps-lg-2">
        <span class="badge bg-light text-primary-pink border px-3 py-1 rounded-pill small fw-bold mb-2">
          <?= htmlspecialchars($product['category_name'] ?? 'Aksesoris') ?>
        </span>

        <h1 class="h3 fw-bold text-dark mb-2 brand-font"><?= htmlspecialchars($product['name']) ?></h1>
        
        <div class="d-flex align-items-center gap-3 mb-3 small">
          <div class="d-flex align-items-center text-warning">
            <i class="bi bi-star-fill me-1"></i>
            <span class="fw-bold text-dark"><?= $avgRating ?></span>
            <span class="text-muted ms-1">(<?= count($reviews) ?> ulasan)</span>
          </div>
          <span class="text-muted">&bull;</span>
          <span class="text-muted">Terjual <strong><?= $product['sales_count'] ?></strong></span>
          <span class="text-muted">&bull;</span>
          <span class="text-muted">SKU: <code><?= htmlspecialchars($product['sku']) ?></code></span>
        </div>

        <!-- Price Box -->
        <div class="p-3 rounded-4 mb-4" style="background: linear-gradient(135deg, #fbf2f2 0%, #fff7f7 100%); border: 1px solid #f2d8da;">
          <div class="d-flex align-items-baseline gap-2">
            <span class="h2 fw-bold text-primary-pink mb-0" id="displayPrice"><?= format_rupiah($currentPrice) ?></span>
            <?php if ($hasDiscount): ?>
              <span class="text-decoration-line-through text-muted fs-6"><?= format_rupiah($product['price']) ?></span>
              <span class="badge bg-danger rounded-pill px-2 py-1 small">Hemat <?= $discountPercent ?>%</span>
            <?php endif; ?>
          </div>
          <small class="text-muted d-block mt-1"><i class="bi bi-shield-check text-success me-1"></i> Jaminan 100% Produk Original nnasa_ca</small>
        </div>

        <!-- Variants Selection (if any) -->
        <?php if (!empty($variants)): ?>
          <div class="mb-4">
            <label class="form-label fw-bold small text-dark d-block">
              Pilihan Varian: <span id="selectedVariantLabel" class="text-primary-pink fw-normal"><?= htmlspecialchars($variants[0]['variant_value']) ?></span>
            </label>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($variants as $idx => $v): ?>
                <div class="variant-pill <?= $idx === 0 ? 'active' : '' ?>" 
                     data-variant-id="<?= $v['id'] ?>"
                     data-variant-name="<?= htmlspecialchars($v['variant_value']) ?>"
                     data-price-adj="<?= (float)$v['price_adjustment'] ?>"
                     data-stock="<?= (int)$v['stock'] ?>"
                     onclick="selectVariant(this)">
                  <?= htmlspecialchars($v['variant_value']) ?>
                  <?php if ((float)$v['price_adjustment'] > 0): ?>
                    <small>(+<?= format_rupiah($v['price_adjustment']) ?>)</small>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Quantity & Stock -->
        <div class="mb-4">
          <label class="form-label fw-bold small text-dark d-block">Kuantitas</label>
          <div class="d-flex align-items-center gap-3">
            <div class="quantity-stepper">
              <button type="button" class="btn-minus"><i class="bi bi-dash"></i></button>
              <input type="number" id="detail-quantity-input" name="quantity" class="qty-input" value="1" min="1" max="<?= $product['stock'] ?>" data-product-id="<?= $product['id'] ?>" readonly>
              <button type="button" class="btn-plus"><i class="bi bi-plus"></i></button>
            </div>
            <div class="small text-muted">
              Tersisa <strong id="stockDisplay" class="text-dark"><?= $product['stock'] ?></strong> buah
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-3 mb-4">
          <button type="button" class="btn btn-nnasa-outline flex-grow-1 py-3 px-4 fw-bold rounded-pill btn-add-to-cart" data-product-id="<?= $product['id'] ?>">
            <i class="bi bi-bag-plus me-2"></i> Tambah ke Keranjang
          </button>
          <button type="button" class="btn btn-nnasa flex-grow-1 py-3 px-4 fw-bold rounded-pill" onclick="buyNow(<?= $product['id'] ?>)">
            Beli Sekarang <i class="bi bi-lightning-fill ms-1"></i>
          </button>
        </div>

        <!-- Description Tab / Box -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mt-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <h5 class="fw-bold mb-3 fs-6"><i class="bi bi-info-circle me-1 text-primary-pink"></i> Deskripsi Produk</h5>
          <div class="text-muted small leading-relaxed" style="white-space: pre-line;"><?= htmlspecialchars($product['description'] ?? 'Tidak ada deskripsi.') ?></div>
          
          <hr class="my-3 opacity-25">
          <div class="row g-2 small text-muted">
            <div class="col-6"><strong>Berat:</strong> <?= $product['weight'] ?> gram</div>
            <div class="col-6"><strong>Kondisi:</strong> Baru 100%</div>
            <div class="col-6"><strong>Pengiriman Dari:</strong> Jakarta Utara</div>
            <div class="col-6"><strong>Asuransi:</strong> Opsional</div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Reviews Section -->
  <section class="mt-5 pt-4 border-top">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h3 class="h4 brand-font fw-bold mb-1">Ulasan Pelanggan (<?= count($reviews) ?>)</h3>
        <p class="text-muted small mb-0">Penilaian pembeli yang telah memiliki produk ini</p>
      </div>
      <div class="text-end">
        <span class="h3 fw-bold text-dark"><?= $avgRating ?></span>
        <span class="text-muted"> / 5.0</span>
        <div class="star-rating small">
          <?php for ($s = 1; $s <= 5; $s++): ?>
            <i class="bi bi-star<?= $s <= round($avgRating) ? '-fill' : '' ?>"></i>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <?php if (empty($reviews)): ?>
      <div class="text-center py-4 bg-white rounded-4 border p-4 text-muted small">
        <i class="bi bi-chat-heart fs-3 text-primary-pink d-block mb-2"></i>
        Belum ada ulasan untuk produk ini. Jadilah yang pertama memberikan ulasan setelah berbelanja!
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($reviews as $rev): ?>
          <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 h-100" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-bold text-dark small"><i class="bi bi-person-circle me-1 text-primary-pink"></i> <?= htmlspecialchars($rev['customer_name']) ?></div>
                <div class="star-rating small">
                  <?php for ($s = 1; $s <= 5; $s++): ?>
                    <i class="bi bi-star<?= $s <= $rev['rating'] ? '-fill' : '' ?>"></i>
                  <?php endfor; ?>
                </div>
              </div>
              <p class="text-muted small mb-2"><?= htmlspecialchars($rev['review_text']) ?></p>
              <small class="text-muted" style="font-size: 0.72rem;"><?= date('d M Y, H:i', strtotime($rev['created_at'])) ?></small>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Related Products Section -->
  <?php if (!empty($relatedProducts)): ?>
  <section class="mt-5 pt-4 border-top">
    <h3 class="h4 brand-font fw-bold mb-4">Produk Terkait Lainnya</h3>
    <div class="row row-cols-2 row-cols-md-4 g-3 g-md-4">
      <?php foreach ($relatedProducts as $rel): ?>
        <?php 
          $relDiscount = !empty($rel['discount_price']) && $rel['discount_price'] > 0;
          $relPercent = $relDiscount ? calculate_discount_percentage((float)$rel['price'], (float)$rel['discount_price']) : 0;
        ?>
        <div class="col">
          <div class="product-card">
            <?php if ($relPercent > 0): ?>
              <span class="product-discount-badge">-<?= $relPercent ?>%</span>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>product-detail.php?id=<?= $rel['id'] ?>" class="product-image-wrapper">
              <img src="<?= get_image_url('products', $rel['main_image'], $rel['name']) ?>" alt="<?= htmlspecialchars($rel['name']) ?>" loading="lazy">
            </a>
            <div class="product-body">
              <a href="<?= BASE_URL ?>product-detail.php?id=<?= $rel['id'] ?>" class="product-title">
                <?= htmlspecialchars($rel['name']) ?>
              </a>
              <div class="product-price-wrapper">
                <span class="current-price"><?= format_rupiah($relDiscount ? $rel['discount_price'] : $rel['price']) ?></span>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</div>

<script>
  function switchMainImage(url, element) {
    document.getElementById('mainDetailImage').src = url;
    document.querySelectorAll('.thumb-box').forEach(box => {
      box.style.borderColor = 'var(--border-subtle)';
      box.classList.remove('active');
    });
    element.style.borderColor = 'var(--primary-pink)';
    element.classList.add('active');
  }

  const basePrice = <?= $currentPrice ?>;
  function selectVariant(element) {
    document.querySelectorAll('.variant-pill').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
    
    const varName = element.getAttribute('data-variant-name');
    const priceAdj = parseFloat(element.getAttribute('data-price-adj')) || 0;
    const stock = parseInt(element.getAttribute('data-stock')) || 0;
    
    document.getElementById('selectedVariantLabel').innerText = varName;
    document.getElementById('stockDisplay').innerText = stock;
    
    const qtyInput = document.getElementById('detail-quantity-input');
    qtyInput.max = stock;
    if (parseInt(qtyInput.value) > stock) {
      qtyInput.value = Math.max(1, stock);
    }

    const newPrice = basePrice + priceAdj;
    document.getElementById('displayPrice').innerText = 'Rp ' + newPrice.toLocaleString('id-ID');
  }

  function buyNow(productId) {
    const qtyInput = document.getElementById('detail-quantity-input');
    const quantity = parseInt(qtyInput.value) || 1;
    let variantId = null;
    const activeVar = document.querySelector('.variant-pill.active');
    if (activeVar) {
      variantId = activeVar.getAttribute('data-variant-id');
    }

    fetch(window.BASE_URL + 'api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'add',
        product_id: productId,
        variant_id: variantId,
        quantity: quantity
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.status === 'unauthorized') {
        window.location.href = window.BASE_URL + 'login.php';
        return;
      }
      if (data.success) {
        window.location.href = window.BASE_URL + 'cart.php';
      } else {
        alert(data.message || 'Gagal menambahkan produk');
      }
    })
    .catch(err => {
      console.error(err);
      alert('Terjadi kesalahan koneksi.');
    });
  }
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
