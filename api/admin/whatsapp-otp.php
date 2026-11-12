<?php
/**
 * Admin API: WhatsApp OTP Configuration & Testing Controller
 * Store: nnasa_ca Accessories Marketplace
 * Strictly handles authenticated admin requests with CSRF validation.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__, 2) . '/app/Config/config.php';
require_once dirname(__DIR__, 2) . '/app/Config/database.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/functions.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth.php';
require_once dirname(__DIR__, 2) . '/app/Models/OTPModel.php';
require_once dirname(__DIR__, 2) . '/app/Services/WhatsApp/WhatsAppService.php';

// Check Admin Authentication
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda harus login sebagai admin toko.']);
    exit;
}

$db = getDB();
$otpModel = new OTPModel($db);
$waService = new WhatsAppService($db);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        /* ---------------------------------------------------------
         * 1. GET DASHBOARD DATA & METRICS
         * --------------------------------------------------------- */
        case 'get_dashboard_data':
            $configInfo = $waService->getConfigInfo();

            // WhatsApp specific metrics from database
            $stmt = $db->query("
                SELECT 
                    COUNT(*) AS total_wa_deliveries,
                    SUM(CASE WHEN delivery_status IN ('sent', 'simulated') THEN 1 ELSE 0 END) AS total_wa_sent,
                    SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) AS total_wa_failed,
                    MAX(sent_at) AS last_wa_sent_at
                FROM otp_delivery_logs 
                WHERE provider_name LIKE 'wa_%'
            ");
            $delivStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Verification metrics for WhatsApp challenges (phone numbers)
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

            $recentLogs = $db->query("
                SELECT l.*, c.destination_masked, c.purpose, c.consumed_at, c.attempts, c.max_attempts, c.expires_at,
                       CASE 
                           WHEN c.consumed_at IS NOT NULL THEN 'Terverifikasi'
                           WHEN c.expires_at < NOW() THEN 'Kedaluwarsa'
                           ELSE 'Menunggu Verifikasi'
                       END AS verification_status
                FROM otp_delivery_logs l
                LEFT JOIN otp_challenges c ON l.challenge_id = c.id
                WHERE l.provider_name LIKE 'wa_%'
                ORDER BY l.id DESC LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'config' => $configInfo,
                'metrics' => array_merge($delivStats, $verifStats, ['recent_logs' => $recentLogs])
            ]);
            break;

        /* ---------------------------------------------------------
         * 2. TEST CONNECTION
         * --------------------------------------------------------- */
        case 'test_connection':
            $inputToken = trim($_POST['api_token'] ?? '');
            $inputUrl = trim($_POST['api_url'] ?? '');
            $providerType = trim($_POST['provider_type'] ?? '');

            // Use input token if provided and not masked; otherwise use loaded config token
            $tokenToUse = (!empty($inputToken) && !str_contains($inputToken, '••••') && !str_contains($inputToken, '****'))
                ? $inputToken
                : null;

            $testRes = $waService->testConnection($tokenToUse, !empty($inputUrl) ? $inputUrl : null, $providerType);
            echo json_encode($testRes);
            break;

        /* ---------------------------------------------------------
         * 3. TEST MESSAGE
         * --------------------------------------------------------- */
        case 'test_message':
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Sesi CSRF tidak valid. Silakan refresh halaman.']);
                exit;
            }

            $phone = trim($_POST['phone'] ?? '');
            $msg = trim($_POST['message'] ?? 'Tes pesan WhatsApp dari sistem e-commerce nnasa_ca. Verifikasi koneksi berhasil! 💕');
            $inputToken = trim($_POST['api_token'] ?? '');
            $inputUrl = trim($_POST['api_url'] ?? '');
            $providerType = trim($_POST['provider_type'] ?? '');

            $tokenToUse = (!empty($inputToken) && !str_contains($inputToken, '••••') && !str_contains($inputToken, '****'))
                ? $inputToken
                : null;

            $res = $waService->testMessage($phone, $msg, $tokenToUse, $inputUrl, $providerType);
            echo json_encode($res);
            break;

        /* ---------------------------------------------------------
         * 3B. DISABLE AUTOREAD (MATIKAN CENTANG BIRU OTOMATIS)
         * --------------------------------------------------------- */
        case 'disable_autoread':
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Sesi CSRF tidak valid.']);
                exit;
            }

            $enable = !empty($_POST['enable']) && $_POST['enable'] === '1';
            $inputToken = trim($_POST['api_token'] ?? '');
            $tokenToUse = (!empty($inputToken) && !str_contains($inputToken, '••••') && !str_contains($inputToken, '****'))
                ? $inputToken
                : null;

            $res = $waService->updateDeviceAutoread($enable, $tokenToUse);
            echo json_encode($res);
            break;

        /* ---------------------------------------------------------
         * 4. SAVE CONFIGURATION & SETTINGS
         * --------------------------------------------------------- */
        case 'save_settings':
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Sesi CSRF tidak valid.']);
                exit;
            }

            $providerType = strtolower(trim($_POST['provider_type'] ?? 'fonnte'));
            $apiUrl = trim($_POST['api_url'] ?? '');
            $senderId = trim($_POST['sender_id'] ?? '');
            $template = trim($_POST['template'] ?? '');
            $apiToken = trim($_POST['api_token'] ?? '');
            $timeout = (int)($_POST['timeout_seconds'] ?? 10);
            $isEnabled = !empty($_POST['whatsapp_otp_enabled']) ? '1' : '0';

            $settingsToSave = [
                'whatsapp_provider_type' => $providerType,
                'whatsapp_api_url' => $apiUrl,
                'whatsapp_sender_id' => $senderId,
                'whatsapp_otp_template' => $template,
                'whatsapp_timeout_seconds' => $timeout,
                'whatsapp_otp_enabled' => $isEnabled
            ];

            // If new raw token entered, encrypt and save
            if (!empty($apiToken) && !str_contains($apiToken, '••••') && !str_contains($apiToken, '****') && $apiToken !== 'ISI_TOKEN_BARU_DI_SERVER') {
                $encryptedToken = encrypt_secret($apiToken);
                $settingsToSave['whatsapp_api_token'] = $encryptedToken;

                // Also update in otp_providers table
                $stmtProv = $db->prepare("UPDATE otp_providers 
                                          SET provider_name = ?, credential_reference = ?, sender_identity = ?, is_active = ?, updated_at = NOW() 
                                          WHERE channel = 'whatsapp'");
                $stmtProv->execute(['wa_' . $providerType, $encryptedToken, $senderId, 1]);
            } else {
                $stmtProv = $db->prepare("UPDATE otp_providers 
                                          SET provider_name = ?, sender_identity = ?, is_active = ?, updated_at = NOW() 
                                          WHERE channel = 'whatsapp'");
                $stmtProv->execute(['wa_' . $providerType, $senderId, 1]);
            }

            // OTP parameter policies
            if (isset($_POST['otp_code_length'])) {
                $settingsToSave['otp_code_length'] = max(4, min(8, (int)$_POST['otp_code_length']));
            }
            if (isset($_POST['otp_expiry_minutes'])) {
                $settingsToSave['otp_expiry_minutes'] = max(1, min(30, (int)$_POST['otp_expiry_minutes']));
            }
            if (isset($_POST['otp_resend_interval_seconds'])) {
                $settingsToSave['otp_resend_interval_seconds'] = max(15, min(300, (int)$_POST['otp_resend_interval_seconds']));
            }
            if (isset($_POST['otp_max_verify_attempts'])) {
                $settingsToSave['otp_max_verify_attempts'] = max(3, min(10, (int)$_POST['otp_max_verify_attempts']));
            }
            if (isset($_POST['otp_max_send_per_account'])) {
                $settingsToSave['otp_max_send_per_account'] = max(3, min(20, (int)$_POST['otp_max_send_per_account']));
            }
            if (isset($_POST['otp_max_send_per_ip'])) {
                $settingsToSave['otp_max_send_per_ip'] = max(5, min(50, (int)$_POST['otp_max_send_per_ip']));
            }
            if (isset($_POST['otp_lockout_duration_minutes'])) {
                $settingsToSave['otp_lockout_duration_minutes'] = max(5, min(120, (int)$_POST['otp_lockout_duration_minutes']));
            }

            $otpModel->saveSettings($settingsToSave);

            echo json_encode([
                'success' => true,
                'message' => 'Konfigurasi WhatsApp OTP berhasil disimpan dengan aman! 💬🔒'
            ]);
            break;

        /* ---------------------------------------------------------
         * 5. GET WHATSAPP LOGS
         * --------------------------------------------------------- */
        case 'get_logs':
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $stmt = $db->prepare("
                SELECT l.*, c.destination_masked, c.purpose, c.consumed_at, c.attempts, c.max_attempts, c.expires_at,
                       CASE 
                           WHEN c.consumed_at IS NOT NULL THEN 'Terverifikasi'
                           WHEN c.expires_at < NOW() THEN 'Kedaluwarsa'
                           ELSE 'Menunggu Verifikasi'
                       END AS verification_status
                FROM otp_delivery_logs l
                LEFT JOIN otp_challenges c ON l.challenge_id = c.id
                WHERE l.provider_name LIKE 'wa_%'
                ORDER BY l.id DESC LIMIT ? OFFSET ?
            ");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
}
