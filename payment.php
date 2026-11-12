<?php
/**
 * Payment Page & DEMO Simulator
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

$orderId = (int)($_GET['order_id'] ?? 0);

// Fetch order details
$stmt = $db->prepare("
    SELECT o.*, p.payment_method, p.transaction_reference, p.amount as payment_amount, p.status as payment_ref_status
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE o.id = ? AND (o.user_id = ? OR ? = 'admin')
    LIMIT 1
");
$stmt->execute([$orderId, $userId, $user['role']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('danger', 'Pesanan tidak ditemukan atau akses ditolak.');
    header('Location: ' . BASE_URL . 'orders.php');
    exit;
}

$pageTitle = 'Pembayaran Pesanan ' . $order['order_number'];
require_once __DIR__ . '/templates/header.php';

// Generate realistic VA numbers or QRIS payload
$method = $order['payment_method'] ?? 'qris';
$vaNumber = match($method) {
    'va_bca' => '88308' . str_pad($order['id'], 8, '0', STR_PAD_LEFT),
    'va_mandiri' => '89201' . str_pad($order['id'], 8, '0', STR_PAD_LEFT),
    'gopay' => '0813-8765-4321',
    default => 'QRIS-' . $order['order_number']
};
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>orders.php" class="text-decoration-none text-muted">Pesanan</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Pembayaran</li>
    </ol>
  </nav>

  <div class="row justify-content-center">
    <div class="col-lg-8">
      
      <!-- Payment Status Alert Banner -->
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
        <div class="p-4 text-center <?= $order['payment_status'] === 'paid' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-dark' ?>">
          <div class="fs-1 mb-2">
            <?php if ($order['payment_status'] === 'paid'): ?>
              <i class="bi bi-check-circle-fill text-success"></i>
            <?php elseif ($order['payment_status'] === 'failed'): ?>
              <i class="bi bi-x-circle-fill text-danger"></i>
            <?php else: ?>
              <i class="bi bi-hourglass-split text-warning"></i>
            <?php endif; ?>
          </div>
          <h4 class="fw-bold mb-1">
            <?php if ($order['payment_status'] === 'paid'): ?>
              Pembayaran Berhasil Diterima!
            <?php elseif ($order['payment_status'] === 'failed'): ?>
              Pembayaran Gagal / Dibatalkan
            <?php else: ?>
              Menunggu Pembayaran
            <?php endif; ?>
          </h4>
          <p class="small mb-0 opacity-75">
            Nomor Pesanan: <strong><?= htmlspecialchars($order['order_number']) ?></strong> &bull; Total: <strong><?= format_rupiah($order['total_amount']) ?></strong>
          </p>
        </div>

        <div class="p-4">
          <!-- DEMO NOTICE BADGE -->
          <div class="alert alert-info border-info-subtle d-flex align-items-center gap-3 p-3 rounded-3 mb-4">
            <i class="bi bi-info-circle-fill fs-3 text-info"></i>
            <div class="small">
              <strong>Simulasi Pembayaran (Lingkungan DEMO):</strong><br>
              Ini adalah gateway pembayaran simulasi untuk tahap uji coba XAMPP. Silakan gunakan tombol simulasi di bawah untuk menguji perubahan status secara real-time.
            </div>
          </div>

          <?php if ($order['payment_status'] === 'pending'): ?>
            <!-- Payment Channel Details -->
            <div class="card border rounded-3 p-3 mb-4 bg-light">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted small">Metode Pembayaran</span>
                <span class="badge bg-dark"><?= strtoupper(str_replace('_', ' ', $method)) ?></span>
              </div>

              <?php if ($method === 'qris'): ?>
                <div class="text-center py-3">
                  <div class="p-3 bg-white d-inline-block rounded-3 border shadow-sm mb-2">
                    <!-- SVG QR Code Representation -->
                    <svg width="180" height="180" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                      <rect width="100" height="100" fill="#ffffff"/>
                      <!-- Outer corner boxes -->
                      <rect x="5" y="5" width="28" height="28" fill="#22181b"/>
                      <rect x="8" y="8" width="22" height="22" fill="#ffffff"/>
                      <rect x="12" y="12" width="14" height="14" fill="#c27d86"/>
                      
                      <rect x="67" y="5" width="28" height="28" fill="#22181b"/>
                      <rect x="70" y="8" width="22" height="22" fill="#ffffff"/>
                      <rect x="74" y="12" width="14" height="14" fill="#c27d86"/>

                      <rect x="5" y="67" width="28" height="28" fill="#22181b"/>
                      <rect x="8" y="70" width="22" height="22" fill="#ffffff"/>
                      <rect x="12" y="74" width="14" height="14" fill="#c27d86"/>

                      <!-- Pattern dots -->
                      <rect x="40" y="10" width="8" height="8" fill="#22181b"/>
                      <rect x="52" y="10" width="8" height="8" fill="#22181b"/>
                      <rect x="40" y="24" width="16" height="8" fill="#22181b"/>
                      <rect x="10" y="40" width="8" height="16" fill="#22181b"/>
                      <rect x="25" y="40" width="12" height="8" fill="#22181b"/>
                      <rect x="45" y="45" width="10" height="10" fill="#c27d86"/>
                      <rect x="65" y="40" width="12" height="8" fill="#22181b"/>
                      <rect x="82" y="40" width="8" height="16" fill="#22181b"/>
                      <rect x="40" y="65" width="14" height="14" fill="#22181b"/>
                      <rect x="60" y="65" width="8" height="14" fill="#22181b"/>
                      <rect x="75" y="65" width="15" height="8" fill="#22181b"/>
                      <rect x="75" y="80" width="8" height="10" fill="#c27d86"/>
                    </svg>
                  </div>
                  <div class="fw-bold text-dark small">Pindai QRIS nnasa_ca Official</div>
                  <div class="text-muted" style="font-size: 0.75rem;">Mendukung BCA, Mandiri, GoPay, OVO, ShopeePay, DANA</div>
                </div>
              <?php else: ?>
                <div class="mb-3">
                  <label class="text-muted small d-block mb-1">Nomor Virtual Account / Akun:</label>
                  <div class="input-group">
                    <input type="text" class="form-control fw-bold font-monospace fs-5 text-primary-pink" value="<?= $vaNumber ?>" id="vaNumberInput" readonly>
                    <button class="btn btn-outline-secondary" type="button" onclick="copyVA()">
                      <i class="bi bi-clipboard me-1"></i> Salin
                    </button>
                  </div>
                </div>
              <?php endif; ?>

              <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <span class="small text-muted">Jumlah yang Harus Dibayar:</span>
                <span class="h5 fw-bold text-primary-pink mb-0"><?= format_rupiah($order['total_amount']) ?></span>
              </div>
            </div>

            <!-- SIMULATION BUTTONS -->
            <div class="p-4 border rounded-4 bg-white shadow-sm mb-4">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-controller text-primary-pink me-2"></i> Simulator Pembayaran Sandbox</h6>
              <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-success flex-grow-1 py-2 fw-semibold" onclick="simulatePayment(<?= $order['id'] ?>, 'pay_success')">
                  <i class="bi bi-check-circle-fill me-1"></i> Simulasikan Pembayaran Berhasil (DEMO)
                </button>
                <button type="button" class="btn btn-outline-danger py-2 fw-semibold" onclick="simulatePayment(<?= $order['id'] ?>, 'pay_fail')">
                  <i class="bi bi-x-circle me-1"></i> Simulasikan Gagal
                </button>
              </div>
            </div>
          <?php else: ?>
            <!-- Order already paid or handled -->
            <div class="text-center py-3">
              <a href="<?= BASE_URL ?>order-detail.php?order_id=<?= $order['id'] ?>" class="btn btn-nnasa px-4 py-2">
                <i class="bi bi-eye me-1"></i> Lihat Rincian Pesanan
              </a>
              <a href="<?= BASE_URL ?>orders.php" class="btn btn-outline-secondary px-4 py-2 ms-2">
                Daftar Pesanan Saya
              </a>
            </div>
          <?php endif; ?>

          <!-- Instructions Accordion -->
          <div class="accordion accordion-flush mt-4 border rounded-3 overflow-hidden" id="accordionPayment">
            <div class="accordion-item">
              <h2 class="accordion-header">
                <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne">
                  Petunjuk Pembayaran ATM & Mobile Banking
                </button>
              </h2>
              <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionPayment">
                <div class="accordion-body small text-muted">
                  <ol class="ps-3 mb-0">
                    <li>Buka aplikasi m-Banking atau kunjungi mesin ATM terdekat.</li>
                    <li>Pilih menu <strong>Transfer / Pembayaran &gt; Virtual Account</strong>.</li>
                    <li>Masukkan nomor Virtual Account di atas.</li>
                    <li>Pastikan nama merchant tertera <strong>nnasa_ca Official</strong> dan jumlah nominal sesuai persis.</li>
                    <li>Konfirmasi transaksi dan simpan bukti pembayaran.</li>
                  </ol>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

    </div>
  </div>
</div>

<script>
  function copyVA() {
    const input = document.getElementById('vaNumberInput');
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    showToast('Nomor Virtual Account berhasil disalin!', 'success');
  }

  function simulatePayment(orderId, action) {
    if (!confirm('Jalankan simulasi pembayaran DEMO ini sekarang?')) return;

    fetch(window.BASE_URL + 'api/payment-sim.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: orderId,
        sim_action: action
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        if (typeof window.showToast === 'function') {
          window.showToast(data.message, 'success');
        }

        // Trigger WhatsApp order verified notification banner
        if (data.wa_notification && typeof window.showIncomingOtpNotification === 'function') {
          window.showIncomingOtpNotification({
            channel: 'whatsapp',
            phone: data.wa_notification.destination_masked || data.wa_notification.phone || '',
            sender: 'nnasa_ca WhatsApp Official',
            message: data.wa_notification.simulated_message || data.wa_notification.message || 'Pembayaran berhasil diverifikasi!'
          });
          setTimeout(() => {
            window.location.href = data.redirect || window.location.href;
          }, 3500);
        } else {
          setTimeout(() => {
            window.location.href = data.redirect || window.location.href;
          }, 1000);
        }
      } else {
        if (typeof window.showToast === 'function') {
          window.showToast(data.message || 'Gagal memproses simulasi.', 'danger');
        }
      }
    })
    .catch(err => {
      console.error(err);
      showToast('Terjadi kesalahan jaringan.', 'danger');
    });
  }
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
