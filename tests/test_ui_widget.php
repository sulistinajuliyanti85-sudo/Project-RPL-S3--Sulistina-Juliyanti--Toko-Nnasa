<?php
/**
 * Test Frontend Widget & Admin Settings Integration
 */

echo "=== MEMVERIFIKASI INTEGRASI WIDGET AI PADA HALAMAN WEBSITE ===\n\n";

// 1. Check Homepage
$ch = curl_init('http://localhost/toko/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$homeHtml = curl_exec($ch);
curl_close($ch);

$hasTrigger = strpos($homeHtml, 'nnasa-ai-trigger') !== false;
$hasWindow = strpos($homeHtml, 'nnasa-ai-window') !== false;
$hasAiScript = strpos($homeHtml, 'ai-chat.js') !== false;

echo "1. Halaman Beranda (Customer):\n";
echo "   - Tombol Floating Trigger: " . ($hasTrigger ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "   - Jendela Chatbot Window: " . ($hasWindow ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "   - Skrip ai-chat.js: " . ($hasAiScript ? "YES (PASS)" : "NO (FAIL)") . "\n";

// 2. Check Product Catalog Page
$ch2 = curl_init('http://localhost/toko/products.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$prodHtml = curl_exec($ch2);
curl_close($ch2);

$hasTrigger2 = strpos($prodHtml, 'nnasa-ai-trigger') !== false;
echo "2. Halaman Katalog Produk:\n";
echo "   - Widget AI Hadir & Aktif: " . ($hasTrigger2 ? "YES (PASS)" : "NO (FAIL)") . "\n";

// 3. Check Admin Settings Integration
$cookieFile = __DIR__ . '/test_admin_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// Login to admin
$ch3 = curl_init('http://localhost/toko/admin/login.php');
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_COOKIEJAR, $cookieFile);
$loginHtml = curl_exec($ch3);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $loginHtml, $matches);
$csrf = $matches[1] ?? '';

curl_setopt($ch3, CURLOPT_POST, true);
curl_setopt($ch3, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'email' => 'admin@nnasaca.com',
    'password' => 'admin123'
]));
curl_setopt($ch3, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch3);

// Fetch settings page
curl_setopt($ch3, CURLOPT_URL, 'http://localhost/toko/admin/settings.php');
curl_setopt($ch3, CURLOPT_POST, false);
curl_setopt($ch3, CURLOPT_COOKIEFILE, $cookieFile);
$settingsHtml = curl_exec($ch3);
curl_close($ch3);
if (file_exists($cookieFile)) unlink($cookieFile);

$hasAiCard = strpos($settingsHtml, 'Konfigurasi AI Assistant (NNasa AI)') !== false;
$hasProviderSelect = strpos($settingsHtml, 'ai_provider') !== false;
$hasApiKeyInput = strpos($settingsHtml, 'ai_api_key') !== false;

echo "3. Panel Admin (Pengaturan Toko):\n";
echo "   - Card Konfigurasi AI Assistant: " . ($hasAiCard ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "   - Pilihan Mode / Provider: " . ($hasProviderSelect ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "   - Input Gemini API Key: " . ($hasApiKeyInput ? "YES (PASS)" : "NO (FAIL)") . "\n";
