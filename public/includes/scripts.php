<?php
/**
 * Shared JavaScript includes — loaded on all authenticated pages.
 *
 * This ensures functions like openBackupModal(), loadBackupList(),
 * restoreUpload(), doLogout(), etc. are available everywhere,
 * including the report page where only laporan.js was previously loaded.
 *
 * Cache-busting: file paths include a query param with the file's
 * modification timestamp so browsers always get the latest version
 * after a rebuild.
 */
function jsUrl(string $path): string {
    // paths here are relative to public/ (the docroot)
    $fullPath = __DIR__ . '/../' . $path;
    $v = file_exists($fullPath) ? filemtime($fullPath) : time();
    return $path . '?v=' . $v;
}
?>
<script>
  window.today = '<?= $today ?? date('Y-m-d') ?>';
</script>
<?php if (is_logged_in()): ?>
  <script src="<?= jsUrl('assets/js/app.js') ?>"></script>
  <?php if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'laporan.php'): ?>
    <script src="<?= jsUrl('assets/js/laporan.js') ?>"></script>
  <?php endif; ?>
<?php else: ?>
  <script src="<?= jsUrl('assets/js/app.js') ?>"></script>
<?php endif; ?>
