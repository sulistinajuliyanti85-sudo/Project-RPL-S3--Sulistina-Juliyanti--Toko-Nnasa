<?php
/**
 * Application Configuration
 * Store: nnasa_ca Accessories Marketplace
 */

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters
    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 days
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Timezone Setting for Store & OTP Expiry Consistency
date_default_timezone_set('Asia/Jakarta');

// Environment & Base URL
define('APP_NAME', 'nnasa_ca');
define('APP_VERSION', '1.0.0');
define('APP_TAGLINE', 'Toko Aksesoris Wanita Elegan & Kekinian');

// Detect Base URL dynamically for XAMPP
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Path relative to web root
define('BASE_URL', $protocol . $host . '/toko/');
define('ADMIN_URL', BASE_URL . 'admin/');

// Filesystem Paths
define('ROOT_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
define('APP_PATH', ROOT_PATH . 'app' . DIRECTORY_SEPARATOR);
define('PUBLIC_PATH', ROOT_PATH . 'public' . DIRECTORY_SEPARATOR);
define('UPLOAD_PATH', PUBLIC_PATH . 'uploads' . DIRECTORY_SEPARATOR);
define('TEMPLATE_PATH', ROOT_PATH . 'templates' . DIRECTORY_SEPARATOR);

// Safe Environment Loader (.env)
if (!function_exists('load_env')) {
    function load_env(string $path): void {
        if (!file_exists($path) || !is_readable($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (str_contains($line, '=')) {
                [$key, $val] = explode('=', $line, 2);
                $key = trim($key);
                $val = trim($val);
                if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                    (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                    $val = substr($val, 1, -1);
                }
                $_ENV[$key] = $val;
                putenv("{$key}={$val}");
            }
        }
    }
}
load_env(ROOT_PATH . '.env');

// Database Credentials (Standard XAMPP default)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'nnasa_ca_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Default Currency & Format
define('CURRENCY_SYMBOL', 'Rp');
define('LOW_STOCK_ALERT', 5);

// Allowed Uploads
define('ALLOWED_IMAGE_EXT', ['jpg', 'jpeg', 'png', 'webp', 'svg']);
define('MAX_UPLOAD_SIZE', 3 * 1024 * 1024); // 3MB

// AI Chatbot API Key (loaded safely from .env)
define('CHATBOT_API_KEY', getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? ''));
define('GEMINI_API_KEY', CHATBOT_API_KEY);

// Master App Encryption Key (AES-256)
define('APP_KEY', getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? ('base64:' . base64_encode('nnasa_ca_sec_token_2026_super_secret_key_32bytes!'))));

// Include Security Helper
require_once dirname(__DIR__) . '/Helpers/security.php';

