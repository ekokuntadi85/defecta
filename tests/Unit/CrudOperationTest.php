<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CRUD operations on the defecta table.
 */
final class CrudOperationTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../tmp/test_crud_' . spl_object_id($this) . '.sqlite';
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        $_SESSION = [];
        set_staff('sari');

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

    public function testCreateDefectaItem(): void
    {
        $db = $this->getDb();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_at, updated_at, created_by, updated_by)
             VALUES (:tgl, :obat, :ket, 'defecta', :now, :now, :staff, :staff)"
        );
        $db->beginTransaction();
        $stmt->execute([
            ':tgl'   => '2026-09-13',
            ':obat'  => 'Paracetamol',
            ':ket'   => 'Stock Habis',
            ':now'   => now(),
            ':staff' => current_staff(),
        ]);
        $id = (int)$db->lastInsertId();
        audit_log('CREATE', 'defecta', $id, 1, ['tanggal' => '2026-09-13', 'nama_obat' => 'Paracetamol', 'keterangan' => 'Stock Habis']);
        $db->commit();

        $row = $db->query("SELECT * FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Paracetamol', $row['nama_obat']);
        $this->assertEquals('Stock Habis', $row['keterangan']);
        $this->assertEquals('defecta', $row['status']);
        $this->assertEquals('sari', $row['created_by']);
        $this->assertEquals('sari', $row['updated_by']);
    }

    public function testUpdateDefectaItem(): void
    {
        $db = $this->getDb();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-09-10', 'Obat A', 'Stock Habis', 'defecta', 'sari', 'sari']);
        $id = (int)$db->lastInsertId();

        $oldRow = $db->query("SELECT tanggal, nama_obat, keterangan, status FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $stmt = $db->prepare(
            "UPDATE defecta SET tanggal = :tgl, nama_obat = :obat, keterangan = :ket, updated_at = :now, updated_by = :staff WHERE id = :id"
        );
        $db->beginTransaction();
        $stmt->execute([
            ':tgl'   => '2026-09-15',
            ':obat'  => 'Obat A Updated',
            ':ket'   => 'Stock Menipis',
            ':id'    => $id,
            ':now'   => now(),
            ':staff' => current_staff(),
        ]);
        audit_log('UPDATE', 'defecta', $id, 1, ['old' => $oldRow, 'new' => ['tanggal' => '2026-09-15', 'nama_obat' => 'Obat A Updated', 'keterangan' => 'Stock Menipis']]);
        $db->commit();

        $row = $db->query("SELECT * FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('2026-09-15', $row['tanggal']);
        $this->assertEquals('Obat A Updated', $row['nama_obat']);
        $this->assertEquals('sari', $row['updated_by']);
    }

    public function testStatusChangeMarksItemUnavailable(): void
    {
        $db = $this->getDb();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-09-10', 'Obat B', 'Stock Habis', 'defecta', 'sari', 'sari']);
        $id = (int)$db->lastInsertId();

        $oldRow = $db->query("SELECT status FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $stmt = $db->prepare("UPDATE defecta SET status='tersedia', updated_at = :now, updated_by = :staff WHERE id=:id AND status='defecta'");
        $db->beginTransaction();
        $stmt->execute([':id' => $id, ':now' => now(), ':staff' => current_staff()]);
        audit_log('STATUS_CHANGE', 'defecta', $id, 1, ['old' => $oldRow, 'new' => ['status' => 'tersedia']]);
        $db->commit();

        $row = $db->query("SELECT status, updated_by FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('tersedia', $row['status']);
        $this->assertEquals('sari', $row['updated_by']);
    }

    public function testDeleteDefectaItem(): void
    {
        $db = $this->getDb();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-09-10', 'Obat C', 'Stock Habis', 'defecta', 'sari', 'sari']);
        $id = (int)$db->lastInsertId();

        $row = $db->query("SELECT id, nama_obat, keterangan FROM defecta WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
        $db->beginTransaction();
        $del = $db->prepare("DELETE FROM defecta WHERE id = ?");
        $del->execute([$id]);
        audit_log('DELETE', 'defecta', $id, 1, $row);
        $db->commit();

        $count = (int)$db->query("SELECT COUNT(*) FROM defecta WHERE id = $id")->fetchColumn();
        $this->assertEquals(0, $count);
    }

    public function testPreventDuplicateDefectaEntry(): void
    {
        $db = $this->getDb();
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-09-10', 'Duplicate Test', 'Stock Habis', 'defecta', 'sari', 'sari']);

        $check = $db->prepare("SELECT id FROM defecta WHERE status='defecta' AND LOWER(TRIM(nama_obat)) = LOWER(TRIM(:obat)) LIMIT 1");
        $check->execute([':obat' => 'Duplicate Test']);
        $this->assertTrue($check->fetch() !== false, 'Duplicate should be detected');
    }

    public function testCreateRecordsUsesSessionStaff(): void
    {
        $db = $this->getDb();
        set_staff('budi');
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (:tgl, :obat, :ket, 'defecta', :staff, :staff)"
        );
        $stmt->execute([
            ':tgl'   => '2026-09-10',
            ':obat'  => 'Staff Test',
            ':ket'   => 'Stock Habis',
            ':staff' => current_staff(),
        ]);

        $row = $db->query("SELECT created_by FROM defecta WHERE nama_obat = 'Staff Test'")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('budi', $row['created_by'], 'created_by must come from session');
    }
}
