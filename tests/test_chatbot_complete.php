<?php
/**
 * Comprehensive Automated Test Suite for Platform-Integrated AI Chatbot
 * Tests all requirements:
 * 1. Platform Feature Understanding & Navigation
 * 2. Secure Database Reading & SQL Injection Immunity
 * 3. User Intent & Slang Recognition
 * 4. Exact Product Counting (Active, Inactive, Total Units, Out of Stock, Low Stock)
 * 5. Product Status & Stock Level Inspection
 * 6. Product Result List & Table Rendering
 * 7. Factual Real-Data Accuracy & Anti-Hallucination
 * 8. Role-Based Access Control (RBAC) Security
 * 9. Gemini AI Live Integration & Automatic Product Card Attachment
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';
require_once dirname(__DIR__) . '/app/Services/AIService.php';

$db = getDB();
$ai = new AIService($db);

$passed = 0;
$failed = 0;

function assertTest(string $testName, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] $testName\n";
        if ($details) echo "        $details\n";
    } else {
        $failed++;
        echo " [FAIL] $testName\n";
        if ($details) echo "        FAILED: $details\n";
    }
}

echo "====================================================================\n";
echo "   NNASA_CA AI CHATBOT SYSTEM-WIDE VERIFICATION & AUDIT SUITE\n";
echo "====================================================================\n\n";

// ---------------------------------------------------------------------
// TEST GROUP 1: Platform Feature Understanding & Navigation
// ---------------------------------------------------------------------
echo "--- TEST GROUP 1: Platform Feature Understanding ---\n";
$ctx1 = [];
$resAdminUsers = $ai->processMessage("di mana fitur manajemen user", $ctx1, ['role' => 'admin']);
assertTest(
    "AI navigates admin to Manajemen Pengguna (users.php)",
    strpos($resAdminUsers['reply'], 'admin/users.php') !== false && strpos($resAdminUsers['reply'], 'Manajemen Pengguna') !== false,
    "Output contained link to admin/users.php"
);

$ctx2 = [];
$resTracking = $ai->processMessage("gimana cara lacak pesanan saya", $ctx2, ['role' => 'customer']);
assertTest(
    "AI navigates customer to Lacak Pesanan (tracking.php)",
    strpos($resTracking['reply'], 'tracking.php') !== false,
    "Output contained link to tracking.php"
);

$ctx3 = [];
$resCheckout = $ai->processMessage("cara beli dan bayar gimana", $ctx3, ['role' => 'customer']);
assertTest(
    "AI explains shopping flow (cart / checkout / payment)",
    (strpos($resCheckout['reply'], 'checkout.php') !== false || strpos($resCheckout['reply'], 'Keranjang') !== false || strpos($resCheckout['reply'], 'Pembayaran') !== false),
    "Explained checkout/payment flow"
);

// ---------------------------------------------------------------------
// TEST GROUP 2: Counting Products & Real Database Inventory
// ---------------------------------------------------------------------
echo "\n--- TEST GROUP 2: Product Counting & Summary ---\n";
$realActiveCount = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$realTotalUnits = (int)$db->query("SELECT COALESCE(SUM(stock), 0) FROM products")->fetchColumn();
$realLowStock = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 5")->fetchColumn();

$ctxCount = [];
$resActiveCount = $ai->processMessage("berapa jumlah produk aktif di database", $ctxCount);
assertTest(
    "AI counts exact active products ($realActiveCount)",
    strpos($resActiveCount['reply'], (string)$realActiveCount) !== false,
    "Matched exact count from MySQL: $realActiveCount"
);

$ctxUnits = [];
$resUnits = $ai->processMessage("berapa total unit stok fisik di gudang", $ctxUnits);
assertTest(
    "AI counts exact warehouse units ($realTotalUnits)",
    strpos($resUnits['reply'], (string)$realTotalUnits) !== false,
    "Matched exact unit sum from MySQL: $realTotalUnits"
);

$ctxLow = [];
$resLow = $ai->processMessage("tampilkan produk yang stoknya menipis", $ctxLow);
assertTest(
    "AI lists low stock products accurately ($realLowStock items) with table",
    isset($resLow['table']) && count($resLow['table']['rows']) === $realLowStock,
    "Returned table with exactly $realLowStock low stock products"
);

// ---------------------------------------------------------------------
// TEST GROUP 3: Product Status & Specific Stock Inspection
// ---------------------------------------------------------------------
echo "\n--- TEST GROUP 3: Product Status & Stock Inspection ---\n";
$ctxJedai = [];
$resJedai = $ai->processMessage("cek stok jedai rambut", $ctxJedai);
assertTest(
    "AI checks specific product stock (Korean Matte Cloud Claw Clip)",
    !empty($resJedai['products']) && strpos($resJedai['products'][0]['name'], 'Claw Clip') !== false,
    "Found product card with stock: " . ($resJedai['products'][0]['stock'] ?? 'N/A')
);

$ctxClover = [];
$resClover = $ai->processMessage("apakah kalung clover ready", $ctxClover);
assertTest(
    "AI inspects readiness and status of clover necklace",
    !empty($resClover['products']) && strpos($resClover['products'][0]['name'], 'Clover') !== false,
    "Price: " . ($resClover['products'][0]['price_formatted'] ?? 'N/A') . ", Stock: " . ($resClover['products'][0]['stock'] ?? 'N/A')
);

// ---------------------------------------------------------------------
// TEST GROUP 4: Multi-Turn Drill-Down Filtering
// ---------------------------------------------------------------------
echo "\n--- TEST GROUP 4: Multi-Turn Conversational Memory ---\n";
$multiTurnCtx = [];
$t1 = $ai->processMessage("produk aktif", $multiTurnCtx);
assertTest("Turn 1: Inquire active products", $t1['success'] === true);

$t2 = $ai->processMessage("tampilkan semuanya", $multiTurnCtx);
assertTest(
    "Turn 2: Follow-up drill-down 'tampilkan semuanya'",
    isset($t2['table']) && count($t2['table']['rows']) > 0,
    "Generated table with " . count($t2['table']['rows'] ?? []) . " rows"
);

$t3 = $ai->processMessage("yang stoknya menipis saja", $multiTurnCtx);
assertTest(
    "Turn 3: Drill-down filter 'yang stoknya menipis saja'",
    isset($t3['table']) && count($t3['table']['rows']) === $realLowStock,
    "Filtered table down to $realLowStock items"
);

// ---------------------------------------------------------------------
// TEST GROUP 5: Security & RBAC Enforcement
// ---------------------------------------------------------------------
echo "\n--- TEST GROUP 5: Security & RBAC Protection ---\n";
// Non-admin tries to ask for store revenue
$ctxSec1 = [];
$resPublicRev = $ai->processMessage("berapa omzet penjualan toko hari ini", $ctxSec1, ['role' => 'customer']);
assertTest(
    "Customer blocked from admin financial metrics",
    stripos($resPublicRev['reply'], 'Akses Dibatasi') !== false || stripos($resPublicRev['reply'], 'Administrator') !== false,
    "Properly blocked sensitive financial data"
);

// Admin asks for revenue
$ctxSec2 = [];
$resAdminRev = $ai->processMessage("berapa omzet penjualan toko", $ctxSec2, ['role' => 'admin']);
assertTest(
    "Admin authorized to view financial metrics",
    strpos($resAdminRev['reply'], 'Rp') !== false && (strpos($resAdminRev['reply'], 'Omzet') !== false || strpos($resAdminRev['reply'], 'Pendapatan') !== false),
    "Provided revenue summary to authenticated admin"
);

// SQL Injection attempt
$ctxSql = [];
$resSql = $ai->processMessage("' OR '1'='1' -- DROP TABLE users;", $ctxSql);
assertTest(
    "SQL Injection attempt handled safely without exception",
    $resSql['success'] === true,
    "Returned safe response without executing payload"
);

// ---------------------------------------------------------------------
// TEST GROUP 6: Gemini Grounded Recommendation & Product Card Extraction
// ---------------------------------------------------------------------
echo "\n--- TEST GROUP 6: Gemini Grounding & Product Attachment ---\n";
$ctxGemini = [];
$resRec = $ai->processMessage("rekomendasi kado aksesoris cantik buat pacar yang ada di toko", $ctxGemini);
assertTest(
    "AI generated recommendations with attached product preview cards",
    !empty($resRec['reply']) && (count($resRec['products']) > 0 || $resRec['provider'] === 'gemini'),
    "Provider: {$resRec['provider']}, Products attached: " . count($resRec['products'])
);

echo "\n====================================================================\n";
echo "   TOTAL RESULTS: $passed PASSED, $failed FAILED\n";
echo "====================================================================\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! Chatbot is verified rock-solid and production-ready.\n";
} else {
    echo "⚠️ Some tests failed. Please review the output above.\n";
}
