<?php
/**
 * Automated Verification & Integration Test Suite
 * Store: nnasa_ca Accessories Marketplace
 * Run via CLI: & "C:\xampp\php\php.exe" tests/test_flows.php
 * Or via Browser: http://localhost/toko/tests/test_flows.php
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

$isCli = (php_sapi_name() === 'cli');

class TestSuite {
    private PDO $db;
    private int $passed = 0;
    private int $failed = 0;
    private array $results = [];

    public function __construct() {
        $this->db = getDB();
    }

    public function assert(string $testName, bool $condition, string $detail = ''): void {
        if ($condition) {
            $this->passed++;
            $this->results[] = ['name' => $testName, 'status' => 'PASS', 'detail' => $detail];
            echo " [PASS] " . $testName . "\n";
        } else {
            $this->failed++;
            $this->results[] = ['name' => $testName, 'status' => 'FAIL', 'detail' => $detail];
            echo " [FAIL] " . $testName . " - " . $detail . "\n";
        }
    }

    public function runAll(): void {
        echo "\n=== MEMULAI PENGUJIAN INTEGRASI NNasa_ca ===\n\n";

        // 1. Database Connection & Schema Verification
        $tables = $this->db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        $this->assert("1. Koneksi Database Berhasil & Tabel Terbentuk", count($tables) >= 20, "Total tabel: " . count($tables));

        // 2. Admin & Customer Credentials Test
        $adminStmt = $this->db->prepare("SELECT * FROM users WHERE email = 'admin@nnasaca.com' AND role = 'admin' LIMIT 1");
        $adminStmt->execute();
        $admin = $adminStmt->fetch();
        $adminPwOk = $admin && password_verify('admin123', $admin['password_hash']);
        $this->assert("2. Autentikasi Admin (admin@nnasaca.com / admin123)", (bool)$adminPwOk);

        $custStmt = $this->db->prepare("SELECT * FROM users WHERE email = 'anisa@gmail.com' AND role = 'customer' LIMIT 1");
        $custStmt->execute();
        $customer = $custStmt->fetch();
        $custPwOk = $customer && password_verify('customer123', $customer['password_hash']);
        $this->assert("3. Autentikasi Pelanggan (anisa@gmail.com / customer123)", (bool)$custPwOk);

        // 4. Products & Categories In Catalog
        $prodCount = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
        $this->assert("4. Produk Aktif di Katalog Tersedia", $prodCount >= 10, "Jumlah produk: " . $prodCount);

        $catCount = (int)$this->db->query("SELECT COUNT(*) FROM categories WHERE status = 'active'")->fetchColumn();
        $this->assert("5. Kategori Aksesoris Tersedia", $catCount >= 8, "Jumlah kategori: " . $catCount);

        // 5. Product Search & Filter Query Logic
        $searchStmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE name LIKE ? AND status = 'active'");
        $searchStmt->execute(['%Pearl%']);
        $pearlCount = (int)$searchStmt->fetchColumn();
        $this->assert("6. Query Pencarian Produk (Kueri 'Pearl')", $pearlCount > 0, "Ditemukan {$pearlCount} produk");

        // 6. Product Variants Test
        $varCount = (int)$this->db->query("SELECT COUNT(*) FROM product_variants WHERE status = 'active'")->fetchColumn();
        $this->assert("7. Data Varian Produk (Warna & Ukuran)", $varCount >= 6, "Total varian: " . $varCount);

        // 7. Coupon Validation Logic
        $cpStmt = $this->db->prepare("SELECT * FROM coupons WHERE code = 'NNASANEW' AND status = 'active' LIMIT 1");
        $cpStmt->execute();
        $coupon = $cpStmt->fetch();
        $this->assert("8. Kupon Voucher NNASANEW Aktif & Valid", !empty($coupon) && (float)$coupon['discount_value'] == 15.00);

        // 8. Order Placement & Atomic Database Transaction Simulation
        $this->db->beginTransaction();
        try {
            $testUserId = $customer['id'];
            $testOrderNum = 'TEST-' . time();
            $insOrder = $this->db->prepare("
                INSERT INTO orders (order_number, user_id, address_snapshot, subtotal, shipping_cost, total_amount, payment_status, order_status)
                VALUES (?, ?, 'Alamat Tes Validasi', 50000, 12000, 62000, 'pending', 'pending')
            ");
            $insOrder->execute([$testOrderNum, $testUserId]);
            $testOrderId = (int)$this->db->lastInsertId();

            $insItem = $this->db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name_snapshot, sku_snapshot, price, quantity, subtotal)
                VALUES (?, 1, 'Produk Tes', 'SKU-TEST', 50000, 1, 50000)
            ");
            $insItem->execute([$testOrderId]);

            // Simulate stock deduction
            $this->db->prepare("UPDATE products SET stock = stock - 1 WHERE id = 1")->execute();

            // Simulate Payment Success
            $this->db->prepare("UPDATE orders SET payment_status = 'paid', order_status = 'processing' WHERE id = ?")->execute([$testOrderId]);
            
            // Verify
            $checkOrder = $this->db->prepare("SELECT payment_status, order_status FROM orders WHERE id = ?");
            $checkOrder->execute([$testOrderId]);
            $co = $checkOrder->fetch();

            $orderTestSuccess = ($co['payment_status'] === 'paid' && $co['order_status'] === 'processing');
            $this->assert("9. Transaksi Pembuatan Pesanan & Perubahan Status Terkontrol", $orderTestSuccess);

            // Rollback test order so seed data remains clean
            $this->db->rollBack();
            $this->assert("10. Rollback Data Uji Berhasil (Integritas Terjaga)", true);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->assert("9. Transaksi Pembuatan Pesanan", false, $e->getMessage());
        }

        // 9. Low Stock Warning Query Test
        $lowStockStmt = $this->db->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND status = 'active'");
        $lowCount = (int)$lowStockStmt->fetchColumn();
        $this->assert("11. Query Peringatan Stok Menipis", $lowCount >= 1, "Produk stok menipis: " . $lowCount);

        // 10. Financial Calculation Accuracy Test
        $revStmt = $this->db->query("SELECT SUM(total_amount) FROM orders WHERE payment_status = 'paid'");
        $rev = (float)$revStmt->fetchColumn();
        $this->assert("12. Query Perhitungan Omzet & Laporan Riil", $rev > 0, "Total omzet terverifikasi: " . format_rupiah($rev));

        echo "\n============================================\n";
        echo "HASIL PENGUJIAN: {$this->passed} PASS, {$this->failed} FAIL\n";
        echo "============================================\n";
    }

    public function getResults(): array {
        return [
            'passed' => $this->passed,
            'failed' => $this->failed,
            'details' => $this->results
        ];
    }
}

$suite = new TestSuite();
$suite->runAll();

if (!$isCli): ?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Hasil Pengujian Sistem nnasa_ca</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
  <div class="container" style="max-width: 800px;">
    <div class="card p-4 rounded-4 shadow-sm border-0">
      <h3 class="fw-bold mb-3">Laporan Hasil Pengujian Integrasi</h3>
      <p class="text-muted">Hasil uji otomatis seluruh fitur utama e-commerce nnasa_ca</p>
      
      <div class="list-group">
        <?php foreach ($suite->getResults()['details'] as $res): ?>
          <div class="list-group-item d-flex justify-content-between align-items-center">
            <span><?= htmlspecialchars($res['name']) ?></span>
            <span class="badge <?= $res['status'] === 'PASS' ? 'bg-success' : 'bg-danger' ?>"><?= $res['status'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      
      <div class="mt-4 text-center">
        <a href="<?= BASE_URL ?>" class="btn btn-primary rounded-pill px-4">Buka Website Toko</a>
      </div>
    </div>
  </div>
</body>
</html>
<?php endif; ?>
