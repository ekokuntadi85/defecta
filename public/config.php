<?php
/**
 * Konfigurasi Database - Apotek Mentari Farma Bondowoso
 * Edit sesuai kredensial MySQL Anda
 */

// Guard: file ini tidak boleh diakses langsung via HTTP
if (php_sapi_name() !== 'cli' && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    http_response_code(403);
    exit('Forbidden');
}


// Semua nilai di bawah bisa di-override lewat environment variable
// (lihat docker-compose.yml / .env). Fallback ke nilai lama agar
// aplikasi tetap jalan di setup lokal tanpa Docker.
define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_NAME',     getenv('DB_NAME')     ?: 'defecta_apotik');
define('DB_USER',     getenv('DB_USER')     ?: 'defecta');
define('DB_PASS',     getenv('DB_PASS')     ?: 'lakiLAKI46');
define('DB_CHARSET',  getenv('DB_CHARSET')  ?: 'utf8mb4');

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
 * Buat koneksi PDO MySQL
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    return $pdo;
}

/**
 * Inisialisasi tabel (jalankan sekali)
 */
function initDB(): void {
    $db = getDB();
    $db->exec("
        CREATE TABLE IF NOT EXISTS defecta (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tanggal     DATE         NOT NULL,
            nama_obat   VARCHAR(255) NOT NULL,
            keterangan  VARCHAR(500) NOT NULL,
            status      ENUM('defecta','tersedia') NOT NULL DEFAULT 'defecta',
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status  (status),
            INDEX idx_tanggal (tanggal),
            INDEX idx_nama    (nama_obat)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
