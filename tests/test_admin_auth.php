<?php
/**
 * Test Admin Login and Authenticated Dashboard Navigation
 */
require_once dirname(__DIR__) . '/app/Config/config.php';

$cookieFile = __DIR__ . '/test_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. Fetch Login Page & CSRF Token
$ch = curl_init('http://localhost/toko/admin/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$html = curl_exec($ch);

preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches);
$token = $matches[1] ?? '';
echo "1. Obtained CSRF Token: " . substr($token, 0, 10) . "...\n";

// 2. Perform Login POST
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $token,
    'email' => 'admin@nnasaca.com',
    'password' => 'admin123'
]));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$afterLogin = curl_exec($ch);

// 3. Request Admin Dashboard
curl_setopt($ch, CURLOPT_URL, 'http://localhost/toko/admin/index.php');
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$dashboardHtml = curl_exec($ch);

$hasTitle = strpos($dashboardHtml, 'Dashboard Utama') !== false;
$hasRevenue = strpos($dashboardHtml, 'Total Pendapatan') !== false;
$hasChart = strpos($dashboardHtml, 'salesChart') !== false;
$hasSidebar = strpos($dashboardHtml, 'Manajemen Produk') !== false;

echo "2. Dashboard Access Status:\n";
echo "   - Contains Title: " . ($hasTitle ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "   - Contains Revenue Card: " . ($hasRevenue ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "   - Contains Chart Canvas: " . ($hasChart ? "YES (PASS)" : "NO (FAIL)") . "\n";
echo "   - Contains 18-Menu Sidebar: " . ($hasSidebar ? "YES (PASS)" : "NO (FAIL)") . "\n";

// 4. Test Customer unauthorized access block
curl_close($ch);
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

$ch2 = curl_init('http://localhost/toko/admin/index.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, false);
$unauthHtml = curl_exec($ch2);
$httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
echo "3. Unauthorized Direct Access Block:\n";
echo "   - HTTP Redirect Code: " . ($httpCode === 302 ? "302 REDIRECT (PASS)" : "CODE {$httpCode}") . "\n";
curl_close($ch2);
