<?php
/**
 * Admin Activity Logs (Log Aktivitas)
 * Store: nnasa_ca Accessories Marketplace
 */

$adminPageTitle = 'Log Aktivitas Sistem';
require_once dirname(__DIR__) . '/templates/admin_header.php';

$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$totalLogs = (int)$db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$totalPages = ceil($totalLogs / $limit);

$stmt = $db->prepare("
    SELECT al.*, u.name as user_name, u.email as user_email, u.role as user_role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.id DESC
    LIMIT {$limit} OFFSET {$offset}
");
$stmt->execute();
$logs = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
  <div>
    <h4 class="fw-bold text-dark mb-1">Log Aktivitas Sistem (<?= $totalLogs ?>)</h4>
    <small class="text-muted">Catatan audit riwayat login, perubahan pesanan, mutasi produk, dan aksi admin</small>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-body p-0">
    <div class="table-responsive">
      <table class="table table-admin">
        <thead>
          <tr>
            <th>Waktu</th>
            <th>Pengguna</th>
            <th>Aksi</th>
            <th>Entitas</th>
            <th>Deskripsi Aktivitas</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">Belum ada riwayat aktivitas.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><small class="text-muted"><?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?></small></td>
                <td>
                  <?php if (!empty($l['user_name'])): ?>
                    <strong class="text-dark small d-block"><?= htmlspecialchars($l['user_name']) ?></strong>
                    <span class="badge bg-light text-muted border" style="font-size:0.65rem;"><?= ucfirst($l['user_role'] ?? '') ?></span>
                  <?php else: ?>
                    <span class="text-muted small">Sistem / Pengunjung</span>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="badge bg-dark"><?= htmlspecialchars($l['action']) ?></span>
                </td>
                <td>
                  <small class="text-muted"><?= htmlspecialchars($l['entity_type'] ?? '-') ?> #<?= htmlspecialchars($l['entity_id'] ?? '') ?></small>
                </td>
                <td>
                  <small class="text-dark"><?= htmlspecialchars($l['description'] ?? '-') ?></small>
                </td>
                <td><code><?= htmlspecialchars($l['ip_address'] ?? '127.0.0.1') ?></code></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="p-3 border-top">
        <ul class="pagination pagination-sm justify-content-center mb-0">
          <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <li class="page-item <?= $page === $p ? 'active' : '' ?>">
              <a class="page-link" href="<?= ADMIN_URL ?>activity-logs.php?page=<?= $p ?>"><?= $p ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once dirname(__DIR__) . '/templates/admin_footer.php'; ?>
