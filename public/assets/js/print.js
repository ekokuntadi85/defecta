// ── SHARED PRINT HTML GENERATOR ─────────────────────────────
/**
 * Builds a complete HTML document for printing reports.
 * @param {Object} opts
 * @param {string} opts.title - Document title
 * @param {Array<Object>} opts.rows - Data rows (each row is an object with keys matching columns)
 * @param {Array<{key:string, header:string, width?:string, align?:string}>} opts.columns - Column definitions
 * @param {Object} opts.meta - Metadata for header/footer
 *   @param {number} opts.meta.total - Total row count
 *   @param {string} opts.meta.filter - Filter description
 *   @param {string} opts.meta.printedAt - Formatted print timestamp
 *   @param {string} opts.meta.apotekName - Apotek name
 *   @param {string} opts.meta.apotekSub - Apotek sub/location
 * @param {string} opts.modeLabel - Report mode label (e.g., "Daftar Obat Defecta")
 * @param {string} [opts.dateFrom] - Date range from (for laporan)
 * @param {string} [opts.dateTo] - Date range to (for laporan)
 * @returns {string} Complete HTML document
 */
function generatePrintHTML({ title, rows, columns, meta, modeLabel, dateFrom, dateTo }) {
    const {
        total = rows.length,
        filter = 'Semua data',
        printedAt = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) + ', ' +
                   new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB',
        apotekName = 'Apotek Mentari Farma',
        apotekSub = 'Bondowoso, Jawa Timur'
    } = meta || {};

    // Build table header
    const theadHtml = `
    <thead>
      <tr>
        ${columns.map(col => `
        <th style="${col.width ? 'width:' + col.width + ';' : ''}${col.align ? 'text-align:' + col.align + ';' : ''}"
            ${col.width ? '' : ''}>${col.header}</th>`).join('')}
      </tr>
    </thead>`;

    // Build table body
    const tbodyHtml = `
    <tbody>
      ${rows.map((r, i) => `
      <tr>
        ${columns.map(col => {
            const val = r[col.key] ?? '';
            const align = col.align ? 'text-align:' + col.align + ';' : '';
            return `<td style="${align}border:1px solid #000;padding:8px 10px;">${val}</td>`;
        }).join('')}
      </tr>`).join('')}
    </tbody>`;

    // Build meta bar
    const metaParts = [
        `<span><strong>Total:</strong> ${total} item</span>`,
        `<span><strong>Filter:</strong> ${filter}</span>`
    ];
    if (dateFrom && dateTo) {
        metaParts.unshift(`<span><strong>Periode:</strong> ${dateFrom} — ${dateTo}</span>`);
    }
    if (modeLabel) {
        metaParts.splice(1, 0, `<span><strong>Mode:</strong> ${modeLabel}</span>`);
    }
    const metaBarHtml = `<div class="meta-bar">${metaParts.join('')}</div>`;

    return `<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>${title}</title>
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; color:#000; background:#fff; padding:40px; }
    .header-print { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end; }
    .apotek-name { font-size: 20px; font-weight:800; }
    .apotek-sub  { font-size: 12px; margin-top:2px; }
    .report-title { font-size:18px; font-weight:700; text-align:center; margin-bottom:20px; text-transform:uppercase; }
    .meta-bar { margin-bottom: 15px; display:flex; justify-content:space-between; font-size:12px; flex-wrap:wrap; gap:8px; }
    table { width:100%; border-collapse:collapse; font-size:12px; margin-bottom:30px; }
    thead th { padding:8px 10px; text-align:left; font-weight:700; border:1px solid #000; background:#f2f2f2; }
    tbody td { padding:8px 10px; border:1px solid #000; vertical-align:middle; }
    .footer { display:flex; justify-content:space-between; align-items:flex-start; font-size:11px; }
    .sign-area { text-align:center; width:200px; }
    .sign-line { border-top:1px solid #000; width:100%; margin:60px auto 5px; }
    @media print { body { padding: 0; } .no-print { display:none !important; } @page { size:A4; margin:1.5cm; } }
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
      <div class="apotek-name">${apotekName}</div>
      <div class="apotek-sub">${apotekSub}</div>
    </div>
    <div style="text-align:right;font-size:11px">Dicetak pada: ${printedAt}</div>
  </div>
  <div class="report-title">${modeLabel}</div>
  ${metaBarHtml}
  <table>${theadHtml}${tbodyHtml}</table>
  <div class="footer">
    <div>Sistem Defecta Obat &mdash; ${apotekName} ${apotekSub}</div>
    <div class="sign-area">
      <div>Mengetahui,</div>
      <div class="sign-line"></div>
      <div>Apoteker Penanggung Jawab</div>
    </div>
  </div>
</body>
</html>`;
}

// ── OPEN PRINT WINDOW ─────────────────────────────
/**
 * Opens a new window with the generated print HTML.
 * @param {string} html - The HTML document string from generatePrintHTML()
 */
function openPrintWindow(html) {
    const win = window.open('', '_blank', 'width=900,height=700');
    win.document.write(html);
    win.document.close();
}