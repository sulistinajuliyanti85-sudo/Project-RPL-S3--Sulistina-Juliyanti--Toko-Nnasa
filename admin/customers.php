<?php
/**
 * Admin Customer Management (Pelanggan)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Daftar Pelanggan';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

$stmt = $db->query("
    SELECT u.*,
           COUNT(o.id) as total_orders,
           COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spent
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id
    WHERE u.role = 'customer'
    GROUP BY u.id
    ORDER BY total_spent DESC, u.id DESC
");
$customers = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Pelanggan Terdaftar (<?= count($customers) ?>)</h4>
    <small class="text-muted">Daftar pelanggan toko nnasa_ca beserta riwayat belanja dan total transaksi</small>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Pelanggan</th>
            <th>Email</th>
            <th>No Telepon</th>
            <th>Total Pesanan</th>
            <th>Total Belanja Valid</th>
            <th>Status Akun</th>
            <th>Bergabung Pada</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($customers)): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">Belum ada pelanggan terdaftar.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($customers as $c): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 36px; height: 36px; background: var(--primary-pink); font-size: 0.9rem;">
                      <?= strtoupper(substr($c['name'], 0, 1)) ?>
                    </div>
                    <strong class="text-dark small"><?= htmlspecialchars($c['name']) ?></strong>
                  </div>
                </td>
                <td><small class="text-muted"><?= htmlspecialchars($c['email']) ?></small></td>
                <td><small class="text-muted"><?= htmlspecialchars($c['phone'] ?? '-') ?></small></td>
                <td>
                  <span class="badge bg-light text-dark border"><?= $c['total_orders'] ?> pesanan</span>
                </td>
                <td>
                  <strong class="text-primary-pink"><?= format_rupiah($c['total_spent']) ?></strong>
                </td>
                <td>
                  <span class="badge <?= $c['status'] === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger' ?>">
                    <?= ucfirst($c['status']) ?>
                  </span>
                </td>
                <td><small class="text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></small></td>
                <td class="text-end">
                  <a href="<?= ADMIN_URL ?>orders.php?q=<?= urlencode($c['email']) ?>" class="btn btn-sm btn-light border rounded-pill px-3" title="Lihat Pesanan Pelanggan Ini">
                    <i class="bi bi-box-seam me-1"></i> Pesanan
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
