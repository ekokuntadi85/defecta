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
  <meta name="description" content="Kelola akun staff - Software Defecta">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link rel="icon" type="image/png" href="favicon.png">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
  <style>
    /* Staff page specific styles */
    .btn-delete {
      width: 34px;
      height: 34px;
      background: rgba(239, 68, 68, .12);
      border: 1.5px solid rgba(239, 68, 68, .3);
      border-radius: 8px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 15px;
      transition: all .18s;
      flex-shrink: 0;
    }
    .btn-delete:hover {
      background: var(--danger);
      border-color: var(--danger);
      transform: scale(1.1);
    }
    .action-cell {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 6px;
      width: 100%;
    }
    .staff-table-wrap tbody td {
      padding: 13.2px 16px;
    }
    .staff-table-wrap tbody tr {
      height: 44px;
    }
    .modal { max-width: 480px; }
    .form-control, .form-select {
      width: 100%;
      padding: 10px 12px;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      background: var(--surface2);
      color: var(--text);
      font-size: 14px;
    }
    .form-select { cursor: pointer; }
    .btn--outline {
      background: transparent;
      border: 1.5px solid var(--border);
      border-radius: var(--radius);
      padding: 8px 16px;
      font-size: 13px;
      cursor: pointer;
    }
    .btn--outline:hover { background: var(--surface2); }
    @media (max-width: 600px) {
      .staff-summary { flex-direction: column; gap: 8px; }
      .staff-table-wrap { max-height: 400px; }
      .staff-table-wrap thead { display: none; }
      .staff-table-wrap tbody, .staff-table-wrap tbody tr { display: block; }
      .staff-table-wrap tbody tr { margin-bottom: 12px; border-radius: 8px; border: 1px solid var(--border); }
      .staff-table-wrap tbody td { display: block; padding: 8px 12px; text-align: left; }
      .staff-table-wrap tbody td:not(:first-child) { border-top: 1px solid var(--border); }
    }
  </style>
</head>
<body data-today="<?= htmlspecialchars($today, ENT_QUOTES) ?>">

<?php include 'includes/scripts.php'; ?>

<?php if (!is_logged_in()): ?>
  <?php include 'includes/auth_login.php'; ?>
<?php else: ?>

  <?php $headerActivePage = 'staff'; include 'includes/header.php'; ?>

  <main class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
      <h2 style="font-size: 18px; font-weight: 700; margin: 0;">👥 Manajemen Staff</h2>
      <?php if (is_admin()): ?>
        <button class="btn btn-primary" onclick="openStaffModal()" style="padding: 8px 16px; font-size: 13px;">
          ➕ Tambah Staff Baru
        </button>
      <?php endif; ?>
    </div>

    <?php if (is_admin()): ?>
      <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
        Anda login sebagai <strong><?= htmlspecialchars(current_user_display()) ?></strong> (<?= htmlspecialchars(ucfirst(current_user_role())) ?>).
        Anda dapat menambah, mengedit, dan menonaktifkan staff lain.
      </p>
    <?php else: ?>
      <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 16px;">
        Anda login sebagai <strong><?= htmlspecialchars(current_user_display()) ?></strong> (<?= htmlspecialchars(ucfirst(current_user_role())) ?>).
        Anda hanya dapat mengubah PIN dan profil pribadi Anda.
      </p>
    <?php endif; ?>

    <div class="staff-table-wrap" style="max-height: 500px; overflow-y: auto;">
      <table>
        <thead>
          <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 18%;">Username</th>
            <th>Nama Lengkap</th>
            <th style="width: 15%;">Peran</th>
            <th style="width: 12%;">Status</th>
            <th style="width: 20%;">Aksi</th>
          </tr>
        </thead>
        <tbody id="staffTableBody">
          <tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)"><span class="spinner"></span> Memuat...</td></tr>
        </tbody>
      </table>
    </div>

    <?php if (is_admin()): ?>
      <div style="margin-top: 24px; padding: 16px; background: var(--surface2); border-radius: 8px; border: 1px solid var(--border);">
        <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text);">📊 Ringkasan</h3>
        <p style="font-size: 12px; color: var(--text-muted); margin: 0;">
          Total: <span id="staffTotalCount" style="font-weight:600">-</span> staff terdaftar |
          Aktif: <span id="staffActiveCount" style="font-weight:600;color:#22c55e">-</span> |
          Non-aktif: <span id="staffInactiveCount" style="font-weight:600;color:#ef4444;-"></span>
        </p>
      </div>
    <?php endif; ?>
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
  <?php include 'includes/modal_backup.php'; ?>
  <?php include 'includes/modal_confirm.php'; ?>

  <!-- Staff Modal -->
  <div class="modal-overlay" id="staffModalOverlay">
    <div class="modal" style="max-width: 480px;">
      <div class="modal-header">
        <h2 class="modal-title" id="staffModalTitle">Tambah Staff Baru</h2>
        <button class="close-btn" onclick="closeStaffModal()">✕</button>
      </div>
      <form id="staffForm" onsubmit="event.preventDefault(); submitStaff();">
        <input type="hidden" id="staffId" value="">
        <div style="display: flex; flex-direction: column; gap: 16px;">
          <?php if (is_admin()): ?>
          <div>
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text);">Username</label>
            <input type="text" id="staffUsername" class="form-control" placeholder="username" style="width: 100%;" required>
          </div>
          <?php endif; ?>
          <div>
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text);">Nama Lengkap</label>
            <input type="text" id="staffDisplayName" class="form-control" placeholder="Nama lengkap" style="width: 100%;" required>
          </div>
          <?php if (is_admin()): ?>
          <div>
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text);">Peran</label>
            <select id="staffRole" class="form-select" style="width: 100%;">
              <option value="staff">Staf</option>
              <option value="manager">Manajer</option>
              <option value="apoteker">Apoteker</option>
            </select>
          </div>
          <div>
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text);">PIN (6 digit)</label>
            <input type="password" id="staffPin" class="pin-input" maxlength="6" minlength="6" placeholder="••••••" style="width: 100%;">
          </div>
          <div>
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text);">Status Aktif</label>
            <select id="staffActive" class="form-select" style="width: 100%;">
              <option value="1">Aktif</option>
              <option value="0">Non-aktif</option>
            </select>
          </div>
          <?php else: ?>
          <div>
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: var(--text);">PIN Lama</label>
            <input type="password" id="staffOldPin" class="pin-input" maxlength="6" placeholder="••••••" style="width: 100%;">
            <label style="display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; margin-top: 12px; color: var(--text);">PIN Baru</label>
            <input type="password" id="staffNewPin" class="pin-input" maxlength="6" placeholder="••••••" style="width: 100%;">
          </div>
          <?php endif; ?>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end;">
          <button type="button" class="btn btn--outline" onclick="closeStaffModal()" style="font-size: 13px;">Batal</button>
          <button type="submit" class="btn btn-primary" id="staffSubmitBtn" style="font-size: 13px;">
            <span id="staffSubmitText">Simpan</span>
          </button>
        </div>
      </form>
    </div>
   </div>

   <script>
     window.IS_ADMIN = <?= json_encode(is_admin()) ?>;
     window.CURRENT_USER_ID = <?= json_encode(current_user_id()) ?>;
   </script>

   <script src="<?= jsUrl('assets/js/staff.js') ?>"></script>

<?php endif; ?>

</body>
</html>
