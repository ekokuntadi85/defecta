<div class="modal-overlay" id="modalOverlay" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="modalTitle">💾 Tambah Data Defecta</h2>
      <button class="close-btn" onclick="closeModal()" aria-label="Tutup modal">✕</button>
    </div>
    <form id="addForm" onsubmit="submitForm(event)">
      <div class="form-group">
        <label class="form-label" for="f-tanggal">Tanggal</label>
        <input type="date" id="f-tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required aria-required="true">
      </div>
      
      <div class="form-group">
        <label class="form-label" for="f-obat">Nama Obat</label>
        <div class="drug-search-wrap">
          <input type="text" id="f-obat" class="drug-input-field" placeholder="Ketik nama obat..." autocomplete="off" required aria-required="true" aria-describedby="dupeWarn" aria-autocomplete="list" aria-controls="autocompleteDropdown" aria-expanded="false" role="combobox">
          <div id="autocompleteDropdown" class="autocomplete-dropdown" role="listbox"></div>
        </div>
        <div id="dupeWarn" class="dupe-warn" style="display:none" role="alert"></div>
      </div>

      <div class="form-group">
        <label class="form-label" for="f-ket">Keterangan</label>
        <select id="f-ket" class="form-control" onchange="onKetChange(this)" required aria-required="true">
          <option value="">-- Pilih --</option>
          <option value="Stock Menipis">Stock Menipis</option>
          <option value="Stock Habis">Stock Habis</option>
          <option value="Penolakan">Penolakan</option>
          <option value="Lainnya">Lainnya...</option>
        </select>
        <div id="ketLainnyaWrap" class="ket-lainnya-wrap">
          <input type="text" id="f-ket-lainnya" class="form-control" style="margin-top:8px" placeholder="Tulis keterangan lainnya..." aria-label="Keterangan lainnya">
        </div>
      </div>

      <div class="form-group">
        <button type="submit" class="btn btn-primary btn--full-wide" id="submitBtn">
          <span id="submitBtnText">💾 Simpan</span>
        </button>
      </div>
    </form>
  </div>
</div>
