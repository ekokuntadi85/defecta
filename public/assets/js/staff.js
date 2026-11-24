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
    const isAdmin = window.IS_ADMIN || false;
    const currentUserId = window.CURRENT_USER_ID || 0;

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
    const isAdmin = window.IS_ADMIN || false;
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