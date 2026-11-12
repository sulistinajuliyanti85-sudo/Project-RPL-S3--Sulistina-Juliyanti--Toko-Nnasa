<?php
/**
 * Admin: Konfigurasi Authentication OTP WhatsApp
 * Store: nnasa_ca Accessories Marketplace
 * Senior Backend & Cybersecurity Implementation
 */

$adminPageTitle = 'Konfigurasi OTP WhatsApp';
require_once dirname(__DIR__) . '/templates/admin_header.php';
require_once dirname(__DIR__) . '/app/Models/OTPModel.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/WhatsAppService.php';

$db = getDB();
$otpModel = new OTPModel($db);
$waService = new WhatsAppService($db);

$configInfo = $waService->getConfigInfo();
$settings = $otpModel->getAllSettings();
$csrfToken = csrf_token();

// Fetch WhatsApp metrics
$stmtDeliv = $db->query("
    SELECT 
        COUNT(*) AS total_wa_deliveries,
        SUM(CASE WHEN delivery_status IN ('sent', 'simulated') THEN 1 ELSE 0 END) AS total_wa_sent,
        SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) AS total_wa_failed,
        MAX(sent_at) AS last_wa_sent_at
    FROM otp_delivery_logs 
    WHERE provider_name LIKE 'wa_%'
");
$delivStats = $stmtDeliv->fetch(PDO::FETCH_ASSOC) ?: [];

$stmtVerif = $db->query("
    SELECT 
        COUNT(*) AS total_challenges,
        SUM(CASE WHEN consumed_at IS NOT NULL THEN 1 ELSE 0 END) AS total_verified,
        SUM(CASE WHEN consumed_at IS NULL AND expires_at < NOW() THEN 1 ELSE 0 END) AS total_expired,
        SUM(attempts) AS total_wrong_attempts
    FROM otp_challenges 
    WHERE destination_masked NOT LIKE '%@%'
");
$verifStats = $stmtVerif->fetch(PDO::FETCH_ASSOC) ?: [];

$isConfigured = $configInfo['is_configured'];
$isEnvManaged = $configInfo['source'] === 'env';
?>

<div class="container-fluid px-3 py-2">
  <!-- Page Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
    <div>
      <h4 class="fw-bold mb-1 text-dark">
        <i class="bi bi-whatsapp text-success me-2"></i>Konfigurasi OTP WhatsApp
      </h4>
      <p class="text-muted small mb-0">
        Kelola integrasi Gateway WhatsApp API, kredensial token terenkripsi, parameter masa berlaku, rate limiting, dan monitoring riwayat pengiriman real-time.
      </p>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
      <?php if (!empty($settings['whatsapp_otp_enabled']) && $settings['whatsapp_otp_enabled'] === '1'): ?>
        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
          <i class="bi bi-check-circle-fill me-1"></i> WhatsApp OTP Aktif
        </span>
      <?php else: ?>
        <span class="badge bg-warning-subtle text-dark border border-warning px-3 py-2 rounded-pill">
          <i class="bi bi-pause-circle-fill me-1"></i> WhatsApp OTP Siaga / Standby
        </span>
      <?php endif; ?>

      <?php if ($isConfigured): ?>
        <span class="badge bg-primary-subtle text-primary border border-primary px-3 py-2 rounded-pill">
          <i class="bi bi-shield-check me-1"></i> Token Terpasang (<?= strtoupper($configInfo['source']) ?>)
        </span>
      <?php else: ?>
        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
          <i class="bi bi-exclamation-triangle-fill me-1"></i> Token Belum Diisi
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Live Alert Container -->
  <div id="waAlertContainer"></div>

  <!-- 4 Submenu Tabs -->
  <ul class="nav nav-pills mb-4 gap-2 flex-wrap bg-white p-2 rounded-3 border shadow-sm" id="waOtpTabs" role="tablist">
    <li class="nav-item">
      <button class="nav-link active small fw-semibold" id="tab-wa-dashboard-btn" data-bs-toggle="pill" data-bs-target="#tab-wa-dashboard">
        <i class="bi bi-speedometer2 me-1"></i> A. Dashboard OTP
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link small fw-semibold" id="tab-wa-provider-btn" data-bs-toggle="pill" data-bs-target="#tab-wa-provider">
        <i class="bi bi-hdd-network me-1"></i> B. Konfigurasi Provider
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link small fw-semibold" id="tab-wa-settings-btn" data-bs-toggle="pill" data-bs-target="#tab-wa-settings">
        <i class="bi bi-sliders me-1"></i> C. Pengaturan OTP & Keamanan
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link small fw-semibold" id="tab-wa-logs-btn" data-bs-toggle="pill" data-bs-target="#tab-wa-logs">
        <i class="bi bi-journal-text me-1"></i> D. Riwayat Pengiriman
      </button>
    </li>
  </ul>

  <!-- Tab Content -->
  <div class="tab-content" id="waOtpTabContent">

    <!-- ===================================================================
         TAB A: DASHBOARD OTP
         =================================================================== -->
    <div class="tab-pane fade show active" id="tab-wa-dashboard" role="tabpanel">
      <!-- Row 1: Key Metrics -->
      <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-success">
            <div class="text-muted small fw-semibold text-uppercase">Status Gateway WhatsApp</div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <h5 class="fw-bold mb-0 text-dark"><?= strtoupper($configInfo['provider_type']) ?></h5>
              <i class="bi bi-whatsapp fs-2 text-success opacity-75"></i>
            </div>
            <div class="mt-2 small text-muted">
              Source: <span class="badge bg-light text-dark border"><?= htmlspecialchars($configInfo['source']) ?></span> &bull; 
              Status: <span class="badge <?= $isConfigured ? 'bg-success' : 'bg-secondary' ?>"><?= $isConfigured ? 'Siap Kirim' : 'Perlu Setup' ?></span>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-primary">
            <div class="text-muted small fw-semibold text-uppercase">Total Permintaan OTP</div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <h3 class="fw-bold mb-0 text-dark"><?= number_format($delivStats['total_wa_deliveries'] ?? 0) ?></h3>
              <i class="bi bi-send-check fs-2 text-primary opacity-50"></i>
            </div>
            <div class="mt-2 small text-muted">
              Berhasil: <strong class="text-success"><?= number_format($delivStats['total_wa_sent'] ?? 0) ?></strong> | 
              Gagal: <strong class="text-danger"><?= number_format($delivStats['total_wa_failed'] ?? 0) ?></strong>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-info">
            <div class="text-muted small fw-semibold text-uppercase">Verifikasi Berhasil</div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <h3 class="fw-bold mb-0 text-dark"><?= number_format($verifStats['total_verified'] ?? 0) ?></h3>
              <i class="bi bi-shield-check fs-2 text-info opacity-50"></i>
            </div>
            <div class="mt-2 small text-muted">
              Kedaluwarsa: <strong class="text-warning"><?= number_format($verifStats['total_expired'] ?? 0) ?></strong> |
              Salah: <strong class="text-danger"><?= number_format($verifStats['total_wrong_attempts'] ?? 0) ?></strong>
            </div>
          </div>
        </div>

        <div class="col-sm-6 col-lg-3">
          <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-4 border-dark">
            <div class="text-muted small fw-semibold text-uppercase">Pengiriman Terakhir</div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <h6 class="fw-bold mb-0 text-dark">
                <?= !empty($delivStats['last_wa_sent_at']) ? date('d M Y H:i', strtotime($delivStats['last_wa_sent_at'])) : '<span class="text-muted">Belum ada</span>' ?>
              </h6>
              <i class="bi bi-clock-history fs-2 text-secondary opacity-50"></i>
            </div>
            <div class="mt-2 small text-muted">
              Sender: <code><?= htmlspecialchars($configInfo['sender_id'] ?: 'Default') ?></code>
            </div>
          </div>
        </div>
      </div>

      <!-- Row 2: Provider Connection Overview & Quick Actions -->
      <div class="row g-4 mb-4">
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex justify-content-between align-items-center">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-info-circle text-primary me-2"></i>Rincian Status Integrasi Provider</h6>
              <span class="badge bg-light text-dark border">Protokol: HTTPS / E.164</span>
            </div>
            <div class="card-body p-3">
              <div class="table-responsive">
                <table class="table table-sm table-borderless align-middle mb-0">
                  <tbody>
                    <tr>
                      <td class="text-muted" style="width: 35%;">Nama Provider Gateway:</td>
                      <td><strong><?= strtoupper($configInfo['provider_type']) ?></strong> (Modular Adapter Pattern)</td>
                    </tr>
                    <tr>
                      <td class="text-muted">Endpoint URL:</td>
                      <td><code class="text-break"><?= htmlspecialchars($configInfo['api_url']) ?></code></td>
                    </tr>
                    <tr>
                      <td class="text-muted">API Token Status:</td>
                      <td>
                        <span class="font-monospace text-muted"><?= htmlspecialchars($configInfo['masked_token']) ?></span>
                        <span class="badge bg-secondary-subtle text-dark border ms-2">Hashed/Masked</span>
                      </td>
                    </tr>
                    <tr>
                      <td class="text-muted">Sender ID:</td>
                      <td><code><?= htmlspecialchars($configInfo['sender_id'] ?: 'Default') ?></code></td>
                    </tr>
                    <tr>
                      <td class="text-muted">Mode Eksekusi:</td>
                      <td>
                        <?php if ($isConfigured): ?>
                          <span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-lightning-charge me-1"></i> Live Production API Ready</span>
                        <?php else: ?>
                          <span class="badge bg-warning-subtle text-dark border border-warning"><i class="bi bi-shield-lock me-1"></i> Safe Simulation Mode (No Token Expose)</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="mt-3 p-3 bg-light rounded-3 border">
                <div class="small fw-semibold text-secondary mb-1"><i class="bi bi-chat-quote me-1"></i> Template Pesan Aktif:</div>
                <div class="small text-dark font-monospace bg-white p-2 rounded border">
                  <?= nl2br(htmlspecialchars($configInfo['template'])) ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-plug text-success me-2"></i>Uji Cepat Gateway</h6>
            </div>
            <div class="card-body p-3">
              <p class="small text-muted mb-3">
                Uji jangkauan API gateway WhatsApp dan pastikan token serta endpoint merespons dengan benar tanpa menunggu transaksi pengguna.
              </p>

              <div class="d-grid gap-2 mb-3">
                <button type="button" class="btn btn-outline-success btn-sm py-2" id="btnQuickTestConn" onclick="runConnectionTest()">
                  <i class="bi bi-wifi me-1"></i> Uji Koneksi Gateway (Health Check)
                </button>
                <button type="button" class="btn btn-primary-pink btn-sm py-2" data-bs-toggle="modal" data-bs-target="#testMessageModal">
                  <i class="bi bi-whatsapp me-1"></i> Kirim Pesan WhatsApp Uji (Live Test)
                </button>
              </div>

              <!-- Result Box -->
              <div id="quickTestResult" class="d-none p-3 rounded-3 border small">
                <!-- Injected via JS -->
              </div>

              <div class="alert alert-info border-0 bg-info-subtle text-dark small mt-3 mb-0">
                <i class="bi bi-shield-exclamation me-1"></i> <strong>Standar Keamanan:</strong>
                Sistem tidak pernah mengekspos token mentah di browser. Uji koneksi dijalankan melalui backend server PHP menggunakan cURL timeout 10s.
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Row 3: Recent Activity Snapshot -->
      <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-transparent border-0 pt-3 px-3 d-flex justify-content-between align-items-center">
          <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-secondary me-2"></i>10 Pengiriman WhatsApp Terkini</h6>
          <button class="btn btn-sm btn-link text-decoration-none" onclick="refreshDashboardLogs()"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Waktu</th>
                  <th>Nomor Tujuan (Masked)</th>
                  <th>Keperluan (Purpose)</th>
                  <th>Status Pengiriman</th>
                  <th>Status Provider</th>
                  <th>Status Verifikasi</th>
                  <th class="pe-3">ID Pesan Gateway</th>
                </tr>
              </thead>
              <tbody id="dashboardRecentLogsBody">
                <tr>
                  <td colspan="7" class="text-center py-3 text-muted">Memuat log terkini...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================
         TAB B: KONFIGURASI PROVIDER
         =================================================================== -->
    <div class="tab-pane fade" id="tab-wa-provider" role="tabpanel">
      <div class="row g-4">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-3 bg-white">
            <div class="card-header bg-transparent border-0 pt-3 px-4 d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-gear-wide-connected text-success me-2"></i>Parameter Gateway Provider WhatsApp</h5>
              <span class="badge bg-light text-muted border">Enkripsi AES-256-GCM / .env</span>
            </div>
            <div class="card-body px-4 pb-4 pt-2">
              <form id="waProviderConfigForm" onsubmit="saveProviderConfig(event)">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <!-- Status Aktif / Nonaktif Switch -->
                <div class="p-3 bg-light rounded-3 border mb-3 d-flex justify-content-between align-items-center">
                  <div>
                    <label class="fw-bold mb-0 text-dark d-block" for="whatsappOtpEnabledSwitch">Aktifkan Layanan WhatsApp OTP</label>
                    <small class="text-muted">Jika dinonaktifkan, autentikasi dialihkan ke metode email atau simulasi.</small>
                  </div>
                  <div class="form-check form-switch fs-5">
                    <input class="form-check-input" type="checkbox" id="whatsappOtpEnabledSwitch" name="whatsapp_otp_enabled" value="1" <?= (!empty($settings['whatsapp_otp_enabled']) && $settings['whatsapp_otp_enabled'] === '1') ? 'checked' : '' ?>>
                  </div>
                </div>

                <!-- Provider Preset Selection -->
                <div class="mb-3">
                  <label class="form-label fw-semibold text-dark">Nama Provider Gateway</label>
                  <select class="form-select" id="providerTypeSelect" name="provider_type" onchange="applyProviderPreset(this.value)">
                    <option value="fonnte" <?= $configInfo['provider_type'] === 'fonnte' ? 'selected' : '' ?>>Fonnte (Rekomendasi Indonesia - Multi-Device / WhatsApp Business)</option>
                    <option value="wablas" <?= $configInfo['provider_type'] === 'wablas' ? 'selected' : '' ?>>Wablas (Indonesian Gateway Direct)</option>
                    <option value="metacloud" <?= $configInfo['provider_type'] === 'metacloud' ? 'selected' : '' ?>>Meta Cloud API (Official WhatsApp Business Cloud)</option>
                    <option value="generic" <?= $configInfo['provider_type'] === 'generic' ? 'selected' : '' ?>>Generic Custom Webhook / Custom HTTP API</option>
                  </select>
                  <div class="form-text">Setiap provider menggunakan adapter modular yang sesuai dengan standar payload resminya.</div>
                </div>

                <!-- API Endpoint URL -->
                <div class="mb-3">
                  <label class="form-label fw-semibold text-dark">API Endpoint URL</label>
                  <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-link-45deg"></i></span>
                    <input type="url" class="form-control font-monospace" id="apiUrlInput" name="api_url" value="<?= htmlspecialchars($configInfo['api_url']) ?>" required>
                  </div>
                  <div class="d-flex gap-1 mt-1">
                    <span class="small text-muted me-1">Preset:</span>
                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none small" onclick="setApiUrl('https://api.fonnte.com/send')">Fonnte</button> &bull;
                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none small" onclick="setApiUrl('https://kudus.wablas.com/api/send-message')">Wablas Kudus</button> &bull;
                    <button type="button" class="btn btn-sm btn-link p-0 text-decoration-none small" onclick="setApiUrl('https://graph.facebook.com/v19.0/{PHONE_NUMBER_ID}/messages')">Meta Cloud</button>
                  </div>
                </div>

                <!-- API Token (Masked / Secure Input) -->
                <div class="mb-3">
                  <label class="form-label fw-semibold text-dark">
                    API Token / Kredensial Rahasia 
                    <span class="badge bg-secondary-subtle text-secondary ms-1">Disamarkan</span>
                  </label>
                  <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                    <input type="text" class="form-control font-monospace" id="apiTokenInput" name="api_token" placeholder="<?= htmlspecialchars($configInfo['masked_token']) ?>" autocomplete="off">
                    <button class="btn btn-outline-secondary" type="button" onclick="clearTokenInput()" title="Kosongkan untuk input token baru">
                      <i class="bi bi-pencil"></i>
                    </button>
                  </div>
                  <div class="form-text text-muted">
                    Kosongkan jika tidak ingin mengubah token yang tersimpan di <code>.env</code> atau database terenkripsi. Nilai token tidak pernah dikirim balik dalam teks biasa ke antarmuka web.
                  </div>
                </div>

                <!-- Sender ID / Phone Number -->
                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Sender ID / Nomor Pengirim</label>
                    <input type="text" class="form-control" name="sender_id" id="senderIdInput" value="<?= htmlspecialchars($configInfo['sender_id']) ?>" placeholder="Contoh: nnasa_ca atau 628xxxxxxxx">
                    <div class="form-text">Nama sender atau ID perangkat yang terdaftar di provider.</div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark">Request Timeout (Detik)</label>
                    <div class="input-group">
                      <input type="number" class="form-control" name="timeout_seconds" id="timeoutInput" value="<?= (int)($settings['whatsapp_timeout_seconds'] ?? 10) ?>" min="3" max="30">
                      <span class="input-group-text">detik</span>
                    </div>
                    <div class="form-text">Maksimal durasi tunggu respon provider sebelum dianggap timeout.</div>
                  </div>
                </div>

                <!-- Template Pesan Autentikasi -->
                <div class="mb-4">
                  <label class="form-label fw-semibold text-dark">Template Pesan OTP WhatsApp</label>
                  <textarea class="form-control font-monospace" name="template" id="templateInput" rows="4" required><?= htmlspecialchars($configInfo['template']) ?></textarea>
                  <div class="form-text">
                    Gunakan tag <code>{{otp}}</code> untuk menyisipkan kode numerik dan <code>{{expiry}}</code> untuk masa berlaku dalam menit.
                  </div>
                </div>

                <!-- Kontrol Auto-Read (Cegah Centang Biru Otomatis) -->
                <div class="p-3 bg-light rounded-3 border mb-4">
                  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <div>
                      <span class="fw-bold text-dark d-block"><i class="bi bi-eye-slash text-danger me-1"></i> Kontrol Auto-Read (Anti Centang Biru)</span>
                      <small class="text-muted">Matikan fitur baca otomatis di server Fonnte agar WhatsApp Anda tidak otomatis membaca (centang biru) pesan masuk dari pelanggan/teman.</small>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success px-2 py-1 mt-1 mt-md-0" id="autoreadStatusBadge">
                      <i class="bi bi-shield-check me-1"></i> Auto-Read Dimatikan (Aman)
                    </span>
                  </div>
                  <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnDisableAutoread" onclick="toggleFonnteAutoread(false)">
                      <i class="bi bi-x-circle me-1"></i> Matikan Auto-Read Sekarang (Cegah Centang Biru)
                    </button>
                    <button type="button" class="btn btn-sm btn-link text-muted text-decoration-none small" onclick="toggleFonnteAutoread(true)">
                      Aktifkan Kembali
                    </button>
                  </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center pt-3 border-top">
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="testCurrentFormConnection()">
                      <i class="bi bi-wifi me-1"></i> Uji Koneksi Form
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#testMessageModal">
                      <i class="bi bi-send me-1"></i> Kirim Pesan Uji
                    </button>
                  </div>
                  <button type="submit" class="btn btn-primary-pink px-4" id="btnSaveConfig">
                    <i class="bi bi-check2-circle me-1"></i> Simpan Konfigurasi
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Sidebar Helper Guide -->
        <div class="col-lg-4">
          <div class="card border-0 shadow-sm rounded-3 bg-white mb-3">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-lock text-primary me-2"></i>Keamanan Server & .env</h6>
            </div>
            <div class="card-body p-3 small text-muted">
              <p class="mb-2">
                File <code>.env</code> disimpan di root project di luar jangkauan publik dan dilindungi oleh aturan <code>.htaccess</code>:
              </p>
              <pre class="bg-light p-2 rounded border font-monospace text-dark mb-2" style="font-size: 0.75rem;">
# .env WhatsApp Gateway
WHATSAPP_PROVIDER=fonnte
WHATSAPP_API_TOKEN=ISI_TOKEN_DI_SINI
WHATSAPP_API_URL=https://api.fonnte.com/send
WHATSAPP_SENDER_ID=NNASA_CA
              </pre>
              <div class="alert alert-warning p-2 border-0 bg-warning-subtle text-dark mb-0">
                <i class="bi bi-info-circle me-1"></i> Jika token diisi lewat form ini, token otomatis dienkripsi dengan <code>AES-256-GCM</code> sebelum disimpan ke database MariaDB.
              </div>
            </div>
          </div>

          <div class="card border-0 shadow-sm rounded-3 bg-white">
            <div class="card-header bg-transparent border-0 pt-3 px-3">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-check text-success me-2"></i>Panduan Provider</h6>
            </div>
            <div class="card-body p-3 small text-muted">
              <ul class="ps-3 mb-0">
                <li class="mb-2"><strong>Fonnte:</strong> Header <code>Authorization: TOKEN</code>, body form-data <code>target</code> dan <code>message</code>.</li>
                <li class="mb-2"><strong>Wablas:</strong> Header <code>Authorization: TOKEN</code>, body form-data <code>phone</code> dan <code>message</code>.</li>
                <li class="mb-2"><strong>Meta Cloud:</strong> Header <code>Authorization: Bearer TOKEN</code>, body JSON template autentikasi.</li>
                <li><strong>Generic:</strong> Custom JSON POST dengan format field dinamis.</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================
         TAB C: PENGATURAN OTP & KEAMANAN
         =================================================================== -->
    <div class="tab-pane fade" id="tab-wa-settings" role="tabpanel">
      <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-transparent border-0 pt-3 px-4 d-flex justify-content-between align-items-center">
          <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-sliders text-primary me-2"></i>Kebijakan Masa Berlaku & Rate Limiting OTP</h5>
          <span class="badge bg-light text-muted border">Anti Brute-Force & Flooding</span>
        </div>
        <div class="card-body px-4 pb-4 pt-2">
          <form id="waOtpPolicyForm" onsubmit="saveOtpPolicy(event)">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div class="row g-4 mb-4">
              <!-- OTP Length -->
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark">Panjang Kode OTP (Digit)</label>
                <div class="input-group">
                  <input type="number" class="form-control" name="otp_code_length" value="<?= (int)($settings['otp_code_length'] ?? 6) ?>" min="4" max="8" required>
                  <span class="input-group-text">Digit Numerik</span>
                </div>
                <div class="form-text">Standar industri e-commerce adalah 6 digit numerik kriptografis (<code>random_int</code>).</div>
              </div>

              <!-- OTP Expiry -->
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark">Masa Berlaku OTP (Menit)</label>
                <div class="input-group">
                  <input type="number" class="form-control" name="otp_expiry_minutes" value="<?= (int)($settings['otp_expiry_minutes'] ?? 5) ?>" min="1" max="30" required>
                  <span class="input-group-text">Menit</span>
                </div>
                <div class="form-text">Masa aktif kode sebelum kedaluwarsa (Default: 5 menit).</div>
              </div>

              <!-- Resend Interval / Cooldown -->
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark">Interval Kirim Ulang (Cooldown)</label>
                <div class="input-group">
                  <input type="number" class="form-control" name="otp_resend_interval_seconds" value="<?= (int)($settings['otp_resend_interval_seconds'] ?? 60) ?>" min="15" max="300" required>
                  <span class="input-group-text">Detik</span>
                </div>
                <div class="form-text">Waktu jeda wajib sebelum pengguna diizinkan meminta OTP baru (Default: 60 detik).</div>
              </div>

              <!-- Max Verification Attempts -->
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark">Maksimal Percobaan Verifikasi Salah</label>
                <div class="input-group">
                  <input type="number" class="form-control" name="otp_max_verify_attempts" value="<?= (int)($settings['otp_max_verify_attempts'] ?? 5) ?>" min="3" max="10" required>
                  <span class="input-group-text">Kali Percobaan</span>
                </div>
                <div class="form-text">Jika salah melebihi batas ini, challenge langsung dibatalkan (Default: 5 kali).</div>
              </div>

              <!-- Max Send per Phone Number -->
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark">Batas Pengiriman per Nomor WhatsApp</label>
                <div class="input-group">
                  <input type="number" class="form-control" name="otp_max_send_per_account" value="<?= (int)($settings['otp_max_send_per_account'] ?? 5) ?>" min="3" max="20" required>
                  <span class="input-group-text">SMS/WA per Periode</span>
                </div>
                <div class="form-text">Maksimal request per nomor dalam jendela waktu pengawasan (Default: 5 kali).</div>
              </div>

              <!-- Max Send per IP Address -->
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark">Batas Pengiriman per Alamat IP</label>
                <div class="input-group">
                  <input type="number" class="form-control" name="otp_max_send_per_ip" value="<?= (int)($settings['otp_max_send_per_ip'] ?? 10) ?>" min="5" max="50" required>
                  <span class="input-group-text">Request per IP</span>
                </div>
                <div class="form-text">Mencegah serangan bot massal dari alamat IP yang sama (Default: 10 request).</div>
              </div>

              <!-- Lockout Duration -->
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark">Durasi Blokir Sementara (Lockout)</label>
                <div class="input-group">
                  <input type="number" class="form-control" name="otp_lockout_duration_minutes" value="<?= (int)($settings['otp_lockout_duration_minutes'] ?? 15) ?>" min="5" max="120" required>
                  <span class="input-group-text">Menit</span>
                </div>
                <div class="form-text">Lama waktu akun atau IP dibekukan jika melanggar rate limit (Default: 15 menit).</div>
              </div>

              <!-- Purpose Matrix -->
              <div class="col-md-6">
                <label class="form-label fw-semibold text-dark">Tujuan Autentikasi yang Diizinkan</label>
                <div class="p-3 bg-light rounded-3 border">
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" checked disabled id="purpReg">
                    <label class="form-check-label small fw-semibold text-dark" for="purpReg">Registrasi Pelanggan Baru</label>
                  </div>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" checked disabled id="purpLogin">
                    <label class="form-check-label small fw-semibold text-dark" for="purpLogin">Login dengan WhatsApp (Passwordless OTP)</label>
                  </div>
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" checked disabled id="purpVerif">
                    <label class="form-check-label small fw-semibold text-dark" for="purpVerif">Verifikasi Nomor WhatsApp Profil</label>
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" checked disabled id="purpReset">
                    <label class="form-check-label small fw-semibold text-dark" for="purpReset">Pemulihan Password (Reset Password)</label>
                  </div>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-end pt-3 border-top">
              <button type="submit" class="btn btn-primary-pink px-4" id="btnSavePolicy">
                <i class="bi bi-shield-check me-1"></i> Simpan Kebijakan Keamanan
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ===================================================================
         TAB D: RIWAYAT PENGIRIMAN
         =================================================================== -->
    <div class="tab-pane fade" id="tab-wa-logs" role="tabpanel">
      <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-transparent border-0 pt-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-journal-text text-success me-2"></i>Audit Log Pengiriman WhatsApp</h5>
            <p class="text-muted small mb-0">Catatan pengiriman riil dari database. Kode OTP dan token rahasia otomatis disembunyikan.</p>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-sm btn-outline-secondary" onclick="loadAuditLogs()">
              <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data
            </button>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Waktu</th>
                  <th>Nomor Tujuan (Masked)</th>
                  <th>Keperluan</th>
                  <th>Status Permintaan</th>
                  <th>Status Provider</th>
                  <th>ID Pesan Provider</th>
                  <th>Kode Error</th>
                  <th>Status Verifikasi</th>
                  <th class="pe-4">Waktu Verifikasi</th>
                </tr>
              </thead>
              <tbody id="auditLogsBody">
                <tr>
                  <td colspan="9" class="text-center py-4 text-muted">Memuat data log pengiriman...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- ===================================================================
     MODAL: KIRIM PESAN UJI WHATSAPP
     =================================================================== -->
<div class="modal fade" id="testMessageModal" tabindex="-1" aria-labelledby="testMessageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-dark" id="testMessageModalLabel">
          <i class="bi bi-whatsapp text-success me-2"></i>Kirim Pesan WhatsApp Uji
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-3">
          Kirim pesan percobaan langsung ke nomor WhatsApp Anda untuk memverifikasi apakah akun WhatsApp Gateway terhubung dengan baik.
        </p>

        <form id="testMessageForm" onsubmit="executeTestMessage(event)">
          <div class="mb-3">
            <label class="form-label fw-semibold text-dark small">Nomor WhatsApp Penerima</label>
            <div class="input-group">
              <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
              <input type="text" class="form-control" id="testPhoneInput" placeholder="Contoh: 08123456789 atau 628123456789" required>
            </div>
            <div class="form-text small">Nomor akan dinormalisasi ke standar internasional (+62).</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold text-dark small">Isi Pesan Uji</label>
            <textarea class="form-control font-monospace small" id="testMsgBodyInput" rows="3" required>Tes pesan WhatsApp dari sistem e-commerce nnasa_ca. Verifikasi koneksi berhasil! 💕</textarea>
          </div>

          <div id="testMessageResult" class="d-none p-3 rounded-3 border small mb-3">
            <!-- Results injected here -->
          </div>

          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Tutup</button>
            <button type="submit" class="btn btn-success btn-sm px-3" id="btnSubmitTestMsg">
              <i class="bi bi-send-fill me-1"></i> Kirim Pesan Sekarang
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Page Specific JavaScript -->
<script>
const CSRF_TOKEN = '<?= $csrfToken ?>';
const ADMIN_API_URL = '<?= BASE_URL ?>api/admin/whatsapp-otp.php';

document.addEventListener('DOMContentLoaded', function() {
  loadDashboardData();
});

// Switch Provider Presets
function applyProviderPreset(type) {
  const urlInput = document.getElementById('apiUrlInput');
  if (type === 'fonnte') {
    urlInput.value = 'https://api.fonnte.com/send';
  } else if (type === 'wablas') {
    urlInput.value = 'https://kudus.wablas.com/api/send-message';
  } else if (type === 'metacloud') {
    urlInput.value = 'https://graph.facebook.com/v19.0/{PHONE_NUMBER_ID}/messages';
  }
}

function setApiUrl(url) {
  document.getElementById('apiUrlInput').value = url;
}

function clearTokenInput() {
  const tokenInput = document.getElementById('apiTokenInput');
  tokenInput.value = '';
  tokenInput.placeholder = 'Ketik token API WhatsApp baru di sini...';
  tokenInput.focus();
}

// 1. Load Dashboard Data
function loadDashboardData() {
  fetch(`${ADMIN_API_URL}?action=get_dashboard_data`)
    .then(res => res.json())
    .then(data => {
      if (data.success && data.metrics && data.metrics.recent_logs) {
        renderRecentLogs(data.metrics.recent_logs, 'dashboardRecentLogsBody');
      }
    })
    .catch(err => {
      console.error('Failed to load dashboard data:', err);
    });
}

function renderRecentLogs(logs, targetId) {
  const tbody = document.getElementById(targetId);
  if (!tbody) return;

  if (!logs || logs.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-muted">Belum ada riwayat pengiriman WhatsApp.</td></tr>`;
    return;
  }

  tbody.innerHTML = logs.map(item => {
    let statusBadge = '<span class="badge bg-secondary">Unknown</span>';
    if (item.delivery_status === 'sent') {
      statusBadge = '<span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check2"></i> Terkirim</span>';
    } else if (item.delivery_status === 'simulated') {
      statusBadge = '<span class="badge bg-info-subtle text-info border border-info"><i class="bi bi-cone-striped"></i> Simulasi</span>';
    } else if (item.delivery_status === 'failed') {
      statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="bi bi-x-circle"></i> Gagal</span>';
    }

    let verifBadge = '<span class="badge bg-secondary-subtle text-secondary">Menunggu</span>';
    if (item.consumed_at) {
      verifBadge = '<span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check-all"></i> Terverifikasi</span>';
    } else if (item.verification_status === 'Kedaluwarsa') {
      verifBadge = '<span class="badge bg-warning-subtle text-dark border border-warning">Kedaluwarsa</span>';
    }

    return `
      <tr>
        <td class="ps-3 font-monospace text-muted">${item.sent_at || '-'}</td>
        <td class="fw-bold font-monospace">${escapeHtml(item.destination_masked || '-')}</td>
        <td><span class="badge bg-light text-dark border">${escapeHtml(item.purpose || 'otp')}</span></td>
        <td>${statusBadge}</td>
        <td><code>${escapeHtml(item.provider_name || '-')}</code></td>
        <td>${verifBadge}</td>
        <td class="pe-3 font-monospace text-muted small">${escapeHtml(item.provider_message_id || '-')}</td>
      </tr>
    `;
  }).join('');
}

// 2. Test Connection
function runConnectionTest(customData = null) {
  const resultBox = document.getElementById('quickTestResult');
  resultBox.className = 'p-3 rounded-3 border small bg-light';
  resultBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memeriksa koneksi gateway WhatsApp...';

  const formData = new FormData();
  formData.append('action', 'test_connection');
  if (customData) {
    if (customData.api_token) formData.append('api_token', customData.api_token);
    if (customData.api_url) formData.append('api_url', customData.api_url);
    if (customData.provider_type) formData.append('provider_type', customData.provider_type);
  }

  fetch(ADMIN_API_URL, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    resultBox.classList.remove('d-none');
    if (res.success) {
      resultBox.className = 'p-3 rounded-3 border small bg-success-subtle text-dark border-success';
      resultBox.innerHTML = `
        <div class="fw-bold text-success mb-1"><i class="bi bi-check-circle-fill me-1"></i> Koneksi Berhasil!</div>
        <div>Provider: <strong>${escapeHtml(res.provider || 'Gateway')}</strong></div>
        <div>Pesan: ${escapeHtml(res.message || 'Koneksi siap')}</div>
        <div class="text-muted mt-1" style="font-size:0.75rem;">Status Code: ${res.http_code || 200} | Latency: ${res.latency_ms || 0}ms</div>
      `;
    } else {
      resultBox.className = 'p-3 rounded-3 border small bg-danger-subtle text-dark border-danger';
      resultBox.innerHTML = `
        <div class="fw-bold text-danger mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i> Koneksi Gagal / Perhatian</div>
        <div>Pesan: ${escapeHtml(res.message || 'Gagal terhubung ke provider')}</div>
        <div class="text-muted mt-1" style="font-size:0.75rem;">Kode Error: ${escapeHtml(res.error_code || 'CONN_ERR')} | HTTP: ${res.http_code || 0}</div>
      `;
    }
  })
  .catch(err => {
    resultBox.className = 'p-3 rounded-3 border small bg-danger-subtle text-danger border-danger';
    resultBox.innerHTML = `<i class="bi bi-x-circle me-1"></i> Gagal memanggil endpoint test: ${err.message}`;
  });
}

function testCurrentFormConnection() {
  const token = document.getElementById('apiTokenInput').value;
  const url = document.getElementById('apiUrlInput').value;
  const type = document.getElementById('providerTypeSelect').value;

  // switch to dashboard tab or show alert
  runConnectionTest({
    api_token: token,
    api_url: url,
    provider_type: type
  });
  
  // Activate tab A to see result
  const tabBtn = document.getElementById('tab-wa-dashboard-btn');
  if (tabBtn) bootstrap.Tab.getOrCreateInstance(tabBtn).show();
}

// 3. Test Live WhatsApp Message
function executeTestMessage(e) {
  e.preventDefault();
  const phone = document.getElementById('testPhoneInput').value.trim();
  const msg = document.getElementById('testMsgBodyInput').value.trim();
  const resultBox = document.getElementById('testMessageResult');
  const btn = document.getElementById('btnSubmitTestMsg');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...';
  resultBox.className = 'p-3 rounded-3 border small bg-light';
  resultBox.innerHTML = 'Mengirim pesan WhatsApp via gateway backend...';
  resultBox.classList.remove('d-none');

  const formData = new FormData();
  formData.append('action', 'test_message');
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('phone', phone);
  formData.append('message', msg);

  // Send current form inputs if present
  const tokenInput = document.getElementById('apiTokenInput');
  if (tokenInput && tokenInput.value) formData.append('api_token', tokenInput.value);
  const urlInput = document.getElementById('apiUrlInput');
  if (urlInput && urlInput.value) formData.append('api_url', urlInput.value);
  const typeSelect = document.getElementById('providerTypeSelect');
  if (typeSelect && typeSelect.value) formData.append('provider_type', typeSelect.value);

  fetch(ADMIN_API_URL, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Pesan Sekarang';

    if (res.success) {
      resultBox.className = 'p-3 rounded-3 border small bg-success-subtle text-success border-success';
      resultBox.innerHTML = `
        <div class="fw-bold"><i class="bi bi-check-circle-fill me-1"></i> Berhasil Diterima Provider!</div>
        <div class="text-dark mt-1">${escapeHtml(res.message || 'Pesan diteruskan')}</div>
        <div class="text-muted mt-1" style="font-size:0.75rem;">Message ID: ${escapeHtml(res.message_id || '-')}</div>
      `;
      loadDashboardData();

      if (typeof window.showIncomingOtpNotification === 'function') {
        const testPhoneVal = document.getElementById('testPhoneInput') ? document.getElementById('testPhoneInput').value : '';
        const testMsgVal = document.getElementById('testMessageInput') ? document.getElementById('testMessageInput').value : '';
        window.showIncomingOtpNotification({
          channel: 'whatsapp',
          phone: testPhoneVal,
          message: testMsgVal || res.message || 'Pesan uji coba WhatsApp berhasil dikirim.',
          sender: 'nnasa_ca Admin Gateway Test'
        });
      }
    } else {
      resultBox.className = 'p-3 rounded-3 border small bg-danger-subtle text-danger border-danger';
      resultBox.innerHTML = `
        <div class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-1"></i> Pengiriman Gagal</div>
        <div class="text-dark mt-1">${escapeHtml(res.message || 'Error pengiriman')}</div>
        <div class="text-muted mt-1" style="font-size:0.75rem;">Error Code: ${escapeHtml(res.error_code || 'SEND_ERROR')}</div>
      `;
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Kirim Pesan Sekarang';
    resultBox.className = 'p-3 rounded-3 border small bg-danger-subtle text-danger border-danger';
    resultBox.innerHTML = `Terjadi kesalahan jaringan: ${err.message}`;
  });
}

// 4. Save Provider Settings
function saveProviderConfig(e) {
  e.preventDefault();
  const form = document.getElementById('waProviderConfigForm');
  const btn = document.getElementById('btnSaveConfig');
  const formData = new FormData(form);
  formData.append('action', 'save_settings');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

  fetch(ADMIN_API_URL, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Simpan Konfigurasi';

    if (res.success) {
      showAlert('success', res.message);
      loadDashboardData();
    } else {
      showAlert('danger', res.message);
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Simpan Konfigurasi';
    showAlert('danger', 'Gagal menyimpan: ' + err.message);
  });
}

// 4B. Toggle Auto-Read Fonnte (Matikan / Hidupkan Centang Biru)
function toggleFonnteAutoread(enable) {
  const btn = document.getElementById('btnDisableAutoread');
  const badge = document.getElementById('autoreadStatusBadge');
  const token = document.getElementById('apiTokenInput') ? document.getElementById('apiTokenInput').value : '';

  if (btn) btn.disabled = true;

  const formData = new FormData();
  formData.append('action', 'disable_autoread');
  formData.append('csrf_token', CSRF_TOKEN);
  formData.append('enable', enable ? '1' : '0');
  if (token) formData.append('api_token', token);

  fetch(ADMIN_API_URL, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    if (btn) btn.disabled = false;
    if (res.success) {
      showAlert('success', res.message);
      if (badge) {
        if (enable) {
          badge.className = 'badge bg-warning-subtle text-warning border border-warning px-2 py-1 mt-1 mt-md-0';
          badge.innerHTML = '<i class="bi bi-eye me-1"></i> Auto-Read Aktif';
        } else {
          badge.className = 'badge bg-success-subtle text-success border border-success px-2 py-1 mt-1 mt-md-0';
          badge.innerHTML = '<i class="bi bi-shield-check me-1"></i> Auto-Read Dimatikan (Aman)';
        }
      }
    } else {
      showAlert('danger', res.message);
    }
  })
  .catch(err => {
    if (btn) btn.disabled = false;
    showAlert('danger', 'Gagal mengubah setting auto-read: ' + err.message);
  });
}

// 5. Save Policy Settings
function saveOtpPolicy(e) {
  e.preventDefault();
  const form = document.getElementById('waOtpPolicyForm');
  const btn = document.getElementById('btnSavePolicy');
  const formData = new FormData(form);
  formData.append('action', 'save_settings');

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';

  fetch(ADMIN_API_URL, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(res => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Simpan Kebijakan Keamanan';

    if (res.success) {
      showAlert('success', res.message);
    } else {
      showAlert('danger', res.message);
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-shield-check me-1"></i> Simpan Kebijakan Keamanan';
    showAlert('danger', 'Gagal menyimpan: ' + err.message);
  });
}

// 6. Audit Logs Tab
document.getElementById('tab-wa-logs-btn').addEventListener('shown.bs.tab', function() {
  loadAuditLogs();
});

function loadAuditLogs() {
  const tbody = document.getElementById('auditLogsBody');
  tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Memuat data log...</td></tr>`;

  fetch(`${ADMIN_API_URL}?action=get_logs&limit=50`)
    .then(res => res.json())
    .then(data => {
      if (!data.success || !data.logs || data.logs.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-muted">Belum ada riwayat pengiriman WhatsApp.</td></tr>`;
        return;
      }

      tbody.innerHTML = data.logs.map(log => {
        let reqBadge = '<span class="badge bg-secondary">Unknown</span>';
        if (log.delivery_status === 'sent') {
          reqBadge = '<span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check2"></i> Terkirim</span>';
        } else if (log.delivery_status === 'simulated') {
          reqBadge = '<span class="badge bg-info-subtle text-info border border-info"><i class="bi bi-cone-striped"></i> Simulasi</span>';
        } else if (log.delivery_status === 'failed') {
          reqBadge = '<span class="badge bg-danger-subtle text-danger border border-danger"><i class="bi bi-x-circle"></i> Gagal</span>';
        }

        let verifBadge = '<span class="badge bg-secondary-subtle text-secondary">Menunggu</span>';
        if (log.consumed_at) {
          verifBadge = '<span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check-all"></i> Terverifikasi</span>';
        } else if (log.verification_status === 'Kedaluwarsa') {
          verifBadge = '<span class="badge bg-warning-subtle text-dark border border-warning">Kedaluwarsa</span>';
        }

        return `
          <tr>
            <td class="ps-4 font-monospace text-muted">${log.sent_at || '-'}</td>
            <td class="fw-bold font-monospace">${escapeHtml(log.destination_masked || '-')}</td>
            <td><span class="badge bg-light text-dark border">${escapeHtml(log.purpose || 'otp')}</span></td>
            <td>${reqBadge}</td>
            <td><code>${escapeHtml(log.provider_name || '-')}</code></td>
            <td class="font-monospace text-muted small">${escapeHtml(log.provider_message_id || '-')}</td>
            <td class="text-danger font-monospace small">${escapeHtml(log.error_code || '-')}</td>
            <td>${verifBadge}</td>
            <td class="pe-4 font-monospace text-muted small">${log.consumed_at || '-'}</td>
          </tr>
        `;
      }).join('');
    })
    .catch(err => {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center py-4 text-danger">Gagal memuat log: ${err.message}</td></tr>`;
    });
}

function refreshDashboardLogs() {
  loadDashboardData();
}

function showAlert(type, msg) {
  const container = document.getElementById('waAlertContainer');
  container.innerHTML = `
    <div class="alert alert-${type} alert-dismissible fade show rounded-3 shadow-sm border-0 d-flex align-items-center" role="alert">
      <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-2 fs-5"></i>
      <div>${escapeHtml(msg)}</div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  `;
}

function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
</script>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
