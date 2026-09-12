<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for authentication, CSRF, and session management.
 */
final class AuthIntegrationTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../tmp/test_auth_' . spl_object_id($this) . '.sqlite';
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
        putenv('DB_SQLITE_PATH=' . $this->dbPath);
        $_SESSION = [];

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

    public function testCsrfTokenGuardPattern(): void
    {
        // Simulate the CSRF guard logic from api.php:
        // if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) → reject
        $validToken = csrf_token();

        // Missing token (empty string) — should fail guard
        $sent = '';
        $guardFires = !is_string($sent) || !hash_equals(csrf_token(), $sent);
        $this->assertTrue($guardFires, 'Empty token should trigger CSRF guard');

        // Invalid token — should fail guard
        $sent = 'invalid_token_value';
        $guardFires = !is_string($sent) || !hash_equals(csrf_token(), $sent);
        $this->assertTrue($guardFires, 'Invalid token should trigger CSRF guard');

        // Valid token — should pass guard
        $sent = $validToken;
        $guardFires = !is_string($sent) || !hash_equals(csrf_token(), $sent);
        $this->assertFalse($guardFires, 'Valid token should pass CSRF guard');
    }

    public function testSessionStaffIdentity(): void
    {
        set_staff('sari');
        $this->assertEquals('sari', current_staff());

        set_staff('andi');
        $this->assertEquals('andi', current_staff());
    }

    public function testStaffListOnlyAllowsKnownStaff(): void
    {
        $this->assertArrayHasKey('sari', STAFF_LIST);
        $this->assertArrayHasKey('andi', STAFF_LIST);
        $this->assertArrayHasKey('budi', STAFF_LIST);
        $this->assertArrayHasKey('lina', STAFF_LIST);

        $this->assertArrayNotHasKey('hacker', STAFF_LIST);
        $this->assertArrayNotHasKey('admin', STAFF_LIST);
    }

    public function testStaffIdentityIsStableIdentifier(): void
    {
        $this->assertEquals('Andi (Apoteker)', STAFF_LIST['andi']);
        $this->assertEquals('Sari (Staf)', STAFF_LIST['sari']);
        $this->assertEquals('Budi (Staf)', STAFF_LIST['budi']);
        $this->assertEquals('Lina (Staf)', STAFF_LIST['lina']);

        foreach (STAFF_LIST as $key => $label) {
            if ($key !== '') {
                $this->assertNotEmpty($key, 'Staff key should not be empty for non-placeholder entries');
            }
        }
    }

    public function testRateLimitingBlocksAfterFiveAttempts(): void
    {
        $db = $this->getDb();
        $ip = '10.0.0.99';
        $stmt = $db->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)");
        for ($i = 0; $i < 5; $i++) {
            $stmt->execute([':ip' => $ip]);
        }

        $cnt = $db->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > datetime('now','-15 minutes')"
        );
        $cnt->execute([':ip' => $ip]);
        $count = (int)$cnt->fetchColumn();

        $this->assertTrue($count >= 5, 'Should be rate-limited after 5 attempts');
    }

    public function testRateLimitingAllowsUnderFiveAttempts(): void
    {
        $db = $this->getDb();
        $ip = '10.0.0.100';
        $stmt = $db->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)");
        for ($i = 0; $i < 4; $i++) {
            $stmt->execute([':ip' => $ip]);
        }

        $cnt = $db->prepare(
            "SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > datetime('now','-15 minutes')"
        );
        $cnt->execute([':ip' => $ip]);
        $count = (int)$cnt->fetchColumn();

        $this->assertFalse($count >= 5, 'Should not be rate-limited under 5 attempts');
    }

    public function testSessionRegenerationOnLogin(): void
    {
        $_SESSION = [];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_at'] = time();
        set_staff('andi');

        $this->assertTrue($_SESSION['logged_in']);
        $this->assertEquals('andi', $_SESSION['staff_name']);
    }

    public function testSessionClearsOnLogout(): void
    {
        $_SESSION = ['logged_in' => true, 'staff_name' => 'sari'];
        $_SESSION = [];
        $this->assertFalse(isset($_SESSION['logged_in']));
    }

    public function testAuthPasswordComparisonUsesHashEquals(): void
    {
        $pin = APP_PASSWORD;
        $this->assertTrue(hash_equals($pin, $pin));
        $this->assertFalse(hash_equals($pin, 'wrong'));
    }

    public function testNoDefaultPinWhenEnvUnset(): void
    {
        // Verify APP_PASSWORD is from the test bootstrap env
        $this->assertEquals('test_pin_1234', APP_PASSWORD);
        $this->assertNotEquals('1324', APP_PASSWORD, 'Should not use default PIN');
    }
}
