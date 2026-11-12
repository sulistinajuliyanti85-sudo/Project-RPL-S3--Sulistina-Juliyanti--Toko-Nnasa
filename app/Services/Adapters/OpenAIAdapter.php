<?php
/**
 * OpenAI Provider Adapter
 * Store: nnasa_ca Accessories Marketplace
 * Compatible with OpenAI API and OpenAI-compatible endpoints (DeepSeek, Groq, Ollama, etc.)
 */

require_once dirname(__DIR__) . '/AIProviderAdapterInterface.php';

class OpenAIAdapter implements AIProviderAdapterInterface {
    private string $defaultBaseUrl = 'https://api.openai.com/v1/';
    private string $defaultModel = 'gpt-4o-mini';
    private ?string $defaultApiKey;

    public function __construct(?string $apiKey = null) {
        $this->defaultApiKey = $apiKey;
    }

    public function getProviderName(): string {
        return 'openai';
    }

    public function testConnection(?string $apiKey = null, ?string $baseUrl = null): array {
        $key = $apiKey ?: $this->defaultApiKey;
        if (empty($key)) {
            return [
                'success' => false,
                'status' => 'Belum Dikonfigurasi',
                'message' => 'API Key OpenAI belum diisi.',
                'latency_ms' => 0
            ];
        }

        $base = rtrim($baseUrl ?: $this->defaultBaseUrl, '/') . '/';
        $url = $base . 'models';

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $count = isset($data['data']) ? count($data['data']) : 0;
            return [
                'success' => true,
                'status' => 'Berhasil',
                'message' => "Koneksi berhasil! Terhubung dengan OpenAI API ($count model terdeteksi).",
                'latency_ms' => $latency,
                'http_code' => 200
            ];
        }

        $errMsg = 'Gagal menghubungi server OpenAI (HTTP ' . $httpCode . ')';
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

        $url = $this->defaultBaseUrl . 'models';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $models = [];
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if (!empty($data['data'])) {
                foreach ($data['data'] as $m) {
                    $id = $m['id'] ?? '';
                    // Filter popular chat completion models
                    if (preg_match('/^(gpt|chatgpt|o1|o3)/i', $id)) {
                        $models[] = [
                            'name' => $id,
                            'display_name' => $id,
                            'description' => 'OpenAI ' . $id,
                            'input_token_limit' => 128000,
                            'output_token_limit' => 4096
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
                'error' => 'API Key OpenAI belum dikonfigurasi.',
                'latency_ms' => 0
            ];
        }

        $chosenModel = !empty($model) ? $model : $this->defaultModel;
        $url = $this->defaultBaseUrl . 'chat/completions';

        $messages = [];
        if (!empty($systemInstruction)) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemInstruction
            ];
        }
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        $temp = isset($parameters['temperature']) ? (float)$parameters['temperature'] : 0.3;
        $maxTokens = isset($parameters['max_output_tokens']) ? (int)$parameters['max_output_tokens'] : 600;
        $timeout = isset($parameters['timeout_seconds']) ? (int)$parameters['timeout_seconds'] : 35;

        $payload = [
            'model' => $chosenModel,
            'messages' => $messages,
            'temperature' => $temp,
            'max_tokens' => $maxTokens
        ];

        $start = microtime(true);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $latency = (int)round((microtime(true) - $start) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $botText = $data['choices'][0]['message']['content'] ?? null;
            $inputTokens = $data['usage']['prompt_tokens'] ?? (int)round(mb_strlen($prompt) / 4);
            $outputTokens = $data['usage']['completion_tokens'] ?? (int)round(mb_strlen($botText ?? '') / 4);

            if ($botText) {
                return [
                    'success' => true,
                    'reply' => trim($botText),
                    'provider' => 'openai',
                    'model' => $chosenModel,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'latency_ms' => $latency,
                    'raw_response' => $data
                ];
            }
        }

        $errorMsg = 'OpenAI Error (HTTP ' . $httpCode . ')';
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
            'provider' => 'openai',
            'model' => $chosenModel,
            'latency_ms' => $latency,
            'http_code' => $httpCode
        ];
    }
}
