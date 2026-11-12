<?php
/**
 * WhatsApp Adapter: Generic / Custom Webhook Gateway
 * Store: nnasa_ca Accessories Marketplace
 * Allows store owner to integrate any custom HTTP API endpoint
 */

require_once __DIR__ . '/WhatsAppProviderInterface.php';

class GenericWhatsAppAdapter implements WhatsAppProviderInterface {
    public function getProviderName(): string {
        return 'generic';
    }

    public function testConnection(?string $token = null, ?string $apiUrl = null): array {
        if (empty($apiUrl)) {
            return [
                'success' => false,
                'status' => 'Belum Dikonfigurasi',
                'message' => 'URL Endpoint WhatsApp Gateway belum diisi.',
                'latency_ms' => 0,
                'http_code' => 0
            ];
        }

        $start = microtime(true);
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if (!empty($token) && $token !== 'ISI_TOKEN_BARU_DI_SERVER') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . (str_starts_with($token, 'Bearer ') ? $token : 'Bearer ' . $token)
            ]);
        }

        $response = curl_exec($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 400) {
            return [
                'success' => true,
                'status' => 'Berhasil',
                'message' => 'Koneksi ke Generic Gateway berhasil (HTTP ' . $httpCode . ')',
                'latency_ms' => $latency,
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => false,
            'status' => 'Gagal',
            'error_code' => $curlErr ? (str_contains(strtolower($curlErr), 'timeout') ? 'TIMEOUT' : 'CONN_FAILED') : 'HTTP_' . $httpCode,
            'message' => 'Gateway merespons dengan HTTP ' . $httpCode . ($curlErr ? ": {$curlErr}" : ''),
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
        if (empty($apiUrl)) {
            return [
                'success' => false,
                'delivery_status' => 'failed',
                'message_id' => null,
                'error_code' => 'URL_EMPTY',
                'message' => 'Endpoint API WhatsApp belum dikonfigurasi.',
                'latency_ms' => 0,
                'http_code' => 0
            ];
        }

        $payload = [
            'phone' => $normalizedPhone,
            'to' => $normalizedPhone,
            'target' => $normalizedPhone,
            'message' => $message,
            'sender' => $senderId
        ];

        $headers = ['Content-Type: application/json'];
        if (!empty($token) && $token !== 'ISI_TOKEN_BARU_DI_SERVER') {
            $headers[] = 'Authorization: ' . (str_starts_with($token, 'Bearer ') ? $token : 'Bearer ' . $token);
        }

        $start = microtime(true);
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
                'message' => 'Koneksi Generic Gateway error: ' . $curlErr,
                'latency_ms' => $latency,
                'http_code' => $httpCode
            ];
        }

        $data = $response ? json_decode($response, true) : null;

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'delivery_status' => 'sent',
                'message_id' => 'GEN-' . bin2hex(random_bytes(4)),
                'error_code' => null,
                'message' => 'Pesan OTP berhasil dikirim melalui Generic Gateway.',
                'latency_ms' => $latency,
                'http_code' => $httpCode,
                'raw_response' => $data
            ];
        }

        return [
            'success' => false,
            'delivery_status' => 'failed',
            'message_id' => null,
            'error_code' => 'HTTP_' . $httpCode,
            'message' => 'Generic Gateway error (HTTP ' . $httpCode . ')',
            'latency_ms' => $latency,
            'http_code' => $httpCode,
            'raw_response' => $data
        ];
    }
}
