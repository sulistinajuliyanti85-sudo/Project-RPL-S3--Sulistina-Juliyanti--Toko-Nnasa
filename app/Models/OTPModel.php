<?php
/**
 * Model: OTPModel
 * Store: nnasa_ca Accessories Marketplace
 * Handles all database operations for OTP Challenges, Delivery Logs, Settings, Rate Limits, and Providers.
 */

require_once dirname(__DIR__) . '/Config/database.php';
require_once dirname(__DIR__) . '/Helpers/security.php';

class OTPModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? getDB();
    }

    /* =========================================================================
     * 1. OTP CHALLENGES
     * ========================================================================= */

    public function createChallenge(
        ?int $userId,
        string $destination,
        string $purpose,
        string $otpHash,
        int $expiryMinutes = 5,
        int $resendSeconds = 60,
        int $maxAttempts = 5
    ): int {
        $destHash = hash('sha256', strtolower(trim($destination)));
        $destMasked = mask_destination($destination);

        $now = time();
        $expiresAt = date('Y-m-d H:i:s', $now + ($expiryMinutes * 60));
        $resendAt = date('Y-m-d H:i:s', $now + $resendSeconds);

        $sql = "INSERT INTO otp_challenges (
                    user_id, destination_hash, destination_masked, purpose, otp_hash, 
                    expires_at, attempts, max_attempts, resend_available_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $userId,
            $destHash,
            $destMasked,
            $purpose,
            $otpHash,
            $expiresAt,
            $maxAttempts,
            $resendAt
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findLatestActiveChallenge(string $destination, string $purpose): ?array {
        $destHash = hash('sha256', strtolower(trim($destination)));
        $sql = "SELECT * FROM otp_challenges 
                WHERE destination_hash = ? AND purpose = ? AND consumed_at IS NULL
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$destHash, $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getChallengeById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM otp_challenges WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function incrementAttempts(int $id): int {
        $stmt = $this->db->prepare("UPDATE otp_challenges SET attempts = attempts + 1 WHERE id = ?");
        $stmt->execute([$id]);
        
        $stmt2 = $this->db->prepare("SELECT attempts FROM otp_challenges WHERE id = ?");
        $stmt2->execute([$id]);
        return (int)$stmt2->fetchColumn();
    }

    public function markConsumed(int $id): bool {
        $stmt = $this->db->prepare("UPDATE otp_challenges SET consumed_at = NOW() WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /* =========================================================================
     * 2. RATE LIMITING & SECURITY LOCKOUT
     * ========================================================================= */

    public function checkRateLimit(
        string $subject,
        string $purpose,
        int $maxRequests = 5,
        int $windowMinutes = 10,
        int $blockMinutes = 15
    ): array {
        $subjHash = hash('sha256', strtolower(trim($subject)));
        $stmt = $this->db->prepare("SELECT * FROM otp_rate_limits WHERE subject_hash = ? AND purpose = ? LIMIT 1");
        $stmt->execute([$subjHash, $purpose]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = time();

        if ($row) {
            // Check if blocked
            if (!empty($row['blocked_until'])) {
                $blockTime = strtotime($row['blocked_until']);
                if ($blockTime > $now) {
                    $waitSeconds = $blockTime - $now;
                    return [
                        'allowed' => false,
                        'wait_seconds' => $waitSeconds,
                        'reason' => "Terlalu banyak permintaan. Silakan tunggu {$waitSeconds} detik lagi sebelum mencoba kembali."
                    ];
                }
            }

            // Check window expiry
            $windowStart = strtotime($row['window_started_at']);
            if (($now - $windowStart) > ($windowMinutes * 60)) {
                // Window expired, reset window
                $update = $this->db->prepare("UPDATE otp_rate_limits 
                                              SET window_started_at = NOW(), request_count = 1, blocked_until = NULL, updated_at = NOW() 
                                              WHERE id = ?");
                $update->execute([$row['id']]);
                return ['allowed' => true, 'wait_seconds' => 0, 'reason' => ''];
            }

            // Within window, check request count
            if ($row['request_count'] >= $maxRequests) {
                // Trigger block
                $blockedUntil = date('Y-m-d H:i:s', $now + ($blockMinutes * 60));
                $update = $this->db->prepare("UPDATE otp_rate_limits 
                                              SET blocked_until = ?, updated_at = NOW() 
                                              WHERE id = ?");
                $update->execute([$blockedUntil, $row['id']]);
                return [
                    'allowed' => false,
                    'wait_seconds' => $blockMinutes * 60,
                    'reason' => "Batas permintaan terlampaui. Akun/IP sementara dikunci selama {$blockMinutes} menit untuk keamanan."
                ];
            }

            // Increment request count
            $update = $this->db->prepare("UPDATE otp_rate_limits 
                                          SET request_count = request_count + 1, updated_at = NOW() 
                                          WHERE id = ?");
            $update->execute([$row['id']]);
            return ['allowed' => true, 'wait_seconds' => 0, 'reason' => ''];
        } else {
            // First request for this subject
            $insert = $this->db->prepare("INSERT INTO otp_rate_limits (subject_hash, purpose, window_started_at, request_count, updated_at) 
                                          VALUES (?, ?, NOW(), 1, NOW())");
            $insert->execute([$subjHash, $purpose]);
            return ['allowed' => true, 'wait_seconds' => 0, 'reason' => ''];
        }
    }

    /* =========================================================================
     * 3. DELIVERY LOGS
     * ========================================================================= */

    public function logDelivery(array $data): int {
        $sql = "INSERT INTO otp_delivery_logs (
                    challenge_id, provider_name, delivery_status, provider_message_id, error_code, sent_at, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['challenge_id'] ?? null,
            $data['provider_name'] ?? 'unknown',
            $data['delivery_status'] ?? 'pending',
            $data['provider_message_id'] ?? null,
            $data['error_code'] ?? null
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function getDeliveryLogs(int $limit = 50, int $offset = 0, ?string $statusFilter = null): array {
        $sql = "SELECT l.*, c.destination_masked, c.purpose, c.consumed_at, c.attempts, c.max_attempts, c.expires_at,
                       CASE 
                           WHEN c.consumed_at IS NOT NULL THEN 'Terverifikasi'
                           WHEN c.expires_at < NOW() THEN 'Kedaluwarsa'
                           ELSE 'Menunggu Verifikasi'
                       END AS verification_status
                FROM otp_delivery_logs l
                LEFT JOIN otp_challenges c ON l.challenge_id = c.id";
        $params = [];
        if (!empty($statusFilter)) {
            $sql .= " WHERE l.delivery_status = ?";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY l.id DESC LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================================
     * 4. SETTINGS
     * ========================================================================= */

    public function getAllSettings(): array {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM otp_settings");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    }

    public function getSetting(string $key, $default = null) {
        $stmt = $this->db->prepare("SELECT setting_value FROM otp_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    }

    public function saveSettings(array $settings): bool {
        $stmt = $this->db->prepare("INSERT INTO otp_settings (setting_key, setting_value, updated_at) 
                                    VALUES (?, ?, NOW()) 
                                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, (string)$v]);
        }
        return true;
    }

    /* =========================================================================
     * 5. PROVIDERS
     * ========================================================================= */

    public function getAllProviders(): array {
        $stmt = $this->db->query("SELECT * FROM otp_providers ORDER BY is_active DESC, channel ASC, id ASC");
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($providers as &$p) {
            $rawCred = $this->decryptCredential($p['credential_reference'] ?? '');
            $p['has_credentials'] = !empty($rawCred);
            $p['credential_masked'] = !empty($rawCred) ? mask_api_key($rawCred) : 'Belum Dikonfigurasi';
            unset($p['credential_reference']);
        }
        return $providers;
    }

    public function getProviderById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM otp_providers WHERE id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) return null;

        $rawCred = $this->decryptCredential($p['credential_reference'] ?? '');
        $p['has_credentials'] = !empty($rawCred);
        $p['credential_masked'] = !empty($rawCred) ? mask_api_key($rawCred) : 'Belum Dikonfigurasi';
        unset($p['credential_reference']);
        return $p;
    }

    public function getActiveProviderForChannel(string $channel): ?array {
        $stmt = $this->db->prepare("SELECT * FROM otp_providers WHERE channel = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
        $stmt->execute([$channel]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) return null;

        $rawCred = $this->decryptCredential($p['credential_reference'] ?? '');
        $p['raw_credentials'] = $rawCred;
        return $p;
    }

    public function saveProvider(array $data): int {
        $id = !empty($data['id']) ? (int)$data['id'] : 0;
        $name = trim($data['provider_name'] ?? '');
        $channel = trim($data['channel'] ?? 'email');
        $sender = trim($data['sender_identity'] ?? '');
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $cred = $data['credentials'] ?? null;

        $credRef = null;
        if (!empty($cred) && !str_contains($cred, '••••') && !str_contains($cred, '****')) {
            $credRef = encrypt_secret(trim($cred));
        }

        if ($id > 0) {
            if ($credRef !== null) {
                $sql = "UPDATE otp_providers 
                        SET channel = ?, sender_identity = ?, credential_reference = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$channel, $sender, $credRef, $isActive, $id]);
            } else {
                $sql = "UPDATE otp_providers 
                        SET channel = ?, sender_identity = ?, is_active = ?, updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$channel, $sender, $isActive, $id]);
            }
            return $id;
        } else {
            $sql = "INSERT INTO otp_providers (provider_name, channel, credential_reference, sender_identity, is_active, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$name, $channel, $credRef, $sender, $isActive]);
            return (int)$this->db->lastInsertId();
        }
    }

    public function toggleProviderActive(int $id, bool $isActive): bool {
        $val = $isActive ? 1 : 0;
        $stmt = $this->db->prepare("UPDATE otp_providers SET is_active = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$val, $id]);
    }

    /* =========================================================================
     * 6. DASHBOARD METRICS
     * ========================================================================= */

    public function getDashboardMetrics(): array {
        $stmt = $this->db->query("SELECT 
            COUNT(*) AS total_challenges,
            SUM(CASE WHEN consumed_at IS NOT NULL THEN 1 ELSE 0 END) AS total_verified,
            SUM(CASE WHEN consumed_at IS NULL AND expires_at < NOW() THEN 1 ELSE 0 END) AS total_expired,
            SUM(attempts) AS total_wrong_attempts
        FROM otp_challenges");
        $challengeStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmtLogs = $this->db->query("SELECT 
            COUNT(*) AS total_deliveries,
            SUM(CASE WHEN delivery_status IN ('sent', 'simulated') THEN 1 ELSE 0 END) AS total_sent,
            SUM(CASE WHEN delivery_status = 'failed' THEN 1 ELSE 0 END) AS total_failed,
            MAX(sent_at) AS last_sent_at
        FROM otp_delivery_logs");
        $logStats = $stmtLogs->fetch(PDO::FETCH_ASSOC) ?: [];

        $activeProviders = $this->db->query("SELECT channel, provider_name, sender_identity FROM otp_providers WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

        $recentLogs = $this->getDeliveryLogs(5, 0);

        return array_merge($challengeStats, $logStats, [
            'active_providers' => $activeProviders,
            'recent_logs' => $recentLogs
        ]);
    }

    /* =========================================================================
     * INTERNAL HELPERS
     * ========================================================================= */

    private function decryptCredential(string $ref): ?string {
        if (empty($ref)) return null;
        if (str_starts_with($ref, 'enc:')) {
            return decrypt_secret($ref);
        }
        return $ref;
    }
}
