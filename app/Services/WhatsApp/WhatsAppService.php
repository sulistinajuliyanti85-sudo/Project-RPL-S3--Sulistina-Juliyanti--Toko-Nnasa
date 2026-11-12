<?php
/**
 * WhatsApp Gateway Service Facade
 * Store: nnasa_ca Accessories Marketplace
 * Orchestrates token management, adapter factory, phone normalization, and audit delivery logging.
 */

require_once __DIR__ . '/WhatsAppProviderInterface.php';
require_once __DIR__ . '/FonnteAdapter.php';
require_once __DIR__ . '/WablasAdapter.php';
require_once __DIR__ . '/MetaCloudWhatsAppAdapter.php';
require_once __DIR__ . '/GenericWhatsAppAdapter.php';
require_once dirname(__DIR__, 2) . '/Helpers/security.php';

class WhatsAppService {
    private ?PDO $db;
    private array $config = [];

    public function __construct(?PDO $db = null) {
        $this->db = $db;
        $this->loadConfiguration();
    }

    /**
     * Load WhatsApp configuration combining .env and database settings
     */
    public function loadConfiguration(): void {
        // 1. Read from Environment variables (.env)
        $envToken = getenv('WHATSAPP_API_TOKEN') ?: ($_ENV['WHATSAPP_API_TOKEN'] ?? null);
        $envUrl = getenv('WHATSAPP_API_URL') ?: ($_ENV['WHATSAPP_API_URL'] ?? null);
        $envSender = getenv('WHATSAPP_SENDER_ID') ?: ($_ENV['WHATSAPP_SENDER_ID'] ?? null);
        $envProvider = getenv('WHATSAPP_PROVIDER') ?: ($_ENV['WHATSAPP_PROVIDER'] ?? 'fonnte');
        $envTemplate = getenv('WHATSAPP_OTP_TEMPLATE') ?: ($_ENV['WHATSAPP_OTP_TEMPLATE'] ?? null);

        // 2. Read from Database settings as secondary fallback
        $dbSettings = [];
        $dbProvider = null;
        if ($this->db) {
            try {
                $stmt = $this->db->query("SELECT setting_key, setting_value FROM otp_settings WHERE setting_key LIKE 'whatsapp_%'");
                $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

                $stmtProv = $this->db->query("SELECT * FROM otp_providers WHERE channel = 'whatsapp' AND is_active = 1 LIMIT 1");
                $dbProvider = $stmtProv->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Ignore DB error during early boot
            }
        }

        // Resolve active token (decrypt if from DB credential_reference)
        $activeToken = $envToken;
        if (empty($activeToken) || $activeToken === 'ISI_TOKEN_BARU_DI_SERVER') {
            if (!empty($dbSettings['whatsapp_api_token'])) {
                $activeToken = decrypt_secret($dbSettings['whatsapp_api_token']) ?: $dbSettings['whatsapp_api_token'];
            } elseif (!empty($dbProvider['credential_reference'])) {
                $activeToken = decrypt_secret($dbProvider['credential_reference']) ?: $dbProvider['credential_reference'];
            }
        }

        $activeUrl = $envUrl ?: ($dbSettings['whatsapp_api_url'] ?? 'https://api.fonnte.com/send');
        $activeSender = $envSender ?: ($dbSettings['whatsapp_sender_id'] ?? ($dbProvider['sender_identity'] ?? 'NNASA_CA'));
        $activeType = $envProvider ?: ($dbSettings['whatsapp_provider_type'] ?? 'fonnte');
        $activeTemplate = $envTemplate ?: ($dbSettings['whatsapp_otp_template'] ?? "Kode verifikasi nnasa_ca kamu adalah {{otp}}. Kode ini berlaku selama {{expiry_minutes}} menit. Jangan bagikan kode kepada siapa pun.");
        $timeout = !empty($dbSettings['whatsapp_timeout_seconds']) ? (int)$dbSettings['whatsapp_timeout_seconds'] : 10;

        $this->config = [
            'provider' => strtolower(trim($activeType)),
            'api_token' => $activeToken,
            'api_url' => trim($activeUrl),
            'sender_id' => trim($activeSender),
            'template' => trim($activeTemplate),
            'timeout' => $timeout,
            'has_token' => !empty($activeToken) && $activeToken !== 'ISI_TOKEN_BARU_DI_SERVER'
        ];
    }

    /**
     * Get adapter instance for selected provider
     */
    public function getAdapter(?string $providerType = null): WhatsAppProviderInterface {
        $type = strtolower($providerType ?: $this->config['provider']);
        switch ($type) {
            case 'wablas':
                return new WablasAdapter();
            case 'meta_cloud':
            case 'meta':
                return new MetaCloudWhatsAppAdapter();
            case 'generic':
                return new GenericWhatsAppAdapter();
            case 'fonnte':
            default:
                return new FonnteAdapter();
        }
    }

    /**
     * Send OTP via WhatsApp gateway
     */
    public function sendOTP(int $challengeId, string $phone, string $otpCode, int $expiryMinutes = 5): array {
        $normalized = normalize_phone_number($phone);
        if (!$normalized) {
            return [
                'success' => false,
                'message' => 'Format nomor WhatsApp tidak valid. Gunakan format nomor ponsel Indonesia (contoh: 08123456789).',
                'delivery_status' => 'failed',
                'error_code' => 'INVALID_PHONE_FORMAT'
            ];
        }

        // Render message
        $message = str_replace(
            ['{{otp}}', '{{expiry_minutes}}', '{{store_name}}'],
            [$otpCode, $expiryMinutes, 'nnasa_ca'],
            $this->config['template']
        );

        $masked = mask_destination($normalized);

        // Check token presence
        if (!$this->config['has_token']) {
            // Log as simulated / unconfigured
            if ($this->db) {
                $stmt = $this->db->prepare("INSERT INTO otp_delivery_logs (challenge_id, provider_name, delivery_status, error_code, sent_at, created_at)
                                            VALUES (?, 'wa_unconfigured', 'simulated', 'TOKEN_NOT_CONFIGURED', NOW(), NOW())");
                $stmt->execute([$challengeId]);
            }
            return [
                'success' => true,
                'message' => "Kode OTP: [ {$otpCode} ] — Disimulasikan ke WhatsApp {$masked} (Token provider belum dikonfigurasi).",
                'delivery_status' => 'simulated',
                'message_id' => 'SIM-' . bin2hex(random_bytes(4)),
                'simulated_otp' => $otpCode,
                'simulated_message' => $message
            ];
        }

        $adapter = $this->getAdapter();
        $result = $adapter->sendMessage(
            $normalized,
            $message,
            $this->config['api_token'],
            $this->config['api_url'],
            $this->config['sender_id'],
            $this->config['timeout']
        );

        // Log delivery status into database
        if ($this->db) {
            $stmt = $this->db->prepare("INSERT INTO otp_delivery_logs (challenge_id, provider_name, delivery_status, provider_message_id, error_code, sent_at, created_at)
                                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $challengeId,
                'wa_' . $this->config['provider'],
                $result['delivery_status'],
                $result['message_id'] ?? null,
                $result['error_code'] ?? null
            ]);
        }

        return $result;
    }

    /**
     * Test connection to provider API
     */
    public function testConnection(?string $token = null, ?string $apiUrl = null, ?string $providerType = null): array {
        $adapter = $this->getAdapter($providerType);
        $tok = !empty($token) ? $token : $this->config['api_token'];
        $url = !empty($apiUrl) ? $apiUrl : $this->config['api_url'];

        return $adapter->testConnection($tok, $url);
    }

    /**
     * Direct test message dispatch from admin panel
     */
    public function testMessage(
        string $testPhone,
        string $customMessage,
        ?string $token = null,
        ?string $apiUrl = null,
        ?string $providerType = null
    ): array {
        $normalized = normalize_phone_number($testPhone);
        if (!$normalized) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp tujuan tidak valid. Masukkan nomor ponsel (misal: 081234567890).'
            ];
        }

        $adapter = $this->getAdapter($providerType);
        $tok = !empty($token) ? $token : $this->config['api_token'];
        $url = !empty($apiUrl) ? $apiUrl : $this->config['api_url'];

        return $adapter->sendMessage(
            $normalized,
            $customMessage,
            $tok,
            $url,
            $this->config['sender_id'],
            12
        );
    }

    /**
     * Send order status & verification notification via WhatsApp
     *
     * @param int $orderId Order ID
     * @param string $eventType 'payment_verified', 'order_created', 'order_shipped', 'order_completed', 'order_cancelled', 'order_status_updated'
     * @param string|null $customNote Optional admin comment/note
     * @return array Result containing success, delivery_status, destination_phone, message, etc.
     */
    public function sendOrderNotification(int $orderId, string $eventType = 'payment_verified', ?string $customNote = null): array {
        if (!$this->db) {
            return ['success' => false, 'message' => 'Koneksi database tidak tersedia.'];
        }

        // Fetch complete order details
        $stmt = $this->db->prepare("
            SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone,
                   p.payment_method, p.transaction_reference, p.paid_at,
                   s.courier, s.service_type, s.tracking_number, s.shipping_status
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN payments p ON o.id = p.order_id
            LEFT JOIN shipments s ON o.id = s.order_id
            WHERE o.id = ?
            LIMIT 1
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            return ['success' => false, 'message' => "Pesanan #{$orderId} tidak ditemukan."];
        }

        // Extract customer's WhatsApp phone number:
        // Priority 1: From address_snapshot (format: 'Recipient Name | 08123456789\n...')
        $targetPhone = null;
        if (!empty($order['address_snapshot'])) {
            if (preg_match('/\|\s*([0-9\+\-\s]{8,25})(?:\r?\n|$)/', $order['address_snapshot'], $matches)) {
                $targetPhone = trim($matches[1]);
            } elseif (preg_match('/\|\s*([0-9\+\-\s]+)/', $order['address_snapshot'], $matches)) {
                $targetPhone = trim(strtok($matches[1], "\r\n"));
            } elseif (preg_match('/(?:telp|telepon|hp|phone|wa|whatsapp)\s*[:=]\s*([0-9\+\-\s]{8,25})/i', $order['address_snapshot'], $matches)) {
                $targetPhone = trim($matches[1]);
            }
        }
        // Priority 2: From users table
        if (empty($targetPhone) && !empty($order['customer_phone'])) {
            $targetPhone = trim($order['customer_phone']);
        }

        $normalizedPhone = normalize_phone_number((string)$targetPhone);
        if (!$normalizedPhone) {
            return [
                'success' => false,
                'message' => 'Nomor WhatsApp pembeli tidak ditemukan atau format tidak valid pada pesanan ' . $order['order_number'],
                'delivery_status' => 'failed',
                'error_code' => 'INVALID_PHONE'
            ];
        }

        // Fetch order items summary
        $stmtItems = $this->db->prepare("SELECT product_name_snapshot, quantity, subtotal FROM order_items WHERE order_id = ? LIMIT 5");
        $stmtItems->execute([$orderId]);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        $itemsSummaryList = [];
        foreach ($items as $it) {
            $itemsSummaryList[] = "• " . $it['product_name_snapshot'] . " (" . $it['quantity'] . "x)";
        }
        $itemsText = !empty($itemsSummaryList) ? implode("\n", $itemsSummaryList) : "• Aksesoris Cantik nnasa_ca";

        $customerName = htmlspecialchars_decode($order['customer_name'] ?? 'Pelanggan');
        $rawOrderNumber = $order['order_number'] ?? ('ORD-' . str_pad($orderId, 5, '0', STR_PAD_LEFT));
        $orderNumber = str_starts_with($rawOrderNumber, '#') ? $rawOrderNumber : '#' . $rawOrderNumber;
        $formattedTotal = 'Rp ' . number_format((float)$order['total_amount'], 0, ',', '.');
        $storeName = 'NNACOSA';
        $orderDetailUrl = BASE_URL . 'order-detail.php?order_id=' . $orderId;

        // Build personalized WhatsApp notification message based on eventType
        switch ($eventType) {
            case 'order_shipped':
            case 'shipped':
                $statusMsg = "Kabar baik! Pesanan Anda telah dikirim dan dalam perjalanan menuju alamat tujuan.";
                if (!empty($order['courier']) && !empty($order['tracking_number'])) {
                    $statusMsg .= "\n(Kurir: {$order['courier']} - Resi: {$order['tracking_number']})";
                }
                $statusLatest = "SHIPPED (Telah Dikirim)";
                break;

            case 'order_completed':
            case 'completed':
            case 'delivered':
                $statusMsg = "Pesanan Anda telah selesai dan berhasil diterima. Terima kasih!";
                $statusLatest = "COMPLETED (Selesai)";
                break;

            case 'order_processing':
            case 'processing':
            case 'packing':
                $statusMsg = "Pesanan Anda saat ini sedang dikemas (packing) dan dipersiapkan oleh tim kami.";
                $statusLatest = "PROCESSING (Diproses)";
                break;

            case 'payment_verified':
            case 'paid':
                $statusMsg = "Kabar baik! Pembayaran untuk pesanan Anda telah berhasil diverifikasi dan pesanan akan segera diproses.";
                $statusLatest = "PAID (Pembayaran Berhasil)";
                break;

            case 'order_cancelled':
            case 'cancelled':
                $statusMsg = "Pesanan Anda telah dibatalkan." . ($customNote ? " Alasan: {$customNote}." : "");
                $statusLatest = "CANCELLED (Dibatalkan)";
                break;

            case 'order_created':
            case 'pending':
            default:
                $statusMsg = "Pesanan Anda telah berhasil dibuat. Silakan selesaikan pembayaran agar pesanan dapat segera diproses.";
                $statusLatest = "PENDING (Menunggu Pembayaran)";
                break;
        }

        if ($customNote && !in_array($eventType, ['order_cancelled', 'cancelled'])) {
            $statusMsg .= "\nCatatan: " . $customNote;
        }

        $message = "{$storeName} — NOTIFIKASI PESANAN 💎\n\n" .
                   "Halo {$customerName},\n\n" .
                   "{$statusMsg}\n\n" .
                   "📦 Nomor Pesanan: {$orderNumber}\n" .
                   "📊 Status Terbaru: {$statusLatest}\n" .
                   "💰 Total Pembayaran: {$formattedTotal}\n\n" .
                   "Buka riwayat pembelian untuk rincian pesanan:\n" .
                   "{$orderDetailUrl}\n\n" .
                   "Terima kasih telah berbelanja di {$storeName}!\n" .
                   "✨";

        $maskedPhone = mask_destination($normalizedPhone);

        // Check token presence
        if (!$this->config['has_token']) {
            // Log as simulated in otp_delivery_logs
            $stmtLog = $this->db->prepare("
                INSERT INTO otp_delivery_logs (challenge_id, provider_name, delivery_status, provider_message_id, error_code, sent_at, created_at)
                VALUES (NULL, 'wa_order_simulated', 'simulated', ?, 'TOKEN_NOT_CONFIGURED', NOW(), NOW())
            ");
            $simId = 'SIM-ORD-' . bin2hex(random_bytes(4));
            $stmtLog->execute([$simId]);

            // Add note to order_status_history
            $this->db->prepare("
                INSERT INTO order_status_history (order_id, status, comment, created_by)
                VALUES (?, ?, ?, NULL)
            ")->execute([$orderId, $order['order_status'], "Notifikasi WhatsApp pesanan disimulasikan ke {$maskedPhone}."]);

            return [
                'success' => true,
                'delivery_status' => 'simulated',
                'phone' => $normalizedPhone,
                'destination_masked' => $maskedPhone,
                'message_id' => $simId,
                'message' => "Notifikasi WhatsApp untuk pesanan {$orderNumber} berhasil dibuat ke nomor {$maskedPhone} (Mode simulasi: token gateway belum diisi).",
                'simulated_message' => $message,
                'order_number' => $orderNumber
            ];
        }

        // Live WhatsApp Dispatch via configured gateway adapter (Fonnte / Wablas / Meta)
        $adapter = $this->getAdapter();
        $dispatchRes = $adapter->sendMessage(
            $normalizedPhone,
            $message,
            $this->config['api_token'],
            $this->config['api_url'],
            $this->config['sender_id'],
            $this->config['timeout']
        );

        // Log into otp_delivery_logs
        $stmtLog = $this->db->prepare("
            INSERT INTO otp_delivery_logs (challenge_id, provider_name, delivery_status, provider_message_id, error_code, sent_at, created_at)
            VALUES (NULL, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmtLog->execute([
            'wa_order_' . $this->config['provider'],
            $dispatchRes['delivery_status'],
            $dispatchRes['message_id'] ?? null,
            $dispatchRes['error_code'] ?? null
        ]);

        // Add history record in order_status_history
        $statusLabel = $dispatchRes['success'] ? 'berhasil dikirim' : 'gagal dikirim';
        $this->db->prepare("
            INSERT INTO order_status_history (order_id, status, comment, created_by)
            VALUES (?, ?, ?, NULL)
        ")->execute([$orderId, $order['order_status'], "Notifikasi WhatsApp pesanan {$statusLabel} ke {$maskedPhone}."]);

        $dispatchRes['phone'] = $normalizedPhone;
        $dispatchRes['destination_masked'] = $maskedPhone;
        $dispatchRes['order_number'] = $orderNumber;
        $dispatchRes['simulated_message'] = $message;
        if ($dispatchRes['success']) {
            $dispatchRes['message'] = "Notifikasi WhatsApp status pesanan berhasil dikirim ke {$maskedPhone}.";
        }

        return $dispatchRes;
    }

    /**
     * Update device auto-read settings on Fonnte API to prevent auto-seen / blue ticks
     */
    public function updateDeviceAutoread(bool $enable = false, ?string $token = null): array {
        $tokenToUse = $token ?: $this->config['api_token'];
        if (empty($tokenToUse) || $tokenToUse === 'ISI_TOKEN_BARU_DI_SERVER') {
            return [
                'success' => false,
                'message' => 'Token API WhatsApp belum dikonfigurasi.'
            ];
        }

        // 1. Ambil info device dari Fonnte
        $ch = curl_init('https://api.fonnte.com/device');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . trim($tokenToUse)]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        $deviceData = json_decode($res, true);
        if (empty($deviceData['status'])) {
            return [
                'success' => false,
                'message' => 'Gagal membaca device Fonnte: ' . ($deviceData['reason'] ?? 'Device tidak aktif atau token salah.')
            ];
        }

        $deviceNumber = $deviceData['device'] ?? null;
        $deviceName   = $deviceData['name'] ?? 'NNASA_CA';
        $flagStr      = $enable ? 'true' : 'false';

        // 2. Update device pengaturan autoread
        $chUpdate = curl_init('https://api.fonnte.com/update-device');
        curl_setopt($chUpdate, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chUpdate, CURLOPT_POST, true);
        curl_setopt($chUpdate, CURLOPT_POSTFIELDS, [
            'device'   => $deviceNumber,
            'name'     => $deviceName,
            'autoread' => $flagStr,
            'personal' => $flagStr,
            'group'    => $flagStr,
            'quick'    => $flagStr
        ]);
        curl_setopt($chUpdate, CURLOPT_HTTPHEADER, ['Authorization: ' . trim($tokenToUse)]);
        curl_setopt($chUpdate, CURLOPT_SSL_VERIFYPEER, false);
        $resUpdate = curl_exec($chUpdate);
        $httpCode = curl_getinfo($chUpdate, CURLINFO_HTTP_CODE);
        curl_close($chUpdate);

        $updateData = json_decode($resUpdate, true);
        if ($httpCode === 200 && !empty($updateData['status'])) {
            return [
                'success' => true,
                'autoread_enabled' => $enable,
                'message' => $enable 
                    ? 'Fitur Auto-Read Fonnte berhasil diaktifkan.'
                    : 'Fitur Auto-Read Fonnte berhasil DIMATIKAN! Chat masuk tidak akan otomatis centang biru lagi. 🔒'
            ];
        }

        return [
            'success' => false,
            'message' => 'Gagal mengupdate Fonnte: ' . ($updateData['detail'] ?? ($updateData['reason'] ?? 'Server Fonnte menolak request.'))
        ];
    }

    /**
     * Get sanitized configuration info for admin dashboard
     */
    public function getConfigInfo(): array {
        $masked = $this->config['has_token'] ? mask_api_key($this->config['api_token'], 6, 4) : 'Belum Dikonfigurasi';
        return [
            'provider' => $this->config['provider'],
            'provider_type' => $this->config['provider'],
            'api_url' => $this->config['api_url'],
            'sender_id' => $this->config['sender_id'],
            'template' => $this->config['template'],
            'has_token' => $this->config['has_token'],
            'is_configured' => $this->config['has_token'],
            'source' => $this->config['source'] ?? 'env',
            'token_masked' => $masked,
            'masked_token' => $masked,
            'timeout' => $this->config['timeout']
        ];
    }
}
