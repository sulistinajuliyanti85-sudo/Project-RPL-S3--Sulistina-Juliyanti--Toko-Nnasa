<?php
/**
 * Checkout Page & Order Placement
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';

require_login();
$user = current_user();
$userId = $user['id'];
$db = getDB();

// 1. Fetch user's selected cart items
$stmt = $db->prepare("
    SELECT ci.id as item_id, ci.quantity,
           p.id as product_id, p.name as product_name, p.sku as product_sku, p.price, p.discount_price, p.stock as product_stock, p.weight, p.main_image,
           pv.id as variant_id, pv.variant_name, pv.variant_value, pv.sku as variant_sku, pv.price_adjustment, pv.stock as variant_stock
    FROM carts c
    JOIN cart_items ci ON c.id = ci.cart_id
    JOIN products p ON ci.product_id = p.id
    LEFT JOIN product_variants pv ON ci.variant_id = pv.id
    WHERE c.user_id = ? AND ci.is_selected = 1
    ORDER BY ci.id DESC
");
$stmt->execute([$userId]);
$checkoutItems = $stmt->fetchAll();

if (empty($checkoutItems)) {
    set_flash('warning', 'Tidak ada produk yang dipilih untuk dicheckout.');
    header('Location: ' . BASE_URL . 'cart.php');
    exit;
}

// 2. Fetch user's saved addresses
$stmtAddr = $db->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$stmtAddr->execute([$userId]);
$addresses = $stmtAddr->fetchAll();

// Handle Place Order Submission
$errorMsg = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (!csrf_verify($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Sesi keamanan tidak valid, silakan coba kembali.';
    } else {
        $addressId = (int)($_POST['address_id'] ?? 0);
        $courier = sanitize($_POST['courier'] ?? 'SiCepat');
        $paymentMethod = sanitize($_POST['payment_method'] ?? 'qris');
        $couponCode = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $notes = sanitize($_POST['notes'] ?? '');

        // Fetch selected address
        $stmtSelectedAddr = $db->prepare("SELECT * FROM addresses WHERE id = ? AND user_id = ? LIMIT 1");
        $stmtSelectedAddr->execute([$addressId, $userId]);
        $address = $stmtSelectedAddr->fetch();

        if (!$address) {
            $errorMsg = 'Silakan pilih alamat pengiriman yang valid atau tambahkan alamat baru.';
        } else {
            // Build formatted address snapshot
            $addressSnapshot = $address['recipient_name'] . ' | ' . $address['phone'] . "\n" .
                               $address['address_line'] . ', ' . ($address['village'] ? $address['village'] . ', ' : '') .
                               ($address['district'] ? $address['district'] . ', ' : '') . $address['city'] . ', ' .
                               $address['province'] . ' ' . $address['postal_code'];

            // Calculate shipping cost based on courier
            $shippingCost = match($courier) {
                'JNE' => 15000.00,
                'J&T Express' => 14000.00,
                default => 12000.00 // SiCepat
            };

            // Begin Atomic Database Transaction
            try {
                $db->beginTransaction();

                // Recalculate Subtotal & Validate Stock strictly in server
                $subtotal = 0.00;
                $validatedItems = [];

                foreach ($checkoutItems as $item) {
                    $pid = (int)$item['product_id'];
                    $vid = $item['variant_id'] ? (int)$item['variant_id'] : null;
                    $qty = (int)$item['quantity'];

                    // Lock and re-read product stock
                    $pStmt = $db->prepare("SELECT id, name, sku, price, discount_price, stock, status FROM products WHERE id = ? FOR UPDATE");
                    $pStmt->execute([$pid]);
                    $currProduct = $pStmt->fetch();

                    if (!$currProduct || $currProduct['status'] !== 'active') {
                        throw new Exception("Produk '" . $item['product_name'] . "' sudah tidak tersedia.");
                    }

                    $unitPrice = !empty($currProduct['discount_price']) ? (float)$currProduct['discount_price'] : (float)$currProduct['price'];
                    $availStock = (int)$currProduct['stock'];
                    $skuSnapshot = $currProduct['sku'];
                    $variantSnapshot = null;

                    if ($vid) {
                        $vStmt = $db->prepare("SELECT id, variant_name, variant_value, sku, price_adjustment, stock, status FROM product_variants WHERE id = ? FOR UPDATE");
                        $vStmt->execute([$vid]);
                        $currVariant = $vStmt->fetch();

                        if (!$currVariant || $currVariant['status'] !== 'active') {
                            throw new Exception("Varian produk '" . $item['product_name'] . "' sudah tidak aktif.");
                        }

                        $unitPrice += (float)$currVariant['price_adjustment'];
                        $availStock = (int)$currVariant['stock'];
                        $skuSnapshot = $currVariant['sku'] ?: $currProduct['sku'];
                        $variantSnapshot = $currVariant['variant_name'] . ': ' . $currVariant['variant_value'];
                    }

                    if ($qty > $availStock) {
                        throw new Exception("Stok untuk '" . $item['product_name'] . "' tidak mencukupi (sisa: {$availStock} pcs).");
                    }

                    $lineSubtotal = $unitPrice * $qty;
                    $subtotal += $lineSubtotal;

                    $validatedItems[] = [
                        'product_id' => $pid,
                        'variant_id' => $vid,
                        'product_name' => $currProduct['name'],
                        'sku' => $skuSnapshot,
                        'variant_snapshot' => $variantSnapshot,
                        'price' => $unitPrice,
                        'quantity' => $qty,
                        'subtotal' => $lineSubtotal
                    ];
                }

                // Check and apply coupon
                $discountAmount = 0.00;
                $couponId = null;
                if (!empty($couponCode)) {
                    $cStmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND status = 'active' FOR UPDATE");
                    $cStmt->execute([$couponCode]);
                    $coupon = $cStmt->fetch();

                    if ($coupon) {
                        $now = date('Y-m-d H:i:s');
                        $validDate = (empty($coupon['start_at']) || $now >= $coupon['start_at']) && (empty($coupon['end_at']) || $now <= $coupon['end_at']);
                        $hasQuota = (int)$coupon['used_count'] < (int)$coupon['usage_limit'];
                        $meetsMin = $subtotal >= (float)$coupon['minimum_purchase'];

                        if ($validDate && $hasQuota && $meetsMin) {
                            $couponId = $coupon['id'];
                            if ($coupon['discount_type'] === 'percentage') {
                                $discountAmount = ($subtotal * (float)$coupon['discount_value']) / 100;
                                if (!empty($coupon['maximum_discount']) && $discountAmount > (float)$coupon['maximum_discount']) {
                                    $discountAmount = (float)$coupon['maximum_discount'];
                                }
                            } else {
                                $discountAmount = min($subtotal, (float)$coupon['discount_value']);
                            }
                        }
                    }
                }

                $totalAmount = max(0, ($subtotal - $discountAmount) + $shippingCost);

                // Generate Unique Order Number: NNA-YYYYMMDD-XXXX
                $datePrefix = date('Ymd');
                $randomSuffix = strtoupper(bin2hex(random_bytes(2)));
                $orderNumber = "NNA-{$datePrefix}-{$randomSuffix}";

                // Insert into orders
                $orderStmt = $db->prepare("
                    INSERT INTO orders (
                        order_number, user_id, address_snapshot, subtotal, discount_amount, 
                        shipping_cost, total_amount, payment_status, order_status, notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?)
                ");
                $orderStmt->execute([
                    $orderNumber, $userId, $addressSnapshot, $subtotal, $discountAmount,
                    $shippingCost, $totalAmount, $notes
                ]);
                $orderId = (int)$db->lastInsertId();

                // Insert order items & reduce stock & record movements
                $orderItemStmt = $db->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, variant_id, product_name_snapshot, 
                        sku_snapshot, variant_snapshot, price, quantity, subtotal
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stockProdStmt = $db->prepare("UPDATE products SET stock = stock - ?, sales_count = sales_count + ? WHERE id = ?");
                $stockVarStmt = $db->prepare("UPDATE product_variants SET stock = stock - ? WHERE id = ?");
                $stockMoveStmt = $db->prepare("
                    INSERT INTO stock_movements (
                        product_id, variant_id, movement_type, quantity, reference_type, reference_id, notes, created_by
                    ) VALUES (?, ?, 'sale', ?, 'order', ?, ?, ?)
                ");

                foreach ($validatedItems as $vItem) {
                    $orderItemStmt->execute([
                        $orderId, $vItem['product_id'], $vItem['variant_id'], $vItem['product_name'],
                        $vItem['sku'], $vItem['variant_snapshot'], $vItem['price'], $vItem['quantity'], $vItem['subtotal']
                    ]);

                    // Decrement product stock
                    $stockProdStmt->execute([$vItem['quantity'], $vItem['quantity'], $vItem['product_id']]);

                    // Decrement variant stock if applicable
                    if ($vItem['variant_id']) {
                        $stockVarStmt->execute([$vItem['quantity'], $vItem['variant_id']]);
                    }

                    // Stock movement record
                    $stockMoveStmt->execute([
                        $vItem['product_id'], $vItem['variant_id'], -$vItem['quantity'], $orderId,
                        'Pengurangan stok pesanan ' . $orderNumber, $userId
                    ]);
                }

                // If coupon applied, record coupon usage and increment used_count
                if ($couponId) {
                    $db->prepare("INSERT INTO coupon_usages (coupon_id, user_id, order_id) VALUES (?, ?, ?)")
                       ->execute([$couponId, $userId, $orderId]);
                    $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")
                       ->execute([$couponId]);
                }

                // Insert Payment record
                $txRef = strtoupper($paymentMethod) . '-' . date('Ymd') . '-' . bin2hex(random_bytes(3));
                $db->prepare("
                    INSERT INTO payments (order_id, payment_method, transaction_reference, amount, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ")->execute([$orderId, $paymentMethod, $txRef, $totalAmount]);

                // Insert Shipment record
                $db->prepare("
                    INSERT INTO shipments (order_id, courier, service_type, shipping_status)
                    VALUES (?, ?, 'REG', 'preparing')
                ")->execute([$orderId, $courier]);

                // Record initial Order Status History
                $db->prepare("
                    INSERT INTO order_status_history (order_id, status, comment, created_by)
                    VALUES (?, 'pending', 'Pesanan dibuat. Menunggu konfirmasi pembayaran.', ?)
                ")->execute([$orderId, $userId]);

                // Remove selected items from cart
                $db->prepare("
                    DELETE ci FROM cart_items ci
                    JOIN carts c ON ci.cart_id = c.id
                    WHERE c.user_id = ? AND ci.is_selected = 1
                ")->execute([$userId]);

                // Log Activity
                log_activity($userId, 'checkout_success', 'orders', $orderId, 'Customer checked out order ' . $orderNumber);

                $db->commit();

                // Dispatch WhatsApp order created notification to customer's exact order phone
                try {
                    require_once __DIR__ . '/app/Services/WhatsApp/WhatsAppService.php';
                    $waService = new WhatsAppService($db);
                    $waService->sendOrderNotification($orderId, 'order_created');
                } catch (Exception $waEx) {
                    // Fail silently to not disrupt customer checkout
                }

                // Redirect to Payment Gateway Simulator
                header('Location: ' . BASE_URL . 'payment.php?order_id=' . $orderId);
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errorMsg = 'Gagal memproses pesanan: ' . $e->getMessage();
            }
        }
    }
}

// Compute checkout subtotal for initial render
$subtotal = 0;
foreach ($checkoutItems as $item) {
    $effPrice = !empty($item['discount_price']) ? (float)$item['discount_price'] : (float)$item['price'];
    if ($item['variant_id']) {
        $effPrice += (float)$item['price_adjustment'];
    }
    $subtotal += ($effPrice * (int)$item['quantity']);
}
$defaultShipping = 12000.00;

$pageTitle = 'Checkout Pesanan';
require_once __DIR__ . '/templates/header.php';
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>cart.php" class="text-decoration-none text-muted">Keranjang</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Checkout</li>
    </ol>
  </nav>

  <h1 class="h3 fw-bold text-dark mb-4 brand-font">Checkout Pengiriman & Pembayaran</h1>

  <?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errorMsg) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <form action="<?= BASE_URL ?>checkout.php" method="POST" id="checkoutForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="place_order">

    <div class="row g-4">
      <!-- Left Column: Address, Courier, Payment Methods -->
      <div class="col-lg-8">
        
        <!-- 1. Alamat Pengiriman -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark fs-6 mb-0">
              <i class="bi bi-geo-alt-fill text-primary-pink me-2"></i> 1. Alamat Pengiriman
            </h5>
            <a href="<?= BASE_URL ?>addresses.php?redirect=checkout.php" class="btn btn-sm btn-outline-secondary rounded-pill">
              <i class="bi bi-plus-lg me-1"></i> Tambah Alamat Baru
            </a>
          </div>

          <?php if (empty($addresses)): ?>
            <div class="p-3 bg-light rounded-3 text-center border">
              <p class="text-muted small mb-2">Kamu belum memiliki alamat pengiriman tersimpan.</p>
              <a href="<?= BASE_URL ?>addresses.php?redirect=checkout.php" class="btn btn-sm btn-nnasa">Tambah Alamat Sekarang</a>
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($addresses as $idx => $addr): ?>
                <label class="p-3 border rounded-3 d-flex align-items-start gap-3 cursor-pointer address-option <?= ($addr['is_default'] || $idx === 0) ? 'border-pink bg-light-pink' : '' ?>" style="cursor: pointer;">
                  <input type="radio" name="address_id" value="<?= $addr['id'] ?>" class="form-check-input mt-1" <?= ($addr['is_default'] || $idx === 0) ? 'checked' : '' ?> required>
                  <div class="flex-grow-1 small">
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <strong class="text-dark"><?= htmlspecialchars($addr['recipient_name']) ?></strong>
                      <span class="text-muted">(<?= htmlspecialchars($addr['phone']) ?>)</span>
                      <?php if ($addr['is_default']): ?>
                        <span class="badge bg-primary-pink text-white" style="font-size: 0.68rem;">Utama</span>
                      <?php endif; ?>
                    </div>
                    <div class="text-secondary">
                      <?= htmlspecialchars($addr['address_line']) ?>, 
                      <?= $addr['village'] ? htmlspecialchars($addr['village']) . ', ' : '' ?>
                      <?= $addr['district'] ? htmlspecialchars($addr['district']) . ', ' : '' ?>
                      <?= htmlspecialchars($addr['city']) ?>, <?= htmlspecialchars($addr['province']) ?> <?= htmlspecialchars($addr['postal_code']) ?>
                    </div>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- 2. Ekspedisi & Kurir Pengiriman -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <h5 class="fw-bold text-dark fs-6 mb-3">
            <i class="bi bi-truck text-primary-pink me-2"></i> 2. Opsi Pengiriman
          </h5>

          <div class="row g-3">
            <div class="col-md-4">
              <label class="p-3 border rounded-3 d-flex align-items-center gap-3 w-100 cursor-pointer courier-card active" style="cursor: pointer;">
                <input type="radio" name="courier" value="SiCepat" class="form-check-input" checked onchange="updateShipping(12000, 'SiCepat')">
                <div>
                  <div class="fw-bold text-dark small">SiCepat REG</div>
                  <div class="text-muted" style="font-size: 0.75rem;">Estimasi 1-2 hari</div>
                  <div class="fw-bold text-primary-pink small mt-1">Rp 12.000</div>
                </div>
              </label>
            </div>
            <div class="col-md-4">
              <label class="p-3 border rounded-3 d-flex align-items-center gap-3 w-100 cursor-pointer courier-card" style="cursor: pointer;">
                <input type="radio" name="courier" value="JNE" class="form-check-input" onchange="updateShipping(15000, 'JNE')">
                <div>
                  <div class="fw-bold text-dark small">JNE Reguler</div>
                  <div class="text-muted" style="font-size: 0.75rem;">Estimasi 2-3 hari</div>
                  <div class="fw-bold text-primary-pink small mt-1">Rp 15.000</div>
                </div>
              </label>
            </div>
            <div class="col-md-4">
              <label class="p-3 border rounded-3 d-flex align-items-center gap-3 w-100 cursor-pointer courier-card" style="cursor: pointer;">
                <input type="radio" name="courier" value="J&T Express" class="form-check-input" onchange="updateShipping(14000, 'J&T Express')">
                <div>
                  <div class="fw-bold text-dark small">J&T Express</div>
                  <div class="text-muted" style="font-size: 0.75rem;">Estimasi 1-3 hari</div>
                  <div class="fw-bold text-primary-pink small mt-1">Rp 14.000</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- 3. Metode Pembayaran (DEMO Simulator Ready) -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark fs-6 mb-0">
              <i class="bi bi-wallet2 text-primary-pink me-2"></i> 3. Metode Pembayaran
            </h5>
            <span class="badge bg-warning text-dark border">Mode Simulasi DEMO Aktif</span>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="p-3 border rounded-3 d-flex align-items-center gap-3 w-100 cursor-pointer" style="cursor: pointer;">
                <input type="radio" name="payment_method" value="qris" class="form-check-input" checked>
                <div>
                  <div class="fw-bold text-dark small"><i class="bi bi-qr-code me-1 text-primary-pink"></i> QRIS (Semua E-Wallet & Mobile Banking)</div>
                  <small class="text-muted">BCA, Mandiri, GoPay, OVO, ShopeePay</small>
                </div>
              </label>
            </div>
            <div class="col-md-6">
              <label class="p-3 border rounded-3 d-flex align-items-center gap-3 w-100 cursor-pointer" style="cursor: pointer;">
                <input type="radio" name="payment_method" value="va_bca" class="form-check-input">
                <div>
                  <div class="fw-bold text-dark small"><i class="bi bi-bank me-1 text-primary-pink"></i> BCA Virtual Account</div>
                  <small class="text-muted">Konfirmasi instan otomatis</small>
                </div>
              </label>
            </div>
            <div class="col-md-6">
              <label class="p-3 border rounded-3 d-flex align-items-center gap-3 w-100 cursor-pointer" style="cursor: pointer;">
                <input type="radio" name="payment_method" value="va_mandiri" class="form-check-input">
                <div>
                  <div class="fw-bold text-dark small"><i class="bi bi-bank me-1 text-primary-pink"></i> Mandiri Virtual Account</div>
                  <small class="text-muted">Konfirmasi instan otomatis</small>
                </div>
              </label>
            </div>
            <div class="col-md-6">
              <label class="p-3 border rounded-3 d-flex align-items-center gap-3 w-100 cursor-pointer" style="cursor: pointer;">
                <input type="radio" name="payment_method" value="gopay" class="form-check-input">
                <div>
                  <div class="fw-bold text-dark small"><i class="bi bi-phone me-1 text-primary-pink"></i> GoPay / GoPay Later</div>
                  <small class="text-muted">Bayar instan via aplikasi</small>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- 4. Catatan Pesanan -->
        <div class="card border-0 shadow-sm rounded-4 p-4" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <h5 class="fw-bold text-dark fs-6 mb-2">
            <i class="bi bi-pencil-square text-primary-pink me-2"></i> 4. Catatan Pesanan (Opsional)
          </h5>
          <textarea name="notes" class="form-control rounded-3" rows="2" placeholder="Contoh: Titip di pos satpam, request bungkus hampers, atau ucapan ultah..."></textarea>
        </div>

      </div>

      <!-- Right Column: Order Summary & Coupon -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 80px; background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <h5 class="fw-bold text-dark fs-6 mb-3">Ringkasan Pesanan</h5>

          <!-- Items Mini List -->
          <div class="mb-3" style="max-height: 220px; overflow-y: auto;">
            <?php foreach ($checkoutItems as $it): ?>
              <?php
                $itemPrice = !empty($it['discount_price']) ? (float)$it['discount_price'] : (float)$it['price'];
                if ($it['variant_id']) {
                    $itemPrice += (float)$it['price_adjustment'];
                }
              ?>
              <div class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
                <img src="<?= get_image_url('products', $it['main_image'], $it['product_name']) ?>" class="rounded-2 border flex-shrink-0" style="width: 45px; height: 45px; object-fit: cover;">
                <div class="flex-grow-1 min-w-0 small">
                  <div class="text-truncate fw-semibold text-dark"><?= htmlspecialchars($it['product_name']) ?></div>
                  <small class="text-muted"><?= $it['quantity'] ?>x <?= format_rupiah($itemPrice) ?></small>
                </div>
                <div class="small fw-bold text-dark flex-shrink-0">
                  <?= format_rupiah($itemPrice * (int)$it['quantity']) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <!-- Coupon Input Form -->
          <div class="mb-3">
            <label class="form-label small fw-bold text-muted text-uppercase">Voucher Diskon</label>
            <div class="input-group">
              <input type="text" id="couponInput" name="coupon_code" class="form-control form-control-sm text-uppercase" placeholder="Kode Voucher">
              <button type="button" class="btn btn-sm btn-nnasa-outline" onclick="applyCoupon()">Gunakan</button>
            </div>
            <div id="couponFeedback" class="small mt-1"></div>
          </div>

          <hr class="my-3 opacity-25">

          <!-- Price Calculations -->
          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>Subtotal Produk</span>
            <span class="fw-semibold text-dark"><?= format_rupiah($subtotal) ?></span>
          </div>

          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>Ongkos Kirim</span>
            <span id="shippingDisplay" class="fw-semibold text-dark"><?= format_rupiah($defaultShipping) ?></span>
          </div>

          <div class="d-flex justify-content-between small text-success mb-3" id="discountRow" style="display: none !important;">
            <span>Diskon Voucher</span>
            <span id="discountDisplay" class="fw-bold">-Rp 0</span>
          </div>

          <hr class="my-3 opacity-25">

          <div class="d-flex justify-content-between align-items-baseline mb-4">
            <span class="fw-bold text-dark">Total Pembayaran</span>
            <span id="grandTotalDisplay" class="h4 fw-bold text-primary-pink mb-0">
              <?= format_rupiah($subtotal + $defaultShipping) ?>
            </span>
          </div>

          <button type="submit" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill text-center shadow-sm" <?= empty($addresses) ? 'disabled' : '' ?>>
            Buat Pesanan Sekarang <i class="bi bi-shield-lock-fill ms-1"></i>
          </button>

          <?php if (empty($addresses)): ?>
            <div class="alert alert-warning small mt-3 mb-0 p-2 text-center rounded-3">
              Tambahkan alamat terlebih dahulu sebelum membuat pesanan.
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </form>
</div>

<script>
  let baseSubtotal = <?= $subtotal ?>;
  let currentShipping = <?= $defaultShipping ?>;
  let currentDiscount = 0;

  function updateShipping(cost, courierName) {
    currentShipping = cost;
    document.getElementById('shippingDisplay').innerText = 'Rp ' + cost.toLocaleString('id-ID');
    recalculateTotal();
  }

  function recalculateTotal() {
    const total = Math.max(0, (baseSubtotal - currentDiscount) + currentShipping);
    document.getElementById('grandTotalDisplay').innerText = 'Rp ' + total.toLocaleString('id-ID');
  }

  function applyCoupon() {
    const code = document.getElementById('couponInput').value.trim();
    const feedback = document.getElementById('couponFeedback');
    
    if (!code) {
      feedback.className = 'small text-danger mt-1';
      feedback.innerText = 'Ketikkan kode kupon.';
      return;
    }

    fetch(window.BASE_URL + 'api/coupon.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        code: code,
        subtotal: baseSubtotal
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        currentDiscount = parseFloat(data.discount_amount) || 0;
        feedback.className = 'small text-success mt-1 fw-semibold';
        feedback.innerText = data.message;
        
        document.getElementById('discountRow').style.setProperty('display', 'flex', 'important');
        document.getElementById('discountDisplay').innerText = '-' + data.discount_formatted;
        recalculateTotal();
      } else {
        feedback.className = 'small text-danger mt-1';
        feedback.innerText = data.message;
        currentDiscount = 0;
        document.getElementById('discountRow').style.setProperty('display', 'none', 'important');
        recalculateTotal();
      }
    })
    .catch(err => {
      feedback.className = 'small text-danger mt-1';
      feedback.innerText = 'Terjadi kesalahan sistem voucher.';
    });
  }
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
