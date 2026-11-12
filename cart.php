<?php
/**
 * Shopping Cart Page
 * Store: nnasa_ca Accessories Marketplace
 */

$pageTitle = 'Keranjang Belanja';
require_once __DIR__ . '/templates/header.php';

require_login();
$user = current_user();
$userId = $user['id'];
$db = getDB();

// Fetch user's cart and items with fresh product details
$stmt = $db->prepare("
    SELECT ci.id as item_id, ci.quantity, ci.is_selected,
           p.id as product_id, p.name as product_name, p.slug, p.price, p.discount_price, p.stock as product_stock, p.main_image,
           pv.id as variant_id, pv.variant_name, pv.variant_value, pv.price_adjustment, pv.stock as variant_stock
    FROM carts c
    JOIN cart_items ci ON c.id = ci.cart_id
    JOIN products p ON ci.product_id = p.id
    LEFT JOIN product_variants pv ON ci.variant_id = pv.id
    WHERE c.user_id = ?
    ORDER BY ci.id DESC
");
$stmt->execute([$userId]);
$cartItems = $stmt->fetchAll();

// Calculate subtotal of selected items
$subtotal = 0;
$totalSelectedItems = 0;

foreach ($cartItems as $item) {
    if ($item['is_selected']) {
        $effectivePrice = !empty($item['discount_price']) ? (float)$item['discount_price'] : (float)$item['price'];
        if ($item['variant_id']) {
            $effectivePrice += (float)$item['price_adjustment'];
        }
        $subtotal += ($effectivePrice * (int)$item['quantity']);
        $totalSelectedItems += (int)$item['quantity'];
    }
}
?>

<div class="container my-4">
  <!-- Breadcrumb -->
  <nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-decoration-none text-muted">Beranda</a></li>
      <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Keranjang Belanja</li>
    </ol>
  </nav>

  <h1 class="h3 fw-bold text-dark mb-4 brand-font">Keranjang Belanja Kamu 🛍️</h1>

  <?php if (empty($cartItems)): ?>
    <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm">
      <i class="bi bi-bag-x text-muted opacity-50" style="font-size: 4rem;"></i>
      <h4 class="fw-bold mt-3">Keranjang Belanja Masih Kosong</h4>
      <p class="text-muted small mb-4">Yuk, cari aksesoris cantik dan jadikan gayamu lebih memikat!</p>
      <a href="<?= BASE_URL ?>products.php" class="btn btn-nnasa px-4 py-2">Mulai Belanja Sekarang</a>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <!-- Items List -->
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-3" style="background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-bold small text-dark">Daftar Produk (<?= count($cartItems) ?> item)</span>
            <small class="text-muted">Centang produk yang ingin dicheckout</small>
          </div>

          <div class="card-body p-0">
            <?php foreach ($cartItems as $it): ?>
              <?php
                $itemPrice = !empty($it['discount_price']) ? (float)$it['discount_price'] : (float)$it['price'];
                if ($it['variant_id']) {
                    $itemPrice += (float)$it['price_adjustment'];
                }
                $itemSubtotal = $itemPrice * (int)$it['quantity'];
                $availableStock = $it['variant_id'] ? (int)$it['variant_stock'] : (int)$it['product_stock'];
              ?>
              <div class="p-3 border-bottom d-flex align-items-center gap-3 item-row" id="cart-row-<?= $it['item_id'] ?>">
                <!-- Checkbox -->
                <div class="form-check">
                  <input class="form-check-input item-checkbox" type="checkbox" value="<?= $it['item_id'] ?>" <?= $it['is_selected'] ? 'checked' : '' ?> onchange="toggleSelect(<?= $it['item_id'] ?>, this.checked)">
                </div>

                <!-- Product Thumbnail -->
                <a href="<?= BASE_URL ?>product-detail.php?id=<?= $it['product_id'] ?>" class="flex-shrink-0 rounded-3 overflow-hidden border" style="width: 80px; height: 80px;">
                  <img src="<?= get_image_url('products', $it['main_image'], $it['product_name']) ?>" alt="<?= htmlspecialchars($it['product_name']) ?>" class="w-100 h-100 object-fit-cover">
                </a>

                <!-- Product Details -->
                <div class="flex-grow-1 min-w-0">
                  <a href="<?= BASE_URL ?>product-detail.php?id=<?= $it['product_id'] ?>" class="fw-semibold text-dark text-decoration-none d-block text-truncate small mb-1">
                    <?= htmlspecialchars($it['product_name']) ?>
                  </a>

                  <?php if ($it['variant_id']): ?>
                    <span class="badge bg-light text-muted border small mb-2">
                      <?= htmlspecialchars($it['variant_name'] ?? 'Varian') ?>: <?= htmlspecialchars($it['variant_value']) ?>
                    </span>
                  <?php endif; ?>

                  <div class="d-flex align-items-baseline gap-2">
                    <span class="fw-bold text-primary-pink small"><?= format_rupiah($itemPrice) ?></span>
                    <?php if (!empty($it['discount_price'])): ?>
                      <small class="text-decoration-line-through text-muted" style="font-size: 0.75rem;"><?= format_rupiah($it['price']) ?></small>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Quantity Stepper -->
                <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                  <div class="quantity-stepper">
                    <button type="button" class="btn-minus" onclick="updateQty(<?= $it['item_id'] ?>, -1)"><i class="bi bi-dash"></i></button>
                    <input type="number" id="qty-input-<?= $it['item_id'] ?>" class="qty-input" value="<?= $it['quantity'] ?>" min="1" max="<?= $availableStock ?>" readonly>
                    <button type="button" class="btn-plus" onclick="updateQty(<?= $it['item_id'] ?>, 1, <?= $availableStock ?>)"><i class="bi bi-plus"></i></button>
                  </div>

                  <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0" onclick="removeItem(<?= $it['item_id'] ?>)" title="Hapus produk">
                    <i class="bi bi-trash3 me-1"></i> <small>Hapus</small>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Summary Box & Checkout -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 p-4 sticky-top" style="top: 80px; background: #ffffff; border: 1px solid var(--border-subtle) !important;">
          <h5 class="fw-bold text-dark fs-6 mb-3">Ringkasan Belanja</h5>

          <div class="d-flex justify-content-between small text-muted mb-2">
            <span>Total Item Terpilih</span>
            <span id="summaryItemCount" class="fw-semibold text-dark"><?= $totalSelectedItems ?> pcs</span>
          </div>

          <div class="d-flex justify-content-between small text-muted mb-3">
            <span>Subtotal Produk</span>
            <span id="summarySubtotal" class="fw-bold text-dark fs-6"><?= format_rupiah($subtotal) ?></span>
          </div>

          <div class="p-3 bg-light rounded-3 small text-muted mb-4 border">
            <i class="bi bi-info-circle text-primary-pink me-1"></i>
            Ongkos kirim dan kupon diskon dapat dipilih pada tahap selanjutnya (Checkout).
          </div>

          <hr class="my-3 opacity-25">

          <div class="d-flex justify-content-between align-items-baseline mb-4">
            <span class="fw-bold text-dark">Estimasi Total</span>
            <span id="summaryTotal" class="h4 fw-bold text-primary-pink mb-0"><?= format_rupiah($subtotal) ?></span>
          </div>

          <?php if ($totalSelectedItems > 0): ?>
            <a href="<?= BASE_URL ?>checkout.php" class="btn btn-nnasa w-100 py-3 fw-bold rounded-pill text-center shadow-sm">
              Lanjut ke Checkout <i class="bi bi-arrow-right ms-2"></i>
            </a>
          <?php else: ?>
            <button class="btn btn-secondary w-100 py-3 fw-bold rounded-pill text-center" disabled>
              Pilih Item Terlebih Dahulu
            </button>
          <?php endif; ?>

          <div class="text-center mt-3">
            <a href="<?= BASE_URL ?>products.php" class="text-decoration-none small text-muted">
              <i class="bi bi-arrow-left me-1"></i> Lanjut Belanja Aksesoris Lain
            </a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  function updateQty(itemId, change, maxStock = 999) {
    const input = document.getElementById('qty-input-' + itemId);
    let curVal = parseInt(input.value) || 1;
    let newVal = curVal + change;

    if (newVal < 1) return;
    if (newVal > maxStock) {
      showToast('Jumlah maksimal stok tersedia: ' + maxStock + ' pcs.', 'warning');
      return;
    }

    input.value = newVal;

    fetch(window.BASE_URL + 'api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'update',
        item_id: itemId,
        quantity: newVal
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      } else {
        showToast(data.message || 'Gagal mengubah kuantitas.', 'danger');
      }
    });
  }

  function removeItem(itemId) {
    if (!confirm('Yakin ingin menghapus produk ini dari keranjang?')) return;

    fetch(window.BASE_URL + 'api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'remove',
        item_id: itemId
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast('Produk dihapus dari keranjang.', 'info');
        setTimeout(() => window.location.reload(), 400);
      }
    });
  }

  function toggleSelect(itemId, isSelected) {
    fetch(window.BASE_URL + 'api/cart.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'toggle_select',
        item_id: itemId,
        is_selected: isSelected ? 1 : 0
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        window.location.reload();
      }
    });
  }
</script>

<?php require_once __DIR__ . '/templates/footer.php'; ?>
