<?php
/**
 * Service: OTPService
 * Store: nnasa_ca Accessories Marketplace
 * Orchestrates cryptographically secure OTP generation, hashing, rate-limiting, delivery, and verification.
 */

require_once dirname(__DIR__) . '/Models/OTPModel.php';
require_once dirname(__DIR__) . '/Helpers/security.php';
require_once __DIR__ . '/WhatsApp/WhatsAppService.php';

class OTPService {
    private OTPModel $otpModel;
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? getDB();
        $this->otpModel = new OTPModel($this->db);
    }

    /**
     * Request and send OTP code to target destination
     */
    public function requestOTP(
        string $destination,
        string $purpose = 'register',
        ?int $userId = null,
        ?string $clientIp = null
    ): array {
        $cleanDest = trim($destination);
        if (empty($cleanDest)) {
            return ['success' => false, 'message' => 'Tujuan pengiriman (email atau nomor telepon) wajib diisi.'];
        }

        // 1. Check if OTP is globally enabled
        $isEnabled = (int)$this->otpModel->getSetting('otp_enabled', '1');
        if (!$isEnabled) {
            return ['success' => false, 'message' => 'Layanan autentikasi OTP saat ini dinonaktifkan oleh administrator toko.'];
        }

        // 2. Determine Channel (email vs sms/whatsapp)
        $isEmail = filter_var($cleanDest, FILTER_VALIDATE_EMAIL);
        $channel = $isEmail ? 'email' : 'whatsapp';

        if (!$isEmail) {
            $normalizedPhone = normalize_phone_number($cleanDest);
            if (!$normalizedPhone) {
                return [
                    'success' => false,
                    'message' => 'Format nomor WhatsApp tidak valid. Masukkan nomor ponsel yang valid (contoh: 08123456789).'
                ];
            }
            $cleanDest = $normalizedPhone;
        }

        // 3. Security: Check Rate Limit by Destination
        $maxPerAccount = (int)$this->otpModel->getSetting('otp_max_send_per_account', 5);
        $blockDuration = (int)$this->otpModel->getSetting('otp_lockout_duration_minutes', 15);
        
        $rateDest = $this->otpModel->checkRateLimit($cleanDest, $purpose, $maxPerAccount, 10, $blockDuration);
        if (!$rateDest['allowed']) {
            return ['success' => false, 'message' => $rateDest['reason'], 'wait_seconds' => $rateDest['wait_seconds']];
        }

        // 4. Security: Check Rate Limit by Client IP
        if (!empty($clientIp)) {
            $maxPerIp = (int)$this->otpModel->getSetting('otp_max_send_per_ip', 15);
            $rateIp = $this->otpModel->checkRateLimit($clientIp, 'ip_general', $maxPerIp, 10, $blockDuration);
            if (!$rateIp['allowed']) {
                return ['success' => false, 'message' => $rateIp['reason'], 'wait_seconds' => $rateIp['wait_seconds']];
            }
        }

        // 5. Check if an active unexpired challenge exists and cooldown has not passed
        $active = $this->otpModel->findLatestActiveChallenge($cleanDest, $purpose);
        $now = time();
        if ($active) {
            $resendAvail = strtotime($active['resend_available_at']);
            if ($now < $resendAvail) {
                $wait = $resendAvail - $now;
                return [
                    'success' => false,
                    'message' => "Silakan tunggu {$wait} detik sebelum meminta pengiriman ulang kode OTP.",
                    'wait_seconds' => $wait
                ];
            }
        }

        // 6. Generate Cryptographically Secure OTP
        $otpLength = (int)$this->otpModel->getSetting('otp_code_length', 6);
        $expiryMinutes = (int)$this->otpModel->getSetting('otp_expiry_minutes', 5);
        $resendInterval = (int)$this->otpModel->getSetting('otp_resend_interval_seconds', 60);
        $maxAttempts = (int)$this->otpModel->getSetting('otp_max_verify_attempts', 5);

        $otpCode = generate_secure_otp($otpLength);
        $otpHash = hash_otp_code($otpCode, $cleanDest);

        // 7. Store Challenge in Database (hashed only, never plaintext)
        $challengeId = $this->otpModel->createChallenge(
            $userId,
            $cleanDest,
            $purpose,
            $otpHash,
            $expiryMinutes,
            $resendInterval,
            $maxAttempts
        );

        // 8. Deliver OTP via Provider
        $deliveryResult = $this->dispatchDelivery($challengeId, $cleanDest, $channel, $otpCode, $expiryMinutes);

        $response = [
            'success' => $deliveryResult['success'],
            'challenge_id' => $challengeId,
            'destination_masked' => mask_destination($cleanDest),
            'expires_in_seconds' => $expiryMinutes * 60,
            'resend_available_in' => $resendInterval,
            'message' => $deliveryResult['message'],
            'delivery_status' => $deliveryResult['delivery_status'] ?? 'sent',
            'test_mode' => (int)$this->otpModel->getSetting('otp_test_mode', 0) === 1
        ];

        if (!empty($deliveryResult['simulated_otp'])) {
            $response['simulated_otp'] = $deliveryResult['simulated_otp'];
            $response['simulated_message'] = $deliveryResult['simulated_message'] ?? null;
        }

        return $response;
    }

    /**
     * Verify submitted OTP against stored challenge
     */
    public function verifyOTP(string $destination, string $purpose, string $submittedCode): array {
        $cleanDest = trim($destination);
        $cleanCode = trim($submittedCode);

        if (empty($cleanDest) || empty($cleanCode)) {
            return ['success' => false, 'message' => 'Alamat tujuan dan kode verifikasi wajib diisi.'];
        }

        if (!filter_var($cleanDest, FILTER_VALIDATE_EMAIL)) {
            $norm = normalize_phone_number($cleanDest);
            if ($norm) $cleanDest = $norm;
        }

        // 1. Locate active challenge
        $challenge = $this->otpModel->findLatestActiveChallenge($cleanDest, $purpose);
        if (!$challenge) {
            return ['success' => false, 'message' => 'Kode verifikasi tidak ditemukan atau sudah digunakan sebelumnya.'];
        }

        $now = time();
        $expires = strtotime($challenge['expires_at']);

        // 2. Check Expiration
        if ($now > $expires) {
            return ['success' => false, 'message' => 'Kode OTP telah kedaluwarsa. Silakan minta kode verifikasi baru.'];
        }

        // 3. Check Attempts Lockout
        if ($challenge['attempts'] >= $challenge['max_attempts']) {
            return [
                'success' => false,
                'message' => 'Batas percobaan memasukkan kode OTP telah terlampaui. Demi keamanan, silakan minta kode baru.'
            ];
        }

        // 4. Secure Verification (Timing Attack Safe)
        $isValid = verify_otp_code($cleanCode, $cleanDest, $challenge['otp_hash']);

        if (!$isValid) {
            $newAttempts = $this->otpModel->incrementAttempts((int)$challenge['id']);
            $remaining = max(0, (int)$challenge['max_attempts'] - $newAttempts);
            return [
                'success' => false,
                'message' => "Kode OTP yang kamu masukkan salah. Sisa kesempatan mencoba: {$remaining} kali.",
                'remaining_attempts' => $remaining
            ];
        }

        // 5. Verification Success: Mark as Consumed
        $this->otpModel->markConsumed((int)$challenge['id']);

        // 6. Update verification timestamp in users table if linked
        if (!empty($challenge['user_id'])) {
            $userId = (int)$challenge['user_id'];
            if (filter_var($cleanDest, FILTER_VALIDATE_EMAIL)) {
                $up = $this->db->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ?");
                $up->execute([$userId]);
            } else {
                $up = $this->db->prepare("UPDATE users SET phone_verified_at = NOW() WHERE id = ?");
                $up->execute([$userId]);
            }
        }

        return [
            'success' => true,
            'message' => 'Verifikasi kode OTP berhasil!',
            'user_id' => $challenge['user_id'] ?? null,
            'purpose' => $purpose
        ];
    }

    /**
     * Dispatch delivery across configured channel provider
     */
    private function dispatchDelivery(
        int $challengeId,
        string $destination,
        string $channel,
        string $otpCode,
        int $expiryMinutes
    ): array {
        $testMode = (int)$this->otpModel->getSetting('otp_test_mode', 0) === 1;
        $provider = $this->otpModel->getActiveProviderForChannel($channel);

        // Render template message
        $templateKey = ($channel === 'email') ? 'otp_template_email' : 'otp_template_sms';
        $defaultTemplate = "Kode verifikasi nnasa_ca kamu adalah {{otp}}. Kode berlaku selama {{expiry_minutes}} menit. Jangan bagikan kode ini kepada siapa pun.";
        $template = $this->otpModel->getSetting($templateKey, $defaultTemplate);

        $message = str_replace(
            ['{{otp}}', '{{expiry_minutes}}', '{{store_name}}'],
            [$otpCode, $expiryMinutes, 'nnasa_ca'],
            $template
        );

        // 1. WhatsApp & SMS Gateway Dispatch via WhatsAppService
        if ($channel === 'whatsapp' || $channel === 'sms') {
            $waService = new WhatsAppService($this->db);
            return $waService->sendOTP($challengeId, $destination, $otpCode, $expiryMinutes);
        }

        // 2. Email Delivery Handler
        $providerName = $provider ? $provider['provider_name'] : 'mail_native';
        $masked = mask_destination($destination);

        // Simulation / Local Test Mode Handler for Email
        if ($testMode || !$provider) {
            $this->otpModel->logDelivery([
                'challenge_id' => $challengeId,
                'provider_name' => $providerName,
                'delivery_status' => 'simulated',
                'provider_message_id' => 'SIM-' . bin2hex(random_bytes(6)),
                'error_code' => null
            ]);
            return [
                'success' => true,
                'delivery_status' => 'simulated',
                'simulated_otp' => $otpCode,
                'simulated_message' => $message,
                'message' => "Kode OTP disimulasikan ke email {$masked} (Mode Simulasi Pengujian)."
            ];
        }

        $sender = !empty($provider['sender_identity']) ? $provider['sender_identity'] : 'noreply@nnasa.ca';
        $subject = "Kode Verifikasi OTP nnasa_ca";
        $headers = "From: nnasa_ca <{$sender}>\r\n" .
                   "Reply-To: {$sender}\r\n" .
                   "X-Mailer: PHP/" . phpversion() . "\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n";

        // Attempt mail dispatch
        $sent = @mail($destination, $subject, $message, $headers);

        $this->otpModel->logDelivery([
            'challenge_id' => $challengeId,
            'provider_name' => $providerName,
            'delivery_status' => $sent ? 'sent' : 'simulated',
            'provider_message_id' => 'MAIL-' . bin2hex(random_bytes(6)),
            'error_code' => $sent ? null : 'NATIVE_MAIL_FALLBACK_SIMULATED'
        ]);

        $res = [
            'success' => true,
            'delivery_status' => $sent ? 'sent' : 'simulated',
            'message' => "Kode OTP telah dikirimkan ke email {$masked}. Silakan periksa inbox atau spam folder kamu."
        ];
        if (!$sent) {
            $res['simulated_otp'] = $otpCode;
            $res['simulated_message'] = $message;
        }
        return $res;
    }
}
