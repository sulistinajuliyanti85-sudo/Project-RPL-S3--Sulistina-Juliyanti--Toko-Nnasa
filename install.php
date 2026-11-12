<?php
/**
 * One-Click Web Installer & Seeder
 * Store: nnasa_ca Accessories Marketplace
 */

define('INSTALLER_MODE', true);
require_once __DIR__ . '/app/Config/config.php';

$success = false;
$message = '';
$logs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_install'])) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $logs[] = "Berhasil terhubung ke MySQL Server pada " . DB_HOST . ":" . DB_PORT;

        // Create Database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        $logs[] = "Database `" . DB_NAME . "` siap digunakan.";

        // Run Schema
        $schemaPath = __DIR__ . '/database/schema.sql';
        if (file_exists($schemaPath)) {
            $schemaSql = file_get_contents($schemaPath);
            $pdo->exec($schemaSql);
            $logs[] = "Struktur 20+ tabel relasional (schema.sql) berhasil dibuat.";
        }

        // Run Seed
        $seedPath = __DIR__ . '/database/seed.sql';
        if (file_exists($seedPath)) {
            $seedSql = file_get_contents($seedPath);
            $pdo->exec($seedSql);
            $logs[] = "Data awal produk aksesoris, kategori, kupon, dan admin (seed.sql) berhasil diimpor.";
        }

        $success = true;
        $message = "Instalasi dan migrasi database nnasa_ca_db berhasil 100%!";

    } catch (Exception $e) {
        $message = "Kesalahan instalasi: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalasi Database nnasa_ca</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body {
      background-color: #faf7f5;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .install-box {
      background: #ffffff;
      border: 1px solid #ebdcd7;
      border-radius: 20px;
      padding: 2.5rem;
      max-width: 600px;
      width: 100%;
      box-shadow: 0 10px 30px rgba(183, 110, 121, 0.1);
    }
    .btn-install {
      background: linear-gradient(135deg, #e8b4b8 0%, #c27d86 50%, #b76e79 100%);
      color: #fff;
      font-weight: 600;
      border: none;
      border-radius: 50px;
      padding: 0.75rem 2rem;
    }
    .btn-install:hover {
      opacity: 0.92;
      color: #fff;
    }
  </style>
</head>
<body>

  <div class="install-box">
    <div class="text-center mb-4">
      <h2 class="fw-bold" style="color: #c27d86;">nnasa_ca</h2>
      <p class="text-muted small">Instalasi Otomatis Database &amp; Data Awal Marketplace</p>
    </div>

    <?php if ($message): ?>
      <div class="alert <?= $success ? 'alert-success' : 'alert-danger' ?> rounded-3">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($logs)): ?>
      <div class="p-3 bg-light rounded-3 small font-monospace mb-4 border text-secondary">
        <?php foreach ($logs as $log): ?>
          <div><i class="bi bi-check2 text-success me-1"></i> <?= htmlspecialchars($log) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="text-center">
        <p class="text-muted small mb-4">Database telah siap. Silakan pilih halaman yang ingin Anda buka:</p>
        <div class="d-flex justify-content-center gap-2">
          <a href="<?= BASE_URL ?>" class="btn btn-install px-4">Buka Website Toko</a>
          <a href="<?= ADMIN_URL ?>login.php" class="btn btn-outline-secondary rounded-pill px-4">Buka Panel Admin</a>
        </div>
      </div>
    <?php else: ?>
      <form action="<?= BASE_URL ?>install.php" method="POST">
        <p class="small text-muted mb-3">
          Skrip ini akan secara otomatis membuat database <code><?= DB_NAME ?></code>, 20 tabel relasional, serta mengisi data awal aksesoris (jepit rambut, kalung, cincin, scrunchie, bundling gift), kategori, kupon, dan akun demo.
        </p>

        <div class="p-3 bg-light rounded-3 small mb-4 border">
          <div><strong>Host:</strong> <?= DB_HOST ?>:<?= DB_PORT ?></div>
          <div><strong>Database:</strong> <?= DB_NAME ?></div>
          <div><strong>User:</strong> <?= DB_USER ?></div>
        </div>

        <button type="submit" name="run_install" value="1" class="btn btn-install w-100 py-3">
          <i class="bi bi-database-fill-gear me-1"></i> Jalankan Instalasi Database Sekarang
        </button>
      </form>
    <?php endif; ?>
  </div>

</body>
</html>
