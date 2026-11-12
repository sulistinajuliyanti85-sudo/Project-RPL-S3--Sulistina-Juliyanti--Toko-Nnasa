<?php
/**
 * Customer & General Login Page with WhatsApp OTP Support
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';
require_once __DIR__ . '/app/Models/OTPModel.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$db = getDB();
$otpModel = new OTPModel($db);
$waOtpEnabled = ($otpModel->getSetting('whatsapp_otp_enabled', '1') === '1');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email dan kata sandi wajib diisi.';
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Akun Anda sedang dinonaktifkan atau diblokir. Silakan hubungi admin.';
                } else {
                    login_user($user);
                    set_flash('success', 'Selamat datang kembali, ' . htmlspecialchars($user['name']) . '! ✨');

                    if ($user['role'] === 'admin') {
                        header('Location: ' . ADMIN_URL . 'index.php');
                        exit;
                    }

                    $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . 'index.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                }
            } else {
                $error = 'Email atau kata sandi yang Anda masukkan salah.';
            }
        }
    }
}

$initialMethod = (($_GET['method'] ?? '') === 'whatsapp' || !empty($_GET['phone'])) ? 'whatsapp' : 'email';
$initialPhone = sanitize($_GET['phone'] ?? '');

$pageTitle = 'Masuk ke Akun';
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
          <h1 class="h5 fw-bold text-dark mt-2">Selamat Datang Kembali</h1>
          <p class="text-muted small">Pilih metode masuk untuk melanjutkan belanja aksesoris favoritmu</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger small p-3 rounded-3 mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <!-- Login Method Navigation Pills -->
        <ul class="nav nav-pills nav-fill mb-4 p-1 bg-light rounded-pill border" id="loginMethodTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $initialMethod === 'email' ? 'active' : '' ?> rounded-pill small fw-semibold py-2" id="tab-email-login-btn" data-bs-toggle="pill" data-bs-target="#tab-email-login" type="button">
              <i class="bi bi-envelope me-1"></i> Email &amp; Kata Sandi
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $initialMethod === 'whatsapp' ? 'active' : '' ?> rounded-pill small fw-semibold py-2" id="tab-wa-login-btn" data-bs-toggle="pill" data-bs-target="#tab-wa-login" type="button">
              <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp OTP
            </button>
          </li>
        </ul>

        <div class="tab-content" id="loginMethodTabContent">
          
          <!-- METHOD 1: EMAIL & PASSWORD -->
          <div class="tab-pane fade <?= $initialMethod === 'email' ? 'show active' : '' ?>" id="tab-email-login" role="tabpanel">
            <form action="<?= BASE_URL ?>login.php" method="POST">
              <?= csrf_field() ?>

              <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Alamat Email</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                  <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="nama@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
              </div>

              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <label class="form-label small fw-bold text-dark mb-0">Kata Sandi</label>
                  <a href="<?= BASE_URL ?>forgot-password.php" class="small text-primary-pink text-decoration-none">Lupa Sandi?</a>
                </div>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                  <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
                </div>
              </div>

              <button type="submit" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill mt-2">
                Masuk Sekarang <i class="bi bi-box-arrow-in-right ms-1"></i>
              </button>
            </form>
          </div>

          <!-- METHOD 2: WHATSAPP OTP -->
          <div class="tab-pane fade <?= $initialMethod === 'whatsapp' ? 'show active' : '' ?>" id="tab-wa-login" role="tabpanel">
            <!-- Alert Container for WA Login -->
            <div id="waLoginAlert"></div>

            <!-- Step 1: Input Phone -->
            <div id="waStepPhone">
              <div class="mb-3">
                <label class="form-label small fw-bold text-dark">Nomor WhatsApp Terdaftar</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="bi bi-whatsapp text-success"></i></span>
                  <input type="tel" id="waLoginPhone" class="form-control border-start-0 ps-0" placeholder="Contoh: 08123456789 atau 628123456789" value="<?= htmlspecialchars($initialPhone) ?>" autofocus>
                </div>
                <div class="form-text small text-muted">Kami akan mengirimkan 6 digit kode OTP rahasia ke WhatsApp Anda.</div>
              </div>

              <button type="button" class="btn btn-success w-100 py-3 fw-bold rounded-pill mt-2" id="btnRequestWaOtp" onclick="requestWaLoginOtp()">
                <i class="bi bi-send-fill me-1"></i> Kirim Kode OTP ke WhatsApp
              </button>
            </div>

            <!-- Step 2: Input OTP (Hidden initially) -->
            <div id="waStepOtp" class="d-none">
              <div class="text-center p-3 bg-light rounded-3 mb-3 border">
                <small class="text-muted d-block mb-1">Kode OTP telah dikirimkan ke:</small>
                <span class="fw-bold text-dark font-monospace" id="waMaskedPhoneDisplay">+62 8xx-xxxx-xxxx</span>
                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 ms-2 small" onclick="switchBackToPhone()">Ganti</button>
              </div>

              <div class="mb-3">
                <label class="form-label small fw-bold text-dark">6 Digit Kode OTP WhatsApp</label>
                <input type="text" id="waLoginCode" class="form-control text-center font-monospace fs-4 tracking-wider" maxlength="6" placeholder="______" autocomplete="one-time-code">
                <div class="d-flex justify-content-between align-items-center mt-2 small">
                  <span class="text-muted" id="waCountdownText">Kirim ulang dalam <strong id="waTimer">60</strong> detik</span>
                  <button type="button" class="btn btn-link p-0 text-decoration-none text-primary-pink small fw-semibold d-none" id="btnResendWaOtp" onclick="resendWaLoginOtp()">
                    Kirim Ulang Kode
                  </button>
                </div>
              </div>

              <button type="button" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill mt-2" id="btnVerifyWaOtp" onclick="verifyWaLoginOtp()">
                <i class="bi bi-shield-check me-1"></i> Verifikasi & Masuk
              </button>
            </div>
          </div>

        </div>

        <div class="p-3 bg-light rounded-3 mt-4 border">
          <small class="text-muted d-block fw-bold text-center mb-2">Akun Demo Pengujian:</small>
          <div class="d-flex flex-column gap-2">
            <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded-2 border small">
              <div>
                <span class="badge bg-success-subtle text-success border border-success me-1">WA</span>
                <strong>Pelanggan:</strong> <code>081387654321</code>
                <div class="text-muted" style="font-size:11px;">(anisa@gmail.com / customer123)</div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2" style="font-size:11px;" onclick="fillDemoWa('081387654321', 'anisa@gmail.com', 'customer123')">
                Gunakan
              </button>
            </div>
            <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded-2 border small">
              <div>
                <span class="badge bg-success-subtle text-success border border-success me-1">WA</span>
                <strong>Admin:</strong> <code>081298765432</code>
                <div class="text-muted" style="font-size:11px;">(admin@nnasaca.com / admin123)</div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-success rounded-pill py-0 px-2" style="font-size:11px;" onclick="fillDemoWa('081298765432', 'admin@nnasaca.com', 'admin123')">
                Gunakan
              </button>
            </div>
          </div>
          <small class="text-muted text-center d-block mt-2" style="font-size: 11px;">
            <i class="bi bi-info-circle me-1"></i> Klik <strong>Gunakan</strong> untuk mengisi otomatis form login.
          </small>
        </div>

        <div class="text-center mt-4">
          <p class="small text-muted mb-0">
            Belum memiliki akun? <a href="<?= BASE_URL ?>register.php" class="text-primary-pink fw-bold text-decoration-none">Daftar Sekarang</a>
          </p>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
let waCurrentPhone = '';
let waTimerInterval = null;

function fillDemoWa(phone, email, password) {
  // Check active tab
  const activeTab = document.querySelector('#loginMethodTabs .nav-link.active');
  const isWaTab = activeTab && activeTab.id === 'tab-wa-login-btn';

  if (isWaTab) {
    const phoneInput = document.getElementById('waLoginPhone');
    if (phoneInput) {
      phoneInput.value = phone;
      phoneInput.focus();
    }
  } else {
    // Fill email and password
    const emailInput = document.querySelector('input[name="email"]');
    const passInput = document.querySelector('input[name="password"]');
    if (emailInput) emailInput.value = email;
    if (passInput) passInput.value = password;
  }

  if (typeof window.showToast === 'function') {
    window.showToast('Akun demo berhasil diisi (' + phone + ')', 'success');
  }
}

function showWaAlert(type, message, extraHtml = '') {
  const container = document.getElementById('waLoginAlert');
  container.innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show small p-3 rounded-3 mb-3 border-0 shadow-sm" role="alert">
      <div class="d-flex align-items-start">
        <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} fs-5 me-2 mt-0"></i>
        <div class="flex-grow-1">
          <div>${escapeHtml(message)}</div>
          ${extraHtml}
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  `;
}

function requestWaLoginOtp() {
  const phone = document.getElementById('waLoginPhone').value.trim();
  const btn = document.getElementById('btnRequestWaOtp');

  if (!phone) {
    showWaAlert('danger', 'Silakan masukkan nomor WhatsApp Anda.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim OTP...';

  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=request', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: phone, purpose: 'login_whatsapp' })
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Kode OTP ke WhatsApp';

    if (data.success) {
      waCurrentPhone = phone;
      document.getElementById('waMaskedPhoneDisplay').innerText = data.destination_masked || phone;
      document.getElementById('waStepPhone').classList.add('d-none');
      document.getElementById('waStepOtp').classList.remove('d-none');
      document.getElementById('waLoginCode').focus();
      showWaAlert('success', data.message || 'Kode OTP telah dikirimkan ke WhatsApp Anda.');
      startWaTimer(data.resend_available_in || 60);

      // Trigger high-visibility Incoming WhatsApp notification toast!
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: data.destination_masked || phone,
          otp: data.simulated_otp || '',
          sender: 'nnasa_ca WhatsApp Official',
          message: data.simulated_message || `Halo Kak! Kode verifikasi resmi nnasa_ca telah dikirim ke nomor WhatsApp Anda (${data.destination_masked || phone}). Silakan masukkan 6 digit kode OTP rahasia untuk masuk ke akun Anda.`,
          autoFillSelector: '#waLoginCode'
        });
      }
    } else {
      let extraAction = '';
      if (data.message && data.message.includes('belum terdaftar')) {
        extraAction = `
          <div class="mt-2">
            <a href="<?= BASE_URL ?>register.php?phone=${encodeURIComponent(phone)}" class="btn btn-sm btn-outline-danger rounded-pill fw-semibold" style="font-size:11px;">
              <i class="bi bi-person-plus-fill me-1"></i> Daftar Akun dengan Nomor Ini
            </a>
          </div>
        `;
      }
      showWaAlert('danger', data.message || 'Gagal mengirim kode OTP WhatsApp.', extraAction);
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Kode OTP ke WhatsApp';
    showWaAlert('danger', 'Terjadi kesalahan koneksi: ' + err.message);
  });
}

function verifyWaLoginOtp() {
  const code = document.getElementById('waLoginCode').value.trim();
  const btn = document.getElementById('btnVerifyWaOtp');

  if (!code || code.length < 4) {
    showWaAlert('danger', 'Masukkan 6 digit kode OTP yang diterima.');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Memverifikasi...';

  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      phone: waCurrentPhone,
      otp_code: code,
      purpose: 'login_whatsapp'
    })
  })
  .then(res => res.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Verifikasi & Masuk';

    if (data.success) {
      showWaAlert('success', data.message || 'Login berhasil! Mengalihkan...');
      setTimeout(() => {
        window.location.href = data.redirect || '<?= BASE_URL ?>index.php';
      }, 1000);
    } else {
      showWaAlert('danger', data.message || 'Kode OTP salah atau kedaluwarsa.');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Verifikasi & Masuk';
    showWaAlert('danger', 'Terjadi kesalahan jaringan: ' + err.message);
  });
}

function resendWaLoginOtp() {
  const btnResend = document.getElementById('btnResendWaOtp');
  btnResend.disabled = true;

  fetch('<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=resend', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ phone: waCurrentPhone, purpose: 'login_whatsapp' })
  })
  .then(res => res.json())
  .then(data => {
    btnResend.disabled = false;
    if (data.success) {
      showWaAlert('success', data.message || 'Kode OTP baru berhasil dikirim ulang.');
      startWaTimer(data.resend_available_in || 60);

      // Trigger high-visibility Incoming WhatsApp notification toast!
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: data.destination_masked || waCurrentPhone,
          otp: data.simulated_otp || '',
          sender: 'nnasa_ca WhatsApp Official',
          message: data.simulated_message || `Halo Kak! Kode verifikasi baru nnasa_ca telah dikirim ulang ke nomor WhatsApp Anda (${data.destination_masked || waCurrentPhone}). Berlaku 5 menit.`,
          autoFillSelector: '#waLoginCode'
        });
      }
    } else {
      showWaAlert('danger', data.message || 'Gagal mengirim ulang OTP.');
    }
  })
  .catch(err => {
    btnResend.disabled = false;
    showWaAlert('danger', 'Kesalahan koneksi: ' + err.message);
  });
}

function switchBackToPhone() {
  clearInterval(waTimerInterval);
  document.getElementById('waStepOtp').classList.add('d-none');
  document.getElementById('waStepPhone').classList.remove('d-none');
}

function startWaTimer(seconds) {
  clearInterval(waTimerInterval);
  let timeLeft = seconds;
  const timerElem = document.getElementById('waTimer');
  const countdownText = document.getElementById('waCountdownText');
  const resendBtn = document.getElementById('btnResendWaOtp');

  countdownText.classList.remove('d-none');
  resendBtn.classList.add('d-none');
  timerElem.innerText = timeLeft;

  waTimerInterval = setInterval(() => {
    timeLeft--;
    timerElem.innerText = timeLeft;
    if (timeLeft <= 0) {
      clearInterval(waTimerInterval);
      countdownText.classList.add('d-none');
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
