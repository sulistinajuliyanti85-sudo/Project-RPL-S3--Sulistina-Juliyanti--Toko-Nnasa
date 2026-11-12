<?php
/**
 * Admin Shipments Management (Pengiriman dan Resi)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Pengiriman dan Resi';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// Handle quick tracking number update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tracking') {
    if (csrf_verify($_POST['csrf_token'] ?? '')) {
        $shipmentId = (int)$_POST['shipment_id'];
        $trackingNumber = strtoupper(trim(sanitize($_POST['tracking_number'] ?? '')));
        $courier = sanitize($_POST['courier'] ?? 'SiCepat');
        $shippingStatus = $_POST['shipping_status'] ?? 'shipped';

        $db->prepare("
            UPDATE shipments 
            SET courier = ?, tracking_number = ?, shipping_status = ?, updated_at = NOW() 
            WHERE id = ?
        ")->execute([$courier, $trackingNumber, $shippingStatus, $shipmentId]);

        // Also update orders table if shipped
        $stmt = $db->prepare("SELECT order_id FROM shipments WHERE id = ?");
        $stmt->execute([$shipmentId]);
        $orderId = $stmt->fetchColumn();

        if ($orderId && $shippingStatus === 'shipped') {
            $db->prepare("UPDATE orders SET order_status = 'shipped' WHERE id = ?")->execute([$orderId]);
            $db->prepare("INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES (?, 'shipped', ?, ?)")
               ->execute([$orderId, "Paket telah dikirim via {$courier} (Resi: {$trackingNumber})", $adminUser['id']]);
        }

        set_flash('success', 'Nomor resi dan kurir berhasil diperbarui! 🚚');
        header('Location: ' . ADMIN_URL . 'shipments.php');
        exit;
    }
}

$stmt = $db->prepare("
    SELECT s.*, o.order_number, o.created_at as order_date, u.name as customer_name, u.phone as customer_phone
    FROM shipments s
    JOIN orders o ON s.order_id = o.id
    JOIN users u ON o.user_id = u.id
    ORDER BY s.id DESC
");
$stmt->execute();
$shipments = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Pengiriman &amp; Nomor Resi (<?= count($shipments) ?>)</h4>
    <small class="text-muted">Pantau status kurir dan input nomor resi pengiriman untuk pelanggan</small>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>No Pesanan</th>
            <th>Penerima</th>
            <th>Kurir</th>
            <th>Layanan</th>
            <th>Nomor Resi</th>
            <th>Status Pengiriman</th>
            <th>Tanggal Order</th>
            <th class="text-end">Aksi Resi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($shipments)): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">Belum ada data pengiriman.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($shipments as $sh): ?>
              <tr>
                <td>
                  <a href="<?= ADMIN_URL ?>order-detail.php?id=<?= $sh['order_id'] ?>" class="fw-bold text-dark text-decoration-none">
                    <?= htmlspecialchars($sh['order_number']) ?>
                  </a>
                </td>
                <td>
                  <strong class="text-dark small d-block"><?= htmlspecialchars($sh['customer_name']) ?></strong>
                  <small class="text-muted"><?= htmlspecialchars($sh['customer_phone'] ?? '-') ?></small>
                </td>
                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($sh['courier']) ?></span></td>
                <td><small class="text-muted"><?= htmlspecialchars($sh['service_type'] ?? 'REG') ?></small></td>
                <td>
                  <?php if (!empty($sh['tracking_number'])): ?>
                    <code class="fw-bold fs-6 text-primary-pink"><?= htmlspecialchars($sh['tracking_number']) ?></code>
                  <?php else: ?>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Belum Ada Resi</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge <?= $sh['shipping_status'] === 'delivered' ? 'bg-success' : ($sh['shipping_status'] === 'shipped' ? 'bg-primary' : 'bg-secondary') ?>">
                    <?= ucfirst($sh['shipping_status']) ?>
                  </span>
                </td>
                <td><small class="text-muted"><?= date('d/m/Y', strtotime($sh['order_date'])) ?></small></td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-nnasa-outline rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#resiModal<?= $sh['id'] ?>">
                    <i class="bi bi-pencil-square me-1"></i> Input Resi
                  </button>

                  <!-- Modal Input Resi -->
                  <div class="modal fade text-start" id="resiModal<?= $sh['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content rounded-4 border-0 shadow">
                        <form action="<?= ADMIN_URL ?>shipments.php" method="POST">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="update_tracking">
                          <input type="hidden" name="shipment_id" value="<?= $sh['id'] ?>">

                          <div class="modal-header border-bottom">
                            <h6 class="modal-title fw-bold">Update Resi: <?= htmlspecialchars($sh['order_number']) ?></h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <div class="mb-3">
                              <label class="form-label small fw-bold">Kurir Ekspedisi</label>
                              <select name="courier" class="form-select">
                                <option value="SiCepat" <?= $sh['courier'] === 'SiCepat' ? 'selected' : '' ?>>SiCepat Express</option>
                                <option value="JNE" <?= $sh['courier'] === 'JNE' ? 'selected' : '' ?>>JNE</option>
                                <option value="J&T Express" <?= $sh['courier'] === 'J&T Express' ? 'selected' : '' ?>>J&T Express</option>
                              </select>
                            </div>
                            <div class="mb-3">
                              <label class="form-label small fw-bold">Nomor Resi / AWB</label>
                              <input type="text" name="tracking_number" class="form-control text-uppercase" placeholder="Contoh: SC0192837465" value="<?= htmlspecialchars($sh['tracking_number'] ?? '') ?>" required>
                            </div>
                            <div class="mb-3">
                              <label class="form-label small fw-bold">Status Pengiriman</label>
                              <select name="shipping_status" class="form-select">
                                <option value="preparing" <?= $sh['shipping_status'] === 'preparing' ? 'selected' : '' ?>>Sedang Disiapkan</option>
                                <option value="shipped" <?= $sh['shipping_status'] === 'shipped' ? 'selected' : '' ?>>Sedang Dikirim (Shipped)</option>
                                <option value="delivered" <?= $sh['shipping_status'] === 'delivered' ? 'selected' : '' ?>>Telah Diterima (Delivered)</option>
                              </select>
                            </div>
                          </div>
                          <div class="modal-footer border-top">
                            <button type="submit" class="btn btn-sm btn-nnasa w-100 rounded-pill">Simpan Resi</button>
                          </div>
                        </form>
                      </div>
                    </div>
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

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
