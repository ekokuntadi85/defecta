<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ── Proteksi Akses API ──────────────────────────────────────
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── CSRF GUARD ──────────────────────────────────────────────
// Semua permintaan POST wajib membawa token CSRF dari session.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Muat ulang halaman.']);
        exit;
    }
}

// ── AUTH CHECK ──────────────────────────────────────────────
if ($action === 'login') {
    // Rate limit: maksimal 5 percobaan per 15 menit per IP
    $db = getDB();
    initDB();
    $db->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now','-1 hour')");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $recent = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > datetime('now','-15 minutes')");
    $recent->execute([':ip' => $ip]);
    if ((int)$recent->fetchColumn() >= 5) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.']);
        exit;
    }

    $password = $_POST['password'] ?? '';
    if (is_string($password) && hash_equals(APP_PASSWORD, $password)) {
        session_regenerate_id(true); // cegah session fixation
        $_SESSION['logged_in'] = true;
        echo json_encode(['success' => true, 'message' => 'Login berhasil.']);
    } else {
        $ins = $db->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)");
        $ins->execute([':ip' => $ip]);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'PIN salah!']);
    }
    exit;
}

if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit;
    }
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logout berhasil.']);
    exit;
}

// Semua aksi di bawah ini butuh login
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesi habis, silakan login ulang.']);
    exit;
}

try {
    $db = getDB();
    initDB();

    switch ($action) {

        // ── DAFTAR OBAT (defecta ATAU riwayat/tersedia) ────────────
        case 'list':
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 10;
            $offset = ($page - 1) * $limit;
            $search = trim($_GET['search'] ?? '');
            $mode   = ($_GET['mode'] ?? 'defecta') === 'riwayat' ? 'tersedia' : 'defecta';

            $where  = "WHERE status = :mode";
            $params = [':mode' => $mode];

            if ($search !== '') {
                $where .= " AND nama_obat LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }

            // Total untuk mode saat ini
            $cntStmt = $db->prepare("SELECT COUNT(*) FROM defecta $where");
            $cntStmt->execute($params);
            $totalCount = (int)$cntStmt->fetchColumn();

            // Rows
            $stmt = $db->prepare("
                SELECT id, tanggal, nama_obat, keterangan, status, updated_at
                FROM defecta $where
                ORDER BY updated_at DESC, id DESC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            // Hitung kedua sisi untuk card stats
            $totalDefecta  = (int)$db->query("SELECT COUNT(*) FROM defecta WHERE status='defecta'")->fetchColumn();
            $totalRiwayat  = (int)$db->query("SELECT COUNT(*) FROM defecta WHERE status='tersedia'")->fetchColumn();

            echo json_encode([
                'success'       => true,
                'data'          => $rows,
                'total'         => $totalCount,
                'page'          => $page,
                'pages'         => (int)ceil($totalCount / $limit),
                'limit'         => $limit,
                'mode'          => $mode,
                'total_defecta' => $totalDefecta,
                'total_riwayat' => $totalRiwayat,
            ]);
            break;

        // ── EXPORT SEMUA DATA UNTUK CETAK / PDF ──────────────────
        case 'export':
            $mode   = ($_GET['mode'] ?? 'defecta') === 'riwayat' ? 'tersedia' : 'defecta';
            $search = trim($_GET['search'] ?? '');
            $where  = "WHERE status = :mode";
            $params = [':mode' => $mode];
            if ($search !== '') {
                $where .= " AND nama_obat LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }
            $stmt = $db->prepare("
                SELECT id, tanggal, nama_obat, keterangan, status, updated_at
                FROM defecta $where
                ORDER BY updated_at DESC, id DESC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            $total = (int)$db->query("SELECT COUNT(*) FROM defecta WHERE status='$mode'")->fetchColumn();
            echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows), 'mode' => $mode]);
            break;

        // ── AUTOCOMPLETE NAMA OBAT (deteksi juga yang sudah Defecta) ──
        case 'search_obat':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 1) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            $stmt = $db->prepare("
                SELECT nama_obat, status FROM defecta
                WHERE LOWER(TRIM(nama_obat)) LIKE LOWER(TRIM(:q))
                ORDER BY CASE status WHEN 'tersedia' THEN 0 ELSE 1 END, nama_obat ASC
                LIMIT 10
            ");
            $stmt->execute([':q' => '%' . $q . '%']);
            $data = array_map(
                fn($r) => ['nama' => $r['nama_obat'], 'status' => $r['status']],
                $stmt->fetchAll()
            );
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        // ── TAMBAH ITEM DEFECTA BARU ───────────────────────────────
        case 'tambah':
            $tanggal    = trim($_POST['tanggal']    ?? '');
            $nama_obat  = trim($_POST['nama_obat']  ?? '');
            $keterangan = trim($_POST['keterangan'] ?? '');

            if (!$tanggal || !$nama_obat || !$keterangan) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
                break;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
                break;
            }
            if (strlen($nama_obat) > 255 || strlen($keterangan) > 500) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Data terlalu panjang.']);
                break;
            }
            // ── VALIDASI DUPLIKASI RIWAYAT ──────────────────
            // Cek apakah obat ini sudah ada di Riwayat Tersedia
            // (nama dicocokkan tanpa mempedulikan huruf besar/kecil dan spasi ujung)
            $check = $db->prepare("SELECT id FROM defecta WHERE status='tersedia' AND LOWER(TRIM(nama_obat)) = LOWER(TRIM(:obat)) LIMIT 1");
            $check->execute([':obat' => $nama_obat]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => "Obat \"$nama_obat\" sudah ada di Riwayat Tersedia. Silakan kembalikan dari sana jika ingin defecta lagi."]);
                break;
            }

            // Cek juga apakah sudah ada di daftar Defecta
            $checkDef = $db->prepare("SELECT id FROM defecta WHERE status='defecta' AND LOWER(TRIM(nama_obat)) = LOWER(TRIM(:obat)) LIMIT 1");
            $checkDef->execute([':obat' => $nama_obat]);
            if ($checkDef->fetch()) {
                echo json_encode(['success' => false, 'message' => "Obat \"$nama_obat\" sudah ada dalam daftar Defecta."]);
                break;
            }

            $stmt = $db->prepare("INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_at, updated_at) VALUES (:tgl, :obat, :ket, 'defecta', :now, :now)");
            $stmt->execute([':tgl' => $tanggal, ':obat' => $nama_obat, ':ket' => $keterangan, ':now' => now()]);
            echo json_encode(['success' => true, 'message' => 'Data berhasil ditambahkan.', 'id' => (int)$db->lastInsertId()]);
            break;

        // ── TANDAI BANYAK OBAT TERSEDIA (defecta → tersedia) ───────
        case 'bulk_tersedia':
            $ids = array_filter(array_map('intval', explode(',', trim($_POST['ids'] ?? ''))));
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tidak ada ID yang dipilih.']);
                break;
            }
            $ph     = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
            $params = [':now' => now()];
            foreach ($ids as $i => $id) $params[":id$i"] = $id;
            $stmt = $db->prepare("UPDATE defecta SET status='tersedia', updated_at = :now WHERE id IN ($ph) AND status='defecta'");
            $stmt->execute($params);
            $n = $stmt->rowCount();
            echo json_encode(['success' => true, 'message' => "{$n} obat ditandai tersedia.", 'affected' => $n]);
            break;

        // ── KEMBALIKAN BANYAK OBAT KE DEFECTA (tersedia → defecta) ─
        case 'bulk_defecta':
            $ids = array_filter(array_map('intval', explode(',', trim($_POST['ids'] ?? ''))));
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tidak ada ID yang dipilih.']);
                break;
            }
            $ph     = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
            $params = [':now' => now()];
            foreach ($ids as $i => $id) $params[":id$i"] = $id;
            $stmt = $db->prepare("UPDATE defecta SET status='defecta', updated_at = :now WHERE id IN ($ph) AND status='tersedia'");
            $stmt->execute($params);
            $n = $stmt->rowCount();
            echo json_encode(['success' => true, 'message' => "{$n} obat dikembalikan ke defecta.", 'affected' => $n]);
            break;

        // ── HAPUS BANYAK ITEM SEKALIGUS ────────────────────────────
        case 'bulk_delete':
            $ids = array_filter(array_map('intval', explode(',', trim($_POST['ids'] ?? ''))));
            if (empty($ids)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tidak ada ID yang dipilih.']);
                break;
            }
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("DELETE FROM defecta WHERE id IN ($ph)");
            $stmt->execute(array_values($ids));
            $n = $stmt->rowCount();
            echo json_encode(['success' => true, 'message' => "{$n} item berhasil dihapus.", 'affected' => $n]);
            break;

        // ── EDIT ITEM DEFECTA ──────────────────────────────────
        case 'edit':
            $id         = (int)($_POST['id'] ?? 0);
            $tanggal    = trim($_POST['tanggal']    ?? '');
            $nama_obat  = trim($_POST['nama_obat']  ?? '');
            $keterangan = trim($_POST['keterangan'] ?? '');

            if ($id <= 0 || !$tanggal || !$nama_obat || !$keterangan) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
                break;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Format tanggal tidak valid.']);
                break;
            }
            if (strlen($nama_obat) > 255 || strlen($keterangan) > 500) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Data terlalu panjang.']);
                break;
            }

            // Pastikan item yang diedit ada
            $exist = $db->prepare("SELECT id, status FROM defecta WHERE id = :id LIMIT 1");
            $exist->execute([':id' => $id]);
            $item = $exist->fetch();
            if (!$item) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
                break;
            }

            // Cek duplikasi nama_obat (kecuali item itu sendiri, dan hanya dalam status yang sama)
            $dupCheck = $db->prepare("
                SELECT id FROM defecta
                WHERE LOWER(TRIM(nama_obat)) = LOWER(TRIM(:obat)) AND status = :status AND id != :id
                LIMIT 1
            ");
            $dupCheck->execute([':obat' => $nama_obat, ':status' => $item['status'], ':id' => $id]);
            if ($dupCheck->fetch()) {
                echo json_encode(['success' => false, 'message' => "Obat \"$nama_obat\" sudah ada dalam daftar {$item['status']}."]);
                break;
            }

            $stmt = $db->prepare("
                UPDATE defecta
                SET tanggal = :tgl, nama_obat = :obat, keterangan = :ket, updated_at = :now
                WHERE id = :id
            ");
            $stmt->execute([':tgl' => $tanggal, ':obat' => $nama_obat, ':ket' => $keterangan, ':id' => $id, ':now' => now()]);

            echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui.']);
            break;

        // ── TANDAI SATU OBAT TERSEDIA ──────────────────────────────
        case 'tersedia':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID tidak valid.']); break; }
            $stmt = $db->prepare("UPDATE defecta SET status='tersedia', updated_at = :now WHERE id=:id AND status='defecta'");
            $stmt->execute([':id' => $id, ':now' => now()]);
            echo $stmt->rowCount() > 0
                ? json_encode(['success' => true,  'message' => 'Status diperbarui menjadi tersedia.'])
                : json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
            break;

        // ── LAPORAN RIWAYAT BERDASARKAN TANGGAL ────────────────────
        case 'report':
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $limit    = 15;
            $offset   = ($page - 1) * $limit;
            $mode     = $_GET['mode'] ?? 'semua'; // defecta | tersedia | semua
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo   = trim($_GET['date_to'] ?? '');
            $search   = trim($_GET['search'] ?? '');

            $where  = "WHERE 1=1";
            $params = [];

            // Filter mode
            if ($mode === 'defecta') {
                $where .= " AND status = 'defecta'";
            } elseif ($mode === 'tersedia') {
                $where .= " AND status = 'tersedia'";
            }

            // Filter tanggal — defecta pakai 'tanggal', tersedia pakai 'updated_at'
            if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                if ($mode === 'tersedia') {
                    $where .= " AND DATE(updated_at) >= :date_from";
                } else {
                    $where .= " AND tanggal >= :date_from";
                }
                $params[':date_from'] = $dateFrom;
            }
            if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                if ($mode === 'tersedia') {
                    $where .= " AND DATE(updated_at) <= :date_to";
                } else {
                    $where .= " AND tanggal <= :date_to";
                }
                $params[':date_to'] = $dateTo;
            }

            // Filter search
            if ($search !== '') {
                $where .= " AND nama_obat LIKE :search";
                $params[':search'] = '%' . $search . '%';
            }

            // Count
            $cntStmt = $db->prepare("SELECT COUNT(*) FROM defecta $where");
            $cntStmt->execute($params);
            $totalCount = (int)$cntStmt->fetchColumn();

            // Rows
            $orderCol = ($mode === 'tersedia') ? 'updated_at' : 'tanggal';
            $stmt = $db->prepare("
                SELECT id, tanggal, nama_obat, keterangan, status, created_at, updated_at
                FROM defecta $where
                ORDER BY $orderCol DESC, id DESC
                LIMIT :limit OFFSET :offset
            ");
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'data'    => $rows,
                'total'   => $totalCount,
                'page'    => $page,
                'pages'   => (int)ceil($totalCount / $limit),
                'limit'   => $limit,
                'mode'    => $mode,
            ]);
            break;

        // ── RINGKASAN LAPORAN ──────────────────────────────────────
        case 'report_summary':
            $mode     = $_GET['mode'] ?? 'semua';
            $dateFrom = trim($_GET['date_from'] ?? '');
            $dateTo   = trim($_GET['date_to'] ?? '');

            $whereBase = "WHERE 1=1";
            $paramsBase = [];

            if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
                $whereBase .= " AND (CASE WHEN status='tersedia' THEN DATE(updated_at) ELSE tanggal END) >= :date_from";
                $paramsBase[':date_from'] = $dateFrom;
            }
            if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $whereBase .= " AND (CASE WHEN status='tersedia' THEN DATE(updated_at) ELSE tanggal END) <= :date_to";
                $paramsBase[':date_to'] = $dateTo;
            }

            // Total per status dalam rentang
            $stmtDef = $db->prepare("SELECT COUNT(*) FROM defecta $whereBase AND status='defecta'");
            $stmtDef->execute($paramsBase);
            $countDef = (int)$stmtDef->fetchColumn();

            $stmtTer = $db->prepare("SELECT COUNT(*) FROM defecta $whereBase AND status='tersedia'");
            $stmtTer->execute($paramsBase);
            $countTer = (int)$stmtTer->fetchColumn();

            // Top 5 obat paling sering defecta
            $stmtTop = $db->prepare("
                SELECT nama_obat, COUNT(*) as jumlah
                FROM defecta $whereBase AND status='defecta'
                GROUP BY nama_obat
                ORDER BY jumlah DESC
                LIMIT 5
            ");
            $stmtTop->execute($paramsBase);
            $topDefecta = $stmtTop->fetchAll();

            echo json_encode([
                'success'       => true,
                'total'         => $countDef + $countTer,
                'total_defecta' => $countDef,
                'total_tersedia'=> $countTer,
                'top_defecta'   => $topDefecta,
            ]);
            break;

        // ── BACKUP: BUAT BACKUP BARU (kompresi bzip2) ─────────────────
        case 'backup_create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
                break;
            }
            $src = getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/data/defecta.sqlite');
            if (!is_file($src)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Database tidak ditemukan.']);
                break;
            }
            $backupDir = dirname($src) . '/backups';
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }
            $timestamp = date('Ymd-His');
            $filename  = "defecta-{$timestamp}.sqlite.bz2";
            $dest      = $backupDir . '/' . $filename;

            // Baca file SQLite, kompresi dengan bzip2 (level 9 = max)
            $data = file_get_contents($src);
            if ($data === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal membaca database.']);
                break;
            }
            $compressed = bzcompress($data, 9);
            if ($compressed === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal mengompresi backup.']);
                break;
            }
            if (file_put_contents($dest, $compressed) === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal menyimpan backup.']);
                break;
            }

            $size = filesize($dest);
            echo json_encode([
                'success'    => true,
                'message'    => 'Backup berhasil dibuat.',
                'filename'   => $filename,
                'size'       => $size,
                'created_at' => now(),
            ]);
            break;

        // ── BACKUP: DAFTAR BACKUP TERSEDIA ────────────────────────────
        case 'backup_list':
            $backupDir = dirname((getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/data/defecta.sqlite'))) . '/backups';
            $files = [];
            if (is_dir($backupDir)) {
                $dh = opendir($backupDir);
                while (($file = readdir($dh)) !== false) {
                    if (str_ends_with($file, '.sqlite.bz2')) {
                        $path = $backupDir . '/' . $file;
                        $files[] = [
                            'filename'   => $file,
                            'size'       => filesize($path),
                            'created_at' => date('Y-m-d H:i:s', filemtime($path)),
                        ];
                    }
                }
                closedir($dh);
                // Urutkan terbaru dulu
                usort($files, fn($a, $b) => strcmp($b['filename'], $a['filename']));
            }
            echo json_encode(['success' => true, 'data' => $files]);
            break;

        // ── BACKUP: DOWNLOAD FILE BACKUP ──────────────────────────────
        case 'backup_download':
            $file = basename($_GET['file'] ?? '');
            if ($file === '' || !str_ends_with($file, '.sqlite.bz2')) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nama file tidak valid.']);
                break;
            }
            $backupDir = dirname((getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/data/defecta.sqlite'))) . '/backups';
            $path = $backupDir . '/' . $file;
            if (!is_file($path)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'File backup tidak ditemukan.']);
                break;
            }
            // Stream file download
            header('Content-Type: application/x-bzip2');
            header('Content-Disposition: attachment; filename="' . $file . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: no-store, private');
            readfile($path);
            break;

        // ── BACKUP: HAPUS FILE BACKUP ──────────────────────────────────
        case 'backup_delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
                break;
            }
            $file = basename($_POST['file'] ?? '');
            if ($file === '' || !str_ends_with($file, '.sqlite.bz2')) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nama file tidak valid.']);
                break;
            }
            $backupDir = dirname((getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/data/defecta.sqlite'))) . '/backups';
            $path = $backupDir . '/' . $file;
            if (!is_file($path)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'File backup tidak ditemukan.']);
                break;
            }
            if (!unlink($path)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus file backup.']);
                break;
            }
            echo json_encode(['success' => true, 'message' => 'Backup berhasil dihapus.']);
            break;

        // ── BACKUP: RESTORE DARI FILE BACKUP ──────────────────────────
        case 'backup_restore':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
                break;
            }
            $file = basename($_POST['file'] ?? '');
            if ($file === '' || !str_ends_with($file, '.sqlite.bz2')) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Nama file tidak valid.']);
                break;
            }
            $src = getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/data/defecta.sqlite');
            $backupDir = dirname($src) . '/backups';
            $path = $backupDir . '/' . $file;
            if (!is_file($path)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'File backup tidak ditemukan.']);
                break;
            }

            // Baca & dekompresi ke file temporary
            $compressed = file_get_contents($path);
            if ($compressed === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal membaca file backup.']);
                break;
            }
            $data = bzdecompress($compressed);
            if ($data === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal mengekstrak backup (file korup?).']);
                break;
            }

            // Tulis ke file temporary & verifikasi integritas SQLite
            $tmp = $src . '.tmp.' . bin2hex(random_bytes(8));
            if (file_put_contents($tmp, $data) === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal menulis file temporary.']);
                break;
            }

            // Verifikasi: coba buka sebagai SQLite & cek tabel defecta ada
            try {
                $pdo = new PDO('sqlite:' . $tmp, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo->exec('PRAGMA busy_timeout=5000');
                $chk = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='defecta'")->fetch();
                if (!$chk) {
                    @unlink($tmp);
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Backup tidak valid: tabel defecta tidak ditemukan.']);
                    break;
                }
            } catch (PDOException $e) {
                @unlink($tmp);
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Backup tidak valid: ' . $e->getMessage()]);
                break;
            }

            // Atomic replace: rename temporary file ke database utama
            // (rename adalah atomic di POSIX)
            if (!rename($tmp, $src)) {
                @unlink($tmp);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal mengganti database.']);
                break;
            }

            // Reset koneksi PDO singleton agar pakai DB baru
            // (getDB() pakai static $pdo, jadi butuh reload halaman)
            echo json_encode([
                'success' => true,
                'message' => 'Database berhasil dipulihkan. Halaman akan dimuat ulang.',
                'reload'  => true,
            ]);
            break;

        // ── BACKUP: RESTORE DARI FILE YANG DI-UPLOAD ────────────────────
        // Memungkinkan upload file .sqlite.bz2 (mis. yang sudah didownload
        // dari server lain) dan restore ke database lokal.
        case 'backup_restore_upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
                break;
            }
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] === UPLOAD_ERR_NO_FILE) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diupload.']);
                break;
            }
            $f = $_FILES['backup_file'];
            if ($f['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Upload gagal. Kode error: ' . $f['error']]);
                break;
            }
            // Validasi ekstensi: .sqlite.bz2 (dipaksa) atau .bz2
            $origName = $f['name'];
            $extOk = str_ends_with($origName, '.sqlite.bz2') || str_ends_with($origName, '.bz2');
            if (!$extOk) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Hanya file .sqlite.bz2 yang diizinkan.']);
                break;
            }
            // Validasi ukuran (max 50 MB)
            $maxSize = 50 * 1024 * 1024;
            if ($f['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'File terlalu besar. Maksimal 50 MB.']);
                break;
            }
            // Baca isi file yang diupload
            $compressed = file_get_contents($f['tmp_name']);
            if ($compressed === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal membaca file upload.']);
                break;
            }
            $data = bzdecompress($compressed);
            if ($data === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Gagal mengekstrak file (bukan bzip2 atau file korup).']);
                break;
            }

            $src = getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/data/defecta.sqlite');
            $tmp = $src . '.tmp.' . bin2hex(random_bytes(8));
            if (file_put_contents($tmp, $data) === false) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal menulis file temporary.']);
                break;
            }

            // Verifikasi: cek apakah file .sqlite valid & punya tabel defecta
            try {
                $pdo = new PDO('sqlite:' . $tmp, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                ]);
                $pdo->exec('PRAGMA busy_timeout=5000');
                // Cek versi file SQLite
                $hdr = $pdo->query("PRAGMA journal_mode")->fetchColumn();
                // Cek tabel defecta ada
                $chk = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='defecta'")->fetch();
                if (!$chk) {
                    @unlink($tmp);
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Database tidak valid: tabel defecta tidak ditemukan.']);
                    break;
                }
                // Hitung total data
                $totalRows = (int)$pdo->query("SELECT COUNT(*) FROM defecta")->fetchColumn();
                unset($pdo);
            } catch (PDOException $e) {
                @unlink($tmp);
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Database tidak valid: ' . $e->getMessage()]);
                break;
            }

            // Backup database lama sebelum replace (simpan di backups/)
            $backupDir = dirname($src) . '/backups';
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }
            if (is_file($src)) {
                $bkName = 'defecta-pre-restore-' . date('Ymd-His') . '.sqlite.bz2';
                $bkPath = $backupDir . '/' . $bkName;
                $oldData = file_get_contents($src);
                if ($oldData !== false) {
                    $oldCompressed = bzcompress($oldData, 9);
                    if ($oldCompressed !== false) {
                        file_put_contents($bkPath, $oldCompressed);
                    }
                }
            }

            // Atomic replace
            if (!rename($tmp, $src)) {
                @unlink($tmp);
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal mengganti database.']);
                break;
            }

            echo json_encode([
                'success'    => true,
                'message'    => 'Database berhasil dipulihkan dari file upload. Halaman akan dimuat ulang.',
                'reload'     => true,
                'total_rows' => $totalRows,
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenal.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    error_log('[defecta] DB error: ' . $e->getMessage());
    $msg = (str_contains($e->getMessage(), 'Access denied') || str_contains($e->getMessage(), 'Unknown database'))
        ? 'Konfigurasi database tidak valid. Periksa config.php'
        : 'Database error.';
    echo json_encode(['success' => false, 'message' => $msg]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[defecta] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server.']);
}
