// ── STATE ──────────────────────────────────
let currentPage = 1;
let currentSearch = '';
let currentMode = 'defecta'; // 'defecta' | 'riwayat'
let totalPages = 1;
let totalDefecta = 0;
let debounceTimer;
let confirmCallback = null;
let autocompleteTimer;
let autocompleteSelected = false;

// ── BULK SELECTION ─────────────────────────
let selectedIds = new Set();

function toggleAll(cb) {
    const allCbs = document.querySelectorAll('.row-cb');
    allCbs.forEach(r => {
        r.checked = cb.checked;
        const id = parseInt(r.dataset.id);
        if (isNaN(id)) return;
        cb.checked ? selectedIds.add(id) : selectedIds.delete(id);

        // update tr & card
        const tr = document.getElementById('row-' + id);
        if (tr) tr.classList.toggle('row-selected', cb.checked);
        const card = document.getElementById('card-' + id);
        if (card) card.classList.toggle('row-selected', cb.checked);
    });
    updateBulkBar();
}

function toggleRow(cb) {
    const id = parseInt(cb.dataset.id);
    const checked = cb.checked;
    checked ? selectedIds.add(id) : selectedIds.delete(id);

    // Sync semua checkbox dengan ID yang sama (desktop & mobile)
    document.querySelectorAll(`.row-cb[data-id="${id}"]`).forEach(c => c.checked = checked);

    // Sync styling tr & card
    const tr = document.getElementById('row-' + id);
    if (tr) tr.classList.toggle('row-selected', checked);
    const card = document.getElementById('card-' + id);
    if (card) card.classList.toggle('row-selected', checked);

    const allCbs = document.querySelectorAll('#tableBody input.row-cb');
    const cbAll = document.getElementById('cbAll');
    if (cbAll) {
        const checkedCount = document.querySelectorAll('#tableBody input.row-cb:checked').length;
        cbAll.checked = checkedCount === allCbs.length && allCbs.length > 0;
        cbAll.indeterminate = checkedCount > 0 && checkedCount < allCbs.length;
    }
    updateBulkBar();
}

function updateBulkBar() {
    const n = selectedIds.size;
    const bulkCountEl = document.getElementById('bulkCount');
    if (bulkCountEl) bulkCountEl.textContent = n;

    const bulkBarEl = document.getElementById('bulkBar');
    if (bulkBarEl) bulkBarEl.classList.toggle('show', n > 0);

    // Teks tombol sesuai mode
    const btnBulkOk = document.querySelector('.btn-bulk-ok');
    if (btnBulkOk) {
        const isRiwayat = currentMode === 'riwayat';
        btnBulkOk.innerHTML = isRiwayat ? '↩ Defecta Lagi' : '✅ Tandai Tersedia';
    }
}

function clearSelection() {
    selectedIds.clear();
    document.querySelectorAll('#tableBody input.row-cb').forEach(r => {
        r.checked = false;
        r.closest('tr').classList.remove('row-selected');
    });
    const cbAll = document.getElementById('cbAll');
    if (cbAll) { cbAll.checked = false; cbAll.indeterminate = false; }
    updateBulkBar();
}

async function bulkAction() {
    if (selectedIds.size === 0) return;
    const ids = Array.from(selectedIds);
    const isRiwayat = currentMode === 'riwayat';
    const apiAction = isRiwayat ? 'bulk_defecta' : 'bulk_tersedia';
    const btn = document.querySelector('.btn-bulk-ok');
    btn.disabled = true;
    btn.textContent = '⏳ Memproses...';
    try {
        const fd = new FormData();
        fd.append('action', apiAction);
        fd.append('ids', ids.join(','));
        fd.append('csrf_token', csrfToken());
        const res = await fetch('api.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);
        showToast('✅ ' + json.message, 'success');
        selectedIds.clear();
        loadList(currentPage, currentSearch);
    } catch (e) {
        showToast('❌ Gagal: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        updateBulkBar();
    }
}

async function bulkDelete() {
    if (selectedIds.size === 0) return;
    const n = selectedIds.size;

    confirmCallback = async () => {
        const ids = Array.from(selectedIds);
        const btn = document.querySelector('.btn-bulk-delete');
        btn.disabled = true;
        btn.textContent = '⏳';

        try {
            const fd = new FormData();
            fd.append('action', 'bulk_delete');
            fd.append('ids', ids.join(','));
            fd.append('csrf_token', csrfToken());
            const res = await fetch('api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            showToast('🗑️ ' + json.message, 'success');
            selectedIds.clear();
            loadList(currentPage, currentSearch);
        } catch (e) {
            showToast('❌ Gagal: ' + e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.textContent = '🗑️';
            updateBulkBar();
        }
        closeConfirm();
    };

    const confirmMsgEl = document.getElementById('confirmMsg');
    if (confirmMsgEl) confirmMsgEl.textContent = `Hapus ${n} item yang dipilih secara permanen?`;

    const confirmOverlayEl = document.getElementById('confirmOverlay');
    if (confirmOverlayEl) confirmOverlayEl.classList.add('open');

    const confirmOkBtnEl = document.getElementById('confirmOkBtn');
    if (confirmOkBtnEl) {
        confirmOkBtnEl.onclick = confirmCallback;
        // Berikan fokus ke tombol OK agar layar bisa langsung Enter/Space
        setTimeout(() => confirmOkBtnEl.focus(), 100);
    }
}

// Sinkronkan checkbox header dengan status halaman saat ini
function syncCbAll() {
    const allCbs = document.querySelectorAll('#tableBody input.row-cb');
    const cbAll = document.getElementById('cbAll');
    if (!cbAll || allCbs.length === 0) return;
    const checkedCount = [...allCbs].filter(r => selectedIds.has(parseInt(r.dataset.id))).length;
    cbAll.checked = checkedCount === allCbs.length;
    cbAll.indeterminate = checkedCount > 0 && checkedCount < allCbs.length;
}

// ── MODE SWITCH ──────────────────────────
function switchMode(mode) {
    if (currentMode === mode) return; // sudah di mode ini
    currentMode = mode;
    selectedIds.clear();

    const searchInputEl = document.getElementById('searchInput');
    if (searchInputEl) searchInputEl.value = '';
    currentSearch = '';

    // Update UI Desktop Tabs
    const cardDefectaEl = document.getElementById('cardDefecta');
    const cardRiwayatEl = document.getElementById('cardRiwayat');
    if (cardDefectaEl) cardDefectaEl.classList.toggle('stat-active', mode === 'defecta');
    if (cardRiwayatEl) cardRiwayatEl.classList.toggle('stat-active', mode === 'riwayat');

    // Update UI Mobile Nav
    const navDefectaEl = document.getElementById('navDefecta');
    const navRiwayatEl = document.getElementById('navRiwayat');
    if (navDefectaEl) navDefectaEl.classList.toggle('active', mode === 'defecta');
    if (navRiwayatEl) navRiwayatEl.classList.toggle('active', mode === 'riwayat');

    // Update header kolom (Desktop)
    const thAksiEl = document.getElementById('thAksi');
    if (thAksiEl) thAksiEl.textContent = 'Aksi';

    // Tombol tambah & FAB hanya di mode defecta
    const btnTambahEl = document.getElementById('btnTambah');
    const fabTambahEl = document.getElementById('fabTambah');
    if (btnTambahEl) btnTambahEl.style.display = mode === 'defecta' ? '' : 'none';
    if (fabTambahEl) fabTambahEl.style.display = mode === 'defecta' ? '' : 'none';

    // Muat data
    loadList(1, '');
}

// ── FETCH LIST ─────────────────────────────
async function loadList(page = 1, search = currentSearch) {
    currentPage = page;
    currentSearch = search;

    // Loading indicator
    const tbody = document.getElementById('tableBody');
    const mList = document.getElementById('mobileCardList');
    if (tbody) tbody.innerHTML = `<tr class="loading-row"><td colspan="5"><span class="spinner"></span>Memuat data...</td></tr>`;
    if (mList) mList.innerHTML = `<div style="text-align:center;padding:40px;opacity:.6;"><span class="spinner"></span></div>`;

    try {
        const url = `api.php?action=list&page=${page}&mode=${currentMode}&search=${encodeURIComponent(search)}`;
        const res = await fetch(url);
        const json = await res.json();

        if (!json.success) throw new Error(json.message);

        totalPages = json.pages || 1;
        totalDefecta = json.total_defecta || 0;

        // Update kedua stat card
        const statDefectaEl = document.getElementById('statDefecta');
        const statRiwayatEl = document.getElementById('statRiwayat');
        if (statDefectaEl) statDefectaEl.textContent = json.total_defecta;
        if (statRiwayatEl) statRiwayatEl.textContent = json.total_riwayat;

        renderTable(json.data, page, json.limit);
        renderPagination(json.page, json.pages, json.total);
        syncCbAll();
        updateBulkBar();

    } catch (e) {
        const errMsg = `<tr class="loading-row"><td colspan="5" style="color:var(--danger)">❌ Gagal memuat: ${e.message}</td></tr>`;
        if (tbody) tbody.innerHTML = errMsg;
        if (mList) mList.innerHTML = `<div style="color:var(--danger);text-align:center;padding:20px;">❌ ${e.message}</div>`;
    }
}

function renderTable(rows, page, limit) {
    const tbody = document.getElementById('tableBody');
    const mList = document.getElementById('mobileCardList');
    if (!tbody || !mList) return;

    const isRiwayat = currentMode === 'riwayat';

    if (!rows || rows.length === 0) {
        const emptyMsg = `
        <div class="empty-state">
          <div class="empty-icon">${isRiwayat ? '📚' : '🎉'}</div>
          <h3>${isRiwayat ? 'Belum ada riwayat terpenuhi.' : 'Semua obat tersedia!'}</h3>
          <p>${isRiwayat ? 'Tandai obat defecta sebagai tersedia terlebih dahulu.' : 'Tidak ada item defecta saat ini.'}</p>
        </div>`;
        tbody.innerHTML = `<tr><td colspan="5">${emptyMsg}</td></tr>`;
        mList.innerHTML = emptyMsg;
        return;
    }

    const startNo = (page - 1) * limit + 1;
    const htmlRows = rows.map((row, idx) => {
        const ket = escHtml(row.keterangan);
        let badgeClass = 'ket-lain';
        if (row.keterangan === 'Stock Habis') badgeClass = 'ket-habis';
        else if (row.keterangan === 'Penolakan') badgeClass = 'ket-tolak';
        const tgl = isRiwayat ? formatDate(row.updated_at?.split(' ')[0]) : formatDate(row.tanggal);
        const isChecked = selectedIds.has(row.id);

        // Desktop Action Btn (pakai data-* + delegation, aman dari XSS)
        const editBtn = `<button class="btn-edit" data-action="edit" data-id="${row.id}" data-nama="${escHtml(row.nama_obat)}" data-tanggal="${escHtml(row.tanggal)}" data-ket="${escHtml(row.keterangan)}" title="Edit data">✏️</button>`;
        const actionBtn = isRiwayat
            ? `<button class="btn-check" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3)" data-action="undo" data-id="${row.id}" data-nama="${escHtml(row.nama_obat)}" title="Kembalikan ke Defecta"><span style="color:var(--danger)">↩</span></button>`
            : `<button class="btn-check" data-action="done" data-id="${row.id}" data-nama="${escHtml(row.nama_obat)}" title="Tandai Tersedia" id="chk-${row.id}"><span>✓</span></button>`;

        return {
            id: row.id,
            html: `
        <tr id="row-${row.id}" class="${isChecked ? 'row-selected' : ''}">
          <td class="td-no" style="text-align:center">
            <input type="checkbox" class="row-cb" data-id="${row.id}" ${isChecked ? 'checked' : ''}>
          </td>
          <td class="td-date">${tgl}</td>
          <td class="td-drug">${escHtml(row.nama_obat)}</td>
          <td class="td-notes"><span class="badge-ket ${badgeClass}">${ket}</span></td>
          <td style="text-align:center"><div style="display:flex;gap:6px;justify-content:center;align-items:center;">${editBtn}${actionBtn}</div></td>
        </tr>`,
            card: `
        <div class="mobile-card ${isChecked ? 'row-selected' : ''}" id="card-${row.id}" data-card-id="${row.id}">
          <div class="mobile-card-header">
            <div class="mobile-card-title">${escHtml(row.nama_obat)}</div>
            <input type="checkbox" class="row-cb" style="width:20px;height:20px;" data-id="${row.id}" ${isChecked ? 'checked' : ''}>
          </div>
          <div class="mobile-card-meta">
            <span>📅 ${tgl}</span>
            <span class="badge-ket ${badgeClass}" style="font-size:10px;padding:2px 8px">${ket}</span>
          </div>
          <div class="mobile-card-actions">
            <button class="mobile-card-edit" data-action="edit" data-id="${row.id}" data-nama="${escHtml(row.nama_obat)}" data-tanggal="${escHtml(row.tanggal)}" data-ket="${escHtml(row.keterangan)}" title="Edit">✏️</button>
            ${isRiwayat
                    ? `<button class="mobile-card-check btn-undo" data-action="undo" data-id="${row.id}" data-nama="${escHtml(row.nama_obat)}">↩ Kembalikan ke Defecta</button>`
                    : `<button class="mobile-card-check" data-action="done" data-id="${row.id}" data-nama="${escHtml(row.nama_obat)}">✅ Tandai Tersedia</button>`
                }
          </div>
        </div>`
        };
    });

    tbody.innerHTML = htmlRows.map(r => r.html).join('');
    mList.innerHTML = htmlRows.map(r => r.card).join('');
}

// Aksi tombol via event delegation (data-*), aman dari XSS
function handleActionClick(e) {
    const btn = e.target.closest('[data-action]');
    if (!btn) return;
    const id    = parseInt(btn.dataset.id, 10);
    const nama  = btn.dataset.nama || '';
    if (isNaN(id)) return;

    switch (btn.dataset.action) {
        case 'edit':
            openEditModal(id, nama, btn.dataset.tanggal || '', btn.dataset.ket || '');
            break;
        case 'done':
            confirmMarkSingle(id, nama, 'tersedia', btn);
            break;
        case 'undo':
            confirmMarkSingle(id, nama, 'defecta', btn);
            break;
    }
}

// Tambahan Mobile interaction (klik kartu = toggle seleksi)
function handleCardClick(e) {
    const card = e.target.closest('.mobile-card');
    if (!card) return;
    if (e.target.closest('button') || e.target.closest('input')) return;

    const id = parseInt(card.dataset.cardId, 10);
    const cb = card.querySelector('.row-cb');
    if (cb && !isNaN(id)) {
        cb.checked = !cb.checked;
        toggleRow(cb);
    }
}

function renderPagination(page, pages, total) {
    const el = document.getElementById('pagination');
    if (!el) return;
    if (pages <= 1) { el.innerHTML = ''; return; }

    let html = '';
    html += `<button class="page-btn" onclick="loadList(${page - 1})" ${page <= 1 ? 'disabled' : ''}>‹ Prev</button>`;

    // Show limited range of pages
    let start = Math.max(1, page - 2);
    let end = Math.min(pages, page + 2);
    if (start > 1) html += `<button class="page-btn" onclick="loadList(1)">1</button>${start > 2 ? '<span class="page-info">…</span>' : ''}`;

    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadList(${i})">${i}</button>`;
    }
    if (end < pages) html += `${end < pages - 1 ? '<span class="page-info">…</span>' : ''}<button class="page-btn" onclick="loadList(${pages})">${pages}</button>`;

    html += `<button class="page-btn" onclick="loadList(${page + 1})" ${page >= pages ? 'disabled' : ''}>Next ›</button>`;
    html += `<span class="page-info">Total: ${total}</span>`;
    el.innerHTML = html;
}

// ── DATE FORMATTER ─────────────────────────
function formatDate(str) {
    if (!str) return '–';
    const d = new Date(str + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── MARK SINGLE (Bidirectional) ──────────────
function confirmMarkSingle(id, namaObat, targetStatus, btn) {
    const isKeDefecta = targetStatus === 'defecta';
    const msg = isKeDefecta
        ? `Kembalikan "${namaObat}" ke daftar defecta?`
        : `"${namaObat}" sudah tersedia di stok?`;

    confirmCallback = async () => {
        btn.disabled = true;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px"></span>';

        try {
            const fd = new FormData();
            fd.append('action', isKeDefecta ? 'bulk_defecta' : 'tersedia');
            fd.append(isKeDefecta ? 'ids' : 'id', id);
            fd.append('csrf_token', csrfToken());

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const json = await res.json();
            if (!json.success) throw new Error(json.message);

            // Animasi hapus (Desktop)
            const row = document.getElementById('row-' + id);
            if (row) row.classList.add('row-removing');

            // Animasi hapus (Mobile Card)
            const card = document.getElementById('card-' + id);
            if (card) {
                card.style.transition = 'all .4s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateX(50px)';
            }

            setTimeout(() => {
                loadList(currentPage, currentSearch);
            }, 400);

            showToast('✅ ' + (isKeDefecta ? 'Dikembalikan ke defecta' : 'Ditandai tersedia'), 'success');
        } catch (e) {
            showToast('❌ Gagal: ' + e.message, 'error');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
        closeConfirm();
    };

    const confirmMsgEl = document.getElementById('confirmMsg');
    if (confirmMsgEl) confirmMsgEl.textContent = msg;

    const confirmOverlayEl = document.getElementById('confirmOverlay');
    if (confirmOverlayEl) confirmOverlayEl.classList.add('open');

    const confirmOkBtnEl = document.getElementById('confirmOkBtn');
    if (confirmOkBtnEl) {
        confirmOkBtnEl.onclick = confirmCallback;
        // Berikan fokus ke tombol OK agar layar bisa langsung Enter/Space
        setTimeout(() => confirmOkBtnEl.focus(), 100);
    }
}

function closeConfirm() {
    const confirmOverlayEl = document.getElementById('confirmOverlay');
    if (confirmOverlayEl) confirmOverlayEl.classList.remove('open');
    confirmCallback = null;
}

// ── MODAL ──────────────────────────────────
function openModal() {
    const addFormEl = document.getElementById('addForm');
    if (addFormEl) addFormEl.reset();

    const fTanggalEl = document.getElementById('f-tanggal');
    if (fTanggalEl) fTanggalEl.value = window.today || '';

    const ketLainnyaWrapEl = document.getElementById('ketLainnyaWrap');
    if (ketLainnyaWrapEl) ketLainnyaWrapEl.classList.remove('show');

    const fKetLainnyaEl = document.getElementById('f-ket-lainnya');
    if (fKetLainnyaEl) fKetLainnyaEl.value = '';

    closeAutocomplete();
    updateDefectaWarning(null);

    const modalOverlayEl = document.getElementById('modalOverlay');
    if (modalOverlayEl) modalOverlayEl.classList.add('open');

    setTimeout(() => {
        const fObatEl = document.getElementById('f-obat');
        if (fObatEl) fObatEl.focus();
    }, 150);
}

function closeModal() {
    const modalOverlayEl = document.getElementById('modalOverlay');
    if (modalOverlayEl) modalOverlayEl.classList.remove('open');
}

// ── KETERANGAN CHANGE ──────────────────────
function onKetChange(sel) {
    const wrap = document.getElementById('ketLainnyaWrap');
    const lainField = document.getElementById('f-ket-lainnya');
    if (!wrap || !lainField) return;

    if (sel.value === 'Lainnya') {
        wrap.classList.add('show');
        lainField.required = true;
        lainField.focus();
    } else {
        wrap.classList.remove('show');
        lainField.required = false;
        lainField.value = '';
    }
}

// ── AUTOCOMPLETE ───────────────────────────
let acItems = [];
let acIndex = -1;

async function fetchAutocomplete(q) {
    const dropdown = document.getElementById('autocompleteDropdown');
    if (!dropdown) return;

    try {
        const res = await fetch(`api.php?action=search_obat&q=${encodeURIComponent(q)}`);
        const json = await res.json();
        acItems = json.data || [];
        acIndex = -1;

        if (acItems.length === 0) {
            dropdown.innerHTML = `<div class="autocomplete-empty">Tidak ada riwayat – tulis bebas</div>`;
        } else {
            dropdown.innerHTML = acItems.map((it, i) => {
                if (it.status === 'defecta') {
                    return `<div class="autocomplete-item autocomplete-warn" title="Sudah di daftar Defecta">⚠️ ${escHtml(it.nama)}</div>`;
                }
                return `<div class="autocomplete-item" data-i="${i}" onmousedown="selectAc(${i})">${escHtml(it.nama)}</div>`;
            }).join('');
        }

        // Deteksi kecocokan persis dengan obat yang sedang Defecta
        const qLower = q.trim().toLowerCase();
        const exactDefecta = acItems.find(it =>
            it.status === 'defecta' && it.nama.trim().toLowerCase() === qLower
        );
        updateDefectaWarning(exactDefecta ? exactDefecta.nama : null);

        dropdown.classList.add('open');
    } catch (e) { }
}

// Peringatan inline: nama sudah ada di daftar Defecta (blokir submit)
let defectaLock = false;

function updateDefectaWarning(nama) {
    const el = document.getElementById('dupeWarn');
    const btn = document.getElementById('submitBtn');
    defectaLock = !!nama;
    if (el) {
        if (nama) {
            el.style.display = 'flex';
            el.innerHTML = `⚠️ <b>${escHtml(nama)}</b> sudah ada di daftar Defecta. Tidak bisa ditambahkan dua kali.`;
        } else {
            el.style.display = 'none';
            el.innerHTML = '';
        }
    }
    if (btn) btn.disabled = defectaLock;
}

function selectAc(i) {
    const item = acItems[i];
    if (!item || item.status !== 'tersedia') return;
    const drugInput = document.getElementById('f-obat');
    if (drugInput) drugInput.value = item.nama;
    closeAutocomplete();
}

function closeAutocomplete() {
    const dropdown = document.getElementById('autocompleteDropdown');
    if (dropdown) dropdown.classList.remove('open');
    acItems = [];
    acIndex = -1;
}

// ── SUBMIT FORM ────────────────────────────
async function submitForm(e) {
    e.preventDefault();

    const ketSel = document.getElementById('f-ket').value;
    let keterangan = ketSel;
    if (ketSel === 'Lainnya') {
        keterangan = document.getElementById('f-ket-lainnya').value.trim();
        if (!keterangan) {
            showToast('⚠️ Isi keterangan lainnya terlebih dahulu.', 'error');
            return;
        }
    }
    if (!keterangan) {
        showToast('⚠️ Pilih keterangan terlebih dahulu.', 'error');
        return;
    }

    const namaObat = document.getElementById('f-obat').value.trim();
    if (!namaObat) {
        showToast('⚠️ Nama obat wajib diisi.', 'error');
        return;
    }
    if (defectaLock) {
        showToast('⚠️ Obat ini sudah ada di daftar Defecta.', 'error');
        return;
    }

    const btn = document.getElementById('submitBtn');
    const text = document.getElementById('submitBtnText');
    btn.disabled = true;
    text.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px"></span> Menyimpan...';

    try {
        const fd = new FormData();
        fd.append('action', 'tambah');
        fd.append('tanggal', document.getElementById('f-tanggal').value);
        fd.append('nama_obat', namaObat);
        fd.append('keterangan', keterangan);
        fd.append('csrf_token', csrfToken());

        const res = await fetch('api.php', { method: 'POST', body: fd });
        const json = await res.json();

        if (!json.success) throw new Error(json.message);

        showToast('✅ ' + namaObat + ' berhasil ditambahkan.', 'success');
        closeModal();
        loadList(1, currentSearch);
    } catch (err) {
        showToast('❌ Gagal menyimpan: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        text.innerHTML = '💾 Simpan';
    }
}

// ── TOAST ──────────────────────────────────
function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const t = document.createElement('div');
    t.className = `toast toast-${type}`;
    t.innerHTML = `<span class="toast-ico">${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    container.appendChild(t);
    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateX(60px)';
        t.style.transition = 'all .3s';
        setTimeout(() => t.remove(), 320);
    }, 3200);
}

// ── UTIL ───────────────────────────────────
function escHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── CETAK / PDF ─────────────────────────────
async function printReport() {
    const btn = document.getElementById('btnCetak');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Menyiapkan...';
    }

    try {
        const url = `api.php?action=export&mode=${currentMode}&search=${encodeURIComponent(currentSearch)}`;
        const res = await fetch(url);
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        const rows = json.data;
        const isRiwayat = currentMode === 'riwayat';
        const modeLabel = isRiwayat ? 'Riwayat Obat Terpenuhi' : 'Daftar Obat Defecta';
        const now = new Date();
        const tglCetak = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const jamCetak = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        const filterLabel = currentSearch ? `Filter: "${currentSearch}"` : 'Semua data';

        const tableRows = rows.map((r, i) => {
            const tgl = isRiwayat
                ? new Date(r.updated_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
                : new Date(r.tanggal + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            return `
        <tr>
          <td style="text-align:center;border:1px solid #000;">${i + 1}</td>
          <td style="border:1px solid #000;">${tgl}</td>
          <td style="font-weight:600;border:1px solid #000;">${r.nama_obat}</td>
          <td style="border:1px solid #000;">${r.keterangan}</td>
        </tr>`;
        }).join('');

        const html = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>${modeLabel} - Apotek Mentari Farma Bondowoso</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; color:#000; background:#fff; padding:40px; }
    .header-print {
      border-bottom: 2px solid #000;
      padding-bottom: 10px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
    }
    .apotek-name { font-size: 20px; font-weight:800; }
    .apotek-sub  { font-size: 12px; margin-top:2px; }
    .report-title { font-size:18px; font-weight:700; text-align:center; margin-bottom:20px; text-transform:uppercase; }
    .meta-bar {
      margin-bottom: 15px;
      display:flex;
      justify-content: space-between;
      font-size:12px;
    }
    table {
      width:100%;
      border-collapse:collapse;
      font-size:12px;
      margin-bottom:30px;
    }
    thead th {
      padding:8px 10px;
      text-align:left;
      font-weight:700;
      border:1px solid #000;
      background:#f2f2f2;
    }
    tbody td {
      padding:8px 10px;
      border:1px solid #000;
      vertical-align:middle;
    }
    .footer {
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      font-size:11px;
    }
    .sign-area { text-align:center; width:200px; }
    .sign-line { border-top:1px solid #000; width:100%; margin:60px auto 5px; }
    @media print {
      body { padding: 0; }
      .no-print { display:none !important; }
      @page { size:A4; margin:1.5cm; }
    }
    .print-btn-bar {
      background:#f2f2f2;
      padding:10px 20px;
      display:flex;
      gap:10px;
      margin-bottom:20px;
      border:1px solid #ccc;
    }
    .print-btn {
      padding:6px 15px;
      background:#000;
      color:#fff;
      border:none;
      border-radius:4px;
      font-size:13px;
      font-weight:700;
      cursor:pointer;
    }
    .close-btn-pr {
      padding:6px 15px;
      background:#fff;
      color:#000;
      border:1px solid #000;
      border-radius:4px;
      font-size:13px;
      cursor:pointer;
    }
  </style>
</head>
<body>
  <div class="print-btn-bar no-print">
    <button class="print-btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    <button class="close-btn-pr" onclick="window.close()">✕ Tutup</button>
  </div>
  <div class="header-print">
    <div>
      <div class="apotek-name">Apotek Mentari Farma</div>
      <div class="apotek-sub">Bondowoso, Jawa Timur</div>
    </div>
    <div style="text-align:right;font-size:11px">
      Dicetak pada: ${tglCetak}, ${jamCetak} WIB
    </div>
  </div>
  <div class="report-title">${modeLabel}</div>
  <div class="meta-bar">
    <span><strong>Total:</strong> ${rows.length} item</span>
    <span><strong>Filter:</strong> ${filterLabel}</span>
  </div>
  <table>
    <thead>
      <tr>
        <th style="width:40px;text-align:center">#</th>
        <th style="width:120px">Tanggal</th>
        <th>Nama Obat</th>
        <th style="width:160px">Keterangan</th>
      </tr>
    </thead>
    <tbody>${tableRows}</tbody>
  </table>
  <div class="footer">
    <div>
      Sistem Defecta Obat &mdash; Apotek Mentari Farma Bondowoso
    </div>
    <div class="sign-area">
      <div>Mengetahui,</div>
      <div class="sign-line"></div>
      <div>Apoteker Penanggung Jawab</div>
    </div>
  </div>
</body>
</html>`;

        const win = window.open('', '_blank', 'width=900,height=700');
        win.document.write(html);
        win.document.close();
    } catch (e) {
        showToast('❌ Gagal menyiapkan laporan: ' + e.message, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '🖨️ Cetak PDF';
        }
    }
}

// ── EDIT MODAL ─────────────────────────────
const KNOWN_KET = ['Stock Menipis', 'Stock Habis', 'Penolakan'];

function openEditModal(id, namaObat, tanggal, keterangan) {
    document.getElementById('ef-id').value       = id;
    document.getElementById('ef-tanggal').value  = tanggal;
    document.getElementById('ef-obat').value     = namaObat;

    const ketSel  = document.getElementById('ef-ket');
    const ketWrap = document.getElementById('efKetLainnyaWrap');
    const ketLain = document.getElementById('ef-ket-lainnya');

    if (KNOWN_KET.includes(keterangan)) {
        ketSel.value = keterangan;
        ketWrap.classList.remove('show');
        ketLain.value = '';
        ketLain.required = false;
    } else {
        ketSel.value = 'Lainnya';
        ketWrap.classList.add('show');
        ketLain.value    = keterangan;
        ketLain.required = true;
    }

    document.getElementById('editModalOverlay').classList.add('open');
    setTimeout(() => document.getElementById('ef-obat').focus(), 150);
}

function closeEditModal() {
    document.getElementById('editModalOverlay').classList.remove('open');
}

function onEditKetChange(sel) {
    const wrap = document.getElementById('efKetLainnyaWrap');
    const lain = document.getElementById('ef-ket-lainnya');
    if (sel.value === 'Lainnya') {
        wrap.classList.add('show');
        lain.required = true;
        lain.focus();
    } else {
        wrap.classList.remove('show');
        lain.required = false;
        lain.value = '';
    }
}

async function submitEdit(e) {
    e.preventDefault();

    const id        = document.getElementById('ef-id').value;
    const tanggal   = document.getElementById('ef-tanggal').value;
    const namaObat  = document.getElementById('ef-obat').value.trim();
    const ketSel    = document.getElementById('ef-ket').value;
    let   keterangan = ketSel;

    if (ketSel === 'Lainnya') {
        keterangan = document.getElementById('ef-ket-lainnya').value.trim();
        if (!keterangan) {
            showToast('⚠️ Isi keterangan lainnya terlebih dahulu.', 'error');
            return;
        }
    }
    if (!keterangan) {
        showToast('⚠️ Pilih keterangan terlebih dahulu.', 'error');
        return;
    }
    if (!namaObat) {
        showToast('⚠️ Nama obat wajib diisi.', 'error');
        return;
    }

    const btn  = document.getElementById('editSubmitBtn');
    const text = document.getElementById('editSubmitBtnText');
    btn.disabled = true;
    text.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px"></span> Menyimpan...';

    try {
        const fd = new FormData();
        fd.append('action',     'edit');
        fd.append('id',         id);
        fd.append('tanggal',    tanggal);
        fd.append('nama_obat',  namaObat);
        fd.append('keterangan', keterangan);
        fd.append('csrf_token', csrfToken());

        const res  = await fetch('api.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        showToast('✅ Data berhasil diperbarui.', 'success');
        closeEditModal();
        loadList(currentPage, currentSearch);
    } catch (err) {
        showToast('❌ Gagal: ' + err.message, 'error');
    } finally {
        btn.disabled = false;
        text.innerHTML = '💾 Simpan Perubahan';
    }
}

// ── AUTH ───────────────────────────────────
async function doLogin() {
    console.log('[Auth] Memulai login...');
    const pinInput = document.getElementById('pinInput');
    const pin = pinInput.value;
    const btn = document.getElementById('btnLogin');
    if (!pin) { alert('Masukkan PIN terlebih dahulu.'); return; }

    btn.disabled = true;
    btn.textContent = '⏳ Cek PIN...';

    try {
        const params = new URLSearchParams();
        params.append('action', 'login');
        params.append('password', pin);
        params.append('csrf_token', csrfToken());

        console.log('[Auth] Mengirim request ke ./api.php');
        const res = await fetch('./api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        });

        console.log('[Auth] Response HTTP:', res.status, res.statusText);

        if (!res.ok) {
            const text = await res.text();
            console.error('[Auth] Server Error Body:', text);
            throw new Error(`Koneksi server gagal (${res.status}). Hubungi admin.`);
        }

        const text = await res.text();
        console.log('[Auth] Raw Response:', text);

        let json;
        try {
            json = JSON.parse(text);
        } catch (e) {
            console.error('[Auth] JSON Parse Error:', e);
            throw new Error('Format respon server tidak valid (bukan JSON).');
        }

        if (json.success) {
            console.log('[Auth] Login BERHASIL, memuat ulang halaman...');
            location.reload();
        } else {
            console.warn('[Auth] Login GAGAL:', json.message);
            throw new Error(json.message || 'PIN yang dimasukkan salah.');
        }
    } catch (e) {
        console.error('[Auth] Terjadi Error:', e);
        alert('Login Gagal: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Masuk';
        pinInput.focus();
    }
}

async function doLogout() {
    if (!confirm('Logout dari aplikasi?')) return;
    const fd = new FormData();
    fd.append('action', 'logout');
    fd.append('csrf_token', csrfToken());
    await fetch('api.php', { method: 'POST', body: fd });
    location.reload();
}

// ── CSRF ────────────────────────────────────
function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.content : '';
}

// ── BACKUP / RESTORE ────────────────────────
function openBackupModal() {
    const overlay = document.getElementById('backupModalOverlay');
    if (overlay) overlay.classList.add('open');
    loadBackupList();
}

function closeBackupModal() {
    const overlay = document.getElementById('backupModalOverlay');
    if (overlay) overlay.classList.remove('open');
}

async function createBackup() {
    const btn = document.getElementById('btnCreateBackup');
    const text = document.getElementById('btnCreateBackupText');
    if (!btn || !text) return;

    btn.disabled = true;
    text.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px"></span> Membuat...';

    try {
        const fd = new FormData();
        fd.append('action', 'backup_create');
        fd.append('csrf_token', csrfToken());

        const res = await fetch('api.php', { method: 'POST', body: fd });
        const json = await res.json();

        if (!json.success) throw new Error(json.message);

        showToast('✅ ' + json.message, 'success');
        loadBackupList();
    } catch (e) {
        showToast('❌ Gagal membuat backup: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        text.innerHTML = '💾 Buat Backup';
    }
}

async function loadBackupList() {
    const tbody = document.getElementById('backupTableBody');
    const empty = document.getElementById('backupEmpty');
    if (!tbody) return;

    tbody.innerHTML = `<tr class="loading-row"><td colspan="4"><span class="spinner"></span>Memuat daftar backup...</td></tr>`;
    if (empty) empty.style.display = 'none';

    try {
        const res = await fetch('api.php?action=backup_list');
        const json = await res.json();

        if (!json.success) throw new Error(json.message);

        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = '';
            if (empty) empty.style.display = 'block';
            return;
        }

        if (empty) empty.style.display = 'none';

        tbody.innerHTML = json.data.map(item => {
            const sizeKB = (item.size / 1024).toFixed(1);
            return `
            <tr>
              <td style="font-family: monospace; font-size: 12px;">${escHtml(item.filename)}</td>
              <td style="text-align: center; color: var(--text-muted); font-size: 12px;">${sizeKB} KB</td>
              <td style="text-align: center; color: var(--text-muted); font-size: 12px;">${escHtml(item.created_at)}</td>
              <td style="text-align: center;">
                <div style="display: flex; gap: 6px; justify-content: center; align-items: center;">
                  <a href="api.php?action=backup_download&file=${encodeURIComponent(item.filename)}" class="btn-edit" title="Download" style="width:30px;height:30px;padding:0">⬇️</a>
                  <button class="btn-check" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3)" data-action="restore" data-file="${escHtml(item.filename)}" title="Restore"><span style="color:var(--danger)">🔄</span></button>
                  <button class="btn-check" style="background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.3)" data-action="delete_backup" data-file="${escHtml(item.filename)}" title="Hapus"><span style="color:var(--danger)">🗑️</span></button>
                </div>
              </td>
            </tr>`;
        }).join('');

        // Attach restore button handlers (event delegation)
        tbody.querySelectorAll('[data-action="restore"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const file = this.dataset.file;
                confirmRestore(file);
            });
        });

        // Attach delete backup button handlers
        tbody.querySelectorAll('[data-action="delete_backup"]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const file = this.dataset.file;
                confirmDeleteBackup(file);
            });
        });

    } catch (e) {
        tbody.innerHTML = `<tr class="loading-row"><td colspan="4" style="color:var(--danger)">❌ Gagal: ${e.message}</td></tr>`;
    }
}

function confirmRestore(filename) {
    confirmCallback = async () => {
        const btn = document.querySelector('[data-file="' + filename + '"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px"></span>';
        }

        try {
            const fd = new FormData();
            fd.append('action', 'backup_restore');
            fd.append('file', filename);
            fd.append('csrf_token', csrfToken());

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (!json.success) throw new Error(json.message);

            showToast('✅ ' + json.message, 'success');
            closeConfirm();

            if (json.reload) {
                // Tunggu sebentar lalu reload halaman
                setTimeout(() => location.reload(), 800);
            }
        } catch (e) {
            showToast('❌ Gagal restore: ' + e.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<span style="color:var(--danger)">🔄</span>';
            }
        }
    };

    const msgEl = document.getElementById('confirmMsg');
    if (msgEl) msgEl.textContent = `Pulihkan database dari backup "${filename}"? Perubahan saat ini akan HILANG permanen.`;

    const overlay = document.getElementById('confirmOverlay');
    if (overlay) overlay.classList.add('open');

    const okBtn = document.getElementById('confirmOkBtn');
    if (okBtn) {
        okBtn.onclick = confirmCallback;
        setTimeout(() => okBtn.focus(), 100);
    }
}

function confirmDeleteBackup(filename) {
    confirmCallback = async () => {
        const btn = document.querySelector('[data-action="delete_backup"][data-file="' + filename + '"]');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner" style="width:16px;height:16px;border-width:2px"></span>';
        }

        try {
            const fd = new FormData();
            fd.append('action', 'backup_delete');
            fd.append('file', filename);
            fd.append('csrf_token', csrfToken());

            const res = await fetch('api.php', { method: 'POST', body: fd });
            const json = await res.json();

            if (!json.success) throw new Error(json.message);

            showToast('✅ ' + json.message, 'success');
            closeConfirm();
            loadBackupList();
        } catch (e) {
            showToast('❌ Gagal hapus: ' + e.message, 'error');
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<span style="color:var(--danger)">🗑️</span>';
            }
        }
    };

    const msgEl = document.getElementById('confirmMsg');
    if (msgEl) msgEl.textContent = `Hapus backup "${filename}"? Tindakan ini tidak bisa dibatalkan.`;

    const overlay = document.getElementById('confirmOverlay');
    if (overlay) overlay.classList.add('open');

    const okBtn = document.getElementById('confirmOkBtn');
    if (okBtn) {
        okBtn.onclick = confirmCallback;
        setTimeout(() => okBtn.focus(), 100);
    }
}

// ── EVENT LISTENERS ───────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Pin Input Enter Key
    const pinInput = document.getElementById('pinInput');
    if (pinInput) {
        pinInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') doLogin();
        });
    }

    // Modal Background Click
    const modalOverlay = document.getElementById('modalOverlay');
    if (modalOverlay) {
        modalOverlay.addEventListener('click', function (e) {
            if (e.target === this) closeModal();
        });
    }

    // Aksi tombol tabel & kartu mobile via event delegation
    const tbody = document.getElementById('tableBody');
    if (tbody) tbody.addEventListener('click', handleActionClick);

    const mList = document.getElementById('mobileCardList');
    if (mList) {
        mList.addEventListener('click', handleActionClick);
        mList.addEventListener('click', handleCardClick);
    }

    // Checkbox seleksi (desktop & mobile)
    document.addEventListener('change', e => {
        const cb = e.target.closest('.row-cb');
        if (cb) toggleRow(cb);
    });

    // Global Keyboard Listeners
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeModal();
            closeEditModal();
            closeConfirm();
        }
        // Jika pop-up konfirmasi terbuka dan menekan Enter
        if (e.key === 'Enter') {
            const confirmOverlay = document.getElementById('confirmOverlay');
            if (confirmOverlay && confirmOverlay.classList.contains('open') && typeof confirmCallback === 'function') {
                e.preventDefault();
                confirmCallback();
            }
        }
    });

    // Tanggal header di-render server-side (includes/header.php) agar konsisten di semua halaman

    // Search Input Debounce
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                loadList(1, this.value.trim());
            }, 350);
        });
    }

    // Drug Input Autocomplete
    const drugInput = document.getElementById('f-obat');
    if (drugInput) {
        drugInput.addEventListener('input', function () {
            clearTimeout(autocompleteTimer);
            const q = this.value.trim();
            if (q.length < 1) { closeAutocomplete(); updateDefectaWarning(null); return; }
            autocompleteTimer = setTimeout(() => fetchAutocomplete(q), 280);
        });

        drugInput.addEventListener('keydown', function (e) {
            const dropdown = document.getElementById('autocompleteDropdown');
            if (!dropdown) return;
            const items = dropdown.querySelectorAll('.autocomplete-item');
            if (!dropdown.classList.contains('open') || items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                acIndex = Math.min(acIndex + 1, items.length - 1);
                items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                acIndex = Math.max(acIndex - 1, -1);
                items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
            } else if (e.key === 'Enter' && acIndex >= 0) {
                e.preventDefault();
                selectAc(acIndex);
            } else if (e.key === 'Escape') {
                closeAutocomplete();
            }
        });

        drugInput.addEventListener('blur', () => { setTimeout(closeAutocomplete, 200); });
    }
});
