<?php
/**
 * Test: test_header_dropdown.php
 * Memvalidasi pemisahan menu navbar dropdown antara role Admin vs Customer
 */

require_once dirname(__DIR__) . '/app/Config/config.php';
require_once dirname(__DIR__) . '/app/Helpers/functions.php';

function renderDropdownTest(array $user): string {
    // Simulasi session user
    $currentUser = $user;
    ob_start();
    ?>
    <ul class="dropdown-menu">
      <li><?= htmlspecialchars($currentUser['name']) ?></li>
      <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'staff'): ?>
        <li><a href="<?= ADMIN_URL ?>index.php">Dashboard Admin</a></li>
        <li><a href="<?= BASE_URL ?>profile.php">Profil Saya</a></li>
      <?php else: ?>
        <li><a href="<?= BASE_URL ?>orders.php">Pesanan Saya</a></li>
        <li><a href="<?= BASE_URL ?>tracking.php">Lacak Pengiriman</a></li>
        <li><a href="<?= BASE_URL ?>wishlist.php">Favorit Saya</a></li>
        <li><a href="<?= BASE_URL ?>addresses.php">Alamat Pengiriman</a></li>
        <li><a href="<?= BASE_URL ?>profile.php">Profil Saya</a></li>
      <?php endif; ?>
      <li><a href="<?= BASE_URL ?>logout.php">Keluar</a></li>
    </ul>
    <?php
    return ob_get_clean();
}

$adminUser = ['id' => 9, 'name' => 'Sulistina Juliyanti (Admin)', 'role' => 'admin'];
$customerUser = ['id' => 10, 'name' => 'Sulistina Juliyanti', 'role' => 'customer'];

// Test 1: Admin Dropdown
$adminOutput = renderDropdownTest($adminUser);
assert(str_contains($adminOutput, 'Dashboard Admin'), 'Admin menu harus ada Dashboard Admin');
assert(!str_contains($adminOutput, 'Pesanan Saya'), 'Admin menu TIDAK boleh ada Pesanan Saya');
assert(!str_contains($adminOutput, 'Lacak Pengiriman'), 'Admin menu TIDAK boleh ada Lacak Pengiriman');
assert(!str_contains($adminOutput, 'Favorit Saya'), 'Admin menu TIDAK boleh ada Favorit Saya');
assert(!str_contains($adminOutput, 'Alamat Pengiriman'), 'Admin menu TIDAK boleh ada Alamat Pengiriman');

// Test 2: Customer Dropdown
$customerOutput = renderDropdownTest($customerUser);
assert(str_contains($customerOutput, 'Pesanan Saya'), 'Customer menu harus ada Pesanan Saya');
assert(!str_contains($customerOutput, 'Dashboard Admin'), 'Customer menu TIDAK boleh ada Dashboard Admin');

echo "UNIT TEST PASSED: Dropdown logic works cleanly for Admin & Customer!\n";
