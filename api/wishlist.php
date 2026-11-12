<?php
/**
 * Wishlist API Endpoint
 * Handles toggling products in wishlist
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

$user = current_user();
$userId = $user['id'];
$db = getDB();

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;
$productId = (int)($input['product_id'] ?? 0);

if (!$productId) {
    json_response(['success' => false, 'message' => 'Produk ID tidak valid.'], 400);
}

try {
    // Check if product already in wishlist
    $stmt = $db->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ? LIMIT 1");
    $stmt->execute([$userId, $productId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $stmt = $db->prepare("DELETE FROM wishlists WHERE id = ?");
        $stmt->execute([$existing['id']]);
        $status = 'removed';
    } else {
        $stmt = $db->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$userId, $productId]);
        $status = 'added';
    }

    $count = get_user_wishlist_count($userId);
    json_response([
        'success' => true,
        'status' => $status,
        'count' => $count
    ]);

} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Kesalahan server: ' . $e->getMessage()], 500);
}
