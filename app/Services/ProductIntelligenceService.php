<?php
/**
 * Product Intelligence Service
 * Store: nnasa_ca Accessories Marketplace
 * 
 * Provides deep database aggregation, status auditing, stock ranking,
 * category distribution, data completeness checks, and structured search.
 */

class ProductIntelligenceService {
    private PDO $db;
    private int $lowStockThreshold = 5;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->loadThreshold();
    }

    private function loadThreshold(): void {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'low_stock_threshold' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if ($val !== false && is_numeric($val)) {
                $this->lowStockThreshold = (int)$val;
            }
        } catch (Exception $e) {
            $this->lowStockThreshold = 5;
        }
    }

    /**
     * Get real-time comprehensive catalog statistics
     */
    public function getSummary(): array {
        $total = (int)$this->db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        $active = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
        $inactive = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE status != 'active'")->fetchColumn();
        $outOfStock = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE stock = 0")->fetchColumn();
        
        $lowStockStmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE stock > 0 AND stock <= ?");
        $lowStockStmt->execute([$this->lowStockThreshold]);
        $lowStock = (int)$lowStockStmt->fetchColumn();

        $totalUnits = (int)$this->db->query("SELECT COALESCE(SUM(stock), 0) FROM products")->fetchColumn();
        $totalCategories = (int)$this->db->query("SELECT COUNT(*) FROM categories")->fetchColumn();

        return [
            'total_products' => $total,
            'active_products' => $active,
            'inactive_products' => $inactive,
            'out_of_stock_products' => $outOfStock,
            'low_stock_products' => $lowStock,
            'total_stock_units' => $totalUnits,
            'total_categories' => $totalCategories,
            'low_stock_threshold' => $this->lowStockThreshold,
            'checked_at' => date('d M Y, H:i:s') . ' WIB'
        ];
    }

    /**
     * Formats official summary text as required by prompt Section 5
     */
    public function formatSummaryText(array $summary): string {
        return "Berikut ringkasan produk berdasarkan data terbaru:\n\n"
             . "- Total produk: **{$summary['total_products']} produk**\n"
             . "- Produk aktif: **{$summary['active_products']} produk**\n"
             . "- Produk nonaktif: **{$summary['inactive_products']} produk**\n"
             . "- Produk dengan stok habis: **{$summary['out_of_stock_products']} produk**\n"
             . "- Produk dengan stok menipis (<= {$summary['low_stock_threshold']} unit): **{$summary['low_stock_products']} produk**\n"
             . "- Total unit stok fisik di gudang: **{$summary['total_stock_units']} unit**\n\n"
             . "Data diperiksa pada: {$summary['checked_at']}\n\n"
             . "*Catatan: status dan angka ditentukan berdasarkan struktur serta aturan bisnis aplikasi.*";
    }

    /**
     * Fetch products by status criteria
     */
    public function getProductsByStatus(string $statusType = 'active', int $limit = 20): array {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/toko/';
        $sql = "
            SELECT p.*, COALESCE(c.name, 'Tanpa Kategori') AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
        ";

        $params = [];
        switch ($statusType) {
            case 'inactive':
            case 'draft':
            case 'archived':
                $sql .= " WHERE p.status != 'active' ORDER BY p.updated_at DESC";
                break;
            case 'out_of_stock':
            case 'habis':
                $sql .= " WHERE p.stock = 0 ORDER BY p.name ASC";
                break;
            case 'low_stock':
            case 'menipis':
                $sql .= " WHERE p.stock > 0 AND p.stock <= ? ORDER BY p.stock ASC";
                $params[] = $this->lowStockThreshold;
                break;
            case 'all':
                $sql .= " ORDER BY p.id ASC";
                break;
            case 'active':
            default:
                $sql .= " WHERE p.status = 'active' ORDER BY p.sales_count DESC";
                break;
        }

        $sql .= " LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'formatProductRow'], $rows);
    }

    /**
     * Get stock extremes (highest and lowest stock)
     */
    public function getStockExtremes(): array {
        // Most stocked
        $most = $this->db->query("
            SELECT p.*, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' 
            ORDER BY p.stock DESC LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        // Least stocked (lowest positive)
        $least = $this->db->query("
            SELECT p.*, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' AND p.stock > 0 
            ORDER BY p.stock ASC LIMIT 1
        ")->fetch(PDO::FETCH_ASSOC);

        return [
            'most_stocked' => $most ? $this->formatProductRow($most) : null,
            'least_stocked' => $least ? $this->formatProductRow($least) : null
        ];
    }

    /**
     * Get products by category breakdown
     */
    public function getCategoryBreakdown(): array {
        $stmt = $this->db->query("
            SELECT 
                COALESCE(c.name, 'Tanpa Kategori') AS category_name,
                c.slug AS category_slug,
                COUNT(p.id) AS total_products,
                COALESCE(SUM(p.stock), 0) AS total_units
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
            GROUP BY p.category_id
            ORDER BY total_products DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get newly added products
     */
    public function getRecentProducts(int $limit = 5): array {
        $stmt = $this->db->prepare("
            SELECT p.*, COALESCE(c.name, 'Tanpa Kategori') AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([$this, 'formatProductRow'], $rows);
    }

    /**
     * Check for products with incomplete data
     */
    public function getIncompleteProducts(): array {
        $stmt = $this->db->query("
            SELECT p.id, p.sku, p.name, p.status, p.stock,
                CASE 
                    WHEN p.description IS NULL OR TRIM(p.description) = '' THEN 'Deskripsi Kosong'
                    WHEN p.category_id IS NULL THEN 'Kategori Belum Ditentukan'
                    WHEN p.main_image IS NULL OR TRIM(p.main_image) = '' THEN 'Foto Utama Belum Ada'
                    ELSE 'Data Lain'
                END AS issue
            FROM products p
            WHERE p.description IS NULL OR TRIM(p.description) = ''
               OR p.category_id IS NULL
               OR p.main_image IS NULL OR TRIM(p.main_image) = ''
            ORDER BY p.id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check exact stock of a specific product by name or SKU
     */
    public function getProductStock(string $query): ?array {
        $q = trim($query);
        $stmt = $this->db->prepare("
            SELECT p.*, COALESCE(c.name, 'Tanpa Kategori') AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.sku = ? OR p.name LIKE ? OR p.slug = ?
            LIMIT 1
        ");
        $stmt->execute([$q, "%{$q}%", $q]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $product = $this->formatProductRow($row);

        // Fetch variants
        $vStmt = $this->db->prepare("SELECT * FROM product_variants WHERE product_id = ? AND status = 'active' ORDER BY id ASC");
        $vStmt->execute([$product['id']]);
        $product['variants'] = $vStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $product;
    }

    /**
     * Multi-criteria structured search
     */
    public function searchProductsStructured(array $filters): array {
        $sql = "
            SELECT p.*, COALESCE(c.name, 'Tanpa Kategori') AS category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'inactive') {
                $sql .= " AND p.status != 'active'";
            } else {
                $sql .= " AND p.status = ?";
                $params[] = $filters['status'];
            }
        }

        if (!empty($filters['name'])) {
            $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
            $term = "%{$filters['name']}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND (p.category_id = ? OR c.parent_id = ?)";
            $params[] = (int)$filters['category_id'];
            $params[] = (int)$filters['category_id'];
        }

        if (isset($filters['min_stock'])) {
            $sql .= " AND p.stock >= ?";
            $params[] = (int)$filters['min_stock'];
        }

        if (isset($filters['max_stock'])) {
            $sql .= " AND p.stock <= ?";
            $params[] = (int)$filters['max_stock'];
        }

        if (isset($filters['min_price'])) {
            $sql .= " AND COALESCE(p.discount_price, p.price) >= ?";
            $params[] = (float)$filters['min_price'];
        }

        if (isset($filters['max_price'])) {
            $sql .= " AND COALESCE(p.discount_price, p.price) <= ?";
            $params[] = (float)$filters['max_price'];
        }

        // Sorting
        $sort = $filters['sort'] ?? 'sales';
        switch ($sort) {
            case 'stock_asc':
                $sql .= " ORDER BY p.stock ASC";
                break;
            case 'stock_desc':
                $sql .= " ORDER BY p.stock DESC";
                break;
            case 'price_asc':
                $sql .= " ORDER BY COALESCE(p.discount_price, p.price) ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY COALESCE(p.discount_price, p.price) DESC";
                break;
            case 'newest':
                $sql .= " ORDER BY p.created_at DESC";
                break;
            case 'sales':
            default:
                $sql .= " ORDER BY p.sales_count DESC";
                break;
        }

        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 20;
        $sql .= " LIMIT " . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'formatProductRow'], $rows);
    }

    /**
     * Format a database product row
     */
    private function formatProductRow(array $p): array {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/toko/';
        $price = (float)$p['price'];
        $discountPrice = !empty($p['discount_price']) ? (float)$p['discount_price'] : null;
        $finalPrice = $discountPrice ?: $price;

        $discountPercent = 0;
        if ($discountPrice && $price > $discountPrice) {
            $discountPercent = round((($price - $discountPrice) / $price) * 100);
        }

        $imageUrl = function_exists('get_image_url')
            ? get_image_url($p['main_image'] ?? null, 'product', $p['name'] ?? 'Produk')
            : $baseUrl . 'public/assets/images/placeholder.php';

        return [
            'id' => (int)$p['id'],
            'sku' => $p['sku'],
            'name' => $p['name'],
            'slug' => $p['slug'],
            'category_name' => $p['category_name'] ?? 'Aksesoris',
            'price' => $finalPrice,
            'price_formatted' => function_exists('format_rupiah') ? format_rupiah($finalPrice) : 'Rp ' . number_format($finalPrice, 0, ',', '.'),
            'original_price' => $price,
            'original_price_formatted' => function_exists('format_rupiah') ? format_rupiah($price) : 'Rp ' . number_format($price, 0, ',', '.'),
            'has_discount' => $discountPrice !== null,
            'discount_percent' => $discountPercent,
            'stock' => (int)$p['stock'],
            'status' => $p['status'],
            'is_in_stock' => (int)$p['stock'] > 0,
            'is_low_stock' => (int)$p['stock'] > 0 && (int)$p['stock'] <= $this->lowStockThreshold,
            'image_url' => $imageUrl,
            'url' => $baseUrl . 'product-detail.php?slug=' . urlencode($p['slug'])
        ];
    }
}
