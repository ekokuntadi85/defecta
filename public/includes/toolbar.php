<div class="toolbar">
  <div class="search-wrap" id="searchWrap">
    <span class="search-ico">🔍</span>
    <input type="text" id="searchInput" placeholder="Cari nama obat..." autocomplete="off">
  </div>
  <div class="toolbar-actions">
    <select id="sortSelect" onchange="applySort(this.value)" title="Urutkan">
      <option value="id_desc">Default (Terbaru)</option>
      <option value="permintaan_count_desc">Paling Banyak Dicari</option>
      <option value="permintaan_count_asc">Paling Sedikit Dicari</option>
      <option value="tanggal_desc">Tanggal Terbaru</option>
      <option value="tanggal_asc">Tanggal Terlama</option>
      <option value="nama_obat_asc">Nama Obat (A-Z)</option>
    </select>
    <button class="btn btn-print" onclick="printReport()" id="btnCetak" title="Cetak / Ekspor PDF">
      🖨️ Cetak PDF
    </button>
    <button class="btn btn-primary" onclick="openModal()" id="btnTambah">
      ➕ Tambah Defecta
    </button>
  </div>
</div>
