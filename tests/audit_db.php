<?php
require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Config/database.php';

$db = getDB();

$total = (int)$db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$active = (int)$db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
$inactive = (int)$db->query("SELECT COUNT(*) FROM products WHERE status != 'active'")->fetchColumn();
$outOfStock = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
$lowStock = (int)$db->query("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= 5")->fetchColumn();
$totalStock = (int)$db->query("SELECT COALESCE(SUM(stock), 0) FROM products")->fetchColumn();

// Most and least stocked
$mostStocked = $db->query("SELECT name, stock FROM products ORDER BY stock DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$leastStocked = $db->query("SELECT name, stock FROM products ORDER BY stock ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Incomplete data check (missing description, category null, or empty image)
$incomplete = $db->query("SELECT id, name, sku FROM products WHERE description IS NULL OR description = '' OR category_id IS NULL OR main_image IS NULL OR main_image = ''")->fetchAll(PDO::FETCH_ASSOC);

// Category breakdown
$byCategory = $db->query("SELECT COALESCE(c.name, 'Tanpa Kategori') AS cat_name, COUNT(p.id) AS total, SUM(p.stock) AS total_units FROM products p LEFT JOIN categories c ON p.category_id = c.id GROUP BY p.category_id ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);

echo "=== AUDIT STATISTIK PRODUK DATABASE NNASA_CA_DB ===\n";
echo "Total Produk: {$total}\n";
echo "Produk Aktif: {$active}\n";
echo "Produk Nonaktif: {$inactive}\n";
echo "Produk Stok Habis (stock = 0): {$outOfStock}\n";
echo "Produk Stok Menipis (1-5 unit): {$lowStock}\n";
echo "Total Unit Stok Fisik: {$totalStock}\n";
echo "Produk Stok Terbanyak: {$mostStocked['name']} ({$mostStocked['stock']} unit)\n";
echo "Produk Stok Paling Sedikit: {$leastStocked['name']} ({$leastStocked['stock']} unit)\n";
echo "Produk Data Belum Lengkap: " . count($incomplete) . "\n";
echo "Distribusi per Kategori:\n";
foreach ($byCategory as $cat) {
    echo "  - {$cat['cat_name']}: {$cat['total']} produk ({$cat['total_units']} unit)\n";
}
