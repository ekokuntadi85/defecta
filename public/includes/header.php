<?php
// Tentukan halaman aktif (default: beranda)
$_active = $headerActivePage ?? 'beranda';
$_aClass = fn(string $p): string => $_active === $p ? 'active' : '';
?>
<header class="header">
  <a href="index.php" class="header-brand">
    <div class="header-icon">💊</div>
    <div class="header-info">
      <h1>Software Defecta</h1>
      <p>Apotek Mentari Farma Bondowoso</p>
    </div>
  </a>
  <div class="header-right">
    <a href="index.php" class="header-nav-link <?= $_aClass('beranda') ?>">🏠 Beranda</a>
    <a href="laporan.php" class="header-nav-link <?= $_aClass('laporan') ?>">📊 Laporan</a>
    <div class="badge-date" id="header-date">
      <span>📅</span>
      <span id="dateText">Memuat...</span>
    </div>
    <button class="logout-btn" onclick="doLogout()" title="Logout">🚪</button>
  </div>
</header>
