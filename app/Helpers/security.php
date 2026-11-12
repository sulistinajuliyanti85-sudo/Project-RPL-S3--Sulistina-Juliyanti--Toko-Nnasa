<?php
/**
 * Security and Encryption Helpers
 * Store: nnasa_ca Accessories Marketplace
 * 
 * Provides:
 * - Cryptographically secure AES-256-CBC encryption/decryption for API credentials
 * - Secret key masking (e.g. sk-****abcd)
 * - Safe destination masking (e.g. s***a@gmail.com, 0812****5678)
 * - Cryptographically secure OTP generation (random_int)
 * - Hash comparison resistant to timing attacks (hash_equals)
 */

if (!defined('ENCRYPTION_CIPHER')) {
    define('ENCRYPTION_CIPHER', 'AES-256-CBC');
}

/**
 * Get internal encryption key
 */
function get_app_secret_key(): string {
    $rawKey = defined('APP_KEY') ? APP_KEY : 'nnasa_ca_default_secure_key_2026_salt_token!';
    if (str_starts_with($rawKey, 'base64:')) {
        $rawKey = base64_decode(substr($rawKey, 7));
    }
    return hash('sha256', $rawKey, true); // 32 bytes binary
}

/**
 * Encrypt a plaintext secret
 */
function encrypt_secret(?string $plaintext): ?string {
    if ($plaintext === null || $plaintext === '') {
        return null;
    }

    $key = get_app_secret_key();
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $iv = openssl_random_pseudo_bytes($ivLength);

    $encrypted = openssl_encrypt($plaintext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return null;
    }

    // HMAC for integrity verification
    $mac = hash_hmac('sha256', $iv . $encrypted, $key, true);

    return 'enc:' . base64_encode($iv . $mac . $encrypted);
}

/**
 * Decrypt an encrypted secret
 */
function decrypt_secret(?string $encryptedPayload): ?string {
    if (empty($encryptedPayload)) {
        return null;
    }

    // If not prefixed with 'enc:', return as plaintext fallback
    if (!str_starts_with($encryptedPayload, 'enc:')) {
        return $encryptedPayload;
    }

    $raw = base64_decode(substr($encryptedPayload, 4), true);
    if ($raw === false) {
        return null;
    }

    $key = get_app_secret_key();
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_CIPHER);
    $macLength = 32; // sha256 output size

    if (strlen($raw) < $ivLength + $macLength) {
        return null;
    }

    $iv = substr($raw, 0, $ivLength);
    $mac = substr($raw, $ivLength, $macLength);
    $ciphertext = substr($raw, $ivLength + $macLength);

    $calculatedMac = hash_hmac('sha256', $iv . $ciphertext, $key, true);
    if (!hash_equals($mac, $calculatedMac)) {
        // HMAC verification failed
        return null;
    }

    $decrypted = openssl_decrypt($ciphertext, ENCRYPTION_CIPHER, $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : null;
}

/**
 * Mask an API key for safe display in UI
 * e.g. AQ.Ab8RN6K9...vDCXaOfig -> AQ.Ab****Ofig
 */
function mask_api_key(?string $key, int $prefix = 5, int $suffix = 4): string {
    if (empty($key)) {
        return '(Belum dikonfigurasi)';
    }

    $len = strlen($key);
    if ($len <= ($prefix + $suffix)) {
        return str_repeat('*', $len);
    }

    $start = substr($key, 0, $prefix);
    $end = substr($key, -$suffix);
    return $start . '••••••••' . $end;
}

/**
 * Mask an email or phone number
 * e.g. sulistina.admin@gmail.com -> su******in@gmail.com
 * e.g. 081234567890 -> 0812****7890
 */
function mask_destination(string $dest): string {
    $dest = trim($dest);
    if (filter_var($dest, FILTER_VALIDATE_EMAIL)) {
        $parts = explode('@', $dest);
        $user = $parts[0];
        $domain = $parts[1] ?? '';
        $userLen = strlen($user);

        if ($userLen <= 2) {
            $maskedUser = $user . '***';
        } else {
            $maskedUser = substr($user, 0, 2) . str_repeat('*', max(3, $userLen - 4)) . substr($user, -2);
        }
        return $maskedUser . '@' . $domain;
    }

    // Phone number
    $cleanPhone = preg_replace('/[^0-9]/', '', $dest);
    $phoneLen = strlen($cleanPhone);
    if ($phoneLen >= 8) {
        return substr($cleanPhone, 0, 4) . '****' . substr($cleanPhone, -4);
    }

    return substr($dest, 0, 2) . '****' . substr($dest, -2);
}

/**
 * Normalize and validate a phone number to international format (Indonesian 628...)
 * Returns normalized numeric string or null if invalid
 */
function normalize_phone_number(?string $phone): ?string {
    if (empty($phone)) return null;
    $raw = trim($phone);
    $digits = preg_replace('/[^0-9]/', '', $raw);

    if (empty($digits)) return null;

    // Indonesian normalization rules
    if (str_starts_with($digits, '08')) {
        $digits = '62' . substr($digits, 1);
    } elseif (str_starts_with($digits, '8') && strlen($digits) >= 9 && strlen($digits) <= 13) {
        $digits = '62' . $digits;
    } elseif (str_starts_with($digits, '6208')) {
        $digits = '62' . substr($digits, 3);
    }

    // Minimum 10 digits (e.g. 6281234567), maximum 15 digits (E.164 standard)
    $len = strlen($digits);
    if ($len < 10 || $len > 15) {
        return null;
    }

    return $digits;
}

/**
 * Generate cryptographically secure numeric OTP
 */
function generate_secure_otp(int $digits = 6): string {
    $min = 10 ** ($digits - 1);
    $max = (10 ** $digits) - 1;
    return (string)random_int($min, $max);
}

/**
 * Hash an OTP code with destination salt to prevent pre-computation attacks
 */
function hash_otp_code(string $otp, string $destination): string {
    $salt = get_app_secret_key();
    return hash_hmac('sha256', $otp . '|' . strtolower(trim($destination)), $salt);
}

/**
 * Verify an OTP code against stored hash
 */
function verify_otp_code(string $inputOtp, string $destination, string $storedHash): bool {
    $expectedHash = hash_otp_code($inputOtp, $destination);
    return hash_equals($storedHash, $expectedHash);
}
