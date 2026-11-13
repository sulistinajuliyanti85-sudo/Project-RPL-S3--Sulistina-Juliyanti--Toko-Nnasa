<?php
/**
 * CustomerAIChatController
 * 
 * Controller Komprehensif Chatbot AI (NNasa AI Assistant) untuk Nnaco.SA
 * Mampu membaca dan merangkum SELURUH DATABASE toko:
 * - Identitas & Akun Pengguna (Session & Users Table)
 * - Pesanan & Transaksi Pribadi (Orders, Status Pembayaran, Nominal Tagihan)
 * - Statistik Toko & Omzet (Untuk Admin: Total Penjualan, Pesanan Masuk, dsb)
 * - Katalog Produk & Stok Fisik (Products: Stok Ready, Stok Menipis, Terlaris, Termurah, dsb)
 * - Kategori Aksesoris (Categories)
 * - Promo, Diskon & Voucher Belanja (Coupons)
 * - Ulasan & Testimoni Pelanggan (Reviews)
 * - Informasi Layanan Toko (Metode Pembayaran, Ekspedisi Pengiriman)
 * 
 * Arsitektur: PHP Native MVC + MariaDB / MySQL (100% Aman via PDO Prepared Statements)
 * 
 * @package Nnaco_SA
 * @author Senior PHP Backend Developer
 */

namespace App\Controllers;

use PDO;
use Exception;
use Throwable;

class CustomerAIChatController
{
    private PDO $db;
    private string $aiApiKey;
    private array $aiApiKeysPool = [];
    private string $aiModel;

    // Nama tabel database agar mudah dikustomisasi
    protected string $tableOrders     = 'orders';
    protected string $tableOrderItems = 'order_items';
    protected string $tableProducts   = 'products';
    protected string $tableCategories = 'categories';
    protected string $tableUsers      = 'users';
    protected string $tableCoupons    = 'coupons';
    protected string $tableReviews    = 'reviews';

    public function __construct(PDO $db, $aiApiKey, string $aiModel = 'gemini-3.5-flash-lite')
    {
        $this->db = $db;
        $keys = is_array($aiApiKey) ? $aiApiKey : [$aiApiKey];
        if (function_exists('get_setting')) {
            $keys[] = get_setting('ai_api_key', '');
            $keys[] = get_setting('ai_api_key_main', '');
            $keys[] = get_setting('ai_api_key_backup_1', '');
            $keys[] = get_setting('ai_api_key_backup_2', '');
        }
        if (defined('GEMINI_API_KEY')) {
            $keys[] = GEMINI_API_KEY;
        }

        $validKeys = [];
        foreach ($keys as $k) {
            $dec = self::decryptApiKey($k) ?? trim((string)$k);
            if (!empty($dec) && strlen($dec) >= 20) {
                $validKeys[] = $dec;
            }
        }
        $this->aiApiKeysPool = array_values(array_unique($validKeys));
        $this->aiApiKey = $this->aiApiKeysPool[0] ?? '';

        $cleanModel = !empty($aiModel) ? trim($aiModel) : 'gemini-3.5-flash-lite';
        if (in_array($cleanModel, ['gemini-1.5-flash', 'gemini-2.5-flash', 'gemini-pro', ''])) {
            $cleanModel = 'gemini-3.5-flash-lite';
        }
        $this->aiModel = $cleanModel;
    }

    /**
     * Dekripsi Kunci API AES-256-GCM / Plaintext
     */
    public static function decryptApiKey(?string $encryptedKey, ?string $secretKey = null): ?string
    {
        if (empty($encryptedKey)) {
            return null;
        }

        $raw = trim($encryptedKey);
        if (str_starts_with($raw, 'AIzaSy') || str_starts_with($raw, 'AQ.')) {
            return $raw;
        }

        $masterPass = $secretKey ?: (defined('APP_KEY') ? APP_KEY : 'nnasa_ca_default_secure_key_2026_salt_token!');
        if (str_starts_with($masterPass, 'base64:')) {
            $masterPass = base64_decode(substr($masterPass, 7));
        }
        $keyBin = hash('sha256', $masterPass, true);

        $clean = $raw;
        if (str_starts_with($clean, 'enc:gcm:')) {
            $clean = substr($clean, 8);
        } elseif (str_starts_with($clean, 'enc:')) {
            $clean = substr($clean, 4);
        }

        // Format JSON {"iv":"...", "tag":"...", "ciphertext":"..."}
        if (str_starts_with($clean, '{') && str_ends_with($clean, '}')) {
            $json = json_decode($clean, true);
            if (isset($json['ciphertext'], $json['iv'], $json['tag'])) {
                $iv = base64_decode($json['iv']);
                $tag = base64_decode($json['tag']);
                $cipher = base64_decode($json['ciphertext']);
                $decrypted = openssl_decrypt($cipher, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag);
                if ($decrypted !== false && !empty($decrypted)) {
                    return trim($decrypted);
                }
            }
        }

        // Format Binary Base64 (IV 12-byte + Tag 16-byte + Ciphertext)
        $decoded = base64_decode($clean, true);
        if ($decoded !== false && strlen($decoded) > 28) {
            $iv = substr($decoded, 0, 12);
            $tag = substr($decoded, 12, 16);
            $cipher = substr($decoded, 28);
            $decrypted = openssl_decrypt($cipher, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag);
            if ($decrypted !== false && !empty($decrypted)) {
                return trim($decrypted);
            }
        }

        if (function_exists('decrypt_secret')) {
            $legacy = decrypt_secret($raw);
            if (!empty($legacy)) {
                return trim($legacy);
            }
        }

        return $raw;
    }

    /**
     * Endpoint utama untuk menangani chat
     * 
     * @param string $userMessage Pesan teks dari pengguna
     * @param array|null $sessionData Data session ($_SESSION)
     * @return array Response terstruktur siap di-json_encode
     */
    public function handleChat(string $userMessage, ?array $sessionData = null): array
    {
        $cleanMessage = trim($userMessage);
        if (empty($cleanMessage)) {
            return [
                'status'      => 'success',
                'success'     => true,
                'reply'       => 'Halo Kak! Ada yang bisa aku bantu seputar koleksi aksesoris atau status pesanan hari ini? 💕',
                'products'    => [],
                'suggestions' => ['📦 Cek Pesanan Saya', '💎 Aksesoris Murah', '✨ Cek Stok Jedai', '🏷️ Info Promo']
            ];
        }

        $session = $sessionData ?? ($_SESSION ?? []);

        // 1. IDENTIFIKASI PENGGUNA & ROLE-AWARENESS (SESSION INJECTION)
        // -------------------------------------------------------------
        $userId = $session['user_id'] ?? ($session['id'] ?? null);
        $userName = $session['nama_user'] ?? ($session['user_name'] ?? ($session['nama'] ?? ($session['name'] ?? null)));
        $userRole = strtolower(trim((string)($session['role'] ?? ($session['user_role'] ?? 'guest'))));

        if (!empty($userId)) {
            try {
                $stmtUser = $this->db->prepare("SELECT name, role FROM {$this->tableUsers} WHERE id = ? LIMIT 1");
                $stmtUser->execute([$userId]);
                $userData = $stmtUser->fetch(PDO::FETCH_ASSOC);
                if ($userData) {
                    if (empty($userName)) {
                        $userName = $userData['name'];
                    }
                    if (!empty($userData['role'])) {
                        $userRole = strtolower(trim((string)$userData['role']));
                    }
                }
            } catch (Throwable $e) {
                // Abaikan jika ada perbedaan skema
            }
        }

        $isLoggedIn = !empty($userId);
        $isAdmin = in_array($userRole, ['admin', 'owner', 'staff']);
        $displayName = $userName ?: ($isAdmin ? 'Admin' : ($isLoggedIn ? 'Pelanggan' : 'Tamu'));

        // 2. INJEKSI DATA DASBOR GLOBAL (KHUSUS ADMIN/OWNER)
        // -------------------------------------------------------------
        $dashboardData = null;
        if ($isAdmin) {
            $dashboardData = $this->queryGlobalDashboardAggregate();

            // Konstruksi System Instruction khusus Admin dengan Ringkasan Metrik Toko
            $systemInstruction = "Kamu adalah NNasa AI, asisten internal dan partner operasional cerdas untuk toko e-commerce 'Nnaco.SA'.\n"
                               . "Kamu sedang berbicara dengan Admin {$displayName}.\n"
                               . "Berikut ringkasan data dasbor toko saat ini: "
                               . "Total Pendapatan Valid: {$dashboardData['total_pendapatan_fmt']}, "
                               . "Pesanan Belum Bayar: {$dashboardData['pesanan_belum_bayar']}, "
                               . "Pesanan Perlu Diproses: {$dashboardData['pesanan_perlu_diproses']}, "
                               . "Total Produk Aktif: {$dashboardData['total_produk_aktif']}, "
                               . "Total Unit Stok: {$dashboardData['total_stok']} unit, "
                               . "Total Kategori: {$dashboardData['total_kategori']}.\n"
                               . "PANDUAN UTAMA MENJAWAB:\n"
                               . "1. WAJIB MENJAWAB DENGAN JELAS, TEPAT, LENGKAP, DAN SOLUTIF APA PUN YANG DITANYAKAN oleh Admin. Jangan menolak menjawab pertanyaan.\n"
                               . "2. Untuk pertanyaan seputar data toko (omzet/pendapatan, pesanan belum dibayar, pesanan diproses, daftar pelanggan/pembeli, riwayat transaksi, katalog produk, sisa stok gudang, produk terlaris, kupon promo): Gunakan fakta dari DATA RESMI DATABASE yang dilampirkan agar 100% akurat.\n"
                               . "3. Jika Admin bertanya hal manajerial, saran strategi promosi, analisis bisnis, ide event diskon, rekomendasi penataan stok, atau obrolan umum: Jawablah secara cerdas, taktis, mendalam, dan profesional.\n"
                               . "4. Sajikan jawaban yang rapi, padat, dan mudah dibaca (gunakan poin tebal / markdown jika menyajikan angka atau ringkasan data).\n";
        } else {
            // Konstruksi System Instruction ramah untuk Pelanggan (Customer)
            $systemInstruction = "Kamu adalah NNasa AI Assistant, asisten virtual dan konsultan aksesoris resmi untuk toko e-commerce aksesoris wanita 'Nnaco.SA'.\n"
                               . "Sifatmu: Sangat ramah, anggun, hangat, sopan, cerdas, informatif, dan solutif. Sapa pelanggan dengan manis (misal: 'Halo Kak {$displayName}!' atau 'Hai Kak! 💕').\n"
                               . "ATURAN UTAMA MENJAWAB:\n"
                               . "1. WAJIB MENJAWAB DENGAN TEPAT, LENGKAP, DAN SOLUTIF APA PUN YANG DITANYAKAN OLEH PENGGUNA. Jawab langsung inti pertanyaan pengguna secara memuaskan.\n"
                               . "2. Jika pengguna bertanya hal umum (seperti cara merawat perhiasan/aksesoris agar tidak berkarat/hitam/pudar, rekomendasi kado ulang tahun/wisuda, tips padu padan OOTD, kelebihan material perhiasan titanium/18K plated/mutiara, panduan cara memesan/checkout, sapaan santai): JAWABLAH SECARA LENGKAP & BERMANFAAT menggunakan wawasanmu sebagai konsultan aksesoris fashion terpercaya. DILARANG KERAS menolak menjawab atau mengatakan 'informasi tidak tersedia di database' untuk pertanyaan umum semacam ini!\n"
                               . "3. Untuk data spesifik toko (nama produk, harga, ketersediaan stok, diskon kupon, tarif ongkir ekspedisi, metode pembayaran, atau status pesanan pribadi): Gunakan data faktual dari DATA RESMI DATABASE TOKO yang dilampirkan agar informasinya 100% akurat.\n"
                               . "4. Jika pelanggan mencari model aksesoris yang kebetulan belum ada di katalog: Beritahu secara luwes dan ramah bahwa model tersebut saat ini belum tersedia, lalu rekomendasikan koleksi cantik kami yang siap dibeli (seperti Jedai Korea 11cm, Kalung Clover Rose Gold, Gelang Serut Zirconia, Cincin Daisy, Anting Mutiara).\n"
                               . "5. Kemas jawaban dengan format rapi dan sentuhan emoji manis yang elegan (🌸, ✨, 💕, 💎, 🛍️).\n";
        }

        // 3. CONTEXT INJECTION MENYELURUH DARI DATABASE (ROLE-BASED RAG ENGINE)
        // -------------------------------------------------------------
        $normalized = mb_strtolower($cleanMessage, 'UTF-8');
        $ragResult = $this->fetchDatabaseContext($normalized, $userId, $displayName, $isLoggedIn, $isAdmin, $dashboardData);
        $detectedIntent  = $ragResult['intent'];
        $databaseContext = $ragResult['context'];
        $extractedData   = $ragResult['data'];

        // 4. COBA PEMANGGILAN GOOGLE GEMINI API DENGAN FULL DATABASE GROUNDING
        // -------------------------------------------------------------
        $geminiError = null;
        if (!empty($this->aiApiKey) && strlen($this->aiApiKey) >= 20) {
            $fullDbGrounding = $this->buildCompleteStoreDatabaseContext($isAdmin, $userId, $displayName);
            $combinedGrounding = (!empty($databaseContext) ? "Konteks Spesifik Pertanyaan:\n{$databaseContext}\n\n" : "") . $fullDbGrounding;

            $geminiRes = $this->callGeminiAPI($systemInstruction, $combinedGrounding, $cleanMessage);
            if (isset($geminiRes['status']) && $geminiRes['status'] === 'success') {
                return [
                    'status'        => 'success',
                    'success'       => true,
                    'reply'         => $geminiRes['reply'],
                    'intent'        => $detectedIntent,
                    'engine'        => 'google_gemini',
                    'grounded_data' => $databaseContext
                ];
            } else {
                $geminiError = $geminiRes['message'] ?? 'Gemini API Error';
            }
        } else {
            $geminiError = "Kunci API Gemini belum diatur atau tidak valid. Sistem menjawab langsung dari Database RAG Engine.";
        }

        // 4. SMART RAG FALLBACK GENERATOR (SELALU MERESPONS SESUAI DATA DATABASE)
        // -------------------------------------------------------------
        $smartReply = $this->generateDirectRAGReply($detectedIntent, $displayName, $isLoggedIn, $isAdmin, $extractedData, $databaseContext, $normalized);

        return [
            'status'            => 'success',
            'success'           => true,
            'reply'             => $smartReply,
            'intent'            => $detectedIntent,
            'engine'            => 'database_rag_engine',
            'debug_gemini_info' => $geminiError,
            'grounded_data'     => $databaseContext
        ];
    }

    /**
     * Query Agregat Data Dasbor Global (Khusus Admin / Owner)
     * Menggunakan satu query SQL efisien untuk performa maksimal tanpa membebani server
     */
    public function queryGlobalDashboardAggregate(): array
    {
        try {
            $sql = "
                SELECT 
                    (SELECT COALESCE(SUM(total_amount), 0) FROM {$this->tableOrders} WHERE payment_status = 'paid') AS total_pendapatan,
                    (SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status = 'pending') AS pesanan_belum_bayar,
                    (SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status = 'processing') AS pesanan_perlu_diproses,
                    (SELECT COUNT(*) FROM {$this->tableProducts} WHERE status = 'active') AS total_produk_aktif,
                    (SELECT COUNT(*) FROM {$this->tableCategories}) AS total_kategori,
                    (SELECT COALESCE(SUM(stock), 0) FROM {$this->tableProducts}) AS total_stok,
                    (SELECT COUNT(*) FROM {$this->tableProducts} WHERE stock <= 5 AND status = 'active') AS stok_menipis,
                    (SELECT COUNT(*) FROM {$this->tableOrders}) AS total_pesanan
            ";
            $data = $this->db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'total_pendapatan'       => (float)($data['total_pendapatan'] ?? 0),
                'total_pendapatan_fmt'   => 'Rp ' . number_format((float)($data['total_pendapatan'] ?? 0), 0, ',', '.'),
                'pesanan_belum_bayar'    => (int)($data['pesanan_belum_bayar'] ?? 0),
                'pesanan_perlu_diproses' => (int)($data['pesanan_perlu_diproses'] ?? 0),
                'total_produk_aktif'     => (int)($data['total_produk_aktif'] ?? 0),
                'total_kategori'         => (int)($data['total_kategori'] ?? 0),
                'total_stok'             => (int)($data['total_stok'] ?? 0),
                'stok_menipis'           => (int)($data['stok_menipis'] ?? 0),
                'total_pesanan'          => (int)($data['total_pesanan'] ?? 0)
            ];
        } catch (Throwable $e) {
            return [
                'total_pendapatan'       => 0.0,
                'total_pendapatan_fmt'   => 'Rp 0',
                'pesanan_belum_bayar'    => 0,
                'pesanan_perlu_diproses' => 0,
                'total_produk_aktif'     => 0,
                'total_kategori'         => 0,
                'total_stok'             => 0,
                'stok_menipis'           => 0,
                'total_pesanan'          => 0,
                'error'                  => $e->getMessage()
            ];
        }
    }

    /**
     * Membangun Konteks Grounding Komprehensif Seluruh Database Toko Nnaco.SA
     * Mengambil snapshot real-time dari seluruh tabel: orders, products, categories, users, coupons, reviews
     */
    public function buildCompleteStoreDatabaseContext(bool $isAdmin, ?int $userId, string $displayName): string
    {
        try {
            // 1. Data Agregat Finansial & Operasional
            $revenue = (float)$this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM {$this->tableOrders} WHERE payment_status = 'paid'")->fetchColumn();
            $revFmt = 'Rp ' . number_format($revenue, 0, ',', '.');
            $unpaid = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status = 'pending'")->fetchColumn();
            $unpaidAmt = (float)$this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM {$this->tableOrders} WHERE order_status = 'pending'")->fetchColumn();
            $unpaidAmtFmt = 'Rp ' . number_format($unpaidAmt, 0, ',', '.');
            $processing = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status = 'processing'")->fetchColumn();
            $shipped = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status = 'shipped'")->fetchColumn();
            $completed = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status IN ('completed', 'delivered')")->fetchColumn();
            $totalOrders = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders}")->fetchColumn();

            $totalActiveProd = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableProducts} WHERE status = 'active'")->fetchColumn();
            $totalStock = (int)$this->db->query("SELECT COALESCE(SUM(stock), 0) FROM {$this->tableProducts} WHERE status = 'active'")->fetchColumn();
            $lowStockCount = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableProducts} WHERE stock <= 5 AND status = 'active'")->fetchColumn();
            $totalCategories = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableCategories}")->fetchColumn();

            $totalUsers = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableUsers}")->fetchColumn();
            $totalCustomers = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableUsers} WHERE role = 'customer'")->fetchColumn();

            $ctx = "=== RINGKASAN DATA RESMI DATABASE TOKO NNACO.SA ===\n";
            $ctx .= "- METRIK DASBOR TOKO:\n";
            $ctx .= "  * Total Pendapatan Valid (Lunas): {$revFmt}\n";
            $ctx .= "  * Total Seluruh Transaksi: {$totalOrders} pesanan\n";
            $ctx .= "  * Pesanan Belum Bayar: {$unpaid} pesanan (Total Nilai: {$unpaidAmtFmt})\n";
            $ctx .= "  * Pesanan Perlu Diproses: {$processing} pesanan\n";
            $ctx .= "  * Pesanan Sedang Dikirim: {$shipped} pesanan\n";
            $ctx .= "  * Pesanan Selesai/Terkirim: {$completed} pesanan\n";
            $ctx .= "  * Total Produk Aktif: {$totalActiveProd} model aksesoris\n";
            $ctx .= "  * Total Persediaan Stok Fisik: {$totalStock} unit\n";
            $ctx .= "  * Produk Stok Menipis (<= 5 pcs): {$lowStockCount} produk\n";
            $ctx .= "  * Total Kategori: {$totalCategories} kategori\n";
            $ctx .= "  * Total Pengguna Terdaftar: {$totalUsers} akun ({$totalCustomers} akun pelanggan)\n\n";

            // 2. Daftar Seluruh Produk Aktif (Katalog Lengkap)
            $stmtProd = $this->db->query("
                SELECT p.name, p.price, p.discount_price, p.stock, p.sales_count, c.name as category_name
                FROM {$this->tableProducts} p
                LEFT JOIN {$this->tableCategories} c ON p.category_id = c.id
                WHERE p.status = 'active'
                ORDER BY p.sales_count DESC
            ");
            $products = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

            $ctx .= "- KATALOG SELURUH PRODUK AKSESORIS TOKO:\n";
            foreach ($products as $p) {
                $effPrice = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                $prFmt = 'Rp ' . number_format($effPrice, 0, ',', '.');
                $ctx .= "  * {$p['name']} [Kategori: {$p['category_name']}]: Harga {$prFmt}, Sisa Stok: {$p['stock']} pcs, Terjual: {$p['sales_count']} pcs\n";
            }
            $ctx .= "\n";

            // 3. Daftar Pembeli / Pelanggan yang Pernah Belanja
            $stmtBuyers = $this->db->query("
                SELECT u.name as customer_name, u.email, COUNT(o.id) as total_orders,
                       COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spend,
                       MAX(o.created_at) as last_order
                FROM {$this->tableOrders} o
                JOIN {$this->tableUsers} u ON o.user_id = u.id
                GROUP BY u.id, u.name, u.email
                ORDER BY total_spend DESC, total_orders DESC
            ");
            $buyers = $stmtBuyers->fetchAll(PDO::FETCH_ASSOC);
            $ctx .= "- DAFTAR PEMBELI / PELANGGAN TOKO YANG PERNAH BERBELANJA:\n";
            foreach ($buyers as $b) {
                $spFmt = 'Rp ' . number_format($b['total_spend'], 0, ',', '.');
                $ctx .= "  * {$b['customer_name']} ({$b['email']}): {$b['total_orders']} pesanan, Total Belanja Lunas: {$spFmt}, Terakhir Belanja: {$b['last_order']}\n";
            }
            $ctx .= "\n";

            // 4. Daftar 10 Transaksi Pesanan Terbaru
            $stmtRecent = $this->db->query("
                SELECT o.order_number, u.name as customer_name, o.total_amount, o.payment_status, o.order_status, o.created_at
                FROM {$this->tableOrders} o
                JOIN {$this->tableUsers} u ON o.user_id = u.id
                ORDER BY o.id DESC
                LIMIT 10
            ");
            $recent = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
            $ctx .= "- DAFTAR 10 TRANSAKSI PESANAN TERBARU TOKO:\n";
            foreach ($recent as $r) {
                $aFmt = 'Rp ' . number_format($r['total_amount'], 0, ',', '.');
                $paySt = ($r['payment_status'] === 'paid') ? 'Lunas' : 'Belum Dibayar';
                $ctx .= "  * Invoice {$r['order_number']}: Pelanggan {$r['customer_name']}, Total {$aFmt}, Pembayaran: {$paySt}, Status: {$r['order_status']}, Tanggal: {$r['created_at']}\n";
            }
            $ctx .= "\n";

            // 5. Daftar Kupon Promo Aktif
            $stmtCoupons = $this->db->query("SELECT code, discount_type, discount_value, minimum_purchase FROM {$this->tableCoupons} WHERE status = 'active'");
            $coupons = $stmtCoupons->fetchAll(PDO::FETCH_ASSOC);
            $ctx .= "- VOUCHER & KUPON PROMO AKTIF:\n";
            foreach ($coupons as $c) {
                $disc = ($c['discount_type'] === 'percentage') ? $c['discount_value'] . '%' : 'Rp ' . number_format($c['discount_value'], 0, ',', '.');
                $minB = 'Rp ' . number_format($c['minimum_purchase'], 0, ',', '.');
                $ctx .= "  * Kode: {$c['code']} (Potongan {$disc}, Min. Belanja: {$minB})\n";
            }
            $ctx .= "\n";

            // 6. Layanan Toko
            $ctx .= "- INFORMASI LAYANAN TOKO:\n";
            $ctx .= "  * Ekspedisi Pengiriman: SiCepat (Rp 12.000), J&T Express (Rp 14.000), JNE Reguler (Rp 15.000). Semua pesanan memiliki nomor resi terlacak.\n";
            $ctx .= "  * Metode Pembayaran: QRIS Otomatis (GoPay, OVO, Dana, ShopeePay, BCA, Mandiri), Transfer Bank (BCA, Mandiri, BNI), dan Simulasi Midtrans.\n\n";

            // 7. Ulasan & Testimoni Pelanggan
            try {
                $stmtRev = $this->db->query("
                    SELECT r.rating, r.review_text, p.name as product_name
                    FROM {$this->tableReviews} r
                    JOIN {$this->tableProducts} p ON r.product_id = p.id
                    WHERE r.status = 'approved'
                    ORDER BY r.id DESC
                    LIMIT 5
                ");
                $recentReviews = $stmtRev ? $stmtRev->fetchAll(PDO::FETCH_ASSOC) : [];
                if (!empty($recentReviews)) {
                    $ctx .= "- ULASAN & TESTIMONI PELANGGAN:\n";
                    foreach ($recentReviews as $rv) {
                        $ctx .= "  * [Bintang {$rv['rating']}/5] {$rv['product_name']}: \"{$rv['review_text']}\"\n";
                    }
                    $ctx .= "\n";
                }
            } catch (Throwable $eRev) {
                // Ignore review query error gracefully
            }

            // 8. Jika Customer Login: Sisipkan Data Pesanan Pribadinya
            if (!$isAdmin && !empty($userId)) {
                $stmtMyOrders = $this->db->prepare("
                    SELECT order_number, total_amount, payment_status, order_status, created_at
                    FROM {$this->tableOrders}
                    WHERE user_id = ?
                    ORDER BY id DESC
                    LIMIT 5
                ");
                $stmtMyOrders->execute([$userId]);
                $myOrders = $stmtMyOrders->fetchAll(PDO::FETCH_ASSOC);
                $ctx .= "\n- RIWAYAT PESANAN PRIBADI USER INI ({$displayName}):\n";
                if (empty($myOrders)) {
                    $ctx .= "  * User ini belum memiliki riwayat pesanan.\n";
                } else {
                    foreach ($myOrders as $mo) {
                        $mPay = ($mo['payment_status'] === 'paid') ? 'Lunas' : 'Belum Dibayar';
                        $mAmt = 'Rp ' . number_format($mo['total_amount'], 0, ',', '.');
                        $ctx .= "  * {$mo['order_number']}: Total {$mAmt}, Pembayaran: {$mPay}, Status: {$mo['order_status']}\n";
                    }
                }
            }

            return $ctx;
        } catch (Throwable $e) {
            return "Data Toko Nnaco.SA (Ringkasan Real-Time): " . $e->getMessage();
        }
    }

    /**
     * Query Seluruh Produk Aktif Toko (Katalog Lengkap & Rincian Stok)
     */
    private function queryAllProductsContext(bool $isAdmin): array
    {
        try {
            $stmt = $this->db->query("
                SELECT p.id, p.name, p.price, p.discount_price, p.stock, p.sales_count, COALESCE(c.name, 'Aksesoris') as category_name
                FROM {$this->tableProducts} p
                LEFT JOIN {$this->tableCategories} c ON p.category_id = c.id
                WHERE p.status = 'active'
                ORDER BY c.name ASC, p.name ASC
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totalStock = array_sum(array_column($products, 'stock'));

            $context = "Data Katalog Seluruh Produk Toko Nnaco.SA (Total: " . count($products) . " produk, {$totalStock} unit stok):\n";
            foreach ($products as $p) {
                $effPrice = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                $prFmt = 'Rp ' . number_format($effPrice, 0, ',', '.');
                $context .= "- {$p['name']} [{$p['category_name']}]: {$prFmt} (Sisa Stok: {$p['stock']} pcs, Terjual: {$p['sales_count']} pcs)\n";
            }

            return [
                'intent'  => 'all_products_catalog',
                'context' => $context,
                'data'    => [
                    'products'     => $products,
                    'total_count'  => count($products),
                    'total_stock'  => $totalStock,
                    'is_admin'     => $isAdmin
                ]
            ];
        } catch (Throwable $e) {
            return ['intent' => 'all_products_catalog', 'context' => 'Data produk: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Query Layanan Ekspedisi & Pengiriman Toko
     */
    private function queryShippingMethodsContext(): array
    {
        $context = "Layanan Pengiriman Toko Nnaco.SA:\n"
                 . "- Ekspedisi 1: SiCepat (Best Seller/Paling Cepat) - Tarif Flat Rp 12.000 (Estimasi 1-2 hari kerja)\n"
                 . "- Ekspedisi 2: J&T Express - Tarif Flat Rp 14.000 (Estimasi 1-3 hari kerja)\n"
                 . "- Ekspedisi 3: JNE Reguler - Tarif Flat Rp 15.000 (Estimasi 2-3 hari kerja)\n"
                 . "- Seluruh paket dipacking rapi dengan bubble wrap tebal dan dilengkapi nomor resi resmi yang dapat dilacak secara real-time di menu Lacak Pesanan.";

        return [
            'intent'  => 'shipping_inquiry',
            'context' => $context,
            'data'    => [
                'methods' => [
                    ['name' => 'SiCepat', 'cost' => 12000, 'desc' => '1-2 hari kerja'],
                    ['name' => 'J&T Express', 'cost' => 14000, 'desc' => '1-3 hari kerja'],
                    ['name' => 'JNE Reguler', 'cost' => 15000, 'desc' => '2-3 hari kerja']
                ]
            ]
        ];
    }

    /**
     * Query Metode Pembayaran Toko
     */
    private function queryPaymentMethodsContext(): array
    {
        $context = "Metode Pembayaran Toko Nnaco.SA:\n"
                 . "1. QRIS Instan Otomatis (GoPay, OVO, Dana, ShopeePay, LinkAja, BCA Mobile, Livin' Mandiri, dll)\n"
                 . "2. Transfer Bank Virtual Account (BCA, Mandiri, BNI)\n"
                 . "3. Pembayaran Simulasi Midtrans (Verifikasi instan otomatis untuk pengujian)";

        return [
            'intent'  => 'payment_inquiry',
            'context' => $context,
            'data'    => [
                'methods' => ['QRIS (E-Wallet & Mobile Banking)', 'Transfer Bank (BCA, Mandiri, BNI)', 'Midtrans Payment Simulation']
            ]
        ];
    }

    /**
     * Mesin RAG: Mendeteksi Maksud Pertanyaan & Mengambil Data Terkait dari Database
     * Mendukung Role-Awareness: Admin (Metrik Dasbor Toko) vs Customer (Pesanan Pribadi)
     */
    private function fetchDatabaseContext(
        string $normalized, 
        ?int $userId, 
        string $displayName, 
        bool $isLoggedIn, 
        bool $isAdmin,
        ?array $dashData = null
    ): array {
        // Normalisasi teks pertanyaan & tangani variasi spasi / typo
        $norm = ' ' . $normalized . ' ';
        $norm = str_replace([' di bayar ', ' di pesan '], [' dibayar ', ' dipesan '], $norm);
        $norm = str_replace('ada siapa saya yang belanja', 'ada siapa saja yang belanja', $norm);
        $norm = trim($norm);

        // =====================================================================
        // TAHAP 1: PERTANYAAN META UMUM (ULASAN, PENGIRIMAN, BAYAR, PROMO, KATEGORI)
        // =====================================================================

        // 1. Ulasan / Review / Rating / Testimoni Pelanggan
        if (
            str_contains($norm, 'ulasan') ||
            str_contains($norm, 'review') ||
            str_contains($norm, 'rating') ||
            str_contains($norm, 'testimoni') ||
            str_contains($norm, 'bintang')
        ) {
            return $this->queryReviewsContext();
        }

        // 2. Ekspedisi & Jasa Pengiriman
        if (
            str_contains($norm, 'ekspedisi') ||
            str_contains($norm, 'kurir') ||
            str_contains($norm, 'pengiriman') ||
            str_contains($norm, 'ongkir') ||
            str_contains($norm, 'kirim pakai apa') ||
            str_contains($norm, 'jasa kirim')
        ) {
            return $this->queryShippingMethodsContext();
        }

        // 3. Metode Pembayaran & Cara Bayar
        if (
            str_contains($norm, 'metode pembayaran') ||
            str_contains($norm, 'cara bayar') ||
            str_contains($norm, 'bisa bayar pakai apa') ||
            str_contains($norm, 'bayar pakai apa') ||
            str_contains($norm, 'transfer bank') ||
            str_contains($norm, 'rekening') ||
            str_contains($norm, 'qris')
        ) {
            return $this->queryPaymentMethodsContext();
        }

        // 3b. Kebijakan Pembayaran COD
        if (str_contains($norm, 'cod') || str_contains($norm, 'bayar di tempat') || str_contains($norm, 'cash on delivery')) {
            $context = "Kebijakan Pembayaran COD Toko Nnaco.SA:\n"
                     . "- Saat ini Nnaco.SA belum menyediakan metode pembayaran Cash On Delivery (COD).\n"
                     . "- Pembayaran yang tersedia: QRIS Otomatis (GoPay, OVO, Dana, ShopeePay) serta Transfer Bank Virtual Account (BCA, Mandiri, BNI).";
            return ['intent' => 'cod_inquiry', 'context' => $context, 'data' => []];
        }

        // 3c. Panduan Cara Pesan / Belanja
        if (str_contains($norm, 'cara pesan') || str_contains($norm, 'cara order') || str_contains($norm, 'cara beli') || str_contains($norm, 'cara checkout') || str_contains($norm, 'gimana cara belanja')) {
            $context = "Panduan Belanja di Toko Nnaco.SA:\n"
                     . "1. Buka menu Katalog dan pilih aksesoris favorit.\n"
                     . "2. Klik 'Tambah ke Keranjang' atau 'Beli Sekarang'.\n"
                     . "3. Masukkan alamat pengiriman dan pilih ekspedisi (SiCepat / J&T / JNE).\n"
                     . "4. Masukkan kode kupon promo (misal: NNASANEW) jika ada.\n"
                     . "5. Selesaikan pembayaran via QRIS / Transfer Bank.\n"
                     . "6. Pesanan akan langsung diproses dan nomor resi pengiriman dapat dilacak di menu Lacak Pesanan!";
            return ['intent' => 'how_to_order', 'context' => $context, 'data' => []];
        }

        // 3d. Tips Perawatan Aksesoris & Bahan Anti Karat
        if (str_contains($norm, 'merawat') || str_contains($norm, 'karat') || str_contains($norm, 'pudar') || str_contains($norm, 'awet') || str_contains($norm, 'menghitam') || str_contains($norm, 'membersihkan')) {
            $context = "Panduan & Tips Perawatan Aksesoris Nnaco.SA:\n"
                     . "1. Hindari kontak langsung dengan bahan kimia keras (parfum, body lotion, alkohol, sanitizer).\n"
                     . "2. Lepas perhiasan sebelum mandi, berenang di air klorin/laut, atau berolahraga.\n"
                     . "3. Simpan di tempat yang kering dan wadah tertutup (kotak perhiasan atau pouch beludru).\n"
                     . "4. Bersihkan secara berkala dengan mengusap lembut menggunakan kain microfiber kering.\n"
                     . "5. Koleksi aksesoris kami menggunakan material berkualitas tinggi seperti Titanium Stainless Steel, Zirconia AAA, dan Lapis Emas 18K yang awet dan berkilau mewah.";
            return ['intent' => 'care_tips', 'context' => $context, 'data' => []];
        }

        // 3e. Rekomendasi Hadiah / Kado
        if (str_contains($norm, 'kado') || str_contains($norm, 'hadiah') || str_contains($norm, 'wisuda') || str_contains($norm, 'ulang tahun') || str_contains($norm, 'sahabat') || str_contains($norm, 'pacar')) {
            $context = "Rekomendasi Kado & Hadiah Spesial di Nnaco.SA:\n"
                     . "1. Romantic Rose Gold Luxury Gift Box Set (4-in-1) - Rp 149.000 (Box mewah berpita dengan 4 perhiasan rose gold).\n"
                     . "2. Aura Rose Gold Clover Pendant Necklace - Rp 69.000 (Kalung liontin clover simbol keberuntungan).\n"
                     . "3. Blooming Daisy Adjustable Ring 18K Plated - Rp 45.000 (Cincin motif bunga daisy lapis emas 18K yang ukurannya bisa diatur).\n"
                     . "4. Dainty Freshwater Pearl Drop Earrings - Rp 65.000 (Anting mutiara air tawar asli yang anggun).";
            return ['intent' => 'gift_recommendation', 'context' => $context, 'data' => []];
        }

        // 3f. Sapaan Santai & Profil AI
        if (
            str_contains($norm, 'halo') || 
            str_contains($norm, 'hai') || 
            str_contains($norm, 'selamat pagi') || 
            str_contains($norm, 'selamat siang') || 
            str_contains($norm, 'selamat sore') || 
            str_contains($norm, 'selamat malam') ||
            str_contains($norm, 'assalamualaikum') ||
            str_contains($norm, 'kamu siapa') ||
            str_contains($norm, 'siapa kamu') ||
            str_contains($norm, 'bisa bantu apa')
        ) {
            $context = "Profil Asisten Toko Nnaco.SA:\n"
                     . "- Nama: NNasa AI Assistant (Asisten virtual resmi toko aksesoris Nnaco.SA).\n"
                     . "- Kemampuan: Membantu rekomendasi produk, cek stok, status pesanan, kupon diskon, tarif ekspedisi, tips perawatan, dan melayani pelanggan secara cerdas 24/7.";
            return ['intent' => 'greeting', 'context' => $context, 'data' => []];
        }

        // 4. Kupon, Voucher & Promo Diskon
        if (
            str_contains($norm, 'promo') ||
            str_contains($norm, 'diskon') ||
            str_contains($norm, 'kupon') ||
            str_contains($norm, 'voucher') ||
            str_contains($norm, 'potongan') ||
            str_contains($norm, 'cashback')
        ) {
            return $this->queryPromosContext();
        }

        // 5. Kategori Produk Aksesoris
        if (
            str_contains($norm, 'kategori') ||
            str_contains($norm, 'jenis aksesoris') ||
            str_contains($norm, 'macam aksesoris')
        ) {
            return $this->queryCategoriesContext();
        }

        // 6. Produk Terlaris (Best Seller)
        if (
            str_contains($norm, 'terlaris') ||
            str_contains($norm, 'best seller') ||
            str_contains($norm, 'bestseller') ||
            str_contains($norm, 'paling laku') ||
            str_contains($norm, 'paling banyak dibeli') ||
            str_contains($norm, 'top seller')
        ) {
            return $this->queryBestSellerProductsContext();
        }

        // 7. Produk Stok Menipis / Kritis / Mau Habis
        if (
            str_contains($norm, 'menipis') ||
            str_contains($norm, 'kritis') ||
            str_contains($norm, 'hampir habis') ||
            str_contains($norm, 'mau habis') ||
            str_contains($norm, 'sisa sedikit') ||
            str_contains($norm, 'stok habis') ||
            str_contains($norm, 'restock') ||
            str_contains($norm, 'kehabisan stok')
        ) {
            return $this->queryLowStockProductsContext();
        }

        // 8. Produk Termurah / Termahal
        if (str_contains($norm, 'termurah') || str_contains($norm, 'paling murah') || str_contains($norm, 'harga terendah')) {
            return $this->queryCheapestProductsContext();
        }
        if (str_contains($norm, 'termahal') || str_contains($norm, 'paling mahal') || str_contains($norm, 'harga tertinggi') || str_contains($norm, 'paling mewah')) {
            return $this->queryMostExpensiveProductsContext();
        }

        // 9. Total Pengguna / User / Member / Pelanggan Terdaftar
        if (
            str_contains($norm, 'total user') ||
            str_contains($norm, 'total pengguna') ||
            str_contains($norm, 'total member') ||
            str_contains($norm, 'total pelanggan') ||
            str_contains($norm, 'jumlah user') ||
            str_contains($norm, 'jumlah pengguna') ||
            str_contains($norm, 'jumlah member') ||
            str_contains($norm, 'jumlah pelanggan') ||
            str_contains($norm, 'berapa pelanggan terdaftar') ||
            str_contains($norm, 'berapa user') ||
            str_contains($norm, 'banyak pengguna')
        ) {
            return $this->queryTotalUsersContext();
        }

        // 10. Daftar Seluruh Produk Aktif (Katalog Lengkap & Rincian Stok)
        if (
            str_contains($norm, 'daftar semua produk') ||
            str_contains($norm, 'semua produk dan stok') ||
            str_contains($norm, 'daftar produk') ||
            str_contains($norm, 'list produk') ||
            str_contains($norm, 'katalog produk') ||
            str_contains($norm, 'stok seluruh produk') ||
            str_contains($norm, 'ada produk apa saja') ||
            str_contains($norm, 'produk apa saja') ||
            str_contains($norm, 'barang apa saja')
        ) {
            return $this->queryAllProductsContext($isAdmin);
        }

        // 11. Daftar Pembeli / Pelanggan yang Berbelanja di Toko (Siapa Saja yang Belanja)
        if (
            str_contains($norm, 'siapa yang belanja') ||
            str_contains($norm, 'siapa saja yang belanja') ||
            str_contains($norm, 'siapa saya yang belanja') ||
            str_contains($norm, 'daftar pembeli') ||
            str_contains($norm, 'siapa yang beli') ||
            str_contains($norm, 'siapa pelanggan yang belanja') ||
            str_contains($norm, 'siapa yang belanja paling banyak') ||
            str_contains($norm, 'pembeli terbanyak') ||
            str_contains($norm, 'siapa aja yang belanja') ||
            str_contains($norm, 'siapa customer yang belanja') ||
            ($isAdmin && (str_contains($norm, 'pembeli') || str_contains($norm, 'yang belanja')))
        ) {
            return $this->queryStoreBuyersContext($norm);
        }

        // 12. Pencarian Pelanggan Tertentu atau Nomor Invoice
        $stmtCustomerNames = $this->db->query("SELECT DISTINCT LOWER(name) FROM {$this->tableUsers}");
        $custNames = $stmtCustomerNames->fetchAll(PDO::FETCH_COLUMN);
        $matchedCust = null;
        foreach ($custNames as $cn) {
            $first = explode(' ', trim($cn))[0];
            if (strlen($first) >= 3 && str_contains($norm, $first)) {
                $matchedCust = $first;
                break;
            }
        }
        if ($matchedCust || preg_match('/\bnna-[\w-]+\b/i', $norm)) {
            return $this->queryStoreBuyersContext($norm);
        }

        // =====================================================================
        // TAHAP 2: KHUSUS ADMINISTRATOR (METRIK DASBOR GLOBAL)
        // =====================================================================
        if ($isAdmin && !empty($dashData)) {
            // Pesanan Belum Dibayar
            if (
                str_contains($norm, 'belum dibayar') ||
                str_contains($norm, 'belum bayar') ||
                str_contains($norm, 'menunggu pembayaran') ||
                str_contains($norm, 'unpaid') ||
                (str_contains($norm, 'pesanan') && str_contains($norm, 'bayar'))
            ) {
                $context = "Data Dasbor Admin (Pesanan Belum Bayar Real-Time):\n"
                         . "- Pesanan Belum Bayar Toko: {$dashData['pesanan_belum_bayar']} pesanan\n"
                         . "- Pesanan Perlu Diproses: {$dashData['pesanan_perlu_diproses']} pesanan\n"
                         . "- Total Pendapatan Valid: {$dashData['total_pendapatan_fmt']}\n"
                         . "PANDUAN: Beritahukan ke admin bahwa saat ini terdapat {$dashData['pesanan_belum_bayar']} pesanan yang belum dibayar di dasbor toko.";
                return [
                    'intent'  => 'admin_unpaid_orders',
                    'context' => $context,
                    'data'    => $dashData
                ];
            }

            // Total Pendapatan / Omzet
            if (
                str_contains($norm, 'pendapatan') ||
                str_contains($norm, 'omzet') ||
                str_contains($norm, 'omset') ||
                str_contains($norm, 'penjualan') ||
                str_contains($norm, 'uang masuk')
            ) {
                $context = "Data Dasbor Admin (Total Pendapatan Valid Toko):\n"
                         . "- Total Pendapatan Valid (Lunas): {$dashData['total_pendapatan_fmt']}\n"
                         . "- Total Seluruh Transaksi: {$dashData['total_pesanan']} transaksi\n"
                         . "- Pesanan Belum Bayar: {$dashData['pesanan_belum_bayar']} pesanan\n";
                return [
                    'intent'  => 'admin_revenue',
                    'context' => $context,
                    'data'    => $dashData
                ];
            }

            // Pesanan Perlu Diproses / Siap Dikirim
            if (
                str_contains($norm, 'perlu diproses') ||
                str_contains($norm, 'perlu dikirim') ||
                str_contains($norm, 'diproses') ||
                str_contains($norm, 'dikemas') ||
                str_contains($norm, 'packing')
            ) {
                $context = "Data Dasbor Admin (Pesanan Perlu Diproses):\n"
                         . "- Pesanan Perlu Diproses: {$dashData['pesanan_perlu_diproses']} pesanan\n"
                         . "- Pesanan Belum Bayar: {$dashData['pesanan_belum_bayar']} pesanan\n"
                         . "- Total Transaksi: {$dashData['total_pesanan']} pesanan\n";
                return [
                    'intent'  => 'admin_processing_orders',
                    'context' => $context,
                    'data'    => $dashData
                ];
            }

            // Produk Aktif Toko
            if (
                str_contains($norm, 'produk aktif') ||
                str_contains($norm, 'total produk') ||
                str_contains($norm, 'jumlah produk')
            ) {
                $context = "Data Dasbor Admin (Produk Aktif):\n"
                         . "- Total Produk Aktif: {$dashData['total_produk_aktif']} model\n"
                         . "- Total Unit Stok: {$dashData['total_stok']} unit\n"
                         . "- Total Kategori: {$dashData['total_kategori']} kategori\n";
                return [
                    'intent'  => 'admin_active_products',
                    'context' => $context,
                    'data'    => $dashData
                ];
            }

            // Total Unit Stok Persediaan Toko
            if (
                str_contains($norm, 'unit stok') ||
                str_contains($norm, 'total stok') ||
                str_contains($norm, 'stok toko') ||
                str_contains($norm, 'jumlah stok') ||
                str_contains($norm, 'persediaan')
            ) {
                $context = "Data Dasbor Admin (Persediaan Stok Toko):\n"
                         . "- Total Unit Stok: {$dashData['total_stok']} unit\n"
                         . "- Total Produk Aktif: {$dashData['total_produk_aktif']} model\n"
                         . "- Stok Menipis (<= 5 unit): {$dashData['stok_menipis']} produk\n";
                return [
                    'intent'  => 'admin_stock_summary',
                    'context' => $context,
                    'data'    => $dashData
                ];
            }

            // Pesanan Toko Secara Umum
            if (
                str_contains($norm, 'pesanan masuk') ||
                str_contains($norm, 'total pesanan') ||
                (str_contains($norm, 'berapa') && str_contains($norm, 'pesanan'))
            ) {
                $context = "Data Dasbor Admin (Ringkasan Pesanan Toko):\n"
                         . "- Total Seluruh Pesanan: {$dashData['total_pesanan']} transaksi\n"
                         . "- Pesanan Belum Bayar: {$dashData['pesanan_belum_bayar']} pesanan\n"
                         . "- Pesanan Perlu Diproses: {$dashData['pesanan_perlu_diproses']} pesanan\n";
                return [
                    'intent'  => 'admin_orders_summary',
                    'context' => $context,
                    'data'    => $dashData
                ];
            }

            // Pesanan Terbaru
            if (
                str_contains($norm, 'terbaru') ||
                str_contains($norm, 'terakhir') ||
                str_contains($norm, 'baru masuk')
            ) {
                return $this->queryRecentOrdersContext();
            }
        }

        // =====================================================================
        // TAHAP 3: KHUSUS CUSTOMER / PELANGGAN
        // =====================================================================
        if (!$isAdmin) {
            // Pesanan Pribadi Pelanggan
            if (
                str_contains($norm, 'pesanan saya') ||
                str_contains($norm, 'pesanan ku') ||
                str_contains($norm, 'orderan saya') ||
                str_contains($norm, 'status pesanan') ||
                str_contains($norm, 'lacak pesanan') ||
                str_contains($norm, 'resi saya') ||
                (str_contains($norm, 'pesanan') && !str_contains($norm, 'produk')) ||
                str_contains($norm, 'belum dibayar') ||
                str_contains($norm, 'belum bayar')
            ) {
                return $this->queryUserOrdersContext($userId, $displayName);
            }

            // Pertanyaan Jumlah Produk Umum
            if (
                str_contains($norm, 'jumlah produk') ||
                str_contains($norm, 'banyak produk') ||
                str_contains($norm, 'total produk') ||
                str_contains($norm, 'ada berapa produk') ||
                str_contains($norm, 'ada berapa barang') ||
                str_contains($norm, 'ada apa saja') ||
                str_contains($norm, 'jual apa saja')
            ) {
                return $this->queryCustomerProductCountContext($displayName);
            }
        }

        // =====================================================================
        // TAHAP 4: IDENTITAS ATAU PENCARIAN PRODUK
        // =====================================================================

        // Identitas Pengguna ("Siapa Saya") - Hanya jika murni tanya nama diri
        $isPureIdentity = (
            str_contains($norm, 'siapa saya') ||
            str_contains($norm, 'siapa aku') ||
            str_contains($norm, 'nama saya') ||
            str_contains($norm, 'profil saya') ||
            preg_match('/\bsiapa\s+(saya|aku|nama\s*ku)\b/i', $norm)
        ) && !str_contains($norm, 'belanja') && !str_contains($norm, 'toko');

        if ($isPureIdentity) {
            $context = $isLoggedIn
                ? "Data Identitas: Pengguna saat ini sudah login dengan nama {$displayName} (User ID: {$userId}, Peran: " . ($isAdmin ? 'Admin' : 'Customer') . ")."
                : "Data Identitas: Pengguna saat ini belum login (Pengunjung/Guest).";
            return ['intent' => 'identity_inquiry', 'context' => $context, 'data' => ['is_logged_in' => $isLoggedIn, 'is_admin' => $isAdmin]];
        }

        // Pencarian Produk Spesifik via Kata Kunci (Jedai, Kalung, Cincin, Gelang, Anting, Bros, dll)
        if (
            str_contains($norm, 'stok') ||
            str_contains($norm, 'produk') ||
            str_contains($norm, 'harga') ||
            str_contains($norm, 'ready') ||
            str_contains($norm, 'tersedia') ||
            str_contains($norm, 'cari') ||
            str_contains($norm, 'jual') ||
            str_starts_with($norm, 'ada ') ||
            str_contains($norm, ' ada ') ||
            preg_match('/(?:jedai|jepit|kalung|cincin|anting|gelang|bros|brooch|scrunchie|aksesoris|gold|silver|titanium|pearl|mutiara|vintage|korea|flower|butterfly)/i', $norm)
        ) {
            return $this->querySpecificProductsContext($norm, $displayName);
        }

        // Fallback default
        if ($isAdmin && !empty($dashData)) {
            $context = "Data Dasbor Admin:\n"
                     . "- Total Pendapatan Valid: {$dashData['total_pendapatan_fmt']}\n"
                     . "- Pesanan Belum Bayar: {$dashData['pesanan_belum_bayar']}\n"
                     . "- Pesanan Perlu Diproses: {$dashData['pesanan_perlu_diproses']}\n"
                     . "- Total Produk Aktif: {$dashData['total_produk_aktif']}\n"
                     . "- Total Unit Stok: {$dashData['total_stok']}\n"
                     . "- Total Kategori: {$dashData['total_kategori']}";
            return ['intent' => 'admin_dashboard_overview', 'context' => $context, 'data' => $dashData];
        }

        return $this->queryStoreOverviewContext();
    }
    private function queryStoreBuyersContext(string $normalizedMessage): array
    {
        try {
            // Cek apakah ada pencarian nama spesifik pelanggan dalam pesan
            $stmtAllNames = $this->db->query("SELECT DISTINCT LOWER(name) as name FROM {$this->tableUsers}");
            $allNames = $stmtAllNames->fetchAll(PDO::FETCH_COLUMN);

            $matchedName = null;
            foreach ($allNames as $nm) {
                $firstWord = explode(' ', trim($nm))[0];
                if (strlen($firstWord) >= 3 && str_contains($normalizedMessage, $firstWord)) {
                    $matchedName = $firstWord;
                    break;
                }
            }

            // Jika ada nama spesifik yang dicari (misal: "pesanan nizar", "clara belanja apa")
            if ($matchedName) {
                $stmtSpecific = $this->db->prepare("
                    SELECT o.order_number, u.name as customer_name, o.total_amount, o.payment_status, o.order_status, o.created_at
                    FROM {$this->tableOrders} o
                    JOIN {$this->tableUsers} u ON o.user_id = u.id
                    WHERE LOWER(u.name) LIKE ?
                    ORDER BY o.id DESC
                    LIMIT 5
                ");
                $stmtSpecific->execute(["%{$matchedName}%"]);
                $specificOrders = $stmtSpecific->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($specificOrders)) {
                    $context = "Data Riwayat Pesanan Pelanggan '{$matchedName}':\n";
                    foreach ($specificOrders as $so) {
                        $pay = ($so['payment_status'] === 'paid') ? 'Lunas' : 'Belum Dibayar';
                        $context .= "- Invoice {$so['order_number']}: Total Rp " . number_format($so['total_amount'], 0, ',', '.') . " | Pembayaran: {$pay} | Status: {$so['order_status']} | Tanggal: {$so['created_at']}\n";
                    }
                    return [
                        'intent'  => 'admin_specific_buyer_orders',
                        'context' => $context,
                        'data'    => ['customer_name' => $matchedName, 'orders' => $specificOrders]
                    ];
                }
            }

            // Query seluruh pembeli / pelanggan yang pernah berbelanja di toko
            $sql = "
                SELECT u.id as user_id, u.name as customer_name, u.email,
                       COUNT(o.id) as total_orders,
                       COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spend,
                       MAX(o.created_at) as last_order_date
                FROM {$this->tableOrders} o
                JOIN {$this->tableUsers} u ON o.user_id = u.id
                GROUP BY u.id, u.name, u.email
                ORDER BY total_orders DESC, total_spend DESC
                LIMIT 10
            ";
            $buyers = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $totalUniqueBuyers = (int)$this->db->query("SELECT COUNT(DISTINCT user_id) FROM {$this->tableOrders}")->fetchColumn();

            $context = "Data Pembeli / Pelanggan yang Berbelanja di Toko Nnaco.SA:\n"
                     . "- Total pembeli unik: {$totalUniqueBuyers} pelanggan.\n"
                     . "- Daftar Pelanggan yang Memiliki Transaksi Belanja:\n";

            foreach ($buyers as $b) {
                $spendFmt = 'Rp ' . number_format($b['total_spend'], 0, ',', '.');
                $context .= "  * {$b['customer_name']} ({$b['email']}): {$b['total_orders']} pesanan (Total Belanja Lunas: {$spendFmt}, Terakhir Belanja: {$b['last_order_date']})\n";
            }

            return [
                'intent'  => 'admin_buyers_inquiry',
                'context' => $context,
                'data'    => [
                    'total_buyers' => $totalUniqueBuyers,
                    'buyers'       => $buyers
                ]
            ];
        } catch (Throwable $e) {
            return ['intent' => 'admin_buyers_inquiry', 'context' => 'Data pembeli: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Query Data Pengguna / Member Terdaftar
     */
    private function queryTotalUsersContext(): array
    {
        try {
            $totalUsers = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableUsers}")->fetchColumn();
            $totalCustomers = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableUsers} WHERE role = 'customer'")->fetchColumn();
            $totalAdmins = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableUsers} WHERE role IN ('admin', 'owner', 'staff')")->fetchColumn();

            $context = "Data Pengguna Terdaftar Nnaco.SA:\n"
                     . "- Total Pengguna Terdaftar: {$totalUsers} akun\n"
                     . "- Total Akun Pelanggan (Customer): {$totalCustomers} akun\n"
                     . "- Total Akun Pengelola (Admin/Staff): {$totalAdmins} akun\n";

            return [
                'intent'  => 'admin_users_count',
                'context' => $context,
                'data'    => [
                    'total_users'     => $totalUsers,
                    'total_customers' => $totalCustomers,
                    'total_admins'    => $totalAdmins
                ]
            ];
        } catch (Throwable $e) {
            return ['intent' => 'admin_users_count', 'context' => 'Data pengguna: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Query Transaksi / Pesanan Terbaru Toko
     */
    private function queryRecentOrdersContext(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT o.id, o.order_number, u.name as customer_name, o.total_amount, o.payment_status, o.order_status, o.created_at
                FROM {$this->tableOrders} o
                JOIN {$this->tableUsers} u ON o.user_id = u.id
                ORDER BY o.id DESC
                LIMIT 6
            ");
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $context = "Data 6 Transaksi / Pesanan Terbaru di Toko Nnaco.SA:\n";
            foreach ($recent as $r) {
                $pay = ($r['payment_status'] === 'paid') ? 'Lunas' : 'Belum Bayar';
                $amt = 'Rp ' . number_format($r['total_amount'], 0, ',', '.');
                $context .= "- {$r['order_number']} oleh {$r['customer_name']} | Nominal: {$amt} | Pembayaran: {$pay} | Status: {$r['order_status']} | Waktu: {$r['created_at']}\n";
            }

            return [
                'intent'  => 'admin_recent_orders',
                'context' => $context,
                'data'    => $recent
            ];
        } catch (Throwable $e) {
            return ['intent' => 'admin_recent_orders', 'context' => 'Data pesanan terbaru: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 1. Query Data Statistik Toko (Omzet, Total Pesanan, Perlu Diproses)
     */
    private function queryStoreStatisticsContext(bool $isAdmin): array
    {
        try {
            $totalRevenue = (float)$this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM {$this->tableOrders} WHERE payment_status = 'paid'")->fetchColumn();
            $revFmt = 'Rp ' . number_format($totalRevenue, 0, ',', '.');

            $pendingOrders = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders} WHERE payment_status = 'pending'")->fetchColumn();
            $processingOrders = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status = 'processing'")->fetchColumn();
            $shippedOrders = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status = 'shipped'")->fetchColumn();
            $completedOrders = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders} WHERE order_status = 'completed'")->fetchColumn();
            $totalOrders = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableOrders}")->fetchColumn();

            $context = "Data Statistik Toko Nnaco.SA:\n"
                     . "- Total Pendapatan/Omzet Lunas: {$revFmt}\n"
                     . "- Total Keseluruhan Pesanan: {$totalOrders} pesanan\n"
                     . "- Pesanan Menunggu Pembayaran: {$pendingOrders} pesanan\n"
                     . "- Pesanan Siap / Sedang Diproses: {$processingOrders} pesanan\n"
                     . "- Pesanan Sedang Dikirim Ekspedisi: {$shippedOrders} pesanan\n"
                     . "- Pesanan Selesai: {$completedOrders} pesanan\n";

            return [
                'intent'  => 'store_statistics',
                'context' => $context,
                'data'    => [
                    'revenue'    => $revFmt,
                    'total'      => $totalOrders,
                    'pending'    => $pendingOrders,
                    'processing' => $processingOrders,
                    'shipped'    => $shippedOrders,
                    'completed'  => $completedOrders,
                    'is_admin'   => $isAdmin
                ]
            ];
        } catch (Throwable $e) {
            return ['intent' => 'store_statistics', 'context' => 'Data statistik toko tidak dapat dimuat: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 2. Query Data Pesanan & Transaksi Pengguna
     */
    private function queryUserOrdersContext(?int $userId, string $displayName): array
    {
        if (empty($userId)) {
            return [
                'intent'  => 'order_inquiry',
                'context' => "Data Pesanan Pengguna: Pengguna saat ini BELUM LOGIN (Guest/Tamu). AI harus memberi tahu dengan ramah bahwa untuk melihat status pesanan atau tagihan yang belum dibayar, pengguna harus login terlebih dahulu ke akun Nnaco.SA.",
                'data'    => ['is_logged_in' => false]
            ];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT order_number, total_amount, payment_status, order_status, created_at 
                FROM {$this->tableOrders} 
                WHERE user_id = ? 
                ORDER BY (CASE WHEN payment_status = 'pending' THEN 0 ELSE 1 END) ASC, id DESC 
                LIMIT 5
            ");
            $stmt->execute([$userId]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($orders)) {
                return [
                    'intent'  => 'order_inquiry',
                    'context' => "Data Pesanan Pengguna ({$displayName}): Pengguna saat ini belum memiliki transaksi atau riwayat pesanan di toko Nnaco.SA.",
                    'data'    => ['is_logged_in' => true, 'unpaid_count' => 0, 'orders' => []]
                ];
            }

            $unpaid = array_filter($orders, fn($o) => strtolower((string)$o['payment_status']) === 'pending');
            $unpaidCount = count($unpaid);
            $totalUnpaidAmount = array_sum(array_column($unpaid, 'total_amount'));

            $summary = "Data Pesanan Pengguna ({$displayName}):\n";
            if ($unpaidCount > 0) {
                $totalFmt = 'Rp ' . number_format($totalUnpaidAmount, 0, ',', '.');
                $summary .= "- Pengguna memiliki {$unpaidCount} pesanan yang BELUM DIBAYAR (Status: Pending) dengan total tagihan {$totalFmt}:\n";
                foreach ($unpaid as $uo) {
                    $summary .= "  * {$uo['order_number']} (Rp " . number_format($uo['total_amount'], 0, ',', '.') . ")\n";
                }
            } else {
                $summary .= "- Pengguna TIDAK MEMILIKI pesanan yang belum dibayar. Semua transaksi telah lunas atau selesai.\n";
            }

            $summary .= "- 5 Pesanan Terakhir:\n";
            foreach ($orders as $o) {
                $summary .= "  * {$o['order_number']} | Total: Rp " . number_format($o['total_amount'], 0, ',', '.') . " | Pembayaran: {$o['payment_status']} | Status: {$o['order_status']}\n";
            }

            return [
                'intent'  => 'order_inquiry',
                'context' => $summary,
                'data'    => [
                    'is_logged_in'  => true,
                    'unpaid_count'  => $unpaidCount,
                    'unpaid_orders' => array_values($unpaid),
                    'unpaid_total'  => $totalUnpaidAmount,
                    'orders'        => $orders
                ]
            ];
        } catch (Throwable $e) {
            return ['intent' => 'order_inquiry', 'context' => 'Data pesanan: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 3. Query Produk Terlaris (Best Seller)
     */
    private function queryBestSellerProductsContext(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT p.name, p.price, p.discount_price, p.stock, p.sales_count, COALESCE(c.name, 'Aksesoris') as category_name
                FROM {$this->tableProducts} p
                LEFT JOIN {$this->tableCategories} c ON p.category_id = c.id
                WHERE p.status = 'active'
                ORDER BY p.sales_count DESC, p.views_count DESC
                LIMIT 5
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $context = "Data Produk Terlaris (Best Seller) Nnaco.SA:\n";
            foreach ($products as $p) {
                $price = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                $context .= "- {$p['name']} [Kategori: {$p['category_name']}] | Terjual: {$p['sales_count']} pcs | Harga: Rp " . number_format($price, 0, ',', '.') . " | Stok: {$p['stock']} pcs\n";
            }

            return ['intent' => 'product_bestseller', 'context' => $context, 'data' => $products];
        } catch (Throwable $e) {
            return ['intent' => 'product_bestseller', 'context' => 'Data best seller: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 4. Query Produk Stok Menipis (Kritis)
     */
    private function queryLowStockProductsContext(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT p.name, p.stock, p.price, COALESCE(c.name, 'Aksesoris') as category_name
                FROM {$this->tableProducts} p
                LEFT JOIN {$this->tableCategories} c ON p.category_id = c.id
                WHERE p.status = 'active' AND p.stock <= 5
                ORDER BY p.stock ASC
                LIMIT 5
            ");
            $lowProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($lowProducts)) {
                $context = "Data Stok Produk: Semua produk saat ini dalam kondisi stok aman (stok di atas 5 pcs). Tidak ada produk yang menipis.";
            } else {
                $context = "Data Produk Stok Menipis / Kritis (Segera Restock):\n";
                foreach ($lowProducts as $p) {
                    $context .= "- {$p['name']} [{$p['category_name']}]: Sisa {$p['stock']} pcs (Harga: Rp " . number_format($p['price'], 0, ',', '.') . ")\n";
                }
            }

            return ['intent' => 'product_low_stock', 'context' => $context, 'data' => $lowProducts];
        } catch (Throwable $e) {
            return ['intent' => 'product_low_stock', 'context' => 'Data stok menipis: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 5. Query Produk Termurah & Termahal
     */
    private function queryCheapestProductsContext(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT p.name, p.price, p.discount_price, p.stock, COALESCE(c.name, 'Aksesoris') as category_name
                FROM {$this->tableProducts} p
                LEFT JOIN {$this->tableCategories} c ON p.category_id = c.id
                WHERE p.status = 'active' AND p.stock > 0
                ORDER BY (CASE WHEN p.discount_price > 0 THEN p.discount_price ELSE p.price END) ASC
                LIMIT 5
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $context = "Data Produk Termurah di Toko Nnaco.SA:\n";
            foreach ($products as $p) {
                $price = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                $context .= "- {$p['name']} [{$p['category_name']}]: Rp " . number_format($price, 0, ',', '.') . " (Stok: {$p['stock']} pcs)\n";
            }

            return ['intent' => 'product_cheapest', 'context' => $context, 'data' => $products];
        } catch (Throwable $e) {
            return ['intent' => 'product_cheapest', 'context' => 'Data termurah: ' . $e->getMessage(), 'data' => []];
        }
    }

    private function queryMostExpensiveProductsContext(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT p.name, p.price, p.discount_price, p.stock, COALESCE(c.name, 'Aksesoris') as category_name
                FROM {$this->tableProducts} p
                LEFT JOIN {$this->tableCategories} c ON p.category_id = c.id
                WHERE p.status = 'active' AND p.stock > 0
                ORDER BY p.price DESC
                LIMIT 5
            ");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $context = "Data Produk Koleksi Premium / Termahal di Toko Nnaco.SA:\n";
            foreach ($products as $p) {
                $context .= "- {$p['name']} [{$p['category_name']}]: Rp " . number_format($p['price'], 0, ',', '.') . " (Stok: {$p['stock']} pcs)\n";
            }

            return ['intent' => 'product_expensive', 'context' => $context, 'data' => $products];
        } catch (Throwable $e) {
            return ['intent' => 'product_expensive', 'context' => 'Data termahal: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 6. Query Daftar Kategori Produk
     */
    private function queryCategoriesContext(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT c.name, COUNT(p.id) as product_count
                FROM {$this->tableCategories} c
                LEFT JOIN {$this->tableProducts} p ON c.id = p.category_id AND p.status = 'active'
                WHERE c.status = 'active'
                GROUP BY c.id
                ORDER BY c.id ASC
            ");
            $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $context = "Daftar Kategori Aksesoris di Toko Nnaco.SA:\n";
            foreach ($cats as $c) {
                $context .= "- Kategori {$c['name']} (Memiliki {$c['product_count']} model produk aktif)\n";
            }

            return ['intent' => 'categories_list', 'context' => $context, 'data' => $cats];
        } catch (Throwable $e) {
            return ['intent' => 'categories_list', 'context' => 'Data kategori: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 7. Query Voucher & Promo Aktif
     */
    private function queryPromosContext(): array
    {
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare("
                SELECT code, discount_type, discount_value, minimum_purchase, maximum_discount
                FROM {$this->tableCoupons}
                WHERE status = 'active' AND (end_at IS NULL OR end_at >= ?)
                ORDER BY id ASC
                LIMIT 5
            ");
            $stmt->execute([$now]);
            $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmtFlash = $this->db->query("
                SELECT name, price, discount_price, stock
                FROM {$this->tableProducts}
                WHERE status = 'active' AND discount_price IS NOT NULL AND discount_price > 0 AND discount_price < price
                LIMIT 4
            ");
            $flashSale = $stmtFlash->fetchAll(PDO::FETCH_ASSOC);

            $context = "Data Promo & Diskon Hari Ini di Nnaco.SA:\n";
            if (!empty($coupons)) {
                $context .= "- Kode Kupon Diskon Aktif:\n";
                foreach ($coupons as $c) {
                    $pot = ($c['discount_type'] === 'percentage') ? "Diskon {$c['discount_value']}%" : "Potongan Rp " . number_format($c['discount_value'], 0, ',', '.');
                    $min = "Min. Belanja: Rp " . number_format($c['minimum_purchase'], 0, ',', '.');
                    $context .= "  * Kode: \"{$c['code']}\" ({$pot}, {$min})\n";
                }
            } else {
                $context .= "- Belum ada kode kupon baru hari ini.\n";
            }

            if (!empty($flashSale)) {
                $context .= "- Produk Sedang Diskon Langsung:\n";
                foreach ($flashSale as $fs) {
                    $context .= "  * {$fs['name']}: Promo Rp " . number_format($fs['discount_price'], 0, ',', '.') . " (Normal: Rp " . number_format($fs['price'], 0, ',', '.') . ")\n";
                }
            }

            return ['intent' => 'promo_query', 'context' => $context, 'data' => ['coupons' => $coupons, 'flash' => $flashSale]];
        } catch (Throwable $e) {
            return ['intent' => 'promo_query', 'context' => 'Data promo: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * 8. Query Ulasan & Rating Pelanggan
     */
    private function queryReviewsContext(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT r.rating, r.review_text as comment, p.name as product_name, u.name as customer_name
                FROM {$this->tableReviews} r
                JOIN {$this->tableProducts} p ON r.product_id = p.id
                LEFT JOIN {$this->tableUsers} u ON r.user_id = u.id
                WHERE r.status = 'approved'
                ORDER BY r.id DESC
                LIMIT 5
            ");
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($reviews)) {
                $context = "Data Ulasan Pelanggan: Belum ada ulasan baru dari pelanggan. Namun toko Nnaco.SA selalu mengutamakan kepuasan pelanggan dengan produk berkualitas dan pengemasan aman.";
            } else {
                $context = "Data Ulasan & Kepuasan Pelanggan Nnaco.SA:\n";
                foreach ($reviews as $rv) {
                    $cust = !empty($rv['customer_name']) ? substr($rv['customer_name'], 0, 1) . '***' : 'Pelanggan';
                    $context .= "- [Bintang {$rv['rating']}/5] {$rv['product_name']} oleh {$cust}: \"{$rv['comment']}\"\n";
                }
            }

            return ['intent' => 'reviews_query', 'context' => $context, 'data' => $reviews];
        } catch (Throwable $e) {
            return ['intent' => 'reviews_query', 'context' => 'Data ulasan: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Query Agregat Jumlah Produk Aktif & Katalog Toko untuk Customer (General Inquiry)
     * Menangani pertanyaan umum: "ada berapa jumlah produk di toko ini", "banyak produk", "total barang", "ada apa saja"
     */
    private function queryCustomerProductCountContext(string $displayName): array
    {
        try {
            $stmtCount = $this->db->query("SELECT COUNT(id) FROM {$this->tableProducts} WHERE status = 'active'");
            $totalActive = (int)$stmtCount->fetchColumn();

            $stmtStock = $this->db->query("SELECT COALESCE(SUM(stock), 0) FROM {$this->tableProducts} WHERE status = 'active'");
            $totalStock = (int)$stmtStock->fetchColumn();

            $stmtCats = $this->db->query("SELECT name FROM {$this->tableCategories} WHERE status = 'active' ORDER BY id ASC LIMIT 8");
            $categories = $stmtCats->fetchAll(PDO::FETCH_COLUMN);
            $catList = !empty($categories) ? implode(', ', $categories) : 'Jedai, Jepit Rambut, Kalung, Cincin, Gelang, Anting, Bros';

            $stmtTop = $this->db->query("
                SELECT name, price, discount_price 
                FROM {$this->tableProducts} 
                WHERE status = 'active' 
                ORDER BY sales_count DESC, id DESC 
                LIMIT 4
            ");
            $topProducts = $stmtTop->fetchAll(PDO::FETCH_ASSOC);
            $sampleNames = !empty($topProducts) ? implode(', ', array_map(fn($p) => $p['name'], $topProducts)) : '';

            $context = "Info untuk AI: Saat ini toko Nnaco.SA memiliki {$totalActive} produk aktif yang siap dibeli "
                     . "(dengan total persediaan {$totalStock} unit stok fisik siap kirim).\n"
                     . "Kategori produk yang tersedia: {$catList}.\n"
                     . (!empty($sampleNames) ? "Beberapa koleksi terpopuler: {$sampleNames}.\n" : "")
                     . "Instruksi untuk AI: Beritahu pelanggan bernama {$displayName} dengan ramah, hangat, dan anggun mengenai total {$totalActive} produk aktif yang ada di toko Nnaco.SA beserta kategori yang tersedia. Jawab pertanyaan dengan ramah dan tawarkan bantuan untuk memilih aksesoris favorit.";

            return [
                'intent'  => 'general_product_count',
                'context' => $context,
                'data'    => [
                    'total_active' => $totalActive,
                    'total_stock'  => $totalStock,
                    'categories'   => $categories,
                    'top_products' => $topProducts
                ]
            ];
        } catch (Throwable $e) {
            return [
                'intent'  => 'general_product_count',
                'context' => "Info untuk AI: Saat ini toko Nnaco.SA memiliki koleksi aksesoris aktif yang siap dibeli. Jawab pelanggan dengan ramah.",
                'data'    => ['total_active' => 12, 'total_stock' => 420, 'categories' => []]
            ];
        }
    }

    /**
     * 9. Query Pencarian Produk Tertentu (Kata Kunci & LIKE Query)
     * Jika Ditemukan: Sisipkan data produk (nama, harga, stok) ke context Gemini.
     * JIKA TIDAK DITEMUKAN: Sisipkan instruksi luwes dan produk rekomendasi ke Gemini,
     * BUKAN di-hardcode dengan pesan error statis.
     */
    private function querySpecificProductsContext(string $normalizedMessage, string $displayName = 'Pelanggan'): array
    {
        try {
            // Stopwords bahasa Indonesia umum dalam percakapan belanja online
            $stopWords = [
                'apakah', 'berapa', 'harganya', 'harga', 'stoknya', 'stok', 'ada', 'masih', 
                'yang', 'ini', 'itu', 'kak', 'kakak', 'min', 'admin', 'mau', 'tanya', 
                'dong', 'bisa', 'tolong', 'produk', 'barang', 'koleksi', 'item', 'punya', 
                'nggak', 'ga', 'kah', 'sedia', 'tersedia', 'jual', 'cari', 'di', 'ke', 'dari', 'ya'
            ];

            // Bersihkan tanda baca dan ekstrak kata kunci
            $cleanStr = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $normalizedMessage);
            $rawWords = preg_split('/\s+/', trim((string)$cleanStr));
            $keywords = array_values(array_filter($rawWords, fn($w) => mb_strlen($w) >= 2 && !in_array($w, $stopWords)));

            // Jika kata kunci kosong setelah stopwords, cari istilah aksesoris umum
            if (empty($keywords)) {
                if (preg_match('/(jedai|jepit|kalung|cincin|anting|gelang|bros|brooch|scrunchie|bando|kuncir)/i', $normalizedMessage, $m)) {
                    $keywords = [strtolower($m[1])];
                }
            }

            $searchPhrase = !empty($keywords) ? implode(' ', $keywords) : trim($normalizedMessage);

            // Query LIKE ke tabel produk (nama, deskripsi, atau kategori)
            $sql = "
                SELECT p.id, p.name, p.sku, p.price, p.discount_price, p.stock, COALESCE(c.name, 'Aksesoris') AS category_name, p.sales_count
                FROM {$this->tableProducts} p
                LEFT JOIN {$this->tableCategories} c ON p.category_id = c.id
                WHERE p.status = 'active'
            ";
            $params = [];

            if (!empty($keywords)) {
                $clauses = [];
                // 1. Cocokkan frase lengkap terlebih dahulu
                $clauses[] = "p.name LIKE ?";
                $clauses[] = "p.description LIKE ?";
                $clauses[] = "c.name LIKE ?";
                $params[] = "%{$searchPhrase}%";
                $params[] = "%{$searchPhrase}%";
                $params[] = "%{$searchPhrase}%";

                // 2. Cocokkan per keyword
                foreach ($keywords as $kw) {
                    $clauses[] = "p.name LIKE ?";
                    $clauses[] = "c.name LIKE ?";
                    $params[] = "%{$kw}%";
                    $params[] = "%{$kw}%";
                }

                $sql .= " AND (" . implode(' OR ', $clauses) . ")";
            }

            // Urutkan prioritas: stok tersedia > sales terbanyak
            $sql .= " ORDER BY (CASE WHEN p.name LIKE ? THEN 0 ELSE 1 END), (CASE WHEN p.stock > 0 THEN 0 ELSE 1 END) ASC, p.sales_count DESC LIMIT 4";
            $params[] = "%{$searchPhrase}%";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // JIKA DITEMUKAN HASIL
            if (!empty($products)) {
                $count = count($products);
                $context = "Info untuk AI: Ditemukan {$count} produk yang cocok dengan pencarian '{$searchPhrase}' di database toko Nnaco.SA:\n";
                foreach ($products as $p) {
                    $finalPrice = (!empty($p['discount_price']) && $p['discount_price'] > 0 && $p['discount_price'] < $p['price'])
                        ? $p['discount_price']
                        : $p['price'];
                    $priceFmt = 'Rp ' . number_format($finalPrice, 0, ',', '.');
                    $stockStatus = ($p['stock'] > 0) ? "Tersedia ({$p['stock']} pcs siap kirim)" : "Stok Habis (Sedang Restock)";
                    $context .= "- {$p['name']} [Kategori: {$p['category_name']}] | Harga: {$priceFmt} | Status: {$stockStatus}\n";
                }
                $context .= "Instruksi untuk AI: Berikan informasi produk di atas kepada pelanggan bernama {$displayName} dengan ramah, informatif, dan sebutkan harganya dengan jelas.";

                return [
                    'intent'  => 'product_inquiry',
                    'context' => $context,
                    'data'    => [
                        'found'       => true,
                        'search_term' => $searchPhrase,
                        'products'    => $products
                    ]
                ];
            }

            // JIKA TIDAK ADA HASIL: Ambil 3 produk unggulan sebagai alternatif rekomendasi
            $stmtRec = $this->db->query("
                SELECT p.name, p.price, p.discount_price, p.stock, COALESCE(c.name, 'Aksesoris') AS category_name 
                FROM {$this->tableProducts} p 
                LEFT JOIN {$this->tableCategories} c ON p.category_id = c.id
                WHERE p.status = 'active' AND p.stock > 0 
                ORDER BY p.sales_count DESC, p.id DESC 
                LIMIT 3
            ");
            $recommendations = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

            $recList = [];
            foreach ($recommendations as $r) {
                $pPrice = (!empty($r['discount_price']) && $r['discount_price'] > 0) ? $r['discount_price'] : $r['price'];
                $recList[] = "- {$r['name']} ({$r['category_name']}, Rp " . number_format($pPrice, 0, ',', '.') . ", Stok: {$r['stock']} pcs)";
            }
            $recStr = !empty($recList) ? implode("\n", $recList) : "- Jedai Korea, Kalung Titanium, Cincin Elegan";

            // Sisipkan instruksi luwes ke Gemini agar dijawab ramah, BUKAN pesan error statis
            $context = "Info untuk AI: Pelanggan sedang mencari produk terkait '{$searchPhrase}', namun barang/model spesifik tersebut saat ini TIDAK DITEMUKAN atau belum tersedia di database toko Nnaco.SA.\n"
                     . "Rekomendasi koleksi populer lain yang siap dikirim:\n"
                     . $recStr . "\n"
                     . "Instruksi untuk AI: Tanggapi pelanggan bernama {$displayName} dengan bahasa yang sangat luwes, hangat, dan ramah. Beritahukan dengan sopan bahwa produk '{$searchPhrase}' belum tersedia di etalase kami saat ini. Kemudian rekomendasikan beberapa pilihan aksesoris cantik di atas atau tawarkan untuk melihat koleksi kategori lainnya di Nnaco.SA. JANGAN menjawab kaku seperti sistem error!";

            return [
                'intent'  => 'product_inquiry',
                'context' => $context,
                'data'    => [
                    'found'           => false,
                    'search_term'     => $searchPhrase,
                    'products'        => [],
                    'recommendations' => $recommendations
                ]
            ];
        } catch (Throwable $e) {
            return [
                'intent'  => 'product_inquiry',
                'context' => "Info untuk AI: Terjadi kendala saat pencarian produk. Jawab pelanggan dengan ramah dan tawarkan bantuan.",
                'data'    => ['found' => false, 'search_term' => $normalizedMessage, 'products' => []]
            ];
        }
    }

    /**
     * 10. Query Overview Toko (Snapshot Default)
     */
    private function queryStoreOverviewContext(): array
    {
        try {
            $totalActive = (int)$this->db->query("SELECT COUNT(*) FROM {$this->tableProducts} WHERE status = 'active'")->fetchColumn();
            $totalStock = (int)$this->db->query("SELECT COALESCE(SUM(stock), 0) FROM {$this->tableProducts} WHERE status = 'active'")->fetchColumn();

            $context = "Data Toko Nnaco.SA:\n"
                     . "- Nnaco.SA adalah marketplace aksesoris wanita berkualitas (jedai, jepit, kalung, cincin, gelang, anting).\n"
                     . "- Total model produk aktif: {$totalActive} item (Total stok fisik: {$totalStock} pcs siap kirim).\n"
                     . "- Ekspedisi Pengiriman: SiCepat, JNE, J&T Express (Setiap hari kerja).\n"
                     . "- Metode Pembayaran: QRIS, Virtual Account BCA/Mandiri/BRI, dan Transfer Bank.";

            return ['intent' => 'general', 'context' => $context, 'data' => ['total_active' => $totalActive, 'total_stock' => $totalStock]];
        } catch (Throwable $e) {
            return ['intent' => 'general', 'context' => 'Nnaco.SA Official Store.', 'data' => []];
        }
    }

    /**
     * Generator Balasan Cerdas (RAG Natural Language)
     * Langsung merangkum data faktual dari database ke bahasa manusia yang ramah & terstruktur
     */
    private function generateDirectRAGReply(
        string $intent, 
        string $displayName, 
        bool $isLoggedIn, 
        bool $isAdmin,
        array $data, 
        string $databaseContext, 
        string $normalized
    ): string {
        $prefix = $isAdmin ? "Halo Admin **{$displayName}**! " : "Halo Kak **{$displayName}**! ";

        switch ($intent) {
            // =================================================================
            // KASUS DASBOR & BISNIS ADMIN (METRIK TOKO)
            // =================================================================
            case 'admin_unpaid_orders':
                $pesanan = $data['pesanan_belum_bayar'] ?? 0;
                $proses = $data['pesanan_perlu_diproses'] ?? 0;
                $rev = $data['total_pendapatan_fmt'] ?? 'Rp 0';
                return "Halo Admin **{$displayName}**! 📊 Berdasarkan data dasbor toko saat ini, terdapat **{$pesanan} pesanan yang belum dibayar**. Selain itu, ada {$proses} pesanan yang perlu diproses/dikirim dengan total omzet valid {$rev}.";

            case 'admin_revenue':
                $rev = $data['total_pendapatan_fmt'] ?? 'Rp 0';
                $tot = $data['total_pesanan'] ?? 0;
                return "Halo Admin **{$displayName}**! 💰 Total pendapatan valid (lunas) toko Nnaco.SA saat ini tercatat sebesar **{$rev}** dari total {$tot} transaksi pesanan.";

            case 'admin_active_products':
                $prod = $data['total_produk_aktif'] ?? 0;
                $kat = $data['total_kategori'] ?? 0;
                $stok = $data['total_stok'] ?? 0;
                return "Halo Admin **{$displayName}**! 📦 Saat ini toko memiliki **{$prod} produk aktif** yang tersebar di {$kat} kategori dengan total persediaan {$stok} unit stok fisik.";

            case 'admin_stock_summary':
                $stok = $data['total_stok'] ?? 0;
                $prod = $data['total_produk_aktif'] ?? 0;
                $menipis = $data['stok_menipis'] ?? 0;
                return "Halo Admin **{$displayName}**! 📦 Total persediaan stok toko saat ini adalah **{$stok} unit** dari {$prod} produk aktif ({$menipis} produk memiliki stok menipis &le; 5 unit).";

            case 'admin_processing_orders':
                $proses = $data['pesanan_perlu_diproses'] ?? 0;
                return "Halo Admin **{$displayName}**! 📦 Saat ini terdapat **{$proses} pesanan yang perlu diproses** dan segera disiapkan untuk pengiriman ke pelanggan.";

            case 'admin_orders_summary':
                $pesanan = $data['pesanan_belum_bayar'] ?? 0;
                $proses = $data['pesanan_perlu_diproses'] ?? 0;
                $tot = $data['total_pesanan'] ?? 0;
                return "Halo Admin **{$displayName}**! 📋 Ringkasan pesanan toko: Terdapat **{$pesanan} pesanan belum dibayar** dan **{$proses} pesanan perlu diproses** dari total **{$tot} transaksi** di Nnaco.SA.";

            case 'admin_dashboard_overview':
                $rev = $data['total_pendapatan_fmt'] ?? 'Rp 0';
                $pesanan = $data['pesanan_belum_bayar'] ?? 0;
                $proses = $data['pesanan_perlu_diproses'] ?? 0;
                $prod = $data['total_produk_aktif'] ?? 0;
                $stok = $data['total_stok'] ?? 0;
                $kat = $data['total_kategori'] ?? 0;
                return "Halo Admin **{$displayName}**! 📊 Ringkasan data dasbor toko Nnaco.SA saat ini:\n\n"
                     . "• **Total Pendapatan Valid**: {$rev}\n"
                     . "• **Pesanan Belum Bayar**: {$pesanan} pesanan\n"
                     . "• **Pesanan Perlu Diproses**: {$proses} pesanan\n"
                     . "• **Total Produk Aktif**: {$prod} produk ({$stok} unit stok)\n"
                     . "• **Total Kategori**: {$kat} kategori\n\n"
                     . "Ada metrik spesifik yang ingin dicek? 💼";

            case 'admin_buyers_inquiry':
                $buyers = $data['buyers'] ?? [];
                $totalBuyers = $data['total_buyers'] ?? count($buyers);
                if (!empty($buyers)) {
                    $lines = [];
                    foreach ($buyers as $b) {
                        $spendFmt = 'Rp ' . number_format((float)$b['total_spend'], 0, ',', '.');
                        $lines[] = "• **{$b['customer_name']}** ({$b['total_orders']} pesanan, total belanja {$spendFmt})";
                    }
                    $listStr = implode("\n", $lines);
                    return "Halo Admin **{$displayName}**! 🛍️ Saat ini tercatat ada **{$totalBuyers} pelanggan yang telah berbelanja** di toko **Nnaco.SA**:\n\n{$listStr}\n\nAda detail pesanan pelanggan tertentu yang ingin kamu periksa? 📋";
                }
                return "Halo Admin **{$displayName}**! Saat ini belum ada data pelanggan yang menyelesaikan pesanan belanja di toko Nnaco.SA.";

            case 'admin_specific_buyer_orders':
                $orders = $data['orders'] ?? [];
                $cust = ucfirst($data['customer_name'] ?? 'Pelanggan');
                if (!empty($orders)) {
                    $lines = [];
                    foreach ($orders as $o) {
                        $pay = ($o['payment_status'] === 'paid') ? 'Lunas' : 'Belum Dibayar';
                        $amt = 'Rp ' . number_format((float)$o['total_amount'], 0, ',', '.');
                        $lines[] = "• **{$o['order_number']}** ({$amt}) | Pembayaran: **{$pay}** | Status: {$o['order_status']}";
                    }
                    $listStr = implode("\n", $lines);
                    return "Halo Admin **{$displayName}**! 🔍 Ditemukan riwayat transaksi untuk pelanggan **{$cust}**:\n\n{$listStr}\n\nSilakan buka menu **Manajemen Pesanan** untuk mengelola pengiriman atau statusnya! 📦";
                }
                return "Halo Admin **{$displayName}**! Tidak ditemukan riwayat pesanan atas nama {$cust} di database toko.";

            case 'admin_users_count':
                $tot = $data['total_users'] ?? 0;
                $cust = $data['total_customers'] ?? 0;
                $adm = $data['total_admins'] ?? 0;
                return $prefix . "👥 Saat ini di database toko **Nnaco.SA** terdapat total **{$tot} pengguna terdaftar** (terdiri dari **{$cust} akun pelanggan** dan {$adm} akun tim pengelola/admin).";

            case 'admin_recent_orders':
                $recentOrders = $data ?? [];
                if (!empty($recentOrders)) {
                    $lines = [];
                    foreach ($recentOrders as $ro) {
                        $pay = ($ro['payment_status'] === 'paid') ? 'Lunas' : 'Belum Dibayar';
                        $amt = 'Rp ' . number_format((float)$ro['total_amount'], 0, ',', '.');
                        $lines[] = "• **{$ro['order_number']}** ({$amt}) oleh **{$ro['customer_name']}** [{$pay} - {$ro['order_status']}]";
                    }
                    $listStr = implode("\n", $lines);
                    return "Halo Admin **{$displayName}**! ⏱️ Berikut transaksi pesanan paling baru yang masuk di toko **Nnaco.SA**:\n\n{$listStr}\n\nAda pesanan yang ingin kamu proses sekarang? 📦";
                }
                return "Halo Admin **{$displayName}**! Belum ada riwayat pesanan terbaru di toko.";

            // =================================================================
            // KATALOG SELURUH PRODUK
            // =================================================================
            case 'all_products_catalog':
                $prods = $data['products'] ?? [];
                $tot = $data['total_count'] ?? count($prods);
                $stok = $data['total_stock'] ?? 0;
                if (!empty($prods)) {
                    $lines = [];
                    foreach ($prods as $p) {
                        $price = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                        $prFmt = 'Rp ' . number_format($price, 0, ',', '.');
                        $lines[] = "• **{$p['name']}** [{$p['category_name']}] - {$prFmt} (Stok: {$p['stock']} pcs)";
                    }
                    $listStr = implode("\n", $lines);
                    if ($isAdmin) {
                        return "Halo Admin **{$displayName}**! 📦 Berikut daftar lengkap seluruh **{$tot} produk aktif** dan stok fisik di toko **Nnaco.SA** (Total persediaan: {$stok} unit):\n\n{$listStr}\n\nSemua produk terhubung langsung ke inventaris toko! 📋";
                    }
                    return "Halo Kak **{$displayName}**! 💕 Toko kami menyediakan **{$tot} pilihan aksesoris cantik** ready stok (Total {$stok} unit):\n\n{$listStr}\n\nSemua produk 100% real pict dan siap dikirim ya! Yuk klik menu **Katalog** untuk pesan! ✨";
                }
                return $prefix . "Saat ini seluruh produk sedang dalam proses pembaruan katalog.";

            // =================================================================
            // EKSPEDISI & METODE PEMBAYARAN
            // =================================================================
            case 'shipping_inquiry':
                return $prefix . "🚚 Toko **Nnaco.SA** bekerja sama dengan 3 jasa ekspedisi terpercaya:\n\n"
                     . "• **SiCepat**: Ongkir flat Rp 12.000 (Estimasi 1-2 hari kerja)\n"
                     . "• **J&T Express**: Ongkir flat Rp 14.000 (Estimasi 1-3 hari kerja)\n"
                     . "• **JNE Reguler**: Ongkir flat Rp 15.000 (Estimasi 2-3 hari kerja)\n\n"
                     . "Setiap pesanan yang dikirim dilengkapi dengan nomor resi resmi yang dapat dilacak langsung di menu **Lacak Pesanan**! 📦";

            case 'payment_inquiry':
                return $prefix . "💳 Toko **Nnaco.SA** menyediakan metode pembayaran yang praktis dan otomatis:\n\n"
                     . "• **QRIS (Otomatis)**: Bisa scan pakai GoPay, OVO, Dana, ShopeePay, BCA Mobile, Livin' Mandiri, dll.\n"
                     . "• **Transfer Bank**: Tersedia untuk Bank BCA, Mandiri, dan BNI.\n"
                     . "• **Verifikasi Otomatis**: Transaksi langsung terverifikasi tanpa perlu konfirmasi manual yang lama! ✨";

            // =================================================================
            // PRODUK & STOK TOKO
            // =================================================================
            case 'product_bestseller':
                $items = [];
                foreach ($data as $p) {
                    $price = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                    $items[] = "• **{$p['name']}** (Terjual: {$p['sales_count']} pcs) - Rp " . number_format($price, 0, ',', '.');
                }
                $list = implode("\n", $items);
                return $prefix . "Ini dia koleksi aksesoris **paling laris (Best Seller)** di Nnaco.SA:\n\n{$list}\n\nSemua produk di atas ready stok dan siap dikirim ya! 💕";

            case 'product_low_stock':
                if (empty($data)) {
                    return $prefix . "Kabar baik! Saat ini semua stok aksesoris di toko kami dalam kondisi aman dan mencukupi. 🌸";
                }
                $items = [];
                foreach ($data as $p) {
                    $items[] = "• **{$p['name']}** - Sisa **{$p['stock']} pcs**";
                }
                $list = implode("\n", $items);
                if ($isAdmin) {
                    return "Halo Admin **{$displayName}**! ⚠️ Berikut produk yang **stoknya menipis (&le; 5 unit)** dan perlu segera direstock:\n\n{$list}\n\nSilakan buka menu **Manajemen Stok** untuk update barang! 📦";
                }
                return "Halo Kak! Berikut daftar produk aksesoris yang **stoknya menipis (kritis)**:\n\n{$list}\n\nYuk checkout sekarang sebelum kehabisan! 🛍️✨";

            case 'product_cheapest':
                $items = [];
                foreach ($data as $p) {
                    $price = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                    $items[] = "• **{$p['name']}** - **Rp " . number_format($price, 0, ',', '.') . "**";
                }
                $list = implode("\n", $items);
                return $prefix . "Ini dia rekomendasi aksesoris **termurah dan ramah di kantong** di Nnaco.SA:\n\n{$list}\n\nCantik, hemat, dan berkualitas! 💕";

            case 'product_expensive':
                $items = [];
                foreach ($data as $p) {
                    $price = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                    $items[] = "• **{$p['name']}** - **Rp " . number_format($price, 0, ',', '.') . "**";
                }
                $list = implode("\n", $items);
                return $prefix . "Ini dia koleksi aksesoris **paling mewah & premium** di Nnaco.SA:\n\n{$list}\n\nDesain eksklusif dan material pilihan! ✨";

            case 'categories_list':
                $items = [];
                foreach ($data as $c) {
                    $items[] = "• **{$c['name']}** ({$c['product_count']} model)";
                }
                $list = implode("\n", $items);
                return $prefix . "Di toko **Nnaco.SA** tersedia berbagai kategori aksesoris cantik:\n\n{$list}\n\nSilakan pilih kategori favoritmu di menu **Katalog** ya! 🌸";

            case 'promo_query':
                $coupons = $data['coupons'] ?? [];
                if (!empty($coupons)) {
                    $items = [];
                    foreach ($coupons as $c) {
                        $pot = ($c['discount_type'] === 'percentage') ? "Diskon {$c['discount_value']}%" : "Potongan Rp " . number_format($c['discount_value'], 0, ',', '.');
                        $items[] = "• Kode Kupon: **{$c['code']}** ({$pot}, Min. Belanja Rp " . number_format($c['minimum_purchase'], 0, ',', '.') . ")";
                    }
                    $list = implode("\n", $items);
                    return $prefix . "Hari ini ada voucher dan promo spesial di toko kami:\n\n{$list}\n\nGunakan kodenya saat checkout untuk dapat potongan harga langsung ya! 🎁✨";
                }
                return $prefix . "Saat ini banyak produk yang sedang memiliki potongan harga langsung di etalase kami lho! Silakan jelajahi etalase untuk menikmati promo diskonnya ya! 💕";

            case 'reviews_query':
                if (empty($data)) {
                    return $prefix . "Toko **Nnaco.SA** selalu menjaga kualitas produk dan kepuasan pelanggan dengan rating rata-rata bintang 5 dan pengemasan rapi anti rusak! 💕";
                }
                $items = [];
                foreach ($data as $rv) {
                    $items[] = "• **{$rv['product_name']}** (Bintang {$rv['rating']}/5): \"{$rv['comment']}\"";
                }
                $list = implode("\n", $items);
                return $prefix . "Ini beberapa ulasan kepuasan pelanggan terbaru di **Nnaco.SA**:\n\n{$list}\n\nKualitas terjamin dan 100% sesuai foto! 🌸";

            case 'order_inquiry':
                if (!$isLoggedIn) {
                    return "Halo Kak! Untuk memeriksa jumlah pesanan atau tagihan yang belum dibayar, silakan **login terlebih dahulu** ke akun Kakak di Nnaco.SA ya 💕";
                }

                $unpaidCount = $data['unpaid_count'] ?? 0;
                if ($unpaidCount > 0) {
                    $totalFormatted = 'Rp ' . number_format($data['unpaid_total'] ?? 0, 0, ',', '.');
                    $orderList = [];
                    foreach (($data['unpaid_orders'] ?? []) as $uo) {
                        $orderList[] = "• **{$uo['order_number']}** (Rp " . number_format($uo['total_amount'], 0, ',', '.') . ")";
                    }
                    $listStr = implode("\n", $orderList);
                    return "Halo Kak **{$displayName}**! Saat ini kamu memiliki **{$unpaidCount} pesanan yang belum dibayar** dengan total tagihan **{$totalFormatted}**:\n\n{$listStr}\n\nSilakan selesaikan pembayaran melalui menu **Riwayat Pesanan** ya! 💕";
                }

                return "Halo Kak **{$displayName}**! Kamu saat ini **tidak memiliki pesanan yang belum dibayar**. Semua transaksi kamu sebelumnya telah lunas atau selesai. Terima kasih banyak telah berbelanja di Nnaco.SA! 🌸";

            case 'identity_inquiry':
                if ($isLoggedIn) {
                    if ($isAdmin) {
                        return "Halo Admin **{$displayName}**! ✨ Kamu saat ini terhubung sebagai Administrator resmi toko di **Nnaco.SA**. Siap memantau data dasbor, omzet pendapatan, pesanan toko, atau persediaan produk? 📊";
                    }
                    return "Halo Kak **{$displayName}**! ✨ Kamu saat ini terhubung sebagai Pelanggan setia di **Nnaco.SA**. Ada data pesanan, info stok aksesoris, atau promo yang ingin kamu cek hari ini? 💕";
                }
                return "Halo Kak! Saat ini Kakak terhubung sebagai **Pengunjung Toko (Tamu)**. Silakan login ke akun Kakak untuk melihat riwayat pesanan dan promo personal ya! 🌸";

            case 'general_product_count':
                $tot = $data['total_active'] ?? 0;
                $stok = $data['total_stock'] ?? 0;
                $cats = !empty($data['categories']) 
                    ? implode(', ', $data['categories']) 
                    : 'Jedai, Jepit Rambut, Kalung, Cincin, Gelang, dan Anting';
                return $prefix . "Saat ini toko **Nnaco.SA** memiliki **{$tot} produk aksesoris aktif** yang siap dibeli dengan total persediaan **{$stok} unit stok**. Kategori kami meliputi {$cats}. Silakan klik menu **Katalog** untuk melihat koleksi lengkap ya! 🌸";

            case 'product_inquiry':
                $found = $data['found'] ?? (!empty($data['products']));
                $productsList = $data['products'] ?? [];
                if ($found && !empty($productsList)) {
                    $items = [];
                    foreach ($productsList as $p) {
                        $price = (!empty($p['discount_price']) && $p['discount_price'] > 0) ? $p['discount_price'] : $p['price'];
                        $stockText = ($p['stock'] > 0) ? "Stok: {$p['stock']} pcs" : "Stok Habis";
                        $items[] = "• **{$p['name']}** - Rp " . number_format($price, 0, ',', '.') . " ({$stockText})";
                    }
                    $list = implode("\n", $items);
                    return $prefix . "Berikut koleksi aksesoris yang cocok dan tersedia di **Nnaco.SA**:\n\n{$list}\n\nAda produk yang ingin kamu tanyakan lebih lanjut? 💕";
                }

                $term = !empty($data['search_term']) ? " terkait **\"{$data['search_term']}\"**" : "";
                $altItems = [];
                foreach (($data['recommendations'] ?? []) as $rec) {
                    $rPrice = (!empty($rec['discount_price']) && $rec['discount_price'] > 0) ? $rec['discount_price'] : $rec['price'];
                    $altItems[] = "• **{$rec['name']}** (Rp " . number_format($rPrice, 0, ',', '.') . ")";
                }
                $altStr = !empty($altItems) 
                    ? "\n\nSebagai gantinya, Kakak mungkin tertarik dengan beberapa koleksi favorit kami ini:\n" . implode("\n", $altItems)
                    : "";

                return $prefix . "Mohon maaf ya, produk{$term} saat ini belum tersedia di katalog toko kami. Namun jangan khawatir, Nnaco.SA punya beragam koleksi cantik lainnya seperti Jedai Korea, Kalung Rose Gold, Cincin Daisy, dan Gelang Zirconia yang siap melengkapi gayamu!{$altStr}\n\nYuk intip menu **Katalog** atau tanyakan rekomendasi aksesoris lainnya! 🌸";

            case 'care_tips':
                return $prefix . "💎 Berikut tips praktis agar koleksi aksesoris kesayanganmu tetap berkilau dan tidak mudah pudar:\n\n"
                     . "1. **Hindari Bahan Kimia**: Lepaskan aksesoris saat memakai parfum, lotion, atau semprotan rambut.\n"
                     . "2. **Lepas Saat Mandi/Berenang**: Hindari air berklorin atau air laut agar kilau lapisan emas dan mutiara tetap awet.\n"
                     . "3. **Simpan di Tempat Kering**: Simpan di dalam pouch atau kotak perhiasan tersendiri agar tidak tergores.\n"
                     . "4. **Bersihkan dengan Lembut**: Usap perlahan menggunakan kain microfiber halus setelah dipakai.\n\n"
                     . "Koleksi aksesoris Nnaco.SA menggunakan material titanium & 18K plated berkualitas tinggi yang awet dan aman dipakai sehari-hari! ✨";

            case 'cod_inquiry':
                return $prefix . "Mohon maaf ya, saat ini toko **Nnaco.SA** belum menyediakan opsi pembayaran COD (Bayar di Tempat). 🙏\n\n"
                     . "Namun jangan khawatir! Kami menyediakan metode pembayaran digital yang sangat mudah, aman, dan instan:\n"
                     . "• **QRIS Otomatis** (GoPay, OVO, Dana, ShopeePay, BCA, Mandiri)\n"
                     . "• **Transfer Bank Virtual Account** (BCA, Mandiri, BNI)\n\n"
                     . "Semua pembayaran terverifikasi otomatis dalam hitungan detik lho! Ada koleksi yang ingin kamu pinang hari ini? 💕";

            case 'how_to_order':
                return $prefix . "Belanja aksesoris cantik di **Nnaco.SA** gampang banget lho! Ikuti langkah praktis ini ya:\n\n"
                     . "1. 🛍️ Jelajahi koleksi di menu **Katalog** lalu pilih produk impianmu.\n"
                     . "2. 🛒 Klik **Beli Sekarang** atau masukkan ke keranjang belanja.\n"
                     . "3. 📦 Isi alamat pengiriman dan pilih ekspedisi (SiCepat / J&T / JNE).\n"
                     . "4. 🏷️ Masukkan kode kupon promo (misal: **NNASANEW** untuk diskon 15%) jika ada.\n"
                     . "5. 💳 Selesaikan pembayaran via QRIS atau Transfer Bank.\n\n"
                     . "Setelah pembayaran berhasil, pesanan langsung diproses dan nomor resi dapat dipantau di menu **Lacak Pesanan**! 🌸";

            case 'gift_recommendation':
                return $prefix . "Wah, pilihan yang manis banget! 🎁 Untuk kado istimewa sahabat atau orang tersayang, berikut rekomendasi terbaik dari Nnaco.SA:\n\n"
                     . "1. **Romantic Rose Gold Luxury Gift Box Set (4-in-1)** (Rp 149.000) – *Best seller kado!* Sudah lengkap dengan box mewah berpita dan isi 4 aksesoris cantik.\n"
                     . "2. **Aura Rose Gold Clover Pendant Necklace** (Rp 69.000) – Kalung liontin clover simbol cinta dan keberuntungan.\n"
                     . "3. **Blooming Daisy Adjustable Ring 18K Plated** (Rp 45.000) – Cincin bunga manis yang ukurannya bisa diatur (adjustable).\n"
                     . "4. **Dainty Freshwater Pearl Drop Earrings** (Rp 65.000) – Anting mutiara air tawar anggun untuk momen spesial.\n\n"
                     . "Mau aku bantu pilihkan yang paling cocok dengan gaya sahabatmu? 💕";

            case 'greeting':
                if ($isAdmin) {
                    return "Halo Admin **{$displayName}**! 📊 Asisten toko NNasa AI siap membantu memantau omzet penjualan, pesanan masuk, persediaan stok, atau riwayat pembeli. Apa yang ingin dicek hari ini? 💼";
                }
                return "Halo Kak **{$displayName}**! Selamat datang di **Nnaco.SA** 💕 Aku asisten virtual toko yang siap membantu rekomendasi aksesoris, info stok, cek pesanan, hingga tips belanja. Ada yang bisa aku bantu hari ini? 🌸";

            case 'general':
            default:
                if ($isAdmin) {
                    return "Halo Admin **{$displayName}**! 📊 Asisten toko NNasa AI siap membantu mengecek performa penjualan, pesanan masuk, stok menipis, atau riwayat transaksi pelanggan. Apa yang ingin kamu pantau sekarang? 💼";
                }
                return "Halo Kak **{$displayName}**! Selamat datang di **Nnaco.SA** 💕 Aku asisten virtual toko yang bisa membantu mengecek pesanan, info stok aksesoris, produk terlaris, promo kupon, hingga panduan belanja. Ada yang bisa aku bantu hari ini? 🌸";
        }
    }
    private function callGeminiAPI(string $systemInstruction, string $databaseContext, string $userMessage): array
    {
        $primary = !empty($this->aiModel) ? $this->aiModel : 'gemini-3.5-flash-lite';
        $candidateModels = [$primary, 'gemini-3.5-flash-lite', 'gemini-3.8-flash', 'gemini-flash-latest'];
        $candidateModels = array_values(array_unique(array_filter($candidateModels)));

        $candidateKeys = !empty($this->aiApiKeysPool) ? $this->aiApiKeysPool : (strlen($this->aiApiKey) >= 20 ? [$this->aiApiKey] : []);

        $userPromptWithContext = (!empty($databaseContext) ? "=== DATA RESMI DATABASE TOKO NNACO.SA ===\n{$databaseContext}\n==============================================\n\n" : "")
                               . "Pertanyaan Pengguna: " . $userMessage;

        $lastError = 'Unknown error';

        foreach ($candidateKeys as $activeKey) {
            foreach ($candidateModels as $modelName) {
                if (in_array($modelName, ['gemini-1.5-flash', 'gemini-2.5-flash', 'gemini-pro'])) {
                    continue; // Model lama yang sudah dinonaktifkan oleh Google
                }

                $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . urlencode($activeKey);

                $payload = [
                    'system_instruction' => [
                        'parts' => [
                            ['text' => $systemInstruction]
                        ]
                    ],
                    'contents' => [
                        [
                            'role'  => 'user',
                            'parts' => [
                                ['text' => $userPromptWithContext]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature'     => 0.4,
                        'maxOutputTokens' => 800
                    ]
                ];

                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'User-Agent: NnacoSA-Chatbot/2.0'
                    ],
                    CURLOPT_POSTFIELDS     => json_encode($payload),
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT        => 12,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0
                ]);

                $response   = curl_exec($ch);
                $curlErrNo  = curl_errno($ch);
                $curlErrMsg = curl_error($ch);
                $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($curlErrNo !== 0 || $response === false) {
                    $lastError = "cURL Error ({$curlErrNo}): {$curlErrMsg}";
                    continue;
                }

                $result = json_decode($response, true);
                if ($httpCode === 200 && !empty($result['candidates'][0]['content']['parts'][0]['text'])) {
                    return [
                        'status' => 'success',
                        'reply'  => trim($result['candidates'][0]['content']['parts'][0]['text']),
                        'model'  => $modelName
                    ];
                }

                if ($httpCode === 401 || $httpCode === 403) {
                    // API Key tidak valid, coba key berikutnya di pool
                    break;
                }

                $lastError = "Google Gemini API Error (HTTP {$httpCode}): " . ($result['error']['message'] ?? $response);
            }
        }

        return [
            'status'  => 'error',
            'message' => $lastError
        ];
    }
}
