<?php
/**
 * Konfigurasi Database - Apotek Mentari Farma Bondowoso
 * Database: SQLite (satu file), path diatur lewat env DB_SQLITE_PATH.
 */

// Guard: file ini tidak boleh diakses langsung via HTTP
if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Forbidden');
}

// Waktu lokal (sebelumnya MySQL memakai +07:00)
date_default_timezone_set('Asia/Jakarta');

// Password Aplikasi (Simple PIN/Pass untuk seluruh Staf)
define('APP_PASSWORD', getenv('APP_PASSWORD') ?: '1324'); // Silakan ganti sesuai keinginan

// ── USER IDENTITY ─────────────────────────────────────────────
// Daftar staff yang dapat login (nama => label tampilan)
// Untuk keperluan audit trail: mencatat siapa yang melakukan perubahan.
define('STAFF_LIST', [
    ''           => 'Pilih nama...',
    'andi'       => 'Andi (Apoteker)',
    'sari'       => 'Sari (Staf)',
    'budi'       => 'Budi (Staf)',
    'lina'       => 'Lina (Staf)',
]);

/**
 * Memulai session untuk autentikasi
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400 * 30, // 30 Hari agar tidak sering login ulang
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

// Token CSRF per-session (dibuat sekali, dipakai semua POST)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Cek apakah user sudah login
 */
function is_logged_in(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Dapatkan nama staff yang sedang login (untuk audit trail)
 */
function current_staff(): string {
    return $_SESSION['staff_name'] ?? 'unknown';
}

/**
 * Set staff yang sedang login
 */
function set_staff(string $name): void {
    $_SESSION['staff_name'] = $name;
}

/**
 * Timestamp lokal sekarang (format SQLite / MySQL)
 */
function now(): string {
    return date('Y-m-d H:i:s');
}

/**
 * Buat koneksi PDO ke SQLite (WAL + busy_timeout agar tulis bersamaan aman).
 */
function getDB(): PDO {
    static $pdo = null;
    static $lastPath = null;
    $path = getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/data/defecta.sqlite');

    // Reset connection if DB path changed (needed for testing)
    if ($lastPath !== $path) {
        $pdo = null;
        $lastPath = $path;
    }
    if ($pdo !== null) return $pdo;

    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO('sqlite:' . $path, null, null, $options);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
    $pdo->exec('PRAGMA foreign_keys=OFF');

    return $pdo;
}

/**
 * Inisialisasi tabel bila belum ada (tanpa DDL berulang tiap request).
 */
function initDB(): void {
    $db = getDB();
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('defecta', $tables, true)) {
        $db->exec("
            CREATE TABLE defecta (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                tanggal     TEXT NOT NULL,
                nama_obat   TEXT NOT NULL,
                keterangan  TEXT NOT NULL,
                status      TEXT NOT NULL DEFAULT 'defecta' CHECK(status IN ('defecta','tersedia')),
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                created_by  TEXT,
                updated_by  TEXT
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_status  ON defecta(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_tanggal ON defecta(tanggal)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_nama    ON defecta(nama_obat)");
    } else {
        // ── MIGRASI: tambah kolom audit trail jika belum ada ──
        // PRAGMA table_info mengembalikan kolom: cid, name, type, notnull, dflt_value, pk
        // Harus pakai FETCH_NUM dan ambil indeks ke-1 (name) atau FETCH_ASSOC
        $pragmaRows = $db->query("PRAGMA table_info(defecta)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($pragmaRows, 'name');
        if (!in_array('created_by', $colNames)) {
            $db->exec("ALTER TABLE defecta ADD COLUMN created_by TEXT");
        }
        if (!in_array('updated_by', $colNames)) {
            $db->exec("ALTER TABLE defecta ADD COLUMN updated_by TEXT");
        }
    }

    if (!in_array('login_attempts', $tables, true)) {
        $db->exec("
            CREATE TABLE login_attempts (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                ip           TEXT NOT NULL,
                attempted_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip, attempted_at)");
    }
}
