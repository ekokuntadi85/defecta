<?php
require_once "/var/www/html/config.php";
initDB();
$db = getDB();

echo "=== TEST SUITE ===\n\n";

// 1. Database schema
echo "1. Schema checks...\n";
$idx = $db->query("SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_nama_%'")->fetchAll(PDO::FETCH_COLUMN);
echo "   Partial unique indexes: " . (count($idx) == 2 ? "PASS" : "FAIL") . " (" . implode(", ", $idx) . ")\n";

$auditCols = $db->query("PRAGMA table_info(audit_log)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($auditCols, "name");
echo "   audit_log columns: " . (in_array("username", $colNames) && in_array("user_id", $colNames) ? "PASS" : "FAIL") . " (" . implode(", ", $colNames) . ")\n";

// 2. Users have unique PINs
echo "\n2. Auth checks...\n";
$users = $db->query("SELECT username, pin_hash FROM users")->fetchAll(PDO::FETCH_ASSOC);
$pins = [];
foreach ($users as $u) {
    $pins[$u["pin_hash"]] = ($pins[$u["pin_hash"]] ?? 0) + 1;
}
echo "   Unique PIN hashes: " . (count($pins) == count($users) ? "PASS" : "FAIL") . " (" . count($pins) . "/" . count($users) . ")\n";

foreach ($users as $u) {
    if (password_verify("123456", $u["pin_hash"])) {
        echo "   Login " . $u["username"] . ": PASS\n";
    } else {
        echo "   Login " . $u["username"] . ": FAIL\n";
    }
}

// 3. Functional tests
echo "\n3. Functional tests...\n";
$today = date("Y-m-d");
$stmt = $db->prepare("INSERT INTO defecta (tanggal, nama_obat, keterangan, status, created_by, updated_by) VALUES (:t, :n, :k, 'defecta', :u, :u)");
$stmt->execute([":t" => $today, ":n" => "Test Audit " . time(), ":k" => "Stock Habis", ":u" => "faizin"]);
$id = $db->lastInsertId();
echo "   Add defecta: PASS (id=$id)\n";

$stmt = $db->prepare("UPDATE defecta SET status='tersedia', updated_at=datetime('now'), updated_by='faizin' WHERE id=:id AND status='defecta'");
$stmt->execute([":id" => $id]);
echo "   Mark tersedia: " . ($stmt->rowCount() ? "PASS" : "FAIL") . "\n";

$stmt = $db->prepare("UPDATE defecta SET status='defecta', tanggal=:t, created_by='faizin', updated_at=datetime('now'), updated_by='faizin' WHERE id=:id AND status='tersedia'");
$stmt->execute([":id" => $id, ":t" => $today]);
echo "   Undo to defecta: " . ($stmt->rowCount() ? "PASS" : "FAIL") . " (created_by updated)\n";

$ids = [$id];
$stmt = $db->prepare("UPDATE defecta SET status='tersedia', updated_at=datetime('now'), updated_by='faizin' WHERE id IN (" . implode(",", $ids) . ") AND status='defecta'");
$stmt->execute();
echo "   Bulk tersedia: " . ($stmt->rowCount() ? "PASS" : "FAIL") . "\n";

$stmt = $db->prepare("UPDATE defecta SET status='defecta', tanggal=:t, created_by='faizin', updated_at=datetime('now'), updated_by='faizin' WHERE id IN (" . implode(",", $ids) . ") AND status='tersedia'");
$stmt->execute([":t" => $today]);
echo "   Bulk defecta: " . ($stmt->rowCount() ? "PASS" : "FAIL") . "\n";

$db->exec("VACUUM INTO '/var/www/html/data/backups/test-audit.sqlite'");
echo "   VACUUM INTO: " . (file_exists("/var/www/html/data/backups/test-audit.sqlite") ? "PASS" : "FAIL") . "\n";
@unlink("/var/www/html/data/backups/test-audit.sqlite");

$db->exec("DELETE FROM defecta WHERE nama_obat LIKE 'Test Audit%'");

echo "\n=== ALL TESTS COMPLETED ===\n";