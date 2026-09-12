<div class="modal-overlay" id="backupModalOverlay">
  <div class="modal" style="max-width: 560px;">
    <div class="modal-header">
      <h2 class="modal-title">💾 Backup & Restore Database</h2>
      <button class="close-btn" onclick="closeBackupModal()">✕</button>
    </div>
    <div style="display: flex; flex-direction: column; gap: 16px;">

      <!-- Create Backup Section -->
      <div style="padding: 16px; background: var(--surface2); border-radius: 8px; border: 1px solid var(--border);">
        <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 10px; color: var(--text);">Buat Backup Baru</h3>
        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Membuat salinan terkompresi (bzip2) dari database SQLite saat ini.</p>
        <button class="btn btn-primary btn--full-wide" id="btnCreateBackup" onclick="createBackup()">
          <span id="btnCreateBackupText">💾 Buat Backup</span>
        </button>
      </div>

      <!-- Upload & Restore Section -->
      <div style="padding: 16px; background: var(--surface2); border-radius: 8px; border: 1px dashed var(--border);">
        <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 10px; color: var(--text);">Pulihkan dari File yang Di-download</h3>
        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">Upload file backup <code>.sqlite.bz2</code> dari komputer Anda untuk memulihkan database.</p>
        <form id="restoreUploadForm" style="display: flex; flex-direction: column; gap: 10px;">
          <div style="display: flex; gap: 8px; align-items: center;">
            <label for="backupFileInput" class="btn btn--outline" style="flex-shrink: 0; cursor: pointer; padding: 10px 16px; font-size: 13px;">
              📎 Pilih File
            </label>
            <span id="backupFileName" style="font-size: 13px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
              Belum ada file
            </span>
          </div>
          <input type="file" name="backup_file" id="backupFileInput" accept=".sqlite.bz2,.bz2" required style="display: none;">
          <button type="button" class="btn btn--full-wide" id="btnRestoreUpload" onclick="restoreUpload()">
            <span id="btnRestoreUploadText">🔄 Pulihkan dari File</span>
          </button>
        </form>
      </div>

      <!-- Backup List Section -->
        <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 10px; color: var(--text);">Daftar Backup</h3>
        <div class="table-wrap" style="max-height: 300px; overflow-y: auto;">
          <table>
            <thead>
              <tr>
                <th style="width: 35%;">Nama File</th>
                <th style="width: 15%; text-align: center;">Ukuran</th>
                <th style="width: 25%; text-align: center;">Tanggal</th>
                <th style="width: 25%; text-align: center;">Aksi</th>
              </tr>
            </thead>
            <tbody id="backupTableBody">
              <tr class="loading-row"><td colspan="4"><span class="spinner"></span>Memuat daftar backup...</td></tr>
            </tbody>
          </table>
        </div>
        <div id="backupEmpty" style="display: none; text-align: center; padding: 32px; color: var(--text-muted);">
          <div style="font-size: 32px; margin-bottom: 8px;">📭</div>
          <div>Belum ada backup. Klik "Buat Backup" untuk memulai.</div>
        </div>
      </div>

    </div>
  </div>
</div>