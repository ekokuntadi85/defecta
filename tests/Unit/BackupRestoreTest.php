<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for backup and restore functionality.
 */
final class BackupRestoreTest extends TestCase
{
    private string $dbPath;
    private string $backupDir;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../tmp/test_backup_' . spl_object_id($this) . '.sqlite';
        $this->backupDir = __DIR__ . '/../tmp/test_backup_backups_' . spl_object_id($this);
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
        @rmdir($this->backupDir);

        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        $_SESSION = [];
        set_staff('sari');

        initDB();
        $db = getDB();

        // Insert test data
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-09-01', 'Obat Backup Test', 'Stock Habis', 'defecta', 'sari', 'sari']);
        $stmt->execute(['2026-09-02', 'Obat Backup Test 2', 'Stock Menipis', 'tersedia', 'budi', 'budi']);

        $db->exec('VACUUM'); // merge WAL into main file
    }

    protected function tearDown(): void
    {
        $db = getDB(); $db = null;
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
        // Clean up backup dir
        if (is_dir($this->backupDir)) {
            $files = glob($this->backupDir . '/*');
            foreach ($files as $f) { @unlink($f); }
            @rmdir($this->backupDir);
        }
    }

    private function getDb(): PDO
    {
        return getDB();
    }

    public function testValidateDatabaseBackupAcceptsValidDb(): void
    {
        $db = $this->getDb();
        $this->assertTrue(validate_database_backup($this->dbPath));
    }

    public function testBackupCreateProducesValidSQLite(): void
    {
        $db = $this->getDb();
        $snapshotPath = $this->backupDir . '/test_snapshot.sqlite';
        @mkdir($this->backupDir, 0755, true);
        @unlink($snapshotPath);

        $db->exec("VACUUM INTO '" . addslashes($snapshotPath) . "'");
        $this->assertFileExists($snapshotPath, 'VACUUM INTO should create snapshot file');

        $this->assertTrue(validate_database_backup($snapshotPath));
        @unlink($snapshotPath);
    }

    public function testBackupCreateProducesCompressedFile(): void
    {
        if (!function_exists('bzcompress')) {
            $this->markTestSkipped('bz2 extension not available');
        }

        $db = $this->getDb();
        $snapshotPath = $this->backupDir . '/test_snapshot.sqlite';
        $compressedPath = $snapshotPath . '.bz2';
        @mkdir($this->backupDir, 0755, true);
        @unlink($snapshotPath);
        @unlink($compressedPath);

        $db->exec("VACUUM INTO '" . addslashes($snapshotPath) . "'");

        $data = file_get_contents($snapshotPath);
        $compressed = bzcompress($data, 9);
        $this->assertNotFalse($compressed);
        file_put_contents($compressedPath, $compressed);

        $this->assertFileExists($compressedPath);
        $this->assertGreaterThan(0, filesize($compressedPath));

        @unlink($snapshotPath);
        @unlink($compressedPath);
    }

    public function testBackupSnapshotPassesIntegrityCheck(): void
    {
        $db = $this->getDb();
        $snapshotPath = $this->backupDir . '/integrity_test.sqlite';
        @mkdir($this->backupDir, 0755, true);
        @unlink($snapshotPath);

        $db->exec("VACUUM INTO '" . addslashes($snapshotPath) . "'");

        $snapshotDb = new PDO('sqlite:' . $snapshotPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $result = $snapshotDb->query('PRAGMA integrity_check')->fetchColumn();
        $this->assertEquals('ok', $result);

        // Verify data is present
        $count = (int)$snapshotDb->query("SELECT COUNT(*) FROM defecta")->fetchColumn();
        $this->assertEquals(2, $count, 'Snapshot should contain all data');

        unset($snapshotDb);
        @unlink($snapshotPath);
    }

    public function testRestoreFromValidBackupSucceeds(): void
    {
        $db = $this->getDb();
        $snapshotPath = $this->backupDir . '/restore_test.sqlite';
        @mkdir($this->backupDir, 0755, true);
        @unlink($snapshotPath);

        $db->exec("VACUUM INTO '" . addslashes($snapshotPath) . "'");

        $this->assertTrue(validate_database_backup($snapshotPath));

        // Simulate restore: replace the database
        $tmp = $this->dbPath . '.tmp.restore_test';
        @unlink($tmp);
        copy($snapshotPath, $tmp);

        // Release connection
        $db = null;
        // Clear WAL/SHM
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');

        // Replace
        rename($tmp, $this->dbPath);

        // Verify restore
        $restoredDb = getDB();
        $count = (int)$restoredDb->query("SELECT COUNT(*) FROM defecta")->fetchColumn();
        $this->assertEquals(2, $count, 'Restored database should have 2 rows');

        $row = $restoredDb->query("SELECT nama_obat FROM defecta WHERE status='defecta'")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Obat Backup Test', $row['nama_obat']);

        @unlink($snapshotPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
    }

    public function testRestoreRejectsCorruptFile(): void
    {
        $corruptPath = $this->backupDir . '/corrupt.sqlite';
        @mkdir($this->backupDir, 0755, true);
        file_put_contents($corruptPath, 'Not a SQLite file');

        $this->expectException(RuntimeException::class);
        validate_database_backup($corruptPath);

        @unlink($corruptPath);
    }

    public function testRestoreRejectsInvalidSchema(): void
    {
        $badPath = $this->backupDir . '/bad_schema.sqlite';
        @mkdir($this->backupDir, 0755, true);
        $pdo = new PDO('sqlite:' . $badPath);
        $pdo->exec("CREATE TABLE wrong_table (id INTEGER)");
        unset($pdo);

        $this->expectException(RuntimeException::class);
        validate_database_backup($badPath);

        @unlink($badPath);
    }

    public function testValidateRejectsMissingColumns(): void
    {
        $badPath = $this->backupDir . '/missing_cols.sqlite';
        @mkdir($this->backupDir, 0755, true);
        $pdo = new PDO('sqlite:' . $badPath);
        $pdo->exec("CREATE TABLE defecta (id INTEGER, nama_obat TEXT)");
        unset($pdo);

        $this->expectException(RuntimeException::class);
        validate_database_backup($badPath);

        @unlink($badPath);
    }

    public function testWALSnapshotIsComplete(): void
    {
        $db = $this->getDb();

        // Enable WAL mode and add data
        $db->exec('PRAGMA journal_mode=WAL');
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-09-03', 'WAL Test Drug', 'Stock Habis', 'defecta', 'sari', 'sari']);

        // Force WAL checkpoint so data is in main DB
        $db->exec('PRAGMA wal_checkpoint');

        // Create snapshot — should include all data
        $snapshotPath = $this->backupDir . '/wal_snapshot.sqlite';
        @mkdir($this->backupDir, 0755, true);
        @unlink($snapshotPath);
        $db->exec("VACUUM INTO '" . addslashes($snapshotPath) . "'");

        // Verify snapshot contains the data
        $snapDb = new PDO('sqlite:' . $snapshotPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $count = (int)$snapDb->query("SELECT COUNT(*) FROM defecta WHERE nama_obat = 'WAL Test Drug'")->fetchColumn();
        $this->assertEquals(1, $count, 'Snapshot should include all data');
        $total = (int)$snapDb->query("SELECT COUNT(*) FROM defecta")->fetchColumn();
        $this->assertEquals(3, $total); // 2 from setUp + 1 new

        unset($snapDb);
        @unlink($snapshotPath);
    }
}
