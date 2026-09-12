<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for audit trail and user identity tracking.
 */
final class AuditTrailTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../tmp/test_audit.sqlite';
        @unlink($this->dbPath);
        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
    }

    public function testStaffListHasExpectedEntries(): void
    {
        $this->assertNotEmpty(STAFF_LIST);
        $this->assertArrayHasKey('andi', STAFF_LIST);
        $this->assertArrayHasKey('sari', STAFF_LIST);
        $this->assertArrayHasKey('budi', STAFF_LIST);
        $this->assertArrayHasKey('lina', STAFF_LIST);
        // First entry should be empty (placeholder)
        $this->assertArrayHasKey('', STAFF_LIST);
    }

    public function testSetAndGetStaff(): void
    {
        set_staff('andi');
        $this->assertEquals('andi', current_staff());

        set_staff('sari');
        $this->assertEquals('sari', current_staff());
    }

    public function testDefaultStaffIsUnknown(): void
    {
        $_SESSION = [];
        $this->assertEquals('unknown', current_staff());
    }

    public function testDefectaTableHasCreatedByColumn(): void
    {
        initDB();
        $db = getDB();

        $cols = $db->query("PRAGMA table_info(defecta)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($cols, 'name');

        $this->assertContains('created_by', $colNames);
        $this->assertContains('updated_by', $colNames);
    }

    public function testDefectaTableHasCreatedByWithFreshDatabase(): void
    {
        // Fresh database (table just created)
        initDB();
        $db = getDB();

        // Insert with created_by
        $stmt = $db->prepare("INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['2026-09-13', 'TestDrug', 'Stock Habis', 'defecta', 'budi', 'budi']);

        $row = $db->query("SELECT * FROM defecta WHERE nama_obat='TestDrug'")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('budi', $row['created_by']);
        $this->assertEquals('budi', $row['updated_by']);
    }

    public function testMigrationAddsAuditColumnsToExistingTable(): void
    {
        // Use a unique path so getDB() creates a fresh connection
        $dbPath = __DIR__ . '/../tmp/test_migration_audit.sqlite';
        @unlink($dbPath);
        putenv('DB_SQLITE_PATH=' . $dbPath);

        // Force getDB() to reconnect by changing the path
        $db = getDB();

        // Create defecta table WITHOUT audit columns (simulating old schema)
        $db->exec("
            CREATE TABLE defecta (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                tanggal     TEXT NOT NULL,
                nama_obat   TEXT NOT NULL,
                keterangan  TEXT NOT NULL,
                status      TEXT NOT NULL DEFAULT 'defecta',
                created_at  TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at  TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Verify old table has no created_by
        $cols = $db->query("PRAGMA table_info(defecta)")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        $this->assertNotContains('created_by', $names);

        // Run initDB() — should migrate and add columns
        initDB();

        $cols = $db->query("PRAGMA table_info(defecta)")->fetchAll(PDO::FETCH_ASSOC);
        $names = array_column($cols, 'name');
        $this->assertContains('created_by', $names, 'Migration should add created_by');
        $this->assertContains('updated_by', $names, 'Migration should add updated_by');

        // Clean up
        @unlink($dbPath);
        @unlink($dbPath . '-wal');
        @unlink($dbPath . '-shm');
    }
}
