<?php
// Tentukan halaman aktif (default: beranda)
$_active = $headerActivePage ?? 'beranda';
$_aClass = fn(string $p): string => $_active === $p ? 'active' : '';

// Tanggal hari ini (Indonesia) - dirender server-side agar tampil di semua halaman
$_hari  = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'][(int)date('w')];
$_bulan = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
           7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'][(int)date('n')];
$_tanggalHeader = $_hari . ', ' . date('j') . ' ' . $_bulan . ' ' . date('Y');
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
    <a href="#" class="header-nav-link" onclick="openBackupModal(); return false;" title="Backup & Restore Database">💾 Backup</a>
    <div class="badge-date" id="header-date" style="background: var(--surface2); padding: 4px 10px; border-radius: 6px;">
      <span>👤</span>
      <span id="staffName"><?= htmlspecialchars(ucfirst(current_staff())) ?></span>
    </div>
    <div class="badge-date">
      <span>📅</span>
      <span id="dateText"><?= $_tanggalHeader ?></span>
    </div>
    <button class="logout-btn" onclick="doLogout()" title="Logout">🚪</button>
  </div>
</header>
