<?php
/**
 * Admin Product Reviews Moderation (Ulasan Produk)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Ulasan Produk';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// Handle status change
if (isset($_GET['status_change']) && isset($_GET['review_id'])) {
    $revId = (int)$_GET['review_id'];
    $newStatus = in_array($_GET['status_change'], ['approved', 'pending', 'hidden']) ? $_GET['status_change'] : 'approved';
    $db->prepare("UPDATE reviews SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $revId]);
    set_flash('success', "Status ulasan berhasil diubah menjadi {$newStatus}.");
    header('Location: ' . ADMIN_URL . 'reviews.php');
    exit;
}

// Handle delete
if (isset($_GET['delete'])) {
    $revId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM reviews WHERE id = ?")->execute([$revId]);
    set_flash('info', 'Ulasan berhasil dihapus.');
    header('Location: ' . ADMIN_URL . 'reviews.php');
    exit;
}

$stmt = $db->query("
    SELECT r.*, u.name as customer_name, u.email as customer_email, p.name as product_name, p.main_image
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    JOIN products p ON r.product_id = p.id
    ORDER BY r.id DESC
");
$reviews = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Moderasi Ulasan Produk (<?= count($reviews) ?>)</h4>
    <small class="text-muted">Kelola rating dan ulasan pelanggan untuk menjaga reputasi dan kualitas toko</small>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Produk</th>
            <th>Pelanggan</th>
            <th>Bintang</th>
            <th>Isi Ulasan</th>
            <th>Status</th>
            <th>Tanggal</th>
            <th class="text-end">Aksi Moderasi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($reviews)): ?>
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">Belum ada ulasan produk.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($reviews as $rev): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <img src="<?= get_image_url('products', $rev['main_image'], $rev['product_name']) ?>" class="rounded-2 border flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover;">
                    <strong class="text-dark small text-truncate" style="max-width: 180px;"><?= htmlspecialchars($rev['product_name']) ?></strong>
                  </div>
                </td>
                <td>
                  <span class="text-dark fw-semibold small d-block"><?= htmlspecialchars($rev['customer_name']) ?></span>
                  <small class="text-muted"><?= htmlspecialchars($rev['customer_email']) ?></small>
                </td>
                <td>
                  <span class="text-warning small text-nowrap">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <i class="bi bi-star<?= $i <= $rev['rating'] ? '-fill' : '' ?>"></i>
                    <?php endfor; ?>
                  </span>
                  <small class="text-dark fw-bold ms-1">(<?= $rev['rating'] ?>.0)</small>
                </td>
                <td>
                  <p class="small text-muted mb-0" style="max-width: 300px;"><?= htmlspecialchars($rev['review_text'] ?? '-') ?></p>
                </td>
                <td>
                  <span class="badge <?= $rev['status'] === 'approved' ? 'bg-success-subtle text-success border border-success-subtle' : ($rev['status'] === 'hidden' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') ?>">
                    <?= ucfirst($rev['status']) ?>
                  </span>
                </td>
                <td><small class="text-muted"><?= date('d/m/Y', strtotime($rev['created_at'])) ?></small></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <?php if ($rev['status'] !== 'approved'): ?>
                      <a href="<?= ADMIN_URL ?>reviews.php?status_change=approved&review_id=<?= $rev['id'] ?>" class="btn btn-light border text-success" title="Setujui / Tampilkan">
                        <i class="bi bi-check-lg"></i>
                      </a>
                    <?php endif; ?>
                    <?php if ($rev['status'] !== 'hidden'): ?>
                      <a href="<?= ADMIN_URL ?>reviews.php?status_change=hidden&review_id=<?= $rev['id'] ?>" class="btn btn-light border text-warning" title="Sembunyikan">
                        <i class="bi bi-eye-slash"></i>
                      </a>
                    <?php endif; ?>
                    <a href="<?= ADMIN_URL ?>reviews.php?delete=<?= $rev['id'] ?>" class="btn btn-light border text-danger" onclick="return confirm('Hapus ulasan ini permanen?');" title="Hapus">
                      <i class="bi bi-trash3"></i>
                    </a>
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
