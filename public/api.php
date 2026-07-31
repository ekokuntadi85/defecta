<?php
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// ── Proteksi Akses API ──────────────────────────────────────
require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── AUTH CHECK ──────────────────────────────────────────────
if ($action === 'login') {
    $password = $_POST['password'] ?? '';
    if ($password === APP_PASSWORD) {
        $_SESSION['logged_in'] = true;
        echo json_encode(['success' => true, 'message' => 'Login berhasil.']);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'PIN salah!']);
    }
    exit;
}

if ($action === 'logout') {
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

        // ── AUTOCOMPLETE NAMA OBAT DARI RIWAYAT TERSEDIA ───────────
        case 'search_tersedia':
            $q = trim($_GET['q'] ?? '');
            if (strlen($q) < 1) {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            $stmt = $db->prepare("
                SELECT DISTINCT nama_obat FROM defecta
                WHERE status = 'tersedia' AND nama_obat LIKE :q
                ORDER BY nama_obat ASC LIMIT 10
            ");
            $stmt->execute([':q' => '%' . $q . '%']);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
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
            $check = $db->prepare("SELECT id FROM defecta WHERE status='tersedia' AND nama_obat=:obat LIMIT 1");
            $check->execute([':obat' => $nama_obat]);
            if ($check->fetch()) {
                echo json_encode(['success' => false, 'message' => "Obat \"$nama_obat\" sudah ada di Riwayat Tersedia. Silakan kembalikan dari sana jika ingin defecta lagi."]);
                break;
            }

            // Cek juga apakah sudah ada di daftar Defecta
            $checkDef = $db->prepare("SELECT id FROM defecta WHERE status='defecta' AND nama_obat=:obat LIMIT 1");
            $checkDef->execute([':obat' => $nama_obat]);
            if ($checkDef->fetch()) {
                echo json_encode(['success' => false, 'message' => "Obat \"$nama_obat\" sudah ada dalam daftar Defecta."]);
                break;
            }

            $stmt = $db->prepare("INSERT INTO defecta (tanggal, nama_obat, keterangan, status) VALUES (:tgl, :obat, :ket, 'defecta')");
            $stmt->execute([':tgl' => $tanggal, ':obat' => $nama_obat, ':ket' => $keterangan]);
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
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("UPDATE defecta SET status='tersedia', updated_at = CURRENT_TIMESTAMP WHERE id IN ($ph) AND status='defecta'");
            $stmt->execute(array_values($ids));
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
            $ph   = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $db->prepare("UPDATE defecta SET status='defecta', updated_at = CURRENT_TIMESTAMP WHERE id IN ($ph) AND status='tersedia'");
            $stmt->execute(array_values($ids));
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
                WHERE nama_obat = :obat AND status = :status AND id != :id
                LIMIT 1
            ");
            $dupCheck->execute([':obat' => $nama_obat, ':status' => $item['status'], ':id' => $id]);
            if ($dupCheck->fetch()) {
                echo json_encode(['success' => false, 'message' => "Obat \"$nama_obat\" sudah ada dalam daftar {$item['status']}."]);
                break;
            }

            $stmt = $db->prepare("
                UPDATE defecta
                SET tanggal = :tgl, nama_obat = :obat, keterangan = :ket, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $stmt->execute([':tgl' => $tanggal, ':obat' => $nama_obat, ':ket' => $keterangan, ':id' => $id]);

            echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui.']);
            break;

        // ── TANDAI SATU OBAT TERSEDIA ──────────────────────────────
        case 'tersedia':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'ID tidak valid.']); break; }
            $stmt = $db->prepare("UPDATE defecta SET status='tersedia', updated_at = CURRENT_TIMESTAMP WHERE id=:id AND status='defecta'");
            $stmt->execute([':id' => $id]);
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

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    $msg = (str_contains($e->getMessage(), 'Access denied') || str_contains($e->getMessage(), 'Unknown database'))
        ? 'Konfigurasi database tidak valid. Periksa config.php'
        : 'Database error.';
    echo json_encode(['success' => false, 'message' => $msg, '_debug' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
