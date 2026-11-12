<?php
/**
 * Admin Category Management (Kategori Produk)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Kategori Produk';
require_once dirname(__DIR__) . '/templates/admin_header.php';
require_once dirname(__DIR__) . '/app/Helpers/upload.php';

$db = getDB();

// Handle Add / Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $description = sanitize($_POST['description'] ?? '');
        $status = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'active';
        $slug = slugify($name);

        if (empty($name)) {
            set_flash('danger', 'Nama kategori wajib diisi.');
        } else {
            // Handle image upload if provided
            $imageName = null;
            if (!empty($_FILES['image']['name'])) {
                $uploadRes = handle_image_upload($_FILES['image'], 'categories');
                if ($uploadRes['success']) {
                    $imageName = $uploadRes['filename'];
                }
            }

            if ($_POST['action'] === 'add_category') {
                $stmt = $db->prepare("
                    INSERT INTO categories (parent_id, name, slug, description, image, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$parentId, $name, $slug, $description, $imageName, $status]);
                log_activity($adminUser['id'], 'create_category', 'categories', (int)$db->lastInsertId(), "Created category {$name}");
                set_flash('success', 'Kategori baru berhasil ditambahkan! 🌸');
            } elseif ($_POST['action'] === 'edit_category') {
                $catId = (int)$_POST['category_id'];
                if ($imageName) {
                    $stmt = $db->prepare("
                        UPDATE categories SET parent_id = ?, name = ?, slug = ?, description = ?, image = ?, status = ?, updated_at = NOW() WHERE id = ?
                    ");
                    $stmt->execute([$parentId, $name, $slug, $description, $imageName, $status, $catId]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE categories SET parent_id = ?, name = ?, slug = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?
                    ");
                    $stmt->execute([$parentId, $name, $slug, $description, $status, $catId]);
                }
                log_activity($adminUser['id'], 'update_category', 'categories', $catId, "Updated category {$name}");
                set_flash('success', 'Kategori berhasil diperbarui.');
            }
            header('Location: ' . ADMIN_URL . 'categories.php');
            exit;
        }
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$delId]);
    log_activity($adminUser['id'], 'delete_category', 'categories', $delId, "Deleted category ID {$delId}");
    set_flash('info', 'Kategori berhasil dihapus.');
    header('Location: ' . ADMIN_URL . 'categories.php');
    exit;
}

// Fetch categories with product counts and parent name
$categories = $db->query("
    SELECT c.*, p.name as parent_name,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY c.parent_id ASC, c.id ASC
")->fetchAll();

// Main categories for parent selection
$mainCategories = $db->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Kategori Aksesoris</h4>
    <small class="text-muted">Kelola struktur kategori dan sub-kategori aksesoris toko</small>
  </div>
  <button type="button" class="btn btn-nnasa rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
    <i class="bi bi-plus-lg me-1"></i> Tambah Kategori
  </button>
</div>

<!-- Category Table -->
<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Ikon / Gambar</th>
            <th>Nama Kategori</th>
            <th>Slug URL</th>
            <th>Induk Kategori</th>
            <th>Deskripsi</th>
            <th>Jumlah Produk</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $cat): ?>
            <tr>
              <td>
                <img src="<?= get_image_url('categories', $cat['image'], $cat['name']) ?>" class="rounded-circle border" style="width: 40px; height: 40px; object-fit: cover;">
              </td>
              <td>
                <strong class="text-dark"><?= htmlspecialchars($cat['name']) ?></strong>
              </td>
              <td><code><?= htmlspecialchars($cat['slug']) ?></code></td>
              <td>
                <?= $cat['parent_name'] ? '<span class="badge bg-light text-muted border">' . htmlspecialchars($cat['parent_name']) . '</span>' : '<span class="text-muted small">Kategori Utama</span>' ?>
              </td>
              <td>
                <small class="text-muted text-truncate d-inline-block" style="max-width: 250px;">
                  <?= htmlspecialchars($cat['description'] ?? '-') ?>
                </small>
              </td>
              <td>
                <a href="<?= ADMIN_URL ?>products.php?category=<?= $cat['id'] ?>" class="badge bg-light text-primary-pink border text-decoration-none">
                  <?= $cat['product_count'] ?> produk
                </a>
              </td>
              <td>
                <span class="badge <?= $cat['status'] === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' ?>">
                  <?= ucfirst($cat['status']) ?>
                </span>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editModal<?= $cat['id'] ?>">
                  <i class="bi bi-pencil"></i>
                </button>
                <a href="<?= ADMIN_URL ?>categories.php?delete=<?= $cat['id'] ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Hapus kategori ini? Produk dalam kategori ini akan menjadi Uncategorized.');">
                  <i class="bi bi-trash3"></i>
                </a>

                <!-- Edit Modal -->
                <div class="modal fade text-start" id="editModal<?= $cat['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content rounded-4 border-0 shadow">
                      <form action="<?= ADMIN_URL ?>categories.php" method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="edit_category">
                        <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">

                        <div class="modal-header border-bottom">
                          <h5 class="modal-title fw-bold fs-6">Edit Kategori</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                          <div class="mb-3">
                            <label class="form-label small fw-bold">Nama Kategori</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($cat['name']) ?>" required>
                          </div>
                          <div class="mb-3">
                            <label class="form-label small fw-bold">Kategori Induk</label>
                            <select name="parent_id" class="form-select">
                              <option value="">-- Tidak Ada (Kategori Utama) --</option>
                              <?php foreach ($mainCategories as $m): ?>
                                <?php if ($m['id'] != $cat['id']): ?>
                                  <option value="<?= $m['id'] ?>" <?= $cat['parent_id'] == $m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                                <?php endif; ?>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="mb-3">
                            <label class="form-label small fw-bold">Deskripsi Singkat</label>
                            <textarea name="description" rows="2" class="form-control"><?= htmlspecialchars($cat['description'] ?? '') ?></textarea>
                          </div>
                          <div class="mb-3">
                            <label class="form-label small fw-bold">Ubah Gambar/Ikon (Opsional)</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                          </div>
                          <div class="mb-3">
                            <label class="form-label small fw-bold">Status</label>
                            <select name="status" class="form-select">
                              <option value="active" <?= $cat['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                              <option value="inactive" <?= $cat['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                            </select>
                          </div>
                        </div>
                        <div class="modal-footer border-top">
                          <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Batal</button>
                          <button type="submit" class="btn btn-nnasa btn-sm rounded-pill px-4">Simpan Perubahan</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0 shadow">
      <form action="<?= ADMIN_URL ?>categories.php" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_category">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold fs-6">Tambah Kategori Aksesoris</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Nama Kategori <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="Contoh: Bros Hijab & Pin Kerah" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Kategori Induk (Opsional)</label>
            <select name="parent_id" class="form-select">
              <option value="">-- Jadikan Kategori Utama --</option>
              <?php foreach ($mainCategories as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Deskripsi</label>
            <textarea name="description" rows="2" class="form-control" placeholder="Keterangan singkat kategori..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Gambar / Thumbnail Kategori</label>
            <input type="file" name="image" class="form-control" accept="image/*">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Status</label>
            <select name="status" class="form-select">
              <option value="active" selected>Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-nnasa btn-sm rounded-pill px-4">Tambahkan Kategori</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
