<?php
/**
 * Customer Registration Page with WhatsApp & Email OTP Verification
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';
require_once __DIR__ . '/app/Helpers/security.php';
require_once __DIR__ . '/app/Models/OTPModel.php';
require_once __DIR__ . '/app/Services/OTPService.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . 'index.php');
    exit;
}

$db = getDB();
$otpModel = new OTPModel($db);
$otpService = new OTPService($db);

$otpEnabled = ($otpModel->getSetting('otp_enabled', '1') === '1');
$error = null;
$successMsg = null;
$step = 1;

$phoneParam = sanitize($_GET['phone'] ?? '');

$regDraft = [
    'name' => sanitize($_POST['name'] ?? ''),
    'email' => sanitize($_POST['email'] ?? ''),
    'phone' => !empty($_POST['phone']) ? sanitize($_POST['phone']) : $phoneParam,
    'password' => $_POST['password'] ?? '',
    'confirm_password' => $_POST['confirm_password'] ?? '',
    'otp_channel' => sanitize($_POST['otp_channel'] ?? 'whatsapp')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $submittedStep = (int)($_POST['step'] ?? 1);

        if ($submittedStep === 1) {
            // Validate Basic Form Inputs
            if (empty($regDraft['name']) || empty($regDraft['email']) || empty($regDraft['password'])) {
                $error = 'Nama, email, dan kata sandi wajib diisi.';
            } elseif (!filter_var($regDraft['email'], FILTER_VALIDATE_EMAIL)) {
                $error = 'Format alamat email tidak valid.';
            } elseif (strlen($regDraft['password']) < 6) {
                $error = 'Kata sandi minimal 6 karakter.';
            } elseif ($regDraft['password'] !== $regDraft['confirm_password']) {
                $error = 'Konfirmasi kata sandi tidak cocok.';
            } else {
                // Check if email already registered
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$regDraft['email']]);
                if ($stmt->fetch()) {
                    $error = 'Email ini sudah terdaftar. Silakan login atau gunakan email lain.';
                } else {
                    $channel = ($regDraft['otp_channel'] === 'email') ? 'email' : 'whatsapp';
                    $targetDestination = $regDraft['email'];

                    if ($channel === 'whatsapp') {
                        $normPhone = normalize_phone_number($regDraft['phone']);
                        if (!$normPhone) {
                            $error = 'Nomor WhatsApp wajib diisi dengan format valid (contoh: 08123456789 atau 628123456789).';
                        } else {
                            // Check if phone already registered
                            $stmtP = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
                            $stmtP->execute([$normPhone]);
                            if ($stmtP->fetch()) {
                                $error = 'Nomor WhatsApp ini sudah terdaftar. Silakan login atau gunakan nomor lain.';
                            } else {
                                $targetDestination = $normPhone;
                                $regDraft['phone'] = $normPhone;
                            }
                        }
                    }

                    if (!$error) {
                        if ($otpEnabled) {
                            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
                            $otpRes = $otpService->requestOTP($targetDestination, 'register', null, $clientIp);

                            if ($otpRes['success']) {
                                $step = 2;
                                $successMsg = $otpRes['message'];
                                $regDraft['active_destination'] = $targetDestination;
                                $regDraft['destination_masked'] = $otpRes['destination_masked'];
                                $regDraft['channel'] = $channel;
                                $regDraft['simulated_otp'] = $otpRes['simulated_otp'] ?? null;
                                $regDraft['simulated_message'] = $otpRes['simulated_message'] ?? null;
                                $_SESSION['reg_draft'] = $regDraft;
                            } else {
                                $error = $otpRes['message'];
                            }
                        } else {
                            // OTP disabled globally: create user immediately
                            $passwordHash = password_hash($regDraft['password'], PASSWORD_DEFAULT);
                            $stmt = $db->prepare("
                                INSERT INTO users (name, email, password_hash, phone, role, status)
                                VALUES (?, ?, ?, ?, 'customer', 'active')
                            ");
                            $stmt->execute([$regDraft['name'], $regDraft['email'], $passwordHash, $regDraft['phone']]);
                            $userId = (int)$db->lastInsertId();

                            $db->prepare("INSERT INTO carts (user_id) VALUES (?)")->execute([$userId]);

                            login_user([
                                'id' => $userId,
                                'name' => $regDraft['name'],
                                'email' => $regDraft['email'],
                                'role' => 'customer'
                            ]);

                            set_flash('success', 'Registrasi berhasil! Selamat bergabung di nnasa_ca 💕');
                            header('Location: ' . BASE_URL . 'index.php');
                            exit;
                        }
                    }
                }
            }
        } elseif ($submittedStep === 2) {
            // Verify Submitted OTP
            $draft = $_SESSION['reg_draft'] ?? $regDraft;
            $destination = $draft['active_destination'] ?? ($draft['email'] ?? '');
            $otpCode = trim($_POST['otp_code'] ?? '');

            if (empty($destination) || empty($otpCode)) {
                $error = 'Kode verifikasi OTP wajib diisi.';
                $step = 2;
            } else {
                $vRes = $otpService->verifyOTP($destination, 'register', $otpCode);

                if ($vRes['success']) {
                    // Create user in database with verified status
                    $passwordHash = password_hash($draft['password'], PASSWORD_DEFAULT);
                    $isWa = !filter_var($destination, FILTER_VALIDATE_EMAIL);

                    $stmt = $db->prepare("
                        INSERT INTO users (name, email, password_hash, phone, role, status, email_verified_at, phone_verified_at)
                        VALUES (?, ?, ?, ?, 'customer', 'active', ?, ?)
                    ");
                    $stmt->execute([
                        $draft['name'],
                        $draft['email'],
                        $passwordHash,
                        $draft['phone'],
                        $isWa ? null : date('Y-m-d H:i:s'),
                        $isWa ? date('Y-m-d H:i:s') : null
                    ]);
                    $userId = (int)$db->lastInsertId();

                    // Create cart
                    $db->prepare("INSERT INTO carts (user_id) VALUES (?)")->execute([$userId]);

                    // Clean session draft
                    unset($_SESSION['reg_draft']);

                    // Login
                    login_user([
                        'id' => $userId,
                        'name' => $draft['name'],
                        'email' => $draft['email'],
                        'role' => 'customer'
                    ]);

                    set_flash('success', 'Akun berhasil diverifikasi! Selamat datang di nnasa_ca 💕');
                    header('Location: ' . BASE_URL . 'index.php');
                    exit;
                } else {
                    $error = $vRes['message'];
                    $step = 2;
                }
            }
        }
    }
}

$pageTitle = 'Daftar Akun Baru';
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
          <h1 class="h5 fw-bold text-dark mt-2">
            <?= $step === 2 ? 'Verifikasi Kode OTP' : 'Buat Akun nnasa_ca' ?>
          </h1>
          <p class="text-muted small">
            <?= $step === 2 
              ? 'Masukkan 6 digit kode verifikasi yang telah dikirim ke ' . htmlspecialchars($_SESSION['reg_draft']['destination_masked'] ?? '')
              : 'Dapatkan pengalaman belanja aksesoris mewah dengan mudah' ?>
          </p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger small p-3 rounded-3 mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
          <div class="alert alert-success small p-3 rounded-3 mb-3">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($successMsg) ?>
          </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
          <!-- Step 1: Registration Form -->
          <form action="<?= BASE_URL ?>register.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="1">

            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Nama Lengkap</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                <input type="text" name="name" class="form-control border-start-0 ps-0" placeholder="Nama Anda" value="<?= htmlspecialchars($regDraft['name']) ?>" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Alamat Email</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-envelope text-muted"></i></span>
                <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="nama@email.com" value="<?= htmlspecialchars($regDraft['email']) ?>" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Nomor WhatsApp Aktif</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-whatsapp text-success"></i></span>
                <input type="tel" name="phone" id="regPhone" class="form-control border-start-0 ps-0" placeholder="08123456789 atau 628123456789" value="<?= htmlspecialchars($regDraft['phone']) ?>" required>
              </div>
              <div class="form-text small">Nomor WhatsApp diperlukan untuk pengiriman kode OTP verifikasi dan info pesanan.</div>
            </div>

            <!-- OTP Channel Selection -->
            <div class="mb-3 p-3 bg-light rounded-3 border">
              <label class="form-label small fw-bold text-dark mb-2 d-block">Metode Pengiriman OTP:</label>
              <div class="d-flex gap-3">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="otp_channel" id="channelWa" value="whatsapp" <?= $regDraft['otp_channel'] !== 'email' ? 'checked' : '' ?>>
                  <label class="form-check-label small fw-semibold text-dark" for="channelWa">
                    <i class="bi bi-whatsapp text-success me-1"></i> WhatsApp OTP (Cepat)
                  </label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="otp_channel" id="channelEmail" value="email" <?= $regDraft['otp_channel'] === 'email' ? 'checked' : '' ?>>
                  <label class="form-check-label small fw-semibold text-dark" for="channelEmail">
                    <i class="bi bi-envelope text-primary me-1"></i> Email OTP
                  </label>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Kata Sandi</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
                <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Minimal 6 karakter" required minlength="6">
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label small fw-bold text-dark">Konfirmasi Kata Sandi</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-lock text-muted"></i></span>
                <input type="password" name="confirm_password" class="form-control border-start-0 ps-0" placeholder="Ulangi kata sandi" required minlength="6">
              </div>
            </div>

            <button type="submit" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill">
              <?= $otpEnabled ? 'Lanjutkan & Dapatkan OTP' : 'Daftar Sekarang' ?> <i class="bi bi-arrow-right ms-1"></i>
            </button>
          </form>

        <?php elseif ($step === 2): ?>
          <!-- Step 2: OTP Verification Form -->
          <?php 
          $draft = $_SESSION['reg_draft'] ?? $regDraft;
          $activeDest = $draft['active_destination'] ?? ($draft['email'] ?? '');
          $simulatedOtp = $draft['simulated_otp'] ?? '';
          $isWa = ($draft['channel'] ?? 'whatsapp') === 'whatsapp';
          ?>

          <?php if (!empty($simulatedOtp)): ?>
            <!-- Simulated OTP Test Card (Displayed when gateway token is not configured or in test mode) -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 simulated-otp-card" id="simulatedOtpCard" style="background: #f0fdf4; border: 1px solid #86efac !important;">
              <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                  <i class="bi <?= $isWa ? 'bi-whatsapp' : 'bi-envelope' ?> me-1"></i> Mode Simulasi (Kode Pengujian)
                </span>
                <span class="small text-muted font-monospace"><?= htmlspecialchars($draft['destination_masked'] ?? $activeDest) ?></span>
              </div>
              <p class="small text-secondary mb-2" style="font-size: 12.5px; line-height: 1.4;">
                <?= $isWa 
                  ? 'Token API WhatsApp belum diatur di server (<code>.env</code> / menu Admin), sehingga kode OTP verifikasi ditampilkan langsung di bawah ini:' 
                  : 'Mode pengujian aktif, kode OTP Anda ditampilkan di bawah ini:' ?>
              </p>
              <div class="d-flex align-items-center justify-content-between gap-2 p-2 bg-white rounded-3 border mb-2">
                <div>
                  <small class="text-muted d-block fw-semibold" style="font-size: 11px;">KODE OTP VERIFIKASI:</small>
                  <span class="fs-4 fw-bold font-monospace text-dark letter-spacing-2" id="simulatedOtpDisplay"><?= htmlspecialchars($simulatedOtp) ?></span>
                </div>
                <button type="button" class="btn btn-sm btn-success rounded-pill px-3 py-2 fw-bold" onclick="autoFillOtp('<?= htmlspecialchars($simulatedOtp) ?>')">
                  <i class="bi bi-lightning-charge-fill me-1"></i> Isi Otomatis
                </button>
              </div>
              <small class="text-muted" style="font-size: 11px;">
                <i class="bi bi-info-circle me-1"></i> Untuk mengirim ke WhatsApp nomor asli di masa mendatang, masukkan Token Fonnte di file <code>.env</code> atau menu <strong>Admin &gt; Konfigurasi OTP WhatsApp</strong>.
              </small>
            </div>
          <?php endif; ?>

          <form action="<?= BASE_URL ?>register.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="2">
            <input type="hidden" name="destination" value="<?= htmlspecialchars($activeDest) ?>">

            <div class="mb-4 text-center">
              <label class="form-label small fw-bold text-dark d-block mb-2">Kode OTP (6 Digit)</label>
              <input type="text" name="otp_code" id="registerOtpInput" maxlength="8" class="form-control text-center fs-3 fw-bold letter-spacing-2 font-monospace" placeholder="______" value="<?= htmlspecialchars($simulatedOtp) ?>" autofocus required style="letter-spacing: 8px;">
              <small class="text-muted mt-2 d-block">
                Kode berlaku selama 5 menit. Jangan bagikan kode ini kepada siapa pun.
              </small>
            </div>

            <button type="submit" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill mb-3">
              <i class="bi bi-check-circle me-1"></i> Verifikasi & Buat Akun
            </button>

            <div class="text-center">
              <button type="button" class="btn btn-sm btn-link text-decoration-none text-muted" id="btnResendOtp" onclick="resendRegistrationOtp('<?= htmlspecialchars($activeDest) ?>')">
                <i class="bi bi-arrow-repeat me-1"></i> Kirim Ulang Kode OTP
              </button>
              <div id="resendAlert" class="mt-2 small" style="display:none;"></div>
            </div>
          </form>
        <?php endif; ?>

        <div class="text-center mt-4">
          <p class="small text-muted mb-0">
            Sudah punya akun? <a href="<?= BASE_URL ?>login.php" class="text-primary-pink fw-bold text-decoration-none">Masuk di sini</a>
          </p>
        </div>

      </div>
    </div>
  </div>
</div>

<script>
<?php if ($step === 2 && !empty($_SESSION['reg_draft']['simulated_otp'])): ?>
(function() {
  const otpData = <?= json_encode([
    'channel' => $_SESSION['reg_draft']['channel'] ?? 'whatsapp',
    'phone' => $_SESSION['reg_draft']['active_destination'] ?? '',
    'otp' => (string)$_SESSION['reg_draft']['simulated_otp'],
    'message' => $_SESSION['reg_draft']['simulated_message'] ?? '',
    'autoFillSelector' => 'input[name="otp_code"]'
  ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

  function initSimulatedOtp() {
    if (typeof window.showIncomingOtpNotification === 'function') {
      window.showIncomingOtpNotification(otpData);
    }
    const input = document.getElementById('registerOtpInput') || document.querySelector('input[name="otp_code"]');
    if (input && !input.value) {
      input.value = otpData.otp;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSimulatedOtp);
  } else {
    initSimulatedOtp();
  }
})();
<?php endif; ?>

function autoFillOtp(code) {
  const input = document.getElementById('registerOtpInput') || document.querySelector('input[name="otp_code"]');
  if (input) {
    input.value = code;
    input.focus();
    if (typeof window.showToast === 'function') {
      window.showToast('Kode OTP ' + code + ' telah diisi!', 'success');
    }
  }
}

function resendRegistrationOtp(dest) {
  const btn = document.getElementById('btnResendOtp');
  const alertBox = document.getElementById('resendAlert');
  alertBox.style.display = 'block';
  alertBox.className = 'text-info';
  alertBox.innerText = 'Mengirim ulang kode OTP...';
  btn.disabled = true;

  const isEmail = dest.includes('@');
  const targetApi = isEmail ? '<?= BASE_URL ?>api/auth-otp.php?action=resend' : '<?= BASE_URL ?>api/auth-whatsapp-otp.php?action=resend';

  const formData = new FormData();
  if (isEmail) {
    formData.append('destination', dest);
  } else {
    formData.append('phone', dest);
  }
  formData.append('purpose', 'register');

  fetch(targetApi, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alertBox.className = 'text-success fw-semibold';
      alertBox.innerText = data.message;

      // Auto update input & display if simulated OTP
      if (data.simulated_otp) {
        autoFillOtp(data.simulated_otp);
        const simDisplay = document.getElementById('simulatedOtpDisplay');
        if (simDisplay) simDisplay.innerText = data.simulated_otp;
        const simCard = document.getElementById('simulatedOtpCard');
        if (simCard) simCard.style.display = 'block';
      }

      // Trigger incoming notification toast
      if (typeof window.showIncomingOtpNotification === 'function') {
        window.showIncomingOtpNotification({
          channel: isEmail ? 'email' : 'whatsapp',
          phone: dest,
          otp: data.simulated_otp || '',
          message: data.simulated_message || data.message,
          autoFillSelector: 'input[name="otp_code"]'
        });
      }
    } else {
      alertBox.className = 'text-danger fw-semibold';
      alertBox.innerText = data.message;
    }
    setTimeout(() => { btn.disabled = false; }, 10000);
  })
  .catch(() => {
    alertBox.className = 'text-danger';
    alertBox.innerText = 'Gagal menghubungi server.';
    btn.disabled = false;
  });
}
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
