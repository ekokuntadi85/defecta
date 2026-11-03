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
<body>

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
    // ── STAFF MANAGEMENT ───────────────────────
    var currentStaffPage = [];
    let staffListCache = [];

    async function loadStaffList() {
        const tbody = document.getElementById('staffTableBody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;"><span class="spinner"></span> Memuat...</td></tr>';

        try {
            const res = await fetch('api.php?action=staff_list');
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            staffListCache = json.data;
            renderStaffTable(json.data);
            updateStaffSummary();
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:var(--danger)">❌ ' + e.message + '</td></tr>';
        }
    }

    function renderStaffTable(users) {
        const tbody = document.getElementById('staffTableBody');
        if (!tbody) return;
        const isAdmin = <?= json_encode(is_admin()) ?>;
        const currentUserId = <?= json_encode(current_user_id()) ?>;

        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--text-muted)">Belum ada staff terdaftar.</td></tr>';
            return;
        }

        tbody.innerHTML = users.map((u, i) => {
            const roleLabel = u.role === 'apoteker' ? '🔵 Apoteker' : u.role === 'manager' ? '🟦 Manajer' : '🟢 Staf';
            const statusBadge = u.is_active
                ? '<span style="color:#22c55e;font-size:11px">● Aktif</span>'
                : '<span style="color:#ef4444;font-size:11px">○ Non-aktif</span>';
            const isSelf = u.id === currentUserId;
            const canEdit = isAdmin || isSelf;

            let actions = '';
            if (canEdit) {
                actions = '<button class="btn-edit" onclick="openStaffModal(' + u.id + ')" title="Edit">✏️</button>';
            }
            if (isAdmin && !isSelf) {
                actions += '<button class="btn-delete" onclick="deleteStaff(' + u.id + ')" title="Non-aktifkan">🗑️</button>';
            }

            return '<tr>' +
                '<td class="td-no" style="text-align:center;color:var(--text-muted)">' + (i + 1) + '</td>' +
                '<td style="font-family:monospace;font-weight:600">' + escHtml(u.username) + '</td>' +
                '<td>' + escHtml(u.display_name) + '</td>' +
                '<td style="font-size:12px">' + roleLabel + '</td>' +
                '<td style="text-align:center">' + statusBadge + '</td>' +
                '<td class="action-cell">' + actions + '</td>' +
                '</tr>';
        }).join('');
    }

    function updateStaffSummary() {
        const totalEl = document.getElementById('staffTotalCount');
        const activeEl = document.getElementById('staffActiveCount');
        const inactiveEl = document.getElementById('staffInactiveCount');
        if (!totalEl || !activeEl || !inactiveEl) return;

        totalEl.textContent = staffListCache.length;
        activeEl.textContent = staffListCache.filter(u => u.is_active).length;
        inactiveEl.textContent = staffListCache.filter(u => !u.is_active).length;
    }

    function openStaffModal(id = null) {
        const modal = document.getElementById('staffModalOverlay');
        if (!modal) return;
        modal.classList.add('open');

        if (id) {
            document.getElementById('staffModalTitle').textContent = 'Edit Staff';
            const user = staffListCache.find(u => u.id === id);
            if (user) {
                document.getElementById('staffId').value = user.id;
                document.getElementById('staffUsername').value = user.username;
                document.getElementById('staffDisplayName').value = user.display_name;
                document.getElementById('staffRole').value = user.role;
                document.getElementById('staffActive').value = user.is_active ? '1' : '0';
            }
        } else {
            document.getElementById('staffModalTitle').textContent = 'Tambah Staff Baru';
            document.getElementById('staffForm').reset();
            document.getElementById('staffId').value = '';
            document.getElementById('staffRole').value = 'staff';
            document.getElementById('staffActive').value = '1';
        }
    }

    function closeStaffModal() {
        const modal = document.getElementById('staffModalOverlay');
        if (modal) modal.classList.remove('open');
    }

    async function submitStaff() {
        const staffId = document.getElementById('staffId').value;
        const isAdmin = <?= json_encode(is_admin()) ?>;
        const action = staffId ? 'staff_update' : (isAdmin ? 'staff_create' : 'staff_update');

        const fd = new FormData();
        fd.append('csrf_token', csrfToken());
        fd.append('action', action);
        fd.append('id', staffId);
        fd.append('display_name', document.getElementById('staffDisplayName').value);

        if (isAdmin) {
            fd.append('username', document.getElementById('staffUsername').value);
            fd.append('role', document.getElementById('staffRole').value);
            fd.append('is_active', document.getElementById('staffActive').value);
            fd.append('new_pin', document.getElementById('staffPin').value);
        } else {
            fd.append('old_pin', document.getElementById('staffOldPin').value);
            fd.append('new_pin', document.getElementById('staffNewPin').value);
        }

        try {
            const res = await fetch('api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            showToast('✅ ' + json.message, 'success');
            closeStaffModal();
            loadStaffList();
        } catch (e) {
            showToast('❌ ' + e.message, 'error');
        }
    }

    async function createStaff() {
        const fd = new FormData();
        fd.append('csrf_token', csrfToken());
        fd.append('action', 'staff_create');
        fd.append('username', document.getElementById('staffUsername').value);
        fd.append('display_name', document.getElementById('staffDisplayName').value);
        fd.append('role', document.getElementById('staffRole').value);
        fd.append('pin', document.getElementById('staffPin').value);

        try {
            const res = await fetch('api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            showToast('✅ ' + json.message, 'success');
            closeStaffModal();
            loadStaffList();
        } catch (e) {
            showToast('❌ ' + e.message, 'error');
        }
    }

    async function deleteStaff(id) {
        if (!confirm('Nonaktifkan staff ini? Data tetap tersimpan tapi tidak bisa login.')) return;

        const fd = new FormData();
        fd.append('csrf_token', csrfToken());
        fd.append('action', 'staff_delete');
        fd.append('id', id);

        try {
            const res = await fetch('api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);
            showToast('✅ ' + json.message, 'success');
            loadStaffList();
        } catch (e) {
            showToast('❌ ' + e.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadStaffList();
    });
   </script>

<?php endif; ?>

</body>
</html>
