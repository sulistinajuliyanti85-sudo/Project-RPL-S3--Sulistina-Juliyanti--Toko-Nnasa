<?php
/**
 * Admin Product Management (Manajemen Produk)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Manajemen Produk';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// Handle status toggle or quick delete
if (isset($_GET['toggle_status'])) {
    $prodId = (int)$_GET['toggle_status'];
    $stmt = $db->prepare("SELECT status FROM products WHERE id = ?");
    $stmt->execute([$prodId]);
    $currentStatus = $stmt->fetchColumn();
    $newStatus = ($currentStatus === 'active') ? 'draft' : 'active';
    
    $db->prepare("UPDATE products SET status = ? WHERE id = ?")->execute([$newStatus, $prodId]);
    log_activity($adminUser['id'], 'toggle_product_status', 'products', $prodId, "Changed product status to {$newStatus}");
    set_flash('success', "Status produk berhasil diubah menjadi {$newStatus}.");
    header('Location: ' . ADMIN_URL . 'products.php');
    exit;
}

if (isset($_GET['delete'])) {
    $prodId = (int)$_GET['delete'];
    // Check if product is in order items
    $inOrders = (int)$db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?")->execute([$prodId]);
    
    try {
        // Soft archive or delete
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$prodId]);
        log_activity($adminUser['id'], 'delete_product', 'products', $prodId, "Deleted product ID {$prodId}");
        set_flash('info', 'Produk berhasil dihapus dari katalog.');
    } catch (Exception $e) {
        // If foreign key constraint blocks hard delete, set to archived
        $db->prepare("UPDATE products SET status = 'archived' WHERE id = ?")->execute([$prodId]);
        set_flash('warning', 'Produk memiliki riwayat pesanan sehingga diarsipkan (archived).');
    }
    header('Location: ' . ADMIN_URL . 'products.php');
    exit;
}

// Search and filters
$search = trim($_GET['q'] ?? '');
$categoryFilter = (int)($_GET['category'] ?? 0);
$statusFilter = $_GET['status'] ?? 'all';
$stockFilter = $_GET['stock'] ?? 'all';

$where = ["1=1"];
$params = [];

if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($categoryFilter > 0) {
    $where[] = "p.category_id = ?";
    $params[] = $categoryFilter;
}

if ($statusFilter !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
}

if ($stockFilter === 'low') {
    $where[] = "p.stock <= 5 AND p.stock > 0";
} elseif ($stockFilter === 'out') {
    $where[] = "p.stock = 0";
}

$whereClause = implode(' AND ', $where);

// Fetch products
$sql = "
    SELECT p.*, c.name as category_name,
           (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) as variant_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE {$whereClause}
    ORDER BY p.id DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Categories for filter dropdown
$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();
?>

<!-- Header Action Strip -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Daftar Produk Aksesoris (<?= count($products) ?>)</h4>
    <small class="text-muted">Kelola katalog, harga promo, varian, dan persediaan barang</small>
  </div>
  <div>
    <a href="<?= ADMIN_URL ?>product-form.php" class="btn btn-nnasa rounded-pill px-4">
      <i class="bi bi-plus-lg me-1"></i> Tambah Produk Baru
    </a>
  </div>
</div>

<!-- Search & Filter Card -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4" style="background: #ffffff; border: 1px solid var(--admin-border) !important;">
  <form action="<?= ADMIN_URL ?>products.php" method="GET" class="row g-2 align-items-center">
    <div class="col-md-4">
      <input type="text" name="q" class="form-control form-control-sm rounded-pill" placeholder="Cari nama produk atau SKU..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3">
      <select name="category" class="form-select form-select-sm rounded-pill">
        <option value="0">-- Semua Kategori --</option>
        <?php foreach ($categories as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $categoryFilter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <select name="status" class="form-select form-select-sm rounded-pill">
        <option value="all">Semua Status</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
        <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
      </select>
    </div>
    <div class="col-md-2">
      <select name="stock" class="form-select form-select-sm rounded-pill">
        <option value="all">Semua Stok</option>
        <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Stok Menipis (&le; 5)</option>
        <option value="out" <?= $stockFilter === 'out' ? 'selected' : '' ?>>Stok Habis (0)</option>
      </select>
    </div>
    <div class="col-md-1">
      <button type="submit" class="btn btn-sm btn-nnasa w-100 rounded-pill">Filter</button>
    </div>
  </form>
</div>

<!-- Products Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Produk</th>
            <th>SKU</th>
            <th>Kategori</th>
            <th>Harga Normal</th>
            <th>Harga Promo</th>
            <th>Stok</th>
            <th>Varian</th>
            <th>Terjual</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr>
              <td colspan="10" class="text-center py-4 text-muted">Tidak ada produk yang cocok dengan filter.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($products as $p): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <img src="<?= get_image_url('products', $p['main_image'], $p['name']) ?>" class="rounded-2 border flex-shrink-0" style="width: 44px; height: 44px; object-fit: cover;">
                    <div class="min-w-0" style="max-width: 220px;">
                      <a href="<?= BASE_URL ?>product-detail.php?id=<?= $p['id'] ?>" target="_blank" class="fw-semibold text-dark text-truncate d-block text-decoration-none">
                        <?= htmlspecialchars($p['name']) ?>
                      </a>
                    </div>
                  </div>
                </td>
                <td><code><?= htmlspecialchars($p['sku']) ?></code></td>
                <td><span class="badge bg-light text-muted border"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></span></td>
                <td><?= format_rupiah($p['price']) ?></td>
                <td>
                  <?php if (!empty($p['discount_price'])): ?>
                    <strong class="text-danger"><?= format_rupiah($p['discount_price']) ?></strong>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($p['stock'] <= 0): ?>
                    <span class="badge bg-danger">Habis</span>
                  <?php elseif ($p['stock'] <= 5): ?>
                    <span class="badge bg-warning text-dark"><?= $p['stock'] ?> pcs (Tipis)</span>
                  <?php else: ?>
                    <span class="fw-semibold text-dark"><?= $p['stock'] ?> pcs</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-light text-dark border"><?= $p['variant_count'] ?> varian</span>
                </td>
                <td><?= $p['sales_count'] ?></td>
                <td>
                  <a href="<?= ADMIN_URL ?>products.php?toggle_status=<?= $p['id'] ?>" class="text-decoration-none" title="Klik untuk ubah status">
                    <?php if ($p['status'] === 'active'): ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle">Aktif</span>
                    <?php elseif ($p['status'] === 'draft'): ?>
                      <span class="badge bg-secondary-subtle text-secondary border">Draft</span>
                    <?php else: ?>
                      <span class="badge bg-dark">Archived</span>
                    <?php endif; ?>
                  </a>
                </td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a href="<?= ADMIN_URL ?>product-form.php?id=<?= $p['id'] ?>" class="btn btn-light border" title="Edit Produk">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <a href="<?= ADMIN_URL ?>products.php?delete=<?= $p['id'] ?>" class="btn btn-light border text-danger" onclick="return confirm('Hapus produk ini?');" title="Hapus Produk">
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
