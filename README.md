# 🌸 nnasa_ca — Marketplace Toko Aksesoris Wanita Berbasis AI & WhatsApp Gateway

> **Tugas Besar / Ujian Akhir Semester (UAS) Rekayasa Perangkat Lunak (RPL)**  
> *Sistem Informasi E-Commerce & Marketplace Modern dengan Asisten Cerdas Terintegrasi Database & Verifikasi OTP WhatsApp*

---

## 📋 Informasi Mahasiswa & Proyek

| Atribut | Keterangan |
| :--- | :--- |
| **Nama Mahasiswa** | **Sulistina Juliyanti** |
| **NIM** | *41257161* |
| **Kelompok** | *Sumber City* |
| **Tema / Topik Kelompok** | Marketplace Toko Aksesoris Wanita Berbasis Kecerdasan Buatan (AI) & OTP Gateway |
| **Nama Aplikasi** | **nnasa_ca (Nnaco.SA)** |
| **Repositori GitHub** | [https://github.com/sulistinajuliyanti85-sudo/Project-RPL-S3--Sulistina-Juliyanti--Toko-Nnasa](https://github.com/sulistinajuliyanti85-sudo/Project-RPL-S3--Sulistina-Juliyanti--Toko-Nnasa) |
| **Teknologi Utama** | PHP 8.2 (Native MVC), MariaDB/MySQL (PDO InnoDB), Bootstrap 5.3, Google Gemini 3.5 LLM, Fonnte WhatsApp Gateway |

---

## 🎥 Tautan Dokumentasi & Video Progres

Wajib diisi dengan tautan video demo yang telah diunggah ke YouTube / Google Drive:

| No | Kategori Video | Tautan (URL) | Status | Keterangan |
| :---: | :--- | :--- | :---: | :--- |
| 1 | **Video Progres** | *[Tempelkan link YouTube / Drive Video Progres 1]* | ⏳ Siap Diisi | Presentasi rancangan PRD, SRS, pemodelan ERD database, dan wireframe antarmuka UI/UX. |


---

## 💡 Deskripsi Aplikasi & Keunggulan Inovasi AI

**nnasa_ca** adalah platform marketplace dan toko e-commerce aksesoris wanita (kalung mutiara/titanium, gelang charm, cincin aesthetic, hair clip/jedai, anting, dan gift box bundling) yang memadukan estetika desain butik premium (*dusty pink*, *warm beige*, dan *rose gold*) dengan arsitektur rekayasa perangkat lunak modern.

### 🌟 Fitur Unggulan Kecerdasan Buatan (AI Assistant):
1. **Google Gemini 3.5 Flash-Lite LLM Integration**:
   - Asisten AI cerdas (**NNasa AI Assistant**) yang melayani pelanggan 24/7 di etalase toko dan membantu pemilik toko di panel admin.
   - Dilengkapi *Multi-Key Failover Pool* untuk menjamin ketersediaan layanan (*high-availability*) jika salah satu kuota kunci API habis.
2. **Database Context Grounding (RAG Engine)**:
   - Chatbot membaca data faktual langsung dari basis data MariaDB secara *real-time* (katalog 12 produk aktif, 425 unit stok riil gudang, peringatan stok menipis, status transaksi pesanan, hingga ringkasan omzet toko).
   - Menghilangkan halusinasi (*zero hallucination*) dengan memastikan setiap klaim produk, harga diskon, dan ketersediaan stok 100% akurat sesuai database.
3. **Role-Based Access Control (RBAC) pada AI**:
   - Pelanggan publik hanya diizinkan bertanya seputar rekomendasi fashion aksesoris, tips perawatan, ketersediaan produk, kebijakan toko, dan pesanan milik akunnya sendiri.
   - Data sensitif keuangan, omzet bersih toko, dan alamat pelanggan lain diproteksi secara ketat dari publik dan hanya disajikan apabila pengguna login sebagai Admin/Staff.
4. **Natural Fashion Styling & Fallback Cerdas**:
   - AI mampu menjawab konsultasi gaya busana (*mix-and-match* perhiasan), panduan kado (*gift recommendations*), dan tips merawat aksesoris titanium/mutiara agar tahan lama.
   - Jika koneksi internet terputus, sistem otomatis beralih ke *Local Database Fallback Engine* berbasis kueri cerdas internal tanpa memunculkan error pada layar pengguna.

### 🔐 Fitur Keamanan & Gateway WhatsApp OTP:
- **Verifikasi Dua Langkah Kriptografis**: Menggunakan gateway WhatsApp Fonnte untuk mengirimkan 6 digit kode OTP sekali pakai (*single-use*) saat registrasi dan pemulihan akun.
- **Perlindungan Anti-Replay & Anti Brute-Force**: Kode OTP di-hash menggunakan `HMAC-SHA256` dengan salt acak, masa berlaku 5 menit, dan otomatis hangus setelah digunakan (`consumed_at`).
- **Transaksi Basis Data Atomik**: Proses checkout dan pengurangan stok dilindungi blok `PDO::beginTransaction()` dengan row-level lock (`SELECT ... FOR UPDATE`) untuk mencegah *overselling* saat pembeli bersamaan.

---

## 🛠️ Arsitektur & Teknologi

| Komponen | Spesifikasi / Teknologi |
| :--- | :--- |
| **Server Lokal** | Apache 2.4 (via XAMPP) |
| **Backend** | PHP 8.0+ (Diuji optimal pada PHP 8.2.12) |
| **Database** | MariaDB 10.4+ / MySQL (`nnasa_ca_db`) dengan driver **PDO** & engine **InnoDB** |
| **Frontend Framework** | Bootstrap 5.3.3 + Bootstrap Icons 1.11.3 |
| **Styling Kustom** | Vanilla CSS3 Responsif (`public/assets/css/style.css` & `admin.css`) |
| **Interaktivitas & AJAX** | Vanilla JavaScript Fetch API (`public/assets/js/app.js` & `ai-chat.js`) |
| **Visualisasi Data** | Chart.js 4.4.0 (Grafik penjualan real-time di dashboard admin) |
| **Kecerdasan Buatan (AI)** | Google Generative AI REST API (`gemini-3.5-flash-lite`, `gemini-pro`) |
| **WhatsApp Gateway** | Fonnte API / Wablas Gateway dengan token terenkripsi AES-256 |

---

## 🚀 Petunjuk Instalasi Aplikasi (Setup Instructions)

Ikuti langkah-langkah mudah berikut untuk menjalankan aplikasi di komputer lokal:

### 1. Prasyarat Sistem
- Komputer dengan sistem operasi Windows, macOS, atau Linux.
- Telah menginstal paket **XAMPP** dengan **PHP versi 8.0 atau lebih tinggi**.
- Ekstensi PHP aktif: `pdo_mysql`, `curl`, `json`, `mbstring`, `openssl`.

### 2. Kloning & Penempatan Proyek
1. Buka terminal atau Git Bash, lalu kloning repositori ini:
   ```bash
   git clone https://github.com/sulistinajuliyanti85-sudo/Project-RPL-S3--Sulistina-Juliyanti--Toko-Nnasa.git
   ```
2. Pastikan folder proyek berada di direktori `htdocs` server XAMPP Anda:
   ```text
   C:\xampp\htdocs\toko
   ```

### 3. Konfigurasi Lingkungan (`.env`)
1. Salin berkas `.env.example` menjadi `.env` di dalam folder root `toko/`:
   ```bash
   cp .env.example .env
   ```
2. Buka berkas `.env` menggunakan teks editor (VS Code, Notepad, dll.) dan sesuaikan kredensial database lokal Anda (secara default XAMPP menggunakan `root` tanpa password):
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=nnasa_ca_db
   DB_USER=root
   DB_PASS=
   
   # Konfigurasi AI & WhatsApp Gateway (Opsional/Bawaan)
   AI_MODEL=gemini-3.5-flash-lite
   WHATSAPP_PROVIDER=fonnte
   ```

### 4. Instalasi Basis Data (Pilih Salah Satu Metode)

#### Cara A: Instalasi Otomatis 1-Klik via Browser (Sangat Disarankan)
1. Buka **XAMPP Control Panel**, lalu klik **Start** pada modul **Apache** dan **MySQL**.
2. Buka browser web favorit Anda dan kunjungi URL berikut:
   ```text
   http://localhost/toko/install.php
   ```
3. Klik tombol hijau **"Pasang & Isi Data Awal Database"**.
4. Skrip installer akan otomatis:
   - Membuat basis data `nnasa_ca_db` berstandar `utf8mb4_unicode_ci`.
   - Mengimpor seluruh 21 tabel dari `database/schema.sql`.
   - Mengisi data master produk, varian, voucher diskon, banner promosi, dan akun pengguna dari `database/seed.sql`.

#### Cara B: Instalasi Manual via phpMyAdmin
1. Buka browser dan akses `http://localhost/phpmyadmin/`.
2. Buat basis data baru bernama `nnasa_ca_db`.
3. Masuk ke tab **Import**:
   - Pilih dan unggah berkas `database/schema.sql`, klik **Import / Go**.
   - Pilih dan unggah berkas `database/seed.sql`, klik **Import / Go**.

---

## 🔑 Akun Demo Pengujian

Gunakan akun resmi berikut untuk melakukan pengetesan seluruh alur sistem:

| Peran (Role) | Email / Username | Kata Sandi | Halaman Akses | Hak Akses Utama |
| :--- | :--- | :--- | :--- | :--- |
| **Admin Pengujian** | `sulistina.admin@gmail.com` | `AdminSulistina2026!` | `http://localhost/toko/admin/login.php` | Kelola 18 modul toko, pantau omzet riil, audit stok, moderasi ulasan, dan laporan CSV. |
| **Customer Pengujian** | `sulistina.customer@gmail.com` | `CustomerSulistina2026!` | `http://localhost/toko/login.php` | Jelajahi katalog, keranjang belanja, checkout transaksi, chat asisten AI, dan lacak pesanan. |
| **Customer Demo (Anisa)** | `anisa@gmail.com` | `customer123` | `http://localhost/toko/login.php` | Akun demo pelanggan dengan riwayat pesanan aktif. |
| **Super Admin Default** | `admin@nnasaca.com` | `admin123` | `http://localhost/toko/admin/login.php` | Akun pengelola sistem tingkat pertama. |

---

## 🧪 Eksekusi Pengujian Otomatis (Automated Testing)

Sistem telah dilengkapi dengan test suite mandiri di folder `tests/` yang dapat dijalankan langsung melalui Command Prompt atau PowerShell:

1. **Uji Alur Bisnis, Transaksi Atomik & Integritas Data**:
   ```bash
   php tests/test_flows.php
   ```
   *Memverifikasi 12 skenario uji (koneksi DB, autentikasi admin/customer, katalog, kupon promo, transaksi pesanan dengan rollback, dan peringatan stok).*

2. **Uji Keamanan Autentikasi & Proteksi Sesi Admin**:
   ```bash
   php tests/test_admin_auth.php
   ```
   *Memverifikasi proteksi CSRF, rendering modul dashboard, dan pencegahan akses tidak sah (HTTP 302 redirect).*

3. **Uji Kecerdasan Buatan & Grounding Chatbot AI**:
   ```bash
   php tests/test_ai_assistant.php
   ```
   *Memverifikasi 14 skenario respon faktual (identitas toko, rekomendasi harga murah, stok riil, multi-turn drill down, dan proteksi anti-halusinasi).*

4. **Master Uji AI Terintegrasi Seluruh Platform**:
   ```bash
   php tests/test_platform_ai.php
   ```
   *Memverifikasi 22 skenario integrasi menyeluruh antara AI dan database gudang fisik.*

---

## 📂 Struktur Direktori Proyek

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
│   └── logout.php             # Logout sesi admin
├── api/                       # Endpoint AJAX / REST Internal
│   ├── ai-chat.php            # Gateway API untuk komunikasi Chatbot AI
│   ├── cart.php               # Operasi keranjang (tambah, update kuantitas, hapus)
│   ├── wishlist.php           # Toggle favorit pelanggan
│   ├── coupon.php             # Validasi kode kupon saat checkout
│   └── payment-sim.php        # Simulator pembayaran DEMO terkontrol
├── app/                       # Logika Inti & Konfigurasi Backend
│   ├── Config/
│   │   ├── config.php         # Konstanta BASE_URL, status, & sesi
│   │   └── database.php       # Koneksi PDO Database Singleton
│   ├── Controllers/
│   │   └── CustomerAIChatController.php # Controller orkestrator Chatbot AI & RAG
│   ├── Helpers/
│   │   ├── auth.php           # Middleware autentikasi & otorisasi role
│   │   ├── functions.php      # Helper rupiah, sanitasi, CSRF, flash message
│   │   └── upload.php         # Validasi MIME type, ukuran, & upload gambar aman
│   └── Services/
│       ├── AIService.php      # Layanan orkestrasi model AI & fallback
│       ├── FeatureRegistry.php# Peta navigasi fitur sistem untuk bantuan AI
│       ├── OrderIntelligenceService.php # Agregasi status pesanan & keuangan
│       └── ProductIntelligenceService.php # Agregasi katalog & stok gudang
├── database/                  # Skrip Arsitektur Database
│   ├── schema.sql             # 21 Skema DDL tabel relasional InnoDB utf8mb4
│   └── seed.sql               # Data awal (katalog aksesoris, voucher, banner, user)
├── docs/                      # Dokumentasi Akademik Lengkap (SRS, SDD, Pengujian)
│   └── DOKUMEN_LENGKAP_RPL_NNACO_SA.md # Naskah lengkap siap cetak/ekspor ke PDF
├── public/                    # Aset Publik
│   ├── assets/
│   │   ├── css/               # style.css (pelanggan) & admin.css (admin)
│   │   ├── js/                # app.js (AJAX) & ai-chat.js (Chatbot UI)
│   │   └── images/            # Logo SVG & generator placeholder dinamis
│   └── uploads/               # Direktori upload gambar (.htaccess terlindungi)
├── templates/                 # Header, Footer, Navigasi Mobile & AI Chat Widget
├── tests/                     # Skrip Pengujian Otomatis
├── cart.php                   # Keranjang belanja interaktif
├── checkout.php               # Checkout dengan transaksi atomik database
├── index.php                  # Beranda (Hero banner, kategori, flash sale, produk)
├── install.php                # Web installer database 1-klik
├── login.php                  # Login pelanggan
├── order-detail.php           # Detail pesanan, riwayat status, & beri ulasan
├── orders.php                 # Riwayat pesanan pelanggan
├── product-detail.php         # Galeri, pemilih varian, stok riil, ulasan
├── products.php               # Katalog lengkap, filter kategori/harga, pencarian
├── profile.php                # Profil akun & pengaturan pengguna
└── tracking.php               # Lacak pesanan & nomor resi pengiriman
```

---

## 🔒 Keamanan & Perlindungan Sistem (Security Design)

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
  - Snapshot harga, nama produk, dan alamat pengiriman disimpan permanen dalam tabel pesanan sehingga riwayat transaksi tidak berubah meskipun data master produk diedit di masa depan.
- **Role-Based Access Control (RBAC)**: Folder `/admin/` diproteksi secara ketat; pengguna tanpa sesi login admin akan langsung dialihkan ke halaman login dengan pesan peringatan.

---

## 📄 Berkas Dokumen Lengkap (SRS, SDD, Laporan Pengujian)

Untuk memenuhi ketentuan pengumpulan **BAB V Butir 2 (Berkas Dokumen Lengkap PDF Tunggal)**, seluruh naskah akademik terstruktur telah disediakan pada:
👉 [docs/DOKUMEN_LENGKAP_RPL_NNACO_SA.md](docs/DOKUMEN_LENGKAP_RPL_NNACO_SA.md)

Dokumen tersebut memuat secara komprehensif:
1. **Bagian I: Spesifikasi Kebutuhan Perangkat Lunak (PRD / SRS)**.
2. **Bagian II: Desain Arsitektur & Pemodelan UML (SDD)** dengan Use Case, Class Diagram, Sequence Diagram, dan ERD 21 Tabel.
3. **Bagian III: Laporan Pengujian Mutu Perangkat Lunak (QA & CI-CD Testing Report)** dengan hasil uji 100% lulus.

Mahasiswa dapat membuka dokumen tersebut di Visual Studio Code, Word, atau browser dan mengekspornya menjadi berkas **PDF Tunggal** resmi untuk diunggah ke LMS perkuliahan.

---
*Dibuat dengan dedikasi untuk penyelesaian Tugas Besar & Ujian Akhir Semester Rekayasa Perangkat Lunak (RPL).*
