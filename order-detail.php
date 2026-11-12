<?php
/**
 * Customer Order Detail Page
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

$orderId = (int)($_GET['order_id'] ?? 0);

// Fetch order
$stmt = $db->prepare("
    SELECT o.*, p.payment_method, p.transaction_reference, p.paid_at,
           s.courier, s.service_type, s.tracking_number, s.shipping_status, s.shipped_at, s.delivered_at
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    LEFT JOIN shipments s ON o.id = s.order_id
    WHERE o.id = ? AND (o.user_id = ? OR ? = 'admin')
    LIMIT 1
");
$stmt->execute([$orderId, $userId, $user['role']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    header('Location: ' . BASE_URL . 'orders.php');
    exit;
}

// Extract exact customer WhatsApp phone number from address_snapshot
$customerOrderPhone = null;
if (!empty($order['address_snapshot'])) {
    if (preg_match('/\|\s*([0-9\+\-\s]{8,25})(?:\r?\n|$)/', $order['address_snapshot'], $mPhone)) {
        $customerOrderPhone = trim($mPhone[1]);
    }
}
if (!$customerOrderPhone && !empty($user['phone'])) {
    $customerOrderPhone = trim($user['phone']);
}

// Handle Order Cancellation (Only when pending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } elseif ($order['order_status'] === 'pending') {
        try {
            $db->beginTransaction();

            // Return stock to products
            $items = $db->prepare("SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?");
            $items->execute([$orderId]);
            $orderItemsList = $items->fetchAll();

            $pReturn = $db->prepare("UPDATE products SET stock = stock + ?, sales_count = GREATEST(0, sales_count - ?) WHERE id = ?");
            $vReturn = $db->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
            $smReturn = $db->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_type, quantity, reference_type, reference_id, notes, created_by) VALUES (?, ?, 'return', ?, 'order', ?, 'Pengembalian stok pembatalan pesanan', ?)");

            foreach ($orderItemsList as $oi) {
                $pReturn->execute([$oi['quantity'], $oi['quantity'], $oi['product_id']]);
                if ($oi['variant_id']) {
                    $vReturn->execute([$oi['quantity'], $oi['variant_id']]);
                }
                $smReturn->execute([$oi['product_id'], $oi['variant_id'], $oi['quantity'], $orderId, $userId]);
            }

            // Update order status
            $db->prepare("UPDATE orders SET order_status = 'cancelled', payment_status = 'failed', updated_at = NOW() WHERE id = ?")->execute([$orderId]);
            $db->prepare("INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES (?, 'cancelled', 'Pesanan dibatalkan oleh pelanggan.', ?)")->execute([$orderId, $userId]);

            $db->commit();
            set_flash('success', 'Pesanan berhasil dibatalkan.');
            header('Location: ' . BASE_URL . 'order-detail.php?order_id=' . $orderId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            set_flash('danger', 'Gagal membatalkan pesanan: ' . $e->getMessage());
        }
    }
}

// Handle Customer Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    if (csrf_verify($_POST['csrf_token'] ?? '')) {
        $productId = (int)($_POST['product_id'] ?? 0);
        $orderItemId = (int)($_POST['order_item_id'] ?? 0);
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $reviewText = sanitize($_POST['review_text'] ?? '');

        // Check if user actually bought this item and order is completed
        if (in_array($order['order_status'], ['completed', 'delivered'])) {
            $ins = $db->prepare("
                INSERT INTO reviews (user_id, product_id, order_item_id, rating, review_text, status)
                VALUES (?, ?, ?, ?, ?, 'approved')
            ");
            $ins->execute([$userId, $productId, $orderItemId, $rating, $reviewText]);
            set_flash('success', 'Terima kasih atas ulasan cantik kamu! 💖');
            header('Location: ' . BASE_URL . 'order-detail.php?order_id=' . $orderId);
            exit;
        }
    }
}

// Fetch order items
$stmtItems = $db->prepare("
    SELECT oi.*, p.main_image,
           (SELECT id FROM reviews WHERE order_item_id = oi.id LIMIT 1) as existing_review_id
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmtItems->execute([$orderId]);
$orderItems = $stmtItems->fetchAll();

// Fetch status history
$stmtHist = $db->prepare("SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC");
$stmtHist->execute([$orderId]);
$statusHistory = $stmtHist->fetchAll();

$pageTitle = 'Rincian Pesanan ' . $order['order_number'];
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-4">
  <!-- Breadcrumbs -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>orders.php" class="text-decoration-none text-muted">Pesanan</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page"><?= htmlspecialchars($order['order_number']) ?></li>
    </ol>
  </nav>

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h1 class="h3 fw-bold text-dark mb-1 brand-font">Pesanan: <?= htmlspecialchars($order['order_number']) ?></h1>
      <small class="text-muted">Dibuat pada: <?= date('d F Y, H:i', strtotime($order['created_at'])) ?></small>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?= payment_status_badge($order['payment_status']) ?>
      <?= order_status_badge($order['order_status']) ?>
    </div>
  </div>

  <div class="row g-4">
    <!-- Left Column: Items, History, and Addresses -->
    <div class="col-lg-8">
      
      <!-- Items Table -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="card-header bg-white py-3 border-bottom">
          <h5 class="fw-bold fs-6 mb-0 text-dark"><i class="bi bi-box-seam text-primary-pink me-2"></i> Rincian Produk</h5>
        </div>
        <div class="card-body p-0">
          <?php foreach ($orderItems as $it): ?>
            <div class="p-3 border-bottom d-flex align-items-center gap-3">
              <img src="<?= get_image_url('products', $it['main_image'], $it['product_name_snapshot']) ?>" class="rounded-3 border flex-shrink-0" style="width: 65px; height: 65px; object-fit: cover;">
              <div class="flex-grow-1 min-w-0 small">
                <strong class="text-dark d-block text-truncate"><?= htmlspecialchars($it['product_name_snapshot']) ?></strong>
                <?php if ($it['variant_snapshot']): ?>
                  <span class="badge bg-light text-muted border"><?= htmlspecialchars($it['variant_snapshot']) ?></span>
                <?php endif; ?>
                <div class="text-muted mt-1">
                  <?= $it['quantity'] ?> x <?= format_rupiah($it['price']) ?>
                </div>
              </div>
              <div class="text-end flex-shrink-0">
                <span class="fw-bold text-dark small d-block"><?= format_rupiah($it['subtotal']) ?></span>
                
                <?php if (in_array($order['order_status'], ['completed', 'delivered']) && !$it['existing_review_id']): ?>
                  <button type="button" class="btn btn-sm btn-nnasa-outline rounded-pill mt-2 py-1 px-3" data-bs-toggle="modal" data-bs-target="#reviewModal<?= $it['id'] ?>">
                    <i class="bi bi-star me-1"></i> Beri Ulasan
                  </button>
                  
                  <!-- Review Modal -->
                  <div class="modal fade text-start" id="reviewModal<?= $it['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content rounded-4 border-0 shadow">
                        <form action="<?= BASE_URL ?>order-detail.php?order_id=<?= $order['id'] ?>" method="POST">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="submit_review">
                          <input type="hidden" name="product_id" value="<?= $it['product_id'] ?>">
                          <input type="hidden" name="order_item_id" value="<?= $it['id'] ?>">
                          <div class="modal-header border-bottom">
                            <h5 class="modal-title fw-bold fs-6">Ulas Produk: <?= htmlspecialchars($it['product_name_snapshot']) ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <div class="mb-3">
                              <label class="form-label small fw-bold">Rating Bintang (1 - 5):</label>
                              <select name="rating" class="form-select">
                                <option value="5">⭐⭐⭐⭐⭐ 5 Bintang - Sangat Puas</option>
                                <option value="4">⭐⭐⭐⭐ 4 Bintang - Puas</option>
                                <option value="3">⭐⭐⭐ 3 Bintang - Cukup</option>
                                <option value="2">⭐⭐ 2 Bintang - Kurang</option>
                                <option value="1">⭐ 1 Bintang - Kecewa</option>
                              </select>
                            </div>
                            <div class="mb-3">
                              <label class="form-label small fw-bold">Tulis Ulasan & Pengalaman:</label>
                              <textarea name="review_text" rows="3" class="form-control" placeholder="Ceritakan bagaimana keindahan produk, kualitas bahan, dan pengemasan..."></textarea>
                            </div>
                          </div>
                          <div class="modal-footer border-top">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-nnasa btn-sm">Kirim Ulasan</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Shipment & Tracking Info -->
      <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="fw-bold fs-6 mb-0 text-dark"><i class="bi bi-truck text-primary-pink me-2"></i> Pengiriman & Ekspedisi</h5>
          <a href="<?= BASE_URL ?>tracking.php?order_number=<?= urlencode($order['order_number']) ?>" class="btn btn-sm btn-nnasa-outline rounded-pill">
            <i class="bi bi-geo-alt me-1"></i> Pelacakan Live
          </a>
        </div>
        <div class="row g-3 small">
          <div class="col-sm-6">
            <span class="text-muted d-block">Kurir Pengiriman:</span>
            <strong class="text-dark"><?= htmlspecialchars($order['courier'] ?? 'SiCepat') ?> (<?= htmlspecialchars($order['service_type'] ?? 'REG') ?>)</strong>
          </div>
          <div class="col-sm-6">
            <span class="text-muted d-block">Nomor Resi:</span>
            <code class="fs-6 fw-bold text-primary-pink"><?= htmlspecialchars($order['tracking_number'] ?: 'Belum Tersedia (Sedang Dikemas)') ?></code>
          </div>
        </div>
      </div>

      <!-- Status Timeline History -->
      <div class="card border-0 shadow-sm rounded-4 p-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <h5 class="fw-bold fs-6 mb-3 text-dark"><i class="bi bi-clock-history text-primary-pink me-2"></i> Riwayat Status Pesanan</h5>
        <div class="position-relative ps-4" style="border-left: 2px solid var(--border-subtle);">
          <?php foreach ($statusHistory as $hist): ?>
            <div class="mb-3 position-relative">
              <span class="position-absolute rounded-circle" style="left: -22px; top: 3px; width: 12px; height: 12px; background: var(--primary-pink); border: 2px solid #ffffff;"></span>
              <div class="fw-bold small text-dark"><?= ucfirst($hist['status']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars($hist['comment'] ?? '') ?></div>
              <small class="text-muted" style="font-size: 0.72rem;"><?= date('d M Y, H:i:s', strtotime($hist['created_at'])) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <!-- Right Column: Address Snapshot & Cost Breakdown -->
    <div class="col-lg-4">
      
      <!-- Address Snapshot -->
      <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <h5 class="fw-bold fs-6 mb-3 text-dark"><i class="bi bi-geo-alt-fill text-primary-pink me-2"></i> Alamat Pengiriman</h5>
        <p class="small text-muted mb-0 leading-relaxed" style="white-space: pre-line;"><?= htmlspecialchars($order['address_snapshot']) ?></p>
        
        <?php if (!empty($order['notes'])): ?>
          <hr class="my-3 opacity-25">
          <small class="text-muted d-block"><strong>Catatan:</strong> <?= htmlspecialchars($order['notes']) ?></small>
        <?php endif; ?>
      </div>

      <!-- WhatsApp Status Notification Card -->
      <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 border border-success-subtle" style="background: #f8fff9; border: 1px solid rgba(37, 211, 102, 0.3) !important;">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="fw-bold fs-6 mb-0 text-success">
            <i class="bi bi-whatsapp me-2"></i> Notifikasi WhatsApp
          </h5>
          <span class="badge bg-success-subtle text-success border border-success">Terhubung</span>
        </div>
        <p class="small text-muted mb-2" style="font-size: 12px;">
          Pembaruan status pesanan dan resi pengiriman otomatis masuk ke nomor WhatsApp tujuan berikut:
        </p>
        <div class="p-2 bg-white rounded-3 border d-flex align-items-center justify-content-between mb-3 shadow-sm">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-telephone-fill text-success small"></i>
            <span class="font-monospace fw-bold text-dark small"><?= htmlspecialchars($customerOrderPhone ?: ($user['phone'] ?? '-')) ?></span>
          </div>
          <span class="badge bg-light text-muted border" style="font-size: 10px;">Nomor Tujuan</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-success rounded-pill w-100 fw-semibold py-2" id="btnCustomerCheckWa" onclick="triggerCustomerWaNotify(<?= $order['id'] ?>)">
          <i class="bi bi-bell-fill me-1"></i> Cek / Kirim Update ke WhatsApp
        </button>
      </div>

      <!-- Cost Breakdown -->
      <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <h5 class="fw-bold fs-6 mb-3 text-dark">Rincian Pembayaran</h5>
        
        <div class="d-flex justify-content-between small text-muted mb-2">
          <span>Subtotal Produk</span>
          <span class="text-dark fw-semibold"><?= format_rupiah($order['subtotal']) ?></span>
        </div>

        <div class="d-flex justify-content-between small text-muted mb-2">
          <span>Ongkos Kirim</span>
          <span class="text-dark fw-semibold"><?= format_rupiah($order['shipping_cost']) ?></span>
        </div>

        <?php if ((float)$order['discount_amount'] > 0): ?>
          <div class="d-flex justify-content-between small text-success mb-2">
            <span>Diskon Voucher</span>
            <span class="fw-bold">-<?= format_rupiah($order['discount_amount']) ?></span>
          </div>
        <?php endif; ?>

        <hr class="my-3 opacity-25">

        <div class="d-flex justify-content-between align-items-baseline mb-4">
          <span class="fw-bold text-dark">Total Pembayaran</span>
          <span class="h4 fw-bold text-primary-pink mb-0"><?= format_rupiah($order['total_amount']) ?></span>
        </div>

        <?php if ($order['payment_status'] === 'pending'): ?>
          <a href="<?= BASE_URL ?>payment.php?order_id=<?= $order['id'] ?>" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill mb-2">
            Bayar Sekarang <i class="bi bi-wallet2 ms-1"></i>
          </a>
          
          <form action="<?= BASE_URL ?>order-detail.php?order_id=<?= $order['id'] ?>" method="POST" onsubmit="return confirm('Apakah kamu yakin ingin membatalkan pesanan ini?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel_order">
            <button type="submit" class="btn btn-outline-danger w-100 py-2 rounded-pill small">
              Batalkan Pesanan
            </button>
          </form>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
function triggerCustomerWaNotify(orderId) {
  const btn = document.getElementById('btnCustomerCheckWa');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim ke WhatsApp...';
  }

  fetch(window.BASE_URL + 'api/order-whatsapp-notify.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId, event_type: 'payment_verified' })
  })
  .then(res => res.json())
  .then(data => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-bell-fill me-1"></i> Cek / Kirim Update ke WhatsApp';
    }
    if (data.success) {
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: data.destination_masked || data.phone || '<?= htmlspecialchars($customerOrderPhone ?: '') ?>',
          sender: 'nnasa_ca WhatsApp Official',
          message: data.simulated_message || data.message || 'Pembaruan status pesanan telah dikirim ke WhatsApp Anda.'
        });
      } else if (typeof window.showToast === 'function') {
        window.showToast(data.message || 'Notifikasi WhatsApp berhasil dikirim!', 'success');
      }
    } else {
      if (typeof window.showToast === 'function') {
        window.showToast(data.message || 'Gagal mengirim notifikasi WhatsApp.', 'danger');
      }
    }
  })
  .catch(err => {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-bell-fill me-1"></i> Cek / Kirim Update ke WhatsApp';
    }
    if (typeof window.showToast === 'function') {
      window.showToast('Kesalahan jaringan: ' + err.message, 'danger');
    }
  });
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
