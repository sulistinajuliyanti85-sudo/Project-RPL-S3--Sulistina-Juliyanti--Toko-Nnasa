<?php
/**
 * Customer Set New Password Page
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

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$sessionToken = $_SESSION['wa_pwd_reset_token'] ?? '';
$sessionUserId = $_SESSION['wa_pwd_reset_user_id'] ?? null;
$sessionExpires = $_SESSION['wa_pwd_reset_expires'] ?? 0;

// Validate Reset Token
if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token) || time() > $sessionExpires || !$sessionUserId) {
    set_flash('danger', 'Sesi pengaturan ulang kata sandi tidak valid atau telah kedaluwarsa. Silakan minta kode OTP baru.');
    header('Location: ' . BASE_URL . 'forgot-password.php');
    exit;
}

$db = getDB();
$stmtUser = $db->prepare("SELECT id, name, email FROM users WHERE id = ? AND status = 'active' LIMIT 1");
$stmtUser->execute([$sessionUserId]);
$targetUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    set_flash('danger', 'Akun tidak ditemukan atau sedang tidak aktif.');
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($newPass) || empty($confirmPass)) {
            $error = 'Seluruh kolom kata sandi wajib diisi.';
        } elseif (strlen($newPass) < 6) {
            $error = 'Kata sandi baru minimal 6 karakter.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Konfirmasi kata sandi baru tidak cocok.';
        } else {
            // Hash and update password
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmtUp = $db->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $stmtUp->execute([$newHash, $targetUser['id']]);

            // Invalidate reset session
            unset(
                $_SESSION['wa_pwd_reset_token'],
                $_SESSION['wa_pwd_reset_user_id'],
                $_SESSION['wa_pwd_reset_expires'],
                $_SESSION['wa_pwd_reset_phone']
            );

            log_activity($targetUser['id'], 'password_reset_whatsapp', 'users', $targetUser['id'], 'Password diatur ulang via verifikasi OTP WhatsApp');

            set_flash('success', 'Kata sandi berhasil diperbarui! Silakan masuk dengan kata sandi baru Anda. ✨');
            header('Location: ' . BASE_URL . 'login.php');
            exit;
        }
    }
}

$pageTitle = 'Atur Kata Sandi Baru';
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
          <h1 class="h5 fw-bold text-dark mt-2">Buat Kata Sandi Baru</h1>
          <p class="text-muted small">Halo <strong><?= htmlspecialchars($targetUser['name']) ?></strong>, silakan buat kata sandi baru yang kuat untuk akun Anda.</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger small p-3 rounded-3 mb-3">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>reset-password.php" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Kata Sandi Baru</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock text-muted"></i></span>
              <input type="password" name="new_password" class="form-control border-start-0 ps-0" placeholder="Minimal 6 karakter" required minlength="6" autofocus>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold text-dark">Konfirmasi Kata Sandi Baru</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-shield-lock text-muted"></i></span>
              <input type="password" name="confirm_password" class="form-control border-start-0 ps-0" placeholder="Ulangi kata sandi baru" required minlength="6">
            </div>
          </div>

          <button type="submit" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill">
            <i class="bi bi-check2-circle me-1"></i> Simpan Kata Sandi Baru
          </button>
        </form>

        <div class="text-center mt-4 pt-3 border-top">
          <a href="<?= BASE_URL ?>login.php" class="small text-muted text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i> Batal & Masuk
          </a>
        </div>

      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
