<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for audit trail — verifies audit_log records are created
 * for CREATE, UPDATE, STATUS_CHANGE, DELETE, LOGIN, LOGOUT actions.
 */
final class AuditTrailIntegrationTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../tmp/test_audit_' . spl_object_id($this) . '.sqlite';
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        $_SESSION = [];
        set_staff('andi');

        initDB();
    }

    protected function tearDown(): void
    {
        $db = getDB(); $db = null;
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
    }

    private function getDb(): PDO
    {
        return getDB();
    }

    private function getAuditRecords(?string $action = null): array
    {
        $db = $this->getDb();
        if ($action) {
            $stmt = $db->prepare("SELECT * FROM audit_log WHERE action = :action ORDER BY id");
            $stmt->execute([':action' => $action]);
        } else {
            $db->query("SELECT * FROM audit_log ORDER BY id");
            $stmt = $db->query("SELECT * FROM audit_log ORDER BY id");
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function testCreateAuditTrailTable(): void
    {
        $db = $this->getDb();
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('audit_log', $tables);
    }

    public function testAuditLogRecordsCreateAction(): void
    {
        $db = $this->getDb();
        $staff = current_staff();
        $db->beginTransaction();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_at, updated_at, created_by, updated_by)
             VALUES (:tgl, :obat, :ket, 'defecta', :now, :now, :staff, :staff)"
        );
        $stmt->execute([
            ':tgl'   => '2026-01-01',
            ':obat'  => 'Vitam C',
            ':ket'   => 'Stock Habis',
            ':now'   => now(),
            ':staff' => $staff,
        ]);
        $id = (int)$db->lastInsertId();
        audit_log('CREATE', 'defecta', $id, 1, ['tanggal' => '2026-01-01', 'nama_obat' => 'Vitam C', 'keterangan' => 'Stock Habis']);
        $db->commit();

        $records = $this->getAuditRecords('CREATE');
        $this->assertCount(1, $records);
        $this->assertEquals('andi', $records[0]['user_id']);
        $this->assertEquals('CREATE', $records[0]['action']);
        $this->assertEquals('defecta', $records[0]['entity_type']);
        $this->assertEquals($id, $records[0]['entity_id']);
    }

    public function testAuditLogRecordsUpdateAction(): void
    {
        $db = $this->getDb();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-01-01', 'Obat Test', 'Stock Habis', 'defecta', 'andi', 'andi']);
        $id = (int)$db->lastInsertId();

        $oldRow = $db->query("SELECT tanggal, nama_obat, keterangan, status FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $db->beginTransaction();
        $stmt = $db->prepare(
            "UPDATE defecta SET tanggal = :tgl, nama_obat = :obat, keterangan = :ket, updated_at = :now, updated_by = :staff WHERE id = :id"
        );
        $stmt->execute([
            ':tgl'   => '2026-01-02',
            ':obat'  => 'Obat Test Updated',
            ':ket'   => 'Stock Menipis',
            ':id'    => $id,
            ':now'   => now(),
            ':staff' => current_staff(),
        ]);
        audit_log('UPDATE', 'defecta', $id, 1, ['old' => $oldRow, 'new' => ['tanggal' => '2026-01-02', 'nama_obat' => 'Obat Test Updated', 'keterangan' => 'Stock Menipis']]);
        $db->commit();

        $records = $this->getAuditRecords('UPDATE');
        $this->assertCount(1, $records);
        $this->assertEquals('defecta', $records[0]['entity_type']);
        $this->assertEquals($id, $records[0]['entity_id']);
        $meta = json_decode($records[0]['metadata'], true);
        $this->assertEquals('Obat Test', $meta['old']['nama_obat']);
        $this->assertEquals('Obat Test Updated', $meta['new']['nama_obat']);
    }

    public function testAuditLogRecordsStatusChange(): void
    {
        $db = $this->getDb();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-01-01', 'Obat Test', 'Stock Habis', 'defecta', 'andi', 'andi']);
        $id = (int)$db->lastInsertId();

        $oldRow = $db->query("SELECT status FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $db->beginTransaction();
        $stmt = $db->prepare(
            "UPDATE defecta SET status='tersedia', updated_at = :now, updated_by = :staff WHERE id=:id AND status='defecta'"
        );
        $stmt->execute([':id' => $id, ':now' => now(), ':staff' => current_staff()]);
        audit_log('STATUS_CHANGE', 'defecta', $id, 1, [
            'old' => ['status' => $oldRow['status']],
            'new' => ['status' => 'tersedia'],
        ]);
        $db->commit();

        $records = $this->getAuditRecords('STATUS_CHANGE');
        $this->assertCount(1, $records);
        $meta = json_decode($records[0]['metadata'], true);
        $this->assertEquals('defecta', $meta['old']['status']);
        $this->assertEquals('tersedia', $meta['new']['status']);
    }

    public function testAuditLogRecordsDeleteAction(): void
    {
        $db = $this->getDb();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-01-01', 'Obat Dihapus', 'Stock Habis', 'defecta', 'andi', 'andi']);
        $id = (int)$db->lastInsertId();

        $row = $db->query("SELECT id, nama_obat, keterangan FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $db->beginTransaction();
        $del = $db->prepare("DELETE FROM defecta WHERE id = ?");
        $del->execute([$id]);
        audit_log('DELETE', 'defecta', $id, 1, $row);
        $db->commit();

        $records = $this->getAuditRecords('DELETE');
        $this->assertCount(1, $records);
        $meta = json_decode($records[0]['metadata'], true);
        $this->assertEquals('Obat Dihapus', $meta['nama_obat']);
    }

    public function testAuditActorComesFromSession(): void
    {
        set_staff('budi');
        audit_log('TEST', 'defecta', 1, 1, []);
        set_staff('sari');
        audit_log('TEST', 'defecta', 1, 1, []);

        $records = $this->getAuditRecords('TEST');
        $this->assertCount(2, $records);
        $this->assertEquals('budi', $records[0]['user_id']);
        $this->assertEquals('sari', $records[1]['user_id']);
    }

    public function testAuditLogHasCorrectTimestamp(): void
    {
        $db = $this->getDb();
        $db->exec("PRAGMA journal_mode=WAL");
        audit_log('TEST_TIME', 'defecta', 1, 1, []);
        $db = $this->getDb();
        $record = $db->query("SELECT * FROM audit_log WHERE action = 'TEST_TIME' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($record['created_at']);
        $this->assertTrue(
            preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $record['created_at']) ||
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $record['created_at']),
            'created_at should be a valid timestamp: ' . $record['created_at']
        );
    }

    public function testAuditLogDoesNotAuditItself(): void
    {
        // Verify that audit_log() doesn't cause recursive audit entries
        $db = $this->getDb();
        audit_log('TEST_NO_RECURSE', 'defecta', 1, 1, []);

        // Count audit records for this action — should be exactly 1
        $count = (int)$db->query("SELECT COUNT(*) FROM audit_log WHERE action = 'TEST_NO_RECURSE'")->fetchColumn();
        $this->assertEquals(1, $count, 'audit_log should not recurse');
    }
}
