<?php
/**
 * Helper Functions
 * Store: nnasa_ca Accessories Marketplace
 */

require_once dirname(__DIR__) . '/Config/config.php';
require_once dirname(__DIR__) . '/Config/database.php';

/**
 * Format number to Indonesian Rupiah
 */
function format_rupiah($amount): string {
    return 'Rp ' . number_format((float)$amount, 0, ',', '.');
}

/**
 * Sanitize string input
 */
function sanitize($input): string {
    if (is_array($input)) {
        return '';
    }
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or get CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF input field HTML
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Verify CSRF token
 */
function csrf_verify(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message setter
 */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Flash message getter & renderer
 */
function display_flash(): string {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $icon = match($flash['type']) {
            'success' => '<i class="bi bi-check-circle-fill me-2"></i>',
            'danger' => '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
            'warning' => '<i class="bi bi-exclamation-circle-fill me-2"></i>',
            default => '<i class="bi bi-info-circle-fill me-2"></i>'
        };
        return '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show shadow-sm" role="alert">
            ' . $icon . htmlspecialchars($flash['message']) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>';
    }
    return '';
}

/**
 * Convert string to URL friendly slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'item-' . time() : $text;
}

/**
 * Get setting value from database
 */
function get_setting(string $key, string $default = ''): string {
    static $settingsCache = null;
    if ($settingsCache === null) {
        $db = getDB();
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $settingsCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $settingsCache[$key] ?? $default;
}

/**
 * Log activity in activity_logs
 */
function log_activity(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt->execute([$userId, $action, $entityType, $entityId, $description, $ip]);
    } catch (Exception $e) {
        // Silently continue if log fails
    }
}

/**
 * Get Product Image URL with elegant fallback placeholder
 */
function get_image_url(string $type, ?string $filename, string $title = 'nnasa_ca'): string {
    if (!empty($filename)) {
        $relativePath = 'public/uploads/' . $type . '/' . $filename;
        $fullPath = ROOT_PATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (file_exists($fullPath)) {
            return BASE_URL . $relativePath;
        }
    }

    // Dynamic placeholder with elegant dusty pink styling
    return BASE_URL . 'public/assets/images/placeholder.php?text=' . urlencode(substr($title, 0, 24)) . '&type=' . urlencode($type);
}

/**
 * Return JSON response
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * Calculate discount percentage
 */
function calculate_discount_percentage(float $original, ?float $discount): int {
    if (empty($discount) || $discount >= $original || $original <= 0) {
        return 0;
    }
    return (int)round((($original - $discount) / $original) * 100);
}

/**
 * Order status badge HTML
 */
function order_status_badge(string $status): string {
    return match($status) {
        'pending' => '<span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Menunggu Pembayaran</span>',
        'processing' => '<span class="badge bg-info text-dark"><i class="bi bi-gear-wide-connected me-1"></i>Diproses</span>',
        'shipped' => '<span class="badge bg-primary"><i class="bi bi-truck me-1"></i>Sedang Dikirim</span>',
        'completed', 'delivered' => '<span class="badge bg-success"><i class="bi bi-check2-circle me-1"></i>Selesai</span>',
        'cancelled' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Dibatalkan</span>',
        default => '<span class="badge bg-secondary">' . htmlspecialchars(ucfirst($status)) . '</span>'
    };
}

/**
 * Payment status badge HTML
 */
function payment_status_badge(string $status): string {
    return match($status) {
        'paid' => '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Lunas</span>',
        'pending' => '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="bi bi-hourglass-split me-1"></i>Belum Dibayar</span>',
        'failed' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Gagal</span>',
        'expired' => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="bi bi-clock-history me-1"></i>Kedaluwarsa</span>',
        default => '<span class="badge bg-light text-dark">' . htmlspecialchars(ucfirst($status)) . '</span>'
    };
}
