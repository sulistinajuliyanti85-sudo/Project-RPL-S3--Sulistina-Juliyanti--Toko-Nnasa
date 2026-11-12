<?php
/**
 * Admin Dedicated Login Page
 * Store: nnasa_ca Accessories Marketplace
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

// If already admin or staff, redirect to admin dashboard
if (is_admin() || is_staff()) {
    header('Location: ' . ADMIN_URL . 'index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF tidak valid. Silakan muat ulang halaman.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email dan kata sandi wajib diisi.';
        } else {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!in_array($user['role'], ['admin', 'staff'])) {
                    $error = 'Akun ini bukan administrator atau staf toko.';
                } elseif ($user['status'] !== 'active') {
                    $error = 'Akun administrator ini sedang dinonaktifkan.';
                } else {
                    login_user($user);
                    set_flash('success', 'Selamat bertugas, ' . htmlspecialchars($user['name']) . '! 💼');
                    header('Location: ' . ADMIN_URL . 'index.php');
                    exit;
                }
            } else {
                $error = 'Kredensial login admin tidak sesuai.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login Administrator — <?= APP_NAME ?></title>
  
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>public/assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: radial-gradient(circle at 50% 50%, #2e2226 0%, #1a1215 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #ffffff;
    }
    .admin-login-card {
      background: rgba(43, 31, 35, 0.95);
      border: 1px solid rgba(194, 125, 134, 0.25);
      border-radius: 20px;
      padding: 2.5rem;
      width: 100%;
      max-width: 440px;
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
    }
    .brand-title {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 2rem;
      color: #ffffff;
      font-weight: 700;
    }
    .brand-title span {
      color: #c27d86;
    }
    .form-control {
      background: #1c1417;
      border: 1px solid #443237;
      color: #ffffff;
      border-radius: 10px;
      padding: 0.75rem 1rem;
    }
    .form-control:focus {
      background: #241a1e;
      border-color: #c27d86;
      color: #ffffff;
      box-shadow: 0 0 0 3px rgba(194, 125, 134, 0.25);
    }
    .btn-admin-login {
      background: linear-gradient(135deg, #e8b4b8 0%, #c27d86 50%, #b76e79 100%);
      border: none;
      border-radius: 50px;
      padding: 0.75rem;
      font-weight: 700;
      color: #ffffff;
      transition: all 0.2s ease;
    }
    .btn-admin-login:hover {
      opacity: 0.95;
      transform: translateY(-2px);
    }
  </style>
</head>
<body>

  <div class="admin-login-card">
    <div class="text-center mb-4">
      <div class="brand-title">nnasa<span>_ca</span></div>
      <span class="badge bg-danger rounded-pill px-3 py-1 text-uppercase small" style="letter-spacing: 1px;">Admin Portal</span>
      <p class="text-muted small mt-2">Masuk ke panel manajemen toko aksesoris</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger small p-3 rounded-3 mb-3 border-0 bg-danger text-white">
        <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form action="<?= ADMIN_URL ?>login.php" method="POST">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label small text-muted">Email Administrator</label>
        <div class="input-group">
          <span class="input-group-text bg-dark border-secondary border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="admin@nnasaca.com" value="<?= htmlspecialchars($_POST['email'] ?? 'admin@nnasaca.com') ?>" required autofocus>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label small text-muted">Kata Sandi</label>
        <div class="input-group">
          <span class="input-group-text bg-dark border-secondary border-end-0 text-muted"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••" value="admin123" required>
        </div>
      </div>

      <button type="submit" class="btn btn-admin-login w-100">
        Masuk ke Dashboard <i class="bi bi-arrow-right ms-1"></i>
      </button>
    </form>

    <div class="text-center mt-4 pt-3 border-top border-secondary">
      <a href="<?= BASE_URL ?>" class="text-muted text-decoration-none small">
        <i class="bi bi-arrow-left me-1"></i> Kembali ke Website Toko
      </a>
    </div>
  </div>

</body>
</html>
