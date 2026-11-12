<?php
/**
 * API Endpoint: WhatsApp Order Notification Dispatcher
 * Store: nnasa_ca Accessories Marketplace
 * Sends customized WhatsApp notifications for Order Verification, Shipping, & Completion.
 */

header('Content-Type: application/json; charset=UTF-8');

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';
require_once dirname(__DIR__) . '/app/Services/WhatsApp/WhatsAppService.php';

// Authentication check
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Silakan masuk untuk mengakses fitur ini.']);
    exit;
}

$currentUser = current_user();
$userId = (int)$currentUser['id'];
$isAdmin = ($currentUser['role'] === 'admin');

// Parse input payload
$input = $_POST;
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $input = array_merge($input, $decoded);
    }
}

$orderId = (int)($input['order_id'] ?? ($_GET['order_id'] ?? 0));
$eventType = sanitize($input['event_type'] ?? ($_GET['event_type'] ?? 'payment_verified'));
$customNote = sanitize($input['note'] ?? '');

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parameter order_id wajib diisi.']);
    exit;
}

$db = getDB();

// Verify order ownership if not admin
if (!$isAdmin) {
    $stmt = $db->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$orderId, $userId]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk pesanan ini.']);
        exit;
    }
}

try {
    $waService = new WhatsAppService($db);
    $result = $waService->sendOrderNotification($orderId, $eventType, $customNote);

    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan sistem saat mengirim notifikasi WhatsApp: ' . $e->getMessage()
    ]);
}
