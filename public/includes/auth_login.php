<div class="login-overlay">
  <div class="login-box">
    <div class="login-icon">🔐</div>
    <h2 class="login-title">Akses Staf</h2>
    <p class="login-sub">Masukkan PIN untuk mengelola stok</p>
    <form onsubmit="event.preventDefault(); doLogin();">
      <input type="password" id="pinInput" class="pin-input" placeholder="••••" maxlength="8" autofocus>
      <button type="submit" class="btn btn-primary btn-login" id="btnLogin">Masuk</button>
    </form>
  </div>
</div>
