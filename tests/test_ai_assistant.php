<?php
/**
 * Automated Verification Script for NNasa AI Assistant
 * Tests grounding, accuracy, multi-turn context, stock queries, and fallback behavior
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';
require_once dirname(__DIR__) . '/app/Services/AIService.php';

echo "=== MEMULAI PENGUJIAN KOMPREHENSIF NNASA AI ASSISTANT ===\n\n";

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

// 1. Initial Greeting
$res1 = $ai->processMessage("Halo kak!", $context);
assertTest("1. Sapaan Ramah & Sesuai Identitas", 
    $res1['success'] === true && (strpos($res1['reply'], 'NNasa AI') !== false || strpos($res1['reply'], 'nnasa_ca') !== false),
    $res1['reply']
);

// 2. Identity Query
$res2 = $ai->processMessage("Kamu siapa?", $context);
assertTest("2. Menjelaskan Peran & Identitas Resmi", 
    strpos($res2['reply'], 'NNasa AI Assistant') !== false
);

// 3. Search Cheap Products (Step 1 of Master Prompt Context Scenario)
$res3 = $ai->processMessage("Kak, ada aksesoris yang harganya murah?", $context);
$hasProducts = !empty($res3['products']);
$isCheap = $hasProducts && ($res3['products'][0]['price'] <= 35000);
assertTest("3. Pencarian Aksesoris Murah dari Database", 
    $hasProducts && $isCheap,
    "Jumlah produk: " . count($res3['products']) . " | Harga pertama: " . ($res3['products'][0]['price_formatted'] ?? '')
);

// 4. Follow-up: "Kalau yang warna hitam ada?" (Step 2 of Master Prompt Context Scenario)
$res4 = $ai->processMessage("Kalau yang warna hitam ada?", $context);
$foundBlack = false;
foreach ($res4['products'] as $p) {
    if (isset($p['matched_variant']) && stripos($p['matched_variant']['variant_value'], 'black') !== false) {
        $foundBlack = true;
        break;
    }
}
assertTest("4. Konteks Lanjutan: Pencarian Varian Warna Hitam (Onyx Black Clover Necklace)", 
    $foundBlack || strpos($res4['reply'], 'Onyx Black') !== false || strpos($res4['reply'], 'hitam') !== false,
    $res4['reply']
);

// 5. Follow-up: "Berapa harganya?" (Step 3 of Master Prompt Context Scenario)
$res5 = $ai->processMessage("Berapa harganya?", $context);
assertTest("5. Konteks Lanjutan: Pertanyaan Harga Produk Terakhir Secara Akurat", 
    strpos($res5['reply'], 'Rp') !== false && !empty($res5['products']),
    $res5['reply']
);

// 6. Specific Stock Check: "Berapa stok jepit jedai?"
$res6 = $ai->processMessage("Berapa stok jepit jedai?", $context);
assertTest("6. Cek Stok Produk Spesifik (Korean Matte Claw Clip)", 
    strpos($res6['reply'], 'stok') !== false && strpos($res6['reply'], 'pcs') !== false,
    $res6['reply']
);

// 7. General Stock & Catalog Statistics (Distinguishing model count vs unit stock)
$res7 = $ai->processMessage("Berapa total produk dan stok di toko?", $context);
assertTest("7. Statistik Katalog Membedakan Jenis Produk vs Unit Stok Fisik", 
    strpos($res7['reply'], 'Total Model/Jenis Produk Aktif') !== false && strpos($res7['reply'], 'Total Unit Stok Fisik') !== false,
    $res7['reply']
);

// 8. Low Stock & Out of Stock Queries
$res8 = $ai->processMessage("Ada produk yang stoknya menipis?", $context);
assertTest("8. Deteksi Produk dengan Stok Kritis (<= 5 pcs)", 
    strpos($res8['reply'], 'stoknya menipis') !== false || strpos($res8['reply'], 'sisa') !== false,
    $res8['reply']
);

// 9. Website Feature Guide: "Gimana cara belinya?"
$res9 = $ai->processMessage("Gimana cara beli dan checkout produk?", $context);
assertTest("9. Panduan Belanja & Checkout dengan Link Halaman Resmi", 
    strpos($res9['reply'], 'Keranjang Belanja') !== false && stripos($res9['reply'], 'checkout') !== false,
    $res9['reply']
);

// 10. Payment Methods Guide
$res10 = $ai->processMessage("Metode pembayarannya apa aja?", $context);
assertTest("10. Informasi Metode Pembayaran Faktual (QRIS, VA, Bank Transfer)", 
    strpos($res10['reply'], 'QRIS') !== false && strpos($res10['reply'], 'Virtual Account') !== false,
    $res10['reply']
);

// 11. Courier & Shipping Guide
$res11 = $ai->processMessage("Kurir pengirimannya pakai apa dan ongkirnya berapa?", $context);
assertTest("11. Informasi Ekspedisi Kurir & Biaya Ongkir Aktual", 
    strpos($res11['reply'], 'SiCepat') !== false && strpos($res11['reply'], 'JNE') !== false,
    $res11['reply']
);

// 12. Promo & Voucher Voucher Inquiry
$res12 = $ai->processMessage("Ada kode voucher atau kupon diskon?", $context);
assertTest("12. Informasi Kupon Diskon Aktif (NNASANEW)", 
    strpos($res12['reply'], 'NNASANEW') !== false,
    $res12['reply']
);

// 13. Contact Admin / WhatsApp
$res13 = $ai->processMessage("Minta nomor WhatsApp admin dong", $context);
assertTest("13. Informasi Kontak WhatsApp Resmi", 
    strpos($res13['reply'], 'WhatsApp Resmi') !== false && strpos($res13['reply'], '081298765432') !== false,
    $res13['reply']
);

// 14. Out of Domain / Non-existent Product (Refusal & redirection)
$res14 = $ai->processMessage("Ada jual kulkas dua pintu dan laptop gaming?", $context);
assertTest("14. Menangani Pertanyaan di Luar Produk Aksesoris Tanpa Halusinasi", 
    strpos($res14['reply'], 'belum menemukan informasi') !== false || strpos($res14['reply'], 'aksesoris') !== false,
    $res14['reply']
);

echo "\n============================================\n";
echo "HASIL PENGUJIAN AI ASSISTANT: {$passed} PASS, {$failed} FAIL\n";
echo "============================================\n";
