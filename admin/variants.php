<?php
/**
 * Admin Product Variants Management (Varian Produk)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Varian Produk';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// Handle inline quick update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_update') {
    if (csrf_verify($_POST['csrf_token'] ?? '')) {
        $variantId = (int)$_POST['variant_id'];
        $newStock = max(0, (int)$_POST['stock']);
        $newPriceAdj = (float)$_POST['price_adjustment'];
        
        $db->prepare("UPDATE product_variants SET stock = ?, price_adjustment = ? WHERE id = ?")
           ->execute([$newStock, $newPriceAdj, $variantId]);
           
        set_flash('success', 'Varian produk berhasil diperbarui.');
        header('Location: ' . ADMIN_URL . 'variants.php');
        exit;
    }
}

$productIdFilter = (int)($_GET['product_id'] ?? 0);
$where = "1=1";
$params = [];
if ($productIdFilter > 0) {
    $where .= " AND pv.product_id = ?";
    $params[] = $productIdFilter;
}

$stmt = $db->prepare("
    SELECT pv.*, p.name as product_name, p.sku as product_sku, p.price as base_price, p.main_image
    FROM product_variants pv
    JOIN products p ON pv.product_id = p.id
    WHERE {$where}
    ORDER BY p.name ASC, pv.id ASC
");
$stmt->execute($params);
$variants = $stmt->fetchAll();

// Product list for filter
$productsList = $db->query("SELECT id, name FROM products WHERE id IN (SELECT DISTINCT product_id FROM product_variants) ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Daftar Varian Produk (<?= count($variants) ?>)</h4>
    <small class="text-muted">Kelola varian warna, ukuran, penyesuaian harga, dan stok spesifik</small>
  </div>
  <div>
    <form action="<?= ADMIN_URL ?>variants.php" method="GET" class="d-flex align-items-center gap-2">
      <select name="product_id" class="form-select form-select-sm rounded-pill" onchange="this.form.submit()">
        <option value="0">-- Semua Produk Ber-Varian --</option>
        <?php foreach ($productsList as $pl): ?>
          <option value="<?= $pl['id'] ?>" <?= $productIdFilter == $pl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pl['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Produk Induk</th>
            <th>Tipe Varian</th>
            <th>Pilihan / Nilai</th>
            <th>SKU Varian</th>
            <th>Penyesuaian Harga</th>
            <th>Harga Efektif</th>
            <th>Stok Varian</th>
            <th>Status</th>
            <th class="text-end">Aksi Cepat</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($variants)): ?>
            <tr>
              <td colspan="9" class="text-center py-4 text-muted">Belum ada varian produk.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($variants as $v): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <img src="<?= get_image_url('products', $v['main_image'], $v['product_name']) ?>" class="rounded-2 border flex-shrink-0" style="width: 38px; height: 38px; object-fit: cover;">
                    <div class="min-w-0" style="max-width: 200px;">
                      <a href="<?= ADMIN_URL ?>product-form.php?id=<?= $v['product_id'] ?>" class="fw-semibold text-dark text-truncate d-block text-decoration-none">
                        <?= htmlspecialchars($v['product_name']) ?>
                      </a>
                    </div>
                  </div>
                </td>
                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($v['variant_name']) ?></span></td>
                <td><strong class="text-primary-pink"><?= htmlspecialchars($v['variant_value']) ?></strong></td>
                <td><code><?= htmlspecialchars($v['sku'] ?: '-') ?></code></td>
                <td>+<?= format_rupiah($v['price_adjustment']) ?></td>
                <td><strong><?= format_rupiah((float)$v['base_price'] + (float)$v['price_adjustment']) ?></strong></td>
                <td>
                  <span class="badge <?= $v['stock'] <= 5 ? 'bg-danger' : 'bg-success' ?>">
                    <?= $v['stock'] ?> pcs
                  </span>
                </td>
                <td>
                  <span class="badge <?= $v['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary' ?>">
                    <?= ucfirst($v['status']) ?>
                  </span>
                </td>
                <td class="text-end">
                  <button type="button" class="btn btn-sm btn-light border rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#quickEditModal<?= $v['id'] ?>">
                    <i class="bi bi-pencil-square me-1"></i> Ubah Cepat
                  </button>

                  <!-- Modal Quick Edit -->
                  <div class="modal fade text-start" id="quickEditModal<?= $v['id'] ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                      <div class="modal-content rounded-4 border-0 shadow">
                        <form action="<?= ADMIN_URL ?>variants.php" method="POST">
                          <?= csrf_field() ?>
                          <input type="hidden" name="action" value="quick_update">
                          <input type="hidden" name="variant_id" value="<?= $v['id'] ?>">

                          <div class="modal-header border-bottom">
                            <h6 class="modal-title fw-bold">Ubah Varian: <?= htmlspecialchars($v['variant_value']) ?></h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <div class="mb-3">
                              <label class="form-label small fw-bold">Stok Varian</label>
                              <input type="number" name="stock" class="form-control" value="<?= $v['stock'] ?>" required>
                            </div>
                            <div class="mb-3">
                              <label class="form-label small fw-bold">Penyesuaian Harga (+Rp)</label>
                              <input type="number" step="500" name="price_adjustment" class="form-control" value="<?= (float)$v['price_adjustment'] ?>" required>
                            </div>
                          </div>
                          <div class="modal-footer border-top">
                            <button type="submit" class="btn btn-sm btn-nnasa w-100 rounded-pill">Simpan</button>
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
