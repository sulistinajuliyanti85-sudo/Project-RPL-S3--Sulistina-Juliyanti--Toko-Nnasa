<?php
/**
 * Admin Dashboard
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Dashboard Utama';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// 1. Core Statistics Queries (Direct from Database)
$totalActiveProducts = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$totalCategories = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalStock = (int)$db->query("SELECT COALESCE(SUM(stock), 0) FROM products")->fetchColumn();
$lowStockCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND status = 'active'")->fetchColumn();

$newOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
$processingOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'processing'")->fetchColumn();
$completedOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('completed', 'delivered')")->fetchColumn();
$cancelledOrdersCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'cancelled'")->fetchColumn();

$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$totalCustomers = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 2. Best-Selling Products
$bestSellers = $db->query("
    SELECT p.id, p.name, p.sku, p.price, p.discount_price, p.stock, p.sales_count, p.main_image, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.sales_count DESC
    LIMIT 5
")->fetchAll();

// 3. Recent Orders
$recentOrders = $db->query("
    SELECT o.*, u.name as customer_name,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    ORDER BY o.id DESC
    LIMIT 6
")->fetchAll();

// 4. Monthly Sales Data for Chart.js
$salesMonthly = $db->query("
    SELECT DATE_FORMAT(created_at, '%b %Y') as month_label,
           COALESCE(SUM(total_amount), 0) as total_sales,
           COUNT(id) as orders_count
    FROM orders
    WHERE payment_status = 'paid'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at ASC
    LIMIT 6
")->fetchAll();

$chartLabels = [];
$chartData = [];
if (!empty($salesMonthly)) {
    foreach ($salesMonthly as $sm) {
        $chartLabels[] = $sm['month_label'];
        $chartData[] = (float)$sm['total_sales'];
    }
} else {
    $chartLabels = [date('M Y')];
    $chartData = [$totalRevenue];
}
?>

<!-- Statistics Overview Cards -->
<div class="row g-3 mb-4">
  <!-- Total Revenue -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div>
        <div class="stat-value text-primary-pink"><?= format_rupiah($totalRevenue) ?></div>
        <div class="stat-label">Total Pendapatan Valid</div>
      </div>
      <div class="stat-icon bg-success-subtle text-success">
        <i class="bi bi-cash-stack"></i>
      </div>
    </div>
  </div>

  <!-- New & Pending Orders -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div>
        <div class="stat-value"><?= $newOrdersCount ?></div>
        <div class="stat-label">Pesanan Belum Bayar</div>
      </div>
      <div class="stat-icon bg-warning-subtle text-warning">
        <i class="bi bi-clock-history"></i>
      </div>
    </div>
  </div>

  <!-- Processing Orders -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div>
        <div class="stat-value"><?= $processingOrdersCount ?></div>
        <div class="stat-label">Pesanan Perlu Diproses</div>
      </div>
      <div class="stat-icon bg-info-subtle text-info">
        <i class="bi bi-box-seam"></i>
      </div>
    </div>
  </div>

  <!-- Low Stock Alert -->
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div>
        <div class="stat-value <?= $lowStockCount > 0 ? 'text-danger' : 'text-dark' ?>"><?= $lowStockCount ?></div>
        <div class="stat-label">Stok Menipis (&le; 5 pcs)</div>
      </div>
      <div class="stat-icon bg-danger-subtle text-danger">
        <i class="bi bi-exclamation-octagon"></i>
      </div>
    </div>
  </div>
</div>

<!-- Secondary Metrics Strip -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg">
    <div class="p-3 bg-white border rounded-3 text-center shadow-sm h-100">
      <small class="text-muted d-block">Produk Aktif</small>
      <span class="fs-4 fw-bold text-dark"><?= $totalActiveProducts ?></span>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="p-3 bg-white border rounded-3 text-center shadow-sm h-100">
      <small class="text-muted d-block">Total Kategori</small>
      <span class="fs-4 fw-bold text-dark"><?= $totalCategories ?></span>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="p-3 bg-white border rounded-3 text-center shadow-sm h-100">
      <small class="text-muted d-block">Total Unit Stok</small>
      <span class="fs-4 fw-bold text-dark"><?= number_format($totalStock) ?></span>
    </div>
  </div>
  <div class="col-6 col-lg">
    <div class="p-3 bg-white border rounded-3 text-center shadow-sm h-100">
      <small class="text-muted d-block">Pesanan Selesai</small>
      <span class="fs-4 fw-bold text-success"><?= $completedOrdersCount ?></span>
    </div>
  </div>
  <div class="col-12 col-lg">
    <a href="<?= ADMIN_URL ?>users.php" class="text-decoration-none">
      <div class="p-3 bg-white border border-danger-subtle rounded-3 text-center shadow-sm h-100" style="transition: transform 0.2s, box-shadow 0.2s;">
        <small class="text-muted d-block"><i class="bi bi-people-fill text-primary-pink me-1"></i> Manajemen User</small>
        <span class="fs-4 fw-bold text-primary-pink"><?= $totalUsers ?></span>
        <div class="small text-primary-pink fw-semibold" style="font-size: 0.75rem;">Buka Pengguna &rarr;</div>
      </div>
    </a>
  </div>
</div>

<!-- Charts & Best Sellers Row -->
<div class="row g-4 mb-4">
  <!-- Sales Chart -->
  <div class="col-lg-8">
    <div class="admin-card h-100">
      <div class="admin-card-header">
        <h5 class="admin-card-title"><i class="bi bi-graph-up text-primary-pink me-2"></i> Tren Penjualan &amp; Pendapatan</h5>
        <span class="badge bg-light text-dark border">Data Riil Database</span>
      </div>
      <div class="admin-card-body">
        <canvas id="salesChart" height="120"></canvas>
      </div>
    </div>
  </div>

  <!-- Best Sellers -->
  <div class="col-lg-4">
    <div class="admin-card h-100">
      <div class="admin-card-header">
        <h5 class="admin-card-title"><i class="bi bi-fire text-danger me-2"></i> Aksesoris Terlaris</h5>
        <a href="<?= ADMIN_URL ?>products.php" class="small text-decoration-none">Semua</a>
      </div>
      <div class="admin-card-body p-0">
        <div class="list-group list-group-flush small">
          <?php foreach ($bestSellers as $bs): ?>
            <div class="list-group-item d-flex align-items-center gap-2 p-3">
              <img src="<?= get_image_url('products', $bs['main_image'], $bs['name']) ?>" class="rounded-2 border flex-shrink-0" style="width: 44px; height: 44px; object-fit: cover;">
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-dark text-truncate"><?= htmlspecialchars($bs['name']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($bs['category_name'] ?? 'Aksesoris') ?> &bull; Stok: <?= $bs['stock'] ?></small>
              </div>
              <div class="text-end flex-shrink-0">
                <span class="badge bg-primary-pink text-white rounded-pill px-2 py-1"><?= $bs['sales_count'] ?> Terjual</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Recent Orders Table -->
<div class="admin-card">
  <div class="admin-card-header">
    <h5 class="admin-card-title"><i class="bi bi-receipt text-primary-pink me-2"></i> Pesanan Terbaru Masuk</h5>
    <a href="<?= ADMIN_URL ?>orders.php" class="btn btn-sm btn-nnasa-outline">Lihat Seluruh Pesanan</a>
  </div>
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Nomor Pesanan</th>
            <th>Pelanggan</th>
            <th>Item</th>
            <th>Total Transaksi</th>
            <th>Pembayaran</th>
            <th>Status Pesanan</th>
            <th>Waktu</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentOrders)): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">Belum ada pesanan masuk.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($recentOrders as $ro): ?>
              <tr>
                <td>
                  <strong class="text-dark font-monospace"><?= htmlspecialchars($ro['order_number']) ?></strong>
                </td>
                <td>
                  <span class="fw-semibold text-dark"><?= htmlspecialchars($ro['customer_name']) ?></span>
                </td>
                <td><?= $ro['item_count'] ?> item</td>
                <td>
                  <strong class="text-primary-pink"><?= format_rupiah($ro['total_amount']) ?></strong>
                </td>
                <td><?= payment_status_badge($ro['payment_status']) ?></td>
                <td><?= order_status_badge($ro['order_status']) ?></td>
                <td>
                  <small class="text-muted"><?= date('d/m/Y H:i', strtotime($ro['created_at'])) ?></small>
                </td>
                <td class="text-end">
                  <a href="<?= ADMIN_URL ?>order-detail.php?id=<?= $ro['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3">
                    Detail <i class="bi bi-chevron-right ms-1"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [{
          label: 'Total Penjualan (Rp)',
          data: <?= json_encode($chartData) ?>,
          borderColor: '#c27d86',
          backgroundColor: 'rgba(194, 125, 134, 0.12)',
          fill: true,
          tension: 0.35,
          pointBackgroundColor: '#b76e79',
          pointRadius: 5
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(val) {
                return 'Rp ' + val.toLocaleString('id-ID');
              }
            }
          }
        }
      }
    });
  });
</script>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
