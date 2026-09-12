<div id="reportResultWrap" style="display:none">
  <!-- Desktop Table -->
  <div class="table-wrap report-table-desktop">
    <table>
      <thead>
        <tr>
          <th style="width:40px;text-align:center">#</th>
          <th class="td-date">Tanggal</th>
          <th>Nama Obat</th>
          <th>Keterangan</th>
          <th style="text-align:center;width:100px">Status</th>
          <th style="width:120px">Dibuat Oleh</th>
        </tr>
      </thead>
      <tbody id="reportTableBody">
        <tr class="loading-row"><td colspan="6"><span class="spinner"></span>Memuat data...</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Mobile Card View -->
  <div id="reportMobileCards" class="mobile-card-list report-mobile-cards"></div>

  <!-- Pagination -->
  <div id="reportPagination" class="pagination"></div>

  <!-- Print Button -->
  <div class="report-actions">
    <button class="btn btn-print" onclick="printReportLaporan()" id="btnCetakLaporan">
      🖨️ Cetak Laporan
    </button>
  </div>
</div>

<!-- Empty State -->
<div id="reportEmpty" class="empty-state" style="display:none">
  <div class="empty-icon">📊</div>
  <h3>Belum ada data laporan</h3>
  <p>Pilih rentang tanggal dan klik "Tampilkan Laporan" untuk melihat data.</p>
</div>
