<?php
require_once 'config.php';
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Software Defecta – Apotek Mentari Farma Bondowoso</title>
  <meta name="description" content="Sistem monitoring stok defecta Apotek Mentari Farma Bondowoso">
  
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

  <?php include 'includes/header.php'; ?>

  <main class="container">
    <?php include 'includes/stats_bar.php'; ?>
    <?php include 'includes/toolbar.php'; ?>
    <?php include 'includes/table_desktop.php'; ?>
    <?php include 'includes/card_mobile.php'; ?>
    <?php include 'includes/pagination.php'; ?>
  </main>

  <?php include 'includes/navigation.php'; ?>
  <?php include 'includes/modal_add.php'; ?>
  <?php include 'includes/modal_edit.php'; ?>
  <?php include 'includes/modal_confirm.php'; ?>
  <?php include 'includes/bulk_bar.php'; ?>
  <?php include 'includes/toasts.php'; ?>

<?php endif; ?>

<script>
  // Inisialisasi variabel global untuk JS
  window.today = '<?= $today ?>';
  
  <?php if (is_logged_in()): ?>
  // Memuat data awal jika sudah login
  document.addEventListener('DOMContentLoaded', () => {
    if (typeof loadList === 'function') {
      loadList(1);
    }
  });
  <?php endif; ?>
</script>
<script src="assets/js/app.js"></script>

</body>
</html>
