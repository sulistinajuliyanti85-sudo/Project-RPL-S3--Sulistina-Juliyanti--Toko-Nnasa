<?php
/**
 * NNasa AI Chatbot API Endpoint (Controller Invoker)
 * Store: Nnaco.SA E-Commerce
 * Menerima request AJAX/Fetch dari widget JS (ai-chat.js)
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';
require_once dirname(__DIR__) . '/app/Controllers/CustomerAIChatController.php';

use App\Controllers\CustomerAIChatController;

header('Content-Type: application/json; charset=utf-8');

// Rate-limiting session protection (Maks. 25 request per menit)
if (session_status() === PHP_SESSION_ACTIVE || !empty($_SESSION)) {
    $now = time();
    $_SESSION['ai_rate_limit'] = $_SESSION['ai_rate_limit'] ?? [];
    $_SESSION['ai_rate_limit'] = array_filter($_SESSION['ai_rate_limit'], function($ts) use ($now) {
        return ($now - $ts) < 60;
    });

    if (count($_SESSION['ai_rate_limit']) >= 25) {
        http_response_code(429);
        echo json_encode([
            'status'    => 'error',
            'success'   => false,
            'message'   => 'Wah, kamu mengetik terlalu cepat! Tunggu beberapa detik ya sebelum bertanya lagi. 🌸',
            'http_code' => 429
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['ai_rate_limit'][] = $now;
}

// Ambil payload JSON atau POST
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

$message = isset($input['message']) ? trim((string)$input['message']) : '';
$isReset = !empty($input['reset']);

// Reset Percakapan
if ($isReset && empty($message)) {
    echo json_encode([
        'status'      => 'success',
        'success'     => true,
        'reply'       => 'Percakapan telah direset. Ada koleksi aksesoris atau data pesanan yang ingin kamu tanyakan? 🌸',
        'products'    => [],
        'suggestions' => ['📦 Cek Pesanan Saya', '💎 Aksesoris Murah', '✨ Cek Stok Jedai', '🏷️ Info Promo']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode([
        'status'    => 'error',
        'success'   => false,
        'message'   => 'Pesan chat tidak boleh kosong.',
        'http_code' => 400
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($message) > 500) {
    $message = mb_substr($message, 0, 500);
}

try {
    $db = getDB();

    // 1. Ambil API Key dari Database Settings atau Konfigurasi
    $rawApiKey = get_setting('ai_api_key_main', '');
    if (empty($rawApiKey)) {
        $rawApiKey = get_setting('ai_api_key', '');
    }
    if (empty($rawApiKey) && defined('CHATBOT_API_KEY')) {
        $rawApiKey = CHATBOT_API_KEY;
    }
    if (empty($rawApiKey) && defined('GEMINI_API_KEY')) {
        $rawApiKey = GEMINI_API_KEY;
    }

    $model = get_setting('ai_model', 'gemini-3.5-flash-lite');
    if (in_array($model, ['gemini-1.5-flash', 'gemini-2.5-flash', 'gemini-pro', ''])) {
        $model = 'gemini-3.5-flash-lite';
    }

    // 2. Inisialisasi Controller
    $chatController = new CustomerAIChatController($db, (string)$rawApiKey, $model);

    // 3. Eksekusi Chat dengan Session Injection & Database RAG
    $response = $chatController->handleChat($message, $_SESSION ?? []);

    // 4. Output Response (Jika terjadi error, kirim status HTTP dan JSON detail aslinya)
    if (isset($response['status']) && $response['status'] === 'error') {
        $httpCode = (isset($response['http_code']) && $response['http_code'] >= 400 && $response['http_code'] < 600)
            ? $response['http_code']
            : 500;
        http_response_code($httpCode);
    } else {
        http_response_code(200);
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    // Tangkap error asli dari server/database agar developer dapat mendebug dengan jelas di browser console
    http_response_code(500);
    echo json_encode([
        'status'    => 'error',
        'success'   => false,
        'message'   => 'Database/Server Error: ' . $e->getMessage(),
        'http_code' => 500,
        'file'      => basename($e->getFile()),
        'line'      => $e->getLine()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
exit;
