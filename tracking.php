<?php
/**
 * Order Tracking Page (Lacak Pesanan)
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';

$orderNumber = trim($_GET['order_number'] ?? '');
$order = null;
$statusHistory = [];
$db = getDB();

if ($orderNumber !== '') {
    $stmt = $db->prepare("
        SELECT o.*, s.courier, s.service_type, s.tracking_number, s.shipping_status, s.shipped_at, s.delivered_at
        FROM orders o
        LEFT JOIN shipments s ON o.id = s.order_id
        WHERE o.order_number = ?
        LIMIT 1
    ");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        $stmtHist = $db->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
        $stmtHist->execute([$order['id']]);
        $statusHistory = $stmtHist->fetchAll();
    }
}

$pageTitle = 'Lacak Pengiriman Pesanan';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Lacak Pesanan</li>
    </ol>
  </nav>

  <div class="row justify-content-center">
    <div class="col-lg-8">
      
      <!-- Search Order Card -->
      <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5 mb-4 text-center" style="background: linear-gradient(135deg, #fffcfc 0%, #fff7f8 100%); border: 1px solid var(--border-subtle) !important;">
        <i class="bi bi-geo-alt-fill text-primary-pink fs-1 mb-2"></i>
        <h1 class="h3 fw-bold text-dark mb-2 brand-font">Lacak Pesanan nnasa_ca</h1>
        <p class="text-muted small mb-4">Masukkan nomor pesanan kamu (contoh: <code>NNA-20261014-0001</code>) untuk memeriksa posisi pengiriman paket.</p>

        <form action="<?= BASE_URL ?>tracking.php" method="GET" class="d-flex justify-content-center">
          <div class="input-group" style="max-width: 480px;">
            <input type="text" name="order_number" class="form-control rounded-pill-start ps-3" placeholder="Masukkan Nomor Pesanan..." value="<?= htmlspecialchars($orderNumber) ?>" required>
            <button type="submit" class="btn btn-nnasa rounded-pill-end px-4">
              <i class="bi bi-search me-1"></i> Lacak
            </button>
          </div>
        </form>
      </div>

      <!-- Tracking Results -->
      <?php if ($orderNumber !== '' && !$order): ?>
        <div class="alert alert-warning text-center rounded-4 p-4 shadow-sm">
          <i class="bi bi-exclamation-triangle-fill fs-3 text-warning d-block mb-2"></i>
          <h5 class="fw-bold">Pesanan Tidak Ditemukan</h5>
          <p class="small text-muted mb-0">Pastikan nomor pesanan yang kamu masukkan sudah sesuai persis dengan yang tertera di email atau riwayat pesanan.</p>
        </div>
      <?php elseif ($order): ?>
        
        <?php
          // Compute tracking step progression (1 to 4)
          $step = 1;
          if ($order['order_status'] === 'processing') $step = 2;
          if ($order['order_status'] === 'shipped') $step = 3;
          if (in_array($order['order_status'], ['completed', 'delivered'])) $step = 4;
          if ($order['order_status'] === 'cancelled') $step = 0;
        ?>

        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <!-- Header Bar -->
          <div class="d-flex flex-wrap justify-content-between align-items-center pb-3 border-bottom mb-4 gap-2">
            <div>
              <span class="text-muted small d-block">Nomor Pesanan:</span>
              <strong class="h5 fw-bold text-dark mb-0"><?= htmlspecialchars($order['order_number']) ?></strong>
            </div>
            <div class="text-end">
              <?= order_status_badge($order['order_status']) ?>
            </div>
          </div>

          <!-- Step Progression Bar -->
          <?php if ($order['order_status'] !== 'cancelled'): ?>
            <div class="py-3 px-2 mb-4">
              <div class="d-flex justify-content-between position-relative">
                <div class="position-absolute top-50 start-0 end-0 translate-middle-y bg-light-subtle" style="height: 4px; z-index: 1;">
                  <div class="bg-primary-pink" style="height: 100%; width: <?= ($step - 1) * 33.33 ?>%; transition: width 0.5s ease;"></div>
                </div>

                <!-- Step 1: Pesanan Dibuat -->
                <div class="text-center position-relative" style="z-index: 2;">
                  <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 text-white shadow-sm" style="width: 36px; height: 36px; background: <?= $step >= 1 ? 'var(--primary-pink)' : '#ced4da' ?>;">
                    <i class="bi bi-file-earmark-text"></i>
                  </div>
                  <small class="fw-bold d-block text-dark" style="font-size: 0.75rem;">Dibuat</small>
                </div>

                <!-- Step 2: Pembayaran Lunas & Diproses -->
                <div class="text-center position-relative" style="z-index: 2;">
                  <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 text-white shadow-sm" style="width: 36px; height: 36px; background: <?= $step >= 2 ? 'var(--primary-pink)' : '#ced4da' ?>;">
                    <i class="bi bi-box-seam"></i>
                  </div>
                  <small class="fw-bold d-block text-dark" style="font-size: 0.75rem;">Diproses</small>
                </div>

                <!-- Step 3: Sedang Dikirim -->
                <div class="text-center position-relative" style="z-index: 2;">
                  <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 text-white shadow-sm" style="width: 36px; height: 36px; background: <?= $step >= 3 ? 'var(--primary-pink)' : '#ced4da' ?>;">
                    <i class="bi bi-truck"></i>
                  </div>
                  <small class="fw-bold d-block text-dark" style="font-size: 0.75rem;">Dikirim</small>
                </div>

                <!-- Step 4: Selesai Diterima -->
                <div class="text-center position-relative" style="z-index: 2;">
                  <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 text-white shadow-sm" style="width: 36px; height: 36px; background: <?= $step >= 4 ? 'var(--primary-pink)' : '#ced4da' ?>;">
                    <i class="bi bi-check2-circle"></i>
                  </div>
                  <small class="fw-bold d-block text-dark" style="font-size: 0.75rem;">Selesai</small>
                </div>
              </div>
            </div>
          <?php endif; ?>

          <!-- Courier Details -->
          <div class="p-3 bg-light rounded-3 mb-4 border">
            <div class="row g-3 small">
              <div class="col-6 col-md-3">
                <span class="text-muted d-block">Kurir:</span>
                <strong class="text-dark"><?= htmlspecialchars($order['courier'] ?? 'SiCepat') ?></strong>
              </div>
              <div class="col-6 col-md-3">
                <span class="text-muted d-block">Layanan:</span>
                <strong class="text-dark"><?= htmlspecialchars($order['service_type'] ?? 'REG') ?></strong>
              </div>
              <div class="col-12 col-md-6">
                <span class="text-muted d-block">Nomor Resi Pengiriman:</span>
                <strong class="fs-6 text-primary-pink font-monospace">
                  <?= htmlspecialchars($order['tracking_number'] ?: 'Resi belum diinput admin') ?>
                </strong>
              </div>
            </div>
          </div>

          <!-- Chronological History Timeline -->
          <h6 class="fw-bold text-dark mb-3">Detail Perjalanan Paket</h6>
          <div class="position-relative ps-4" style="border-left: 2px solid var(--border-subtle);">
            <?php foreach (array_reverse($statusHistory) as $hist): ?>
              <div class="mb-3 position-relative">
                <span class="position-absolute rounded-circle" style="left: -22px; top: 3px; width: 12px; height: 12px; background: var(--primary-pink); border: 2px solid #ffffff;"></span>
                <div class="fw-bold small text-dark"><?= ucfirst($hist['status']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($hist['comment'] ?? '') ?></div>
                <small class="text-muted" style="font-size: 0.72rem;"><?= date('d F Y, H:i', strtotime($hist['created_at'])) ?></small>
              </div>
            <?php endforeach; ?>
          </div>

        </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
