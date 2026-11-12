<?php
/**
 * Authentication & Authorization Helper
 * Store: nnasa_ca Accessories Marketplace
 */

require_once __DIR__ . '/functions.php';

/**
 * Check if a user is logged in
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user details
 */
function current_user(): ?array {
    if (!is_logged_in()) {
        return null;
    }

    static $currentUser = null;
    if ($currentUser === null) {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $currentUser = $stmt->fetch() ?: null;
    }
    return $currentUser;
}

/**
 * Check if logged in user is admin
 */
function is_admin(): bool {
    $user = current_user();
    return $user && ($user['role'] === 'admin');
}

/**
 * Check if logged in user is staff or admin
 */
function is_staff(): bool {
    $user = current_user();
    return $user && in_array($user['role'], ['admin', 'staff']);
}

/**
 * Require customer login, redirect if not authenticated
 */
function require_login(string $redirect = 'login.php'): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        set_flash('warning', 'Silakan login terlebih dahulu untuk melanjutkan belanja.');
        header('Location: ' . BASE_URL . $redirect);
        exit;
    }
}

/**
 * Require admin or staff role
 */
function require_admin(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_admin_login'] = $_SERVER['REQUEST_URI'];
        set_flash('warning', 'Akses dibatasi. Silakan login sebagai administrator.');
        header('Location: ' . ADMIN_URL . 'login.php');
        exit;
    }

    $user = current_user();
    if (!$user || !in_array($user['role'], ['admin', 'staff'])) {
        set_flash('danger', 'Anda tidak memiliki hak akses ke halaman administrator.');
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

/**
 * Log in a user securely
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    log_activity($user['id'], 'login', 'users', $user['id'], 'User logged in successfully');
}

/**
 * Log out user
 */
function logout_user(): void {
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        log_activity($userId, 'logout', 'users', $userId, 'User logged out');
    }
    
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Get count of items in user's cart
 */
function get_user_cart_count(?int $userId = null): int {
    if (!$userId && is_logged_in()) {
        $userId = $_SESSION['user_id'];
    }
    if (!$userId) {
        return 0;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COALESCE(SUM(ci.quantity), 0) FROM cart_items ci JOIN carts c ON ci.cart_id = c.id WHERE c.user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get count of items in user's wishlist
 */
function get_user_wishlist_count(?int $userId = null): int {
    if (!$userId && is_logged_in()) {
        $userId = $_SESSION['user_id'];
    }
    if (!$userId) {
        return 0;
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}
