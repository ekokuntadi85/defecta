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

// ── BACKWARD COMPATIBILITY ─────────────────────────────────────────
// APP_PASSWORD kept as optional fallback for environments that still use
// a shared PIN. Individual user PINs (users table) take priority.
$envPin = getenv('APP_PASSWORD');
if ($envPin === false || $envPin === '') {
    $envPin = getenv('APP_PIN') ?: '';
}
define('APP_PASSWORD', $envPin !== '' ? $envPin : '');

// Default PIN for seeded users (change after first login)
define('DEFAULT_USER_PIN', '123456');

// ── RATE LIMITING ──────────────────────────────────────────────────
// Applied to login and sensitive endpoints (backup restore)
// Use env vars for override, fall back to defaults
if (!defined('LOGIN_MAX_ATTEMPTS')) {
    define('LOGIN_MAX_ATTEMPTS', (int)(getenv('LOGIN_MAX_ATTEMPTS') ?: 5));
}
if (!defined('LOGIN_WINDOW_MINUTES')) {
    define('LOGIN_WINDOW_MINUTES', (int)(getenv('LOGIN_WINDOW_MINUTES') ?: 15));
}

// ── STAFF LIST (backward compat — seeded from users table at runtime) ─
define('STAFF_LIST', [
    ''           => 'Pilih nama...',
    'damayanti'  => 'Damayanti (Apoteker)',
    'faizin'     => 'Faizin (Manajer)',
    'aisyiyah'   => 'Aisyiyah (Staf)',
    'anggun'     => 'Anggun (Staf)',
]);

// ── USER ROLES ──────────────────────────────────────────────────────
define('ROLE_Apoteker', 'apoteker');
define('ROLE_Manager', 'manager');
define('ROLE_Staff', 'staff');

/**
 * Memulai session untuk autentikasi
 */
if (session_status() === PHP_SESSION_NONE) {
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
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        return false;
    }
    
    // Session idle timeout: 30 minutes
    $idleTimeout = 1800; // 30 minutes in seconds
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $idleTimeout) {
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    
    return true;
}

/**
 * Dapatkan nama staff yang sedang login (untuk audit trail)
 * Backward-compatible wrapper around current_user()
 */
function current_staff(): string {
    return $_SESSION['username'] ?? 'unknown';
}

/**
 * Set staff yang sedang login (backward compat)
 */
function set_staff(string $name): void {
    $_SESSION['username'] = $name;
}

/**
 * Dapatkan user ID yang sedang login
 */
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Dapatkan nama username yang sedang login
 */
function current_username(): string {
    return $_SESSION['username'] ?? 'unknown';
}

/**
 * Dapatkan nama tampilan user yang sedang login
 */
function current_user_display(): string {
    return $_SESSION['display_name'] ?? 'Unknown';
}

/**
 * Dapatkan peran user yang sedang login
 */
function current_user_role(): string {
    return $_SESSION['role'] ?? 'staff';
}

/**
 * Cek apakah user adalah admin (apoteker atau manajer)
 */
function is_admin(): bool {
    return in_array(current_user_role(), ['apoteker', 'manager'], true);
}

/**
 * Authenticate user by username + PIN
 * Returns user array on success, false on failure
 */
function authenticate_user(string $username, string $pin): ?array {
    $db = getDB();

    // Rate limit: max LOGIN_MAX_ATTEMPTS per LOGIN_WINDOW_MINUTES per IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Clean old attempts (>1 hour)
    $db->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now','-1 hour')");

    $window = LOGIN_WINDOW_MINUTES;
    $recent = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > datetime('now','-{$window} minutes')");
    $recent->execute([':ip' => $ip]);
    if ((int)$recent->fetchColumn() >= LOGIN_MAX_ATTEMPTS) {
        return null; // rate limited
    }

    // Check username exists and is active
    $stmt = $db->prepare("SELECT id, username, display_name, role, pin_hash, is_active FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();

    if (!$user || !$user['is_active'] || !password_verify($pin, $user['pin_hash'])) {
        // Record failed attempt
        $ins = $db->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)");
        $ins->execute([':ip' => $ip]);
        return null;
    }

    // Clear failed attempts for this IP on success
    $db->exec("DELETE FROM login_attempts WHERE ip = '$ip'");

    return $user;
}

/**
 * Authenticate by PIN (shared fallback) — only used when APP_PASSWORD is set
 * and no username is provided (legacy login support)
 */
function authenticate_shared_pin(string $pin): bool {
    if (APP_PASSWORD === '') return false;
    return hash_equals(APP_PASSWORD, $pin);
}

/**
 * Seed default users if users table is empty
 */
function seed_default_users(): void {
    $db = getDB();
    $defaultPin = DEFAULT_USER_PIN;
    $pinHash = password_hash($defaultPin, PASSWORD_DEFAULT);

    $users = [
        ['damayanti', 'Damayanti (Apoteker)', 'apoteker'],
        ['faizin',    'Faizin (Manajer)',     'manager'],
        ['aisyiyah',  'Aisyiyah (Staf)',      'staff'],
        ['anggun',    'Anggun (Staf)',        'staff'],
    ];

    $stmt = $db->prepare("INSERT OR IGNORE INTO users (username, display_name, role, pin_hash, is_active) VALUES (:u, :dn, :r, :ph, 1)");
    foreach ($users as [$u, $dn, $r]) {
        $stmt->execute([':u' => $u, ':dn' => $dn, ':r' => $r, ':ph' => $pinHash]);
    }
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
        $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_nama_unique ON defecta(nama_obat)");
    } else {
        // ── MIGRASI: tambah kolom audit trail jika belum ada ──
        $pragmaRows = $db->query("PRAGMA table_info(defecta)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($pragmaRows, 'name');
        if (!in_array('created_by', $colNames)) {
            $db->exec("ALTER TABLE defecta ADD COLUMN created_by TEXT");
        }
        if (!in_array('updated_by', $colNames)) {
            $db->exec("ALTER TABLE defecta ADD COLUMN updated_by TEXT");
        }

        // ── MIGRASI: UNIQUE constraint pada nama_obat ──
        // Deduplicate (keep latest by id) then enforce uniqueness
        $hasUnique = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='index' AND name='idx_nama_unique'")->fetchColumn();
        if (!$hasUnique) {
            $db->exec("DELETE FROM defecta WHERE id NOT IN (SELECT MAX(id) FROM defecta GROUP BY nama_obat)");
            $db->exec("DROP INDEX IF EXISTS idx_nama");
            $db->exec("CREATE UNIQUE INDEX idx_nama_unique ON defecta(nama_obat)");
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

    // ── USERS TABLE (individual PIN authentication) ──
    if (!in_array('users', $tables, true)) {
        $db->exec("
            CREATE TABLE users (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                username     TEXT UNIQUE NOT NULL,
                display_name TEXT NOT NULL,
                role         TEXT NOT NULL DEFAULT 'staff' CHECK(role IN ('apoteker','manager','staff')),
                pin_hash     TEXT NOT NULL,
                is_active    INTEGER DEFAULT 1,
                created_at   TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at   TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_users_role    ON users(role)");

        // Seed default users
        seed_default_users();
    }

    if (!in_array('audit_log', $tables, true)) {
        $db->exec("
            CREATE TABLE audit_log (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      TEXT NOT NULL DEFAULT 'unknown',
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
        $pdo = null;
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

/**
 * Get all active staff list (username => display_name) for login dropdown.
 * Falls back to STAFF_LIST constant if users table not yet initialized.
 */
function get_staff_list(): array {
    try {
        $db = getDB();
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('users', $tables, true)) {
            $rows = $db->query("SELECT username, display_name FROM users WHERE is_active = 1 ORDER BY display_name")->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!empty($rows)) {
                // Prepend placeholder
                return array_merge(['' => 'Pilih nama...'], $rows);
            }
        }
    } catch (Throwable $e) {
        // Fall through to static list
    }
    return STAFF_LIST;
}
