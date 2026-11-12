# Dokumentasi Integrasi Authentication OTP WhatsApp — nnasa_ca

Panduan teknis dan implementasi enterprise-grade untuk modul **Authentication OTP WhatsApp** pada platform marketplace e-commerce aksesoris wanita **nnasa_ca** (PHP 8+, XAMPP, MariaDB/MySQL).

---

## 1. Arsitektur & Prinsip Keamanan (Cybersecurity)

1. **Zero Secret Exposure**:
   - Token API WhatsApp tidak pernah ditulis dalam source code, JavaScript, HTML, atau database dalam teks biasa.
   - Token disimpan di `.env` yang berada di luar jangkauan publik, dan jika disimpan via Dashboard Admin, dienkripsi menggunakan algoritma `AES-256-GCM` (`app/Helpers/security.php`).
   - Server Apache/XAMPP dilindungi aturan `.htaccess` yang secara ketat memblokir akses browser langsung ke file `.env`, `.sql`, dan `.log`.
2. **Kriptografi OTP**:
   - Kode OTP berupa 6 digit angka acak yang dihasilkan secara kriptografis menggunakan `random_int(100000, 999999)`.
   - Kode OTP **tidak pernah disimpan dalam bentuk plaintext** di database. Database hanya menyimpan hash `HMAC-SHA256` yang di-salt dengan nomor telepon tujuan dan secret server.
   - Verifikasi kode menggunakan `hash_equals()` untuk mencegah timing-attack.
3. **Single-Use & Anti-Replay**:
   - Setelah kode berhasil diverifikasi, kolom `consumed_at` langsung diisi dengan timestamp saat itu. Kode yang sama tidak akan pernah dapat digunakan kembali.
4. **Rate Limiting & Anti Brute-Force**:
   - Jeda kirim ulang (cooldown): Wajib menunggu 60 detik sebelum request baru.
   - Batas percobaan verifikasi salah: Maksimal 5 kali. Jika terlampaui, challenge langsung dibekukan.
   - Batas pengiriman per nomor & per IP: Maksimal request dalam 10 menit, dengan durasi blokir sementara (lockout) 15 menit.
5. **Anti-Enumeration Protection**:
   - Pada alur pemulihan akun (lupa kata sandi), sistem mengembalikan respons seragam sehingga penyerang tidak dapat menebak apakah suatu nomor terdaftar di database.

---

## 2. Konfigurasi XAMPP & File `.env`

Di direktori root project (`c:\xampp\htdocs\toko\`), terdapat file `.env` dan `.env.example`:

```env
# =====================================================================
# KONFIGURASI GATEWAY WHATSAPP NNasa_ca
# =====================================================================
WHATSAPP_PROVIDER=fonnte
WHATSAPP_API_TOKEN=ISI_TOKEN_BARU_DI_SERVER
WHATSAPP_API_URL=https://api.fonnte.com/send
WHATSAPP_SENDER_ID=NNASA_CA
WHATSAPP_OTP_TEMPLATE="Kode verifikasi nnasa_ca kamu adalah {{otp}}. Kode ini berlaku selama 5 menit. Jangan bagikan kode kepada siapa pun. Jika kamu tidak meminta kode ini, abaikan pesan ini."
```

### Cara Menghubungkan Token Nyata Provider:
1. **Fonnte (Rekomendasi Indonesia)**:
   - Dapatkan token dari menu Device di [https://fonnte.com](https://fonnte.com).
   - Masukkan token ke `.env` pada variabel `WHATSAPP_API_TOKEN=...`, atau simpan melalui panel admin di `admin/whatsapp-otp.php`.
2. **Wablas**:
   - Gunakan `WHATSAPP_PROVIDER=wablas` dan endpoint `https://kudus.wablas.com/api/send-message`.
3. **Meta Cloud API**:
   - Gunakan `WHATSAPP_PROVIDER=metacloud` dan endpoint `https://graph.facebook.com/v19.0/{PHONE_NUMBER_ID}/messages`.
4. **Mode Simulasi Aman**:
   - Jika token belum diisi atau bernilai `ISI_TOKEN_BARU_DI_SERVER`, sistem otomatis menjalankan mode simulasi dengan mencatat delivery log berstatus `simulated` (`TOKEN_NOT_CONFIGURED`) sehingga aplikasi tidak pernah crash saat tahap testing.

---

## 3. Fitur yang Telah Terintegrasi

| Modul | Halaman File | Deskripsi Integrasi |
| :--- | :--- | :--- |
| **Admin OTP WhatsApp** | [`admin/whatsapp-otp.php`](file:///c:/xampp/htdocs/toko/admin/whatsapp-otp.php) | Dashboard 4 Tab: A. Dashboard OTP, B. Konfigurasi Provider (Uji Koneksi & Pesan Uji), C. Pengaturan OTP & Keamanan, D. Riwayat Pengiriman. |
| **Login Tanpa Sandi** | [`login.php`](file:///c:/xampp/htdocs/toko/login.php) | Tab Masuk Cepat dengan WhatsApp OTP. Pengguna memasukkan no WA -> terima 6 digit OTP -> otomatis login tanpa kata sandi. |
| **Registrasi Pelanggan** | [`register.php`](file:///c:/xampp/htdocs/toko/register.php) | Pilihan metode verifikasi: **WhatsApp OTP** atau **Email OTP**. Verifikasi OTP otomatis mencatat `phone_verified_at = NOW()`. |
| **Verifikasi Profil** | [`profile.php`](file:///c:/xampp/htdocs/toko/profile.php) | Menampilkan badge `Terverifikasi WhatsApp` dan tombol modal interaktif `Verifikasi Sekarang` untuk akun yang belum terverifikasi. |
| **Pemulihan Akun** | [`forgot-password.php`](file:///c:/xampp/htdocs/toko/forgot-password.php) & [`reset-password.php`](file:///c:/xampp/htdocs/toko/reset-password.php) | Alur lupa kata sandi dengan validasi OTP WhatsApp sebelum diizinkan membuat password baru. |

---

## 4. Rute & Endpoint API

### Public Authentication API (`api/auth-whatsapp-otp.php`)
- `POST /api/auth/whatsapp-otp/request`
  - Payload: `{ "phone": "08123456789", "purpose": "login_whatsapp|register|phone_verify|password_reset" }`
  - Normalisasi nomor otomatis ke format E.164 (`+628...`).
- `POST /api/auth/whatsapp-otp/verify`
  - Payload: `{ "phone": "08123456789", "otp_code": "123456", "purpose": "..." }`
- `POST /api/auth/whatsapp-otp/resend`
  - Payload: `{ "phone": "08123456789", "purpose": "..." }`

### Admin Management API (`api/admin/whatsapp-otp.php`)
- Dilindungi RBAC (hanya admin toko terautentikasi yang dapat mengakses, non-admin mengembalikan `HTTP 403`).
- `GET ?action=get_dashboard_data`: Statistik riil pengiriman, tingkat verifikasi, latensi, dan log terkini.
- `POST ?action=test_connection`: Menguji responsivitas gateway WhatsApp via cURL.
- `POST ?action=test_message`: Mengirimkan pesan tes langsung ke nomor admin.
- `POST ?action=save_settings`: Menyimpan konfigurasi dan mengenkripsi token rahasia.
- `GET ?action=get_logs`: Mengambil riwayat pengiriman dengan nomor tersamarkan (`+62 812-****-7890`).

---

## 5. Hasil Pengujian Otomatis (15 Skenario Uji)

Pengujian lengkap dieksekusi melalui runner [`tests/test_whatsapp_otp_suite.php`](file:///c:/xampp/htdocs/toko/tests/test_whatsapp_otp_suite.php) dengan hasil **15 / 15 Skenario Lulus (100% SUCCESS)**:

1. **[PASS] Skenario 1**: Konfigurasi tanpa token ditangani aman (`simulated` delivery log).
2. **[PASS] Skenario 2**: Token tidak valid ditolak oleh gateway dengan error resmi.
3. **[PASS] Skenario 3**: Endpoint provider salah ditangani secara anggun (`CONN_FAILED`).
4. **[PASS] Skenario 4**: Pengiriman OTP ke nomor valid berhasil dengan normalisasi E.164 (`628...`).
5. **[PASS] Skenario 5**: Nomor dengan format tidak valid ditolak ketat sebelum challenge dibuat.
6. **[PASS] Skenario 6**: Verifikasi kode OTP benar berhasil & status challenge ditandai `consumed_at`.
7. **[PASS] Skenario 7**: Kode OTP salah ditolak & counter percobaan bertambah.
8. **[PASS] Skenario 8**: Kode OTP kedaluwarsa ditolak secara tegas.
9. **[PASS] Skenario 9**: Proteksi Replay Attack: Kode yang sudah digunakan tidak dapat dipakai kembali.
10. **[PASS] Skenario 10**: Cooldown pengiriman ulang 60 detik ditegakkan.
11. **[PASS] Skenario 11**: Challenge terkunci otomatis setelah batas percobaan salah terlampaui.
12. **[PASS] Skenario 12**: Penanganan cURL timeout pada gateway WhatsApp berjalan tanpa hang.
13. **[PASS] Skenario 13**: RBAC: Pengguna tanpa hak akses admin ditolak dengan `HTTP 403`.
14. **[PASS] Skenario 14**: Perlindungan Rahasia: Token API tidak bocor di browser atau database logs.
15. **[PASS] Skenario 15**: Akun terverifikasi (`phone_verified_at`) HANYA setelah kode OTP terbukti benar.
