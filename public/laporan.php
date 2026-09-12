<?php
require_once 'config.php';
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan Riwayat – Apotek Mentari Farma Bondowoso</title>
  <meta name="description" content="Laporan riwayat defecta berdasarkan tanggal - Apotek Mentari Farma Bondowoso">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">
  
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  
  <link rel="icon" type="image/png" href="favicon.png">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php if (!is_logged_in()): ?>
  <?php include 'includes/auth_login.php'; ?>
<?php else: ?>

  <?php $headerActivePage = 'laporan'; include 'includes/header.php'; ?>

  <main class="container">
    <?php include 'includes/laporan_filter.php'; ?>
    <?php include 'includes/laporan_summary.php'; ?>
    <?php include 'includes/laporan_table.php'; ?>
  </main>

  <!-- Mobile Bottom Nav -->
  <nav class="bottom-nav">
    <a class="nav-item" href="index.php">
      <i class="stat-ico">🔴</i>
      <span>Defecta</span>
    </a>
    <a class="nav-item" href="index.php">
      <i class="stat-ico">✅</i>
      <span>Riwayat</span>
    </a>
    <a class="nav-item active" href="laporan.php">
      <i class="stat-ico">📊</i>
      <span>Laporan</span>
    </a>
    <div class="nav-item" onclick="doLogout()">
      <i class="stat-ico">🚪</i>
      <span>Keluar</span>
    </div>
  </nav>

  <?php include 'includes/toasts.php'; ?>
  <?php include 'includes/modal_backup.php'; ?>

<?php endif; ?>

<?php if (!is_logged_in()): ?>
  <script src="assets/js/app.js"></script>
<?php else: ?>
  <script>
    async function doLogout() {
      if (!confirm('Logout dari aplikasi?')) return;
      const fd = new FormData();
      fd.append('action', 'logout');
      const m = document.querySelector('meta[name="csrf-token"]');
      fd.append('csrf_token', m ? m.content : '');
      await fetch('api.php', { method: 'POST', body: fd });
      location.reload();
    }
  </script>
  <script src="assets/js/laporan.js"></script>
<?php endif; ?>

</body>
</html>
