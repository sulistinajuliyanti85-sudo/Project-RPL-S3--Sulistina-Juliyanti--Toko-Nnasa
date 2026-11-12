# nnasa_ca — Website Marketplace Toko Aksesoris Wanita

Website e-commerce marketplace toko aksesoris wanita **nnasa_ca** dengan pengalaman belanja modern ala marketplace, desain orisinal yang elegan, feminin, dan bersih (*dusty pink*, *warm beige*, dan aksen *rose gold*). Dibangun menggunakan **PHP 8+**, **MySQL / MariaDB (PDO)**, **Bootstrap 5**, dan **Vanilla CSS/JS** yang sepenuhnya terintegrasi secara lokal di lingkungan **XAMPP**.

---

## 🌸 1. Identitas & Desain Merek
- **Nama Toko**: `nnasa_ca`
- **Kategori Spesialisasi**: Aksesoris rambut (hair clip, jedai, scrunchie), kalung titanium/mutiara, gelang charm, cincin aesthetic, anting, aksesoris tas (bag charm), fashion accessories, dan paket gift box bundling.
- **Palet Warna**:
  - Primary: *Dusty Pink* (`#C27D86`, `#B0656F`)
  - Accent: *Rose Gold Metallic Gradient* (`linear-gradient(135deg, #ECC5C8, #DDA7A5)`)
  - Neutral Soft: *Warm Beige / Soft Cream* (`#FFF9F8`, `#FDF3F2`)
  - Text & Dark: *Deep Charcoal* (`#2D2426`)
- **Tipografi**: *Playfair Display* (Heading mewah) & *Plus Jakarta Sans* (Body text modern & nyaman dibaca).
- **Desain Orisinal**: Bukan salinan mentah UI Shopee, melainkan dirancang khusus dengan estetika butik aksesoris mewah yang responsif di Desktop maupun Smartphone (dengan bilah navigasi bawah / Mobile Bottom Nav).

---

## 🛠️ 2. Arsitektur & Teknologi
| Komponen | Spesifikasi / Teknologi |
| :--- | :--- |
| **Server Lokal** | Apache 2.4 (via XAMPP) |
| **Backend** | PHP 8.0+ (Diuji pada PHP 8.2.12) |
| **Database** | MySQL / MariaDB 10.4+ (`nnasa_ca_db`) menggunakan Driver **PDO** |
| **Frontend Framework** | Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 |
| **Styling Kustom** | Vanilla CSS3 (`public/assets/css/style.css` & `public/assets/css/admin.css`) |
| **Interaktivitas & AJAX** | Vanilla JavaScript Fetch API (`public/assets/js/app.js`) |
| **Grafik & Visualisasi** | Chart.js 4.4.0 (Grafik penjualan dinamis di dashboard admin) |
| **Asset Image Handling** | Dynamic Vector SVG Fallback Engine + Secure File Upload Handler |

---

## 📂 3. Struktur Direktori Proyek
```text
nnasa_ca/
├── admin/                     # Dashboard & Manajemen Admin (18 Modul)
│   ├── index.php              # Dashboard utama, statistik riil & Chart.js
│   ├── products.php           # Manajemen katalog produk
│   ├── product-form.php       # Form tambah/edit produk & varian dinamis
│   ├── categories.php         # Manajemen kategori berjenjang & gambar
│   ├── variants.php           # Manajemen varian & stok per varian
│   ├── stock.php              # Penyesuaian stok manual & log mutasi
│   ├── orders.php             # Monitoring pesanan & filter status
│   ├── order-detail.php       # Proses pesanan, update status, & input nomor resi
│   ├── payments.php           # Verifikasi pembayaran & riwayat transaksi
│   ├── shipments.php          # Manajemen ekspedisi & nomor resi
│   ├── customers.php          # Database pelanggan & riwayat belanja
│   ├── reviews.php            # Moderasi ulasan & rating pelanggan
│   ├── coupons.php            # Manajemen kupon diskon (persen / nominal)
│   ├── banners.php            # Manajemen carousel banner beranda
│   ├── reports.php            # Laporan penjualan, omzet riil, & ekspor CSV
│   ├── settings.php           # Konfigurasi toko, WhatsApp, & rekening bank
│   ├── users.php              # Manajemen admin & hak akses role
│   ├── activity-logs.php      # Audit log aktivitas sistem & keamanan
│   ├── login.php              # Halaman login admin terproteksi
│   ├── logout.php             # Logout sesi admin
│   └── templates/             # Header & footer layout admin
├── api/                       # Endpoint AJAX / REST Internal
│   ├── cart.php               # Operasi keranjang (tambah, update kuantitas, hapus)
│   ├── wishlist.php           # Toggle favorit pelanggan
│   ├── coupon.php             # Validasi kode kupon saat checkout
│   └── payment-sim.php        # Simulator pembayaran DEMO terkontrol
├── app/                       # Logika Inti & Konfigurasi Backend
│   ├── Config/
│   │   ├── config.php         # Konstanta BASE_URL, status, & sesi
│   │   └── database.php       # Koneksi PDO Database Singleton
│   └── Helpers/
│       ├── auth.php           # Middleware autentikasi & otorisasi role
│       ├── functions.php      # Helper rupiah, sanitasi, CSRF, flash message
│       └── upload.php         # Validasi MIME type, ukuran, & upload gambar aman
├── database/                  # Skrip Arsitektur Database
│   ├── schema.sql             # 21 Skema DDL tabel relasional InnoDB utf8mb4
│   └── seed.sql               # Data awal (kategori, produk, varian, voucher, user)
├── public/                    # Aset Publik
│   ├── assets/
│   │   ├── css/               # style.css (pelanggan) & admin.css (admin)
│   │   ├── js/                # app.js (AJAX & interaksi UI)
│   │   └── images/            # Logo SVG & generator placeholder dinamis
│   └── uploads/               # Direktori upload gambar (.htaccess terlindungi)
├── templates/                 # Header, Footer, & Navigasi Mobile Pelanggan
├── tests/                     # Skrip Pengujian Otomatis
│   ├── test_flows.php         # 12 skenario uji integrasi & integritas data
│   └── test_admin_auth.php    # Uji login admin, CSRF, & proteksi sesi
├── about.php                  # Halaman profil & cerita toko nnasa_ca
├── addresses.php              # Buku alamat pengiriman pelanggan
├── cart.php                   # Keranjang belanja interaktif
├── checkout.php               # Checkout dengan transaksi atomik database
├── contact.php                # Kontak toko & formulir pesan
├── help.php                   # Pusat bantuan & FAQ pelanggan
├── index.php                  # Beranda (Hero banner, kategori, flash sale, produk)
├── install.php                # Web installer database 1-klik
├── login.php                  # Login pelanggan
├── logout.php                 # Logout pelanggan
├── order-detail.php           # Detail pesanan, riwayat status, & beri ulasan
├── orders.php                 # Riwayat & tab status pesanan pelanggan
├── payment.php                # Halaman simulasi pembayaran (QRIS, VA, Transfer)
├── product-detail.php         # Galeri, pemilih varian, stok riil, ulasan
├── products.php               # Katalog lengkap, filter kategori/harga, pencarian
├── profile.php                # Profil akun & ganti password aman
├── register.php               # Registrasi akun pelanggan baru
├── tracking.php               # Lacak status pesanan & nomor resi pengiriman
└── wishlist.php               # Halaman produk favorit tersimpan
```

---

## 🗄️ 4. Rancangan Database (`nnasa_ca_db`)
Menggunakan **MySQL / MariaDB** dengan engine **InnoDB** dan charset **utf8mb4_unicode_ci**. Seluruh relasi dilengkapi Foreign Key constraint, indeks pencarian, dan tipe data uang berbasis `DECIMAL(12,2)`:

1. `users`: Akun pengguna, peran (`admin`, `staff`, `customer`), password hash, status.
2. `addresses`: Buku alamat pengiriman (desa, kecamatan, kota, provinsi, kode pos, alamat utama).
3. `categories`: Kategori produk berjenjang (`parent_id`), slug, status, gambar.
4. `products`: Katalog produk (SKU unik, harga reguler, harga coret/diskon, stok master, bobot, status).
5. `product_images`: Galeri gambar tambahan per produk beserta urutan tampilan.
6. `product_variants`: Varian produk (warna, ukuran), penyesuaian harga (`price_adjustment`), dan stok spesifik varian.
7. `carts`: Keranjang belanja terikat ID pengguna.
8. `cart_items`: Item dalam keranjang (terikat varian atau produk tunggal) beserta kuantitas.
9. `wishlists`: Daftar produk favorit pelanggan.
10. `orders`: Header transaksi pesanan (nomor pesanan unik `NNA-YYYYMMDD-XXXX`, snapshot alamat JSON, subtotal, diskon kupon, ongkir, total, status pesanan & pembayaran).
11. `order_items`: Detail barang pesanan dengan snapshot harga, nama, dan SKU saat transaksi.
12. `order_status_history`: Jejak riwayat audit perubahan status pesanan.
13. `payments`: Log transaksi pembayaran (metode, kode referensi unik, jumlah, status, timestamp).
14. `shipments`: Data logistik (kurir JNE/SiCepat/J&T, layanan, nomor resi pengiriman, estimasi).
15. `reviews`: Ulasan dan rating bintang 1-5 dengan status moderasi admin.
16. `coupons`: Manajemen voucher diskon (persen/nominal, minimal belanja, batas diskon, kuota pemakaian).
17. `coupon_usages`: Audit pemakaian kupon per pesanan untuk mencegah eksploitasi ganda.
18. `banners`: Banner promo slider beranda dengan jadwal tayang.
19. `stock_movements`: Audit trail kartu stok (tipe mutasi: restock, penjualan, pembatalan, penyesuaian manual).
20. `settings`: Pengaturan konfigurasi toko berpasangan *key-value*.
21. `activity_logs`: Log audit keamanan dan aksi operasional admin/pengguna.

---

## 🚀 5. Cara Instalasi & Menjalankan di XAMPP

### Prasyarat
1. Telah menginstal **XAMPP** (disarankan versi dengan PHP 8.0 ke atas).
2. Memastikan modul **Apache** dan **MySQL** berjalan aktif di XAMPP Control Panel.

### Langkah Cepat (1-Klik via Browser)
1. Letakkan folder proyek ini di dalam folder htdocs XAMPP:
   ```text
   C:\xampp\htdocs\toko
   ```
2. Buka browser dan jalankan skrip Web Installer otomatis:
   ```text
   http://localhost/toko/install.php
   ```
3. Klik tombol **"Pasang & Isi Data Awal Database"**.
4. Sistem akan otomatis:
   - Membuat database `nnasa_ca_db` jika belum ada.
   - Mengeksekusi tabel-tabel di `database/schema.sql`.
   - Mengisi data awal di `database/seed.sql` (katalog aksesoris, varian, voucher, banner, user demo).
5. Setelah instalasi selesai, Anda dapat langsung menjelajahi toko atau masuk ke panel admin!

### Langkah Alternatif (Manual via phpMyAdmin)
1. Buka `http://localhost/phpmyadmin/`.
2. Buat database baru bernama: `nnasa_ca_db` (Collation: `utf8mb4_unicode_ci`).
3. Masuk ke tab **Import**:
   - Pilih file `database/schema.sql`, klik **Go**.
   - Pilih file `database/seed.sql`, klik **Go**.
4. Buka website di browser: `http://localhost/toko/`.

---

## 🔑 6. Akun Pengguna & Kredensial Demo

| Peran (Role) | Nama Akun | Email / Gmail | Kata Sandi | Halaman Login |
| :--- | :--- | :--- | :--- | :--- |
| **Admin Pengujian** | Sulistina Juliyanti (Admin) | `sulistina.admin@gmail.com` | `AdminSulistina2026!` | `http://localhost/toko/admin/login.php` |
| **Customer Pengujian** | Sulistina Juliyanti | `sulistina.customer@gmail.com` | `CustomerSulistina2026!` | `http://localhost/toko/login.php` |
| **Owner (Pemilik Toko)** | Owner Sulis | `owner.sulis@gmail.com` | `OwnerSulis2026!` | `http://localhost/toko/admin/login.php` |
| **Admin Toko (CS / Operator)** | Admin CS nnasa_ca | `admin.nnasaca@gmail.com` | `AdminNnasa2026!` | `http://localhost/toko/admin/login.php` |
| **Customer (Pembeli Utama)** | Customer nnasa_ca | `customer.nnasaca@gmail.com` | `CustomerNnasa2026!` | `http://localhost/toko/login.php` |
| **Customer Demo (Anisa)** | Anisa Rahmawati | `anisa@gmail.com` | `customer123` | `http://localhost/toko/login.php` |
| **Customer Demo (Clara)** | Clara Maharani | `clara@gmail.com` | `customer123` | `http://localhost/toko/login.php` |
| **Default Super Admin** | Admin nnasa_ca | `admin@nnasaca.com` | `admin123` | `http://localhost/toko/admin/login.php` |

*Catatan: Seluruh akun di atas telah aktif di database `nnasa_ca_db` dan kata sandi dienkripsi menggunakan algoritma BCrypt standar PHP.*

---

## 🛍️ 7. Fitur Utama & Pengalaman Belanja (Customer Flow)

1. **Beranda Interaktif**:
   - Carousel banner promosi aksesoris musiman.
   - Grid kategori berbentuk lingkaran estetis dengan ikon & gambar.
   - Section Flash Sale dengan badge diskon rupiah & hitung mundur.
   - Section Produk Terlaris (*Best Sellers*) dan Koleksi Terbaru (*New Arrivals*).
   - Testimoni pelanggan & jaminan keaslian / kepuasan belanja.
2. **Katalog & Pencarian Cerdas**:
   - Grid produk responsif dengan informasi harga diskon, rating bintang, dan jumlah terjual.
   - Pencarian real-time berdasarkan kata kunci nama & deskripsi.
   - Filter berjenjang: Kategori, Rentang Harga (Min - Max), dan Ketersediaan Stok.
   - Pengurutan (*Sorting*): Terbaru, Terlaris, Harga Terendah, dan Harga Tertinggi.
   - Paginasi halaman terstruktur dari database.
3. **Halaman Detail Produk**:
   - Galeri foto interaktif dengan thumbnail switcher.
   - Pemilih varian (warna & ukuran) yang dinamis menyesuaikan penyesuaian harga dan stok riil.
   - Validasi batas pembelian tidak melebihi stok yang tersedia.
   - Tab deskripsi lengkap, rincian bahan/material aksesoris, dan ulasan pembeli terverifikasi.
   - Tombol "Tambah ke Keranjang" (via AJAX tanpa reload) dan "Beli Sekarang".
4. **Keranjang Belanja**:
   - Checkbox pemilihan item yang akan dicheckout.
   - Tombol stepper `+` dan `-` kuantitas dengan sinkronisasi ke server.
   - Hapus item keranjang secara instan.
   - Perhitungan subtotal belanja otomatis.
5. **Checkout & Transaksi Aman**:
   - Pemilihan atau penambahan alamat pengiriman langsung dari form checkout.
   - Pemilihan kurir ekspedisi (SiCepat Express, JNE Regular, J&T Express) dengan ongkir transparan.
   - Penggunaan voucher promo (contoh kode demo: `NNASANEW` untuk diskon Rp 15.000).
   - Seluruh perhitungan subtotal, diskon, dan total akhir divalidasi dan dihitung ulang di server backend.
   - Eksekusi pembuatan pesanan dibungkus dalam **Database Transaction (`PDO::beginTransaction`)** dengan row-locking (`FOR UPDATE`) untuk menjamin stok berkurang secara atomik tanpa risiko *overselling*.
   - Pembuatan nomor pesanan unik berformat standar industri (contoh: `NNA-20261016-A1B2`).
6. **Simulasi Pembayaran (DEMO Sandbox)**:
   - Mendukung pilihan simulasi pembayaran QRIS (dengan QR code SVG dinamis), Virtual Account (BCA, Mandiri, BRI), dan Transfer Bank Manual.
   - Dilengkapi tombol simulasi pembayaran sandbox yang mengubah status menjadi *Paid* secara terkontrol di backend dan mencatat riwayat ke database.
7. **Pelacakan Pesanan & Akun**:
   - Tab riwayat pesanan (Menunggu Pembayaran, Diproses, Dikirim, Selesai, Dibatalkan).
   - Halaman detail pesanan dengan snapshot data alamat dan produk.
   - Pelacak resi pengiriman dengan progress bar 4 langkah status logistik.
   - Formulir ulasan & rating bintang untuk pesanan yang telah selesai.
   - Buku alamat lengkap (tambah, edit, hapus, jadikan alamat utama).
   - Edit profil pelanggan dan ganti password dengan verifikasi password lama.

---

## 👑 8. Fitur Panel Admin (18 Modul Lengkap)

Akses dashboard: `http://localhost/toko/admin/`

1. **Dashboard Utama**: Ringkasan metrik riil dari database (Omzet valid, total pesanan, produk aktif, total kategori, total stok, produk stok menipis), grafik tren penjualan 7 hari terakhir berbasis Chart.js, daftar produk terlaris, dan tabel pesanan terbaru.
2. **Manajemen Produk**: Tabel produk lengkap dengan filter kategori & stok, tombol status aktif/draft, serta hapus produk aman.
3. **Tambah & Edit Produk**: Form pembuatan produk komprehensif, multi upload gambar, kategori, SKU, berat, harga, diskon, stok master, dan generator baris varian dinamis.
4. **Kategori Produk**: Manajemen kategori berjenjang (*parent-child*), slug otomatis, dan upload gambar kategori.
5. **Varian Produk**: Panel pemantauan seluruh varian produk di toko dan pembaruan stok cepat per varian.
6. **Manajemen Stok**: Ringkasan stok rendah dengan badge peringatan merah, form penyesuaian/restock manual, dan pencatatan otomatis ke kartu stok (`stock_movements`).
7. **Manajemen Pesanan**: Pemantauan pesanan dengan filter tanggal & status, pencarian nomor pesanan/nama pelanggan.
8. **Proses & Rincian Pesanan**: Verifikasi bukti pesanan, update status pemrosesan (Diproses -> Dikirim -> Selesai / Batalkan), dan pengembalian stok otomatis jika pesanan dibatalkan.
9. **Pembayaran**: Audit transaksi pembayaran pelanggan, nomor referensi pembayaran, metode yang digunakan, dan tanggal lunas.
10. **Pengiriman & Resi**: Input kurir pengiriman dan nomor resi ekspedisi yang langsung terhubung ke halaman pelacakan pelanggan.
11. **Pelanggan**: Daftar pelanggan terdaftar, nomor telepon, alamat default, total belanja (*lifetime spending*), dan jumlah pesanan yang diselesaikan.
12. **Ulasan Produk**: Moderasi ulasan ulasan masuk (Approve / Pending / Hide) dengan filter rating bintang.
13. **Voucher & Promo**: CRUD kupon diskon (tipe persentase atau nominal rupiah), pengaturan minimal belanja, maksimal diskon, tanggal berlaku, dan kuota limit pemakaian.
14. **Banner Beranda**: Pengaturan banner hero slider di halaman depan beserta URL target dan status aktif.
15. **Laporan & Analitik**: Laporan penjualan berdasarkan rentang tanggal fleksibel, rekapitulasi omzet bersih, produk terlaris, dan **fitur ekspor data langsung ke file spreadsheet CSV** (`?export=csv`).
16. **Pengaturan Toko**: Pengaturan nama toko, deskripsi, alamat, kontak WhatsApp, email, dan rekening bank.
17. **Manajemen Admin & Role**: Manajemen pengguna internal toko dengan hak akses peran (`admin`, `staff`).
18. **Log Aktivitas**: Audit trail rekaman aktivitas pengguna dan sistem demi keamanan operasional.

---

## 🔒 9. Keamanan & Aturan Bisnis

- **Pencegahan SQL Injection**: 100% kueri database menggunakan **PDO Prepared Statements** dengan parameter binding.
- **Pencegahan Cross-Site Scripting (XSS)**: Seluruh output yang ditampilkan ke layar disanitasi menggunakan fungsi `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- **Proteksi CSRF**: Formulir sensitif (login, checkout, perubahan data) dilindungi token CSRF berbasis sesi `csrf_token()`.
- **Enkripsi Kata Sandi**: Menggunakan algoritma hash standar `password_hash($pass, PASSWORD_BCRYPT)` dan diverifikasi dengan `password_verify()`.
- **Keamanan Upload File**:
  - Validasi ketat terhadap ukuran file (maksimal 2MB).
  - Validasi tipe MIME asli menggunakan `mime_content_type()` (hanya mengizinkan `image/jpeg`, `image/png`, `image/webp`).
  - Hashing nama file acak unik untuk mencegah *arbitrary file overwrite*.
  - Pemblokiran eksekusi skrip PHP di folder upload via file `public/uploads/.htaccess`.
- **Integritas Transaksi & Stok**:
  - Menggunakan Database Transaction (`BEGIN TRANSACTION ... COMMIT / ROLLBACK`).
  - Kueri `SELECT ... FOR UPDATE` saat checkout untuk mencegah *race condition* dan *overselling* saat beberapa pelanggan membeli barang yang sama secara bersamaan.
  - Snapshot harga, nama produk, dan alamat pengiriman disimpan permanen dalam tabel pesanan sehingga riwayat transaksi tidak berubah meskipun data master produk atau alamat pelanggan diedit di masa depan.
- **Role-Based Access Control (RBAC)**: Folder `/admin/` diproteksi secara ketat; pengguna tanpa sesi login admin akan langsung dialihkan ke halaman login dengan pesan peringatan.

---

## ✅ 10. Hasil Pengujian Otomatis

Proyek ini telah dilengkapi dengan rangkaian pengujian integrasi otomatis di dalam folder `tests/`:

### A. Uji Alur Logika & Database (`tests/test_flows.php`)
Dijalankan via CLI: `php tests/test_flows.php`
```text
=== MEMULAI PENGUJIAN INTEGRASI NNasa_ca ===

 [PASS] 1. Koneksi Database Berhasil & Tabel Terbentuk
 [PASS] 2. Autentikasi Admin (admin@nnasaca.com / admin123)
 [PASS] 3. Autentikasi Pelanggan (anisa@gmail.com / customer123)
 [PASS] 4. Produk Aktif di Katalog Tersedia
 [PASS] 5. Kategori Aksesoris Tersedia
 [PASS] 6. Query Pencarian Produk (Kueri 'Pearl')
 [PASS] 7. Data Varian Produk (Warna & Ukuran)
 [PASS] 8. Kupon Voucher NNASANEW Aktif & Valid
 [PASS] 9. Transaksi Pembuatan Pesanan & Perubahan Status Terkontrol
 [PASS] 10. Rollback Data Uji Berhasil (Integritas Terjaga)
 [PASS] 11. Query Peringatan Stok Menipis
 [PASS] 12. Query Perhitungan Omzet & Laporan Riil

============================================
HASIL PENGUJIAN: 12 PASS, 0 FAIL
============================================
```

### B. Uji Sesi & Keamanan Admin (`tests/test_admin_auth.php`)
Dijalankan via CLI: `php tests/test_admin_auth.php`
```text
1. Obtained CSRF Token: PASS
2. Dashboard Access Status:
   - Contains Title: YES (PASS)
   - Contains Revenue Card: YES (PASS)
   - Contains Chart Canvas: YES (PASS)
   - Contains 18-Menu Sidebar: YES (PASS)
3. Unauthorized Direct Access Block:
   - HTTP Redirect Code: 302 REDIRECT (PASS)
```

### C. Uji Kecerdasan & Grounding AI Assistant (`tests/test_ai_assistant.php`)
Dijalankan via CLI: `php tests/test_ai_assistant.php`
```text
=== MEMULAI PENGUJIAN KOMPREHENSIF NNASA AI ASSISTANT ===

 [PASS] 1. Sapaan Ramah & Sesuai Identitas
 [PASS] 2. Menjelaskan Peran & Identitas Resmi
 [PASS] 3. Pencarian Aksesoris Murah dari Database
 [PASS] 4. Konteks Lanjutan: Pencarian Varian Warna Hitam (Onyx Black Clover Necklace)
 [PASS] 5. Konteks Lanjutan: Pertanyaan Harga Produk Terakhir Secara Akurat
 [PASS] 6. Cek Stok Produk Spesifik (Korean Matte Claw Clip)
 [PASS] 7. Statistik Katalog Membedakan Jenis Produk vs Unit Stok Fisik
 [PASS] 8. Deteksi Produk dengan Stok Kritis (<= 5 pcs)
 [PASS] 9. Panduan Belanja & Checkout dengan Link Halaman Resmi
 [PASS] 10. Informasi Metode Pembayaran Faktual (QRIS, VA, Bank Transfer)
 [PASS] 11. Informasi Ekspedisi Kurir & Biaya Ongkir Aktual
 [PASS] 12. Informasi Kupon Diskon Aktif (NNASANEW)
 [PASS] 13. Informasi Kontak WhatsApp Resmi
 [PASS] 14. Menangani Pertanyaan di Luar Produk Aksesoris Tanpa Halusinasi

============================================
HASIL PENGUJIAN AI ASSISTANT: 14 PASS, 0 FAIL (100% SUKSES)
============================================
```

### D. Master Pengujian AI Terintegrasi Seluruh Platform (`tests/test_platform_ai.php`)
Dijalankan via CLI: `php tests/test_platform_ai.php`
```text
=== MEMULAI MASTER PENGUJIAN AI TERINTEGRASI SELURUH PLATFORM ===

 [PASS] 1. Total Produk Tersedia (12 model)
 [PASS] 2. Jumlah Produk di Database (12 model)
 [PASS] 3. Jumlah Produk Berstatus Aktif (12 model aktif)
 [PASS] 4. Jumlah Produk Tidak Aktif / Draft (0 model nonaktif)
 [PASS] 5. Jumlah Produk Stok Habis (stock = 0) (0 model habis)
 [PASS] 6. Jumlah Produk Stok Menipis (1-5 unit) (2 model: Camellia Brooch & Rose Quartz Hairpin)
 [PASS] 7. Distribusi Produk Berdasarkan Kategori (Semua kategori aksesoris)
 [PASS] 8. Koleksi Produk Terbaru (Baru ditambahkan)
 [PASS] 9. Tampilkan Produk Berdasarkan Status Tertentu (Tabel katalog produk aktif)
 [PASS] 10. Produk Stok Paling Sedikit (Crystal Camellia Brooch Pin, 3 unit)
 [PASS] 11. Produk Stok Paling Banyak (Korean Matte Claw Clip, 100 unit)
 [PASS] 12. Cek Ketersediaan Produk Bernama Spesifik (Floral Pearl Hair Clip Set)
 [PASS] 13. Cek Kuantitas Stok Riil Produk & Varian (45 unit total, Gold: 20, Rose Gold: 25)
 [PASS] 14. Tampilkan Daftar Produk Nonaktif (0 nonaktif terkonfirmasi)
 [PASS] 15. Total Unit Stok Fisik Gudang (425 unit fisik teragregasi)
 [PASS] 16. Audit Kelengkapan Data Produk (Deskripsi, Gambar, Kategori terverifikasi)
 [PASS] 17. Multi-Turn Drill-Down: Hitung Aktif -> Tampilkan Tabel -> Filter Stok Habis
 [PASS] 18. Peta Fitur: Navigasi Lacak Pesanan (tracking.php)
 [PASS] 19. Peta Fitur: Navigasi Laporan Penjualan Admin (admin/reports.php)
 [PASS] 20. Order Lookup Berdasarkan Nomor Pesanan Nyata (NNA-20261014-0001)
 [PASS] 21. RBAC: Customer Dilarang Melihat Omzet & Keuangan Toko (Akses dibatasi)
 [PASS] 22. RBAC: Admin Diizinkan Membaca Omzet Riil Database (Rp 1.487.500)

======================================================
HASIL MASTER PENGUJIAN: 22 PASS, 0 FAIL (100% SUKSES)
======================================================
```

---

## 🤖 11. Integrasi NNasa AI Assistant (Asisten Virtual Pintar Terintegrasi Seluruh Platform)

Website nnasa_ca dilengkapi asisten virtual pintar bernama **NNasa AI Assistant** yang terintegrasi penuh di seluruh modul platform (baik etalase toko pelanggan maupun panel dashboard admin):

### Arsitektur Layanan Terpisah (Service-Oriented Architecture):
1. **`ProductIntelligenceService` (`app/Services/ProductIntelligenceService.php`)**:
   - Agregasi real-time database via PDO tanpa data dummy.
   - Perhitungan status produk (`active`, `inactive`, `out_of_stock`, `low_stock`).
   - Ekstremitas stok (produk stok terbanyak & paling sedikit).
   - Detail stok fisik per varian (warna, ukuran, SKU).
   - Audit kelengkapan data produk (gambar, deskripsi, harga).
   - Pembedaan tegas antara **Jumlah Jenis Model Produk** vs **Total Unit Stok Fisik Gudang**.

2. **`FeatureRegistry` (`app/Services/FeatureRegistry.php`)**:
   - Peta navigasi terpusat mencakup **16 Halaman Pelanggan** dan **18 Modul Panel Admin**.
   - Menghubungkan nama fitur, peran (*roles*), URL halaman, sumber data, dan panduan penggunaan.
   - Pencarian semantik cerdas berbasis kata kunci (misal: "di mana lacak pesanan", "halaman laporan penjualan admin").

3. **`OrderIntelligenceService` (`app/Services/OrderIntelligenceService.php`)**:
   - Pelacakan pesanan aman real-time berdasarkan nomor resi atau ID pesanan (`NNA-YYYYMMDD-XXXX`).
   - Ringkasan riwayat belanja akun pelanggan yang login.
   - Metrik keuangan dan omzet riil untuk admin.

4. **`AIService` (`app/Services/AIService.php`)**:
   - Orkestrator percakapan cerdas yang menghubungkan seluruh service di atas.
   - *Multi-Turn Drill-Down Memory*: Mendukung alur penajaman filter (Contoh: "Berapa produk aktif?" ➡️ "Tampilkan semuanya" ➡️ "Yang stoknya habis saja").
   - *Role-Based Access Control (RBAC)*: Melindungi data finansial dan rahasia bisnis dari pelanggan publik, namun menyajikannya secara transparan untuk akun Admin/Staff.
   - Format respons terstruktur dengan tabel interaktif (`extra.table`) dan kartu statistik produk (`extra.stats_card`).

5. **Antarmuka Chat UI Responsif (`templates/ai_chat.php`, `public/assets/js/ai-chat.js`, `public/assets/css/style.css`)**:
   - Tersedia di etalase toko dan panel admin.
   - Rendering tabel dinamis yang elegan dan responsif di mobile.
   - Kartu statistik mini untuk ringkasan cepat produk.
   - Quick Action Chips kontekstual yang berubah otomatis berdasarkan hak akses pengguna.
   - Penyimpanan riwayat percakapan sesi di `sessionStorage`.

---

## 👩‍💻 Dibuat untuk Toko Aksesoris nnasa_ca
*Aplikasi web e-commerce siap pakai, modern, modular, dan dapat dikembangkan lebih lanjut untuk integrasi Payment Gateway resmi (Midtrans/Xendit) atau API RajaOngkir di masa mendatang.*
