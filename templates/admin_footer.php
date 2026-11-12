<?php
/**
 * Admin Panel Footer Template
 */
?>
    </main>

    <!-- Admin Footer -->
    <footer class="py-3 px-4 bg-white border-top text-center text-sm-start text-muted small mt-auto">
      <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
        <div>&copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong> Admin Panel &bull; Versi <?= APP_VERSION ?></div>
        <div class="mt-1 mt-sm-0 text-muted">Aksesoris Marketplace System &bull; MariaDB &amp; PHP 8</div>
      </div>
    </footer>
  </div>

  <!-- NNasa AI Assistant Widget (Integrated Across Platform) -->
  <?php require_once __DIR__ . '/ai_chat.php'; ?>

  <!-- Bootstrap 5.3 JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Global App JS (Toast & OTP Notifications) -->
  <script src="<?= BASE_URL ?>public/assets/js/app.js?v=<?= time() ?>"></script>

  <!-- NNasa AI Assistant JS -->
  <script src="<?= BASE_URL ?>public/assets/js/ai-chat.js?v=<?= time() ?>"></script>

  <script>
    function toggleAdminSidebar() {
      const sidebar = document.getElementById('adminSidebar');
      sidebar.classList.toggle('show');
    }
  </script>
</body>
</html>
