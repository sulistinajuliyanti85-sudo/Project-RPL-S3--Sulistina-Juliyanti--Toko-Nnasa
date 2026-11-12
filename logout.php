<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/app/Config/config.php';
require_once __DIR__ . '/app/Helpers/functions.php';
require_once __DIR__ . '/app/Helpers/auth.php';

logout_user();
set_flash('info', 'Anda telah berhasil keluar dari akun.');
header('Location: ' . BASE_URL . 'index.php');
exit;
