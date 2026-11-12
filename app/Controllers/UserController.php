<?php
/**
 * Controller: UserController
 * Bertanggung jawab menangani alur bisnis, validasi request, pembatasan hak akses,
 * dan memanggil UserModel sebelum menampilkan View.
 */

require_once dirname(__DIR__) . '/Config/config.php';
require_once dirname(__DIR__) . '/Helpers/functions.php';
require_once dirname(__DIR__) . '/Helpers/auth.php';
require_once dirname(__DIR__) . '/Models/UserModel.php';

class UserController {
    private UserModel $userModel;
    private array $currentUser;

    public function __construct() {
        // Pembatasan hak akses: Hanya Administrator / Staf yang boleh mengakses
        require_admin();
        $this->currentUser = current_user();
        $this->userModel = new UserModel();
    }

    /**
     * Entrypoint utama untuk halaman Manajemen User
     */
    public function handleRequest(): void {
        // Handle POST Requests (Create & Update)
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $action = $_POST['action'] ?? '';
            if ($action === 'create') {
                $this->handleCreate();
            } elseif ($action === 'update') {
                $this->handleUpdate();
            }
        }

        // Handle GET Request (Delete)
        if (isset($_GET['action']) && $_GET['action'] === 'delete') {
            $this->handleDelete((int)($_GET['id'] ?? 0));
        }

        // READ: Ambil filter pencarian dan role
        $keyword = sanitize($_GET['q'] ?? '');
        $roleFilter = sanitize($_GET['role'] ?? '');

        $users = $this->userModel->getAllUsers($keyword, $roleFilter);
        $counts = $this->userModel->getCounts();
        $currentUser = $this->currentUser;

        // Render View
        $viewPath = dirname(__DIR__) . '/Views/admin/user_management.php';
        if (file_exists($viewPath)) {
            require_once $viewPath;
        } else {
            echo "Error: File view tidak ditemukan pada: {$viewPath}";
        }
    }

    /**
     * CREATE: Logika validasi dan penambahan pengguna baru
     */
    private function handleCreate(): void {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            set_flash('danger', 'Token keamanan CSRF tidak valid. Silakan ulangi.');
            $this->redirect();
        }

        $name = sanitize($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $phone = sanitize($_POST['phone'] ?? '');
        $role = sanitize($_POST['role'] ?? 'customer');
        $status = sanitize($_POST['status'] ?? 'active');

        // Validasi input
        if (empty($name)) {
            set_flash('danger', 'Nama lengkap pengguna wajib diisi.');
            $this->redirect();
        }

        if (!$email) {
            set_flash('danger', 'Format alamat email tidak valid.');
            $this->redirect();
        }

        if ($this->userModel->emailExists($email)) {
            set_flash('danger', "Email '{$email}' sudah terdaftar pada pengguna lain.");
            $this->redirect();
        }

        if (empty($password) || strlen($password) < 6) {
            set_flash('danger', 'Kata sandi akun minimal 6 karakter.');
            $this->redirect();
        }

        // Simpan ke database melalui Model
        $newId = $this->userModel->createUser([
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'phone'    => $phone,
            'role'     => $role,
            'status'   => $status
        ]);

        if ($newId) {
            log_activity($this->currentUser['id'], 'create_user', 'users', $newId, "Menambahkan pengguna baru: {$email} ({$role})");
            set_flash('success', "Akun '{$name}' dengan role {$role} berhasil ditambahkan!");
        } else {
            set_flash('danger', 'Gagal menambahkan akun baru ke database.');
        }

        $this->redirect();
    }

    /**
     * UPDATE: Logika validasi dan pembaruan data pengguna / reset password
     */
    private function handleUpdate(): void {
        if (!csrf_verify($_POST['csrf_token'] ?? '')) {
            set_flash('danger', 'Token keamanan CSRF tidak valid.');
            $this->redirect();
        }

        $id = (int)($_POST['id'] ?? 0);
        $user = $this->userModel->getUserById($id);

        if (!$user) {
            set_flash('danger', 'Pengguna yang ingin diedit tidak ditemukan.');
            $this->redirect();
        }

        $name = sanitize($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $phone = sanitize($_POST['phone'] ?? '');
        $role = sanitize($_POST['role'] ?? $user['role']);
        $status = sanitize($_POST['status'] ?? $user['status']);

        if (empty($name) || !$email) {
            set_flash('danger', 'Nama lengkap dan email yang valid wajib diisi.');
            $this->redirect();
        }

        // Cek duplikasi email ke ID user lain
        if ($this->userModel->emailExists($email, $id)) {
            set_flash('danger', "Email '{$email}' sudah digunakan oleh akun lain.");
            $this->redirect();
        }

        // Validasi password jika diisi untuk reset
        if (!empty($password) && strlen($password) < 6) {
            set_flash('danger', 'Kata sandi baru minimal 6 karakter.');
            $this->redirect();
        }

        $updated = $this->userModel->updateUser($id, [
            'name'     => $name,
            'email'    => $email,
            'password' => $password,
            'phone'    => $phone,
            'role'     => $role,
            'status'   => $status
        ]);

        if ($updated) {
            log_activity($this->currentUser['id'], 'update_user', 'users', $id, "Memperbarui data pengguna: {$email}");
            set_flash('success', "Data pengguna '{$name}' berhasil diperbarui!");
        } else {
            set_flash('danger', 'Gagal memperbarui data pengguna.');
        }

        $this->redirect();
    }

    /**
     * DELETE: Logika penghapusan pengguna dengan proteksi anti hapus akun sendiri
     */
    private function handleDelete(int $id): void {
        // Proteksi keamanan: Admin tidak boleh menghapus akunnya sendiri yang sedang aktif
        if ($id === (int)$this->currentUser['id']) {
            set_flash('danger', 'Tindakan ditolak! Anda tidak dapat menghapus akun Anda sendiri yang sedang digunakan login.');
            $this->redirect();
        }

        $user = $this->userModel->getUserById($id);
        if (!$user) {
            set_flash('danger', 'Pengguna tidak ditemukan.');
            $this->redirect();
        }

        $deleted = $this->userModel->deleteUser($id);
        if ($deleted) {
            log_activity($this->currentUser['id'], 'delete_user', 'users', $id, "Menghapus akun: {$user['email']}");
            set_flash('info', "Akun '{$user['name']}' ({$user['email']}) berhasil dihapus dari sistem.");
        } else {
            set_flash('danger', 'Gagal menghapus pengguna dari database.');
        }

        $this->redirect();
    }

    /**
     * Helper redirect kembali ke halaman users
     */
    private function redirect(): void {
        header('Location: ' . ADMIN_URL . 'users.php');
        exit;
    }
}
