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


// Password Aplikasi (Simple PIN/Pass untuk seluruh Staf)
define('APP_PASSWORD', getenv('APP_PASSWORD') ?: '1324'); // Silakan ganti sesuai keinginan

/**
 * Memulai session untuk autentikasi
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400 * 30, // 30 Hari agar tidak sering login ulang
        'cookie_httponly' => true,
    ]);
}

/**
 * Cek apakah user sudah login
 */
function is_logged_in(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Buat koneksi PDO ke SQLite (WAL + busy_timeout agar tulis bersamaan aman).
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $path = getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/data/defecta.sqlite');
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
 * Inisialisasi tabel (dipanggil otomatis pada API pertama).
 */
function initDB(): void {
    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS defecta (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            tanggal     TEXT NOT NULL,
            nama_obat   TEXT NOT NULL,
            keterangan  TEXT NOT NULL,
            status      TEXT NOT NULL DEFAULT 'defecta' CHECK(status IN ('defecta','tersedia')),
            created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_status  ON defecta(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_tanggal ON defecta(tanggal)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_nama    ON defecta(nama_obat)");
}
