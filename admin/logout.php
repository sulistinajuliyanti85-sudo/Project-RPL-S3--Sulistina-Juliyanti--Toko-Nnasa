<?php
/**
 * Admin Logout Handler
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';
require_once dirname(__DIR__) . '/app/Helpers/auth.php';

logout_user();
set_flash('info', 'Anda telah keluar dari panel administrator.');
header('Location: ' . ADMIN_URL . 'login.php');
exit;
