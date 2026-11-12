<?php
/**
 * Cart API Endpoint
 * Handles: add, update, remove, toggle_select
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

// Read JSON input or POST
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;
$action = $input['action'] ?? '';

// Ensure user has a cart
$stmt = $db->prepare("SELECT id FROM carts WHERE user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$cartId = $stmt->fetchColumn();

if (!$cartId) {
    $stmt = $db->prepare("INSERT INTO carts (user_id) VALUES (?)");
    $stmt->execute([$userId]);
    $cartId = (int)$db->lastInsertId();
}

try {
    if ($action === 'add') {
        $productId = (int)($input['product_id'] ?? 0);
        $variantId = !empty($input['variant_id']) ? (int)$input['variant_id'] : null;
        $quantity = max(1, (int)($input['quantity'] ?? 1));

        // Validate product
        $stmt = $db->prepare("SELECT id, name, price, discount_price, stock, status FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product || $product['status'] !== 'active') {
            json_response(['success' => false, 'message' => 'Produk tidak ditemukan atau tidak aktif.']);
        }

        // Validate variant & stock
        $availableStock = (int)$product['stock'];
        if ($variantId) {
            $stmt = $db->prepare("SELECT id, stock, status FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1");
            $stmt->execute([$variantId, $productId]);
            $variant = $stmt->fetch();
            if ($variant) {
                $availableStock = (int)$variant['stock'];
            }
        }

        // Check existing cart item
        if ($variantId) {
            $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id = ? LIMIT 1");
            $stmt->execute([$cartId, $productId, $variantId]);
        } else {
            $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id IS NULL LIMIT 1");
            $stmt->execute([$cartId, $productId]);
        }
        $existing = $stmt->fetch();

        $newQty = $quantity;
        if ($existing) {
            $newQty += (int)$existing['quantity'];
        }

        if ($newQty > $availableStock) {
            json_response([
                'success' => false,
                'message' => 'Stok tidak mencukupi. Sisa stok tersedia: ' . $availableStock . ' pcs.'
            ]);
        }

        if ($existing) {
            $stmt = $db->prepare("UPDATE cart_items SET quantity = ?, is_selected = 1, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newQty, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, is_selected) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$cartId, $productId, $variantId, $newQty]);
        }

        $cartCount = get_user_cart_count($userId);
        json_response([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke keranjang.',
            'cart_count' => $cartCount
        ]);

    } elseif ($action === 'update') {
        $itemId = (int)($input['item_id'] ?? 0);
        $quantity = max(1, (int)($input['quantity'] ?? 1));

        // Verify item belongs to user cart
        $stmt = $db->prepare("
            SELECT ci.id, ci.variant_id, p.stock as prod_stock, pv.stock as var_stock 
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_variants pv ON ci.variant_id = pv.id
            WHERE ci.id = ? AND ci.cart_id = ? LIMIT 1
        ");
        $stmt->execute([$itemId, $cartId]);
        $item = $stmt->fetch();

        if (!$item) {
            json_response(['success' => false, 'message' => 'Item keranjang tidak ditemukan.']);
        }

        $maxStock = $item['variant_id'] ? (int)$item['var_stock'] : (int)$item['prod_stock'];
        if ($quantity > $maxStock) {
            json_response(['success' => false, 'message' => "Stok maksimal: {$maxStock} pcs."]);
        }

        $stmt = $db->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$quantity, $itemId]);

        $cartCount = get_user_cart_count($userId);
        json_response(['success' => true, 'cart_count' => $cartCount]);

    } elseif ($action === 'remove') {
        $itemId = (int)($input['item_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
        $stmt->execute([$itemId, $cartId]);

        $cartCount = get_user_cart_count($userId);
        json_response(['success' => true, 'cart_count' => $cartCount]);

    } elseif ($action === 'toggle_select') {
        $itemId = (int)($input['item_id'] ?? 0);
        $isSelected = !empty($input['is_selected']) ? 1 : 0;

        $stmt = $db->prepare("UPDATE cart_items SET is_selected = ? WHERE id = ? AND cart_id = ?");
        $stmt->execute([$isSelected, $itemId, $cartId]);

        json_response(['success' => true]);

    } else {
        json_response(['success' => false, 'message' => 'Aksi tidak valid.']);
    }

} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Kesalahan server: ' . $e->getMessage()], 500);
}
