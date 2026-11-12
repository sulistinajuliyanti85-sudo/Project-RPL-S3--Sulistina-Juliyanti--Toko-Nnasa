<?php
/**
 * AdminAIChatController
 * 
 * Controller khusus Chatbot+ Administrator (Backoffice / Admin Facing)
 * 
 * Fitur & Tanggung Jawab:
 * 1. Intent Detection dari pesan admin ($user_message) via strpos / preg_match.
 * 2. Database Grounding (Query SQL PDO aman ke tabel user, pesanan, dan stok).
 * 3. Session Extraction (Mengambil data nama & jabatan admin yang sedang login dari $_SESSION).
 * 4. System Prompt Enforcer yang super ketat (anti-ceramah, maksimal 1-2 kalimat pendek).
 * 5. Dispatch cURL ke API AI (Gemini / OpenAI) dengan batas token ketat (max_tokens 150).
 * 
 * @package NNasa_Accessories
 * @author Senior Backend Developer
 */

namespace App\Controllers;

use PDO;
use Exception;

class AdminAIChatController
{
    private PDO $db;
    private string $aiApiKey;
    private string $aiProvider; // 'gemini' atau 'openai'
    private string $aiModel;
    private string $userTable;

    public function __construct(
        PDO $db,
        string $aiApiKey = '',
        string $aiProvider = 'gemini',
        string $aiModel = 'gemini-1.5-flash',
        string $userTable = 'users'
    ) {
        $this->db = $db;
        $this->aiApiKey = $aiApiKey;
        $this->aiProvider = strtolower($aiProvider);
        $this->aiModel = $aiModel;
        $this->userTable = preg_replace('/[^a-zA-Z0-9_]/', '', $userTable);
    }

    /**
     * Entry point utama untuk memproses chat dari Administrator
     * 
     * @param string $user_message Pesan teks mentah dari input admin
     * @param array|null $sessionData Opsional: override data $_SESSION untuk testing
     * @return array Response terstruktur siap di-json_encode ke frontend
     */
    public function handleChat(string $user_message, ?array $sessionData = null): array
    {
        // 1. Sanitasi dan normalisasi input teks pesan
        $cleanMessage = trim($user_message);
        if (empty($cleanMessage)) {
            return [
                'success' => false,
                'reply' => 'Ada yang bisa saya bantu, Admin?',
                'intent' => 'empty'
            ];
        }

        $session = $sessionData ?? ($_SESSION ?? []);
        $normalized = mb_strtolower($cleanMessage, 'UTF-8');

        $injectedContext = '';
        $detectedIntent = 'general';

        // ---------------------------------------------------------------------
        // 2. QUERY SELURUH INDIKATOR DASHBOARD DARI DATABASE (DYNAMIC GROUNDING)
        // ---------------------------------------------------------------------
        try {
            $revenue = (float)$this->db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
            $revFormatted = number_format($revenue, 0, ',', '.');
            $pendingOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
            $processingOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'processing'")->fetchColumn();
            $totalActive = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
            $totalCategories = (int)$this->db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            $totalStock = (int)$this->db->query("SELECT COALESCE(SUM(stock), 0) FROM products")->fetchColumn();
            $lowStock = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND status = 'active'")->fetchColumn();
            $completedOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('completed', 'delivered')")->fetchColumn();

            // Query 5 Pesanan Terbaru (Detail Baris Tabel Database Ringkas)
            $stmtRecent = $this->db->query("
                SELECT o.order_number, u.name as customer_name, o.total_amount, o.payment_status, o.order_status
                FROM orders o
                JOIN users u ON o.user_id = u.id
                ORDER BY o.id DESC
                LIMIT 5
            ");
            $recentOrders = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

            $recentLines = [];
            foreach ($recentOrders as $ro) {
                $payText = ($ro['payment_status'] === 'paid') ? 'Lunas' : 'Belum Dibayar';
                $stMap = [
                    'pending' => 'Menunggu Pembayaran',
                    'processing' => 'Diproses',
                    'shipped' => 'Sedang Dikirim',
                    'delivered' => 'Selesai',
                    'completed' => 'Selesai',
                    'cancelled' => 'Dibatalkan'
                ];
                $stText = $stMap[$ro['order_status']] ?? ucfirst($ro['order_status']);
                $amtText = 'Rp ' . number_format((float)$ro['total_amount'], 0, ',', '.');
                $recentLines[] = "- {$ro['order_number']}, Pelanggan: {$ro['customer_name']}, Total: {$amtText}, Pembayaran: {$payText}, Status: {$stText}.";
            }
            $recentOrdersFormatted = implode("\n", $recentLines);

            $dashboardPrompt = "Anda adalah Asisten Admin Nnaco.SA. Jawab berdasarkan data ini.\n"
                             . "DATA AGREGAT TOKO: Pendapatan: Rp {$revFormatted}, Pesanan Belum Bayar: {$pendingOrders}, Pesanan Perlu Diproses: {$processingOrders}, Produk Aktif: {$totalActive}, Total Kategori: {$totalCategories}, Total Unit Stok: {$totalStock}.\n\n"
                             . "DETAIL 10 PESANAN TERBARU (TABEL DATABASE):\n"
                             . "{$recentOrdersFormatted}\n\n"
                             . "ATURAN: Jawab secara ringkas dan akurat. Jika ditanya spesifik per pelanggan (misal: Clara Maharani, yudhistira, nizar), sebutkan nominal, status pembayaran, dan status pesanannya.";
        } catch (Exception $e) {
            $dashboardPrompt = "Anda adalah Asisten Admin Nnaco.SA. Jawab pertanyaan admin secara ringkas.";
        }

        // ---------------------------------------------------------------------
        // 2. INTENT DETECTION (Deteksi Maksud Admin via Kata Kunci)
        // ---------------------------------------------------------------------

        // INTENT 1: Pertanyaan Total / Jumlah Pengguna (User Count)
        // Mendeteksi kata 'total' (atau 'berapa' / 'jumlah') DAN 'pengguna' (atau 'user')
        $hasTotalWord = str_contains($normalized, 'total') || 
                         str_contains($normalized, 'jumlah') || 
                         str_contains($normalized, 'berapa');
        $hasUserWord  = str_contains($normalized, 'pengguna') || 
                         str_contains($normalized, 'user') || 
                         str_contains($normalized, 'member');

        if ($hasTotalWord && $hasUserWord) {
            $detectedIntent = 'total_user_count';

            // Query efisien: SELECT COUNT(*) FROM tabel_user
            try {
                $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->userTable}");
                $totalCount = (int)$stmt->fetchColumn();
                $injectedContext = "Konteks: Total pengguna terdaftar saat ini adalah {$totalCount} orang.";
            } catch (Exception $e) {
                $injectedContext = "Konteks: Data total pengguna belum berhasil diambil dari database.";
            }
        }
        // INTENT 2: Pertanyaan Identitas Pengguna ("Siapa Saya")
        elseif (
            str_contains($normalized, 'siapa saya') || 
            str_contains($normalized, 'siapa aku') ||
            str_contains($normalized, 'nama saya') ||
            str_contains($normalized, 'profil saya') ||
            preg_match('/\bsiapa\s+(saya|aku|nama\s*ku)\b/i', $normalized)
        ) {
            $detectedIntent = 'identity_inquiry';

            // Ambil nama dan role dari $_SESSION
            $userName = $session['user_name'] ?? ($session['name'] ?? 'Admin');
            $rawRole  = $session['user_role'] ?? ($session['role'] ?? 'admin');
            $userRole = ucfirst(trim($rawRole));

            $injectedContext = "Konteks: User yang bertanya adalah {$userRole} bernama {$userName}.";
        }
        // INTENT 3: Pertanyaan Stok Produk (Opsional / Fitur Admin Pendukung)
        elseif (str_contains($normalized, 'stok') || str_contains($normalized, 'stock')) {
            $detectedIntent = 'stock_inquiry';
            try {
                $stmt = $this->db->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND stock < 5");
                $lowStockCount = (int)$stmt->fetchColumn();
                $injectedContext = "Konteks: Ada {$lowStockCount} produk aktif yang stoknya menipis di bawah 5 unit.";
            } catch (Exception $e) {
                $injectedContext = "Konteks: Data stok belum dapat diakses.";
            }
        }
        // INTENT 4: Pertanyaan Penjualan / Omzet (Opsional / Fitur Admin Pendukung)
        elseif (str_contains($normalized, 'penjualan') || str_contains($normalized, 'omzet') || str_contains($normalized, 'omset')) {
            $detectedIntent = 'sales_inquiry';
            try {
                $stmt = $this->db->query("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as omzet FROM orders WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $omzetFmt = 'Rp ' . number_format((float)($row['omzet'] ?? 0), 0, ',', '.');
                $injectedContext = "Konteks: Penjualan lunas hari ini berjumlah {$row['total']} transaksi dengan total omzet {$omzetFmt}.";
            } catch (Exception $e) {
                $injectedContext = "Konteks: Data penjualan hari ini belum tersedia.";
            }
        }
        // INTENT 5: Pertanyaan Pesanan / Status Order Toko (Belum Bayar, Perlu Diproses)
        elseif (str_contains($normalized, 'pesanan') || str_contains($normalized, 'order') || str_contains($normalized, 'proses') || str_contains($normalized, 'bayar')) {
            $detectedIntent = 'orders_inquiry';
            try {
                $pendingOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
                $processingOrders = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'processing'")->fetchColumn();
                $lowStock = (int)$this->db->query("SELECT COUNT(*) FROM products WHERE stock <= 5 AND status = 'active'")->fetchColumn();
                $injectedContext = "Konteks Toko: Pesanan Belum Bayar: {$pendingOrders}, Pesanan Perlu Diproses: {$processingOrders}, Produk Stok Menipis (<= 5 pcs): {$lowStock}.";
            } catch (Exception $e) {
                $injectedContext = "Konteks: Data pesanan belum dapat diakses dari database.";
            }
        }
        // INTENT 5: General / Fallback
        else {
            $detectedIntent = 'general';
            $injectedContext = "Konteks: Tidak ada query database khusus yang cocok.";
        }

        // ---------------------------------------------------------------------
        // 3. INJEKSI DATA SESI PENGGUNA (Session Context Injection)
        // ---------------------------------------------------------------------
        $userName = $session['nama'] ?? ($session['user_name'] ?? ($session['name'] ?? null));
        $rawRole  = strtolower(trim((string)($session['role'] ?? ($session['user_role'] ?? 'admin'))));

        // 1. Logika PHP (Session Injection):
        if ($rawRole === 'admin' && !empty($userName)) {
            $userSessionContext = "Konteks Internal: Kamu sedang berbicara dengan Admin toko atas nama {$userName}.";
            $greetingInstruction = "Gunakan panggilan yang sesuai (Sapaan 'Admin' untuk admin, contoh: 'Halo Admin {$userName}').";
        } elseif ($rawRole === 'customer' && !empty($userName)) {
            $userSessionContext = "Konteks Internal: Kamu sedang berbicara dengan Customer setia kami atas nama {$userName}.";
            $greetingInstruction = "Gunakan panggilan yang sesuai (Sapaan 'Kak' untuk Customer, contoh: 'Halo Kak {$userName}').";
        } else {
            $userSessionContext = "Konteks Internal: Pengguna saat ini belum login (Pengunjung/Guest).";
            $greetingInstruction = "Gunakan sapaan netral. DILARANG berhalusinasi atau menebak nama jika Konteks Internal kosong (belum login).";
        }

        // ---------------------------------------------------------------------
        // 4. SYSTEM PROMPT PEMBARUAN (Sangat Ketat, Anti-Ceramah, To The Point)
        // ---------------------------------------------------------------------
        $system_prompt = <<<PROMPT
{$dashboardPrompt}

=== IDENTITAS PENGGUNA (SESI LOGIN) ===
{$userSessionContext}
=======================================

=== DATA KONTEKS TAMBAHAN ===
{$injectedContext}
==============================

ATURAN KETAT:
1. Jika ditanya jumlah kategori, produk aktif, total unit stok, pesanan belum bayar, pesanan perlu diproses, atau omzet, jawablah langsung berdasarkan data indikator di atas.
2. Gunakan sapaan yang sesuai. {$greetingInstruction}
3. DILARANG berhalusinasi atau mengarang angka.
4. Jawab maksimal dalam 1 hingga 2 kalimat pendek saja. Langsung to the point.
PROMPT;

        // ---------------------------------------------------------------------
        // 5. DISPATCH KE API AI (Dengan Pembatasan Token Maksimal 150)
        // ---------------------------------------------------------------------
        $aiReply = '';
        if (empty($this->aiApiKey)) {
            // Fallback cerdas tanpa API jika key belum disetel
            $aiReply = $this->generateDirectFallbackReply($detectedIntent, $injectedContext, $session);
        } else {
            $aiReply = ($this->aiProvider === 'openai')
                ? $this->sendToOpenAI($system_prompt, $cleanMessage)
                : $this->sendToGemini($system_prompt, $cleanMessage);
        }

        return [
            'success' => true,
            'intent' => $detectedIntent,
            'reply' => trim($aiReply),
            'context' => $injectedContext
        ];
    }

    /**
     * Direct Fallback Reply (Zero-token deterministic answer)
     */
    private function generateDirectFallbackReply(string $intent, string $context, array $session): string
    {
        if ($intent === 'total_user_count') {
            try {
                $stmt = $this->db->query("SELECT COUNT(*) FROM {$this->userTable}");
                $total = (int)$stmt->fetchColumn();
                return "Total pengguna terdaftar saat ini adalah {$total} orang.";
            } catch (Exception $e) {
                return "Data total pengguna belum tersedia di database.";
            }
        }

        if ($intent === 'identity_inquiry') {
            $name = $session['nama'] ?? ($session['user_name'] ?? ($session['name'] ?? null));
            $role = strtolower(trim((string)($session['role'] ?? ($session['user_role'] ?? 'admin'))));
            if (!empty($name) && $role === 'admin') {
                return "Anda adalah Admin toko atas nama {$name}.";
            } elseif (!empty($name) && $role === 'customer') {
                return "Anda adalah Customer kami atas nama {$name}.";
            } else {
                return "Anda saat ini belum login ke dalam sistem.";
            }
        }

        if ($intent === 'orders_inquiry') {
            try {
                $pending = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
                $processing = (int)$this->db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'processing'")->fetchColumn();
                return "Saat ini terdapat {$pending} pesanan belum dibayar dan {$processing} pesanan yang perlu diproses, Admin.";
            } catch (Exception $e) {
                return "Data pesanan belum dapat diakses dari database.";
            }
        }

        return "Informasi yang Anda minta telah tercatat di ringkasan sistem admin.";
    }

    /**
     * Dispatch ke Google Gemini API via cURL
     */
    private function sendToGemini(string $systemPrompt, string $userMessage): string
    {
        $model = !empty($this->aiModel) ? $this->aiModel : 'gemini-1.5-flash';
        // Periksa dan ganti ID model: jika gemini-3.8-flash ganti ke model valid gemini-1.5-flash
        if (str_contains($model, '3.8')) {
            $model = 'gemini-1.5-flash';
        }
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($this->aiApiKey);

        // Kompresi Teks: Bersihkan HTML dan rapikan enter/tab/spasi berlebih agar hemat token & responsif
        $fullText = $systemPrompt . " Pertanyaan Admin: " . $userMessage;
        $cleanText = strip_tags($fullText);
        $cleanText = preg_replace('/[\r\n\t]+/', ' ', $cleanText);
        $cleanText = preg_replace('/\s{2,}/', ' ', trim($cleanText));

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $cleanText]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2, // Rendah agar faktual dan langsung to-the-point
                'maxOutputTokens' => 150 // Dibatasi ketat agar tidak bisa berceramah
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30, // Batas waktu cURL 30 detik
            CURLOPT_TCP_KEEPALIVE  => 1,
            CURLOPT_ENCODING       => "", // Menerima semua format kompresi (gzip/deflate)
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false, // Melewati verifikasi SSL lokal
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response   = curl_exec($ch);
        $curlErrNo  = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Blok pengecekan curl_errno() dan curl_error() setelah curl_exec()
        if ($curlErrNo !== 0 || !$response) {
            return "Maaf Admin, terjadi kesalahan koneksi cURL ({$curlErrNo}): " . ($curlErrMsg ?: "Koneksi ke server Google Gemini gagal.");
        }

        if ($httpCode !== 200) {
            return "Maaf, server AI memberikan respon error (HTTP {$httpCode}).";
        }

        $json = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
            return "Maaf, format balasan dari server AI tidak valid.";
        }

        $reply = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return !empty($reply) ? trim($reply) : "Maaf, tidak ada balasan dari server AI.";
    }

    /**
     * Dispatch ke OpenAI Chat Completions API via cURL
     */
    private function sendToOpenAI(string $systemPrompt, string $userMessage): string
    {
        $url = 'https://api.openai.com/v1/chat/completions';

        $payload = [
            'model' => $this->aiModel ?: 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage]
            ],
            'temperature' => 0.2,
            'max_tokens' => 150 // Dibatasi ketat
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->aiApiKey
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 35,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response   = curl_exec($ch);
        $curlErrNo  = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrNo !== 0 || !$response) {
            if ($curlErrNo === CURLE_OPERATION_TIMEDOUT || $curlErrNo === 28) {
                return "Maaf Admin, server OpenAI mengalami batas waktu tunggu (Timeout).";
            }
            return "Maaf, koneksi ke asisten AI sedang terganggu.";
        }

        if ($httpCode !== 200) {
            return "Maaf, server OpenAI memberikan respon error (HTTP {$httpCode}).";
        }

        $json = json_decode($response, true);
        $reply = $json['choices'][0]['message']['content'] ?? '';
        return !empty($reply) ? trim($reply) : "Maaf, tidak ada balasan dari server AI.";
    }

    /**
     * Endpoint / Method: test_prompt (Playground Pengujian Model Gemini)
     * Menerima request POST prompt dan model_id dari AJAX
     */
    public function test_prompt(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        // 1. Terima data POST prompt dan model_id
        $prompt  = trim($_POST['prompt'] ?? ($_POST['message'] ?? ''));
        $modelId = trim($_POST['model_id'] ?? 'gemini-1.5-flash');

        // Periksa dan ganti ID model: jika gemini-3.8-flash ganti ke model valid gemini-1.5-flash
        if (empty($modelId) || str_contains($modelId, '3.8')) {
            $modelId = 'gemini-1.5-flash';
        }

        if (empty($prompt)) {
            echo json_encode([
                'status'  => 'error',
                'message' => 'Pesan / prompt ujian tidak boleh kosong.'
            ]);
            exit;
        }

        // 2. Ambil API Key Gemini (dari property, database, constant, atau fallback)
        $apiKey = !empty($this->aiApiKey) ? $this->aiApiKey : (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : "API_KEY_SAYA");

        // 3. Endpoint Google Gemini API: https://generativelanguage.googleapis.com/v1beta/models/{model_id}:generateContent?key={apiKey}
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelId}:generateContent?key=" . urlencode($apiKey);

        // 4. Struktur payload JSON standar Gemini API (contents -> parts -> text)
        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.3,
                'maxOutputTokens' => 800
            ]
        ];

        // 5. Inisialisasi cURL murni PHP dengan timeout 30 detik & bypass SSL XAMPP
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30, // Batas waktu cURL 30 detik
            CURLOPT_SSL_VERIFYPEER => false, // Melewati verifikasi SSL lokal
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response  = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 6. Tangkap respons cURL dan blok pengecekan curl_errno() dan curl_error()
        if ($curlErrNo !== 0 || !$response) {
            echo json_encode([
                'status'  => 'error',
                'message' => "cURL Error ({$curlErrNo}): " . ($curlError ?: 'Gagal menghubungi server Google Gemini.')
            ]);
            exit;
        }

        $resData = json_decode($response, true);

        // 7. Ekstrak teks jawaban dari array candidates[0]['content']['parts'][0]['text']
        if ($httpCode === 200 && isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
            $jawaban_ai = trim($resData['candidates'][0]['content']['parts'][0]['text']);
            echo json_encode([
                'status' => 'success',
                'data'   => $jawaban_ai
            ]);
        } else {
            $errMsg = $resData['error']['message'] ?? "Google API Error (HTTP {$httpCode}): Respons tidak valid.";
            echo json_encode([
                'status'  => 'error',
                'message' => $errMsg
            ]);
        }
        exit;
    }
}
