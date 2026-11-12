<?php
/**
 * View: user_management.php
 * Antarmuka pengguna (View) berbasis Bootstrap 5 untuk fitur CRUD Manajemen User.
 */

$adminPageTitle = 'Manajemen Pengguna (CRUD)';
require_once defined('TEMPLATE_PATH') ? TEMPLATE_PATH . 'admin_header.php' : (dirname(__DIR__, 3) . '/templates/admin_header.php');
?>

<!-- Header & Quick Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">
      <i class="bi bi-people-fill text-primary-pink me-2"></i>Manajemen Pengguna
    </h4>
    <small class="text-muted">Kelola akun administrator, staf kasir/CS, dan pelanggan marketplace nnasa_ca</small>
  </div>
  <button type="button" class="btn btn-nnasa rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
  </button>
</div>

<!-- Flash Message Notification -->
<?= display_flash() ?>

<!-- 4 Summary Cards (Total per Role) -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Total Pengguna</small>
          <h3 class="fw-bold text-dark mb-0"><?= number_format($counts['total'] ?? 0) ?></h3>
        </div>
        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center" style="background: rgba(194, 125, 134, 0.15); width: 48px; height: 48px;">
          <i class="bi bi-people fs-4" style="color: #c27d86;"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Admin</small>
          <h3 class="fw-bold text-dark mb-0"><?= number_format($counts['total_admin'] ?? 0) ?></h3>
        </div>
        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-danger-subtle" style="width: 48px; height: 48px;">
          <i class="bi bi-shield-lock-fill fs-4 text-danger"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Staf Toko</small>
          <h3 class="fw-bold text-dark mb-0"><?= number_format($counts['total_staff'] ?? 0) ?></h3>
        </div>
        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-primary-subtle" style="width: 48px; height: 48px;">
          <i class="bi bi-person-badge-fill fs-4 text-primary"></i>
        </div>
      </div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm rounded-4 p-3 bg-white h-100">
      <div class="d-flex align-items-center justify-content-between">
        <div>
          <small class="text-muted text-uppercase fw-semibold" style="font-size:0.75rem;">Pelanggan</small>
          <h3 class="fw-bold text-dark mb-0"><?= number_format($counts['total_customer'] ?? 0) ?></h3>
        </div>
        <div class="rounded-circle p-3 d-flex align-items-center justify-content-center bg-success-subtle" style="width: 48px; height: 48px;">
          <i class="bi bi-bag-check-fill fs-4 text-success"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filter & Search Toolbar -->
<div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
  <form method="GET" action="<?= ADMIN_URL ?>users.php" class="row g-2 align-items-center">
    <div class="col-md-5 col-lg-6">
      <div class="input-group">
        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" name="q" class="form-control border-start-0 ps-0" placeholder="Cari nama pengguna, email, atau no HP..." value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
      </div>
    </div>

    <div class="col-md-4 col-lg-3">
      <select name="role" class="form-select">
        <option value="">Semua Peran (Role)</option>
        <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>🛡️ Administrator</option>
        <option value="staff" <?= ($_GET['role'] ?? '') === 'staff' ? 'selected' : '' ?>>💼 Staf Kasir / CS</option>
        <option value="customer" <?= ($_GET['role'] ?? '') === 'customer' ? 'selected' : '' ?>>🛍️ Pelanggan (Customer)</option>
      </select>
    </div>

    <div class="col-md-3 col-lg-3 d-flex gap-2">
      <button type="submit" class="btn btn-outline-secondary w-100 rounded-pill fw-semibold">
        <i class="bi bi-funnel me-1"></i> Filter
      </button>
      <?php if (!empty($_GET['q']) || !empty($_GET['role'])): ?>
        <a href="<?= ADMIN_URL ?>users.php" class="btn btn-light rounded-pill px-3" title="Reset Filter">
          <i class="bi bi-x-lg"></i>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- Table Card: READ -->
<div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-5">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th class="ps-4 text-uppercase text-muted" style="font-size:0.75rem; width: 60px;">ID</th>
          <th class="text-uppercase text-muted" style="font-size:0.75rem;">Pengguna</th>
          <th class="text-uppercase text-muted" style="font-size:0.75rem;">Email & Kontak</th>
          <th class="text-uppercase text-muted" style="font-size:0.75rem;">Hak Akses (Role)</th>
          <th class="text-uppercase text-muted" style="font-size:0.75rem;">Status</th>
          <th class="text-uppercase text-muted" style="font-size:0.75rem;">Terdaftar</th>
          <th class="text-center text-uppercase text-muted pe-4" style="font-size:0.75rem; width: 130px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="7" class="text-center py-5 text-muted">
              <i class="bi bi-person-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
              Tidak ada data pengguna yang sesuai dengan filter pencarian.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td class="ps-4 fw-bold text-muted">#<?= $u['id'] ?></td>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold shadow-sm" style="width: 38px; height: 38px; background: <?= $u['role'] === 'admin' ? '#c27d86' : ($u['role'] === 'staff' ? '#3b82f6' : '#10b981') ?>; font-size: 0.9rem;">
                    <?= strtoupper(substr($u['name'], 0, 1)) ?>
                  </div>
                  <div>
                    <div class="fw-bold text-dark"><?= htmlspecialchars($u['name']) ?></div>
                    <?php if ((int)$u['id'] === (int)$currentUser['id']): ?>
                      <span class="badge bg-secondary-subtle text-dark border" style="font-size: 0.65rem;">Akun Anda</span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <div><i class="bi bi-envelope text-muted me-1"></i> <?= htmlspecialchars($u['email']) ?></div>
                <small class="text-muted"><i class="bi bi-telephone text-muted me-1"></i> <?= htmlspecialchars($u['phone'] ?: '-') ?></small>
              </td>
              <td>
                <?php if ($u['role'] === 'admin'): ?>
                  <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">
                    <i class="bi bi-shield-lock-fill me-1"></i> Administrator
                  </span>
                <?php elseif ($u['role'] === 'staff'): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 rounded-pill">
                    <i class="bi bi-person-badge me-1"></i> Staf Kasir / CS
                  </span>
                <?php else: ?>
                  <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                    <i class="bi bi-bag-check me-1"></i> Pelanggan
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($u['status'] === 'active'): ?>
                  <span class="badge bg-success rounded-pill px-2 py-1"><i class="bi bi-check-circle me-1"></i> Aktif</span>
                <?php elseif ($u['status'] === 'inactive'): ?>
                  <span class="badge bg-warning text-dark rounded-pill px-2 py-1"><i class="bi bi-pause-circle me-1"></i> Nonaktif</span>
                <?php else: ?>
                  <span class="badge bg-danger rounded-pill px-2 py-1"><i class="bi bi-slash-circle me-1"></i> Diblokir</span>
                <?php endif; ?>
              </td>
              <td>
                <small class="text-muted"><?= date('d M Y, H:i', strtotime($u['created_at'])) ?></small>
              </td>
              <td class="pe-4 text-center">
                <div class="btn-group">
                  <!-- Tombol Edit (Update) -->
                  <button type="button" class="btn btn-sm btn-outline-primary" title="Edit Pengguna" 
                          onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                    <i class="bi bi-pencil-square"></i>
                  </button>

                  <!-- Tombol Hapus (Delete) -->
                  <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                    <a href="<?= ADMIN_URL ?>users.php?action=delete&id=<?= $u['id'] ?>" 
                       class="btn btn-sm btn-outline-danger" 
                       title="Hapus Pengguna"
                       onclick="return confirm('Apakah Anda yakin ingin menghapus akun pengguna <?= htmlspecialchars(addslashes($u['name'])) ?> (<?= $u['email'] ?>)? Tindakan ini permanen.')">
                      <i class="bi bi-trash"></i>
                    </a>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Tidak bisa menghapus akun sendiri">
                      <i class="bi bi-lock"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============================================================== -->
<!-- MODAL CREATE: Form Tambah Pengguna Baru                        -->
<!-- ============================================================== -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="addUserModalLabel">
          <i class="bi bi-person-plus-fill text-primary-pink me-2"></i>Tambah Pengguna Baru
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?= ADMIN_URL ?>users.php" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" placeholder="Contoh: Sulistina Juliyanti" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Alamat Email <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="nama@email.com" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nomor WhatsApp / HP</label>
            <input type="text" name="phone" class="form-control" placeholder="Contoh: 08123456789">
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Hak Akses (Role) <span class="text-danger">*</span></label>
              <select name="role" class="form-select" required>
                <option value="customer">🛍️ Pelanggan (Customer)</option>
                <option value="staff">💼 Staf Kasir / CS</option>
                <option value="admin">🛡️ Administrator</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Status Akun <span class="text-danger">*</span></label>
              <select name="status" class="form-select" required>
                <option value="active" selected>Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="banned">Diblokir</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Kata Sandi Awal <span class="text-danger">*</span></label>
            <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required minlength="6">
            <small class="text-muted" style="font-size:0.75rem;">User dapat mengubah password ini setelah login.</small>
          </div>
        </div>

        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-nnasa rounded-pill px-4">
            <i class="bi bi-save me-1"></i> Simpan Pengguna
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- MODAL UPDATE: Form Edit Pengguna, Role & Reset Password        -->
<!-- ============================================================== -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="editUserModalLabel">
          <i class="bi bi-pencil-square text-primary-pink me-2"></i>Edit Pengguna &amp; Hak Akses
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form action="<?= ADMIN_URL ?>users.php" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" id="edit_user_id">

        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nama Lengkap <span class="text-danger">*</span></label>
            <input type="text" name="name" id="edit_name" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Alamat Email <span class="text-danger">*</span></label>
            <input type="email" name="email" id="edit_email" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nomor WhatsApp / HP</label>
            <input type="text" name="phone" id="edit_phone" class="form-control">
          </div>

          <div class="row g-2 mb-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Hak Akses (Role) <span class="text-danger">*</span></label>
              <select name="role" id="edit_role" class="form-select" required>
                <option value="customer">🛍️ Pelanggan (Customer)</option>
                <option value="staff">💼 Staf Kasir / CS</option>
                <option value="admin">🛡️ Administrator</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Status Akun <span class="text-danger">*</span></label>
              <select name="status" id="edit_status" class="form-select" required>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
                <option value="banned">Diblokir</option>
              </select>
            </div>
          </div>

          <div class="mb-3 p-3 rounded-3" style="background:#fff7f8; border:1px dashed #f2c7ce;">
            <label class="form-label small fw-bold text-dark mb-1">
              <i class="bi bi-key-fill text-primary-pink me-1"></i>Reset Kata Sandi (Opsional)
            </label>
            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah sandi" minlength="6">
            <small class="text-muted d-block mt-1" style="font-size:0.75rem;">Hanya isi kolom ini jika ingin mereset password akun pengguna ini.</small>
          </div>
        </div>

        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-nnasa rounded-pill px-4">
            <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openEditUserModal(user) {
  document.getElementById('edit_user_id').value = user.id;
  document.getElementById('edit_name').value = user.name;
  document.getElementById('edit_email').value = user.email;
  document.getElementById('edit_phone').value = user.phone || '';
  document.getElementById('edit_role').value = user.role;
  document.getElementById('edit_status').value = user.status;

  const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
  editModal.show();
}
</script>

<?php require_once defined('TEMPLATE_PATH') ? TEMPLATE_PATH . 'admin_footer.php' : (dirname(__DIR__, 3) . '/templates/admin_footer.php'); ?>
