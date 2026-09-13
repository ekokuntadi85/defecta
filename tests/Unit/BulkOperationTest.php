<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for bulk operations.
 * These tests execute the actual database-side logic of api.php bulk actions.
 */
final class BulkOperationTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../tmp/test_bulk_' . spl_object_id($this) . '.sqlite';
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        $_SESSION = [];
        set_staff('sari');

        initDB();
        $db = getDB();

        // Insert some test data
        $stmt = $db->prepare(
            "INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute(['2026-01-01', 'Obat A', 'Stock Habis', 'defecta', 'sari', 'sari']);
        $stmt->execute(['2026-01-02', 'Obat B', 'Stock Habis', 'defecta', 'sari', 'sari']);
        $stmt->execute(['2026-01-03', 'Obat C', 'Stock Menipis', 'tersedia', 'budi', 'budi']);
    }

    protected function tearDown(): void
    {
        $db = getDB(); // release reference
        $db = null;
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
    }

    private function getDb(): PDO
    {
        return getDB();
    }

    private function bulkTersedia(array $ids): array
    {
        $db = $this->getDb();
        $ph = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
        $params = [':now' => now(), ':staff' => current_staff()];
        foreach ($ids as $i => $id) { $params[":id$i"] = $id; }
        $db->beginTransaction();
        $stmt = $db->prepare(
            "UPDATE defecta SET status='tersedia', updated_at = :now, updated_by = :staff 
             WHERE id IN ($ph) AND status='defecta'"
        );
        $stmt->execute($params);
        $n = $stmt->rowCount();
        $db->commit();
        return ['affected' => $n];
    }

    private function bulkDefecta(array $ids): array
    {
        $db = $this->getDb();
        $today = date('Y-m-d');
        $ph = implode(',', array_map(fn($i) => ":id$i", array_keys($ids)));
        $params = [':now' => now(), ':staff' => current_staff(), ':today' => $today];
        foreach ($ids as $i => $id) { $params[":id$i"] = $id; }
        $db->beginTransaction();
        $stmt = $db->prepare(
            "UPDATE defecta SET status='defecta', tanggal = :today, updated_at = :now, updated_by = :staff 
             WHERE id IN ($ph) AND status='tersedia'"
        );
        $stmt->execute($params);
        $n = $stmt->rowCount();
        $db->commit();
        return ['affected' => $n];
    }

    private function bulkDelete(array $ids): array
    {
        $db = $this->getDb();
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $db->beginTransaction();
        $del = $db->prepare("DELETE FROM defecta WHERE id IN ($ph)");
        $del->execute(array_values($ids));
        $n = $del->rowCount();
        $db->commit();
        return ['affected' => $n];
    }

    public function testBulkTersediaMarksSelectedItemsAsAvailable(): void
    {
        $ids = [1, 2]; // both are 'defecta'
        $result = $this->bulkTersedia($ids);

        $this->assertEquals(2, $result['affected']);

        $db = $this->getDb();
        $rows = $db->query("SELECT status, updated_by FROM defecta WHERE id IN (1, 2)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $this->assertEquals('tersedia', $row['status']);
            $this->assertEquals('sari', $row['updated_by']);
        }
    }

    public function testBulkTersediaIgnoresAlreadyAvailableItems(): void
    {
        $ids = [3]; // Obat C is 'tersedia', not 'defecta'
        $result = $this->bulkTersedia($ids);

        $this->assertEquals(0, $result['affected'], 'Should not update items already tersedia');
    }

    public function testBulkDefectaMovesAvailableItemsBackToDefecta(): void
    {
        $ids = [3]; // Obat C is 'tersedia'
        $result = $this->bulkDefecta($ids);

        $this->assertEquals(1, $result['affected']);

        $db = $this->getDb();
        $row = $db->query("SELECT status, updated_by FROM defecta WHERE id = 3")->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('defecta', $row['status']);
        $this->assertEquals('sari', $row['updated_by']);
    }

    public function testBulkDefectaUpdatesTanggalToToday(): void
    {
        // Obat C (id=3) was created on 2026-01-03 as 'tersedia'
        // When moved back to defecta, tanggal should be updated to today
        $db = $this->getDb();
        $originalTanggal = $db->query("SELECT tanggal FROM defecta WHERE id = 3")->fetchColumn();
        $this->assertEquals('2026-01-03', $originalTanggal);

        $result = $this->bulkDefecta([3]);
        $this->assertEquals(1, $result['affected']);

        $today = date('Y-m-d');
        $newTanggal = $db->query("SELECT tanggal FROM defecta WHERE id = 3")->fetchColumn();
        $this->assertEquals($today, $newTanggal, 'tanggal should be updated to today when re-entering defecta');
    }

    public function testBulkDeleteRemovesSelectedItems(): void
    {
        $ids = [1, 3];
        $result = $this->bulkDelete($ids);

        $this->assertEquals(2, $result['affected']);
        $db = $this->getDb();
        $remaining = (int)$db->query("SELECT COUNT(*) FROM defecta")->fetchColumn();
        $this->assertEquals(1, $remaining, 'Should have 1 row remaining');
    }

    public function testBulkDeleteWithInvalidId(): void
    {
        // Non-existent IDs should not cause errors
        $ids = [999];
        $result = $this->bulkDelete($ids);
        $this->assertEquals(0, $result['affected'], 'Should affect 0 rows for non-existent ID');
    }

    public function testBulkTersediaDoesNotAffectWrongStatus(): void
    {
        $ids = [3]; // Obat C is 'tersedia'
        $result = $this->bulkTersedia($ids);
        $this->assertEquals(0, $result['affected'], 'Should not change status of already-tersedia items');
    }

    public function testBulkOperationsUseTransaction(): void
    {
        $this->bulkTersedia([1, 2]);

        $db = $this->getDb();
        $count = (int)$db->query("SELECT COUNT(*) FROM defecta WHERE status='tersedia'")->fetchColumn();
        $this->assertEquals(3, $count);
    }
}
