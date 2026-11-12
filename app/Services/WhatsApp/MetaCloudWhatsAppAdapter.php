<?php
/**
 * WhatsApp Adapter: Meta Cloud API (Official WhatsApp Business Platform)
 * Store: nnasa_ca Accessories Marketplace
 * Docs: https://developers.facebook.com/docs/whatsapp/cloud-api
 */

require_once __DIR__ . '/WhatsAppProviderInterface.php';

class MetaCloudWhatsAppAdapter implements WhatsAppProviderInterface {
    private string $defaultApiUrl = 'https://graph.facebook.com/v19.0/';

    public function getProviderName(): string {
        return 'meta_cloud';
    }

    public function testConnection(?string $token = null, ?string $apiUrl = null): array {
        if (empty($token) || $token === 'ISI_TOKEN_BARU_DI_SERVER') {
            return [
                'success' => false,
                'status' => 'Belum Dikonfigurasi',
                'message' => 'API Token Meta WhatsApp Cloud API belum diisi.',
                'latency_ms' => 0,
                'http_code' => 0
            ];
        }

        $url = !empty($apiUrl) ? $apiUrl : ($this->defaultApiUrl . 'me');

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($token),
            'Content-Type: application/json'
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
            return [
                'success' => true,
                'status' => 'Berhasil',
                'message' => 'Koneksi Meta WhatsApp Cloud API terverifikasi!',
                'latency_ms' => $latency,
                'http_code' => 200,
                'device_info' => $data
            ];
        }

        $msg = 'Gagal menghubungi Meta Graph API (HTTP ' . $httpCode . ')';
        if ($response) {
            $d = json_decode($response, true);
            if (!empty($d['error']['message'])) {
                $msg = $d['error']['message'];
            }
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
                'message' => 'Token API WhatsApp Meta belum dikonfigurasi.',
                'latency_ms' => 0,
                'http_code' => 0
            ];
        }

        $phoneId = !empty($senderId) ? $senderId : 'me';
        $url = !empty($apiUrl) ? $apiUrl : ($this->defaultApiUrl . $phoneId . '/messages');

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $normalizedPhone,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $message
            ]
        ];

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . trim($token),
            'Content-Type: application/json'
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
                'message' => 'Gagal koneksi ke Meta Cloud API: ' . $curlErr,
                'latency_ms' => $latency,
                'http_code' => $httpCode
            ];
        }

        $data = $response ? json_decode($response, true) : null;

        if (($httpCode === 200 || $httpCode === 201) && is_array($data)) {
            $msgId = $data['messages'][0]['id'] ?? ('META-' . bin2hex(random_bytes(4)));
            return [
                'success' => true,
                'delivery_status' => 'sent',
                'message_id' => $msgId,
                'error_code' => null,
                'message' => 'Pesan OTP berhasil dikirim melalui Meta WhatsApp Cloud API.',
                'latency_ms' => $latency,
                'http_code' => $httpCode,
                'raw_response' => $data
            ];
        }

        $err = 'Meta Cloud API Error (HTTP ' . $httpCode . ')';
        if (is_array($data) && !empty($data['error']['message'])) {
            $err = $data['error']['message'];
        }

        return [
            'success' => false,
            'delivery_status' => 'failed',
            'message_id' => null,
            'error_code' => 'META_' . $httpCode,
            'message' => $err,
            'latency_ms' => $latency,
            'http_code' => $httpCode,
            'raw_response' => $data
        ];
    }
}
