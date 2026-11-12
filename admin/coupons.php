<?php
/**
 * Admin Coupons & Vouchers Management
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Voucher & Promo';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

// Handle Create / Edit Coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $code = strtoupper(trim(sanitize($_POST['code'] ?? '')));
        $discountType = $_POST['discount_type'] ?? 'percentage';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $minPurchase = (float)($_POST['minimum_purchase'] ?? 0);
        $maxDiscount = !empty($_POST['maximum_discount']) ? (float)$_POST['maximum_discount'] : null;
        $usageLimit = max(1, (int)($_POST['usage_limit'] ?? 100));
        $status = in_array($_POST['status'], ['active', 'inactive']) ? $_POST['status'] : 'active';

        if (empty($code) || $discountValue <= 0) {
            set_flash('danger', 'Kode voucher dan nilai diskon wajib diisi dengan benar.');
        } else {
            if ($_POST['action'] === 'add_coupon') {
                // Check code uniqueness
                $check = $db->prepare("SELECT id FROM coupons WHERE code = ? LIMIT 1");
                $check->execute([$code]);
                if ($check->fetch()) {
                    set_flash('danger', "Kode kupon '{$code}' sudah ada.");
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO coupons (code, discount_type, discount_value, minimum_purchase, maximum_discount, usage_limit, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$code, $discountType, $discountValue, $minPurchase, $maxDiscount, $usageLimit, $status]);
                    set_flash('success', "Voucher promo '{$code}' berhasil dibuat! 🎟️");
                }
            } elseif ($_POST['action'] === 'edit_coupon') {
                $couponId = (int)$_POST['coupon_id'];
                $stmt = $db->prepare("
                    UPDATE coupons 
                    SET code = ?, discount_type = ?, discount_value = ?, minimum_purchase = ?, maximum_discount = ?, usage_limit = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$code, $discountType, $discountValue, $minPurchase, $maxDiscount, $usageLimit, $status, $couponId]);
                set_flash('success', 'Voucher berhasil diperbarui.');
            }
            header('Location: ' . ADMIN_URL . 'coupons.php');
            exit;
        }
    }
}

// Handle Delete Coupon
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM coupons WHERE id = ?")->execute([$delId]);
    set_flash('info', 'Kupon voucher berhasil dihapus.');
    header('Location: ' . ADMIN_URL . 'coupons.php');
    exit;
}

$coupons = $db->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Manajemen Voucher &amp; Kupon Diskon (<?= count($coupons) ?>)</h4>
    <small class="text-muted">Buat kode kupon promosi belanja untuk menarik pembeli</small>
  </div>
  <button type="button" class="btn btn-nnasa rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCouponModal">
    <i class="bi bi-plus-lg me-1"></i> Buat Voucher Baru
  </button>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Kode Kupon</th>
            <th>Tipe Diskon</th>
            <th>Nilai Diskon</th>
            <th>Min. Belanja</th>
            <th>Maks. Diskon</th>
            <th>Kuota Terpakai</th>
            <th>Status</th>
            <th class="text-end">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($coupons)): ?>
            <tr>
              <td colspan="8" class="text-center py-4 text-muted">Belum ada voucher promosi.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($coupons as $coup): ?>
              <tr>
                <td>
                  <span class="badge bg-light text-dark font-monospace fs-6 border px-3 py-2">
                    <i class="bi bi-ticket-perforated text-primary-pink me-1"></i> <?= htmlspecialchars($coup['code']) ?>
                  </span>
                </td>
                <td><small class="text-muted"><?= ucfirst($coup['discount_type']) ?></small></td>
                <td>
                  <strong class="text-primary-pink">
                    <?= $coup['discount_type'] === 'percentage' ? $coup['discount_value'] . '%' : format_rupiah($coup['discount_value']) ?>
                  </strong>
                </td>
                <td><?= format_rupiah($coup['minimum_purchase']) ?></td>
                <td><?= $coup['maximum_discount'] ? format_rupiah($coup['maximum_discount']) : '<span class="text-muted">-</span>' ?></td>
                <td>
                  <span class="badge bg-light text-dark border">
                    <?= $coup['used_count'] ?> / <?= $coup['usage_limit'] ?> kali
                  </span>
                </td>
                <td>
                  <span class="badge <?= $coup['status'] === 'active' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' ?>">
                    <?= ucfirst($coup['status']) ?>
                  </span>
                </td>
                <td class="text-end">
                  <a href="<?= ADMIN_URL ?>coupons.php?delete=<?= $coup['id'] ?>" class="btn btn-sm btn-light border text-danger" onclick="return confirm('Hapus kupon voucher ini?');">
                    <i class="bi bi-trash3"></i>
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

<!-- Add Coupon Modal -->
<div class="modal fade" id="addCouponModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0 shadow">
      <form action="<?= ADMIN_URL ?>coupons.php" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_coupon">

        <div class="modal-header border-bottom">
          <h5 class="modal-title fw-bold fs-6">Buat Voucher Promo Baru</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold">Kode Kupon <span class="text-danger">*</span></label>
            <input type="text" name="code" class="form-control text-uppercase" placeholder="Contoh: BEAUTY15" required>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Tipe Diskon</label>
              <select name="discount_type" class="form-select">
                <option value="percentage">Persentase (%)</option>
                <option value="fixed">Nominal Tetap (Rp)</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Nilai Diskon <span class="text-danger">*</span></label>
              <input type="number" step="0.01" name="discount_value" class="form-control" placeholder="15 atau 10000" required>
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Min. Belanja (Rp)</label>
              <input type="number" step="500" name="minimum_purchase" class="form-control" value="50000">
            </div>
            <div class="col-6">
              <label class="form-label small fw-bold">Maks. Potongan (Rp)</label>
              <input type="number" step="500" name="maximum_discount" class="form-control" placeholder="Kosongkan jika bebas">
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-bold">Batas Penggunaan (Kuota)</label>
              <input type="number" name="usage_limit" class="form-control" value="100">
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
          <button type="submit" class="btn btn-nnasa btn-sm rounded-pill px-4">Buat Voucher</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
