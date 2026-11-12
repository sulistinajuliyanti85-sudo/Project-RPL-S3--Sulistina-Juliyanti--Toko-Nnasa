<?php
/**
 * WhatsApp Adapter: Wablas (Indonesian Gateway)
 * Store: nnasa_ca Accessories Marketplace
 * Docs: https://wablas.com
 */

require_once __DIR__ . '/WhatsAppProviderInterface.php';

class WablasAdapter implements WhatsAppProviderInterface {
    private string $defaultApiUrl = 'https://phone.wablas.com/api/send-message';
    private string $deviceApiUrl = 'https://phone.wablas.com/api/device/info';

    public function getProviderName(): string {
        return 'wablas';
    }

    public function testConnection(?string $token = null, ?string $apiUrl = null): array {
        if (empty($token) || $token === 'ISI_TOKEN_BARU_DI_SERVER') {
            return [
                'success' => false,
                'status' => 'Belum Dikonfigurasi',
                'message' => 'API Token Wablas belum diisi.',
                'latency_ms' => 0,
                'http_code' => 0
            ];
        }

        $url = !empty($apiUrl) ? str_replace('/send-message', '/device/info', $apiUrl) : $this->deviceApiUrl;

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
                return [
                    'success' => true,
                    'status' => 'Berhasil',
                    'message' => 'Koneksi Wablas WhatsApp berhasil! Device aktif.',
                    'latency_ms' => $latency,
                    'http_code' => $httpCode,
                    'device_info' => $data
                ];
            }
        }

        $msg = 'Gagal menghubungi server Wablas (HTTP ' . $httpCode . ')';
        if ($response) {
            $d = json_decode($response, true);
            if (!empty($d['message'])) $msg = $d['message'];
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
                'message' => 'Token API WhatsApp Wablas belum dikonfigurasi.',
                'latency_ms' => 0,
                'http_code' => 0
            ];
        }

        $url = !empty($apiUrl) ? $apiUrl : $this->defaultApiUrl;

        $payload = [
            'phone' => $normalizedPhone,
            'message' => $message
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
                'message' => 'Gagal koneksi ke server Wablas: ' . $curlErr,
                'latency_ms' => $latency,
                'http_code' => $httpCode
            ];
        }

        $data = $response ? json_decode($response, true) : null;

        if ($httpCode === 200 && is_array($data)) {
            if (!empty($data['status'])) {
                $msgId = $data['data']['messages'][0]['id'] ?? ($data['data']['id'] ?? ('WABLAS-' . bin2hex(random_bytes(4))));
                return [
                    'success' => true,
                    'delivery_status' => 'sent',
                    'message_id' => $msgId,
                    'error_code' => null,
                    'message' => 'Pesan OTP berhasil diterima oleh gateway Wablas.',
                    'latency_ms' => $latency,
                    'http_code' => $httpCode,
                    'raw_response' => $data
                ];
            } else {
                return [
                    'success' => false,
                    'delivery_status' => 'failed',
                    'message_id' => null,
                    'error_code' => 'PROVIDER_REJECTED',
                    'message' => 'Gateway Wablas menolak: ' . ($data['message'] ?? 'Error tidak diketahui'),
                    'latency_ms' => $latency,
                    'http_code' => $httpCode,
                    'raw_response' => $data
                ];
            }
        }

        return [
            'success' => false,
            'delivery_status' => 'failed',
            'message_id' => null,
            'error_code' => 'HTTP_' . $httpCode,
            'message' => 'Gateway Wablas Error (HTTP ' . $httpCode . ')',
            'latency_ms' => $latency,
            'http_code' => $httpCode,
            'raw_response' => $data
        ];
    }
}
