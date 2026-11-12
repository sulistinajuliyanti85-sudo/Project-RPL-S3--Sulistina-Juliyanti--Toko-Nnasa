<?php
/**
 * Google Gemini Provider Adapter
 * Store: nnasa_ca Accessories Marketplace
 */

require_once dirname(__DIR__) . '/AIProviderAdapterInterface.php';

class GeminiAdapter implements AIProviderAdapterInterface {
    private string $defaultBaseUrl = 'https://generativelanguage.googleapis.com/v1beta/';
    private string $defaultModel = 'gemini-flash-lite-latest';
    private ?string $defaultApiKey;

    public function __construct(?string $apiKey = null) {
        $this->defaultApiKey = $apiKey;
    }

    public function getProviderName(): string {
        return 'gemini';
    }

    public function testConnection(?string $apiKey = null, ?string $baseUrl = null): array {
        $key = $apiKey ?: $this->defaultApiKey;
        if (empty($key)) {
            return [
                'success' => false,
                'status' => 'Belum Dikonfigurasi',
                'message' => 'API Key Google Gemini belum diisi.',
                'latency_ms' => 0
            ];
        }

        $base = rtrim($baseUrl ?: $this->defaultBaseUrl, '/') . '/';
        $url = $base . "models?key=" . urlencode($key);

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $count = isset($data['models']) ? count($data['models']) : 0;
            return [
                'success' => true,
                'status' => 'Berhasil',
                'message' => "Koneksi berhasil! Terhubung dengan Google Gemini API ($count model tersedia).",
                'latency_ms' => $latency,
                'http_code' => 200
            ];
        }

        $errMsg = 'Gagal menghubungi server Gemini (HTTP ' . $httpCode . ')';
        if ($response) {
            $errData = json_decode($response, true);
            if (!empty($errData['error']['message'])) {
                $errMsg = $errData['error']['message'];
            }
        } elseif ($error) {
            $errMsg = $error;
        }

        return [
            'success' => false,
            'status' => 'Gagal',
            'message' => $errMsg,
            'latency_ms' => $latency,
            'http_code' => $httpCode
        ];
    }

    public function fetchAvailableModels(?string $apiKey = null): array {
        $key = $apiKey ?: $this->defaultApiKey;
        if (empty($key)) return [];

        $url = $this->defaultBaseUrl . "models?key=" . urlencode($key);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $models = [];
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['models'])) {
                foreach ($data['models'] as $m) {
                    $methods = $m['supportedGenerationMethods'] ?? [];
                    if (in_array('generateContent', $methods)) {
                        $shortName = str_replace('models/', '', $m['name']);
                        $models[] = [
                            'name' => $shortName,
                            'display_name' => $m['displayName'] ?? $shortName,
                            'description' => $m['description'] ?? '',
                            'input_token_limit' => $m['inputTokenLimit'] ?? null,
                            'output_token_limit' => $m['outputTokenLimit'] ?? null
                        ];
                    }
                }
            }
        }
        return $models;
    }

    public function generateContent(
        string $prompt,
        string $systemInstruction = '',
        string $model = '',
        array $parameters = [],
        ?string $apiKey = null
    ): array {
        $key = $apiKey ?: $this->defaultApiKey;
        if (empty($key)) {
            return [
                'success' => false,
                'reply' => '',
                'error' => 'API Key Gemini belum dikonfigurasi.',
                'latency_ms' => 0
            ];
        }

        $chosenModel = !empty($model) ? $model : $this->defaultModel;
        // Periksa dan ganti ID model: jika gemini-3.8-flash ganti ke model valid gemini-1.5-flash
        if (str_contains($chosenModel, '3.8')) {
            $chosenModel = 'gemini-1.5-flash';
        }
        $url = $this->defaultBaseUrl . "models/{$chosenModel}:generateContent?key=" . urlencode($key);

        // Kompresi Teks: Hapus tag HTML dan rapikan whitespace/enter berlebih
        $cleanInstruction = !empty($systemInstruction) ? strip_tags($systemInstruction) : '';
        $cleanInstruction = preg_replace('/[\r\n\t]+/', ' ', $cleanInstruction);
        $cleanInstruction = preg_replace('/\s{2,}/', ' ', trim($cleanInstruction));

        $cleanPrompt = strip_tags($prompt);
        $cleanPrompt = preg_replace('/[\r\n\t]+/', ' ', $cleanPrompt);
        $cleanPrompt = preg_replace('/\s{2,}/', ' ', trim($cleanPrompt));

        $fullPrompt = !empty($cleanInstruction)
            ? $cleanInstruction . "\n\nPertanyaan: " . $cleanPrompt
            : $cleanPrompt;

        $temp = isset($parameters['temperature']) ? (float)$parameters['temperature'] : 0.3;
        $maxTokens = isset($parameters['max_output_tokens']) ? (int)$parameters['max_output_tokens'] : 600;
        $topP = isset($parameters['top_p']) ? (float)$parameters['top_p'] : 0.95;
        $timeout = 30; // Batas waktu cURL 30 detik yang masuk akal

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $fullPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $temp,
                'maxOutputTokens' => $maxTokens,
                'topP' => $topP
            ]
        ];

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Batas waktu request 30 detik
        curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Lewati verifikasi SSL lokal
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo = curl_errno($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        // Blok pengecekan curl_errno() dan curl_error() setelah curl_exec()
        if ($curlErrNo !== 0 || !$response) {
            return [
                'success' => false,
                'reply' => '',
                'error' => "cURL Error ({$curlErrNo}): " . ($curlErr ?: 'Gagal menghubungi server Google Gemini.'),
                'provider' => 'gemini',
                'model' => $chosenModel,
                'latency_ms' => $latency,
                'http_code' => $httpCode
            ];
        }

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $botText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
            $inputTokens = $data['usageMetadata']['promptTokenCount'] ?? (int)round(mb_strlen($fullPrompt) / 4);
            $outputTokens = $data['usageMetadata']['candidatesTokenCount'] ?? (int)round(mb_strlen($botText ?? '') / 4);

            if ($botText) {
                return [
                    'success' => true,
                    'reply' => trim($botText),
                    'provider' => 'gemini',
                    'model' => $chosenModel,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'latency_ms' => $latency,
                    'raw_response' => $data
                ];
            }
        }

        $errorMsg = 'Gemini Error (HTTP ' . $httpCode . ')';
        if ($response) {
            $errData = json_decode($response, true);
            if (!empty($errData['error']['message'])) {
                $errorMsg = $errData['error']['message'];
            }
        } elseif ($curlErr) {
            $errorMsg = $curlErr;
        }

        return [
            'success' => false,
            'reply' => '',
            'error' => $errorMsg,
            'provider' => 'gemini',
            'model' => $chosenModel,
            'latency_ms' => $latency,
            'http_code' => $httpCode
        ];
    }
}
