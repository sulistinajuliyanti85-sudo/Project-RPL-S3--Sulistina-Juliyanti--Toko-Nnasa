<?php
/**
 * Coupon Voucher Check API
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
$code = strtoupper(trim($input['code'] ?? ''));
$subtotal = (float)($input['subtotal'] ?? 0);

if (empty($code)) {
    json_response(['success' => false, 'message' => 'Masukkan kode kupon.']);
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active' LIMIT 1");
$stmt->execute([$code]);
$coupon = $stmt->fetch();

if (!$coupon) {
    json_response(['success' => false, 'message' => 'Kode voucher tidak valid atau telah berakhir.']);
}

// Check date bounds
$now = date('Y-m-d H:i:s');
if (!empty($coupon['start_at']) && $now < $coupon['start_at']) {
    json_response(['success' => false, 'message' => 'Voucher belum dapat digunakan.']);
}
if (!empty($coupon['end_at']) && $now > $coupon['end_at']) {
    json_response(['success' => false, 'message' => 'Voucher telah kedaluwarsa.']);
}

// Check usage limit
if ($coupon['used_count'] >= $coupon['usage_limit']) {
    json_response(['success' => false, 'message' => 'Kuota voucher telah habis.']);
}

// Check minimum purchase
$minPurchase = (float)$coupon['minimum_purchase'];
if ($subtotal < $minPurchase) {
    json_response([
        'success' => false,
        'message' => 'Minimal belanja untuk voucher ini adalah ' . format_rupiah($minPurchase) . '.'
    ]);
}

// Calculate discount
$discount = 0;
if ($coupon['discount_type'] === 'percentage') {
    $discount = ($subtotal * (float)$coupon['discount_value']) / 100;
    if (!empty($coupon['maximum_discount']) && $discount > (float)$coupon['maximum_discount']) {
        $discount = (float)$coupon['maximum_discount'];
    }
} else {
    // Fixed amount
    $discount = min($subtotal, (float)$coupon['discount_value']);
}

json_response([
    'success' => true,
    'coupon_id' => $coupon['id'],
    'code' => $coupon['code'],
    'discount_amount' => $discount,
    'discount_formatted' => format_rupiah($discount),
    'message' => 'Voucher ' . $coupon['code'] . ' berhasil diterapkan! Hemat ' . format_rupiah($discount)
]);
