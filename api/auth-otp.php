<?php
/**
 * Public Authentication API: Customer OTP Controller
 * Store: nnasa_ca Accessories Marketplace
 * Handles registration OTP, login verification, rate-limiting, and timing-attack-safe validation.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Services/OTPService.php';

$db = getDB();
$otpService = new OTPService($db);

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

try {
    switch ($action) {
        /* ---------------------------------------------------------
         * 1. REQUEST OTP CODE
         * --------------------------------------------------------- */
        case 'request':
            $destination = trim($input['destination'] ?? ($input['phone'] ?? ($input['email'] ?? '')));
            $purpose = in_array($input['purpose'] ?? '', ['register', 'login', 'password_reset', 'change_contact']) 
                ? $input['purpose'] 
                : 'register';

            if (empty($destination)) {
                echo json_encode(['success' => false, 'message' => 'Email atau nomor telepon wajib diisi.']);
                exit;
            }

            $currentUserId = is_logged_in() ? (int)current_user()['id'] : null;
            $res = $otpService->requestOTP($destination, $purpose, $currentUserId, $clientIp);

            echo json_encode($res);
            break;

        /* ---------------------------------------------------------
         * 2. VERIFY OTP CODE
         * --------------------------------------------------------- */
        case 'verify':
            $destination = trim($input['destination'] ?? ($input['phone'] ?? ($input['email'] ?? '')));
            $purpose = in_array($input['purpose'] ?? '', ['register', 'login', 'password_reset', 'change_contact']) 
                ? $input['purpose'] 
                : 'register';
            $code = trim($input['code'] ?? ($input['otp_code'] ?? ''));

            if (empty($destination) || empty($code)) {
                echo json_encode(['success' => false, 'message' => 'Alamat tujuan dan kode verifikasi wajib diisi.']);
                exit;
            }

            $res = $otpService->verifyOTP($destination, $purpose, $code);

            if ($res['success']) {
                // Set verified session token for flow completion (e.g. registration step)
                $_SESSION['otp_verified_flow'] = [
                    'destination' => $destination,
                    'purpose' => $purpose,
                    'verified_at' => time()
                ];
            }

            echo json_encode($res);
            break;

        /* ---------------------------------------------------------
         * 3. RESEND OTP CODE
         * --------------------------------------------------------- */
        case 'resend':
            $destination = trim($input['destination'] ?? ($input['phone'] ?? ($input['email'] ?? '')));
            $purpose = in_array($input['purpose'] ?? '', ['register', 'login', 'password_reset', 'change_contact']) 
                ? $input['purpose'] 
                : 'register';

            if (empty($destination)) {
                echo json_encode(['success' => false, 'message' => 'Email atau nomor telepon wajib diisi.']);
                exit;
            }

            $currentUserId = is_logged_in() ? (int)current_user()['id'] : null;
            $res = $otpService->requestOTP($destination, $purpose, $currentUserId, $clientIp);

            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Aksi OTP tidak dikenali.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan pada layanan autentikasi.']);
}
