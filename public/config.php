<?php
/**
 * Konfigurasi Database - Apotek Mentari Farma Bondoweso
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
// Production MUST set APP_PASSWORD in the environment.
$envPin = getenv('APP_PASSWORD');
if ($envPin === false || $envPin === '') {
    // Development convenience: .env file can supply APP_PIN (mapped to APP_PASSWORD
    // in docker-compose.yml).  In production, always set APP_PASSWORD directly.
    $envPin = getenv('APP_PIN') ?: '';
}
if ($envPin === '') {
    // No default — fail loudly rather than silently accepting a known PIN.
    if (php_sapi_name() === 'cli') {
        define('APP_PASSWORD', '');
    } else {
        define('APP_PASSWORD', '');
        error_log('[defecta] WARNING: APP_PASSWORD not set in environment. Login will be disabled.');
    }
} else {
    define('APP_PASSWORD', $envPin);
}

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
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SCRIPT_NAME'] ?? '';
    session_start([
        'cookie_lifetime' => 86400 * 30, // 30 Hari agar tidak sering login ulang
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure'   => false, // Set to true in production under HTTPS
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

    if (!in_array('audit_log', $tables, true)) {
        $db->exec("
            CREATE TABLE audit_log (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      TEXT NOT NULL,
                action       TEXT NOT NULL,
                entity_type  TEXT,
                entity_id    INTEGER,
                old_values   TEXT,
                new_values   TEXT,
                metadata     TEXT,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_action  ON audit_log(action, created_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_entity  ON audit_log(entity_type, entity_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_audit_user    ON audit_log(user_id, created_at)");
    }
}

/**
 * Catat sebuah peristabaan ke audit_log.
 *
 * Actor (user_id) is always derived from the authenticated session —
 * never trusted from client input.
 */
function audit_log(string $action, ?string $entityType, $entityId, $count = 1, $metadata = null): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO audit_log (user_id, action, entity_type, entity_id, metadata)
            VALUES (:uid, :action, :etype, :eid, :meta)
        ");
        $stmt->execute([
            ':uid'    => current_staff(),
            ':action' => $action,
            ':etype'  => $entityType,
            ':eid'    => $entityId === null ? null : (int)$entityId,
            ':meta'   => $metadata === null ? null : (is_string($metadata) ? $metadata : json_encode($metadata)),
        ]);
    } catch (Throwable $e) {
        error_log('[defecta] audit_log failed: ' . $e->getMessage());
    }
}

/**
 * Validasi database SQLite backup secara menyeluruh.
 *
 * Returns true on valid DB, throws Exception on any issue.
 * Does NOT leak filesystem paths to the caller.
 */
function validate_database_backup(string $path): true {
    if (!is_file($path)) {
        throw new InvalidArgumentException('File not found');
    }

    // Verify it's a valid SQLite database (magic header)
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        throw new RuntimeException('Cannot open file');
    }
    $header = fread($fh, 16);
    fclose($fh);
    $magic = "SQLite format 3\0";
    if ($header !== $magic) {
        throw new RuntimeException('Invalid SQLite header');
    }

    // Integrity check
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec('PRAGMA busy_timeout=5000');

    $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
    if ($integrity !== 'ok') {
        throw new RuntimeException('Integrity check failed: ' . $integrity);
    }

    // Required tables
    $requiredTables = ['defecta'];
    foreach ($requiredTables as $table) {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :t");
        $stmt->execute([':t' => $table]);
        $exists = $stmt->fetch();
        if (!$exists) {
            $pdo = null;
            throw new RuntimeException("Missing required table: $table");
        }
    }
    unset($pdo);

    // Schema column validation
    $check = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $cols = $check->query("PRAGMA table_info(defecta)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');
    $requiredCols = ['id', 'tanggal', 'nama_obat', 'keterangan', 'status', 'created_at', 'updated_at'];
    foreach ($requiredCols as $col) {
        if (!in_array($col, $colNames)) {
            throw new RuntimeException("Missing required column in defecta table: $col");
        }
    }
    unset($check);

    return true;
}
