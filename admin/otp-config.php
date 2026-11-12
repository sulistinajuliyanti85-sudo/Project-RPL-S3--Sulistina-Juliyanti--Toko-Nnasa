<?php
/**
 * Admin: Konfigurasi Authentication OTP
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Konfigurasi Authentication OTP';
require_once dirname(__DIR__) . '/templates/admin_header.php';
require_once dirname(__DIR__) . '/app/Models/OTPModel.php';

$db = getDB();
$otpModel = new OTPModel($db);

$providers = $otpModel->getAllProviders();
$settings = $otpModel->getAllSettings();
$metrics = $otpModel->getDashboardMetrics();
$csrfToken = csrf_token();
?>

<div class="container-fluid px-3 py-2">
  <!-- Page Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
    <div>
      <h4 class="fw-bold mb-1 text-dark"><i class="bi bi-shield-lock text-primary-pink me-2"></i>Konfigurasi Authentication OTP</h4>
      <p class="text-muted small mb-0">Kelola verifikasi multi-faktor (MFA), provider email/SMS/WhatsApp, template pesan, dan audit keamanan.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
      <?php if (!empty($settings['otp_enabled']) && $settings['otp_enabled'] === '1'): ?>
        <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
          <i class="bi bi-shield-check me-1"></i> Layanan OTP Aktif
        </span>
      <?php else: ?>
        <span class="badge bg-danger-subtle text-danger border border-danger px-3 py-2 rounded-pill">
          <i class="bi bi-shield-slash me-1"></i> Layanan OTP Nonaktif
        </span>
      <?php endif; ?>

      <?php if (!empty($settings['otp_test_mode']) && $settings['otp_test_mode'] === '1'): ?>
        <span class="badge bg-warning-subtle text-dark border border-warning px-3 py-2 rounded-pill">
          <i class="bi bi-cone-striped me-1"></i> Mode Simulasi (Testing)
        </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tab Navigation (8 Submenus) -->
  <ul class="nav nav-pills mb-4 gap-2 flex-wrap bg-white p-2 rounded-3 border shadow-sm" id="otpConfigTabs" role="tablist">
    <li class="nav-item">
      <button class="nav-link active small fw-semibold" id="tab-otp-dashboard-btn" data-bs-toggle="pill" data-bs-target="#tab-otp-dashboard">
        <i class="bi bi-speedometer2 me-1"></i> 1. Dashboard OTP
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link small fw-semibold" id="tab-otp-settings-btn" data-bs-toggle="pill" data-bs-target="#tab-otp-settings">
        <i class="bi bi-gear-wide-connected me-1"></i> 2. Pengaturan OTP
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link small fw-semibold" id="tab-otp-templates-btn" data-bs-toggle="pill" data-bs-target="#tab-otp-templates">
        <i class="bi bi-file-earmark-text me-1"></i> 3. Template Pesan
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link small fw-semibold" id="tab-otp-providers-btn" data-bs-toggle="pill" data-bs-target="#tab-otp-providers">
        <i class="bi bi-send-check me-1"></i> 4. Provider Pengiriman
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link small fw-semibold" id="tab-otp-logs-btn" data-bs-toggle="pill" data-bs-target="#tab-otp-logs">
        <i class="bi bi-journal-check me-1"></i> 5. Riwayat & Log
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link small fw-semibold" id="tab-otp-security-btn" data-bs-toggle="pill" data-bs-target="#tab-otp-security">
        <i class="bi bi-lock me-1"></i> 6. Batas & Keamanan
      </button>
    </li>
  </ul>

  <!-- Tab Contents -->
  <div class="tab-content" id="otpConfigTabContent">

    <!-- ===================================================================
         TAB 1: DASHBOARD OTP
         =================================================================== -->
    <div class="tab-pane fade show active" id="tab-otp-dashboard" role="tabpanel">
      <!-- Row 1: Real OTP Statistics -->
      <div class="row g-3 mb-4">
        <div class="col-md-3">
          <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white">
            <div class="text-muted small fw-semibold text-uppercase">Total Permintaan OTP</div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <h3 class="fw-bold mb-0 text-dark"><?= number_format($metrics['total_challenges'] ?? 0) ?></h3>
              <i class="bi bi-shield-lock fs-2 text-primary-pink opacity-50"></i>
            </div>
            <small class="text-muted mt-2 d-block">Terkirim: <?= number_format($metrics['total_sent'] ?? 0) ?> | Gagal: <?= number_format($metrics['total_failed'] ?? 0) ?></small>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white">
            <div class="text-muted small fw-semibold text-uppercase">Verifikasi Berhasil</div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <h3 class="fw-bold mb-0 text-success"><?= number_format($metrics['total_verified'] ?? 0) ?></h3>
              <i class="bi bi-check-circle fs-2 text-success opacity-50"></i>
            </div>
            <small class="text-muted mt-2 d-block">Tingkat Berhasil: <?= !empty($metrics['total_challenges']) ? round(($metrics['total_verified'] / $metrics['total_challenges']) * 100) : 0 ?>%</small>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white">
            <div class="text-muted small fw-semibold text-uppercase">Kedaluwarsa / Tidak Dipakai</div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <h3 class="fw-bold mb-0 text-warning"><?= number_format($metrics['total_expired'] ?? 0) ?></h3>
              <i class="bi bi-hourglass-bottom fs-2 text-warning opacity-50"></i>
            </div>
            <small class="text-muted mt-2 d-block">Melewati batas waktu kedaluwarsa</small>
          </div>
        </div>

        <div class="col-md-3">
          <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white">
            <div class="text-muted small fw-semibold text-uppercase">Percobaan Salah</div>
            <div class="d-flex align-items-center justify-content-between mt-2">
              <h3 class="fw-bold mb-0 text-danger"><?= number_format($metrics['total_wrong_attempts'] ?? 0) ?></h3>
              <i class="bi bi-exclamation-octagon fs-2 text-danger opacity-50"></i>
            </div>
            <small class="text-muted mt-2 d-block">Pengiriman Terakhir: <?= !empty($metrics['last_sent_at']) ? date('d M Y H:i', strtotime($metrics['last_sent_at'])) : 'Belum Ada' ?></small>
          </div>
        </div>
      </div>

      <!-- Row 2: Recent Deliveries & Quick Test Delivery -->
      <div class="row g-3">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-3 bg-white">
            <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center border-bottom">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history text-primary-pink me-2"></i>Aktivitas Pengiriman OTP Terbaru</h6>
              <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                  <thead class="table-light">
                    <tr>
                      <th>Waktu</th>
                      <th>Tujuan (Disamarkan)</th>
                      <th>Tujuan</th>
                      <th>Provider</th>
                      <th>Pengiriman</th>
                      <th>Status Verifikasi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($metrics['recent_logs'])): ?>
                      <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                          <i class="bi bi-info-circle text-muted fs-4 d-block mb-1"></i>
                          Belum ada aktivitas pengiriman OTP. Lakukan pengujian di formulir samping!
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($metrics['recent_logs'] as $log): ?>
                        <tr>
                          <td><?= date('d M H:i:s', strtotime($log['created_at'])) ?></td>
                          <td><code><?= htmlspecialchars($log['destination_masked'] ?? '-') ?></code></td>
                          <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['purpose'] ?? '-') ?></span></td>
                          <td><?= htmlspecialchars($log['provider_name']) ?></td>
                          <td>
                            <?php if ($log['delivery_status'] === 'sent'): ?>
                              <span class="badge bg-success-subtle text-success">Terkirim</span>
                            <?php elseif ($log['delivery_status'] === 'simulated'): ?>
                              <span class="badge bg-warning-subtle text-dark border border-warning">Simulasi</span>
                            <?php else: ?>
                              <span class="badge bg-danger-subtle text-danger">Gagal</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php if ($log['verification_status'] === 'Terverifikasi'): ?>
                              <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Terverifikasi</span>
                            <?php elseif ($log['verification_status'] === 'Kedaluwarsa'): ?>
                              <span class="badge bg-secondary-subtle text-secondary">Kedaluwarsa</span>
                            <?php else: ?>
                              <span class="badge bg-info-subtle text-info">Menunggu</span>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card border-0 shadow-sm rounded-3 bg-white">
            <div class="card-header bg-transparent py-3 border-bottom">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-send text-primary-pink me-2"></i>Uji Pengiriman OTP Langsung</h6>
            </div>
            <div class="card-body">
              <p class="small text-muted mb-3">Kirimkan kode OTP uji coba untuk memverifikasi alur pembuatan token kriptografis, template, dan provider:</p>
              <form id="formTestDelivery" onsubmit="submitTestDelivery(event)">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="mb-3">
                  <label class="form-label small fw-semibold">Tujuan (Email atau No. Telepon / WhatsApp)</label>
                  <input type="text" name="destination" id="testDestInput" class="form-control form-control-sm" placeholder="Contoh: user@example.com atau 08123456789" required>
                </div>

                <div class="d-grid">
                  <button type="submit" class="btn btn-sm btn-primary-pink text-white rounded-pill">
                    <i class="bi bi-send-fill me-1"></i> Kirim Kode OTP Uji Coba
                  </button>
                </div>
                <div id="testDeliveryAlert" class="mt-3" style="display:none;"></div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================
         TAB 2: PENGATURAN OTP
         =================================================================== -->
    <div class="tab-pane fade" id="tab-otp-settings" role="tabpanel">
      <div class="card border-0 shadow-sm rounded-3 bg-white" style="max-width: 800px;">
        <div class="card-header bg-transparent py-3 border-bottom">
          <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-gear-wide-connected text-primary-pink me-2"></i>Parameter & Kebijakan Autentikasi OTP</h6>
          <small class="text-muted">Konfigurasi batas waktu kedaluwarsa, panjang karakter, dan kuota keamanan verifikasi.</small>
        </div>
        <div class="card-body">
          <form id="formSaveOtpSettings" onsubmit="submitSaveOtpSettings(event)">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="form-check form-switch p-3 border rounded-3 bg-light">
                  <input class="form-check-input" type="checkbox" name="otp_enabled" value="1" id="otpEnabledSwitch" <?= ($settings['otp_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold small" for="otpEnabledSwitch">
                    Aktifkan Autentikasi OTP
                    <span class="d-block text-muted fw-normal" style="font-size:11px;">Jika dimatikan, proses verifikasi OTP tidak akan diwajibkan.</span>
                  </label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check form-switch p-3 border rounded-3 bg-light">
                  <input class="form-check-input" type="checkbox" name="otp_test_mode" value="1" id="otpTestModeSwitch" <?= ($settings['otp_test_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                  <label class="form-check-label fw-semibold small" for="otpTestModeSwitch">
                    Mode Pengujian / Simulasi
                    <span class="d-block text-muted fw-normal" style="font-size:11px;">Catat ke log tanpa mengirimkan email/SMS sebenarnya ke luar.</span>
                  </label>
                </div>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Panjang Kode OTP (Digit)</label>
                <select name="otp_code_length" class="form-select form-select-sm">
                  <option value="4" <?= ($settings['otp_code_length'] ?? '6') === '4' ? 'selected' : '' ?>>4 Digit</option>
                  <option value="6" <?= ($settings['otp_code_length'] ?? '6') === '6' ? 'selected' : '' ?>>6 Digit (Standar Disarankan)</option>
                  <option value="8" <?= ($settings['otp_code_length'] ?? '6') === '8' ? 'selected' : '' ?>>8 Digit</option>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label small fw-semibold">Masa Berlaku OTP (Menit)</label>
                <input type="number" name="otp_expiry_minutes" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['otp_expiry_minutes'] ?? '5') ?>" min="1" max="30" required>
              </div>

              <div class="col-md-4">
                <label class="form-label small fw-semibold">Interval Kirim Ulang (Detik)</label>
                <input type="number" name="otp_resend_interval_seconds" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['otp_resend_interval_seconds'] ?? '60') ?>" min="15" max="300" required>
              </div>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-md-4">
                <label class="form-label small fw-semibold">Maks. Percobaan Verifikasi</label>
                <input type="number" name="otp_max_verify_attempts" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['otp_max_verify_attempts'] ?? '5') ?>" min="3" max="10" required>
                <small class="text-muted">Sebelum token otomatis dibatalkan.</small>
              </div>

              <div class="col-md-4">
                <label class="form-label small fw-semibold">Maks. Pengiriman per Akun</label>
                <input type="number" name="otp_max_send_per_account" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['otp_max_send_per_account'] ?? '5') ?>" min="3" max="20" required>
                <small class="text-muted">Dalam jendela 10 menit.</small>
              </div>

              <div class="col-md-4">
                <label class="form-label small fw-semibold">Maks. Pengiriman per IP</label>
                <input type="number" name="otp_max_send_per_ip" class="form-control form-control-sm" value="<?= htmlspecialchars($settings['otp_max_send_per_ip'] ?? '15') ?>" min="5" max="50" required>
                <small class="text-muted">Proteksi serangan DDoS/brute-force.</small>
              </div>
            </div>

            <div class="mb-4">
              <label class="form-label small fw-semibold">Waktu Blokir Lockout (Menit)</label>
              <input type="number" name="otp_lockout_duration_minutes" class="form-control form-control-sm" style="max-width: 250px;" value="<?= htmlspecialchars($settings['otp_lockout_duration_minutes'] ?? '15') ?>" min="5" max="120" required>
              <small class="text-muted">Durasi penguncian jika pengguna berulang kali melanggar batas percobaan.</small>
            </div>

            <div class="d-flex justify-content-end border-top pt-3">
              <button type="submit" class="btn btn-sm btn-primary-pink text-white px-4">
                <i class="bi bi-save me-1"></i> Simpan Pengaturan
              </button>
            </div>
            <div id="otpSettingsAlert" class="mt-3" style="display:none;"></div>
          </form>
        </div>
      </div>
    </div>

    <!-- ===================================================================
         TAB 3: TEMPLATE PESAN
         =================================================================== -->
    <div class="tab-pane fade" id="tab-otp-templates" role="tabpanel">
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-3 bg-white">
            <div class="card-header bg-transparent py-3 border-bottom">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-envelope-paper text-primary-pink me-2"></i>Editor Template Pesan OTP</h6>
            </div>
            <div class="card-body">
              <form id="formSaveTemplates" onsubmit="submitSaveTemplates(event)">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="mb-3">
                  <label class="form-label small fw-semibold">Template Email</label>
                  <textarea name="otp_template_email" id="emailTemplateArea" rows="5" class="form-control small font-monospace" oninput="updateTemplatePreview()" required><?= htmlspecialchars($settings['otp_template_email'] ?? "Halo!\n\nKode verifikasi nnasa_ca kamu adalah {{otp}}.\nKode berlaku selama {{expiry_minutes}} menit. Jangan bagikan kode ini kepada siapa pun.") ?></textarea>
                </div>

                <div class="mb-3">
                  <label class="form-label small fw-semibold">Template SMS / WhatsApp</label>
                  <textarea name="otp_template_sms" id="smsTemplateArea" rows="4" class="form-control small font-monospace" oninput="updateTemplatePreview()" required><?= htmlspecialchars($settings['otp_template_sms'] ?? "Kode verifikasi nnasa_ca: {{otp}}. Berlaku {{expiry_minutes}} menit. JANGAN BAGIKAN KODE INI.") ?></textarea>
                </div>

                <div class="p-2 border rounded bg-light mb-3">
                  <div class="fw-semibold small mb-1">Variabel Dinamis yang Diizinkan:</div>
                  <span class="badge bg-secondary me-1"><code>{{otp}}</code></span>
                  <span class="badge bg-secondary me-1"><code>{{expiry_minutes}}</code></span>
                  <span class="badge bg-secondary"><code>{{store_name}}</code></span>
                </div>

                <div class="d-flex justify-content-end border-top pt-3">
                  <button type="submit" class="btn btn-sm btn-primary-pink text-white px-4">
                    <i class="bi bi-save me-1"></i> Simpan Template
                  </button>
                </div>
                <div id="templateSaveAlert" class="mt-3" style="display:none;"></div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
            <div class="card-header bg-transparent py-3 border-bottom">
              <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-eye text-primary-pink me-2"></i>Pratinjau Hasil Pesan Pelanggan</h6>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <span class="fw-semibold small text-muted d-block mb-1">Pratinjau Email:</span>
                <div id="previewEmailBox" class="p-3 border rounded-3 bg-light font-monospace small" style="white-space: pre-wrap;"></div>
              </div>

              <div>
                <span class="fw-semibold small text-muted d-block mb-1">Pratinjau WhatsApp / SMS:</span>
                <div id="previewSmsBox" class="p-3 border rounded-3 bg-success-subtle text-success-emphasis font-monospace small" style="white-space: pre-wrap;"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================
         TAB 4: PROVIDER PENGIRIMAN
         =================================================================== -->
    <div class="tab-pane fade" id="tab-otp-providers" role="tabpanel">
      <div class="row g-4">
        <?php foreach ($providers as $prov): ?>
          <div class="col-lg-6">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100">
              <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center border-bottom">
                <div>
                  <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($prov['provider_name']) ?></h6>
                  <span class="badge bg-light text-dark border">Kanal: <?= strtoupper(htmlspecialchars($prov['channel'])) ?></span>
                </div>
                <div>
                  <?php if (!empty($prov['is_active'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success">Aktif</span>
                  <?php else: ?>
                    <span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="card-body">
                <form id="formOtpProv_<?= $prov['id'] ?>" onsubmit="submitOtpProvider(event, <?= $prov['id'] ?>)">
                  <input type="hidden" name="provider_id" value="<?= $prov['id'] ?>">
                  <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                  <input type="hidden" name="provider_name" value="<?= htmlspecialchars($prov['provider_name']) ?>">
                  <input type="hidden" name="channel" value="<?= htmlspecialchars($prov['channel']) ?>">

                  <div class="mb-3">
                    <label class="form-label small fw-semibold">Sender ID / Identitas Pengirim</label>
                    <input type="text" name="sender_identity" class="form-control form-control-sm" value="<?= htmlspecialchars($prov['sender_identity'] ?? '') ?>" placeholder="noreply@nnasa.ca atau NNASA_CA" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label small fw-semibold d-flex justify-content-between">
                      <span>Kredensial / API Key / Token</span>
                      <?php if ($prov['has_credentials']): ?>
                        <span class="text-success small"><i class="bi bi-shield-check"></i> Tersimpan: <?= htmlspecialchars($prov['credential_masked']) ?></span>
                      <?php else: ?>
                        <span class="text-danger small"><i class="bi bi-shield-x"></i> Belum Dikonfigurasi</span>
                      <?php endif; ?>
                    </label>
                    <input type="password" name="credentials" class="form-control form-control-sm" placeholder="<?= $prov['has_credentials'] ? 'Ketik untuk mengubah kunci' : 'Masukkan API Token resmi provider' ?>">
                  </div>

                  <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="provActiveSwitch_<?= $prov['id'] ?>" <?= !empty($prov['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label small fw-semibold" for="provActiveSwitch_<?= $prov['id'] ?>">Aktifkan Provider Ini</label>
                  </div>

                  <div class="d-flex justify-content-end border-top pt-2">
                    <button type="submit" class="btn btn-sm btn-primary-pink text-white">
                      <i class="bi bi-save me-1"></i> Simpan Provider
                    </button>
                  </div>
                  <div id="otpProvAlert_<?= $prov['id'] ?>" class="mt-2" style="display:none;"></div>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ===================================================================
         TAB 5: RIWAYAT & LOG PENGIRIMAN
         =================================================================== -->
    <div class="tab-pane fade" id="tab-otp-logs" role="tabpanel">
      <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-transparent py-3 border-bottom d-flex justify-content-between align-items-center">
          <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-journal-check text-primary-pink me-2"></i>Audit Log Pengiriman & Verifikasi OTP</h6>
          <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="loadOtpLogs()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="tableOtpLogs">
              <thead class="table-light">
                <tr>
                  <th>Waktu</th>
                  <th>Tujuan (Disamarkan)</th>
                  <th>Tujuan Verifikasi</th>
                  <th>Provider</th>
                  <th>Status Kirim</th>
                  <th>Status Verifikasi</th>
                  <th>Percobaan</th>
                </tr>
              </thead>
              <tbody id="otpLogsBody">
                <tr>
                  <td colspan="7" class="text-center py-4 text-muted">Memuat data log pengiriman OTP...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================================================================
         TAB 6: BATAS & KEAMANAN (LOCKOUT LIST)
         =================================================================== -->
    <div class="tab-pane fade" id="tab-otp-security" role="tabpanel">
      <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-transparent py-3 border-bottom d-flex justify-content-between align-items-center">
          <div>
            <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-slash text-danger me-2"></i>Daftar Akun / IP Terkunci Sementara (Rate Limit Lockout)</h6>
            <small class="text-muted">Subjek yang diblokir otomatis oleh sistem karena melewati batas percobaan salah.</small>
          </div>
          <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="loadSecurityLocks()"><i class="bi bi-arrow-clockwise me-1"></i> Refresh</button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
              <thead class="table-light">
                <tr>
                  <th>Subjek Hash</th>
                  <th>Tujuan</th>
                  <th>Jumlah Request</th>
                  <th>Diblokir Sampai</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody id="securityLocksBody">
                <tr>
                  <td colspan="5" class="text-center py-4 text-muted">Tidak ada akun atau IP yang sedang diblokir.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const ADMIN_API_OTP = '<?= BASE_URL ?>api/admin/otp.php';

// 1. Submit Test Delivery
function submitTestDelivery(e) {
  e.preventDefault();
  const alertBox = document.getElementById('testDeliveryAlert');
  alertBox.style.display = 'block';
  alertBox.className = 'alert alert-info py-2 small';
  alertBox.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirimkan kode OTP uji coba...';

  const formData = new FormData(e.target);
  formData.append('action', 'test_delivery');

  fetch(ADMIN_API_OTP, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    alertBox.className = data.success ? 'alert alert-success py-2 small' : 'alert alert-danger py-2 small';
    alertBox.innerHTML = (data.success ? '<i class="bi bi-check-circle me-1"></i> ' : '<i class="bi bi-x-circle me-1"></i> ') + data.message;
    if (data.success) {
      setTimeout(() => location.reload(), 1500);
    }
  })
  .catch(err => {
    alertBox.className = 'alert alert-danger py-2 small';
    alertBox.innerHTML = 'Gagal menghubungi server.';
  });
}

// 2. Save OTP Settings
function submitSaveOtpSettings(e) {
  e.preventDefault();
  const alertBox = document.getElementById('otpSettingsAlert');
  const formData = new FormData(e.target);
  formData.append('action', 'save_settings');

  fetch(ADMIN_API_OTP, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    alertBox.style.display = 'block';
    alertBox.className = data.success ? 'alert alert-success py-2 small' : 'alert alert-danger py-2 small';
    alertBox.innerHTML = data.message;
    if (data.success) {
      setTimeout(() => location.reload(), 1200);
    }
  });
}

// 3. Save Templates
function submitSaveTemplates(e) {
  e.preventDefault();
  const alertBox = document.getElementById('templateSaveAlert');
  const formData = new FormData(e.target);
  formData.append('action', 'save_templates');

  fetch(ADMIN_API_OTP, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    alertBox.style.display = 'block';
    alertBox.className = data.success ? 'alert alert-success py-2 small' : 'alert alert-danger py-2 small';
    alertBox.innerHTML = data.message;
  });
}

// 4. Submit Provider Config
function submitOtpProvider(e, provId) {
  e.preventDefault();
  const alertBox = document.getElementById('otpProvAlert_' + provId);
  const formData = new FormData(e.target);
  formData.append('action', 'save_provider');

  fetch(ADMIN_API_OTP, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    alertBox.style.display = 'block';
    alertBox.className = data.success ? 'alert alert-success py-2 small' : 'alert alert-danger py-2 small';
    alertBox.innerHTML = data.message;
    if (data.success) {
      setTimeout(() => location.reload(), 1200);
    }
  });
}

// 5. Update Template Preview
function updateTemplatePreview() {
  const emailTpl = document.getElementById('emailTemplateArea').value;
  const smsTpl = document.getElementById('smsTemplateArea').value;

  const sampleEmail = emailTpl.replace('{{otp}}', '849201').replace('{{expiry_minutes}}', '5').replace('{{store_name}}', 'nnasa_ca');
  const sampleSms = smsTpl.replace('{{otp}}', '849201').replace('{{expiry_minutes}}', '5').replace('{{store_name}}', 'nnasa_ca');

  document.getElementById('previewEmailBox').innerText = sampleEmail;
  document.getElementById('previewSmsBox').innerText = sampleSms;
}

// 6. Load OTP Logs
function loadOtpLogs() {
  const tbody = document.getElementById('otpLogsBody');
  tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Memuat log...</td></tr>';

  fetch(ADMIN_API_OTP + '?action=get_logs')
  .then(res => res.json())
  .then(data => {
    if (data.success && data.logs.length > 0) {
      let rows = '';
      data.logs.forEach(l => {
        const delivBadge = l.delivery_status === 'sent' 
          ? '<span class="badge bg-success-subtle text-success">Terkirim</span>' 
          : (l.delivery_status === 'simulated' ? '<span class="badge bg-warning-subtle text-dark border border-warning">Simulasi</span>' : '<span class="badge bg-danger-subtle text-danger">Gagal</span>');
        
        const verifBadge = l.verification_status === 'Terverifikasi'
          ? '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Terverifikasi</span>'
          : (l.verification_status === 'Kedaluwarsa' ? '<span class="badge bg-secondary-subtle text-secondary">Kedaluwarsa</span>' : '<span class="badge bg-info-subtle text-info">Menunggu</span>');

        rows += `
          <tr>
            <td>${l.created_at}</td>
            <td><code>${escapeHtml(l.destination_masked || '-')}</code></td>
            <td><span class="badge bg-light text-dark border">${escapeHtml(l.purpose || '-')}</span></td>
            <td>${escapeHtml(l.provider_name || '-')}</td>
            <td>${delivBadge}</td>
            <td>${verifBadge}</td>
            <td>${l.attempts || 0} / ${l.max_attempts || 5}</td>
          </tr>
        `;
      });
      tbody.innerHTML = rows;
    } else {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Belum ada riwayat pengiriman OTP.</td></tr>';
    }
  });
}

// 7. Load Security Locks
function loadSecurityLocks() {
  const tbody = document.getElementById('securityLocksBody');
  tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Memeriksa...</td></tr>';

  fetch(ADMIN_API_OTP + '?action=get_security_locks')
  .then(res => res.json())
  .then(data => {
    if (data.success && data.locks.length > 0) {
      let rows = '';
      data.locks.forEach(lk => {
        rows += `
          <tr>
            <td><code>${lk.subject_hash.substr(0, 16)}...</code></td>
            <td>${escapeHtml(lk.purpose)}</td>
            <td>${lk.request_count} kali</td>
            <td class="text-danger fw-semibold">${lk.blocked_until}</td>
            <td>
              <button class="btn btn-sm btn-outline-danger py-0 px-2" onclick="unblockSecurity(${lk.id})">
                Lepas Blokir
              </button>
            </td>
          </tr>
        `;
      });
      tbody.innerHTML = rows;
    } else {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">Tidak ada akun atau IP yang sedang diblokir.</td></tr>';
    }
  });
}

function unblockSecurity(id) {
  if (!confirm('Lepaskan blokir keamanan subjek ini?')) return;
  const formData = new FormData();
  formData.append('action', 'unblock_rate_limit');
  formData.append('id', id);
  formData.append('csrf_token', '<?= $csrfToken ?>');

  fetch(ADMIN_API_OTP, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message);
    loadSecurityLocks();
  });
}

function escapeHtml(text) {
  if (!text) return '';
  const map = {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'};
  return text.toString().replace(/[&<>"']/g, m => map[m]);
}

// Initial template preview update & listeners
updateTemplatePreview();
document.getElementById('tab-otp-logs-btn').addEventListener('shown.bs.tab', loadOtpLogs);
document.getElementById('tab-otp-security-btn').addEventListener('shown.bs.tab', loadSecurityLocks);
</script>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
