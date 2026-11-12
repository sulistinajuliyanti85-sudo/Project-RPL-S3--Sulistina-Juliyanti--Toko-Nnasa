<?php
/**
 * WhatsApp Adapter: Fonnte (Official Indonesian Gateway)
 * Store: nnasa_ca Accessories Marketplace
 * Docs: https://docs.fonnte.com
 */

require_once __DIR__ . '/WhatsAppProviderInterface.php';

class FonnteAdapter implements WhatsAppProviderInterface {
    private string $defaultApiUrl = 'https://api.fonnte.com/send';
    private string $deviceApiUrl = 'https://api.fonnte.com/device';

    public function getProviderName(): string {
        return 'fonnte';
    }

    public function testConnection(?string $token = null, ?string $apiUrl = null): array {
        if (empty($token) || $token === 'ISI_TOKEN_BARU_DI_SERVER') {
            return [
                'success' => false,
                'status' => 'Belum Dikonfigurasi',
                'message' => 'API Token WhatsApp belum diisi pada konfigurasi server (.env atau admin panel).',
                'latency_ms' => 0,
                'http_code' => 0
            ];
        }

        $url = !empty($apiUrl) ? str_replace('/send', '/device', $apiUrl) : $this->deviceApiUrl;

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . trim($token)
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['status'])) {
                $device = $data['device'] ?? ($data['name'] ?? 'Terhubung');
                return [
                    'success' => true,
                    'status' => 'Berhasil',
                    'message' => "Koneksi Fonnte WhatsApp berhasil! Device aktif: {$device}.",
                    'latency_ms' => $latency,
                    'http_code' => $httpCode,
                    'device_info' => $data
                ];
            } else {
                $reason = $data['reason'] ?? ($data['message'] ?? 'Device tidak aktif atau token salah');
                return [
                    'success' => false,
                    'status' => 'Gagal',
                    'message' => "Fonnte menolak autentikasi: {$reason}",
                    'latency_ms' => $latency,
                    'http_code' => $httpCode
                ];
            }
        }

        $msg = 'Gagal menghubungi server Fonnte (HTTP ' . $httpCode . ')';
        if ($response) {
            $d = json_decode($response, true);
            if (!empty($d['reason'])) $msg = $d['reason'];
        } elseif ($curlErr) {
            $msg = $curlErr;
        }

        return [
            'success' => false,
            'status' => 'Gagal',
            'error_code' => $curlErr ? (str_contains(strtolower($curlErr), 'timeout') ? 'TIMEOUT' : 'CONN_FAILED') : 'HTTP_' . $httpCode,
            'message' => $msg,
            'latency_ms' => $latency,
            'http_code' => $httpCode
        ];
    }

    public function sendMessage(
        string $normalizedPhone,
        string $message,
        ?string $token = null,
        ?string $apiUrl = null,
        ?string $senderId = null,
        int $timeout = 12
    ): array {
        if (empty($token) || $token === 'ISI_TOKEN_BARU_DI_SERVER') {
            return [
                'success' => false,
                'delivery_status' => 'failed',
                'message_id' => null,
                'error_code' => 'TOKEN_EMPTY',
                'message' => 'Token API WhatsApp Fonnte belum dikonfigurasi.',
                'latency_ms' => 0,
                'http_code' => 0
            ];
        }

        $url = !empty($apiUrl) ? $apiUrl : $this->defaultApiUrl;

        $payload = [
            'target' => $normalizedPhone,
            'message' => $message,
            'countryCode' => '62'
        ];

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . trim($token)
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            return [
                'success' => false,
                'delivery_status' => 'failed',
                'message_id' => null,
                'error_code' => str_contains(strtolower($curlErr), 'timeout') ? 'TIMEOUT_ERROR' : 'CURL_ERROR',
                'message' => 'Gagal koneksi ke server WhatsApp: ' . $curlErr,
                'latency_ms' => $latency,
                'http_code' => $httpCode
            ];
        }

        $data = $response ? json_decode($response, true) : null;

        if ($httpCode === 200 && is_array($data)) {
            if (!empty($data['status'])) {
                // Extract provider message ID
                $msgId = null;
                if (!empty($data['id'])) {
                    $msgId = is_array($data['id']) ? ($data['id'][0] ?? null) : $data['id'];
                }
                return [
                    'success' => true,
                    // Status is logged as received/sent by provider, not necessarily read yet
                    'delivery_status' => 'sent',
                    'message_id' => $msgId ?: ('FONNTE-' . bin2hex(random_bytes(4))),
                    'error_code' => null,
                    'message' => 'Kode verifikasi OTP berhasil dikirimkan ke nomor WhatsApp Anda. Silakan cek pesan masuk WhatsApp Anda.',
                    'latency_ms' => $latency,
                    'http_code' => $httpCode,
                    'raw_response' => $data
                ];
            } else {
                $reason = $data['reason'] ?? ($data['detail'] ?? 'Ditolak oleh gateway Fonnte');
                return [
                    'success' => false,
                    'delivery_status' => 'failed',
                    'message_id' => null,
                    'error_code' => 'PROVIDER_REJECTED',
                    'message' => 'Gateway WhatsApp menolak: ' . $reason,
                    'latency_ms' => $latency,
                    'http_code' => $httpCode,
                    'raw_response' => $data
                ];
            }
        }

        $errorMsg = 'Gateway error (HTTP ' . $httpCode . ')';
        if (is_array($data) && !empty($data['reason'])) {
            $errorMsg = $data['reason'];
        }

        return [
            'success' => false,
            'delivery_status' => 'failed',
            'message_id' => null,
            'error_code' => 'HTTP_' . $httpCode,
            'message' => $errorMsg,
            'latency_ms' => $latency,
            'http_code' => $httpCode,
            'raw_response' => $data
        ];
    }
}
