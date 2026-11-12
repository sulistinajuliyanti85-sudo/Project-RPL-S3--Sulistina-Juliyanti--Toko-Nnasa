<?php
/**
 * Admin Homepage Banners Management (Banner Beranda)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Banner Beranda';
require_once dirname(__DIR__) . '/templates/admin_header.php';
require_once dirname(__DIR__) . '/app/Helpers/upload.php';

$db = getDB();

// Handle Add / Edit Banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $subtitle = sanitize($_POST['subtitle'] ?? '');
        $targetUrl = sanitize($_POST['target_url'] ?? 'products.php');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $status = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($title)) {
            set_flash('danger', 'Judul banner wajib diisi.');
        } else {
            $imageName = null;
            if (!empty($_FILES['image']['name'])) {
                $uploadRes = handle_image_upload($_FILES['image'], 'banners');
                if ($uploadRes['success']) {
                    $imageName = $uploadRes['filename'];
                }
            }

            if ($_POST['action'] === 'add_banner') {
                $imagePath = $imageName ?: 'banner-default.jpg';
                $stmt = $db->prepare("
                    INSERT INTO banners (title, subtitle, image_path, target_url, sort_order, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $subtitle, $imagePath, $targetUrl, $sortOrder, $status]);
                set_flash('success', 'Banner promosi baru berhasil ditambahkan! 🎨');
            } elseif ($_POST['action'] === 'edit_banner') {
                $bannerId = (int)$_POST['banner_id'];
                if ($imageName) {
                    $stmt = $db->prepare("
                        UPDATE banners SET title = ?, subtitle = ?, image_path = ?, target_url = ?, sort_order = ?, status = ? WHERE id = ?
                    ");
                    $stmt->execute([$title, $subtitle, $imageName, $targetUrl, $sortOrder, $status, $bannerId]);
                } else {
                    $stmt = $db->prepare("
                        UPDATE banners SET title = ?, subtitle = ?, target_url = ?, sort_order = ?, status = ? WHERE id = ?
                    ");
                    $stmt->execute([$title, $subtitle, $targetUrl, $sortOrder, $status, $bannerId]);
                }
                set_flash('success', 'Banner berhasil diperbarui.');
            }
            header('Location: ' . ADMIN_URL . 'banners.php');
            exit;
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM banners WHERE id = ?")->execute([$delId]);
    set_flash('info', 'Banner berhasil dihapus.');
    header('Location: ' . ADMIN_URL . 'banners.php');
    exit;
}

$banners = $db->query("SELECT * FROM banners ORDER BY sort_order ASC, id ASC")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Banner Carousel Beranda (<?= count($banners) ?>)</h4>
    <small class="text-muted">Kelola gambar promosi slider yang tampil di halaman beranda toko</small>
  </div>
  <button type="button" class="btn btn-nnasa rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addBannerModal">
    <i class="bi bi-plus-lg me-1"></i> Tambah Banner
  </button>
</div>

<div class="row g-4">
  <?php foreach ($banners as $b): ?>
    <div class="col-md-6 col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden h-100" style="background: #ffffff; border: 1px solid var(--admin-border) !important;">
        <div class="position-relative" style="height: 160px; background: #faf4f4;">
          <img src="<?= get_image_url('banners', $b['image_path'], $b['title']) ?>" class="w-100 h-100 object-fit-cover">
          <span class="badge position-absolute top-0 end-0 m-2 <?= $b['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
            <?= ucfirst($b['status']) ?>
          </span>
          <span class="badge position-absolute top-0 start-0 m-2 bg-dark">
            Urutan: <?= $b['sort_order'] ?>
          </span>
        </div>
        <div class="card-body p-3 d-flex flex-column">
          <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($b['title']) ?></h6>
          <small class="text-muted mb-2"><?= htmlspecialchars($b['subtitle'] ?? '-') ?></small>
          <div class="small text-muted mb-3"><i class="bi bi-link-45deg me-1"></i> <code><?= htmlspecialchars($b['target_url']) ?></code></div>
          
          <div class="d-flex justify-content-end gap-2 mt-auto pt-2 border-top">
            <a href="<?= ADMIN_URL ?>banners.php?delete=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="return confirm('Hapus banner ini?');">
              <i class="bi bi-trash3 me-1"></i> Hapus
            </a>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Add Banner Modal -->
<div class="modal fade" id="addBannerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0 shadow">
      <form action="<?= ADMIN_URL ?>banners.php" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_banner">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold fs-6">Tambah Banner Promosi</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Judul Banner <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="Contoh: Flash Sale Rose Gold Series" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Sub-judul / Keterangan</label>
            <input type="text" name="subtitle" class="form-control" placeholder="Contoh: Diskon Hingga 40% &amp; Ekstra Diskon">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Unggah Gambar Banner</label>
            <input type="file" name="image" class="form-control" accept="image/*">
            <small class="text-muted">Rasio 1200x420 px disarankan.</small>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-bold">Target Link URL</label>
            <input type="text" name="target_url" class="form-control" value="products.php?promo=1">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Urutan Tampil</label>
              <input type="number" name="sort_order" class="form-control" value="1">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Status</label>
              <select name="status" class="form-select">
                <option value="active" selected>Aktif</option>
                <option value="inactive">Nonaktif</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top">
          <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-nnasa btn-sm rounded-pill px-4">Tambahkan Banner</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
