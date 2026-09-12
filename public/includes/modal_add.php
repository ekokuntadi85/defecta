<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">💾 Tambah Data Defecta</h2>
      <button class="close-btn" onclick="closeModal()">✕</button>
    </div>
    <form id="addForm" onsubmit="submitForm(event)">
      <div class="form-group">
        <label class="form-label">Tanggal</label>
        <input type="date" id="f-tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
      </div>
      
      <div class="form-group">
        <label class="form-label">Nama Obat</label>
        <div class="drug-search-wrap">
          <input type="text" id="f-obat" class="drug-input-field" placeholder="Ketik nama obat..." autocomplete="off" required>
          <div id="autocompleteDropdown" class="autocomplete-dropdown"></div>
        </div>
        <div id="dupeWarn" class="dupe-warn" style="display:none"></div>
      </div>

      <div class="form-group">
        <label class="form-label">Keterangan</label>
        <select id="f-ket" class="form-control" onchange="onKetChange(this)" required>
          <option value="">-- Pilih --</option>
          <option value="Stock Menipis">Stock Menipis</option>
          <option value="Stock Habis">Stock Habis</option>
          <option value="Penolakan">Penolakan</option>
          <option value="Lainnya">Lainnya...</option>
        </select>
        <div id="ketLainnyaWrap" class="ket-lainnya-wrap">
          <input type="text" id="f-ket-lainnya" class="form-control" style="margin-top:8px" placeholder="Tulis keterangan lainnya...">
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
