# DOKUMEN REKAYASA PERANGKAT LUNAK LENGKAP
## SPESIFIKASI KEBUTUHAN (SRS), DESAIN SISTEM (SDD), DAN LAPORAN PENGUJIAN MUTU (QA REPORT)

---

### IDENTITAS MAHASISWA & PROYEK
* **Nama Mahasiswa**: Sulistina Juliyanti
* **NIM**: 41257161
* **Kelompok**: Sumber City
* **Program Studi**: Teknik Informatika 
* **Mata Kuliah**: Rekayasa Perangkat Lunak (RPL)
* **Dosen Pengampu**: *Payudistira Arie Wijaya M.kom*
* **Judul Proyek**: Pengembangan Sistem Informasi Marketplace Toko Aksesoris Wanita Berbasis Asisten Cerdas (AI Context Grounding) dan Otentikasi OTP WhatsApp (*nnasa_ca*)
* **Tautan Repositori GitHub**: [https://github.com/sulistinajuliyanti85-sudo/Project-RPL-S3--Sulistina-Juliyanti--Toko-Nnasa](https://github.com/sulistinajuliyanti85-sudo/Project-RPL-S3--Sulistina-Juliyanti--Toko-Nnasa)

---

# DAFTAR ISI

1. **BAGIAN I: SPESIFIKASI KEBUTUHAN PERANGKAT LUNAK (PRD / SRS)**
   - 1.1 Latar Belakang & Identifikasi Masalah
   - 1.2 Tujuan & Manfaat Perangkat Lunak
   - 1.3 Ruang Lingkup Sistem (System Scope)
   - 1.4 Karakteristik Pengguna & Hak Akses (User Personas & RBAC)
   - 1.5 Kebutuhan Fungsional Sistem (Functional Requirements)
   - 1.6 Kebutuhan Non-Fungsional (Non-Functional Requirements)
   - 1.7 Arsitektur Inovasi AI & WhatsApp Gateway

2. **BAGIAN II: DOKUMEN DESAIN PERANGKAT LUNAK (SOFTWARE DESIGN DOCUMENT - SDD)**
   - 2.1 Pola Arsitektur Sistem (MVC & Service-Oriented AI)
   - 2.2 Pemodelan UML (Unified Modeling Language)
     - 2.2.1 Use Case Diagram & Skenario Aktor
     - 2.2.2 Class Diagram Berorientasi Objek
     - 2.2.3 Sequence Diagram 1: Transaksi Pembelian & Checkout Atomik
     - 2.2.4 Sequence Diagram 2: Konsultasi Asisten AI Terintegrasi RAG Database
     - 2.2.5 Sequence Diagram 3: Verifikasi OTP WhatsApp Anti-Replay
     - 2.2.6 Activity Diagram Alur Bisnis Belanja
   - 2.3 Perancangan Basis Data Relasional (ERD & Data Dictionary)
   - 2.4 Perancangan Antarmuka Pengguna (UI/UX) & Desain Figma

3. **BAGIAN III: LAPORAN PENGUJIAN MUTU PERANGKAT LUNAK (QA & TESTING REPORT)**
   - 3.1 Pendekatan & Metodologi Pengujian
   - 3.2 Lingkungan Pengujian (Test Environment)
   - 3.3 Matriks Hasil Pengujian Fungsional (12 Skenario Inti)
   - 3.4 Matriks Pengujian Keamanan Siber (Security Testing)
   - 3.5 Matriks Pengujian Kecerdasan Buatan (AI Chatbot Accuracy & RBAC)
   - 3.6 Matriks Pengujian Otentikasi OTP WhatsApp
   - 3.7 Kesimpulan Hasil Uji & Kesiapan Rilis

---

# BAGIAN I: SPESIFIKASI KEBUTUHAN PERANGKAT LUNAK (PRD / SRS)

## 1.1 Latar Belakang & Identifikasi Masalah
Pertumbuhan industri fesyen dan perhiasan wanita di Indonesia menunjukkan peningkatan yang signifikan, khususnya pada kategori aksesoris estetis seperti kalung titanium anti-karat, mutiara air tawar, gelang charm, hair clip ala Korea, dan paket gift box personal. Namun, sebagian besar transaksi e-commerce aksesoris saat ini masih menghadapi beberapa kendala fundamental:
1. **Tingginya Tingkat Penipuan Akun & Nomor Bodong**: Banyak transaksi gagal akibat pembeli mendaftarkan nomor kontak fiktif yang tidak dapat dihubungi saat pengiriman kurir.
2. **Keterbatasan Layanan Pelanggan 24 Jam**: Pembeli wanita seringkali memerlukan konsultasi cepat mengenai ketersediaan stok warna, kecocokan model pakaian (*mix-and-match*), dan panduan kado pada malam hari di luar jam operasional staf admin.
3. **Halusinasi & Ketidakakuratan Chatbot Tradisional**: Sebagian besar chatbot komersial hanya berbasis aturan kata kunci kaku (*rule-based*) atau model AI publik yang tidak mengetahui stok riil gudang toko fisik, sehingga kerap memberikan informasi ketersediaan barang yang keliru.
4. **Masalah Overselling pada Transaksi Bersamaan**: Ketika beberapa pelanggan membeli produk aksesoris edisi terbatas di detik yang sama, sistem tanpa proteksi transaksi atomik berisiko menjual stok melebihi ketersediaan fisik.

Untuk mengatasi permasalahan tersebut, dikembangkanlah sistem e-commerce **nnasa_ca** yang mengintegrasikan arsitektur database transaksional atomik, otentikasi dua langkah OTP WhatsApp resmi, serta asisten virtual pintar berbasis LLM (Google Gemini 3.5 Flash-Lite) yang terhubung langsung ke basis data operasional secara *real-time* (RAG).

## 1.2 Tujuan & Manfaat Perangkat Lunak
* **Tujuan**:
  - Membangun marketplace toko online aksesoris wanita yang aman, elegan, responsif, dan mudah digunakan di perangkat desktop maupun seluler.
  - Mengimplementasikan gateway verifikasi nomor WhatsApp menggunakan One-Time Password (OTP) kriptografis untuk menjamin keaslian data akun pelanggan.
  - Mengembangkan asisten cerdas virtual (*NNasa AI Assistant*) dengan teknik *Context Grounding* pada basis data SQL lokal guna melayani pertanyaan stok, harga, promo, dan konsultasi produk tanpa halusinasi.
  - Menyediakan panel kendali operasional (Admin Dashboard) dengan 18 modul manajemen komprehensif mulai dari inventaris gudang, pesanan, omzet riil, hingga ekspor laporan CSV.

* **Manfaat**:
  - Bagi Pelanggan: Mendapatkan pengalaman belanja butik aksesoris yang nyaman, konsultasi gaya busana 24/7, dan pelacakan pesanan transparan.
  - Bagi Pemilik Toko (Admin): Efisiensi biaya operasional layanan pelanggan, pencatatan keuangan akurat, serta perlindungan stok dari *race condition*.

## 1.3 Ruang Lingkup Sistem (System Scope)
* **Sistem Menangani**:
  - Registrasi, login, dan pemulihan akun pelanggan dengan verifikasi WhatsApp OTP.
  - Katalog produk berjenjang, pencarian real-time, filter rentang harga, varian warna/ukuran, dan rating ulasan.
  - Keranjang belanja interaktif, kalkulasi diskon voucher promo, dan kalkulasi ongkos kirim ekspedisi.
  - Checkout transaksi atomik dengan penguncian stok baris (*row-locking*).
  - Simulasi pembayaran sandbox (QRIS dinamis, Virtual Account, Transfer Bank).
  - Pelacakan pesanan pelanggan dengan riwayat status resi ekspedisi.
  - Interaksi chatbot cerdas dengan pemahaman data katalog riil dan filter RBAC.
  - Panel admin 18 modul (Dashboard, Produk, Varian, Kategori, Stok Gudang, Pesanan, Pembayaran, Pengiriman, Pelanggan, Ulasan, Voucher, Banner, Laporan, Pengaturan Toko, Role Akun, Log Aktivitas, Konfigurasi AI, Konfigurasi OTP).

* **Batasan Sistem (System Limitations)**:
  - Pembayaran menggunakan gateway simulasi sandbox internal untuk keperluan pengujian dan evaluasi akademik.
  - Integrasi WhatsApp menggunakan gateway Fonnte / Wablas via protokol HTTPS REST API.

## 1.4 Karakteristik Pengguna & Hak Akses (Role-Based Access Control)
1. **Pengunjung Anonim (Guest)**: Dapat melihat beranda, katalog, ulasan, halaman tentang kami, kontak, dan berinteraksi dasar dengan AI (rekomendasi fashion umum).
2. **Pelanggan Terdaftar (Customer)**: Memiliki hak akun personal, mengelola buku alamat, memasukkan barang ke keranjang/wishlist, melakukan checkout pesanan, memberi ulasan bintang, dan berkonsultasi dengan AI mengenai pesanan miliknya sendiri.
3. **Staff / Operator Toko**: Mengelola pemrosesan pesanan, input resi ekspedisi, verifikasi bukti pembayaran, dan penyesuaian stok harian.
4. **Super Admin**: Memiliki hak akses penuh ke seluruh 18 modul panel admin, laporan omzet keuangan, pengaturan kredensial API AI dan WhatsApp, serta audit log sistem.

## 1.5 Kebutuhan Fungsional Sistem (Functional Requirements)

| Kode Kebutuhan | Deskripsi Kebutuhan Fungsional | Aktor Terkait |
| :--- | :--- | :--- |
| **FR-AUTH-01** | Sistem dapat mendaftarkan pelanggan baru dengan validasi format email, nomor telepon, dan enkripsi kata sandi BCrypt. | Customer |
| **FR-AUTH-02** | Sistem dapat menghasilkan kode OTP 6 digit acak kriptografis dan mengirimkannya ke WhatsApp pengguna via gateway API. | Customer, Sistem |
| **FR-AUTH-03** | Sistem dapat memverifikasi OTP dengan hash HMAC-SHA256 ber-salt dan menandai token kedaluwarsa setelah dipakai (*single-use*). | Customer, Sistem |
| **FR-AUTH-04** | Sistem dapat membatasi percobaan salah verifikasi OTP (maksimal 5 kali) dan memberi jeda kirim ulang minimal 60 detik. | Sistem |
| **FR-AUTH-05** | Sistem dapat mengotentikasi login admin dan customer serta memisahkan sesi login berdasarkan hak peran (*role-based*). | Admin, Customer |
| **FR-PROD-01** | Sistem dapat menampilkan katalog produk lengkap dengan pagination, pengurutan harga/terbaru, dan filter kategori. | All Users |
| **FR-PROD-02** | Sistem dapat menyajikan detail produk dengan galeri foto, spesifikasi bahan, pilihan varian warna/ukuran, dan stok aktual. | All Users |
| **FR-CART-01** | Sistem dapat menambahkan barang ke keranjang belanja melalui AJAX tanpa reload halaman. | Customer |
| **FR-CART-02** | Sistem dapat memperbarui kuantitas dan menghitung subtotal belanja secara instan. | Customer |
| **FR-TRX-01** | Sistem dapat memproses checkout dengan membungkus operasi database dalam transaksi atomik (`PDO::beginTransaction`). | Customer, Sistem |
| **FR-TRX-02** | Sistem mengunci stok produk pada level database (`SELECT ... FOR UPDATE`) saat checkout guna mencegah overselling. | Sistem |
| **FR-TRX-03** | Sistem dapat memvalidasi dan memotong nilai kupon diskon sesuai aturan minimum belanja dan kuota pemakaian. | Customer, Sistem |
| **FR-TRX-04** | Sistem dapat menghasilkan nomor pesanan unik berformat standar `NNA-YYYYMMDD-XXXX`. | Sistem |
| **FR-PAY-01** | Sistem menyediakan simulator pembayaran multi-channel (QRIS SVG dinamis, Virtual Account, Transfer Bank) untuk pengujian. | Customer, Admin |
| **FR-ORD-01** | Sistem menyajikan halaman riwayat pesanan dengan 5 status (Menunggu Pembayaran, Diproses, Dikirim, Selesai, Dibatalkan). | Customer |
| **FR-ORD-02** | Sistem menyediakan fitur pelacakan nomor resi dengan progress bar status ekspedisi logistik. | Customer |
| **FR-REV-01** | Sistem memungkinkan pembeli memberikan ulasan teks dan rating bintang 1-5 untuk produk yang telah dibeli. | Customer |
| **FR-AI-01** | Sistem menyediakan antarmuka asisten chat virtual cerdas yang responsif di sudut kanan bawah etalase dan panel admin. | All Users, Admin |
| **FR-AI-02** | Asisten AI dapat melakukan query dinamis ke database untuk mendapatkan daftar produk, varian, dan stok riil gudang secara real-time. | Sistem, AI |
| **FR-AI-03** | Asisten AI mematuhi aturan isolasi data: menolak memberikan data keuangan toko kepada customer, tetapi menyajikannya kepada admin. | Sistem, AI |
| **FR-AI-04** | Sistem memiliki mekanisme failover otomatis: beralih ke kunci cadangan atau ke local database engine saat koneksi internet terganggu. | Sistem, AI |
| **FR-ADM-01** | Sistem menyediakan dashboard admin dengan visualisasi Chart.js, statistik omzet valid, jumlah order, dan peringatan stok menipis. | Admin |
| **FR-ADM-02** | Sistem menyediakan modul CRUD produk dengan upload multi-gambar aman (validasi MIME, ukuran <= 2MB, enkripsi nama file). | Admin |
| **FR-ADM-03** | Sistem menyediakan manajemen penyesuaian stok dan mencatat riwayat ke kartu mutasi stok (`stock_movements`). | Admin |
| **FR-ADM-04** | Sistem memungkinkan ekspor laporan transaksi penjualan dalam format spreadsheet CSV secara langsung. | Admin |

## 1.6 Kebutuhan Non-Fungsional (Non-Functional Requirements)
1. **Keamanan (Security)**:
   - Perlindungan 100% terhadap SQL Injection melalui *PDO Prepared Statements*.
   - Perlindungan dari serangan Cross-Site Scripting (XSS) dengan sanitasi global `htmlspecialchars()`.
   - Proteksi formulir terhadap Cross-Site Request Forgery (CSRF) via token sesi unik.
   - Enkripsi kata sandi menggunakan fungsi hashing standar industri `BCrypt`.
   - Folder upload aset publik dilindungi aturan `.htaccess` dari eksekusi berkas skrip PHP liar.
2. **Kinerja (Performance)**:
   - Waktu respon halaman server lokal di bawah 300 ms.
   - Waktu respons Chatbot AI rata-rata 1.0 - 2.5 detik menggunakan model ringan berkecepatan tinggi `gemini-3.5-flash-lite`.
3. **Ketersediaan & Keandalan (Reliability)**:
   - Ketersediaan layanan AI didukung *Multi-Key Pool Failover* dan *Local Database RAG Engine*.
4. **Antarmuka & Pengalaman Pengguna (Usability)**:
   - Mengikuti *responsive web design* dengan tata letak optimal pada perangkat smartphone, tablet, dan desktop.
   - Skema warna elegan berbasis butik aksesoris wanita (*dusty pink*, *warm beige*, dan *rose gold*).

---

# BAGIAN II: DOKUMEN DESAIN PERANGKAT LUNAK (SOFTWARE DESIGN DOCUMENT - SDD)

## 2.1 Pola Arsitektur Sistem
Aplikasi dikembangkan menggunakan arsitektur **Model-View-Controller (MVC)** yang dimodifikasi dengan **Service-Oriented Architecture** untuk subsistem kecerdasan buatan:
- **Model**: Menangani representasi data dan kueri langsung ke MariaDB melalui driver PDO (`app/Config/database.php`).
- **View**: Bertanggung jawab atas antarmuka pengguna berbasis template modular (`templates/header.php`, `footer.php`, `ai_chat.php`).
- **Controller**: Mengorkestrasi logika bisnis, autentikasi, dan validasi input (`app/Controllers/CustomerAIChatController.php`).
- **Service Layer**: Lapisan terpisah untuk kecerdasan data:
  - `ProductIntelligenceService.php`: Agregasi stok fisik gudang dan ekstremitas katalog.
  - `OrderIntelligenceService.php`: Pelacakan pesanan dan kalkulasi omzet bisnis.
  - `FeatureRegistry.php`: Kamus semantik 16 halaman publik dan 18 modul admin.
  - `AIService.php`: Pengelola integrasi Google Gemini API dan fallback lokal.

```mermaid
graph TD
    User([PENGGUNA / BROWSER])

    View[MVC VIEW LAYER<br>(Bootstrap 5/CSS)]
    Widget[AI CHAT WIDGET<br>(ai-chat.js)]
    
    Controller[CONTROLLER & ROUTING LAYER<br>(index.php, checkout.php, api/ai-chat.php, Controller)]
    
    DBHelper[DATABASE HELPER<br>(PDO Transactions)]
    AIService[AI SERVICE LAYER<br>(Product/Order/FeatureRegistry)]
    
    DB[(MARIADB DATABASE<br>21 Tabel SQL)]
    Gemini{{GEMINI 3.5 API<br>Cloud LLM Cloud}}
    WA{{FONNTE WHATSAPP<br>OTP Gateway}}

    User -- "HTTP Request" --> View
    User -- "AJAX / JSON" --> Widget

    View --> Controller
    Widget --> Controller

    Controller -- "Business Logic" --> DBHelper
    Controller -- "AI Orchestration" --> AIService

    DBHelper --> DB
    AIService --> Gemini
    AIService --> WA
    
    classDef default fill:#f9f9f9,stroke:#333,stroke-width:2px;
    classDef db fill:#e1f5fe,stroke:#0277bd,stroke-width:2px;
    classDef api fill:#fff3e0,stroke:#e65100,stroke-width:2px;
    
    class DB db;
    class Gemini,WA api;
```

---

## 2.2 Pemodelan UML (Unified Modeling Language)

### 2.2.1 Use Case Diagram

```mermaid
flowchart LR
    subgraph Sistem nnasa_ca
        UC1([Jelajahi Katalog & Cari Produk])
        UC2([Kelola Keranjang & Wishlist])
        UC3([Checkout & Transaksi Atomik])
        UC4([Lacak Pesanan & Status Resi])
        UC5([Verifikasi OTP via WhatsApp])
        UC6([Konsultasi dengan Asisten AI])
        UC7([Kelola 18 Modul Toko & Inventaris])
        UC8([Lihat Laporan Omzet & Ekspor CSV])
        UC9([Konfigurasi AI & Gateway OTP])
    end

    Customer((Pelanggan))
    Admin((Administrator))
    AI_Bot((NNasa AI Bot))

    Customer --> UC1
    Customer --> UC2
    Customer --> UC3
    Customer --> UC4
    Customer --> UC5
    Customer --> UC6

    Admin --> UC5
    Admin --> UC6
    Admin --> UC7
    Admin --> UC8
    Admin --> UC9

    UC6 -.-> AI_Bot
```

### 2.2.2 Class Diagram Sistem

```mermaid
classDiagram
    class Database {
        -PDO instance
        +getConnection() PDO
        +beginTransaction()
        +commit()
        +rollBack()
    }

    class ProductController {
        +getAllProducts(filter)
        +getProductDetail(id)
        +searchProducts(query)
    }

    class OrderController {
        +processCheckout(cartData, shippingData)
        +trackOrder(orderNumber)
        +updateOrderStatus(orderId, status)
    }

    class CustomerAIChatController {
        -AIService aiService
        +handleChatRequest(userMessage, sessionData)
        +buildCompleteStoreDatabaseContext()
    }

    class ProductIntelligenceService {
        +getActiveProductsCount() int
        +getTotalPhysicalStock() int
        +getLowStockItems(threshold) array
        +getProductVariants(productId) array
    }

    class OrderIntelligenceService {
        +getOrderSummaryByNumber(orderNumber)
        +calculateTotalStoreRevenue() float
        +getCustomerOrderHistory(userId) array
    }

    class WhatsAppOTPService {
        +generateCryptoOTP(phone) string
        +sendOTPMessage(phone, otpCode) bool
        +verifyOTPChallenge(phone, enteredCode) bool
    }

    CustomerAIChatController --> ProductIntelligenceService
    CustomerAIChatController --> OrderIntelligenceService
    OrderController --> Database
    ProductController --> Database
```

### 2.2.3 Sequence Diagram 1: Transaksi Pembelian & Checkout Atomik

```mermaid
sequenceDiagram
    autonumber
    actor Customer as Pelanggan
    participant Web as Halaman Checkout (checkout.php)
    participant DB as MariaDB (Database)
    participant Payment as Payment Simulator

    Customer->>Web: Klik Tombol "Buat Pesanan Sekarang"
    Web->>DB: PDO::beginTransaction()
    Web->>DB: SELECT stock FROM products WHERE id = X FOR UPDATE (Row Lock)
    alt Stok Fisik Tidak Cukup
        Web->>DB: PDO::rollBack()
        Web-->>Customer: Peringatan: Stok barang telah habis!
    else Stok Tersedia
        Web->>DB: INSERT INTO orders (nomor, total, alamat, status)
        Web->>DB: INSERT INTO order_items (order_id, product_id, qty, harga)
        Web->>DB: UPDATE products SET stock = stock - qty WHERE id = X
        Web->>DB: INSERT INTO stock_movements (audit log mutasi keluar)
        Web->>DB: PDO::commit() (Data Paten Disimpan)
        Web-->>Customer: Tampilkan Nomor Pesanan NNA-20261016-XXXX
        Customer->>Payment: Lakukan Simulasi Pembayaran QRIS/VA
        Payment->>DB: UPDATE orders SET status = 'processing', payment_status = 'paid'
        Payment-->>Customer: Transaksi Berhasil! Status Pesanan Diperbarui
    end
```

### 2.2.4 Sequence Diagram 2: Konsultasi Asisten AI Terintegrasi RAG Database

```mermaid
sequenceDiagram
    autonumber
    actor User as Pengguna (Pelanggan / Admin)
    participant UI as Chat Widget (ai-chat.js)
    participant Controller as CustomerAIChatController
    participant DB as MariaDB (Katalog & Omzet)
    participant Gemini as Google Gemini 3.5 LLM

    User->>UI: Input: "Aksesoris apa yang stoknya paling sedikit?"
    UI->>Controller: POST /api/ai-chat.php {message, role}
    Controller->>DB: SELECT name, stock FROM products WHERE status = 'active' ORDER BY stock ASC LIMIT 3
    DB-->>Controller: [Crystal Camellia Brooch: 3 pcs, Rose Quartz: 5 pcs]
    Controller->>Controller: Injeksi Data Faktual ke System Prompt (RAG Context)
    alt Kuota API Gemini Tersedia
        Controller->>Gemini: Kirim Context + Kueri Pengguna
        Gemini-->>Controller: Respon Faktual: "Produk dengan stok paling sedikit adalah Crystal Camellia..."
    else Terjadi Timeout / Limit API
        Controller->>Controller: Failover ke Local Database Query Engine
        Controller-->>UI: Format Respon Berdasarkan Database Langsung
    end
    Controller-->>UI: JSON {success: true, reply: "..."}
    UI-->>User: Tampilkan Jawaban Ringkas, Ramah & Akurat
```

### 2.2.5 Sequence Diagram 3: Verifikasi OTP WhatsApp Anti-Replay

```mermaid
sequenceDiagram
    autonumber
    actor User as Pelanggan
    participant Auth as Auth Controller (register.php)
    participant Gateway as Fonnte WhatsApp API
    participant DB as MariaDB (otp_challenges)

    User->>Auth: Masukkan No. HP WhatsApp & Request OTP
    Auth->>Auth: Cek Rate Limit (Anti-Spam 60 detik)
    Auth->>Auth: Generate 6 digit angka acak (random_int)
    Auth->>DB: INSERT INTO otp_challenges (destination_hash, otp_hash, expires_at)
    Auth->>Gateway: POST https://api.fonnte.com/send (Pesan template OTP)
    Gateway-->>User: Notifikasi WhatsApp: "Kode verifikasi nnasa_ca Anda: 749102"
    User->>Auth: Masukkan Kode OTP "749102"
    Auth->>DB: SELECT otp_hash, consumed_at, expires_at WHERE destination_hash = X
    alt Kode Cocok & Belum Kedaluwarsa & consumed_at IS NULL
        Auth->>DB: UPDATE otp_challenges SET consumed_at = NOW() (Anti-Replay)
        Auth-->>User: Verifikasi Berhasil! Akun Diaktifkan
    else Kode Salah atau Sudah Pernah Digunakan
        Auth->>DB: UPDATE otp_challenges SET attempts = attempts + 1
        Auth-->>User: Kode Tidak Valid atau Telah Kedaluwarsa
    end
```

---

## 2.3 Perancangan Basis Data Relasional (ERD & Data Dictionary)

Basis data `nnasa_ca_db` terdiri dari **21 tabel relasional** berbasis mesin penyimpanan **InnoDB** dengan *charset* **utf8mb4_unicode_ci**:

### Ringkasan Tabel & Entitas:
1. `users`: Menyimpan data akun pengguna, peran (`admin`, `staff`, `customer`), password hash BCrypt, dan status.
2. `addresses`: Menyimpan buku alamat pengiriman lengkap dengan relasi ke `users`.
3. `categories`: Menyimpan hierarki kategori aksesoris (*parent-child*), slug SEO, dan gambar.
4. `products`: Katalog utama aksesoris (SKU, nama, deskripsi, harga normal, diskon, stok master, berat gram, status aktif/draft).
5. `product_images`: Menyimpan galeri foto multi-sudut untuk setiap produk.
6. `product_variants`: Menyimpan kombinasi varian warna dan ukuran beserta kuantitas stok independen.
7. `carts` & `cart_items`: Menyimpan item belanja sementara milik akun pelanggan.
8. `wishlists`: Menyimpan daftar produk favorit yang disimpan oleh pengguna.
9. `orders`: Header transaksi pesanan (nomor invoice `NNA-YYYYMMDD-XXXX`, snapshot alamat JSON, ongkir, kupon diskon, total akhir, status pembayaran).
10. `order_items`: Detail barang yang dibeli dengan snapshot harga paten saat checkout.
11. `order_status_history`: Audit trail perubahan tahapan status pesanan dari waktu ke waktu.
12. `payments`: Log transaksi pembayaran, metode (QRIS, VA, Transfer), dan nomor referensi.
13. `shipments`: Data ekspedisi kurir pengiriman (SiCepat, JNE, J&T) dan nomor resi fisik.
14. `reviews`: Ulasan teks dan rating bintang 1-5 yang terverifikasi pembeli resmi.
15. `coupons` & `coupon_usages`: Pengaturan voucher diskon dan audit penggunaan per pesanan guna mencegah manipulasi kupon berulang.
16. `banners`: Pengaturan gambar hero carousel di halaman beranda.
17. `stock_movements`: Kartu stok terkomputerisasi mencatat setiap pengurangan belanja atau restock manual.
18. `settings`: Pengaturan konfigurasi toko berpasangan *key-value*.
19. `activity_logs`: Rekaman audit aktivitas pengguna dan sistem demi keamanan informasi.
20. `ai_usage_logs`: Pencatatan metrik penggunaan AI (token, latensi, dan model).
21. `otp_challenges`: Penyimpanan token verifikasi OTP dengan hash HMAC-SHA256 berbatas waktu.

---

## 2.4 Perancangan Antarmuka Pengguna (UI/UX) & Desain Figma

Sistem dirancang mengutamakan estetika butik aksesoris wanita modern dengan panduan *Design System* berikut:
- **Warna Utama**: *Dusty Pink* (`#C27D86`, `#B0656F`) memancarkan kelembutan dan keanggunan.
- **Aksen Mewah**: *Rose Gold Gradient* (`linear-gradient(135deg, #ECC5C8, #DDA7A5)`) untuk elemen tombol dan badge spesial.
- **Warna Latar Netral**: *Warm Cream / Soft Beige* (`#FFF9F8`, `#FDF3F2`) untuk kenyamanan membaca.
- **Tipografi**: *Playfair Display* untuk judul yang mewah dan *Plus Jakarta Sans* untuk keterbacaan teks dinamis.
- **Mobile First Experience**: Dilengkapi *Bottom Navigation Bar* di smartphone (Beranda, Katalog, Chatbot AI, Keranjang, Akun).

### Tautan & Tangkapan Layar Desain Figma:
* **Tautan Figma Project**: `https://www.figma.com/file/placeholder-proyek-rpl-nnasa-ca` *(Silakan ganti dengan link figma kelompok Anda jika ada)*
* **Daftar Artboard Utama**:
  1. *Frame 01*: Beranda & Hero Slider Promosi
  2. *Frame 02*: Katalog Produk & Filter Drawer
  3. *Frame 03*: Detail Produk & Varian Selector
  4. *Frame 04*: Keranjang Belanja & Ringkasan Pesanan
  5. *Frame 05*: Halaman Checkout & Pemilihan Alamat
  6. *Frame 06*: Antarmuka Widget NNasa AI Assistant (Floating Mode & Expanded)
  7. *Frame 07*: Dashboard Admin 18 Modul & Visualisasi Grafik Penjualan

---

# BAGIAN III: LAPORAN PENGUJIAN MUTU PERANGKAT LUNAK (QA & TESTING REPORT)

## 3.1 Pendekatan & Metodologi Pengujian
Pengujian perangkat lunak dilakukan menggunakan pendekatan **Multi-Layer Quality Assurance**:
1. **Blackbox Testing**: Pengujian fungsionalitas sistem berdasarkan masukan (*input*) dan keluaran (*output*) tanpa melihat struktur internal kode.
2. **Automated Integration Testing**: Pengujian integrasi logika bisnis, koneksi database, dan transaksi atomik menggunakan skrip PHP otomatis yang dieksekusi melalui CLI.
3. **Security Vulnerability Assessment**: Pengujian kerentanan terhadap ancaman SQL Injection, XSS, CSRF, dan brute-force OTP.
4. **AI & NLP Evaluation**: Pengujian konsistensi jawaban asisten cerdas terhadap pertanyaan data faktual toko, pertanyaan gaya aksesoris, dan pertahanan batas hak akses data (*RBAC data boundary*).

## 3.2 Lingkungan Pengujian (Test Environment)
- **Sistem Operasi**: Windows 11 64-Bit
- **Web Server**: Apache 2.4.58 (XAMPP Suite)
- **Bahasa Pemrograman**: PHP 8.2.12 (CLI & Apache Module)
- **Database Engine**: MariaDB 10.4.32-MariaDB
- **Browser Pengujian**: Google Chrome v128, Mozilla Firefox v129, Microsoft Edge v128
- **Resolusi Perangkat**: Desktop (1920x1080), Tablet (768x1024), Mobile Smartphone (375x812)

---

## 3.3 Matriks Hasil Pengujian Fungsional (12 Skenario Inti)
Dijalankan melalui skrip otomatis: `php tests/test_flows.php`

| No | ID Pengujian | Skenario Pengujian | Hasil yang Diharapkan | Status Hasil |
| :-: | :--- | :--- | :--- | :-: |
| 1 | **TC-INT-01** | Pengujian koneksi PDO ke database `nnasa_ca_db` dan verifikasi integritas 21 tabel. | Koneksi PDO aktif tanpa exception, seluruh tabel terbaca. | **PASS** |
| 2 | **TC-INT-02** | Verifikasi login akun Administrator (`admin@nnasaca.com`). | Verifikasi hash BCrypt sukses, sesi admin terbentuk. | **PASS** |
| 3 | **TC-INT-03** | Verifikasi login akun Pelanggan (`anisa@gmail.com`). | Verifikasi hash BCrypt sukses, sesi pelanggan terbentuk. | **PASS** |
| 4 | **TC-INT-04** | Pengecekan produk aktif pada etalase katalog. | Terbaca 12 produk aktif dengan SKU dan harga valid. | **PASS** |
| 5 | **TC-INT-05** | Pengecekan hierarki kategori aksesoris. | Seluruh kategori (Kalung, Gelang, Anting, Hair Clip) aktif. | **PASS** |
| 6 | **TC-INT-06** | Query pencarian kata kunci produk (*Pearl*). | Mengembalikan daftar produk berbahan mutiara secara tepat. | **PASS** |
| 7 | **TC-INT-07** | Pengecekan ketersediaan varian warna dan ukuran produk. | Setiap varian memiliki penyesuaian harga dan stok riil. | **PASS** |
| 8 | **TC-INT-08** | Validasi kode voucher promo (`NNASANEW`). | Kupon aktif, potongan nominal Rp 15.000 terverifikasi. | **PASS** |
| 9 | **TC-INT-09** | Simulasi pembuatan pesanan dan transaksi checkout. | Nomor pesanan unik `NNA-YYYYMMDD-XXXX` berhasil diterbitkan. | **PASS** |
| 10 | **TC-INT-10** | Pengujian mekanisme rollback transaksi atomik. | Integritas database terjaga saat simulasi kegagalan. | **PASS** |
| 11 | **TC-INT-11** | Deteksi sistem terhadap produk dengan stok kritis (<= 5 unit). | Sistem berhasil mengidentifikasi produk yang perlu direstock. | **PASS** |
| 12 | **TC-INT-12** | Rekapitulasi omzet dan pembuatan laporan penjualan riil. | Nilai penjualan teragregasi secara matematis akurat. | **PASS** |

*Ringkasan Hasil Uji Fungsional*: **12 Passed, 0 Failed (Tingkat Keberhasilan 100%)**.

---

## 3.4 Matriks Pengujian Keamanan Siber (Security Testing)
Dijalankan melalui skrip pengujian: `php tests/test_admin_auth.php` dan audit manual.

| No | ID Pengujian | Parameter Uji | Metode & Vektor Pengujian | Hasil Pengujian | Status |
| :-: | :--- | :--- | :--- | :--- | :-: |
| 1 | **TC-SEC-01** | SQL Injection Defense | Input karakter `' OR '1'='1` pada pencarian & login. | Kueri diproses via PDO Prepared Statements, input diperlakukan sebagai string murni, serangan gagal. | **PASS** |
| 2 | **TC-SEC-02** | XSS Defense | Input `<script>alert('XSS')</script>` pada ulasan produk. | Karakter khusus dikonversi via `htmlspecialchars()` menjadi entitas HTML aman, skrip tidak tereksekusi. | **PASS** |
| 3 | **TC-SEC-03** | CSRF Protection | Pengiriman request POST checkout tanpa token CSRF valid. | Sistem menolak permintaan dan memunculkan exception validasi token. | **PASS** |
| 4 | **TC-SEC-04** | Akses Tak Sah (RBAC) | Akses URL `/admin/index.php` tanpa memiliki sesi login admin. | Sistem otomatis memblokir akses dan mengalihkan browser (HTTP 302) ke halaman login admin. | **PASS** |
| 5 | **TC-SEC-05** | Proteksi Direktori Upload | Upaya mengunggah file `backdoor.php` ke folder publik. | Validasi MIME type dan aturan `.htaccess` memblokir unggahan dan menolak eksekusi berkas PHP di folder uploads. | **PASS** |

---

## 3.5 Matriks Pengujian Kecerdasan Buatan (AI Assistant Evaluation)
Dijalankan melalui skrip komprehensif: `php tests/test_ai_assistant.php` dan `tests/test_platform_ai.php`

| No | ID Pengujian | Masukan Pengguna (User Query) | Respon Sistem AI | Kriteria Evaluasi | Status |
| :-: | :--- | :--- | :--- | :--- | :-: |
| 1 | **TC-AI-01** | "Halo, kamu siapa?" | Mengenalkan diri sebagai Asisten Resmi Toko nnasa_ca yang ramah dan siap melayani. | Kesesuaian Persona | **PASS** |
| 2 | **TC-AI-02** | "Ada aksesoris apa yang paling murah?" | Merekomendasikan produk dengan harga terendah dari database beserta harga resminya. | Faktual Data Katalog | **PASS** |
| 3 | **TC-AI-03** | "Berapa stok Korean Matte Claw Clip?" | Membaca stok riil gudang (100 unit) langsung dari database. | Zero Hallucination | **PASS** |
| 4 | **TC-AI-04** | "Produk apa yang stoknya mau habis?" | Menyebutkan Crystal Camellia Brooch (3 unit) dan Rose Quartz Hairpin (5 unit). | Peringatan Stok Rendah | **PASS** |
| 5 | **TC-AI-05** | "Bagaimana cara melakukan checkout pesanan?" | Memberikan panduan 4 langkah belanja ringkas dan menyertakan URL resmi. | Panduan Operasional | **PASS** |
| 6 | **TC-AI-06** | "Apakah ada kode voucher promo?" | Menyebutkan kupon `NNASANEW` dengan diskon Rp 15.000. | Validasi Promosi | **PASS** |
| 7 | **TC-AI-07** | "Berapa omzet dan keuntungan rahasia toko?" *(Ditanya oleh Customer)* | Menolak sopan: "Maaf, informasi keuangan toko bersifat rahasia dan hanya dapat diakses oleh Admin." | Pertahanan RBAC Data | **PASS** |
| 8 | **TC-AI-08** | "Berapa total omzet toko saat ini?" *(Ditanya oleh Admin)* | Menyajikan total omzet riil terverifikasi dari tabel pesanan. | Keterbukaan Otoritas Admin | **PASS** |
| 9 | **TC-AI-09** | "Tips merawat kalung mutiara agar tidak kusam?" | Memberikan saran perawatan praktis (jauhkan dari parfum/alkohol, simpan di kotak kain). | Natural Fashion Knowledge | **PASS** |
| 10 | **TC-AI-10** | "Siapa saya?" | Menyapa nama akun pengguna tanpa mengekspos nomor User ID teknis database. | Privasi Data Pengguna | **PASS** |

*Ringkasan Hasil Uji AI*: **100% Sesuai Spesifikasi, Lulus Uji Isolasi Data**.

---

## 3.6 Matriks Pengujian Otentikasi OTP WhatsApp

| No | ID Pengujian | Skenario Pengujian | Hasil Pengamatan | Status |
| :-: | :--- | :--- | :--- | :-: |
| 1 | **TC-OTP-01** | Pembuatan token OTP 6 digit acak via `random_int(100000, 999999)`. | Menghasilkan angka acak kriptografis dengan distribusi merata. | **PASS** |
| 2 | **TC-OTP-02** | Pengiriman pesan teks OTP via Gateway Fonnte REST API. | Pesan terkirim ke WhatsApp pengguna dalam waktu < 5 detik. | **PASS** |
| 3 | **TC-OTP-03** | Verifikasi OTP yang benar dalam rentang waktu < 5 menit. | Verifikasi berhasil, kolom `consumed_at` terisi timestamp. | **PASS** |
| 4 | **TC-OTP-04** | Upaya penggunaan kembali kode OTP yang sama (*Replay Attack*). | Ditolak otomatis oleh sistem karena status token telah *consumed*. | **PASS** |
| 5 | **TC-OTP-05** | Percobaan input OTP salah berturut-turut sebanyak 5 kali. | Challenge terkunci otomatis demi mencegah brute-force. | **PASS** |

---

## 3.7 Kesimpulan Hasil Pengujian & Kesiapan Rilis

Berdasarkan seluruh tahapan pengujian fungsionalitas, keamanan siber, kecerdasan buatan, dan gateway otentikasi yang telah dilakukan:
1. **Integritas Sistem 100% Terverifikasi**: Tidak ditemukan kegagalan (*0 bug fatal*) pada seluruh modul alur belanja pelanggan dan operasional 18 modul panel admin.
2. **Kecerdasan Buatan Terintegrasi Penuh**: Asisten virtual berhasil menghubungkan pengguna dengan data faktual database MariaDB tanpa halusinasi, serta mematuhi batasan privasi data pengguna dan kerahasiaan keuangan toko.
3. **Keamanan Transaksi Terjamin**: Penguncian baris database saat checkout terbukti efektif mengeliminasi risiko penjualan ganda (*overselling*).
4. **Kelayakan Akademik**: Sistem aplikasi **nnasa_ca** dinyatakan **LULUS UJI KELAYAKAN** dan **SIAP UNTUK DIUJI COBA & DIKUMPULKAN** sebagai pemenuhan Tugas Besar dan Ujian Akhir Semester mata kuliah Rekayasa Perangkat Lunak.

---
*Dokumen ini disusun sebagai lampiran resmi pengumpulan Tugas Rekayasa Perangkat Lunak.*
