<?php
/**
 * Admin Store Settings (Pengaturan Toko)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Pengaturan Toko';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $allowedKeys = [
            'site_name', 'site_tagline', 'site_description',
            'contact_whatsapp', 'contact_email', 'contact_address',
            'bank_bca_acc', 'bank_bca_name', 'bank_mandiri_acc', 'bank_mandiri_name',
            'qris_merchant_name', 'shipping_cost_default', 'low_stock_threshold',
            'ai_assistant_enabled', 'ai_provider', 'ai_api_key', 'ai_welcome_message'
        ];

        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        foreach ($allowedKeys as $k) {
            if (isset($_POST[$k])) {
                $val = trim($_POST[$k]);
                $stmt->execute([$k, $val]);
            }
        }

        log_activity($adminUser['id'], 'update_settings', 'settings', null, "Updated store settings");
        set_flash('success', 'Pengaturan toko berhasil disimpan! ⚙️');
        header('Location: ' . ADMIN_URL . 'settings.php');
        exit;
    }
}

// Fetch all settings
$settings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-bold text-dark mb-1">Pengaturan Toko nnasa_ca</h4>
        <small class="text-muted">Konfigurasi informasi publik, kontak WhatsApp, nomor rekening, dan tarif dasar</small>
      </div>
    </div>

    <form action="<?= ADMIN_URL ?>settings.php" method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_settings">

      <!-- General Info -->
      <div class="admin-card mb-4">
        <div class="admin-card-header">
          <h5 class="admin-card-title"><i class="bi bi-shop text-primary-pink me-2"></i> Identitas Toko</h5>
        </div>
        <div class="admin-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Nama Toko Online</label>
              <input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($settings['site_name'] ?? 'nnasa_ca') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Tagline Merek</label>
              <input type="text" name="site_tagline" class="form-control" value="<?= htmlspecialchars($settings['site_tagline'] ?? 'Toko Aksesoris Wanita Elegan & Kekinian') ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Deskripsi Toko (SEO &amp; Footer)</label>
              <textarea name="site_description" rows="3" class="form-control"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact Info -->
      <div class="admin-card mb-4">
        <div class="admin-card-header">
          <h5 class="admin-card-title"><i class="bi bi-telephone text-primary-pink me-2"></i> Kontak &amp; Alamat Toko</h5>
        </div>
        <div class="admin-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Nomor WhatsApp CS</label>
              <input type="text" name="contact_whatsapp" class="form-control" value="<?= htmlspecialchars($settings['contact_whatsapp'] ?? '081298765432') ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Email Layanan Pelanggan</label>
              <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($settings['contact_email'] ?? 'support@nnasaca.com') ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Alamat Toko / Warehouse</label>
              <textarea name="contact_address" rows="2" class="form-control"><?= htmlspecialchars($settings['contact_address'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment & Banking Configuration -->
      <div class="admin-card mb-4">
        <div class="admin-card-header">
          <h5 class="admin-card-title"><i class="bi bi-bank text-primary-pink me-2"></i> Rekening Bank &amp; Merchant</h5>
        </div>
        <div class="admin-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Nomor Rekening BCA</label>
              <input type="text" name="bank_bca_acc" class="form-control" value="<?= htmlspecialchars($settings['bank_bca_acc'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Nama Pemilik Rekening BCA</label>
              <input type="text" name="bank_bca_name" class="form-control" value="<?= htmlspecialchars($settings['bank_bca_name'] ?? 'PT NNasa Cantik Aksesoris') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Nomor Rekening Mandiri</label>
              <input type="text" name="bank_mandiri_acc" class="form-control" value="<?= htmlspecialchars($settings['bank_mandiri_acc'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Nama Pemilik Rekening Mandiri</label>
              <input type="text" name="bank_mandiri_name" class="form-control" value="<?= htmlspecialchars($settings['bank_mandiri_name'] ?? 'PT NNasa Cantik Aksesoris') ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">Nama Merchant QRIS</label>
              <input type="text" name="qris_merchant_name" class="form-control" value="<?= htmlspecialchars($settings['qris_merchant_name'] ?? 'nnasa_ca Official') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- Shipping & Inventory Thresholds -->
      <div class="admin-card mb-4">
        <div class="admin-card-header">
          <h5 class="admin-card-title"><i class="bi bi-sliders text-primary-pink me-2"></i> Parameter Logistik &amp; Stok</h5>
        </div>
        <div class="admin-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Ongkos Kirim Default (Rp)</label>
              <input type="number" step="500" name="shipping_cost_default" class="form-control" value="<?= htmlspecialchars($settings['shipping_cost_default'] ?? '12000') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">Batas Peringatan Stok Menipis (Unit)</label>
              <input type="number" name="low_stock_threshold" class="form-control" value="<?= htmlspecialchars($settings['low_stock_threshold'] ?? '5') ?>">
            </div>
          </div>
        </div>
      </div>

      <!-- AI Assistant Configuration Card -->
      <div class="admin-card mb-4 border-primary-subtle">
        <div class="admin-card-header bg-primary-subtle d-flex justify-content-between align-items-center">
          <h5 class="admin-card-title mb-0"><i class="bi bi-stars text-primary-pink me-2"></i> Konfigurasi AI Assistant (NNasa AI)</h5>
          <span class="badge bg-primary-pink text-white rounded-pill px-3 py-1">Grounded RAG Engine</span>
        </div>
        <div class="admin-card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-bold">Status AI Assistant di Website</label>
              <select name="ai_assistant_enabled" class="form-select">
                <option value="1" <?= ($settings['ai_assistant_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Aktif (Tampilkan Chatbot di Pojok Kanan Bawah)</option>
                <option value="0" <?= ($settings['ai_assistant_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Nonaktifkan Sementara</option>
              </select>
              <div class="form-text small">Tombol mengambang akan otomatis muncul di seluruh halaman belanja pelanggan.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold">Mesin / Provider AI</label>
              <select name="ai_provider" class="form-select">
                <option value="builtin" <?= ($settings['ai_provider'] ?? 'builtin') === 'builtin' ? 'selected' : '' ?>>Built-in Intelligent RAG Engine (100% Offline, Cepat &amp; Bebas Biaya)</option>
                <option value="gemini" <?= ($settings['ai_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google Gemini API (Generative AI Hybrid + Grounded Data)</option>
              </select>
              <div class="form-text small">Sistem otomatis menggunakan Built-in Engine jika API key kosong atau koneksi offline.</div>
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold">Google Gemini API Key (Opsional)</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-key-fill text-muted"></i></span>
                <input type="password" name="ai_api_key" class="form-control" placeholder="Contoh: AIzaSyD..." value="<?= htmlspecialchars($settings['ai_api_key'] ?? '') ?>" autocomplete="off">
              </div>
              <div class="form-text small text-muted">
                Dapatkan gratis di <a href="https://aistudio.google.com" target="_blank" class="text-primary-pink fw-bold">Google AI Studio</a>. Kosongkan untuk tetap memakai Built-in Engine lokal berkecepatan tinggi.
              </div>
            </div>

            <div class="col-12">
              <label class="form-label small fw-bold">Pesan Sapaan Pembuka (Welcome Greeting)</label>
              <textarea name="ai_welcome_message" rows="2" class="form-control"><?= htmlspecialchars($settings['ai_welcome_message'] ?? 'Hai! Selamat datang di nnasa_ca. Aku NNasa AI, asisten virtual resmi yang siap membantu kamu mencari aksesoris, mengecek harga dan ketersediaan produk, serta menjawab pertanyaan tentang toko kami. Ada yang bisa aku bantu?') ?></textarea>
              <div class="form-text small">Pesan awal yang otomatis tampil saat pelanggan membuka jendela chat.</div>
            </div>
          </div>
        </div>
      </div>

      <div class="text-end mb-5">
        <button type="submit" class="btn btn-nnasa rounded-pill px-5 py-3 fw-bold">
          <i class="bi bi-save me-1"></i> Simpan Seluruh Pengaturan
        </button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
