<?php
/**
 * Platform Feature Registry & Navigator
 * Store: nnasa_ca Accessories Marketplace
 * Catalogs all customer and admin features, pages, URLs, capabilities, and permissions
 */

class FeatureRegistry {
    private static array $features = [
        // ==========================================
        // Customer Shopping & Account Features
        // ==========================================
        'home' => [
            'name' => 'Beranda (Homepage)',
            'role' => 'public',
            'url' => 'index.php',
            'description' => 'Halaman utama toko menampilkan hero banner slider promosi, ikon kategori aksesoris, flash sale diskon, produk terlaris, koleksi terbaru, dan ulasan pembeli.',
            'category' => 'Belanja',
            'keywords' => ['beranda', 'home', 'halaman utama', 'banner', 'depan']
        ],
        'catalog' => [
            'name' => 'Katalog Semua Produk',
            'role' => 'public',
            'url' => 'products.php',
            'description' => 'Katalog lengkap seluruh aksesoris wanita dengan filter kategori, pencarian teks, rentang harga min-max, status ketersediaan stok, dan pengurutan (terlaris, terbaru, termurah, termahal).',
            'category' => 'Belanja',
            'keywords' => ['katalog', 'semua produk', 'daftar produk', 'cari barang', 'belanja']
        ],
        'product_detail' => [
            'name' => 'Detail Produk & Varian',
            'role' => 'public',
            'url' => 'product-detail.php?slug={slug}',
            'description' => 'Halaman detail aksesoris dengan galeri multi-gambar, pemilih varian (warna/ukuran), stok riil per varian, ulasan pembeli bintang 1-5, dan tombol tambah ke keranjang.',
            'category' => 'Belanja',
            'keywords' => ['detail produk', 'lihat produk', 'varian', 'deskripsi', 'foto']
        ],
        'cart' => [
            'name' => 'Keranjang Belanja',
            'role' => 'customer',
            'url' => 'cart.php',
            'description' => 'Keranjang interaktif untuk memilih item yang ingin dibeli, tombol pengubah kuantitas (+/-), hapus item, dan perhitungan subtotal otomatis.',
            'category' => 'Belanja',
            'keywords' => ['keranjang', 'cart', 'troli', 'belanjaan', 'tambah keranjang']
        ],
        'checkout' => [
            'name' => 'Checkout & Pengiriman',
            'role' => 'customer',
            'url' => 'checkout.php',
            'description' => 'Proses checkout pesanan dengan transaksi atomik database (row-locking), pemilihan buku alamat, kurir ekspedisi (SiCepat, JNE, J&T), dan aplikasi kupon voucher diskon (misal NNASANEW).',
            'category' => 'Belanja',
            'keywords' => ['checkout', 'buat pesanan', 'ongkir', 'kurir', 'kupon']
        ],
        'payment' => [
            'name' => 'Simulasi Pembayaran',
            'role' => 'customer',
            'url' => 'payment.php?order_number={order_number}',
            'description' => 'Halaman pembayaran dengan QRIS SVG dinamis, nomor Virtual Account (BCA, Mandiri, BRI), transfer manual, serta tombol simulasi pelunasan sandbox DEMO.',
            'category' => 'Transaksi',
            'keywords' => ['bayar', 'pembayaran', 'qris', 'virtual account', 'rekening']
        ],
        'orders' => [
            'name' => 'Pesanan Saya (Riwayat Transaksi)',
            'role' => 'customer',
            'url' => 'orders.php',
            'description' => 'Daftar seluruh pesanan pelanggan yang dipisahkan tab status: Menunggu Pembayaran, Diproses, Dikirim, Selesai, dan Dibatalkan.',
            'category' => 'Akun',
            'keywords' => ['pesanan saya', 'riwayat pesanan', 'riwayat transaksi', 'daftar order']
        ],
        'order_detail' => [
            'name' => 'Rincian Pesanan & Ulasan',
            'role' => 'customer',
            'url' => 'order-detail.php?id={id}',
            'description' => 'Snapshot permanen produk dan alamat, timeline riwayat perubahan status, nomor resi, pembatalan pesanan belum bayar, serta formulir pemberian rating bintang.',
            'category' => 'Akun',
            'keywords' => ['rincian pesanan', 'detail order', 'beri ulasan', 'rating', 'batal pesanan']
        ],
        'tracking' => [
            'name' => 'Lacak Pesanan & Resi',
            'role' => 'public',
            'url' => 'tracking.php',
            'description' => 'Halaman pelacakan status logistik kurir ekspedisi dengan progress bar 4 langkah (Pesanan Dibuat -> Diproses -> Dalam Pengiriman -> Diterima) cukup dengan memasukkan nomor pesanan.',
            'category' => 'Bantuan',
            'keywords' => ['lacak pesanan', 'lacak resi', 'tracking', 'posisi paket', 'kurir']
        ],
        'wishlist' => [
            'name' => 'Favorit Saya (Wishlist)',
            'role' => 'customer',
            'url' => 'wishlist.php',
            'description' => 'Koleksi aksesoris yang disimpan pelanggan untuk dibeli di kemudian hari, dilengkapi tombol cepat tambah ke keranjang.',
            'category' => 'Akun',
            'keywords' => ['favorit', 'wishlist', 'disimpan', 'suka']
        ],
        'profile' => [
            'name' => 'Profil Saya',
            'role' => 'customer',
            'url' => 'profile.php',
            'description' => 'Pengaturan data diri pelanggan: ubah nama lengkap, nomor WhatsApp, alamat email, dan ganti kata sandi terlindungi verifikasi password lama.',
            'category' => 'Akun',
            'keywords' => ['profil', 'ubah profil', 'ganti password', 'akun saya']
        ],
        'addresses' => [
            'name' => 'Buku Alamat Pengiriman',
            'role' => 'customer',
            'url' => 'addresses.php',
            'description' => 'Kelola banyak alamat pengiriman (tambah, edit, hapus, dan atur alamat utama/default) lengkap dengan provinsi, kota, kecamatan, kelurahan, dan kode pos.',
            'category' => 'Akun',
            'keywords' => ['alamat', 'buku alamat', 'alamat pengiriman', 'tambah alamat']
        ],
        'help' => [
            'name' => 'Pusat Bantuan & FAQ',
            'role' => 'public',
            'url' => 'help.php',
            'description' => 'Kumpulan jawaban pertanyaan yang sering diajukan mengenai cara pemesanan, pembayaran, pengiriman, kebijakan pengembalian produk, dan voucher belanja.',
            'category' => 'Bantuan',
            'keywords' => ['bantuan', 'faq', 'pertanyaan', 'panduan']
        ],
        'about' => [
            'name' => 'Tentang nnasa_ca',
            'role' => 'public',
            'url' => 'about.php',
            'description' => 'Informasi profil butik online nnasa_ca, filosofi produk aksesoris berkualitas, packaging cantik, dan komitmen pelayanan pelanggan.',
            'category' => 'Informasi',
            'keywords' => ['tentang', 'about', 'cerita', 'profil toko']
        ],
        'contact' => [
            'name' => 'Hubungi Kami',
            'role' => 'public',
            'url' => 'contact.php',
            'description' => 'Kontak resmi WhatsApp, email CS, alamat butik fisik, dan formulir kirim pesan saran/pertanyaan.',
            'category' => 'Informasi',
            'keywords' => ['kontak', 'hubungi kami', 'whatsapp', 'alamat kantor', 'telepon']
        ],
        'auth' => [
            'name' => 'Login & Registrasi Pelanggan',
            'role' => 'public',
            'url' => 'login.php',
            'description' => 'Sistem autentikasi aman dengan enkripsi password BCrypt, proteksi CSRF, proteksi session fixation, dan pembatasan hak akses.',
            'category' => 'Akun',
            'keywords' => ['login', 'masuk', 'daftar', 'register', 'buat akun']
        ],

        // ==========================================
        // Admin Dashboard & Management Modules
        // ==========================================
        'admin_dashboard' => [
            'name' => 'Dashboard Utama Admin',
            'role' => 'admin',
            'url' => 'admin/index.php',
            'description' => 'Pusat pemantauan toko: kartu metrik riil (omzet valid, total pesanan, produk aktif, total stok, peringatan stok menipis, manajemen user), grafik tren penjualan via Chart.js, produk terlaris, dan pesanan terbaru.',
            'category' => 'Admin - Utama',
            'keywords' => ['dashboard admin', 'statistik toko', 'grafik penjualan', 'omzet', 'dashboard', 'dasbor', 'panel admin', 'admin panel', 'halaman admin']
        ],
        'admin_products' => [
            'name' => 'Manajemen Produk Admin',
            'role' => 'admin',
            'url' => 'admin/products.php',
            'description' => 'Katalog produk admin dengan filter kategori, status aktif/draft, filter stok habis/menipis, pengurutan, tombol saklar status instan, dan tombol hapus.',
            'category' => 'Admin - Katalog',
            'keywords' => ['kelola produk', 'edit produk', 'tambah produk', 'status produk']
        ],
        'admin_product_form' => [
            'name' => 'Tambah / Edit Produk',
            'role' => 'admin',
            'url' => 'admin/product-form.php',
            'description' => 'Formulir pembuatan dan pengeditan produk lengkap: SKU, nama, kategori, harga, harga coret/promo, stok, bobot, deskripsi, multi-upload foto, dan pembuat baris varian dinamis.',
            'category' => 'Admin - Katalog',
            'keywords' => ['form produk', 'input produk', 'upload foto produk']
        ],
        'admin_categories' => [
            'name' => 'Kategori Produk',
            'role' => 'admin',
            'url' => 'admin/categories.php',
            'description' => 'Manajemen struktur kategori bertingkat (parent-child), pembuatan slug otomatis, upload gambar ikon, dan monitoring jumlah produk per kategori.',
            'category' => 'Admin - Katalog',
            'keywords' => ['kategori admin', 'tambah kategori', 'kelola kategori']
        ],
        'admin_variants' => [
            'name' => 'Manajemen Varian Produk',
            'role' => 'admin',
            'url' => 'admin/variants.php',
            'description' => 'Pemantauan seluruh varian warna dan ukuran di toko beserta fitur update stok langsung per varian.',
            'category' => 'Admin - Katalog',
            'keywords' => ['varian produk', 'stok varian', 'warna varian', 'ukuran varian']
        ],
        'admin_stock' => [
            'name' => 'Manajemen Stok & Kartu Stok',
            'role' => 'admin',
            'url' => 'admin/stock.php',
            'description' => 'Pusat pemantauan stok rendah/kritis, form restock atau penyesuaian manual (+/-), dan audit trail mutasi stok (tabel stock_movements).',
            'category' => 'Admin - Inventaris',
            'keywords' => ['stok admin', 'restock', 'kartu stok', 'mutasi stok', 'stok menipis']
        ],
        'admin_orders' => [
            'name' => 'Manajemen Pesanan Masuk',
            'role' => 'admin',
            'url' => 'admin/orders.php',
            'description' => 'Daftar seluruh pesanan pelanggan dengan filter rentang tanggal, filter status pembayaran & pesanan, pencarian nomor pesanan atau nama pelanggan.',
            'category' => 'Admin - Transaksi',
            'keywords' => ['pesanan admin', 'kelola pesanan', 'order masuk', 'daftar pesanan']
        ],
        'admin_order_detail' => [
            'name' => 'Proses Pesanan & Resi',
            'role' => 'admin',
            'url' => 'admin/order-detail.php?id={id}',
            'description' => 'Pemrosesan pesanan: update status (Diproses -> Dikirim -> Selesai / Dibatalkan), input kurir & nomor resi, serta restock barang otomatis jika pesanan dibatalkan.',
            'category' => 'Admin - Transaksi',
            'keywords' => ['proses pesanan', 'input resi', 'batalkan pesanan']
        ],
        'admin_payments' => [
            'name' => 'Audit & Verifikasi Pembayaran',
            'role' => 'admin',
            'url' => 'admin/payments.php',
            'description' => 'Rekapitulasi transaksi pembayaran: metode QRIS/VA/Transfer, kode referensi unik, jumlah uang, status pelunasan, dan tanggal pembayaran.',
            'category' => 'Admin - Transaksi',
            'keywords' => ['verifikasi pembayaran', 'log bayar', 'transaksi masuk']
        ],
        'admin_shipments' => [
            'name' => 'Pengiriman & Nomor Resi',
            'role' => 'admin',
            'url' => 'admin/shipments.php',
            'description' => 'Monitoring pengiriman barang, update status kurir SiCepat/JNE/J&T, dan nomor resi pengiriman.',
            'category' => 'Admin - Logistik',
            'keywords' => ['pengiriman admin', 'resi pengiriman', 'logistik']
        ],
        'admin_customers' => [
            'name' => 'Data Pelanggan',
            'role' => 'admin',
            'url' => 'admin/customers.php',
            'description' => 'Database pelanggan terdaftar, nomor WhatsApp, alamat utama, jumlah pesanan, dan total nilai transaksi belanja (Lifetime Value / LTV).',
            'category' => 'Admin - Pengguna',
            'keywords' => ['data pelanggan', 'daftar member', 'customer list']
        ],
        'admin_reviews' => [
            'name' => 'Moderasi Ulasan Pembeli',
            'role' => 'admin',
            'url' => 'admin/reviews.php',
            'description' => 'Moderasi ulasan dan rating produk dari pembeli: tombol setujui (Approve), tunda (Pending), atau sembunyikan (Hide).',
            'category' => 'Admin - Interaksi',
            'keywords' => ['moderasi ulasan', 'ulasan produk', 'rating pembeli']
        ],
        'admin_coupons' => [
            'name' => 'Voucher & Kupon Promo',
            'role' => 'admin',
            'url' => 'admin/coupons.php',
            'description' => 'CRUD kupon diskon toko (tipe persentase atau nominal rupiah), minimum pembelian, batas maksimal diskon, tanggal berlaku, dan kuota pemakaian.',
            'category' => 'Admin - Promosi',
            'keywords' => ['kelola kupon', 'tambah voucher', 'promo diskon']
        ],
        'admin_banners' => [
            'name' => 'Banner Slider Beranda',
            'role' => 'admin',
            'url' => 'admin/banners.php',
            'description' => 'Manajemen banner promo slider beranda: upload gambar, judul, subtitle, tautan URL target, urutan tampilan, dan status aktif.',
            'category' => 'Admin - Tampilan',
            'keywords' => ['banner promo', 'hero slider', 'banner beranda']
        ],
        'admin_reports' => [
            'name' => 'Laporan Penjualan & Ekspor CSV',
            'role' => 'admin',
            'url' => 'admin/reports.php',
            'description' => 'Laporan penjualan komprehensif berdasarkan rentang tanggal: omzet kotor, total diskon kupon, omzet bersih, produk terlaris, laporan stok, dan ekspor spreadsheet CSV langsung.',
            'category' => 'Admin - Laporan',
            'keywords' => ['laporan penjualan', 'laporan keuangan', 'ekspor csv', 'rekap omzet']
        ],
        'admin_settings' => [
            'name' => 'Pengaturan Toko & AI',
            'role' => 'admin',
            'url' => 'admin/settings.php',
            'description' => 'Konfigurasi nama toko, nomor WhatsApp CS, rekening bank, ongkir default, batas alert stok menipis, dan Konfigurasi AI Assistant (Gemini API key & sapaan pembuka).',
            'category' => 'Admin - Pengaturan',
            'keywords' => ['pengaturan toko', 'konfigurasi ai', 'setting bank', 'api key']
        ],
        'admin_users' => [
            'name' => 'Manajemen Pengguna (User CRUD)',
            'role' => 'admin',
            'url' => 'admin/users.php',
            'description' => 'Pengelolaan akun pengguna: tabel daftar seluruh user, tambah user baru, ubah role hak akses (admin/staff/customer), reset password, dan hapus akun.',
            'category' => 'Admin - Pengguna',
            'keywords' => ['manajemen user', 'manajemen pengguna', 'user', 'pengguna', 'kelola user', 'data user', 'crud user', 'kelola staf', 'tambah admin', 'role pengguna', 'daftar user']
        ],
        'admin_activity_logs' => [
            'name' => 'Log Aktivitas Sistem',
            'role' => 'admin',
            'url' => 'admin/activity-logs.php',
            'description' => 'Audit trail rekaman seluruh aksi operasional admin dan pelanggan: login, tambah produk, ubah pesanan, dan checkout lengkap dengan alamat IP.',
            'category' => 'Admin - Keamanan',
            'keywords' => ['log aktivitas', 'audit trail', 'riwayat aksi']
        ]
    ];

    /**
     * Get all mapped features filtered by role
     */
    public static function getFeatures(?string $role = 'public'): array {
        if ($role === 'admin' || $role === 'staff') {
            return self::$features;
        }

        // For public/customer: only return public and customer features
        return array_filter(self::$features, function($f) use ($role) {
            if ($f['role'] === 'public') return true;
            if ($f['role'] === 'customer' && $role === 'customer') return true;
            return false;
        });
    }

    /**
     * Search features by query text
     */
    public static function searchFeatures(string $query, ?string $role = 'public'): array {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $available = self::getFeatures($role);
        $matches = [];

        foreach ($available as $key => $feat) {
            $name = mb_strtolower($feat['name'], 'UTF-8');
            $desc = mb_strtolower($feat['description'], 'UTF-8');
            $cat = mb_strtolower($feat['category'], 'UTF-8');

            $score = 0;
            if (str_contains($name, $q)) $score += 10;
            if (str_contains($desc, $q)) $score += 3;
            if (str_contains($cat, $q)) $score += 5;

            foreach ($feat['keywords'] as $kw) {
                if (str_contains($q, $kw) || str_contains($kw, $q)) {
                    $score += 8;
                }
            }

            if ($score > 0) {
                $feat['key'] = $key;
                $feat['score'] = $score;
                $matches[] = $feat;
            }
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
        return $matches;
    }
}
