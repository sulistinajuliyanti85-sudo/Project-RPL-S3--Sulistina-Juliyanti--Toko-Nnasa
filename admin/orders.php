<?php
/**
 * Admin Orders Management (Manajemen Pesanan)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Manajemen Pesanan';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// Search and filter parameters
$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';
$paymentFilter = $_GET['payment'] ?? 'all';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($statusFilter !== 'all') {
    $where[] = "o.order_status = ?";
    $params[] = $statusFilter;
}

if ($paymentFilter !== 'all') {
    $where[] = "o.payment_status = ?";
    $params[] = $paymentFilter;
}

if (!empty($startDate)) {
    $where[] = "DATE(o.created_at) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $where[] = "DATE(o.created_at) <= ?";
    $params[] = $endDate;
}

$whereClause = implode(' AND ', $where);

// Fetch orders
$sql = "
    SELECT o.*, u.name as customer_name, u.email as customer_email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
           s.courier, s.tracking_number
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN shipments s ON o.id = s.order_id
    WHERE {$whereClause}
    ORDER BY o.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Daftar Seluruh Pesanan (<?= count($orders) ?>)</h4>
    <small class="text-muted">Proses pesanan pelanggan, input nomor resi, dan verifikasi pembayaran</small>
  </div>
  <div>
    <a href="<?= ADMIN_URL ?>reports.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Laporan Penjualan
    </a>
  </div>
</div>

<!-- Filters Card -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4" style="background: #ffffff; border: 1px solid var(--admin-border) !important;">
  <form action="<?= ADMIN_URL ?>orders.php" method="GET" class="row g-2 align-items-center">
    <div class="col-md-3">
      <input type="text" name="q" class="form-control form-control-sm rounded-pill" placeholder="Cari No Pesanan / Pelanggan..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select form-select-sm rounded-pill">
        <option value="all">Semua Status Pesanan</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
        <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Diproses</option>
        <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Sedang Dikirim</option>
        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai</option>
        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="payment" class="form-select form-select-sm rounded-pill">
        <option value="all">Semua Pembayaran</option>
        <option value="paid" <?= $paymentFilter === 'paid' ? 'selected' : '' ?>>Lunas (Paid)</option>
        <option value="pending" <?= $paymentFilter === 'pending' ? 'selected' : '' ?>>Belum Bayar</option>
        <option value="failed" <?= $paymentFilter === 'failed' ? 'selected' : '' ?>>Gagal / Batal</option>
      </select>
    </div>
    <div class="col-md-2">
      <input type="date" name="start_date" class="form-control form-control-sm rounded-pill" value="<?= htmlspecialchars($startDate) ?>" title="Tanggal Mulai">
    </div>
    <div class="col-md-2">
      <input type="date" name="end_date" class="form-control form-control-sm rounded-pill" value="<?= htmlspecialchars($endDate) ?>" title="Tanggal Selesai">
    </div>
    <div class="col-md-1">
      <button type="submit" class="btn btn-sm btn-nnasa w-100 rounded-pill">Filter</button>
    </div>
  </form>
</div>

<!-- Orders Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Nomor Pesanan</th>
            <th>Pelanggan</th>
            <th>Item</th>
            <th>Total Bayar</th>
            <th>Pembayaran</th>
            <th>Status Pesanan</th>
            <th>Kurir / Resi</th>
            <th>Waktu Dibuat</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($orders)): ?>
            <tr>
              <td colspan="9" class="text-center py-4 text-muted">Tidak ada pesanan yang sesuai filter.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($orders as $ord): ?>
              <tr>
                <td>
                  <strong class="text-dark font-monospace"><?= htmlspecialchars($ord['order_number']) ?></strong>
                </td>
                <td>
                  <span class="fw-semibold text-dark d-block"><?= htmlspecialchars($ord['customer_name']) ?></span>
                  <small class="text-muted"><?= htmlspecialchars($ord['customer_email']) ?></small>
                </td>
                <td><?= $ord['item_count'] ?> item</td>
                <td>
                  <strong class="text-primary-pink"><?= format_rupiah($ord['total_amount']) ?></strong>
                </td>
                <td><?= payment_status_badge($ord['payment_status']) ?></td>
                <td><?= order_status_badge($ord['order_status']) ?></td>
                <td>
                  <small class="text-dark d-block"><?= htmlspecialchars($ord['courier'] ?? 'SiCepat') ?></small>
                  <?php if (!empty($ord['tracking_number'])): ?>
                    <code class="text-primary-pink small"><?= htmlspecialchars($ord['tracking_number']) ?></code>
                  <?php else: ?>
                    <small class="text-muted fst-italic">Belum ada resi</small>
                  <?php endif; ?>
                </td>
                <td>
                  <small class="text-muted"><?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?></small>
                </td>
                <td class="text-end">
                  <a href="<?= ADMIN_URL ?>order-detail.php?id=<?= $ord['id'] ?>" class="btn btn-sm btn-nnasa rounded-pill px-3">
                    Proses <i class="bi bi-arrow-right ms-1"></i>
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

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
