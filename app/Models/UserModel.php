<?php
/**
 * Model: UserModel
 * Bertanggung jawab menangani semua query dan operasi database tabel `users` (CRUD).
 */

require_once dirname(__DIR__) . '/Config/database.php';

class UserModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? getDB();
    }

    /**
     * READ: Mengambil semua data pengguna dengan filter pencarian dan role
     */
    public function getAllUsers(?string $keyword = null, ?string $role = null): array {
        $sql = "SELECT id, name, email, phone, role, status, created_at, updated_at 
                FROM users WHERE 1=1";
        $params = [];

        if (!empty($keyword)) {
            $sql .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = "%{$keyword}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($role) && in_array($role, ['admin', 'staff', 'customer'])) {
            $sql .= " AND role = ?";
            $params[] = $role;
        }

        $sql .= " ORDER BY FIELD(role, 'admin', 'staff', 'customer'), id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * READ: Mengambil satu data pengguna berdasarkan ID
     */
    public function getUserById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT id, name, email, phone, role, status, created_at, updated_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }

    /**
     * VALIDASI: Mengecek apakah email sudah dipakai oleh pengguna lain
     */
    public function emailExists(string $email, ?int $excludeId = null): bool {
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $stmt->execute([$email, $excludeId]);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
        }
        return (bool)$stmt->fetch();
    }

    /**
     * CREATE: Menambahkan pengguna baru ke database
     */
    public function createUser(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password_hash, phone, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $role = in_array($data['role'], ['admin', 'staff', 'customer']) ? $data['role'] : 'customer';
        $status = in_array($data['status'], ['active', 'inactive', 'banned']) ? $data['status'] : 'active';

        $stmt->execute([
            $data['name'],
            $data['email'],
            $hash,
            $data['phone'] ?? null,
            $role,
            $status
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * UPDATE: Memperbarui data pengguna, termasuk perubahan role dan reset password (opsional)
     */
    public function updateUser(int $id, array $data): bool {
        $role = in_array($data['role'], ['admin', 'staff', 'customer']) ? $data['role'] : 'customer';
        $status = in_array($data['status'], ['active', 'inactive', 'banned']) ? $data['status'] : 'active';

        if (!empty($data['password'])) {
            // Jika ada input password baru (Reset Password)
            $stmt = $this->db->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, role = ?, status = ?, password_hash = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $hash = password_hash($data['password'], PASSWORD_BCRYPT);
            return $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'] ?? null,
                $role,
                $status,
                $hash,
                $id
            ]);
        } else {
            // Jika password tidak diubah
            $stmt = $this->db->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, role = ?, status = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'] ?? null,
                $role,
                $status,
                $id
            ]);
        }
    }

    /**
     * DELETE: Menghapus akun pengguna dari database
     */
    public function deleteUser(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Hitung total user per role untuk statistik
     */
    public function getCounts(): array {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admin,
                SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as total_staff,
                SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as total_customer
            FROM users
        ");
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'total_admin' => 0, 'total_staff' => 0, 'total_customer' => 0];
    }
}
