<div class="login-overlay">
  <div class="login-box">
    <div class="login-icon">🔐</div>
    <h2 class="login-title">Akses Staf</h2>
    <p class="login-sub">Masukkan nama pengguna dan PIN Anda</p>
    <form onsubmit="event.preventDefault(); doLogin();">
      <select id="staffSelect" name="username" class="form-select" style="margin-bottom: 12px;" required>
        <?php foreach (get_staff_list() as $val => $label): ?>
          <option value="<?= htmlspecialchars($val) ?>" <?= $val === '' ? 'disabled selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="password" id="pinInput" class="pin-input" placeholder="••••••" maxlength="6" minlength="6" autofocus>
      <button type="submit" class="btn btn-primary btn-login" id="btnLogin">Masuk</button>
    </form>
  </div>
</div>
