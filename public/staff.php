<?php
require_once 'config.php';
$today = date('Y-m-d');
$templateVars = [
    'today' => $today,
    'page'  => 'staff',
];
extract($templateVars, EXTR_SKIP);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manajemen Staff - Software Defecta</title>
  <meta name="description" content="Daftar staff yang terdaftar di sistem defecta">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="icon" type="image/png" href="favicon.png">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>

<?php if (!is_logged_in()): ?>
  <?php include 'includes/auth_login.php'; ?>
<?php else: ?>

  <?php $headerActivePage = 'staff'; include 'includes/header.php'; ?>

  <main class="container">
    <div style="margin-bottom: 24px;">
      <h2 style="font-size: 18px; font-weight: 700; margin-bottom: 8px;">👥 Daftar Staff Terdaftar</h2>
      <p style="font-size: 13px; color: var(--text-muted);">Berikut adalah daftar staff yang dapat memilih untuk login dan melakukan transaksi.</p>
    </div>

    <div class="table-wrap" style="max-height: 500px; overflow-y: auto;">
      <table>
        <thead>
          <tr>
            <th style="width: 8%;">#</th>
            <th style="width: 15%;">ID Staff</th>
            <th>Nama Lengkap</th>
            <th style="width: 20%;">Peran</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $i = 1;
          foreach (STAFF_LIST as $key => $label):
              if ($key === '') continue; // skip placeholder
              $parts = explode(' (', $label);
              $name = $parts[0];
              $role = isset($parts[1]) ? rtrim($parts[1], ')') : '';
          ?>
          <tr>
            <td style="text-align: center; color: var(--text-muted);"><?= $i++ ?></td>
            <td style="font-family: monospace; font-weight: 600;"><?= htmlspecialchars($key) ?></td>
            <td><?= htmlspecialchars($name) ?></td>
            <td>
              <?php if ($role === 'Apoteker'): ?>
                <span style="display: inline-block; padding: 2px 8px; background: rgba(59,130,246,.12); border-radius: 10px; font-size: 11px; color: #3b82f6;">Apoteker</span>
              <?php else: ?>
                <span style="display: inline-block; padding: 2px 8px; background: rgba(34,197,94,.12); border-radius: 10px; font-size: 11px; color: #22c55e;">Staf</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div style="margin-top: 24px; padding: 16px; background: var(--surface2); border-radius: 8px; border: 1px solid var(--border);">
      <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text);">ℹ️ Catatan</h3>
      <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 8px;">
        ID staff adalah pengenal unik yang digunakan untuk audit trail. Saat ini, daftar staff didefinisikan
        dalam konstanta <code>STAFF_LIST</code> di <code>config.php</code>. Untuk menambahkan staff baru,
        edit file tersebut.
      </p>
      <p style="font-size: 12px; color: var(--text-muted);">
        Saat login, staff <strong>wajib</strong> memilih nama mereka dari dropdown. Login tidak akan berhasil
        tanpa pemilihan staff yang valid.
      </p>
    </div>
  </main>

  <nav class="bottom-nav">
    <a class="nav-item" href="index.php">
      <i class="stat-ico">🔴</i>
      <span>Defecta</span>
    </a>
    <a class="nav-item" href="index.php">
      <i class="stat-ico">✅</i>
      <span>Riwayat</span>
    </a>
    <a class="nav-item" href="laporan.php">
      <i class="stat-ico">📊</i>
      <span>Laporan</span>
    </a>
    <a class="nav-item active" href="staff.php">
      <i class="stat-ico">👥</i>
      <span>Staff</span>
    </a>
    <div class="nav-item" onclick="doLogout()">
      <i class="stat-ico">🚪</i>
      <span>Keluar</span>
    </div>
  </nav>

  <?php include 'includes/toasts.php'; ?>

<?php endif; ?>

<?php include 'includes/scripts.php'; ?>

</body>
</html>
