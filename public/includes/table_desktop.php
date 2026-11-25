<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th class="td-no" style="width:40px">
          <div class="cb-wrap"><input type="checkbox" class="cb-all" id="cbAll" onchange="toggleAll(this)" title="Pilih semua"></div>
        </th>
        <th class="td-date">Tanggal</th>
        <th>Nama Obat</th>
        <th>Keterangan</th>
        <th style="width:100px;text-align:center" title="Permintaan pelanggan (klik untuk urutkan)" onclick="toggleSort('permintaan_count')">Permintaan ↕</th>
        <th style="width:110px;text-align:center;font-size:12px">Dibuat Oleh</th>
        <th style="text-align:center;width:110px" id="thAksi">Aksi</th>
      </tr>
    </thead>
    <tbody id="tableBody">
      <tr class="loading-row">
        <td colspan="7"><span class="spinner"></span>Memuat data...</td>
      </tr>
    </tbody>
  </table>
</div>
