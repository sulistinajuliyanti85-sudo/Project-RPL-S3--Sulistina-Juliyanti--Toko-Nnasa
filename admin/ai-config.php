<?php
/**
 * Admin: Konfigurasi AI & Pengaturan Dasar Chatbot
 * Store: nnasa_ca Accessories Marketplace
 */

// 1. Inisialisasi Dependensi & Autentikasi (Backend / Controller Layer)
require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

require_admin();
$adminUser = current_user();
$db = getDB();

// 2. Handle Form Submission (POST) & Redirect HTTP - SEBELUM OUTPUT HTML DIBUAT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_chatbot_settings') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Token CSRF tidak valid.');
    } else {
        $allowedKeys = [
            'ai_status', 'ai_provider', 'ai_model',
            'ai_api_key_main', 'ai_api_key_backup_1', 'ai_api_key_backup_2',
            'ai_temperature', 'ai_max_tokens', 'ai_system_instruction'
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

        log_activity($adminUser['id'], 'update_ai_settings', 'settings', null, "Memperbarui konfigurasi chatbot AI");
        set_flash('success', 'Pengaturan Chatbot AI berhasil disimpan! 🤖✨');
        header('Location: ' . ADMIN_URL . 'ai-config.php');
        exit;
    }
}

// 3. Panggil Template Header HTML (View Layer) setelah urusan HTTP Header selesai
$adminPageTitle = 'Konfigurasi AI & Chatbot';
require_once dirname(__DIR__) . '/templates/admin_header.php';

// Retrieve Current Settings
$aiStatus = get_setting('ai_status', 'active');
$aiProvider = get_setting('ai_provider', 'Google Gemini (Generative AI)');
$aiModel = get_setting('ai_model', 'gemini-flash-lite-latest');
$apiKeyMain = get_setting('ai_api_key_main', 'AIzaSySampleKeyMainUtamaX6Q');
$apiKeyBackup1 = get_setting('ai_api_key_backup_1', 'AIzaSySampleBackup1Key');
$apiKeyBackup2 = get_setting('ai_api_key_backup_2', 'AIzaSySampleBackup2Key');
$aiTemperature = get_setting('ai_temperature', '0.2');
$aiMaxTokens = get_setting('ai_max_tokens', '2000');
$aiSystemInstruction = get_setting('ai_system_instruction', '');

// Helper masking untuk tampilan banner
$maskedKeyDisplay = 'AQ.Ab8••••••••X6Q';
if (!empty($apiKeyMain) && strlen($apiKeyMain) > 8) {
    $maskedKeyDisplay = substr($apiKeyMain, 0, 6) . '••••••••' . substr($apiKeyMain, -3);
}
?>

<style>
  .font-mono {
    font-family: 'JetBrains Mono', monospace;
  }

  /* 1. Banner Status AI - Dusty Rose Pink Gradient (Identik dengan tombol Tambah Produk Baru) */
  .ai-status-banner {
    background: linear-gradient(135deg, #d88a93 0%, #be6e7a 50%, #a85864 100%);
    border-radius: 50px;
    padding: 0.95rem 2rem;
    box-shadow: 0 4px 18px rgba(190, 110, 122, 0.35);
  }

  .ai-status-banner .banner-text {
    font-size: 0.9rem;
    color: #ffffff;
  }

  /* 2. Kontainer Utama Card */
  .main-settings-card {
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 6px 24px rgba(0, 0, 0, 0.04);
    padding: 2rem 2.25rem;
  }

  /* Form Controls */
  .form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 0.35rem;
  }

  .form-control,
  .form-select {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.88rem;
    padding: 0.6rem 0.85rem;
    color: #0f172a;
  }

  .form-control:focus,
  .form-select:focus {
    border-color: #c27d86;
    box-shadow: 0 0 0 3px rgba(194, 125, 134, 0.2);
  }

  .form-control:disabled,
  .form-control[readonly] {
    background-color: #f1f5f9;
    color: #475569;
    cursor: not-allowed;
    border-color: #e2e8f0;
  }

  .form-text {
    font-size: 0.77rem;
    color: #64748b;
    margin-top: 0.4rem;
    line-height: 1.45;
  }

  /* Input Group Password */
  .input-group-password .form-control {
    border-right: none;
    letter-spacing: 2px;
    font-family: 'JetBrains Mono', monospace;
  }

  .input-group-password .btn-eye {
    border: 1px solid #cbd5e1;
    border-left: none;
    background-color: #ffffff;
    color: #94a3b8;
    padding: 0 0.85rem;
    transition: color 0.2s ease;
  }

  .input-group-password .btn-eye:hover {
    color: #c27d86;
  }

  .input-group-password .form-control:focus + .btn-eye {
    border-color: #c27d86;
  }

  /* Custom Range Slider Thumb Color */
  .form-range::-webkit-slider-thumb {
    background-color: #c27d86;
  }
  .form-range::-moz-range-thumb {
    background-color: #c27d86;
  }

  /* Brand Button Rose Gold */
  .btn-nnasa {
    background: linear-gradient(135deg, #d8969d 0%, #c27d86 50%, #b06972 100%);
    color: #ffffff;
    border: none;
    box-shadow: 0 4px 14px rgba(194, 125, 134, 0.35);
    transition: all 0.25s ease;
  }

  .btn-nnasa:hover {
    background: linear-gradient(135deg, #df9b9e 0%, #b36e77 100%);
    color: #ffffff;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(194, 125, 134, 0.45);
  }

  /* Badge Tersimpan */
  .badge-saved {
    background-color: #ecfdf5;
    color: #15803d;
    border: 1px solid #bbf7d0;
    font-size: 0.72rem;
    font-weight: 600;
    padding: 0.2rem 0.55rem;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
  }

  /* Tombol Pengaturan Lanjutan */
  .btn-advanced {
    border: 1px solid #cbd5e1;
    color: #334155;
    background-color: #ffffff;
    font-size: 0.85rem;
    font-weight: 600;
    padding: 0.55rem 1.15rem;
    border-radius: 8px;
    transition: all 0.2s ease;
    white-space: nowrap;
  }

  .btn-advanced:hover {
    background-color: #f8fafc;
    border-color: #94a3b8;
    color: #0f172a;
  }
</style>

<div class="container-fluid px-3 py-3">

  <!-- ========================================================================
       1. BANNER STATUS AI (ATAS)
       ======================================================================== -->
  <div class="ai-status-banner mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 text-center text-md-start">
      <!-- Bagian 1: Provider & Model -->
      <div class="banner-text fw-bold">
        GOOGLE_GEMINI (<?= htmlspecialchars($aiModel) ?>)
      </div>

      <!-- Bagian 2: Masked API Key -->
      <div class="banner-text font-mono">
        <?= htmlspecialchars($maskedKeyDisplay) ?>
      </div>

      <!-- Bagian 3: Token & Temperature -->
      <div class="banner-text">
        <?= htmlspecialchars($aiMaxTokens) ?> tokens | Temp: <?= htmlspecialchars($aiTemperature) ?>
      </div>

      <!-- Bagian 4: Date Timestamp -->
      <div class="banner-text text-white-50">
        <?= date('d/m/y, H.i') ?>
      </div>
    </div>
  </div>

  <!-- ========================================================================
       2. KONTAINER UTAMA (CARD PUTIH)
       ======================================================================== -->
  <div class="card main-settings-card">
    <form action="" method="POST" id="formChatbotBasicSettings">
      <input type="hidden" name="action" value="save_chatbot_settings">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <!-- ====================================================================
           3. BAGIAN: 1. PENGATURAN DASAR CHATBOT
           ==================================================================== -->
      <h4 class="fw-bold mb-1 text-dark">1. Pengaturan Dasar Chatbot</h4>
      <p class="text-muted small mb-4">Kredensial API Google Gemini dan pemilihan model utama yang digunakan oleh sistem.</p>

      <!-- Baris Pertama: Status, Provider, dan Model (Grid 3 Kolom) -->
      <div class="row g-4 mb-4">
        
        <!-- Kolom 1: Status Layanan Chatbot -->
        <div class="col-12 col-md-4">
          <label class="form-label" for="chatbotStatus">Status Layanan Chatbot <span class="text-danger">*</span></label>
          <select class="form-select" id="chatbotStatus" name="ai_status">
            <option value="active" <?= $aiStatus === 'active' ? 'selected' : '' ?>>Aktif (Melayani Pengguna Dashboard)</option>
            <option value="inactive" <?= $aiStatus === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
          </select>
          <div class="form-text">
            Jika dinonaktifkan, chatbot akan memberikan respons penonaktifan yang sopan dan konsisten.
          </div>
        </div>

        <!-- Kolom 2: Provider AI -->
        <div class="col-12 col-md-4">
          <label class="form-label" for="aiProvider">Provider AI <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="aiProvider" name="ai_provider" value="Google Gemini (Generative AI)" readonly>
          <div class="form-text">
            Provider resmi Google Generative AI (@ai-sdk/google).
          </div>
        </div>

        <!-- Kolom 3: Model AI Gemini -->
        <div class="col-12 col-md-4">
          <label class="form-label" for="aiModel">Model AI Gemini <span class="text-danger">*</span></label>
          <select class="form-select" id="aiModel" name="ai_model">
            <option value="gemini-flash-lite-latest" <?= $aiModel === 'gemini-flash-lite-latest' ? 'selected' : '' ?>>gemini-flash-lite-latest (Super Ringan &amp; Hemat)</option>
            <option value="gemini-1.5-flash" <?= $aiModel === 'gemini-1.5-flash' ? 'selected' : '' ?>>gemini-1.5-flash (Seimbang &amp; Cepat)</option>
            <option value="gemini-1.5-pro" <?= $aiModel === 'gemini-1.5-pro' ? 'selected' : '' ?>>gemini-1.5-pro (Penalaran Kompleks)</option>
          </select>
          <div class="form-text">
            Pilih model yang didukung oleh Google AI Studio API key Anda.
          </div>
        </div>

      </div>

      <!-- Baris Kedua: Deretan API Key (Grid 3 Kolom) -->
      <div class="row g-4">

        <!-- Kolom 1: API Key Utama -->
        <div class="col-12 col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0" for="apiKeyMain">API Key Google Gemini (Utama) <span class="text-danger">*</span></label>
            <span class="badge-saved">
              <i class="bi bi-check-lg"></i> Tersimpan
            </span>
          </div>

          <div class="input-group input-group-password">
            <input type="password" class="form-control" id="apiKeyMain" name="ai_api_key_main" value="<?= htmlspecialchars($apiKeyMain) ?>" placeholder="••••••••••••••••••••••••">
            <button type="button" class="btn btn-eye" onclick="togglePasswordVisibility('apiKeyMain', this)" title="Lihat/Sembunyikan Kunci">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="form-text">
            Dienkripsi di server dengan AES-256-GCM. Tidak akan pernah dikirim plaintext ke browser.
          </div>
        </div>

        <!-- Kolom 2: API Key Cadangan 1 -->
        <div class="col-12 col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0" for="apiKeyBackup1">API Key Cadangan 1 (Opsional)</label>
            <span class="badge-saved">
              <i class="bi bi-check-lg"></i> Tersimpan
            </span>
          </div>

          <div class="input-group input-group-password">
            <input type="password" class="form-control" id="apiKeyBackup1" name="ai_api_key_backup_1" value="<?= htmlspecialchars($apiKeyBackup1) ?>" placeholder="••••••••••••••••••••••••">
            <button type="button" class="btn btn-eye" onclick="togglePasswordVisibility('apiKeyBackup1', this)" title="Lihat/Sembunyikan Kunci">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="form-text">
            Digunakan otomatis sebagai cadangan jika API Key Utama gagal atau kuota habis (503/429).
          </div>
        </div>

        <!-- Kolom 3: API Key Cadangan 2 -->
        <div class="col-12 col-md-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label mb-0" for="apiKeyBackup2">API Key Cadangan 2 (Opsional)</label>
            <span class="badge-saved">
              <i class="bi bi-check-lg"></i> Tersimpan
            </span>
          </div>

          <div class="input-group input-group-password">
            <input type="password" class="form-control" id="apiKeyBackup2" name="ai_api_key_backup_2" value="<?= htmlspecialchars($apiKeyBackup2) ?>" placeholder="••••••••••••••••••••••••">
            <button type="button" class="btn btn-eye" onclick="togglePasswordVisibility('apiKeyBackup2', this)" title="Lihat/Sembunyikan Kunci">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="form-text">
            Lapisan cadangan kedua jika API Key Utama dan Cadangan 1 keduanya gagal.
          </div>
        </div>

      </div>

      <!-- ====================================================================
           4. GARIS PEMISAH (DIVIDER)
           ==================================================================== -->
      <hr class="my-4 text-secondary opacity-25">

      <!-- ====================================================================
           5. BAGIAN: 2. PENGATURAN LANJUTAN & PERILAKU AI
           ==================================================================== -->
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div>
          <h5 class="fw-bold mb-1 text-dark">2. Pengaturan Lanjutan &amp; Perilaku AI</h5>
          <div class="text-muted small">
            Kustomisasi temperature, batas token, system instruction tambahan, dan template pesan penolakan/fallback.
          </div>
        </div>

        <!-- Tombol Buka Pengaturan Lanjutan -->
        <button type="button" class="btn btn-advanced" data-bs-toggle="collapse" data-bs-target="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
          <span id="btnAdvancedLabel">&#9660; Buka Pengaturan Lanjutan</span>
        </button>
      </div>

      <!-- Konten Collapse Pengaturan Lanjutan -->
      <div class="collapse mt-4" id="collapseAdvanced">
        <div class="card card-body bg-light border-0 rounded-3">
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label" for="temperature">Creativity (Temperature): <strong id="tempDisplay"><?= htmlspecialchars($aiTemperature) ?></strong></label>
              <input type="range" class="form-range" id="temperature" name="ai_temperature" min="0" max="1" step="0.1" value="<?= htmlspecialchars($aiTemperature) ?>" oninput="document.getElementById('tempDisplay').innerText = this.value">
              <div class="form-text">Nilai 0.2 membuat jawaban AI lebih terfokus, faktual, dan presisi.</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label" for="maxTokens">Max Output Tokens</label>
              <input type="number" class="form-control" id="maxTokens" name="ai_max_tokens" value="<?= htmlspecialchars($aiMaxTokens) ?>">
              <div class="form-text">Batas maksimum panjang karakter output dari AI.</div>
            </div>
            <div class="col-12">
              <label class="form-label" for="systemInstruction">Instruksi Sistem Tambahan (System Instruction)</label>
              <textarea class="form-control" id="systemInstruction" name="ai_system_instruction" rows="3" placeholder="Masukkan instruksi khusus atau nada bahasa chatbot di sini..."><?= htmlspecialchars($aiSystemInstruction) ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Tombol Simpan Form -->
      <div class="mt-4 pt-3 border-top d-flex justify-content-end">
        <button type="submit" class="btn btn-nnasa px-4 py-2 rounded-pill fw-semibold shadow-sm">
          <i class="bi bi-check-circle me-1"></i> Simpan Pengaturan Chatbot
        </button>
      </div>

    </form>
  </div>

</div>

<!-- Script Interaktivitas -->
<script>
  // 1. Fungsi Toggle Password Visibility
  function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');

    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('bi-eye');
      icon.classList.add('bi-eye-slash');
      btn.setAttribute('title', 'Sembunyikan Kunci');
    } else {
      input.type = 'password';
      icon.classList.remove('bi-eye-slash');
      icon.classList.add('bi-eye');
      btn.setAttribute('title', 'Lihat Kunci');
    }
  }

  // 2. Listener Toggle Panah Pengaturan Lanjutan
  document.addEventListener('DOMContentLoaded', function () {
    const collapseEl = document.getElementById('collapseAdvanced');
    const btnLabel = document.getElementById('btnAdvancedLabel');

    if (collapseEl && btnLabel) {
      collapseEl.addEventListener('show.bs.collapse', function () {
        btnLabel.innerHTML = '&#9650; Tutup Pengaturan Lanjutan';
      });

      collapseEl.addEventListener('hide.bs.collapse', function () {
        btnLabel.innerHTML = '&#9660; Buka Pengaturan Lanjutan';
      });
    }
  });
</script>

<?php
require_once dirname(__DIR__) . '/templates/admin_footer.php';

