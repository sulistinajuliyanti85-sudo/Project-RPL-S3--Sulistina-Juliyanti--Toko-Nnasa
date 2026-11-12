<?php
/**
 * Admin Stock Management & Movements (Manajemen Stok)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Manajemen Stok & Mutasi';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// Handle Stock Adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $productId = (int)$_POST['product_id'];
        $movementType = $_POST['movement_type'] ?? 'restock';
        $quantity = (int)$_POST['quantity'];
        $notes = sanitize($_POST['notes'] ?? '');

        if ($productId <= 0 || $quantity == 0) {
            set_flash('danger', 'Produk dan kuantitas penyesuaian tidak boleh nol.');
        } else {
            try {
                $db->beginTransaction();

                // If movementType is adjustment or return or restock
                // quantity passed is added (or subtracted if user entered negative)
                $stmt = $db->prepare("UPDATE products SET stock = GREATEST(0, stock + ?) WHERE id = ?");
                $stmt->execute([$quantity, $productId]);

                // Insert movement log
                $mStmt = $db->prepare("
                    INSERT INTO stock_movements (product_id, movement_type, quantity, reference_type, notes, created_by)
                    VALUES (?, ?, ?, 'manual_adjustment', ?, ?)
                ");
                $mStmt->execute([$productId, $movementType, $quantity, $notes, $adminUser['id']]);

                log_activity($adminUser['id'], 'adjust_stock', 'products', $productId, "Adjusted stock {$quantity} ({$movementType})");

                $db->commit();
                set_flash('success', 'Stok berhasil diperbarui dan mutasi tercatat! 📦');
                header('Location: ' . ADMIN_URL . 'stock.php');
                exit;

            } catch (Exception $e) {
                $db->rollBack();
                set_flash('danger', 'Gagal menyesuaikan stok: ' . $e->getMessage());
            }
        }
    }
}

// Low stock items
$lowStockProducts = $db->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.stock <= 5 AND p.status = 'active' 
    ORDER BY p.stock ASC
")->fetchAll();

// All products list for adjustment dropdown
$allProducts = $db->query("SELECT id, name, sku, stock FROM products WHERE status != 'archived' ORDER BY name ASC")->fetchAll();

// Recent Stock Movements (Audit Log)
$movements = $db->query("
    SELECT sm.*, p.name as product_name, p.sku as product_sku, u.name as admin_name
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    LEFT JOIN users u ON sm.created_by = u.id
    ORDER BY sm.id DESC
    LIMIT 20
")->fetchAll();
?>

<div class="row g-4 mb-4">
  <!-- Stock Adjustment Form Card -->
  <div class="col-lg-5">
    <div class="admin-card h-100">
      <div class="admin-card-header">
        <h5 class="admin-card-title"><i class="bi bi-box-arrow-in-down text-primary-pink me-2"></i> Penyesuaian &amp; Restock Barang</h5>
      </div>
      <div class="admin-card-body">
        <form action="<?= ADMIN_URL ?>stock.php" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="adjust_stock">

          <div class="mb-3">
            <label class="form-label small fw-bold">Pilih Produk Aksesoris <span class="text-danger">*</span></label>
            <select name="product_id" class="form-select" required>
              <option value="">-- Pilih Produk --</option>
              <?php foreach ($allProducts as $ap): ?>
                <option value="<?= $ap['id'] ?>">
                  <?= htmlspecialchars($ap['name']) ?> (Stok saat ini: <?= $ap['stock'] ?> pcs)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Jenis Mutasi</label>
              <select name="movement_type" class="form-select">
                <option value="restock">Restock (Barang Masuk)</option>
                <option value="adjustment">Koreksi / Penyesuaian</option>
                <option value="return">Retur Pelanggan</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Jumlah (+/- Unit) <span class="text-danger">*</span></label>
              <input type="number" name="quantity" class="form-control" placeholder="Contoh: 20 atau -5" required>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold">Keterangan / Catatan Mutasi</label>
            <textarea name="notes" rows="2" class="form-control" placeholder="Contoh: Tambahan batch supplier Jakarta, perbaikan selisih hitung..."></textarea>
          </div>

          <button type="submit" class="btn btn-nnasa w-100 py-2 rounded-pill fw-bold">
            Simpan Mutasi Stok
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Low Stock Alert Warning Card -->
  <div class="col-lg-7">
    <div class="admin-card h-100">
      <div class="admin-card-header bg-danger-subtle border-danger-subtle">
        <h5 class="admin-card-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Peringatan Stok Menipis (&le; 5 pcs)</h5>
        <span class="badge bg-danger"><?= count($lowStockProducts) ?> Produk Kritis</span>
      </div>
      <div class="admin-card-body p-0">
        <div class="table-responsive">
          <table class="table table-admin">
            <thead>
              <tr>
                <th>Produk</th>
                <th>SKU</th>
                <th>Sisa Stok</th>
                <th>Status</th>
                <th class="text-end">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($lowStockProducts)): ?>
                <tr>
                  <td colspan="5" class="text-center py-4 text-success"><i class="bi bi-check-circle me-1"></i> Semua stok produk dalam batas aman.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($lowStockProducts as $lp): ?>
                  <tr>
                    <td>
                      <strong class="text-dark small"><?= htmlspecialchars($lp['name']) ?></strong>
                    </td>
                    <td><code><?= htmlspecialchars($lp['sku']) ?></code></td>
                    <td>
                      <span class="badge <?= $lp['stock'] == 0 ? 'bg-danger' : 'bg-warning text-dark' ?>">
                        <?= $lp['stock'] ?> pcs
                      </span>
                    </td>
                    <td>
                      <small class="text-danger fw-semibold"><?= $lp['stock'] == 0 ? 'Habis Total' : 'Hampir Habis' ?></small>
                    </td>
                    <td class="text-end">
                      <a href="<?= ADMIN_URL ?>product-form.php?id=<?= $lp['id'] ?>" class="btn btn-sm btn-nnasa-outline rounded-pill px-3">
                        Restock
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
  </div>
</div>

<!-- Stock Movements Audit History -->
<div class="admin-card">
  <div class="admin-card-header">
    <h5 class="admin-card-title"><i class="bi bi-clock-history text-primary-pink me-2"></i> Riwayat Mutasi Stok Terakhir</h5>
    <span class="text-muted small">Catatan audit otomatis dari pesanan, restock, dan koreksi stok</span>
  </div>
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Produk</th>
            <th>Tipe Mutasi</th>
            <th>Perubahan Jumlah</th>
            <th>Referensi</th>
            <th>Catatan</th>
            <th>Oleh</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movements as $m): ?>
            <tr>
              <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></small></td>
              <td><strong><?= htmlspecialchars($m['product_name']) ?></strong></td>
              <td>
                <span class="badge bg-light text-dark border"><?= ucfirst($m['movement_type']) ?></span>
              </td>
              <td>
                <span class="fw-bold <?= $m['quantity'] > 0 ? 'text-success' : 'text-danger' ?>">
                  <?= $m['quantity'] > 0 ? '+' . $m['quantity'] : $m['quantity'] ?> pcs
                </span>
              </td>
              <td><code><?= htmlspecialchars($m['reference_type'] ?: '-') ?></code></td>
              <td><small class="text-muted"><?= htmlspecialchars($m['notes'] ?? '-') ?></small></td>
              <td><small class="text-dark"><?= htmlspecialchars($m['admin_name'] ?? 'Sistem / Pelanggan') ?></small></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
