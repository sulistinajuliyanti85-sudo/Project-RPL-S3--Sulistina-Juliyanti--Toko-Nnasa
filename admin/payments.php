<?php
/**
 * Admin Payments Management (Pembayaran)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Manajemen Pembayaran';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// Handle manual payment status verification
if (isset($_GET['mark_paid'])) {
    $payId = (int)$_GET['mark_paid'];
    $stmt = $db->prepare("SELECT order_id FROM payments WHERE id = ?");
    $stmt->execute([$payId]);
    $orderId = $stmt->fetchColumn();

    if ($orderId) {
        $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$payId]);
        $db->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing' WHERE id = ?")->execute([$orderId]);
        $db->prepare("INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES (?, 'processing', 'Pembayaran diverifikasi manual oleh Admin.', ?)")->execute([$orderId, $adminUser['id']]);
        
        log_activity($adminUser['id'], 'verify_payment', 'payments', $payId, "Verified payment for order {$orderId}");

        // Send WhatsApp verification notification to customer's exact order phone number
        require_once dirname(__DIR__) . '/app/Services/WhatsApp/WhatsAppService.php';
        $waService = new WhatsAppService($db);
        $waRes = $waService->sendOrderNotification($orderId, 'payment_verified', 'Diverifikasi langsung oleh Admin');

        $flashMsg = 'Status pembayaran berhasil diverifikasi sebagai Lunas.';
        if (!empty($waRes['destination_masked'])) {
            $flashMsg .= ' Notifikasi WhatsApp verifikasi pesanan diteruskan ke nomor ' . $waRes['destination_masked'] . ' 📲';
        }
        set_flash('success', $flashMsg);
    }
    header('Location: ' . ADMIN_URL . 'payments.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$where = "1=1";
$params = [];

if ($statusFilter !== 'all') {
    $where .= " AND p.status = ?";
    $params[] = $statusFilter;
}

$stmt = $db->prepare("
    SELECT p.*, o.order_number, u.name as customer_name, u.email as customer_email
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    JOIN users u ON o.user_id = u.id
    WHERE {$where}
    ORDER BY p.id DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Daftar Transaksi Pembayaran (<?= count($payments) ?>)</h4>
    <small class="text-muted">Periksa status pembayaran masuk dari QRIS, Virtual Account, dan E-Wallet</small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= ADMIN_URL ?>payments.php" class="btn btn-sm rounded-pill <?= $statusFilter === 'all' ? 'btn-nnasa' : 'btn-light border' ?>">Semua</a>
    <a href="<?= ADMIN_URL ?>payments.php?status=paid" class="btn btn-sm rounded-pill <?= $statusFilter === 'paid' ? 'btn-nnasa' : 'btn-light border' ?>">Lunas</a>
    <a href="<?= ADMIN_URL ?>payments.php?status=pending" class="btn btn-sm rounded-pill <?= $statusFilter === 'pending' ? 'btn-nnasa' : 'btn-light border' ?>">Menunggu</a>
    <a href="<?= ADMIN_URL ?>payments.php?status=failed" class="btn btn-sm rounded-pill <?= $statusFilter === 'failed' ? 'btn-nnasa' : 'btn-light border' ?>">Gagal</a>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Kode Referensi</th>
            <th>No Pesanan</th>
            <th>Pelanggan</th>
            <th>Metode</th>
            <th>Nominal</th>
            <th>Status</th>
            <th>Waktu Bayar</th>
            <th class="text-end">Aksi Verifikasi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($payments)): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">Belum ada transaksi pembayaran.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($payments as $pay): ?>
              <tr>
                <td><code><?= htmlspecialchars($pay['transaction_reference']) ?></code></td>
                <td>
                  <a href="<?= ADMIN_URL ?>order-detail.php?id=<?= $pay['order_id'] ?>" class="fw-bold text-dark text-decoration-none">
                    <?= htmlspecialchars($pay['order_number']) ?>
                  </a>
                </td>
                <td>
                  <span class="text-dark fw-semibold d-block"><?= htmlspecialchars($pay['customer_name']) ?></span>
                  <small class="text-muted"><?= htmlspecialchars($pay['customer_email']) ?></small>
                </td>
                <td>
                  <span class="badge bg-light text-dark border"><?= strtoupper(str_replace('_', ' ', $pay['payment_method'])) ?></span>
                </td>
                <td>
                  <strong class="text-primary-pink"><?= format_rupiah($pay['amount']) ?></strong>
                </td>
                <td><?= payment_status_badge($pay['status']) ?></td>
                <td>
                  <small class="text-muted">
                    <?= $pay['paid_at'] ? date('d/m/Y H:i', strtotime($pay['paid_at'])) : '<span class="text-warning">Menunggu</span>' ?>
                  </small>
                </td>
                <td class="text-end">
                  <div class="d-flex justify-content-end align-items-center gap-1">
                    <?php if ($pay['status'] !== 'paid'): ?>
                      <a href="<?= ADMIN_URL ?>payments.php?mark_paid=<?= $pay['id'] ?>" class="btn btn-sm btn-success rounded-pill px-3" onclick="return confirm('Verifikasi pembayaran ini sebagai lunas dan kirim notifikasi WhatsApp ke pemesan?');">
                        <i class="bi bi-check-lg me-1"></i> Konfirmasi Lunas
                      </a>
                    <?php else: ?>
                      <span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check-circle-fill me-1"></i> Terverifikasi</span>
                      <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-2 py-0" title="Kirim Ulang Notifikasi WhatsApp ke Pemesan" onclick="sendAdminWaNotif(<?= $pay['order_id'] ?>, 'payment_verified')">
                        <i class="bi bi-whatsapp"></i>
                      </button>
                    <?php endif; ?>
                  </div>
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
function sendAdminWaNotif(orderId, eventType) {
  if (typeof window.showToast === 'function') {
    window.showToast('Mengirim notifikasi WhatsApp ke pemesan...', 'info');
  }

  fetch('<?= BASE_URL ?>api/order-whatsapp-notify.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId, event_type: eventType })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: data.destination_masked || data.phone || '',
          sender: 'nnasa_ca WhatsApp Gateway',
          message: data.simulated_message || data.message || 'Notifikasi WhatsApp pesanan terkirim.'
        });
      } else if (typeof window.showToast === 'function') {
        window.showToast(data.message || 'Notifikasi WhatsApp berhasil dikirim!', 'success');
      }
    } else {
      if (typeof window.showToast === 'function') {
        window.showToast(data.message || 'Gagal mengirim WhatsApp.', 'danger');
      }
    }
  })
  .catch(err => {
    if (typeof window.showToast === 'function') {
      window.showToast('Kesalahan: ' + err.message, 'danger');
    }
  });
}
</script>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
