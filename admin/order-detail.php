<?php
/**
 * Admin Order Detail & Processing
 * Store: nnasa_ca Accessories Marketplace
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

require_admin();
$adminUser = current_user();

$orderId = (int)($_GET['id'] ?? 0);
$db = getDB();

// Fetch order
$stmt = $db->prepare("
    SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
           p.payment_method, p.transaction_reference, p.paid_at,
           s.courier, s.service_type, s.tracking_number, s.shipping_status
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN payments p ON o.id = p.order_id
    LEFT JOIN shipments s ON o.id = s.order_id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'Pesanan tidak ditemukan.');
    header('Location: ' . ADMIN_URL . 'orders.php');
    exit;
}

// Extract exact customer phone from address_snapshot or users
$orderPhone = null;
if (!empty($order['address_snapshot'])) {
    if (preg_match('/\|\s*([0-9\+\-\s]{8,25})(?:\r?\n|$)/', $order['address_snapshot'], $mPhone)) {
        $orderPhone = trim($mPhone[1]);
    }
}
if (!$orderPhone && !empty($order['customer_phone'])) {
    $orderPhone = trim($order['customer_phone']);
}

// Handle Order Status & Resi Updates
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $action = $_POST['action'];

        if ($action === 'update_order_status') {
            $newOrderStatus = $_POST['order_status'];
            $newPaymentStatus = $_POST['payment_status'];
            $courier = sanitize($_POST['courier'] ?? $order['courier'] ?? 'SiCepat');
            $trackingNumber = sanitize($_POST['tracking_number'] ?? '');
            $adminComment = sanitize($_POST['comment'] ?? '');

            try {
                $db->beginTransaction();

                // If cancelling, return stock
                if ($newOrderStatus === 'cancelled' && $order['order_status'] !== 'cancelled') {
                    $items = $db->prepare("SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?");
                    $items->execute([$orderId]);
                    $orderItemsList = $items->fetchAll();

                    $pReturn = $db->prepare("UPDATE products SET stock = stock + ?, sales_count = GREATEST(0, sales_count - ?) WHERE id = ?");
                    $vReturn = $db->prepare("UPDATE product_variants SET stock = stock + ? WHERE id = ?");
                    $smReturn = $db->prepare("INSERT INTO stock_movements (product_id, variant_id, movement_type, quantity, reference_type, reference_id, notes, created_by) VALUES (?, ?, 'return', ?, 'order', ?, 'Pengembalian stok pembatalan admin', ?)");

                    foreach ($orderItemsList as $oi) {
                        $pReturn->execute([$oi['quantity'], $oi['quantity'], $oi['product_id']]);
                        if ($oi['variant_id']) {
                            $vReturn->execute([$oi['quantity'], $oi['variant_id']]);
                        }
                        $smReturn->execute([$oi['product_id'], $oi['variant_id'], $oi['quantity'], $orderId, $adminUser['id']]);
                    }
                }

                // Update orders table
                $db->prepare("
                    UPDATE orders 
                    SET order_status = ?, payment_status = ?, updated_at = NOW()
                    WHERE id = ?
                ")->execute([$newOrderStatus, $newPaymentStatus, $orderId]);

                // Update payments table
                if ($newPaymentStatus === 'paid' && $order['payment_status'] !== 'paid') {
                    $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE order_id = ?")->execute([$orderId]);
                } elseif ($newPaymentStatus !== 'paid') {
                    $db->prepare("UPDATE payments SET status = ? WHERE order_id = ?")->execute([$newPaymentStatus, $orderId]);
                }

                // Update shipments table
                $shippingStatus = match($newOrderStatus) {
                    'shipped' => 'shipped',
                    'completed', 'delivered' => 'delivered',
                    default => 'preparing'
                };
                $shippedAt = ($newOrderStatus === 'shipped') ? date('Y-m-d H:i:s') : null;

                $db->prepare("
                    UPDATE shipments 
                    SET courier = ?, tracking_number = ?, shipping_status = ?, updated_at = NOW()
                    WHERE order_id = ?
                ")->execute([$courier, $trackingNumber, $shippingStatus, $orderId]);

                // Insert into status history
                $fullComment = $adminComment ?: "Status diperbarui menjadi {$newOrderStatus} (Pembayaran: {$newPaymentStatus}).";
                if ($trackingNumber && $trackingNumber !== $order['tracking_number']) {
                    $fullComment .= " No. Resi: {$trackingNumber}.";
                }
                $db->prepare("
                    INSERT INTO order_status_history (order_id, status, comment, created_by)
                    VALUES (?, ?, ?, ?)
                ")->execute([$orderId, $newOrderStatus, $fullComment, $adminUser['id']]);

                log_activity($adminUser['id'], 'update_order', 'orders', $orderId, "Updated order {$order['order_number']} status to {$newOrderStatus}");

                // Dispatch WhatsApp order notification to customer's exact order phone number
                require_once dirname(__DIR__) . '/app/Services/WhatsApp/WhatsAppService.php';
                $waService = new WhatsAppService($db);
                $eventType = match($newOrderStatus) {
                    'shipped' => 'order_shipped',
                    'completed', 'delivered' => 'order_completed',
                    'processing' => 'order_processing',
                    'cancelled' => 'order_cancelled',
                    default => ($newPaymentStatus === 'paid' ? 'payment_verified' : 'order_status_updated')
                };
                $waRes = $waService->sendOrderNotification($orderId, $eventType, $adminComment);

                $db->commit();

                $flashMsg = 'Status pesanan dan nomor resi berhasil diperbarui! 🚀';
                if (!empty($waRes['destination_masked'])) {
                    $flashMsg .= ' Notifikasi WhatsApp status terkirim ke ' . $waRes['destination_masked'] . ' 📲';
                }
                set_flash('success', $flashMsg);
                header('Location: ' . ADMIN_URL . 'order-detail.php?id=' . $orderId);
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', 'Gagal memperbarui pesanan: ' . $e->getMessage());
            }
        }
    }
}

// Fetch order items
$stmtItems = $db->prepare("
    SELECT oi.*, p.main_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$stmtItems->execute([$orderId]);
$orderItems = $stmtItems->fetchAll();

// Fetch status history
$stmtHist = $db->prepare("
    SELECT osh.*, u.name as admin_name
    FROM order_status_history osh
    LEFT JOIN users u ON osh.created_by = u.id
    WHERE osh.order_id = ?
    ORDER BY osh.created_at ASC
");
$stmtHist->execute([$orderId]);
$statusHistory = $stmtHist->fetchAll();

$adminPageTitle = 'Kelola Pesanan: ' . $order['order_number'];
require_once dirname(__DIR__) . '/templates/admin_header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Pesanan: <?= htmlspecialchars($order['order_number']) ?></h4>
    <small class="text-muted">Tanggal: <?= date('d F Y, H:i:s', strtotime($order['created_at'])) ?> &bull; Pelanggan: <?= htmlspecialchars($order['customer_name']) ?></small>
  </div>
  <a href="<?= ADMIN_URL ?>orders.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
    <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
  </a>
</div>

<div class="row g-4">
  <!-- Left Column: Items, Address, History -->
  <div class="col-lg-8">
    
    <!-- Items Table -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h5 class="admin-card-title"><i class="bi bi-box-seam text-primary-pink me-2"></i> Produk yang Dipesan</h5>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-admin">
            <thead>
              <tr>
                <th>Produk</th>
                <th>SKU</th>
                <th>Harga Satuan</th>
                <th>Kuantitas</th>
                <th class="text-end">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orderItems as $item): ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <img src="<?= get_image_url('products', $item['main_image'], $item['product_name_snapshot']) ?>" class="rounded-2 border flex-shrink-0" style="width: 42px; height: 42px; object-fit: cover;">
                      <div class="min-w-0">
                        <strong class="text-dark small d-block"><?= htmlspecialchars($item['product_name_snapshot']) ?></strong>
                        <?php if ($item['variant_snapshot']): ?>
                          <span class="badge bg-light text-muted border" style="font-size: 0.7rem;"><?= htmlspecialchars($item['variant_snapshot']) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td><code><?= htmlspecialchars($item['sku_snapshot'] ?: '-') ?></code></td>
                  <td><?= format_rupiah($item['price']) ?></td>
                  <td><strong><?= $item['quantity'] ?> pcs</strong></td>
                  <td class="text-end fw-bold text-dark"><?= format_rupiah($item['subtotal']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Address & Notes -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h5 class="admin-card-title"><i class="bi bi-geo-alt-fill text-primary-pink me-2"></i> Snapshot Alamat &amp; Data Pelanggan</h5>
      </div>
      <div class="admin-card-body">
        <div class="row g-3 small">
          <div class="col-md-6">
            <span class="text-muted d-block fw-bold">Kontak Pembeli:</span>
            <div class="text-dark"><strong><?= htmlspecialchars($order['customer_name']) ?></strong></div>
            <div class="text-muted"><?= htmlspecialchars($order['customer_email']) ?> &bull; <?= htmlspecialchars($order['customer_phone'] ?? '-') ?></div>
          </div>
          <div class="col-md-6">
            <span class="text-muted d-block fw-bold">Alamat Pengiriman Lengkap:</span>
            <p class="mb-0 text-dark leading-relaxed" style="white-space: pre-line;"><?= htmlspecialchars($order['address_snapshot']) ?></p>
          </div>
          <?php if (!empty($order['notes'])): ?>
            <div class="col-12 pt-2 border-top">
              <span class="text-muted fw-bold">Catatan Pembeli:</span>
              <p class="mb-0 text-secondary italic"><?= htmlspecialchars($order['notes']) ?></p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- History Log Timeline -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h5 class="admin-card-title"><i class="bi bi-clock-history text-primary-pink me-2"></i> Riwayat Perubahan Status Pesanan</h5>
      </div>
      <div class="admin-card-body">
        <div class="position-relative ps-4" style="border-left: 2px solid var(--admin-border);">
          <?php foreach ($statusHistory as $hist): ?>
            <div class="mb-3 position-relative">
              <span class="position-absolute rounded-circle" style="left: -22px; top: 3px; width: 12px; height: 12px; background: var(--admin-primary); border: 2px solid #ffffff;"></span>
              <div class="fw-bold small text-dark"><?= ucfirst($hist['status']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars($hist['comment'] ?? '') ?></div>
              <small class="text-muted" style="font-size: 0.72rem;">
                <?= date('d/m/Y H:i:s', strtotime($hist['created_at'])) ?> &bull; Oleh: <?= htmlspecialchars($hist['admin_name'] ?? 'Sistem/Pelanggan') ?>
              </small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- Right Column: Processing Actions & Finance Summary -->
  <div class="col-lg-4">
    
    <!-- Action Form -->
    <div class="admin-card mb-4">
      <div class="admin-card-header bg-light">
        <h5 class="admin-card-title fs-6"><i class="bi bi-gear-fill text-primary-pink me-2"></i> Pemrosesan Pesanan</h5>
      </div>
      <div class="admin-card-body">
        <form action="<?= ADMIN_URL ?>order-detail.php?id=<?= $orderId ?>" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_order_status">

          <div class="mb-3">
            <label class="form-label small fw-bold">Status Pesanan</label>
            <select name="order_status" class="form-select">
              <option value="pending" <?= $order['order_status'] === 'pending' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
              <option value="processing" <?= $order['order_status'] === 'processing' ? 'selected' : '' ?>>Diproses (Packing)</option>
              <option value="shipped" <?= $order['order_status'] === 'shipped' ? 'selected' : '' ?>>Sedang Dikirim (Shipped)</option>
              <option value="completed" <?= in_array($order['order_status'], ['completed', 'delivered']) ? 'selected' : '' ?>>Selesai (Completed)</option>
              <option value="cancelled" <?= $order['order_status'] === 'cancelled' ? 'selected' : '' ?>>Batalkan Pesanan</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Status Pembayaran</label>
            <select name="payment_status" class="form-select">
              <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Belum Bayar (Pending)</option>
              <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Lunas (Paid)</option>
              <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Gagal / Batal (Failed)</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Ekspedisi Kurir</label>
            <select name="courier" class="form-select">
              <option value="SiCepat" <?= ($order['courier'] ?? '') === 'SiCepat' ? 'selected' : '' ?>>SiCepat</option>
              <option value="JNE" <?= ($order['courier'] ?? '') === 'JNE' ? 'selected' : '' ?>>JNE</option>
              <option value="J&T Express" <?= ($order['courier'] ?? '') === 'J&T Express' ? 'selected' : '' ?>>J&T Express</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold">Nomor Resi Pengiriman</label>
            <input type="text" name="tracking_number" class="form-control text-uppercase" placeholder="Contoh: SC0192837465" value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>">
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold">Catatan Pembaruan (Opsional)</label>
            <textarea name="comment" rows="2" class="form-control" placeholder="Contoh: Paket sudah diserahkan ke drop point SiCepat..."></textarea>
          </div>

          <button type="submit" class="btn btn-nnasa w-100 py-2 rounded-pill fw-bold">
            Perbarui Status &amp; Resi
          </button>
        </form>
      </div>
    </div>

    <!-- WhatsApp Customer Notification Dispatch Card -->
    <div class="admin-card mb-4 border border-success-subtle shadow-sm" style="background: #f8fff9;">
      <div class="admin-card-header bg-white border-bottom border-success-subtle d-flex justify-content-between align-items-center">
        <h5 class="admin-card-title fs-6 mb-0 text-success">
          <i class="bi bi-whatsapp me-2"></i> Notifikasi WhatsApp
        </h5>
        <span class="badge bg-success-subtle text-success border border-success">Aktif</span>
      </div>
      <div class="admin-card-body small">
        <div class="mb-3">
          <span class="text-muted d-block mb-1">Nomor WhatsApp Pemesan:</span>
          <div class="d-flex align-items-center gap-2">
            <span class="fw-bold font-monospace text-dark fs-6 bg-white px-2 py-1 rounded border shadow-sm">
              <i class="bi bi-telephone-fill text-success me-1 small"></i> <?= htmlspecialchars($orderPhone ?: ($order['customer_phone'] ?? '-')) ?>
            </span>
            <span class="badge bg-light text-muted border">Tercantum di Pesanan</span>
          </div>
          <small class="text-muted d-block mt-1" style="font-size: 11px;">
            <i class="bi bi-shield-check text-success me-1"></i> Pesan verifikasi dan pembaruan resi langsung dikirim ke nomor WhatsApp pemesan ini.
          </small>
        </div>

        <div class="d-flex flex-column gap-2">
          <button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-semibold py-2" id="btnAdminWaProcess" onclick="sendAdminWaNotif(<?= $orderId ?>, 'order_processing')">
            <i class="bi bi-box-seam me-1"></i> Kirim Notif Diproses (Packing)
          </button>
          <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-semibold py-2" id="btnAdminWaShipped" onclick="sendAdminWaNotif(<?= $orderId ?>, 'order_shipped')">
            <i class="bi bi-truck me-1"></i> Kirim Notif Sedang Dikirim (Shipped)
          </button>
          <button type="button" class="btn btn-sm btn-success rounded-pill fw-semibold py-2" id="btnAdminWaDone" onclick="sendAdminWaNotif(<?= $orderId ?>, 'order_completed')">
            <i class="bi bi-check-circle-fill me-1"></i> Kirim Notif Selesai (Completed)
          </button>
          <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill py-2" id="btnAdminWaPaid" onclick="sendAdminWaNotif(<?= $orderId ?>, 'payment_verified')">
            <i class="bi bi-cash-stack me-1"></i> Kirim Notif Pembayaran Lunas
          </button>
        </div>

        <div id="waAdminAlert" class="mt-3"></div>
      </div>
    </div>

    <!-- Financial Breakdown -->
    <div class="admin-card">
      <div class="admin-card-header">
        <h5 class="admin-card-title fs-6">Rincian Keuangan</h5>
      </div>
      <div class="admin-card-body small">
        <div class="d-flex justify-content-between mb-2 text-muted">
          <span>Subtotal Item:</span>
          <span class="text-dark fw-semibold"><?= format_rupiah($order['subtotal']) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2 text-muted">
          <span>Ongkos Kirim:</span>
          <span class="text-dark fw-semibold"><?= format_rupiah($order['shipping_cost']) ?></span>
        </div>
        <?php if ((float)$order['discount_amount'] > 0): ?>
          <div class="d-flex justify-content-between mb-2 text-success">
            <span>Diskon Voucher:</span>
            <span class="fw-bold">-<?= format_rupiah($order['discount_amount']) ?></span>
          </div>
        <?php endif; ?>

        <hr class="my-3 opacity-25">

        <div class="d-flex justify-content-between align-items-baseline mb-3">
          <span class="fw-bold text-dark">Total Tagihan:</span>
          <span class="h5 fw-bold text-primary-pink mb-0"><?= format_rupiah($order['total_amount']) ?></span>
        </div>

        <div class="p-2 bg-light rounded-3 text-center border">
          <small class="text-muted d-block">Metode Bayar: <strong><?= strtoupper(str_replace('_', ' ', $order['payment_method'] ?? 'QRIS')) ?></strong></small>
          <small class="text-muted d-block">Ref: <code><?= htmlspecialchars($order['transaction_reference'] ?? '-') ?></code></small>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
function sendAdminWaNotif(orderId, eventType) {
  const container = document.getElementById('waAdminAlert');
  if (container) {
    container.innerHTML = '<div class="alert alert-info py-2 px-3 small rounded-3 mb-0"><span class="spinner-border spinner-border-sm me-1"></span> Mengirim notifikasi WhatsApp ke pemesan...</div>';
  }

  fetch('<?= BASE_URL ?>api/order-whatsapp-notify.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId, event_type: eventType })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      if (container) {
        container.innerHTML = `
          <div class="alert alert-success py-2 px-3 small rounded-3 mb-0 border-0 shadow-sm">
            <i class="bi bi-check-circle-fill me-1"></i> ${escapeHtml(data.message || 'Notifikasi WhatsApp berhasil dikirim!')}
          </div>
        `;
      }
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: data.destination_masked || data.phone || '<?= htmlspecialchars($orderPhone ?: ($order['customer_phone'] ?? '')) ?>',
          sender: 'nnasa_ca WhatsApp Gateway',
          message: data.simulated_message || data.message || 'Notifikasi WhatsApp pesanan berhasil dikirim.'
        });
      } else if (typeof window.showToast === 'function') {
        window.showToast(data.message || 'Notifikasi WhatsApp berhasil dikirim!', 'success');
      }
    } else {
      if (container) {
        container.innerHTML = `
          <div class="alert alert-danger py-2 px-3 small rounded-3 mb-0 border-0">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> ${escapeHtml(data.message || 'Gagal mengirim WhatsApp.')}
          </div>
        `;
      }
    }
  })
  .catch(err => {
    if (container) {
      container.innerHTML = `<div class="alert alert-danger py-2 px-3 small rounded-3 mb-0">Kesalahan: ${escapeHtml(err.message)}</div>`;
    }
  });
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
