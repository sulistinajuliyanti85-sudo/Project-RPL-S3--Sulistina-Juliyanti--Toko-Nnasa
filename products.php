<?php
/**
 * Catalog & Search Page (Semua Produk)
 * Store: nnasa_ca Accessories Marketplace
 */

$pageTitle = 'Katalog Aksesoris & Perhiasan';
require_once __DIR__ . '/templates/header.php';

$db = getDB();

// Input parameters
$search = trim($_GET['q'] ?? '');
$categorySlug = trim($_GET['category'] ?? '');
$promoOnly = !empty($_GET['promo']);
$inStockOnly = !empty($_GET['in_stock']);
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$sort = $_GET['sort'] ?? 'newest';

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$where = ["p.status = 'active'"];
$params = [];

if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($categorySlug !== '') {
    $where[] = "(c.slug = ? OR c.parent_id = (SELECT id FROM categories WHERE slug = ? LIMIT 1))";
    $params[] = $categorySlug;
    $params[] = $categorySlug;
}

if ($promoOnly) {
    $where[] = "p.discount_price IS NOT NULL AND p.discount_price > 0";
}

if ($inStockOnly) {
    $where[] = "p.stock > 0";
}

if ($minPrice !== null) {
    $where[] = "COALESCE(p.discount_price, p.price) >= ?";
    $params[] = $minPrice;
}

if ($maxPrice !== null) {
    $where[] = "COALESCE(p.discount_price, p.price) <= ?";
    $params[] = $maxPrice;
}

$whereClause = implode(' AND ', $where);

// Sorting
$orderBy = match($sort) {
    'price_asc' => 'COALESCE(p.discount_price, p.price) ASC',
    'price_desc' => 'COALESCE(p.discount_price, p.price) DESC',
    'best_seller' => 'p.sales_count DESC',
    'name_asc' => 'p.name ASC',
    default => 'p.id DESC'
};

// Count total items
$countSql = "
    SELECT COUNT(DISTINCT p.id) 
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$whereClause}
";
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$totalProducts = (int)$stmtCount->fetchColumn();
$totalPages = ceil($totalProducts / $limit);

// Fetch products
$sql = "
    SELECT p.*, c.name as category_name, c.slug as category_slug,
    COALESCE(AVG(r.rating), 5.0) as avg_rating,
    COUNT(r.id) as review_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN reviews r ON p.id = r.product_id AND r.status = 'approved'
    WHERE {$whereClause}
    GROUP BY p.id
    ORDER BY {$orderBy}
    LIMIT {$limit} OFFSET {$offset}
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Fetch all categories for sidebar filter
$allCategories = $db->query("SELECT id, name, slug FROM categories WHERE status = 'active' ORDER BY name ASC")->fetchAll();
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Katalog Produk</li>
      <?php if ($categorySlug): ?>
        <li class="breadcrumb-item active text-primary-pink"><?= htmlspecialchars(ucwords(str_replace('-', ' ', $categorySlug))) ?></li>
      <?php endif; ?>
    </ol>
  </nav>

  <div class="row g-4">
    <!-- Filter Sidebar (Desktop) -->
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 80px; background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold mb-0 text-dark fs-6"><i class="bi bi-funnel me-1 text-primary-pink"></i> Filter Produk</h5>
          <a href="<?= BASE_URL ?>products.php" class="small text-muted text-decoration-none">Reset</a>
        </div>

        <form action="<?= BASE_URL ?>products.php" method="GET">
          <?php if ($search): ?>
            <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">
          <?php endif; ?>
          <?php if ($sort): ?>
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
          <?php endif; ?>

          <!-- Kategori -->
          <div class="mb-4">
            <label class="form-label fw-bold small text-muted text-uppercase">Kategori</label>
            <div class="d-flex flex-column gap-1" style="max-height: 200px; overflow-y: auto;">
              <a href="<?= BASE_URL ?>products.php<?= $search ? '?q='.urlencode($search) : '' ?>" class="small py-1 text-decoration-none <?= empty($categorySlug) ? 'text-primary-pink fw-bold' : 'text-dark' ?>">
                Semua Kategori
              </a>
              <?php foreach ($allCategories as $c): ?>
                <a href="<?= BASE_URL ?>products.php?category=<?= urlencode($c['slug']) ?><?= $search ? '&q='.urlencode($search) : '' ?>" class="small py-1 text-decoration-none <?= $categorySlug === $c['slug'] ? 'text-primary-pink fw-bold' : 'text-muted' ?>">
                  <?= htmlspecialchars($c['name']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </div>

          <hr class="my-3 opacity-25">

          <!-- Rentang Harga -->
          <div class="mb-4">
            <label class="form-label fw-bold small text-muted text-uppercase">Rentang Harga (Rp)</label>
            <div class="row g-2">
              <div class="col-6">
                <input type="number" name="min_price" class="form-control form-control-sm rounded-3" placeholder="Min" value="<?= $minPrice !== null ? $minPrice : '' ?>">
              </div>
              <div class="col-6">
                <input type="number" name="max_price" class="form-control form-control-sm rounded-3" placeholder="Max" value="<?= $maxPrice !== null ? $maxPrice : '' ?>">
              </div>
            </div>
          </div>

          <hr class="my-3 opacity-25">

          <!-- Ketersediaan & Promo -->
          <div class="mb-4">
            <label class="form-label fw-bold small text-muted text-uppercase">Kondisi</label>
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="promo" value="1" id="promoCheck" <?= $promoOnly ? 'checked' : '' ?>>
              <label class="form-check-label small" for="promoCheck">
                Diskon & Promo Saja
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="stockCheck" <?= $inStockOnly ? 'checked' : '' ?>>
              <label class="form-check-label small" for="stockCheck">
                Stok Tersedia
              </label>
            </div>
          </div>

          <?php if ($categorySlug): ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($categorySlug) ?>">
          <?php endif; ?>

          <button type="submit" class="btn btn-sm btn-nnasa w-100 py-2">
            Terapkan Filter
          </button>
        </form>
      </div>
    </div>

    <!-- Product Grid Area -->
    <div class="col-lg-9">
      <!-- Header bar with result count & sorting -->
      <div class="card border-0 shadow-sm rounded-4 p-3 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
          <div>
            <span class="text-muted small">Menampilkan <strong><?= count($products) ?></strong> dari <strong><?= $totalProducts ?></strong> produk</span>
            <?php if ($search): ?>
              <span class="ms-2 badge bg-light text-dark border">Pencarian: "<?= htmlspecialchars($search) ?>"</span>
            <?php endif; ?>
          </div>

          <!-- Sorting Dropdown -->
          <div class="d-flex align-items-center gap-2">
            <span class="small text-muted text-nowrap">Urutkan:</span>
            <select class="form-select form-select-sm rounded-pill" style="width: auto;" onchange="location = this.value;">
              <?php
                $sortParams = $_GET;
                unset($sortParams['sort']);
                $baseQuery = http_build_query($sortParams);
                $prefix = $baseQuery ? '?' . $baseQuery . '&sort=' : '?sort=';
              ?>
              <option value="<?= $prefix ?>newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Terbaru</option>
              <option value="<?= $prefix ?>best_seller" <?= $sort === 'best_seller' ? 'selected' : '' ?>>Terlaris</option>
              <option value="<?= $prefix ?>price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Harga: Rendah ke Tinggi</option>
              <option value="<?= $prefix ?>price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Harga: Tinggi ke Rendah</option>
              <option value="<?= $prefix ?>name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Nama A-Z</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Products Grid -->
      <?php if (empty($products)): ?>
        <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm">
          <i class="bi bi-search text-muted opacity-50" style="font-size: 3.5rem;"></i>
          <h5 class="fw-bold mt-3">Produk Tidak Ditemukan</h5>
          <p class="text-muted small">Coba ubah kata kunci pencarian atau sesuaikan rentang filter kamu.</p>
          <a href="<?= BASE_URL ?>products.php" class="btn btn-sm btn-nnasa mt-2">Lihat Semua Produk</a>
        </div>
      <?php else: ?>
        <div class="row row-cols-2 row-cols-md-3 g-3 g-md-4">
          <?php foreach ($products as $p): ?>
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

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <nav aria-label="Navigasi Halaman" class="mt-5">
            <ul class="pagination justify-content-center">
              <?php
                $queryParams = $_GET;
                function pageUrl($num, $params) {
                  $params['page'] = $num;
                  return '?' . http_build_query($params);
                }
              ?>
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link rounded-pill px-3 me-2" href="<?= pageUrl($page - 1, $queryParams) ?>">Sebelumnya</a>
              </li>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                  <a class="page-link rounded-circle mx-1" href="<?= pageUrl($i, $queryParams) ?>" style="<?= $page === $i ? 'background: var(--primary-pink); border-color: var(--primary-pink);' : '' ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link rounded-pill px-3 ms-2" href="<?= pageUrl($page + 1, $queryParams) ?>">Selanjutnya</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>

      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
