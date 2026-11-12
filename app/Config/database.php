<?php
/**
 * Database Connection using PDO
 * Singleton Pattern for efficient connection management
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+07:00'"
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // If database does not exist yet or connection fails
                die("<div style='font-family:sans-serif;padding:30px;background:#fff0f2;color:#9b1c2b;border:1px solid #f8b4be;border-radius:8px;max-width:600px;margin:50px auto;'>
                    <h3 style='margin-top:0;'>Koneksi Database Gagal</h3>
                    <p>Tidak dapat terhubung ke database <code>" . DB_NAME . "</code>.</p>
                    <p><small>Error: " . htmlspecialchars($e->getMessage()) . "</small></p>
                    <hr style='border:0;border-top:1px solid #f8b4be;margin:15px 0;'>
                    <p>Silakan pastikan MySQL di XAMPP Control Panel telah berjalan (START), dan jalankan skrip instalasi:</p>
                    <a href='" . BASE_URL . "install.php' style='display:inline-block;padding:8px 16px;background:#c27d86;color:#fff;text-decoration:none;border-radius:4px;'>Jalankan Instalasi Otomatis (install.php)</a>
                </div>");
            }
        }
        return self::$instance;
    }
}

/**
 * Global helper to get DB instance quickly
 */
function getDB(): PDO {
    return Database::getConnection();
}
