<?php
/**
 * Payment Simulation API (DEMO Gateway Simulation)
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    echo json_encode(['status' => 'unauthorized', 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;
$orderId = (int)($input['order_id'] ?? 0);
$action = $input['sim_action'] ?? 'pay_success'; // 'pay_success' or 'pay_fail'

$user = current_user();
$userId = $user['id'];
$db = getDB();

// Fetch order
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    json_response(['success' => false, 'message' => 'Pesanan tidak ditemukan.'], 404);
}

// Ensure user owns order or is admin/staff
if ($order['user_id'] != $userId && !in_array($user['role'], ['admin', 'staff'])) {
    json_response(['success' => false, 'message' => 'Akses ditolak.'], 403);
}

if ($order['payment_status'] === 'paid') {
    json_response(['success' => true, 'message' => 'Pesanan sudah berstatus lunas.', 'redirect' => BASE_URL . 'order-detail.php?order_id=' . $orderId]);
}

try {
    $db->beginTransaction();

    if ($action === 'pay_success') {
        // Update payments table
        $stmt = $db->prepare("UPDATE payments SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE order_id = ?");
        $stmt->execute([$orderId]);

        // Update orders table
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);

        // Record history
        $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES (?, 'processing', 'Simulasi Pembayaran DEMO berhasil diselesaikan.', ?)");
        $stmt->execute([$orderId, $userId]);

        // Log activity
        log_activity($userId, 'payment_demo_success', 'orders', $orderId, 'Simulated payment success for order ' . $order['order_number']);

        // Dispatch WhatsApp order notification to customer's exact order phone number
        require_once dirname(__DIR__) . '/app/Services/WhatsApp/WhatsAppService.php';
        $waService = new WhatsAppService($db);
        $waResult = $waService->sendOrderNotification($orderId, 'payment_verified', 'Pembayaran via Simulasi Berhasil');

        $db->commit();
        json_response([
            'success' => true,
            'message' => 'Simulasi pembayaran berhasil! Pesanan diverifikasi Lunas & diproses. 💖',
            'wa_notification' => $waResult,
            'redirect' => BASE_URL . 'order-detail.php?order_id=' . $orderId
        ]);
    } else {
        // Payment failed simulation
        $stmt = $db->prepare("UPDATE payments SET status = 'failed', updated_at = NOW() WHERE order_id = ?");
        $stmt->execute([$orderId]);

        $stmt = $db->prepare("UPDATE orders SET payment_status = 'failed', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);

        $stmt = $db->prepare("INSERT INTO order_status_history (order_id, status, comment, created_by) VALUES (?, 'pending', 'Simulasi Pembayaran DEMO gagal.', ?)");
        $stmt->execute([$orderId, $userId]);

        $db->commit();
        json_response([
            'success' => true,
            'message' => 'Simulasi pembayaran ditandai Gagal.',
            'redirect' => BASE_URL . 'payment.php?order_id=' . $orderId
        ]);
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    json_response(['success' => false, 'message' => 'Gagal memproses pembayaran: ' . $e->getMessage()], 500);
}
