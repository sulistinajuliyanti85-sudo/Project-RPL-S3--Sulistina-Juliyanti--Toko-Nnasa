<?php
/**
 * Admin Reports & CSV Export (Laporan Penjualan & Stok)
 * Store: nnasa_ca Accessories Marketplace
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

require_admin();
$db = getDB();

$startDate = $_GET['start_date'] ?? date('Y-m-01'); // 1st day of current month
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Penjualan_nnasa_ca_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['No Pesanan', 'Tanggal', 'Nama Pelanggan', 'Subtotal (Rp)', 'Diskon Voucher (Rp)', 'Ongkos Kirim (Rp)', 'Total Transaksi (Rp)', 'Status Pembayaran', 'Status Pesanan']);

    $stmtExport = $db->prepare("
        SELECT o.order_number, o.created_at, u.name as customer_name,
               o.subtotal, o.discount_amount, o.shipping_cost, o.total_amount,
               o.payment_status, o.order_status
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
        ORDER BY o.id DESC
    ");
    $stmtExport->execute([$startDate, $endDate]);
    while ($row = $stmtExport->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['order_number'],
            $row['created_at'],
            $row['customer_name'],
            $row['subtotal'],
            $row['discount_amount'],
            $row['shipping_cost'],
            $row['total_amount'],
            $row['payment_status'],
            $row['order_status']
        ]);
    }
    fclose($output);
    exit;
}

$adminPageTitle = 'Laporan Penjualan & Stok';
require_once dirname(__DIR__) . '/templates/admin_header.php';

// Financial Calculations
// 1. Gross sales (Subtotal of all paid orders)
$stmtGross = $db->prepare("
    SELECT COALESCE(SUM(subtotal), 0) as gross_subtotal,
           COALESCE(SUM(discount_amount), 0) as total_discounts,
           COALESCE(SUM(shipping_cost), 0) as total_shipping,
           COALESCE(SUM(total_amount), 0) as net_revenue,
           COUNT(id) as total_paid_orders
    FROM orders 
    WHERE payment_status = 'paid' AND DATE(created_at) >= ? AND DATE(created_at) <= ?
");
$stmtGross->execute([$startDate, $endDate]);
$finance = $stmtGross->fetch();

// 2. Orders list in date range
$stmtOrders = $db->prepare("
    SELECT o.*, u.name as customer_name
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
    ORDER BY o.id DESC
");
$stmtOrders->execute([$startDate, $endDate]);
$reportOrders = $stmtOrders->fetchAll();

// 3. Top selling products
$topProducts = $db->query("
    SELECT p.name, p.sku, p.sales_count, p.stock, c.name as category_name,
           (p.sales_count * COALESCE(p.discount_price, p.price)) as total_product_revenue
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.sales_count DESC
    LIMIT 10
")->fetchAll();
?>

<!-- Header with Export Button -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Laporan Penjualan &amp; Finansial</h4>
    <small class="text-muted">Analisis pendapatan bersih, diskon kupon, ongkir, dan performa produk</small>
  </div>
  <div>
    <a href="<?= ADMIN_URL ?>reports.php?export=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>" class="btn btn-success rounded-pill px-4">
      <i class="bi bi-file-earmark-excel me-1"></i> Ekspor CSV / Excel
    </a>
  </div>
</div>

<!-- Date Filter Form -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4" style="background: #ffffff; border: 1px solid var(--admin-border) !important;">
  <form action="<?= ADMIN_URL ?>reports.php" method="GET" class="row g-3 align-items-end">
    <div class="col-md-4">
      <label class="form-label small fw-bold">Dari Tanggal:</label>
      <input type="date" name="start_date" class="form-control form-control-sm rounded-pill" value="<?= htmlspecialchars($startDate) ?>">
    </div>
    <div class="col-md-4">
      <label class="form-label small fw-bold">Sampai Tanggal:</label>
      <input type="date" name="end_date" class="form-control form-control-sm rounded-pill" value="<?= htmlspecialchars($endDate) ?>">
    </div>
    <div class="col-md-4">
      <button type="submit" class="btn btn-sm btn-nnasa w-100 rounded-pill">Terapkan Periode</button>
    </div>
  </form>
</div>

<!-- Financial Summary Cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="p-3 bg-white border rounded-4 text-center shadow-sm">
      <small class="text-muted d-block mb-1">Total Omzet Bruto (Subtotal)</small>
      <strong class="h5 fw-bold text-dark"><?= format_rupiah($finance['gross_subtotal']) ?></strong>
      <small class="text-muted d-block mt-1">Nilai produk terjual</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="p-3 bg-white border rounded-4 text-center shadow-sm">
      <small class="text-muted d-block mb-1">Total Diskon Voucher</small>
      <strong class="h5 fw-bold text-danger">-<?= format_rupiah($finance['total_discounts']) ?></strong>
      <small class="text-muted d-block mt-1">Subsidi promo toko</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="p-3 bg-white border rounded-4 text-center shadow-sm">
      <small class="text-muted d-block mb-1">Total Ongkir Terkumpul</small>
      <strong class="h5 fw-bold text-secondary"><?= format_rupiah($finance['total_shipping']) ?></strong>
      <small class="text-muted d-block mt-1">Diteruskan ke kurir</small>
    </div>
  </div>
  <div class="col-md-3">
    <div class="p-3 bg-white border rounded-4 text-center shadow-sm" style="border-color: var(--primary-pink) !important;">
      <small class="text-muted d-block mb-1">Total Pendapatan Bersih</small>
      <strong class="h5 fw-bold text-primary-pink"><?= format_rupiah($finance['net_revenue']) ?></strong>
      <small class="text-success d-block mt-1 fw-bold"><?= $finance['total_paid_orders'] ?> Transaksi Lunas</small>
    </div>
  </div>
</div>

<!-- Orders in Period -->
<div class="admin-card mb-4">
  <div class="admin-card-header">
    <h5 class="admin-card-title"><i class="bi bi-receipt text-primary-pink me-2"></i> Transaksi dalam Periode Dipilih (<?= count($reportOrders) ?>)</h5>
  </div>
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Nomor Pesanan</th>
            <th>Waktu</th>
            <th>Pelanggan</th>
            <th>Subtotal</th>
            <th>Diskon</th>
            <th>Ongkir</th>
            <th>Total Bersih</th>
            <th>Status Bayar</th>
            <th>Status Order</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($reportOrders)): ?>
            <tr>
              <td colspan="9" class="text-center py-4 text-muted">Tidak ada transaksi pada rentang tanggal ini.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($reportOrders as $ro): ?>
              <tr>
                <td><code><?= htmlspecialchars($ro['order_number']) ?></code></td>
                <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($ro['created_at'])) ?></small></td>
                <td><strong class="text-dark small"><?= htmlspecialchars($ro['customer_name']) ?></strong></td>
                <td><?= format_rupiah($ro['subtotal']) ?></td>
                <td>
                  <?= (float)$ro['discount_amount'] > 0 ? '<span class="text-danger">-' . format_rupiah($ro['discount_amount']) . '</span>' : '-' ?>
                </td>
                <td><?= format_rupiah($ro['shipping_cost']) ?></td>
                <td><strong class="text-primary-pink"><?= format_rupiah($ro['total_amount']) ?></strong></td>
                <td><?= payment_status_badge($ro['payment_status']) ?></td>
                <td><?= order_status_badge($ro['order_status']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Top Products Performance -->
<div class="admin-card">
  <div class="admin-card-header">
    <h5 class="admin-card-title"><i class="bi bi-star text-warning me-2"></i> 10 Produk Aksesoris Terlaris Sepanjang Masa</h5>
  </div>
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Peringkat</th>
            <th>Produk</th>
            <th>SKU</th>
            <th>Kategori</th>
            <th>Total Unit Terjual</th>
            <th>Sisa Stok Saat Ini</th>
            <th>Estimasi Nilai Omzet Produk</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topProducts as $idx => $tp): ?>
            <tr>
              <td><span class="badge rounded-circle bg-light text-dark border p-2" style="width: 28px; height: 28px;"><?= $idx + 1 ?></span></td>
              <td><strong class="text-dark"><?= htmlspecialchars($tp['name']) ?></strong></td>
              <td><code><?= htmlspecialchars($tp['sku']) ?></code></td>
              <td><span class="badge bg-light text-muted border"><?= htmlspecialchars($tp['category_name'] ?? '-') ?></span></td>
              <td><strong class="text-success"><?= $tp['sales_count'] ?> pcs</strong></td>
              <td><?= $tp['stock'] ?> pcs</td>
              <td><strong class="text-primary-pink"><?= format_rupiah($tp['total_product_revenue']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
