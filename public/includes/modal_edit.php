<div class="modal-overlay" id="editModalOverlay">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">✏️ Edit Data Defecta</h2>
      <button class="close-btn" onclick="closeEditModal()">✕</button>
    </div>
    <form id="editForm" onsubmit="submitEdit(event)">
      <input type="hidden" id="ef-id">
      <div class="form-group">
        <label class="form-label">Tanggal</label>
        <input type="date" id="ef-tanggal" class="form-control" required>
      </div>

      <div class="form-group">
        <label class="form-label">Nama Obat</label>
        <input type="text" id="ef-obat" class="form-control" placeholder="Nama obat..." autocomplete="off" required maxlength="255">
      </div>

      <div class="form-group">
        <label class="form-label">Keterangan</label>
        <select id="ef-ket" class="form-control" onchange="onEditKetChange(this)" required>
          <option value="">-- Pilih --</option>
          <option value="Stock Menipis">Stock Menipis</option>
          <option value="Stock Habis">Stock Habis</option>
          <option value="Penolakan">Penolakan</option>
          <option value="Lainnya">Lainnya...</option>
        </select>
        <div id="efKetLainnyaWrap" class="ket-lainnya-wrap">
          <input type="text" id="ef-ket-lainnya" class="form-control" style="margin-top:8px" placeholder="Tulis keterangan lainnya..." maxlength="500">
        </div>
      </div>

      <div class="form-group">
        <button type="submit" class="btn btn-primary btn--full-wide" id="editSubmitBtn">
          <span id="editSubmitBtnText">💾 Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>
