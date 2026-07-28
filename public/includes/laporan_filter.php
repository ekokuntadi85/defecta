<div class="filter-panel">
  <h2 class="filter-title">📊 Laporan Riwayat</h2>
  
  <div class="filter-mode-group">
    <button class="filter-mode-btn active" data-mode="semua" onclick="setReportMode('semua', this)">📋 Semua</button>
    <button class="filter-mode-btn" data-mode="defecta" onclick="setReportMode('defecta', this)">🔴 Defecta</button>
    <button class="filter-mode-btn" data-mode="tersedia" onclick="setReportMode('tersedia', this)">✅ Tersedia</button>
  </div>

  <div class="filter-date-row">
    <div class="filter-date-field">
      <label class="filter-label">Dari</label>
      <input type="date" id="dateFrom" class="filter-date-input" value="<?= date('Y-m-01') ?>">
    </div>
    <div class="filter-date-field">
      <label class="filter-label">Sampai</label>
      <input type="date" id="dateTo" class="filter-date-input" value="<?= date('Y-m-d') ?>">
    </div>
  </div>

  <div class="filter-search-row">
    <div class="filter-search-field">
      <span class="search-ico">🔍</span>
      <input type="text" id="reportSearch" class="filter-search-input" placeholder="Cari nama obat..." autocomplete="off">
    </div>
  </div>

  <button class="btn btn-primary filter-submit-btn" onclick="loadReport(1)">
    📊 Tampilkan Laporan
  </button>
</div>
