<?php
/**
 * Customer Address Book (Buku Alamat)
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';

require_login();
$user = current_user();
$userId = $user['id'];
$db = getDB();

$redirectParam = $_GET['redirect'] ?? '';

// Handle Add / Edit Address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $action = $_POST['action'];
        $recipientName = sanitize($_POST['recipient_name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $addressLine = sanitize($_POST['address_line'] ?? '');
        $village = sanitize($_POST['village'] ?? '');
        $district = sanitize($_POST['district'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $province = sanitize($_POST['province'] ?? '');
        $postalCode = sanitize($_POST['postal_code'] ?? '');
        $isDefault = !empty($_POST['is_default']) ? 1 : 0;

        if (empty($recipientName) || empty($phone) || empty($addressLine) || empty($city) || empty($province) || empty($postalCode)) {
            set_flash('danger', 'Kolom nama, telepon, alamat, kota, provinsi, dan kode pos wajib diisi.');
        } else {
            if ($isDefault) {
                // Reset other default addresses
                $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
            }

            if ($action === 'add_address') {
                $stmt = $db->prepare("
                    INSERT INTO addresses (user_id, recipient_name, phone, address_line, village, district, city, province, postal_code, is_default)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$userId, $recipientName, $phone, $addressLine, $village, $district, $city, $province, $postalCode, $isDefault]);
                set_flash('success', 'Alamat baru berhasil ditambahkan! 🏡');
            } elseif ($action === 'edit_address') {
                $addressId = (int)($_POST['address_id'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE addresses 
                    SET recipient_name = ?, phone = ?, address_line = ?, village = ?, district = ?, city = ?, province = ?, postal_code = ?, is_default = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$recipientName, $phone, $addressLine, $village, $district, $city, $province, $postalCode, $isDefault, $addressId, $userId]);
                set_flash('success', 'Alamat berhasil diperbarui.');
            }

            if ($redirectParam === 'checkout.php') {
                header('Location: ' . BASE_URL . 'checkout.php');
            } else {
                header('Location: ' . BASE_URL . 'addresses.php');
            }
            exit;
        }
    }
}

// Handle Delete Address
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $stmt = $db->prepare("DELETE FROM addresses WHERE id = ? AND user_id = ?");
    $stmt->execute([$delId, $userId]);
    set_flash('info', 'Alamat berhasil dihapus.');
    header('Location: ' . BASE_URL . 'addresses.php');
    exit;
}

// Handle Set Default
if (isset($_GET['set_default'])) {
    $defId = (int)$_GET['set_default'];
    $db->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?")->execute([$userId]);
    $db->prepare("UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?")->execute([$defId, $userId]);
    set_flash('success', 'Alamat utama berhasil diubah.');
    header('Location: ' . BASE_URL . 'addresses.php');
    exit;
}

// Fetch all addresses for this user
$stmt = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmt->execute([$userId]);
$addresses = $stmt->fetchAll();

// Edit state
$editAddress = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$editId, $userId]);
    $editAddress = $stmt->fetch();
}

$pageTitle = 'Buku Alamat Pengiriman';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>profile.php" class="text-decoration-none text-muted">Profil</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Buku Alamat</li>
    </ol>
  </nav>

  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 fw-bold text-dark mb-0 brand-font">Buku Alamat Pengiriman 📍</h1>
    <a href="#addressFormCollapse" class="btn btn-sm btn-nnasa rounded-pill px-3" data-bs-toggle="collapse">
      <i class="bi bi-plus-lg me-1"></i> Tambah Alamat Baru
    </a>
  </div>

  <!-- Form Add / Edit Collapse -->
  <div class="collapse <?= ($editAddress || isset($_GET['new'])) ? 'show' : '' ?> mb-4" id="addressFormCollapse">
    <div class="card border-0 shadow-sm rounded-4 p-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
      <h5 class="fw-bold text-dark fs-6 mb-3">
        <?= $editAddress ? 'Edit Alamat Pengiriman' : 'Tambah Alamat Baru' ?>
      </h5>

      <form action="<?= BASE_URL ?>addresses.php<?= $redirectParam ? '?redirect='.urlencode($redirectParam) : '' ?>" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editAddress ? 'edit_address' : 'add_address' ?>">
        <?php if ($editAddress): ?>
          <input type="hidden" name="address_id" value="<?= $editAddress['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Nama Penerima</label>
            <input type="text" name="recipient_name" class="form-control rounded-3" value="<?= htmlspecialchars($editAddress['recipient_name'] ?? $user['name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Nomor Telepon / WhatsApp</label>
            <input type="text" name="phone" class="form-control rounded-3" value="<?= htmlspecialchars($editAddress['phone'] ?? $user['phone'] ?? '') ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label small fw-bold">Alamat Lengkap (Jalan, No Rumah, RT/RW, Patokan)</label>
            <textarea name="address_line" rows="2" class="form-control rounded-3" required><?= htmlspecialchars($editAddress['address_line'] ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Kelurahan / Desa</label>
            <input type="text" name="village" class="form-control rounded-3" value="<?= htmlspecialchars($editAddress['village'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Kecamatan</label>
            <input type="text" name="district" class="form-control rounded-3" value="<?= htmlspecialchars($editAddress['district'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-bold">Kota / Kabupaten</label>
            <input type="text" name="city" class="form-control rounded-3" value="<?= htmlspecialchars($editAddress['city'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Provinsi</label>
            <input type="text" name="province" class="form-control rounded-3" value="<?= htmlspecialchars($editAddress['province'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Kode Pos</label>
            <input type="text" name="postal_code" class="form-control rounded-3" value="<?= htmlspecialchars($editAddress['postal_code'] ?? '') ?>" required>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefaultCheck" <?= (!empty($editAddress['is_default']) || empty($addresses)) ? 'checked' : '' ?>>
              <label class="form-check-label small" for="isDefaultCheck">
                Jadikan sebagai alamat utama
              </label>
            </div>
          </div>
        </div>

        <div class="mt-4 text-end">
          <a href="<?= BASE_URL ?>addresses.php" class="btn btn-sm btn-secondary rounded-pill px-3 me-2">Batal</a>
          <button type="submit" class="btn btn-sm btn-nnasa rounded-pill px-4">
            <?= $editAddress ? 'Simpan Perubahan' : 'Tambahkan Alamat' ?>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Addresses List -->
  <?php if (empty($addresses)): ?>
    <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm">
      <i class="bi bi-geo-alt text-muted opacity-50" style="font-size: 3.5rem;"></i>
      <h5 class="fw-bold mt-3">Belum Ada Alamat Tersimpan</h5>
      <p class="text-muted small">Tambahkan alamat tempat pengiriman aksesoris kamu.</p>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($addresses as $ad): ?>
        <div class="col-md-6">
          <div class="card border-0 shadow-sm rounded-4 p-4 h-100 <?= $ad['is_default'] ? 'border-pink' : '' ?>" style="background: #ffffff; border: 1.5px solid <?= $ad['is_default'] ? 'var(--primary-pink)' : 'var(--border-subtle)' ?> !important;">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <strong class="text-dark"><?= htmlspecialchars($ad['recipient_name']) ?></strong>
              <?php if ($ad['is_default']): ?>
                <span class="badge bg-primary-pink text-white rounded-pill px-2">Alamat Utama</span>
              <?php endif; ?>
            </div>
            
            <div class="small text-muted mb-1"><i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($ad['phone']) ?></div>
            <p class="small text-secondary mb-3 leading-relaxed">
              <?= htmlspecialchars($ad['address_line']) ?><br>
              <?= $ad['village'] ? htmlspecialchars($ad['village']) . ', ' : '' ?>
              <?= $ad['district'] ? htmlspecialchars($ad['district']) . ', ' : '' ?>
              <?= htmlspecialchars($ad['city']) ?>, <?= htmlspecialchars($ad['province']) ?> <?= htmlspecialchars($ad['postal_code']) ?>
            </p>

            <div class="d-flex gap-2 pt-2 border-top mt-auto">
              <a href="<?= BASE_URL ?>addresses.php?edit=<?= $ad['id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-pencil me-1"></i> Edit
              </a>
              <?php if (!$ad['is_default']): ?>
                <a href="<?= BASE_URL ?>addresses.php?set_default=<?= $ad['id'] ?>" class="btn btn-sm btn-light border rounded-pill px-3">
                  Atur Utama
                </a>
                <a href="<?= BASE_URL ?>addresses.php?delete=<?= $ad['id'] ?>" class="btn btn-sm btn-link text-danger text-decoration-none ms-auto" onclick="return confirm('Hapus alamat ini?');">
                  <i class="bi bi-trash3"></i>
                </a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
