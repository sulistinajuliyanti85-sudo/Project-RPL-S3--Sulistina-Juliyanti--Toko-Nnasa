<?php
/**
 * Customer Orders List (Pesanan Saya)
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

$filterStatus = $_GET['status'] ?? 'all';

$where = ["user_id = ?"];
$params = [$userId];

if ($filterStatus === 'pending') {
    $where[] = "order_status = 'pending'";
} elseif ($filterStatus === 'processing') {
    $where[] = "order_status = 'processing'";
} elseif ($filterStatus === 'shipped') {
    $where[] = "order_status = 'shipped'";
} elseif ($filterStatus === 'completed') {
    $where[] = "order_status IN ('completed', 'delivered')";
} elseif ($filterStatus === 'cancelled') {
    $where[] = "order_status = 'cancelled'";
}

$whereClause = implode(' AND ', $where);

// Fetch orders with first product details
$stmt = $db->prepare("
    SELECT o.*, 
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
           (SELECT product_name_snapshot FROM order_items WHERE order_id = o.id LIMIT 1) as sample_product_name,
           (SELECT p.main_image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = o.id LIMIT 1) as sample_product_image
    FROM orders o
    WHERE {$whereClause}
    ORDER BY o.id DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$pageTitle = 'Pesanan Saya';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Pesanan Saya</li>
    </ol>
  </nav>

  <h1 class="h3 fw-bold text-dark mb-4 brand-font">Pesanan Saya 📦</h1>

  <!-- Status Tabs -->
  <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
    <div class="d-flex overflow-auto border-bottom p-2 gap-2">
      <a href="<?= BASE_URL ?>orders.php" class="btn btn-sm rounded-pill px-3 <?= $filterStatus === 'all' ? 'btn-nnasa' : 'btn-light text-muted' ?>">
        Semua Pesanan
      </a>
      <a href="<?= BASE_URL ?>orders.php?status=pending" class="btn btn-sm rounded-pill px-3 <?= $filterStatus === 'pending' ? 'btn-nnasa' : 'btn-light text-muted' ?>">
        Belum Bayar
      </a>
      <a href="<?= BASE_URL ?>orders.php?status=processing" class="btn btn-sm rounded-pill px-3 <?= $filterStatus === 'processing' ? 'btn-nnasa' : 'btn-light text-muted' ?>">
        Diproses
      </a>
      <a href="<?= BASE_URL ?>orders.php?status=shipped" class="btn btn-sm rounded-pill px-3 <?= $filterStatus === 'shipped' ? 'btn-nnasa' : 'btn-light text-muted' ?>">
        Dikirim
      </a>
      <a href="<?= BASE_URL ?>orders.php?status=completed" class="btn btn-sm rounded-pill px-3 <?= $filterStatus === 'completed' ? 'btn-nnasa' : 'btn-light text-muted' ?>">
        Selesai
      </a>
      <a href="<?= BASE_URL ?>orders.php?status=cancelled" class="btn btn-sm rounded-pill px-3 <?= $filterStatus === 'cancelled' ? 'btn-nnasa' : 'btn-light text-muted' ?>">
        Dibatalkan
      </a>
    </div>
  </div>

  <!-- Orders List -->
  <?php if (empty($orders)): ?>
    <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm">
      <i class="bi bi-box-seam text-muted opacity-50" style="font-size: 3.5rem;"></i>
      <h5 class="fw-bold mt-3">Belum Ada Pesanan</h5>
      <p class="text-muted small">Pesanan yang kamu buat akan tampil di halaman ini.</p>
      <a href="<?= BASE_URL ?>products.php" class="btn btn-sm btn-nnasa">Mulai Berbelanja</a>
    </div>
  <?php else: ?>
    <div class="d-flex flex-column gap-3">
      <?php foreach ($orders as $ord): ?>
        <div class="card border-0 shadow-sm rounded-4 p-3 p-md-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <!-- Card Header Bar -->
          <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 border-bottom mb-3 gap-2">
            <div class="d-flex align-items-center gap-2">
              <i class="bi bi-bag-check text-primary-pink"></i>
              <strong class="text-dark small"><?= htmlspecialchars($ord['order_number']) ?></strong>
              <span class="text-muted small">&bull; <?= date('d M Y, H:i', strtotime($ord['created_at'])) ?></span>
            </div>
            <div class="d-flex align-items-center gap-2">
              <?= payment_status_badge($ord['payment_status']) ?>
              <?= order_status_badge($ord['order_status']) ?>
            </div>
          </div>

          <!-- Card Content Body -->
          <div class="d-flex align-items-center gap-3">
            <img src="<?= get_image_url('products', $ord['sample_product_image'], $ord['sample_product_name'] ?? 'Aksesoris') ?>" class="rounded-3 border flex-shrink-0" style="width: 70px; height: 70px; object-fit: cover;">
            <div class="flex-grow-1 min-w-0 small">
              <h6 class="fw-bold text-dark text-truncate mb-1"><?= htmlspecialchars($ord['sample_product_name'] ?? 'Paket Aksesoris') ?></h6>
              <?php if ($ord['item_count'] > 1): ?>
                <span class="text-muted">dan <?= $ord['item_count'] - 1 ?> produk lainnya</span>
              <?php endif; ?>
              <div class="mt-1 text-muted">Total Belanja: <strong class="text-primary-pink fs-6"><?= format_rupiah($ord['total_amount']) ?></strong></div>
            </div>
          </div>

          <!-- Card Footer Actions -->
          <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 pt-3 border-top mt-3">
            <a href="<?= BASE_URL ?>order-detail.php?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
              Rincian Pesanan
            </a>
            <?php if ($ord['payment_status'] === 'pending'): ?>
              <a href="<?= BASE_URL ?>payment.php?order_id=<?= $ord['id'] ?>" class="btn btn-sm btn-nnasa rounded-pill px-3">
                Bayar Sekarang <i class="bi bi-wallet2 ms-1"></i>
              </a>
            <?php else: ?>
              <a href="<?= BASE_URL ?>tracking.php?order_number=<?= urlencode($ord['order_number']) ?>" class="btn btn-sm btn-nnasa-outline rounded-pill px-3">
                <i class="bi bi-truck me-1"></i> Lacak Pengiriman
              </a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
