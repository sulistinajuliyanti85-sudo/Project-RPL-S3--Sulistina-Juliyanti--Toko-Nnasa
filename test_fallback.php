<?php
/**
 * Script Pengujian Interaktif: Auto-Fallback Multi-Provider AI
 * Akses via Browser: http://localhost/toko/test_fallback.php
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';

$db = getDB();

// Ambil provider dari database
$providers = $db->query("SELECT id, display_name, provider_type, is_active FROM ai_providers ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$testResult = null;
$promptTest = $_POST['prompt'] ?? 'Halo! Berikan 1 rekomendasi aksesoris wanita singkat.';
$simulateFailure = isset($_POST['simulate_fail']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log = [];
    
    // Ambil semua provider aktif
    $stmt = $db->query("SELECT * FROM ai_providers WHERE is_active = 1 ORDER BY is_active DESC, id ASC");
    $activeList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $success = false;
    $finalReply = '';
    $usedProvider = '';
    $fallbackTriggered = false;

    foreach ($activeList as $index => $prov) {
        $name = $prov['display_name'];
        $type = strtolower($prov['provider_type']);
        $endpoint = rtrim($prov['api_endpoint'] ?? '', '/');
        
        // Dekripsi API Key jika terenkripsi
        $apiKey = $prov['credential_reference'] ?? ($prov['api_key'] ?? '');
        if (str_starts_with($apiKey, 'def50200') || strlen($apiKey) > 100) {
            require_once __DIR__ . '/app/Helpers/security.php';
            $apiKey = decrypt_credential($apiKey);
        }

        // SIMULASI GANGGUAN PADA PROVIDER PERTAMA (JIKA CENTANG AKTIF)
        if ($simulateFailure && $index === 0) {
            $log[] = [
                'status' => 'danger',
                'provider' => $name,
                'message' => "⚠️ [SIMULASI ERROR AKTIF] Provider utama sengaja digagalkan (Simulasi server down/kuota habis/HTTP 429)."
            ];
            $fallbackTriggered = true;
            continue; // Langsung loncat ke provider berikutnya!
        }

        $log[] = [
            'status' => 'info',
            'provider' => $name,
            'message' => "Mencoba koneksi ke [{$name}] dengan model {$prov['default_model']}..."
        ];

        // Eksekusi cURL
        $ch = curl_init();
        if ($type === 'gemini') {
            $url = "{$endpoint}/gemini-flash-lite-latest:generateContent?key=" . urlencode($apiKey);
            $payload = json_encode([
                'contents' => [['role' => 'user', 'parts' => [['text' => $promptTest]]]]
            ]);
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json']
            ]);
        } else {
            $url = !empty($endpoint) ? $endpoint : 'https://api.openai.com/v1/chat/completions';
            $payload = json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [['role' => 'user', 'content' => $promptTest]]
            ]);
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey
                ]
            ]);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            $errDetail = $curlErr ? $curlErr : "HTTP {$httpCode} (" . substr($res, 0, 100) . ")";
            $log[] = [
                'status' => 'warning',
                'provider' => $name,
                'message' => "❌ GAGAL: {$errDetail}. Beralih otomatis ke server cadangan..."
            ];
            $fallbackTriggered = true;
            continue; // Coba provider berikutnya
        }

        $resJson = json_decode($res, true);
        $reply = '';
        if ($type === 'gemini') {
            $reply = $resJson['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            $reply = $resJson['choices'][0]['message']['content'] ?? '';
        }

        if (!empty($reply)) {
            $log[] = [
                'status' => 'success',
                'provider' => $name,
                'message' => "✅ BERHASIL: Respon diterima dengan sukses dari [{$name}]!"
            ];
            $success = true;
            $finalReply = $reply;
            $usedProvider = $name;
            break; // Selesai!
        }
    }

    $testResult = [
        'success' => $success,
        'logs' => $log,
        'reply' => $finalReply,
        'provider' => $usedProvider,
        'fallback_used' => $fallbackTriggered
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Laboratorium Pengujian Fallback AI</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background-color: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
    .card { border-radius: 12px; }
  </style>
</head>
<body class="py-5">
  <div class="container" style="max-width: 800px;">
    
    <div class="text-center mb-4">
      <h3 class="fw-bold text-dark"><i class="bi bi-shield-check text-primary me-2"></i>Uji Coba Auto-Fallback Provider AI</h3>
      <p class="text-muted">Simulasikan kondisi server AI utama padam/gangguan dan lihat bagaimana server cadangan mengambil alih secara otomatis.</p>
    </div>

    <!-- Panel Kontrol Pengujian -->
    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body p-4">
        <form method="POST">
          <div class="mb-3">
            <label class="form-label fw-bold">Pesan Ujian (Prompt)</label>
            <input type="text" name="prompt" class="form-control form-control-lg" value="<?= htmlspecialchars($promptTest) ?>" required>
          </div>

          <div class="p-3 bg-warning-subtle border border-warning rounded-3 mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="simulate_fail" id="simFail" value="1" <?= $simulateFailure ? 'checked' : '' ?>>
              <label class="form-check-label fw-bold text-dark" for="simFail">
                Simulasikan Provider Utama Gangguan (Paksa Failover)
              </label>
            </div>
            <small class="text-muted d-block mt-1">
              Jika dicentang, sistem akan menganggap provider urutan pertama (Gemini) sedang mati/kuota habis, sehingga otomatis melempar prompt ke provider cadangan (OpenAI/lainnya).
            </small>
          </div>

          <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">
            <i class="bi bi-play-fill me-1"></i> Mulai Jalankan Pengujian
          </button>
        </form>
      </div>
    </div>

    <!-- Hasil Pengujian -->
    <?php if ($testResult): ?>
      <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white py-3">
          <h5 class="mb-0 fw-bold">
            <?php if ($testResult['success']): ?>
              <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i> Respon AI Berhasil Diterima</span>
              <?php if ($testResult['fallback_used']): ?>
                <span class="badge bg-warning text-dark ms-2"><i class="bi bi-arrow-repeat me-1"></i> Mode Fallback Berhasil Bekerja!</span>
              <?php else: ?>
                <span class="badge bg-success ms-2"><i class="bi bi-star-fill me-1"></i> Dijawab Provider Utama</span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-danger"><i class="bi bi-x-circle-fill me-1"></i> Pengujian Gagal</span>
            <?php endif; ?>
          </h5>
        </div>
        <div class="card-body p-4">
          
          <h6 class="fw-bold mb-3">Kronologi Alur Eksekusi (Log):</h6>
          <div class="list-group mb-4">
            <?php foreach ($testResult['logs'] as $l): ?>
              <div class="list-group-item list-group-item-<?= $l['status'] ?> py-2">
                <small class="fw-bold">[<?= htmlspecialchars($l['provider']) ?>]</small> <?= htmlspecialchars($l['message']) ?>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($testResult['success']): ?>
            <h6 class="fw-bold mb-2">Jawaban Akhir dari AI (Melalui <?= htmlspecialchars($testResult['provider']) ?>):</h6>
            <div class="p-3 bg-light rounded border text-dark">
              <?= nl2br(htmlspecialchars($testResult['reply'])) ?>
            </div>
          <?php endif; ?>

        </div>
      </div>
    <?php endif; ?>

    <div class="text-center">
      <a href="admin/ai-config.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Kembali ke Panel Konfigurasi AI</a>
    </div>

  </div>
</body>
</html>

