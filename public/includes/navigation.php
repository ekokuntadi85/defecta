<nav class="bottom-nav">
  <a class="nav-item active" href="index.php" id="navDefecta" onclick="event.preventDefault(); switchMode('defecta')">
    <i class="stat-ico">🔴</i>
    <span>Defecta</span>
  </a>
  <a class="nav-item" href="index.php" id="navRiwayat" onclick="event.preventDefault(); switchMode('riwayat')">
    <i class="stat-ico">✅</i>
    <span>Riwayat</span>
  </a>
  <a class="nav-item" href="laporan.php">
    <i class="stat-ico">📊</i>
    <span>Laporan</span>
  </a>
  <div class="nav-item" onclick="doLogout()">
    <i class="stat-ico">🚪</i>
    <span>Keluar</span>
  </div>
</nav>

<button class="fab" id="fabTambah" onclick="openModal()" title="Tambah Defecta">＋</button>
