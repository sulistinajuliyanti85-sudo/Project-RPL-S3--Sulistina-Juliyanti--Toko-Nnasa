<?php
/**
 * Public Authentication API: WhatsApp OTP Controller
 * Store: nnasa_ca Accessories Marketplace
 * Senior Backend & Cybersecurity Implementation
 *
 * Endpoints:
 * - POST /api/auth/whatsapp-otp/request (action=request)
 * - POST /api/auth/whatsapp-otp/verify  (action=verify)
 * - POST /api/auth/whatsapp-otp/resend  (action=resend)
 */

header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';
require_once dirname(__DIR__) . '/app/Helpers/security.php';
require_once dirname(__DIR__) . '/app/Models/OTPModel.php';
require_once dirname(__DIR__) . '/app/Services/OTPService.php';

$db = getDB();
$otpModel = new OTPModel($db);
$otpService = new OTPService($db);

// Parse input payload (handles application/json or application/x-www-form-urlencoded)
$input = $_POST;
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$action = $_GET['action'] ?? ($input['action'] ?? '');
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Supported Authentication Purposes
$validPurposes = [
    'register',
    'login_whatsapp',
    'phone_verify',
    'password_reset',
    'change_contact'
];

try {
    switch ($action) {
        /* =================================================================
         * 1. REQUEST WHATSAPP OTP
         * ================================================================= */
        case 'request':
            $rawPhone = trim($input['phone'] ?? ($input['whatsapp_number'] ?? ($input['destination'] ?? '')));
            $purpose = strtolower(trim($input['purpose'] ?? 'phone_verify'));

            if (!in_array($purpose, $validPurposes)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tujuan verifikasi OTP (purpose) tidak valid.']);
                exit;
            }

            if (empty($rawPhone)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp wajib diisi.']);
                exit;
            }

            // Normalisasi nomor telepon ke standar internasional (+62)
            $normalizedPhone = normalize_phone_number($rawPhone);
            if (!$normalizedPhone) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Format nomor WhatsApp tidak valid. Gunakan format standar Indonesia (contoh: 08123456789 atau 628123456789).'
                ]);
                exit;
            }

            // Keamanan Akun Spesifik per Tujuan
            $targetUserId = null;

            if ($purpose === 'phone_verify') {
                if (!is_logged_in()) {
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Anda harus login untuk memverifikasi nomor telepon akun.']);
                    exit;
                }
                $targetUserId = (int)$_SESSION['user_id'];

                // Periksa apakah nomor sudah digunakan oleh akun lain
                $stmtCheck = $db->prepare("SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1");
                $stmtCheck->execute([$normalizedPhone, $targetUserId]);
                if ($stmtCheck->fetch()) {
                    http_response_code(409);
                    echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp ini sudah digunakan oleh akun pelanggan lain.']);
                    exit;
                }
            } elseif ($purpose === 'register') {
                // Periksa apakah nomor sudah terdaftar & aktif
                $stmtCheck = $db->prepare("SELECT id FROM users WHERE phone = ? LIMIT 1");
                $stmtCheck->execute([$normalizedPhone]);
                if ($stmtCheck->fetch()) {
                    http_response_code(409);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Nomor WhatsApp ini sudah terdaftar. Silakan login atau gunakan nomor lain.'
                    ]);
                    exit;
                }
            } elseif ($purpose === 'login_whatsapp') {
                // Cari pengguna dengan nomor WhatsApp tersebut
                $stmtUser = $db->prepare("SELECT id, name, status FROM users WHERE phone = ? LIMIT 1");
                $stmtUser->execute([$normalizedPhone]);
                $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    // Fallback check: user might have registered with 08xx instead of 628xx
                    $localPhone = '0' . substr($normalizedPhone, 2);
                    $stmtUser2 = $db->prepare("SELECT id, name, status FROM users WHERE phone = ? LIMIT 1");
                    $stmtUser2->execute([$localPhone]);
                    $user = $stmtUser2->fetch(PDO::FETCH_ASSOC);
                }

                if (!$user) {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Nomor WhatsApp belum terdaftar di toko nnasa_ca. Silakan daftar akun baru terlebih dahulu.'
                    ]);
                    exit;
                }

                if ($user['status'] !== 'active') {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Akun Anda sedang dinonaktifkan atau diblokir. Silakan hubungi Customer Care.'
                    ]);
                    exit;
                }

                $targetUserId = (int)$user['id'];
            } elseif ($purpose === 'password_reset') {
                // Rule: Jangan ungkapkan apakah nomor terdaftar (Anti-Enumeration)
                $stmtUser = $db->prepare("SELECT id, name, status FROM users WHERE phone = ? LIMIT 1");
                $stmtUser->execute([$normalizedPhone]);
                $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($user && $user['status'] === 'active') {
                    $targetUserId = (int)$user['id'];
                } else {
                    // Nomor tidak ditemukan: tetap kembalikan respons sukses tersamar untuk keamanan
                    $masked = mask_destination($normalizedPhone);
                    echo json_encode([
                        'success' => true,
                        'message' => "Jika nomor terdaftar di sistem, kode verifikasi OTP telah dikirimkan ke WhatsApp {$masked}.",
                        'destination_masked' => $masked,
                        'resend_available_in' => 60,
                        'expires_in_seconds' => 300
                    ]);
                    exit;
                }
            }

            // Panggil Service OTP
            $reqRes = $otpService->requestOTP($normalizedPhone, $purpose, $targetUserId, $clientIp);

            if (!$reqRes['success']) {
                $httpCode = (!empty($reqRes['wait_seconds'])) ? 429 : 400;
                http_response_code($httpCode);
                echo json_encode($reqRes);
                exit;
            }

            // Simpan konteks sementara di sesi untuk keamanan alur registrasi/login
            $_SESSION['wa_active_phone'] = $normalizedPhone;
            $_SESSION['wa_active_purpose'] = $purpose;

            $resp = [
                'success' => true,
                'message' => $reqRes['message'],
                'destination_masked' => $reqRes['destination_masked'],
                'resend_available_in' => $reqRes['resend_available_in'],
                'expires_in_seconds' => $reqRes['expires_in_seconds'],
                'delivery_status' => $reqRes['delivery_status'] ?? 'sent'
            ];
            if (!empty($reqRes['simulated_otp'])) {
                $resp['simulated_otp'] = $reqRes['simulated_otp'];
                $resp['simulated_message'] = $reqRes['simulated_message'] ?? null;
            }
            echo json_encode($resp);
            break;

        /* =================================================================
         * 2. VERIFY WHATSAPP OTP
         * ================================================================= */
        case 'verify':
            $rawPhone = trim($input['phone'] ?? ($input['whatsapp_number'] ?? ($input['destination'] ?? '')));
            $otpCode = trim($input['otp_code'] ?? ($input['code'] ?? ''));
            $purpose = strtolower(trim($input['purpose'] ?? 'phone_verify'));

            if (empty($rawPhone) || empty($otpCode)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp dan 6 digit kode OTP wajib diisi.']);
                exit;
            }

            $normalizedPhone = normalize_phone_number($rawPhone);
            if (!$normalizedPhone) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Format nomor WhatsApp tidak valid.']);
                exit;
            }

            // Eksekusi verifikasi OTP di backend
            $verifRes = $otpService->verifyOTP($normalizedPhone, $purpose, $otpCode);

            if (!$verifRes['success']) {
                http_response_code(400);
                echo json_encode($verifRes);
                exit;
            }

            // Proses Pasca-Verifikasi Berdasarkan Purpose
            $responsePayload = [
                'success' => true,
                'message' => 'Kode verifikasi WhatsApp valid! ✅'
            ];

            if ($purpose === 'phone_verify') {
                if (is_logged_in()) {
                    $userId = (int)$_SESSION['user_id'];
                    $stmtUp = $db->prepare("UPDATE users SET phone = ?, phone_verified_at = NOW(), updated_at = NOW() WHERE id = ?");
                    $stmtUp->execute([$normalizedPhone, $userId]);
                    log_activity($userId, 'verify_phone', 'users', $userId, 'Nomor WhatsApp akun berhasil diverifikasi');
                    $responsePayload['message'] = 'Nomor WhatsApp Anda berhasil diverifikasi dan disimpan! 💕';
                }
            } elseif ($purpose === 'login_whatsapp') {
                // Autentikasi Pengguna
                $stmt = $db->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
                $stmt->execute([$normalizedPhone]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $localPhone = '0' . substr($normalizedPhone, 2);
                    $stmt2 = $db->prepare("SELECT * FROM users WHERE phone = ? LIMIT 1");
                    $stmt2->execute([$localPhone]);
                    $user = $stmt2->fetch(PDO::FETCH_ASSOC);
                }

                if ($user) {
                    // Update phone_verified_at jika belum ada
                    $db->prepare("UPDATE users SET phone = ?, phone_verified_at = COALESCE(phone_verified_at, NOW()) WHERE id = ?")
                       ->execute([$normalizedPhone, $user['id']]);

                    // Login Sesi
                    login_user($user);
                    
                    $redirectUrl = BASE_URL . 'index.php';
                    if ($user['role'] === 'admin') {
                        $redirectUrl = ADMIN_URL . 'index.php';
                    } elseif (!empty($_SESSION['redirect_after_login'])) {
                        $redirectUrl = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                    }

                    $responsePayload['message'] = 'Login berhasil via WhatsApp OTP! Selamat datang kembali, ' . htmlspecialchars($user['name']) . ' ✨';
                    $responsePayload['redirect'] = $redirectUrl;
                    $responsePayload['user'] = [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ];
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Akun tidak ditemukan. Silakan registrasi terlebih dahulu.']);
                    exit;
                }
            } elseif ($purpose === 'register') {
                // Simpan token verifikasi di sesi agar registrasi form bisa dilanjutkan
                $_SESSION['verified_whatsapp_phone'] = $normalizedPhone;
                $_SESSION['whatsapp_phone_verified_at'] = time();
                $responsePayload['message'] = 'Nomor WhatsApp berhasil diverifikasi! Silakan lanjutkan pembuatan akun Anda.';
            } elseif ($purpose === 'password_reset') {
                $stmt = $db->prepare("SELECT id, name FROM users WHERE phone = ? LIMIT 1");
                $stmt->execute([$normalizedPhone]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $resetToken = bin2hex(random_bytes(32));
                    $_SESSION['wa_pwd_reset_token'] = $resetToken;
                    $_SESSION['wa_pwd_reset_user_id'] = $user['id'];
                    $_SESSION['wa_pwd_reset_expires'] = time() + 900; // 15 menit
                    $responsePayload['redirect'] = BASE_URL . 'reset-password.php?token=' . $resetToken;
                    $responsePayload['message'] = 'Verifikasi berhasil. Silakan buat password baru Anda.';
                }
            }

            echo json_encode($responsePayload);
            break;

        /* =================================================================
         * 3. RESEND WHATSAPP OTP
         * ================================================================= */
        case 'resend':
            $rawPhone = trim($input['phone'] ?? ($input['whatsapp_number'] ?? ($input['destination'] ?? '')));
            $purpose = strtolower(trim($input['purpose'] ?? 'phone_verify'));

            if (empty($rawPhone)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nomor WhatsApp wajib diisi untuk kirim ulang.']);
                exit;
            }

            $normalizedPhone = normalize_phone_number($rawPhone);
            if (!$normalizedPhone) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'Format nomor WhatsApp tidak valid.']);
                exit;
            }

            $targetUserId = is_logged_in() ? (int)$_SESSION['user_id'] : null;

            // Panggil requestOTP yang otomatis memeriksa interval kirim ulang (cooldown) & rate limit
            $resendRes = $otpService->requestOTP($normalizedPhone, $purpose, $targetUserId, $clientIp);

            if (!$resendRes['success']) {
                $httpCode = (!empty($resendRes['wait_seconds'])) ? 429 : 400;
                http_response_code($httpCode);
                echo json_encode($resendRes);
                exit;
            }

            $resendResp = [
                'success' => true,
                'message' => 'Kode OTP baru telah dikirimkan ulang ke nomor WhatsApp Anda.',
                'destination_masked' => $resendRes['destination_masked'],
                'resend_available_in' => $resendRes['resend_available_in'],
                'expires_in_seconds' => $resendRes['expires_in_seconds'],
                'delivery_status' => $resendRes['delivery_status'] ?? 'sent'
            ];
            if (!empty($resendRes['simulated_otp'])) {
                $resendResp['simulated_otp'] = $resendRes['simulated_otp'];
                $resendResp['simulated_message'] = $resendRes['simulated_message'] ?? null;
            }
            echo json_encode($resendResp);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action tidak dikenali.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem saat memproses verifikasi WhatsApp: ' . $e->getMessage()
    ]);
}
