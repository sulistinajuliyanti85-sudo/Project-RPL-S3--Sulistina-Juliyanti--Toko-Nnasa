<?php
/**
 * Customer Account Recovery Page via WhatsApp OTP
 * Store: nnasa_ca Accessories Marketplace
 * Senior Backend & Cybersecurity Implementation
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$pageTitle = 'Pemulihan Akun (Reset Kata Sandi)';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="text-center mb-4">
          <a href="<?= BASE_URL ?>index.php" class="navbar-brand text-decoration-none">
            <span class="brand-logo-text fs-2">nnasa<span class="brand-dot">_ca</span></span>
          </a>
          <h1 class="h5 fw-bold text-dark mt-2">Pemulihan Akun</h1>
          <p class="text-muted small">Verifikasi nomor WhatsApp untuk mengatur ulang kata sandi akun belanja Anda secara aman.</p>
        </div>

        <div id="recoveryAlertContainer"></div>

        <!-- Step 1: Input Phone -->
        <div id="recoveryStepPhone">
          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nomor WhatsApp Terdaftar</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-whatsapp text-success"></i></span>
              <input type="tel" id="recoveryPhoneInput" class="form-control border-start-0 ps-0" placeholder="08123456789 atau 628123456789" autofocus>
            </div>
            <div class="form-text small text-muted">
              Kode OTP keamanan akan dikirimkan langsung ke nomor WhatsApp Anda.
            </div>
          </div>

          <button type="button" class="btn btn-success w-100 py-3 fw-bold rounded-pill" id="btnRequestRecoveryOtp" onclick="requestRecoveryOtp()">
            <i class="bi bi-send-fill me-1"></i> Kirim Kode Pemulihan
          </button>
        </div>

        <!-- Step 2: Input OTP -->
        <div id="recoveryStepOtp" class="d-none">
          <div class="text-center p-3 bg-light rounded-3 mb-3 border">
            <small class="text-muted d-block mb-1">Kode pemulihan telah dikirimkan ke:</small>
            <span class="fw-bold text-dark font-monospace" id="recoveryMaskedPhone">+62 8xx-xxxx-xxxx</span>
            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 ms-2 small" onclick="switchBackToPhone()">Ganti</button>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark text-center d-block">Masukkan 6 Digit Kode OTP</label>
            <input type="text" id="recoveryOtpCode" class="form-control text-center font-monospace fs-4 tracking-wider" maxlength="6" placeholder="______" autocomplete="one-time-code">
            <div class="d-flex justify-content-between align-items-center mt-2 small">
              <span class="text-muted" id="recoveryCountdownText">Kirim ulang dalam <strong id="recoveryTimer">60</strong>s</span>
              <button type="button" class="btn btn-link p-0 text-decoration-none text-primary-pink small fw-semibold d-none" id="btnResendRecoveryOtp" onclick="resendRecoveryOtp()">
                Kirim Ulang Kode
              </button>
            </div>
          </div>

          <button type="button" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill" id="btnVerifyRecoveryOtp" onclick="verifyRecoveryOtp()">
            <i class="bi bi-shield-check me-1"></i> Verifikasi & Buat Kata Sandi Baru
          </button>
        </div>

        <div class="text-center mt-4 pt-3 border-top">
          <a href="<?= BASE_URL ?>login.php" class="small text-muted text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke Halaman Masuk
          </a>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
let recoveryActivePhone = '';
let recoveryTimerInterval = null;

function showRecoveryAlert(type, message) {
  const container = document.getElementById('recoveryAlertContainer');
  container.innerHTML = `
    <div class="alert alert-${type} small p-3 rounded-3 mb-3 border-0 shadow-sm alert-dismissible fade show" role="alert">
      <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-1"></i> ${escapeHtml(message)}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  `;
}

function requestRecoveryOtp() {
  const phone = document.getElementById('recoveryPhoneInput').value.trim();
  const btn = document.getElementById('btnRequestRecoveryOtp');

  if (!phone) {
    showRecoveryAlert('danger', 'Nomor WhatsApp wajib diisi.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim OTP...';

  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=request', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: phone, purpose: 'password_reset' })
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Kode Pemulihan';

    if (data.success) {
      recoveryActivePhone = phone;
      document.getElementById('recoveryMaskedPhone').innerText = data.destination_masked || phone;
      document.getElementById('recoveryStepPhone').classList.add('d-none');
      document.getElementById('recoveryStepOtp').classList.remove('d-none');
      document.getElementById('recoveryOtpCode').focus();
      showRecoveryAlert('success', data.message || 'Kode verifikasi pemulihan telah dikirimkan ke WhatsApp Anda.');
      startRecoveryTimer(data.resend_available_in || 60);

      // Trigger incoming WhatsApp notification toast!
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: phone,
          otp: data.simulated_otp || '',
          message: data.simulated_message || `Kode pemulihan kata sandi nnasa_ca kamu adalah ${data.simulated_otp || ''}. Berlaku selama 5 menit.`,
          autoFillSelector: '#recoveryOtpCode'
        });
      }
    } else {
      showRecoveryAlert('danger', data.message || 'Gagal meminta kode pemulihan.');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Kode Pemulihan';
    showRecoveryAlert('danger', 'Kesalahan koneksi: ' + err.message);
  });
}

function verifyRecoveryOtp() {
  const code = document.getElementById('recoveryOtpCode').value.trim();
  const btn = document.getElementById('btnVerifyRecoveryOtp');

  if (!code || code.length < 4) {
    showRecoveryAlert('danger', 'Masukkan 6 digit kode pemulihan.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memverifikasi...';

  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: recoveryActivePhone,
      otp_code: code,
      purpose: 'password_reset'
    })
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Verifikasi & Buat Kata Sandi Baru';

    if (data.success && data.redirect) {
      showRecoveryAlert('success', data.message || 'Verifikasi berhasil! Mengalihkan...');
      setTimeout(() => {
        window.location.href = data.redirect;
      }, 1000);
    } else {
      showRecoveryAlert('danger', data.message || 'Kode OTP salah atau kedaluwarsa.');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Verifikasi & Buat Kata Sandi Baru';
    showRecoveryAlert('danger', 'Kesalahan koneksi: ' + err.message);
  });
}

function resendRecoveryOtp() {
  const btn = document.getElementById('btnResendRecoveryOtp');
  btn.disabled = true;

  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=resend', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: recoveryActivePhone, purpose: 'password_reset' })
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    if (data.success) {
      showRecoveryAlert('success', data.message || 'Kode baru berhasil dikirimkan.');
      startRecoveryTimer(data.resend_available_in || 60);

      // Trigger incoming WhatsApp notification toast!
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: recoveryActivePhone,
          otp: data.simulated_otp || '',
          message: data.simulated_message || `Kode pemulihan baru nnasa_ca kamu adalah ${data.simulated_otp || ''}. Berlaku 5 menit.`,
          autoFillSelector: '#recoveryOtpCode'
        });
      }
    } else {
      showRecoveryAlert('danger', data.message || 'Gagal mengirim ulang OTP.');
    }
  });
}

function switchBackToPhone() {
  clearInterval(recoveryTimerInterval);
  document.getElementById('recoveryStepOtp').classList.add('d-none');
  document.getElementById('recoveryStepPhone').classList.remove('d-none');
}

function startRecoveryTimer(seconds) {
  clearInterval(recoveryTimerInterval);
  let timeLeft = seconds;
  const timerElem = document.getElementById('recoveryTimer');
  const countText = document.getElementById('recoveryCountdownText');
  const resendBtn = document.getElementById('btnResendRecoveryOtp');

  countText.classList.remove('d-none');
  resendBtn.classList.add('d-none');
  timerElem.innerText = timeLeft;

  recoveryTimerInterval = setInterval(() => {
    timeLeft--;
    timerElem.innerText = timeLeft;
    if (timeLeft <= 0) {
      clearInterval(recoveryTimerInterval);
      countText.classList.add('d-none');
      resendBtn.classList.remove('d-none');
    }
  }, 1000);
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
