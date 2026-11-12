<?php
/**
 * Contact Us Page (Hubungi Kami)
 * Store: nnasa_ca Accessories Marketplace
 */

$pageTitle = 'Hubungi Kami';
require_once __DIR__ . '/templates/header.php';

$whatsapp = get_setting('contact_whatsapp', '081298765432');
$email = get_setting('contact_email', 'support@nnasaca.com');
$address = get_setting('contact_address', 'Grand Boutique Center, Jakarta Utara');

$sentSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    if (csrf_verify($_POST['csrf_token'] ?? '')) {
        $name = sanitize($_POST['name'] ?? '');
        $emailUser = sanitize($_POST['email'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        
        // Log message in activity logs
        log_activity(null, 'contact_message', 'contact', null, "Pesan dari {$name} ({$emailUser}): {$subject}");
        set_flash('success', 'Pesan kamu berhasil dikirimkan! Tim nnasa_ca akan merespons secepatnya. 💌');
        header('Location: ' . BASE_URL . 'contact.php');
        exit;
    }
}
?>

<div class="container my-5">
  <div class="text-center mb-5" style="max-width: 600px; margin: 0 auto;">
    <span class="badge bg-light text-primary-pink border px-3 py-2 rounded-pill fw-bold small mb-2">
      LAYANAN PELANGGAN
    </span>
    <h1 class="h3 fw-bold text-dark brand-font">Ada yang Bisa Kami Bantu?</h1>
    <p class="text-muted small">Punya pertanyaan seputar ukuran, bahan aksesoris, pesanan hampers, atau kolaborasi? Kami siap menyapa kamu!</p>
  </div>

  <div class="row g-4 justify-content-center">
    <!-- Contact Info Cards -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 p-4 mb-3" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle p-3 text-white" style="background: #25d366;">
            <i class="bi bi-whatsapp fs-4"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1 text-dark">WhatsApp Chat</h6>
            <small class="text-muted d-block mb-1">Respons cepat: 09:00 - 21:00 WIB</small>
            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $whatsapp) ?>" target="_blank" class="text-success fw-bold text-decoration-none small">
              Chat Sekarang <i class="bi bi-arrow-right"></i>
            </a>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4 p-4 mb-3" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle p-3 text-white" style="background: var(--primary-pink);">
            <i class="bi bi-envelope-fill fs-4"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1 text-dark">Email Support</h6>
            <small class="text-muted d-block mb-1">Kirim pertanyaan detail</small>
            <a href="mailto:<?= htmlspecialchars($email) ?>" class="text-primary-pink fw-bold text-decoration-none small">
              <?= htmlspecialchars($email) ?>
            </a>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4 p-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle p-3 text-white bg-dark">
            <i class="bi bi-geo-alt-fill fs-4"></i>
          </div>
          <div>
            <h6 class="fw-bold mb-1 text-dark">Alamat Galeri & Kantor</h6>
            <small class="text-muted d-block"><?= htmlspecialchars($address) ?></small>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact Form -->
    <div class="col-lg-6">
      <div class="card border-0 shadow-sm rounded-4 p-4 p-md-5" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <h5 class="fw-bold text-dark fs-6 mb-3">Kirim Pesan Langsung</h5>
        
        <form action="<?= BASE_URL ?>contact.php" method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="send_message">

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Nama Kamu</label>
            <input type="text" name="name" class="form-control rounded-3" placeholder="Masukkan nama..." required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Email</label>
            <input type="email" name="email" class="form-control rounded-3" placeholder="nama@email.com" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-bold text-dark">Subjek Pertanyaan</label>
            <input type="text" name="subject" class="form-control rounded-3" placeholder="Pertanyaan produk, pesanan, dsb." required>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-bold text-dark">Pesan</label>
            <textarea name="message" rows="4" class="form-control rounded-3" placeholder="Tuliskan pesanmu..." required></textarea>
          </div>

          <button type="submit" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill">
            Kirimkan Pesan <i class="bi bi-send ms-1"></i>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
