<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for database initialization and migration.
 */
final class DatabaseMigrationTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../tmp/test_migration.sqlite';
        @unlink($this->dbPath);
        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        // Reset PDO singleton by using new instance
        $GLOBALS['_test_db_reset'] = true;
    }

    protected function tearDown(): void
    {
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
    }

    public function testInitDBCreatesAllTables(): void
    {
        initDB();
        $db = getDB();

        $tables = $db->query(
            "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('defecta', $tables);
        $this->assertContains('login_attempts', $tables);
    }

    public function testDefectaTableHasAuditTrailColumns(): void
    {
        initDB();
        $db = getDB();

        $cols = $db->query("PRAGMA table_info(defecta)")->fetchAll(PDO::FETCH_ASSOC);
        $colNames = array_column($cols, 'name');

        $this->assertContains('id', $colNames);
        $this->assertContains('tanggal', $colNames);
        $this->assertContains('nama_obat', $colNames);
        $this->assertContains('keterangan', $colNames);
        $this->assertContains('status', $colNames);
        $this->assertContains('created_at', $colNames);
        $this->assertContains('updated_at', $colNames);
        $this->assertContains('created_by', $colNames, 'created_by column should exist');
        $this->assertContains('updated_by', $colNames, 'updated_by column should exist');
    }

    public function testStatusCheckConstraint(): void
    {
        initDB();
        $db = getDB();

        // Insert with valid status
        $stmt = $db->prepare("INSERT INTO defecta (tanggal, nama_obat, keterangan, status) VALUES (?, ?, ?, ?)");
        $stmt->execute(['2026-01-01', 'Test Drug', 'Test', 'defecta']);
        $this->assertEquals(1, $db->query("SELECT COUNT(*) FROM defecta")->fetchColumn());

        // Insert with 'tersedia' status
        $stmt->execute(['2026-01-02', 'Test Drug 2', 'Test', 'tersedia']);
        $this->assertEquals(2, $db->query("SELECT COUNT(*) FROM defecta")->fetchColumn());
    }

    public function testIndexCreation(): void
    {
        initDB();
        $db = getDB();

        $indexes = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='defecta'")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('idx_status', $indexes);
        $this->assertContains('idx_tanggal', $indexes);
        $this->assertContains('idx_nama', $indexes);
    }

    public function testLoginAttemptsTableAndIndex(): void
    {
        initDB();
        $db = getDB();

        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('login_attempts', $tables);

        $indexes = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND tbl_name='login_attempts'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('idx_login_ip', $indexes);
    }
}
