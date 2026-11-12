<?php
/**
 * Admin API: OTP Configuration Controller
 * Store: nnasa_ca Accessories Marketplace
 * Strictly handles authenticated admin requests with CSRF validation.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__, 2) . '/app/Config/config.php';
require_once dirname(__DIR__, 2) . '/app/Config/database.php';
require_once dirname(__DIR__, 2) . '/app/Models/OTPModel.php';
require_once dirname(__DIR__, 2) . '/app/Services/OTPService.php';

// Check Admin Authentication
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak. Anda harus login sebagai admin.']);
    exit;
}

$db = getDB();
$otpModel = new OTPModel($db);
$otpService = new OTPService($db);

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    switch ($action) {
        /* ---------------------------------------------------------
         * 1. GET DASHBOARD METRICS
         * --------------------------------------------------------- */
        case 'get_dashboard_data':
            $metrics = $otpModel->getDashboardMetrics();
            echo json_encode(['success' => true, 'data' => $metrics]);
            break;

        /* ---------------------------------------------------------
         * 2. SAVE SETTINGS
         * --------------------------------------------------------- */
        case 'save_settings':
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Sesi CSRF tidak valid.']);
                exit;
            }

            $settings = [
                'otp_enabled' => !empty($_POST['otp_enabled']) ? '1' : '0',
                'otp_code_length' => max(4, min(8, (int)($_POST['otp_code_length'] ?? 6))),
                'otp_expiry_minutes' => max(1, min(30, (int)($_POST['otp_expiry_minutes'] ?? 5))),
                'otp_resend_interval_seconds' => max(15, min(300, (int)($_POST['otp_resend_interval_seconds'] ?? 60))),
                'otp_max_verify_attempts' => max(3, min(10, (int)($_POST['otp_max_verify_attempts'] ?? 5))),
                'otp_max_send_per_account' => max(3, min(20, (int)($_POST['otp_max_send_per_account'] ?? 5))),
                'otp_max_send_per_ip' => max(5, min(50, (int)($_POST['otp_max_send_per_ip'] ?? 15))),
                'otp_lockout_duration_minutes' => max(5, min(120, (int)($_POST['otp_lockout_duration_minutes'] ?? 15))),
                'otp_test_mode' => !empty($_POST['otp_test_mode']) ? '1' : '0'
            ];

            $otpModel->saveSettings($settings);
            echo json_encode(['success' => true, 'message' => 'Pengaturan OTP berhasil disimpan! 🔐']);
            break;

        /* ---------------------------------------------------------
         * 3. SAVE TEMPLATES
         * --------------------------------------------------------- */
        case 'save_templates':
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Sesi CSRF tidak valid.']);
                exit;
            }

            $emailTemplate = trim($_POST['otp_template_email'] ?? '');
            $smsTemplate = trim($_POST['otp_template_sms'] ?? '');

            if (empty($emailTemplate) || empty($smsTemplate)) {
                echo json_encode(['success' => false, 'message' => 'Template pesan email dan SMS/WA tidak boleh kosong.']);
                exit;
            }

            $otpModel->saveSettings([
                'otp_template_email' => $emailTemplate,
                'otp_template_sms' => $smsTemplate
            ]);

            echo json_encode(['success' => true, 'message' => 'Template pesan verifikasi berhasil disimpan!']);
            break;

        /* ---------------------------------------------------------
         * 4. SAVE PROVIDER CONFIGURATION
         * --------------------------------------------------------- */
        case 'save_provider':
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Sesi CSRF tidak valid.']);
                exit;
            }

            $id = (int)($_POST['provider_id'] ?? 0);
            $name = trim($_POST['provider_name'] ?? '');
            $channel = trim($_POST['channel'] ?? 'email');
            $sender = trim($_POST['sender_identity'] ?? '');
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            $cred = trim($_POST['credentials'] ?? '');

            $saveData = [
                'id' => $id,
                'provider_name' => $name,
                'channel' => $channel,
                'sender_identity' => $sender,
                'is_active' => $isActive
            ];

            if (!empty($cred) && !str_contains($cred, '••••') && !str_contains($cred, '****')) {
                $saveData['credentials'] = $cred;
            }

            $pId = $otpModel->saveProvider($saveData);
            echo json_encode(['success' => true, 'message' => 'Konfigurasi provider OTP berhasil diperbarui!', 'provider_id' => $pId]);
            break;

        /* ---------------------------------------------------------
         * 5. TEST DELIVERY
         * --------------------------------------------------------- */
        case 'test_delivery':
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Sesi CSRF tidak valid.']);
                exit;
            }

            $destination = trim($_POST['destination'] ?? '');
            if (empty($destination)) {
                echo json_encode(['success' => false, 'message' => 'Tujuan pengiriman uji coba wajib diisi.']);
                exit;
            }

            $adminUser = current_user();
            $res = $otpService->requestOTP($destination, 'test_delivery', $adminUser['id'] ?? null, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

            echo json_encode($res);
            break;

        /* ---------------------------------------------------------
         * 6. GET DELIVERY LOGS
         * --------------------------------------------------------- */
        case 'get_logs':
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $status = !empty($_GET['status']) ? $_GET['status'] : null;

            $logs = $otpModel->getDeliveryLogs($limit, $offset, $status);
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;

        /* ---------------------------------------------------------
         * 7. GET / UNBLOCK RATE LIMITS
         * --------------------------------------------------------- */
        case 'get_security_locks':
            $stmt = $db->query("SELECT * FROM otp_rate_limits WHERE blocked_until IS NOT NULL AND blocked_until > NOW() ORDER BY blocked_until DESC");
            $locks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'locks' => $locks]);
            break;

        case 'unblock_rate_limit':
            if (!csrf_verify($_POST['csrf_token'] ?? '')) {
                echo json_encode(['success' => false, 'message' => 'Sesi CSRF tidak valid.']);
                exit;
            }
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare("UPDATE otp_rate_limits SET blocked_until = NULL, request_count = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Blokir keamanan berhasil dilepas!']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server: ' . $e->getMessage()]);
}
