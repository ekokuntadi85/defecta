// ── LAPORAN STATE ──────────────────────────
let reportMode = 'semua';
let reportPage = 1;
let reportSearch = '';

// ── MODE TOGGLE ───────────────────────────
function setReportMode(mode, btn) {
    reportMode = mode;
    document.querySelectorAll('.filter-mode-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// ── LOAD REPORT ───────────────────────────
async function loadReport(page = 1) {
    reportPage = page;
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    reportSearch = (document.getElementById('reportSearch')?.value || '').trim();

    if (!dateFrom || !dateTo) {
        alert('Pilih tanggal Dari dan Sampai terlebih dahulu.');
        return;
    }
    if (dateFrom > dateTo) {
        alert('Tanggal "Dari" tidak boleh lebih besar dari "Sampai".');
        return;
    }

    // Show result area
    document.getElementById('reportResultWrap').style.display = 'block';
    document.getElementById('reportEmpty').style.display = 'none';

    const tbody = document.getElementById('reportTableBody');
    const mCards = document.getElementById('reportMobileCards');
    tbody.innerHTML = `<tr class="loading-row"><td colspan="5"><span class="spinner"></span>Memuat data...</td></tr>`;
    mCards.innerHTML = `<div style="text-align:center;padding:40px;opacity:.6;"><span class="spinner"></span></div>`;

    try {
        // Fetch data + summary in parallel
        const params = `mode=${reportMode}&date_from=${dateFrom}&date_to=${dateTo}&search=${encodeURIComponent(reportSearch)}`;
        const [dataRes, summaryRes] = await Promise.all([
            fetch(`api.php?action=report&page=${page}&${params}`),
            fetch(`api.php?action=report_summary&${params}`)
        ]);

        const dataJson = await dataRes.json();
        const summaryJson = await summaryRes.json();

        if (!dataJson.success) throw new Error(dataJson.message);

        // Render summary
        renderSummary(summaryJson);

        // Render table
        if (dataJson.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5">
                <div class="empty-state" style="padding:40px">
                    <div class="empty-icon">📭</div>
                    <h3>Tidak ada data</h3>
                    <p>Tidak ditemukan item dalam rentang tanggal yang dipilih.</p>
                </div>
            </td></tr>`;
            mCards.innerHTML = `<div class="empty-state"><div class="empty-icon">📭</div><h3>Tidak ada data</h3></div>`;
            document.getElementById('reportPagination').innerHTML = '';
            return;
        }

        renderReportTable(dataJson.data, dataJson.page, dataJson.limit);
        renderReportPagination(dataJson.page, dataJson.pages, dataJson.total);

    } catch (e) {
        tbody.innerHTML = `<tr class="loading-row"><td colspan="5" style="color:var(--danger)">❌ Gagal: ${e.message}</td></tr>`;
        mCards.innerHTML = `<div style="color:var(--danger);text-align:center;padding:20px;">❌ ${e.message}</div>`;
    }
}

// ── RENDER SUMMARY ────────────────────────
function renderSummary(json) {
    if (!json.success) return;

    document.getElementById('summaryCards').style.display = 'flex';
    document.getElementById('sumTotal').textContent = json.total;
    document.getElementById('sumDefecta').textContent = json.total_defecta;
    document.getElementById('sumTersedia').textContent = json.total_tersedia;

    // Top defecta
    const panel = document.getElementById('topDefectaPanel');
    const list = document.getElementById('topDefectaList');
    if (json.top_defecta && json.top_defecta.length > 0) {
        panel.style.display = 'block';
        list.innerHTML = json.top_defecta.map((item, i) => `
            <div class="top-defecta-item">
                <span class="top-rank">${i + 1}</span>
                <span class="top-name">${escHtml(item.nama_obat)}</span>
                <span class="top-count">${item.jumlah}×</span>
            </div>
        `).join('');
    } else {
        panel.style.display = 'none';
    }
}

// ── RENDER TABLE ──────────────────────────
function renderReportTable(rows, page, limit) {
    const tbody = document.getElementById('reportTableBody');
    const mCards = document.getElementById('reportMobileCards');

    const htmlRows = rows.map((row, idx) => {
        const no = (page - 1) * limit + idx + 1;
        const tgl = formatDateLaporan(row.status === 'tersedia' ? row.updated_at?.split(' ')[0] : row.tanggal);
        const statusClass = row.status === 'defecta' ? 'badge-status-defecta' : 'badge-status-tersedia';
        const statusLabel = row.status === 'defecta' ? 'Defecta' : 'Tersedia';

        let ketClass = 'ket-lain';
        if (row.keterangan === 'Stock Habis') ketClass = 'ket-habis';
        else if (row.keterangan === 'Penolakan') ketClass = 'ket-tolak';

        return {
            html: `
            <tr>
              <td style="text-align:center;color:var(--text-muted)">${no}</td>
              <td class="td-date">${tgl}</td>
              <td class="td-drug">${escHtml(row.nama_obat)}</td>
              <td><span class="badge-ket ${ketClass}">${escHtml(row.keterangan)}</span></td>
              <td style="text-align:center"><span class="badge-status ${statusClass}">${statusLabel}</span></td>
            </tr>`,
            card: `
            <div class="mobile-card">
              <div class="mobile-card-header">
                <div class="mobile-card-title">${escHtml(row.nama_obat)}</div>
                <span class="badge-status ${statusClass}" style="font-size:11px">${statusLabel}</span>
              </div>
              <div class="mobile-card-meta">
                <span>📅 ${tgl}</span>
                <span class="badge-ket ${ketClass}" style="font-size:10px;padding:2px 8px">${escHtml(row.keterangan)}</span>
              </div>
            </div>`
        };
    });

    tbody.innerHTML = htmlRows.map(r => r.html).join('');
    mCards.innerHTML = htmlRows.map(r => r.card).join('');
}

// ── RENDER PAGINATION ─────────────────────
function renderReportPagination(page, pages, total) {
    const el = document.getElementById('reportPagination');
    if (!el) return;
    if (pages <= 1) { el.innerHTML = `<span class="page-info">Total: ${total}</span>`; return; }

    let html = '';
    html += `<button class="page-btn" onclick="loadReport(${page - 1})" ${page <= 1 ? 'disabled' : ''}>‹ Prev</button>`;

    let start = Math.max(1, page - 2);
    let end = Math.min(pages, page + 2);
    if (start > 1) html += `<button class="page-btn" onclick="loadReport(1)">1</button>${start > 2 ? '<span class="page-info">…</span>' : ''}`;

    for (let i = start; i <= end; i++) {
        html += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="loadReport(${i})">${i}</button>`;
    }
    if (end < pages) html += `${end < pages - 1 ? '<span class="page-info">…</span>' : ''}<button class="page-btn" onclick="loadReport(${pages})">${pages}</button>`;

    html += `<button class="page-btn" onclick="loadReport(${page + 1})" ${page >= pages ? 'disabled' : ''}>Next ›</button>`;
    html += `<span class="page-info">Total: ${total}</span>`;
    el.innerHTML = html;
}

// ── DATE FORMATTER ────────────────────────
function formatDateLaporan(str) {
    if (!str) return '–';
    const d = new Date(str + 'T00:00:00');
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

// ── UTIL ──────────────────────────────────
function escHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── CETAK LAPORAN ─────────────────────────
async function printReportLaporan() {
    const btn = document.getElementById('btnCetakLaporan');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Menyiapkan...'; }

    try {
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;
        const search = (document.getElementById('reportSearch')?.value || '').trim();
        const params = `mode=${reportMode}&date_from=${dateFrom}&date_to=${dateTo}&search=${encodeURIComponent(search)}`;

        // Fetch ALL data (no pagination)
        const res = await fetch(`api.php?action=report&page=1&${params}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message);

        // Fetch all pages if needed
        let allRows = json.data;
        if (json.pages > 1) {
            for (let p = 2; p <= json.pages; p++) {
                const r = await fetch(`api.php?action=report&page=${p}&${params}`);
                const j = await r.json();
                if (j.success) allRows = allRows.concat(j.data);
            }
        }

        const modeLabels = { defecta: 'Defecta', tersedia: 'Tersedia', semua: 'Semua' };
        const modeLabel = modeLabels[reportMode] || 'Semua';
        const now = new Date();
        const tglCetak = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        const jamCetak = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        const fromFormatted = formatDateLaporan(dateFrom);
        const toFormatted = formatDateLaporan(dateTo);

        const tableRows = allRows.map((r, i) => {
            const tgl = r.status === 'tersedia'
                ? formatDateLaporan(r.updated_at?.split(' ')[0])
                : formatDateLaporan(r.tanggal);
            const status = r.status === 'defecta' ? 'Defecta' : 'Tersedia';
            return `
            <tr>
              <td style="text-align:center;border:1px solid #000;">${i + 1}</td>
              <td style="border:1px solid #000;">${tgl}</td>
              <td style="font-weight:600;border:1px solid #000;">${r.nama_obat}</td>
              <td style="border:1px solid #000;">${r.keterangan}</td>
              <td style="text-align:center;border:1px solid #000;">${status}</td>
            </tr>`;
        }).join('');

        const html = `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Laporan Riwayat - Apotek Mentari Farma Bondowoso</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; color:#000; background:#fff; padding:40px; }
    .header-print { border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:flex-end; }
    .apotek-name { font-size:20px; font-weight:800; }
    .apotek-sub { font-size:12px; margin-top:2px; }
    .report-title { font-size:18px; font-weight:700; text-align:center; margin-bottom:16px; text-transform:uppercase; }
    .meta-bar { margin-bottom:15px; display:flex; justify-content:space-between; font-size:12px; flex-wrap:wrap; gap:8px; }
    table { width:100%; border-collapse:collapse; font-size:12px; margin-bottom:30px; }
    thead th { padding:8px 10px; text-align:left; font-weight:700; border:1px solid #000; background:#f2f2f2; }
    tbody td { padding:8px 10px; border:1px solid #000; vertical-align:middle; }
    .footer { display:flex; justify-content:space-between; align-items:flex-start; font-size:11px; }
    .sign-area { text-align:center; width:200px; }
    .sign-line { border-top:1px solid #000; width:100%; margin:60px auto 5px; }
    @media print { body { padding:0; } .no-print { display:none !important; } @page { size:A4; margin:1.5cm; } }
    .print-btn-bar { background:#f2f2f2; padding:10px 20px; display:flex; gap:10px; margin-bottom:20px; border:1px solid #ccc; }
    .print-btn { padding:6px 15px; background:#000; color:#fff; border:none; border-radius:4px; font-size:13px; font-weight:700; cursor:pointer; }
    .close-btn-pr { padding:6px 15px; background:#fff; color:#000; border:1px solid #000; border-radius:4px; font-size:13px; cursor:pointer; }
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
    <div style="text-align:right;font-size:11px">Dicetak pada: ${tglCetak}, ${jamCetak} WIB</div>
  </div>
  <div class="report-title">Laporan Riwayat Defecta</div>
  <div class="meta-bar">
    <span><strong>Periode:</strong> ${fromFormatted} — ${toFormatted}</span>
    <span><strong>Mode:</strong> ${modeLabel}</span>
    <span><strong>Total:</strong> ${allRows.length} item</span>
    ${search ? `<span><strong>Filter:</strong> "${search}"</span>` : ''}
  </div>
  <table>
    <thead><tr>
      <th style="width:40px;text-align:center">#</th>
      <th style="width:120px">Tanggal</th>
      <th>Nama Obat</th>
      <th style="width:140px">Keterangan</th>
      <th style="width:90px;text-align:center">Status</th>
    </tr></thead>
    <tbody>${tableRows}</tbody>
  </table>
  <div class="footer">
    <div>Sistem Defecta Obat &mdash; Apotek Mentari Farma Bondowoso</div>
    <div class="sign-area">
      <div>Mengetahui,</div>
      <div class="sign-line"></div>
      <div>Apoteker Penanggung Jawab</div>
    </div>
  </div>
</body></html>`;

        const win = window.open('', '_blank', 'width=900,height=700');
        win.document.write(html);
        win.document.close();
    } catch (e) {
        alert('Gagal menyiapkan laporan: ' + e.message);
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '🖨️ Cetak Laporan'; }
    }
}

// ── INIT ──────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    // Search debounce
    const searchInput = document.getElementById('reportSearch');
    let debounce;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                if (document.getElementById('reportResultWrap').style.display !== 'none') {
                    loadReport(1);
                }
            }, 400);
        });
    }

    // Escape key
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { /* nothing special for laporan page */ }
    });
});
