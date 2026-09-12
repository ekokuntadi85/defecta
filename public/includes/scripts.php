<?php
/**
 * Shared JavaScript includes — loaded on all authenticated pages.
 *
 * This ensures functions like openBackupModal(), loadBackupList(),
 * restoreUpload(), doLogout(), etc. are available everywhere,
 * including the report page where only laporan.js was previously loaded.
 */
?>
<script>
  // Inisialisasi variabel global untuk JS
  window.today = '<?= $today ?? date('Y-m-d') ?>';
</script>
<?php if (is_logged_in()): ?>
  <script src="assets/js/app.js"></script>
  <?php if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'laporan.php'): ?>
    <script src="assets/js/laporan.js"></script>
  <?php endif; ?>
<?php else: ?>
  <script src="assets/js/app.js"></script>
<?php endif; ?>
