<?php
/**
 * Admin Product Create & Edit Form
 * Store: nnasa_ca Accessories Marketplace
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';
require_once dirname(__DIR__) . '/app/Helpers/upload.php';

require_admin();
$adminUser = current_user();

$db = getDB();
$productId = (int)($_GET['id'] ?? 0);
$product = null;
$productVariants = [];
$productImages = [];

if ($productId > 0) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    
    if ($product) {
        // Fetch variants
        $vStmt = $db->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
        $vStmt->execute([$productId]);
        $productVariants = $vStmt->fetchAll();

        // Fetch gallery images
        $iStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC");
        $iStmt->execute([$productId]);
        $productImages = $iStmt->fetchAll();
    }
}

$isEdit = !empty($product);
$adminPageTitle = $isEdit ? 'Edit Produk: ' . $product['name'] : 'Tambah Produk Baru';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$error = null;

// Handle Form Submission
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $sku = strtoupper(trim(sanitize($_POST['sku'] ?? '')));
        $categoryId = (int)($_POST['category_id'] ?? 0) ?: null;
        $price = (float)($_POST['price'] ?? 0);
        $discountPrice = !empty($_POST['discount_price']) ? (float)$_POST['discount_price'] : null;
        $stock = (int)($_POST['stock'] ?? 0);
        $weight = (int)($_POST['weight'] ?? 100);
        $description = trim($_POST['description'] ?? '');
        $status = in_array($_POST['status'], ['active', 'draft', 'archived']) ? $_POST['status'] : 'active';
        $slug = slugify($name);

        if (empty($name) || empty($sku) || $price <= 0) {
            $error = 'Nama produk, SKU, dan harga produk wajib diisi dengan benar.';
        } else {
            // Check SKU uniqueness
            $checkSku = $db->prepare("SELECT id FROM products WHERE sku = ? AND id != ? LIMIT 1");
            $checkSku->execute([$sku, $productId]);
            if ($checkSku->fetch()) {
                $error = "SKU '{$sku}' sudah digunakan oleh produk lain. Harap gunakan SKU unik.";
            } else {
                try {
                    $db->beginTransaction();

                    $mainImage = $product['main_image'] ?? null;
                    // Handle main image upload if present
                    if (!empty($_FILES['main_image']['name'])) {
                        $uploadResult = handle_image_upload($_FILES['main_image'], 'products');
                        if ($uploadResult['success']) {
                            $mainImage = $uploadResult['filename'];
                        } else {
                            throw new Exception($uploadResult['error']);
                        }
                    }

                    if ($isEdit) {
                        $upd = $db->prepare("
                            UPDATE products 
                            SET category_id = ?, sku = ?, name = ?, slug = ?, description = ?,
                                price = ?, discount_price = ?, stock = ?, weight = ?, main_image = ?, status = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $upd->execute([
                            $categoryId, $sku, $name, $slug, $description,
                            $price, $discountPrice, $stock, $weight, $mainImage, $status, $productId
                        ]);
                        $targetProductId = $productId;
                        log_activity($adminUser['id'], 'update_product', 'products', $productId, "Updated product {$name}");
                    } else {
                        $ins = $db->prepare("
                            INSERT INTO products (
                                category_id, sku, name, slug, description, price, discount_price, stock, weight, main_image, status
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([
                            $categoryId, $sku, $name, $slug, $description,
                            $price, $discountPrice, $stock, $weight, $mainImage, $status
                        ]);
                        $targetProductId = (int)$db->lastInsertId();
                        log_activity($adminUser['id'], 'create_product', 'products', $targetProductId, "Created product {$name}");
                    }

                    // Handle Variants Submission
                    // Delete removed variants if in edit mode
                    $variantNames = $_POST['var_name'] ?? [];
                    $variantValues = $_POST['var_val'] ?? [];
                    $variantSkus = $_POST['var_sku'] ?? [];
                    $variantPrices = $_POST['var_price'] ?? [];
                    $variantStocks = $_POST['var_stock'] ?? [];

                    // Clear existing variants and re-insert or sync
                    $db->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$targetProductId]);

                    $insVar = $db->prepare("
                        INSERT INTO product_variants (product_id, variant_name, variant_value, sku, price_adjustment, stock, status)
                        VALUES (?, ?, ?, ?, ?, ?, 'active')
                    ");

                    for ($i = 0; $i < count($variantNames); $i++) {
                        $vName = sanitize($variantNames[$i] ?? '');
                        $vVal = sanitize($variantValues[$i] ?? '');
                        if (!empty($vName) && !empty($vVal)) {
                            $vSku = !empty($variantSkus[$i]) ? strtoupper(trim(sanitize($variantSkus[$i]))) : null;
                            $vPriceAdj = (float)($variantPrices[$i] ?? 0);
                            $vStock = max(0, (int)($variantStocks[$i] ?? 0));
                            $insVar->execute([$targetProductId, $vName, $vVal, $vSku, $vPriceAdj, $vStock]);
                        }
                    }

                    $db->commit();
                    set_flash('success', 'Data produk berhasil disimpan! ✨');
                    header('Location: ' . ADMIN_URL . 'products.php');
                    exit;

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Terjadi kesalahan penyimpanan: ' . $e->getMessage();
                }
            }
        }
    }
}

// Categories list
$categories = $db->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

$adminPageTitle = $isEdit ? 'Edit Produk' : 'Tambah Produk Baru';
require_once dirname(__DIR__) . '/templates/admin_header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-10">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-bold text-dark mb-1"><?= $isEdit ? 'Edit Produk' : 'Tambah Produk Baru' ?></h4>
        <small class="text-muted">Isi informasi detail aksesoris dengan lengkap dan menarik</small>
      </div>
      <a href="<?= ADMIN_URL ?>products.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Daftar
      </a>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger rounded-3 shadow-sm mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form action="<?= ADMIN_URL ?>product-form.php<?= $isEdit ? '?id=' . $productId : '' ?>" method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <!-- Section 1: Informasi Utama -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h5 class="admin-card-title"><i class="bi bi-info-circle text-primary-pink me-2"></i> 1. Informasi Produk</h5>
        </div>
        <div class="admin-card-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label small fw-bold">Nama Produk <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" placeholder="Contoh: Floral Pearl Hair Clip Set (Isi 3 Pcs)" value="<?= htmlspecialchars($_POST['name'] ?? $product['name'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold">Kode SKU <span class="text-danger">*</span></label>
              <input type="text" name="sku" class="form-control text-uppercase" placeholder="Contoh: NNA-JPT-001" value="<?= htmlspecialchars($_POST['sku'] ?? $product['sku'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Kategori Produk</label>
              <select name="category_id" class="form-select">
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>" <?= (($product['category_id'] ?? 0) == $cat['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Status Produk</label>
              <select name="status" class="form-select">
                <option value="active" <?= (($product['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Aktif (Tampil di Katalog)</option>
                <option value="draft" <?= (($product['status'] ?? '') === 'draft') ? 'selected' : '' ?>>Draft (Disembunyikan)</option>
                <option value="archived" <?= (($product['status'] ?? '') === 'archived') ? 'selected' : '' ?>>Archived</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Deskripsi Produk</label>
              <textarea name="description" rows="4" class="form-control" placeholder="Jelaskan bahan aksesoris, kelebihan, ukuran, dan cara pemakaian..."><?= htmlspecialchars($_POST['description'] ?? $product['description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 2: Harga, Stok, dan Berat -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h5 class="admin-card-title"><i class="bi bi-tag text-primary-pink me-2"></i> 2. Harga &amp; Persediaan Stok</h5>
        </div>
        <div class="admin-card-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label small fw-bold">Harga Normal (Rp) <span class="text-danger">*</span></label>
              <input type="number" step="500" name="price" class="form-control" placeholder="0" value="<?= htmlspecialchars($_POST['price'] ?? $product['price'] ?? '') ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold">Harga Promo / Diskon (Rp)</label>
              <input type="number" step="500" name="discount_price" class="form-control" placeholder="Kosongkan jika tidak promo" value="<?= htmlspecialchars($_POST['discount_price'] ?? $product['discount_price'] ?? '') ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold">Jumlah Stok Tersedia <span class="text-danger">*</span></label>
              <input type="number" name="stock" class="form-control" placeholder="0" value="<?= htmlspecialchars($_POST['stock'] ?? $product['stock'] ?? 0) ?>" required>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold">Berat (Gram)</label>
              <input type="number" name="weight" class="form-control" placeholder="100" value="<?= htmlspecialchars($_POST['weight'] ?? $product['weight'] ?? 100) ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Section 3: Gambar Produk -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h5 class="admin-card-title"><i class="bi bi-image text-primary-pink me-2"></i> 3. Gambar Produk</h5>
        </div>
        <div class="admin-card-body">
          <div class="row g-3 align-items-center">
            <div class="col-md-8">
              <label class="form-label small fw-bold">Unggah Gambar Utama</label>
              <input type="file" name="main_image" class="form-control" accept="image/*">
              <small class="text-muted">Format: JPG, PNG, WEBP, atau SVG. Maksimal 3MB.</small>
            </div>
            <div class="col-md-4 text-center">
              <?php if (!empty($product['main_image'])): ?>
                <div class="p-2 border rounded-3 d-inline-block bg-light">
                  <img src="<?= get_image_url('products', $product['main_image'], $product['name']) ?>" class="rounded-2" style="max-height: 80px; width: 80px; object-fit: cover;">
                  <div class="small text-muted mt-1">Gambar saat ini</div>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Section 4: Varian Produk Dinamis (Warna, Ukuran) -->
      <div class="admin-card">
        <div class="admin-card-header d-flex justify-content-between align-items-center">
          <h5 class="admin-card-title"><i class="bi bi-layers text-primary-pink me-2"></i> 4. Varian Produk (Warna &amp; Ukuran)</h5>
          <button type="button" class="btn btn-sm btn-nnasa-outline rounded-pill px-3" onclick="addVariantRow()">
            <i class="bi bi-plus-lg me-1"></i> Tambah Varian
          </button>
        </div>
        <div class="admin-card-body">
          <p class="text-muted small mb-3">Atur pilihan warna (misal: Rose Gold, Silver) atau ukuran aksesoris yang dapat dipilih pelanggan.</p>
          
          <div id="variantsContainer" class="d-flex flex-column gap-2">
            <?php if (!empty($productVariants)): ?>
              <?php foreach ($productVariants as $pv): ?>
                <div class="row g-2 align-items-center variant-row p-2 bg-light rounded-3 border">
                  <div class="col-md-3">
                    <input type="text" name="var_name[]" class="form-control form-control-sm" placeholder="Tipe Varian (misal: Warna)" value="<?= htmlspecialchars($pv['variant_name']) ?>">
                  </div>
                  <div class="col-md-3">
                    <input type="text" name="var_val[]" class="form-control form-control-sm" placeholder="Nilai Varian (misal: Rose Gold)" value="<?= htmlspecialchars($pv['variant_value']) ?>">
                  </div>
                  <div class="col-md-2">
                    <input type="number" name="var_price[]" class="form-control form-control-sm" placeholder="Penyesuaian Harga (+Rp)" value="<?= (float)$pv['price_adjustment'] ?>">
                  </div>
                  <div class="col-md-2">
                    <input type="number" name="var_stock[]" class="form-control form-control-sm" placeholder="Stok Varian" value="<?= (int)$pv['stock'] ?>">
                  </div>
                  <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.variant-row').remove()">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="<?= ADMIN_URL ?>products.php" class="btn btn-secondary rounded-pill px-4">Batal</a>
        <button type="submit" class="btn btn-nnasa rounded-pill px-5 fw-bold">
          <i class="bi bi-save me-1"></i> Simpan Produk
        </button>
      </div>

    </form>
  </div>
</div>

<script>
  function addVariantRow() {
    const container = document.getElementById('variantsContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center variant-row p-2 bg-light rounded-3 border';
    row.innerHTML = `
      <div class="col-md-3">
        <input type="text" name="var_name[]" class="form-control form-control-sm" placeholder="Tipe Varian (misal: Warna Logam)">
      </div>
      <div class="col-md-3">
        <input type="text" name="var_val[]" class="form-control form-control-sm" placeholder="Nilai (misal: Rose Gold)">
      </div>
      <div class="col-md-2">
        <input type="number" name="var_price[]" class="form-control form-control-sm" placeholder="Ekstra Harga (+Rp)" value="0">
      </div>
      <div class="col-md-2">
        <input type="number" name="var_stock[]" class="form-control form-control-sm" placeholder="Stok Varian" value="10">
      </div>
      <div class="col-md-2 text-end">
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.variant-row').remove()">
          <i class="bi bi-trash3"></i>
        </button>
      </div>
    `;
    container.appendChild(row);
  }
</script>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
