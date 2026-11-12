<?php
/**
 * Customer Profile Page
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

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');

        if (empty($name) || empty($email)) {
            set_flash('danger', 'Nama dan Email wajib diisi.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('danger', 'Format email tidak valid.');
        } else {
            // Check email uniqueness if changed
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                set_flash('danger', 'Email sudah digunakan oleh akun lain.');
            } else {
                $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $email, $phone, $userId]);
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                set_flash('success', 'Profil kamu berhasil diperbarui! ✨');
                header('Location: ' . BASE_URL . 'profile.php');
                exit;
            }
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            set_flash('danger', 'Seluruh kolom kata sandi wajib diisi.');
        } elseif (strlen($newPass) < 6) {
            set_flash('danger', 'Kata sandi baru minimal 6 karakter.');
        } elseif ($newPass !== $confirmPass) {
            set_flash('danger', 'Konfirmasi kata sandi baru tidak cocok.');
        } else {
            // Verify current password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $hash = $stmt->fetchColumn();

            if (!password_verify($currentPass, $hash)) {
                set_flash('danger', 'Kata sandi saat ini salah.');
            } else {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newHash, $userId]);
                set_flash('success', 'Kata sandi berhasil diperbarui.');
                header('Location: ' . BASE_URL . 'profile.php');
                exit;
            }
        }
    }
}

// Re-fetch fresh user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$pageTitle = 'Profil Saya';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Profil Saya</li>
    </ol>
  </nav>

  <div class="row g-4">
    <!-- Sidebar navigation -->
    <div class="col-lg-3">
      <div class="card border-0 shadow-sm rounded-4 p-4 text-center" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center text-white" style="width: 72px; height: 72px; background: var(--rose-gold-gradient); font-size: 1.8rem; font-weight: 700;">
          <?= strtoupper(substr($user['name'], 0, 1)) ?>
        </div>
        <h5 class="fw-bold mb-1 text-dark fs-6"><?= htmlspecialchars($user['name']) ?></h5>
        <small class="text-muted d-block mb-3"><?= htmlspecialchars($user['email']) ?></small>
        
        <div class="list-group list-group-flush text-start small border-top pt-2">
          <a href="<?= BASE_URL ?>profile.php" class="list-group-item list-group-item-action border-0 py-2 fw-bold text-primary-pink">
            <i class="bi bi-person me-2"></i> Profil Akun
          </a>
          <a href="<?= BASE_URL ?>addresses.php" class="list-group-item list-group-item-action border-0 py-2 text-muted">
            <i class="bi bi-geo-alt me-2"></i> Buku Alamat
          </a>
          <a href="<?= BASE_URL ?>orders.php" class="list-group-item list-group-item-action border-0 py-2 text-muted">
            <i class="bi bi-box-seam me-2"></i> Pesanan Saya
          </a>
          <a href="<?= BASE_URL ?>wishlist.php" class="list-group-item list-group-item-action border-0 py-2 text-muted">
            <i class="bi bi-heart me-2"></i> Favorit Saya
          </a>
        </div>
      </div>
    </div>

    <!-- Main Profile Content -->
    <div class="col-lg-9">
      <!-- Edit Profile Card -->
      <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <h5 class="fw-bold text-dark fs-6 mb-3"><i class="bi bi-pencil-square text-primary-pink me-2"></i> Informasi Akun</h5>
        
        <form action="<?= BASE_URL ?>profile.php" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Nama Lengkap</label>
              <input type="text" name="name" class="form-control rounded-3" value="<?= htmlspecialchars($user['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Alamat Email</label>
              <input type="email" name="email" class="form-control rounded-3" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-bold text-dark mb-0">Nomor Handphone / WhatsApp</label>
                <?php if (!empty($user['phone_verified_at'])): ?>
                  <span class="badge bg-success-subtle text-success border border-success small">
                    <i class="bi bi-patch-check-fill me-1"></i> Terverifikasi WhatsApp
                  </span>
                <?php elseif (!empty($user['phone'])): ?>
                  <button type="button" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2 small" onclick="openPhoneVerifyModal('<?= htmlspecialchars($user['phone']) ?>')">
                    <i class="bi bi-whatsapp me-1"></i> Verifikasi WhatsApp
                  </button>
                <?php endif; ?>
              </div>
              <input type="text" name="phone" id="profilePhoneInput" class="form-control rounded-3" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="081234567890">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold text-dark">Peran Akun</label>
              <input type="text" class="form-control rounded-3 bg-light" value="<?= ucfirst($user['role']) ?>" readonly>
            </div>
          </div>

          <div class="mt-4 text-end">
            <button type="submit" class="btn btn-sm btn-nnasa rounded-pill px-4">
              Simpan Perubahan
            </button>
          </div>
        </form>
      </div>

      <!-- Change Password Card -->
      <div class="card border-0 shadow-sm rounded-4 p-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <h5 class="fw-bold text-dark fs-6 mb-3"><i class="bi bi-shield-lock text-primary-pink me-2"></i> Ganti Kata Sandi</h5>
        
        <form action="<?= BASE_URL ?>profile.php" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">

          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-bold text-dark">Kata Sandi Saat Ini</label>
              <input type="password" name="current_password" class="form-control rounded-3" required>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-dark">Kata Sandi Baru</label>
              <input type="password" name="new_password" class="form-control rounded-3" required minlength="6">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-bold text-dark">Konfirmasi Kata Sandi Baru</label>
              <input type="password" name="confirm_password" class="form-control rounded-3" required minlength="6">
            </div>
          </div>

          <div class="mt-4 text-end">
            <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-4">
              Perbarui Kata Sandi
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>
</div>

<!-- Modal: Verifikasi WhatsApp Akun -->
<div class="modal fade" id="modalVerifyPhone" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark">
          <i class="bi bi-whatsapp text-success me-2"></i>Verifikasi Nomor WhatsApp
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div id="modalWaAlert"></div>

        <div id="modalStepPhone">
          <p class="small text-muted mb-3">
            Pastikan nomor WhatsApp Anda aktif. Kami akan mengirimkan 6 digit kode OTP untuk memvalidasi nomor telepon akun Anda.
          </p>
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nomor WhatsApp</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-telephone text-muted"></i></span>
              <input type="tel" id="modalPhoneInput" class="form-control border-start-0 ps-0" placeholder="08123456789">
            </div>
            <div class="form-text small">Contoh: 08123456789 atau 628123456789</div>
          </div>
          <button type="button" class="btn btn-success w-100 py-2 rounded-pill fw-bold" id="btnModalSendOtp" onclick="sendProfileWaOtp()">
            <i class="bi bi-send-fill me-1"></i> Kirim Kode Verifikasi
          </button>
        </div>

        <div id="modalStepOtp" class="d-none">
          <div class="text-center p-3 bg-light rounded-3 mb-3 border">
            <small class="text-muted d-block mb-1">Kode OTP telah dikirimkan ke:</small>
            <span class="fw-bold text-dark font-monospace" id="modalMaskedPhone">+62 8xx-xxxx-xxxx</span>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark text-center d-block">Masukkan 6 Digit Kode OTP</label>
            <input type="text" id="modalOtpCode" maxlength="6" class="form-control text-center font-monospace fs-4 tracking-wider" placeholder="______">
            <div class="d-flex justify-content-between align-items-center mt-2 small">
              <span class="text-muted" id="modalCountdownText">Kirim ulang dalam <strong id="modalTimer">60</strong>s</span>
              <button type="button" class="btn btn-link p-0 text-decoration-none text-primary-pink small fw-semibold d-none" id="btnModalResendOtp" onclick="resendProfileWaOtp()">
                Kirim Ulang
              </button>
            </div>
          </div>

          <button type="button" class="btn btn-nnasa w-100 py-2 rounded-pill fw-bold" id="btnModalVerifyOtp" onclick="verifyProfileWaOtp()">
            <i class="bi bi-shield-check me-1"></i> Verifikasi Kode
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let profileActivePhone = '';
let profileTimerInterval = null;

function openPhoneVerifyModal(phone = '') {
  const currentVal = phone || document.getElementById('profilePhoneInput').value.trim();
  document.getElementById('modalPhoneInput').value = currentVal;
  document.getElementById('modalStepPhone').classList.remove('d-none');
  document.getElementById('modalStepOtp').classList.add('d-none');
  document.getElementById('modalWaAlert').innerHTML = '';
  const modal = new bootstrap.Modal(document.getElementById('modalVerifyPhone'));
  modal.show();
}

function sendProfileWaOtp() {
  const phone = document.getElementById('modalPhoneInput').value.trim();
  const btn = document.getElementById('btnModalSendOtp');

  if (!phone) {
    showModalAlert('danger', 'Nomor WhatsApp wajib diisi.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...';

  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=request', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: phone, purpose: 'phone_verify' })
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Kode Verifikasi';

    if (data.success) {
      profileActivePhone = phone;
      document.getElementById('modalMaskedPhone').innerText = data.destination_masked || phone;
      document.getElementById('modalStepPhone').classList.add('d-none');
      document.getElementById('modalStepOtp').classList.remove('d-none');
      document.getElementById('modalOtpCode').focus();
      showModalAlert('success', data.message || 'Kode OTP telah dikirimkan ke WhatsApp Anda.');
      startProfileTimer(data.resend_available_in || 60);

      // Trigger incoming WhatsApp notification toast!
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: phone,
          otp: data.simulated_otp || '',
          message: data.simulated_message || `Kode verifikasi WhatsApp akun nnasa_ca kamu adalah ${data.simulated_otp || ''}.`,
          autoFillSelector: '#modalOtpCode'
        });
      }
    } else {
      showModalAlert('danger', data.message || 'Gagal mengirim OTP.');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Kode Verifikasi';
    showModalAlert('danger', 'Kesalahan koneksi: ' + err.message);
  });
}

function verifyProfileWaOtp() {
  const code = document.getElementById('modalOtpCode').value.trim();
  const btn = document.getElementById('btnModalVerifyOtp');

  if (!code || code.length < 4) {
    showModalAlert('danger', 'Masukkan 6 digit kode verifikasi.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memverifikasi...';

  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: profileActivePhone,
      otp_code: code,
      purpose: 'phone_verify'
    })
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Verifikasi Kode';

    if (data.success) {
      showModalAlert('success', data.message || 'Verifikasi berhasil!');
      setTimeout(() => {
        window.location.reload();
      }, 1200);
    } else {
      showModalAlert('danger', data.message || 'Kode OTP salah atau kedaluwarsa.');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Verifikasi Kode';
    showModalAlert('danger', 'Kesalahan koneksi: ' + err.message);
  });
}

function resendProfileWaOtp() {
  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=resend', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: profileActivePhone, purpose: 'phone_verify' })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showModalAlert('success', data.message || 'Kode baru telah dikirim.');
      startProfileTimer(data.resend_available_in || 60);

      // Trigger incoming WhatsApp notification toast!
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: profileActivePhone,
          otp: data.simulated_otp || '',
          message: data.simulated_message || `Kode verifikasi baru WhatsApp akun nnasa_ca kamu adalah ${data.simulated_otp || ''}.`,
          autoFillSelector: '#modalOtpCode'
        });
      }
    } else {
      showModalAlert('danger', data.message || 'Gagal mengirim ulang.');
    }
  });
}

function startProfileTimer(seconds) {
  clearInterval(profileTimerInterval);
  let timeLeft = seconds;
  const timerElem = document.getElementById('modalTimer');
  const countText = document.getElementById('modalCountdownText');
  const resendBtn = document.getElementById('btnModalResendOtp');

  countText.classList.remove('d-none');
  resendBtn.classList.add('d-none');
  timerElem.innerText = timeLeft;

  profileTimerInterval = setInterval(() => {
    timeLeft--;
    timerElem.innerText = timeLeft;
    if (timeLeft <= 0) {
      clearInterval(profileTimerInterval);
      countText.classList.add('d-none');
      resendBtn.classList.remove('d-none');
    }
  }, 1000);
}

function showModalAlert(type, msg) {
  document.getElementById('modalWaAlert').innerHTML = `
    <div class="alert alert-${type} small p-2 rounded-3 mb-3 border-0">
      <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-1"></i> ${msg}
    </div>
  `;
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
