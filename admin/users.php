<?php
/**
 * Admin User & Role Management (MVC Architecture)
 * Store: nnasa_ca Accessories Marketplace
 *
 * Route: /admin/users.php
 * Menjalankan UserController untuk menangani logika CRUD Pengguna
 */

require_once dirname(__DIR__) . '/app/Controllers/UserController.php';

$controller = new UserController();
$controller->handleRequest();
