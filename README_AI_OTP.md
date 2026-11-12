# DOKUMENTASI MODUL KONFIGURASI AI & AUTHENTICATION OTP — NNASA_CA

Selamat datang di dokumentasi resmi implementasi modul **Konfigurasi AI** dan **Authentication OTP** untuk platform e-commerce toko aksesoris **nnasa_ca**.

---

## 1. Arsitektur & Gambaran Umum

Sistem ini dirancang secara modular, enterprise-grade, dan aman mengikuti standar **OWASP Application Security Verification Standard (ASVS)**.

### Karakteristik Utama:
- **Zero Mock / Pure Real-Time**: Tidak ada grafik palsu atau status palsu. Semua angka, metrik, latensi, dan token dihitung dari basis data aktual (`ai_usage_logs` & `otp_delivery_logs`).
- **Enkripsi Kredensial Berlapis**: API Key disimpan di database menggunakan algoritma **AES-256-CBC** yang diautentikasi dengan **HMAC-SHA256**.
- **Masking Sensitif**: Kredensial dan kontak pengguna otomatis disamarkan di antarmuka (misal: `AQ.Ab••••••••Ofig`, `te******31@example.com`, `0812****1159`).
- **Pemisahan Adapter Provider**: Provider LLM (Google Gemini & OpenAI) dihubungkan melalui `AIProviderAdapterInterface` modular.
- **OTP Kriptografis Sekali Pakai**: Kode OTP dibuat dengan `random_int`, di-hash menggunakan HMAC-SHA256 ber-salt server-side, memiliki batas waktu kedaluwarsa 5 menit, dan otomatis ditandai `consumed_at`.

---

## 2. Struktur Database (10 Tabel Baru)

1. `ai_providers`: Daftar provider AI (Gemini, OpenAI), base URL, status aktif, dan `credential_reference` (terenkripsi AES-256).
2. `ai_models`: Model AI (Gemini Flash-Lite, Gemini Flash, GPT-4o-mini, dll.) lengkap dengan batas token dan parameter generasi.
3. `ai_prompts`: Riwayat versi system prompt (`v1.0`, `v1.1`, dll.), status (active, draft, archived) dan audit pembuat.
4. `ai_usage_logs`: Catatan metrik setiap request AI (provider, model, user, token input/output, latensi ms, estimasi biaya $, status, error code).
5. `ai_settings`: Pasangan key-value untuk parameter global, rate limit, dan kebijakan over-quota.
6. `otp_challenges`: Token tantangan verifikasi OTP (user_id, destination_hash, destination_masked, purpose, otp_hash, expires_at, attempts, resend_available_at, consumed_at).
7. `otp_delivery_logs`: Catatan log pengiriman OTP (challenge_id, provider_name, delivery_status, provider_message_id, error_code, sent_at).
8. `otp_settings`: Pengaturan durasi kedaluwarsa, panjang karakter, batas percobaan, dan mode testing.
9. `otp_providers`: Konfigurasi kanal pengiriman (Native Mail, SMTP, WhatsApp Official, SMS Gateway).
10. `otp_rate_limits`: Pembatasan frekuensi pengiriman per akun dan per alamat IP untuk mencegah brute-force.

---

## 3. Fitur Panel Admin

### A. Menu Konfigurasi Model AI (`/admin/ai-config.php`)
1. **Dashboard AI**: Metrik riwayat real-time (koneksi aktif, total permintaan, total token, rata-rata latensi ms, estimasi biaya $, error log terbaru).
2. **Konfigurasi Provider AI**: Form pengaturan Gemini & OpenAI, live ping test connection dengan umpan balik latensi ms, simpan kredensial terenkripsi.
3. **Konfigurasi Model**: Manajemen model aktif, pengalihan model default dengan satu klik, konfigurasi max output token dan timeout.
4. **System Prompt**: Editor instruksi sistem dengan audit versioning (v1.0, v1.1), pemisahan draft vs aktif produksi, fitur rollback instan.
5. **Parameter AI**: Pengaturan slider temperature (0.0 - 1.0), top-p sampling, dan timeout koneksi.
6. **Pengujian AI (Playground)**: Area interaktif untuk menguji respon chatbot secara real-time dengan tombol skenario cepat:
   - Tanya jawab produk & spesifikasi
   - Pencarian harga & diskon resmi
   - Pengecekan stok aktual database
   - Pertanyaan status pesanan & resi
   - Pertanyaan kebijakan toko & garansi
   - Pertanyaan di luar aksesoris toko
   - Uji Keamanan: Minta data pelanggan lain (RBAC defense)
   - Uji Keamanan: Minta API Key / rahasia sistem
7. **Riwayat Percakapan**: Rekap percakapan terintegrasi.
8. **Log Penggunaan AI**: Tabel audit lengkap token input/output, latensi, dan kalkulasi biaya.
9. **Batas Penggunaan & Biaya**: Pengaturan batas pesan per pengguna, kuota harian, batas panjang karakter, batas biaya bulanan ($), dan kebijakan fallback saat kuota habis.
10. **Status Layanan AI**: Indikator kesehatan engine Gemini, OpenAI, dan MySQL RAG bawaan.

### B. Menu Konfigurasi OTP (`/admin/otp-config.php`)
1. **Dashboard OTP**: Real-time counter total permintaan, pengiriman terkirim/gagal, verifikasi sukses, dan kode kedaluwarsa.
2. **Pengaturan OTP**: Sakelar global on/off, panjang digit (4-8), masa berlaku (1-30 menit), interval kirim ulang (detik), dan mode simulasi pengujian.
3. **Template Pesan**: Editor template email dan WhatsApp/SMS dengan placeholder dinamis `{{otp}}`, `{{expiry_minutes}}`, dan `{{store_name}}` beserta live preview.
4. **Provider Pengiriman**: Konfigurasi Native Mail, SMTP, WhatsApp Cloud/Fonnte, dan SMS Gateway.
5. **Riwayat & Log**: Audit trail pengiriman lengkap dengan status verifikasi dan masking alamat.
6. **Batas & Keamanan**: Tabel monitoring akun/IP yang terkunci sementara akibat pelanggaran rate limit, lengkap dengan tombol `Lepas Blokir`.

---

## 4. Dokumentasi API Backend

### Admin API Endpoints
- `POST /api/admin/ai.php?action=get_dashboard_data`: Mengambil statistik real-time AI.
- `POST /api/admin/ai.php?action=test_connection`: Menguji kredensial provider API.
- `POST /api/admin/ai.php?action=save_provider`: Menyimpan konfigurasi provider (CSRF protected).
- `POST /api/admin/ai.php?action=set_default_model`: Mengubah model default toko.
- `POST /api/admin/ai.php?action=save_prompt`: Menyimpan versi system prompt baru.
- `POST /api/admin/ai.php?action=playground_test`: Melakukan pengujian interaktif playground.
- `POST /api/admin/otp.php?action=save_settings`: Menyimpan konfigurasi OTP.
- `POST /api/admin/otp.php?action=test_delivery`: Mengirimkan OTP uji coba.
- `POST /api/admin/otp.php?action=unblock_rate_limit`: Membuka kunci subjek yang diblokir rate limit.

### Public Customer Auth Endpoints
- `POST /api/auth-otp.php?action=request`: Meminta kode OTP untuk email/nomor telepon.
  - Parameter: `destination`, `purpose` (`register`, `login`, `password_reset`, `change_contact`).
- `POST /api/auth-otp.php?action=verify`: Memvalidasi kode OTP yang dimasukkan pelanggan.
  - Parameter: `destination`, `purpose`, `code`.
- `POST /api/auth-otp.php?action=resend`: Meminta pengiriman ulang kode dengan validasi interval cooldown.

---

## 5. Hasil Pengujian Otomatis (`tests/test_ai_otp_suite.php`)

Pengujian dilakukan langsung terhadap lingkungan sistem aktif dengan hasil **19 dari 19 Test Cases Lulus (100% PASS)**:

```
[PASS] [AI] 1. Simpan konfigurasi provider — Provider ID: 1
[PASS] [AI] 2. Uji koneksi dengan kredensial valid — Latency: 200 ms
[PASS] [AI] 3. Uji koneksi dengan kredensial salah — Status: Gagal, Msg: API key not valid.
[PASS] [AI] 4. Ganti model default — Default Model: Gemini Flash-Lite Latest
[PASS] [AI] 5. Uji model yang tidak tersedia — Handled safely: Model not found error handled gracefully
[PASS] [AI] 6. Uji timeout dan error provider handling — Handled gracefully with structured output
[PASS] [AI] 7. API Key tidak terekspos (Masked) — Sample mask: AQ.Ab••••••••Ofig
[PASS] [AI] 8. Dashboard mengambil data aktual database — Total Req: 2, Total Tokens: 972
[PASS] [AI] 9. Uji hak akses chatbot data pesanan (RBAC) — Public correctly prompted to login
[PASS] [OTP] 1. Minta OTP menggunakan tujuan valid — Challenge ID: Terbuat di DB
[PASS] [OTP] 2. Verifikasi dengan OTP yang benar — Verifikasi kode OTP berhasil!
[PASS] [OTP] 3. Verifikasi dengan OTP salah — Sisa kesempatan berkurang (4 kali)
[PASS] [OTP] 4. Verifikasi setelah kode kedaluwarsa — Kode OTP telah kedaluwarsa ditolak
[PASS] [OTP] 5. Cegah penggunaan ulang OTP (Single Use) — Re-verification blocked as consumed
[PASS] [OTP] 6. Uji interval pengiriman ulang (Cooldown) — Cooldown 60s ditegakkan
[PASS] [OTP] 7. Batas percobaan & rate limiting lockout — Lockout 15 menit aktif setelah pelanggaran kuota
[PASS] [OTP] 8. Uji provider pengiriman — Pengiriman simulasi tercatat di otp_delivery_logs
[PASS] [OTP] 9. OTP asli tidak disimpan dalam plaintext/log — Hash HMAC-SHA256 (64 hex characters)
[PASS] [OTP] 10. Status email_verified_at hanya berubah setelah verifikasi server — Lulus validasi server
```

---

## 6. Status Kesiapan Layanan

| Modul / Layanan | Status | Keterangan |
| :--- | :---: | :--- |
| **Google Gemini API** | 🟢 **Aktif & Operasional** | API Key resmi terpasang terenkripsi. Teruji live dengan latensi ~176 - 200 ms. |
| **OpenAI Adapter** | 🟡 **Tersedia (Standby)** | Kode adapter siap digunakan, tinggal memasukkan API Key OpenAI pada panel admin jika ingin beralih. |
| **Database Grounding (RAG)** | 🟢 **Aktif & Operasional** | Membaca katalog fisik, stok real-time, varian, dan pesanan via MySQL PDO. |
| **System Prompt Versioning** | 🟢 **Aktif & Operasional** | Versi v1.0 aktif dengan aturan ketat anti-halusinasi dan perlindungan rahasia. |
| **OTP Engine & Security** | 🟢 **Aktif & Operasional** | Token generator kriptografis, hashing, lockout timer, dan single-use verification. |
| **Kanal OTP Native Email** | 🟢 **Aktif (Local / Server)** | Terintegrasi dengan fungsi mail server lokal / hosting. |
| **Kanal OTP WhatsApp & SMS** | ⚪ **Perlu Kredensial Provider** | Template, adapter, dan logger siap. Memerlukan token gateway pihak ketiga (misal: Fonnte / Wablas / Zenziva) untuk dispatch pesan ke nomor fisik. |
