<?php
/**
 * Master Platform Integration Test Suite for NNasa AI Assistant
 * Verifies all 16 Product Intelligence questions, Multi-Turn Drill-down,
 * Feature Registry Navigation, Order Intelligence, and Role-Based Access Control (RBAC).
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';
require_once dirname(__DIR__) . '/app/Services/AIService.php';

echo "=== MEMULAI MASTER PENGUJIAN AI TERINTEGRASI SELURUH PLATFORM ===\n\n";

$db = getDB();
$ai = new AIService($db);
$context = [];
$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition, string $detail = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] {$desc}\n";
    } else {
        $failed++;
        echo " [FAIL] {$desc}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

// -------------------------------------------------------------
// BAGIAN 1: PENGUJIAN 16 PERTANYAAN WAJIB PRODUCT INTELLIGENCE (SEKSI 5)
// -------------------------------------------------------------

// 1. Berapa total produk yang tersedia?
$r1 = $ai->processMessage("Berapa total produk yang tersedia?", $context);
assertTest("1. Total Produk Tersedia", 
    strpos($r1['reply'], '12') !== false,
    $r1['reply']
);

// 2. Ada berapa produk di database?
$r2 = $ai->processMessage("Ada berapa produk di database?", $context);
assertTest("2. Jumlah Produk di Database", 
    strpos($r2['reply'], '12') !== false,
    $r2['reply']
);

// 3. Berapa produk yang aktif?
$r3 = $ai->processMessage("Berapa produk yang aktif?", $context);
assertTest("3. Jumlah Produk Berstatus Aktif", 
    strpos($r3['reply'], '12') !== false && stripos($r3['reply'], 'aktif') !== false,
    $r3['reply']
);

// 4. Berapa produk yang tidak aktif?
$r4 = $ai->processMessage("Berapa produk yang tidak aktif?", $context);
assertTest("4. Jumlah Produk Tidak Aktif / Draft", 
    strpos($r4['reply'], '0') !== false || stripos($r4['reply'], 'tidak ada') !== false,
    $r4['reply']
);

// 5. Berapa produk yang stoknya habis?
$r5 = $ai->processMessage("Berapa produk yang stoknya habis?", $context);
assertTest("5. Jumlah Produk Stok Habis (stock = 0)", 
    strpos($r5['reply'], '0') !== false || stripos($r5['reply'], 'tidak ada') !== false,
    $r5['reply']
);

// 6. Berapa produk yang stoknya menipis?
$r6 = $ai->processMessage("Berapa produk yang stoknya menipis?", $context);
assertTest("6. Jumlah Produk Stok Menipis (1-5 unit)", 
    strpos($r6['reply'], '2') !== false || stripos($r6['reply'], 'menipis') !== false,
    $r6['reply']
);

// 7. Berapa produk berdasarkan kategori?
$r7 = $ai->processMessage("Berapa produk berdasarkan kategori?", $context);
assertTest("7. Distribusi Produk Berdasarkan Kategori", 
    strpos($r7['reply'], 'Kalung') !== false && strpos($r7['reply'], 'Anting') !== false,
    $r7['reply']
);

// 8. Berapa produk yang baru ditambahkan?
$r8 = $ai->processMessage("Berapa produk yang baru ditambahkan?", $context);
assertTest("8. Koleksi Produk Terbaru", 
    !empty($r8['products']) || stripos($r8['reply'], 'terbaru') !== false,
    $r8['reply']
);

// 9. Tampilkan produk berdasarkan status tertentu
$r9 = $ai->processMessage("Tampilkan produk status aktif", $context);
assertTest("9. Tampilkan Produk Berdasarkan Status Tertentu", 
    strpos($r9['reply'], 'Aktif') !== false || !empty($r9['products']) || !empty($r9['table']),
    $r9['reply']
);

// 10. Produk mana yang stoknya paling sedikit?
$r10 = $ai->processMessage("Produk mana yang stoknya paling sedikit?", $context);
assertTest("10. Produk Stok Paling Sedikit (Crystal Camellia Brooch Pin, 3 unit)", 
    stripos($r10['reply'], 'Camellia') !== false && strpos($r10['reply'], '3') !== false,
    $r10['reply']
);

// 11. Produk mana yang jumlah stoknya paling banyak?
$r11 = $ai->processMessage("Produk mana yang jumlah stoknya paling banyak?", $context);
assertTest("11. Produk Stok Paling Banyak (Korean Matte Claw Clip, 100 unit)", 
    stripos($r11['reply'], 'Claw Clip') !== false && strpos($r11['reply'], '100') !== false,
    $r11['reply']
);

// 12. Apakah produk bernama tertentu tersedia?
$r12 = $ai->processMessage("Apakah produk Floral Pearl Hair Clip Set tersedia?", $context);
assertTest("12. Cek Ketersediaan Produk Bernama Spesifik", 
    stripos($r12['reply'], 'Floral Pearl Hair Clip') !== false && stripos($r12['reply'], 'tersedia') !== false,
    $r12['reply']
);

// 13. Berapa jumlah stok produk tertentu?
$r13 = $ai->processMessage("Berapa jumlah stok Floral Pearl Hair Clip Set?", $context);
assertTest("13. Cek Kuantitas Stok Riil Produk & Varian", 
    strpos($r13['reply'], '45') !== false && strpos($r13['reply'], 'Rose Gold') !== false,
    $r13['reply']
);

// 14. Tampilkan daftar produk yang tidak aktif
$r14 = $ai->processMessage("Tampilkan daftar produk yang tidak aktif", $context);
assertTest("14. Tampilkan Daftar Produk Nonaktif", 
    stripos($r14['reply'], 'nonaktif') !== false || stripos($r14['reply'], 'tidak ada') !== false,
    $r14['reply']
);

// 15. Berapa total stok seluruh produk?
$r15 = $ai->processMessage("Berapa total stok seluruh produk di toko?", $context);
assertTest("15. Total Unit Stok Fisik Gudang (425 unit)", 
    strpos($r15['reply'], '425') !== false,
    $r15['reply']
);

// 16. Produk mana yang datanya belum lengkap?
$r16 = $ai->processMessage("Produk mana yang datanya belum lengkap?", $context);
assertTest("16. Audit Kelengkapan Data Produk (Deskripsi, Gambar, Kategori)", 
    stripos($r16['reply'], 'lengkap') !== false,
    $r16['reply']
);

// -------------------------------------------------------------
// BAGIAN 2: MULTI-TURN CONVERSATION & DRILL-DOWN FILTERING (SEKSI 6)
// -------------------------------------------------------------
$drillContext = [];
// Turn 1: "Berapa produk yang aktif?"
$dt1 = $ai->processMessage("Berapa produk yang aktif?", $drillContext);
// Turn 2: "Tampilkan semuanya"
$dt2 = $ai->processMessage("Tampilkan semuanya", $drillContext);
$hasTable = !empty($dt2['table']['rows']);
// Turn 3: "Yang stoknya habis saja"
$dt3 = $ai->processMessage("Yang stoknya habis saja", $drillContext);

assertTest("17. Multi-Turn Drill-Down: Hitung Aktif -> Tampilkan Tabel -> Filter Stok Habis", 
    $hasTable && stripos($dt3['reply'], 'habis') !== false,
    "Table Rows: " . count($dt2['table']['rows'] ?? []) . " | DT3: " . $dt3['reply']
);

// -------------------------------------------------------------
// BAGIAN 3: PETA FITUR PLATFORM & NAVIGASI (SEKSI 4 & 7)
// -------------------------------------------------------------
$f1 = $ai->processMessage("Di mana halaman untuk lacak pesanan dan resi?", $context);
assertTest("18. Peta Fitur: Navigasi Lacak Pesanan (tracking.php)", 
    strpos($f1['reply'], 'tracking.php') !== false || stripos($f1['reply'], 'Lacak Pesanan') !== false,
    $f1['reply']
);

$f2 = $ai->processMessage("Fitur untuk melihat laporan penjualan admin ada di mana?", $context, ['role' => 'admin']);
assertTest("19. Peta Fitur: Navigasi Laporan Penjualan Admin (admin/reports.php)", 
    strpos($f2['reply'], 'admin/reports.php') !== false || stripos($f2['reply'], 'Laporan Penjualan') !== false,
    $f2['reply']
);

// -------------------------------------------------------------
// BAGIAN 4: ORDER INTELLIGENCE & LOOKUP (SEKSI 7)
// -------------------------------------------------------------
$ord = $ai->processMessage("Cek status pesanan NNA-20261014-0001", $context, ['role' => 'admin', 'id' => 1]);
assertTest("20. Order Lookup Berdasarkan Nomor Pesanan Nyata", 
    strpos($ord['reply'], 'NNA-20261014-0001') !== false && stripos($ord['reply'], 'Status') !== false,
    $ord['reply']
);

// -------------------------------------------------------------
// BAGIAN 5: ROLE-BASED ACCESS CONTROL (RBAC) KEAMANAN (SEKSI 9)
// -------------------------------------------------------------
// Non-admin asking for revenue -> Must be BLOCKED / RESTRICTED
$sec1 = $ai->processMessage("Berapa total omzet toko?", $context, ['role' => 'customer', 'id' => 3]);
assertTest("21. RBAC: Customer Dilarang Melihat Omzet & Keuangan Toko", 
    stripos($sec1['reply'], 'Dibatasi') !== false || stripos($sec1['reply'], 'rahasia') !== false,
    $sec1['reply']
);

// Admin asking for revenue -> Must be GRANTED with real DB sum
$sec2 = $ai->processMessage("Berapa total omzet toko?", $context, ['role' => 'admin', 'id' => 1]);
assertTest("22. RBAC: Admin Diizinkan Membaca Omzet Riil Database", 
    strpos($sec2['reply'], 'Omzet') !== false && strpos($sec2['reply'], 'Rp') !== false,
    $sec2['reply']
);

echo "\n======================================================\n";
echo "HASIL MASTER PENGUJIAN: {$passed} PASS, {$failed} FAIL\n";
echo "======================================================\n";
