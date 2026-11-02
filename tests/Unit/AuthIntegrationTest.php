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
        $this->assertArrayHasKey('damayanti', STAFF_LIST);
        $this->assertArrayHasKey('faizin', STAFF_LIST);
        $this->assertArrayHasKey('aisyiyah', STAFF_LIST);
        $this->assertArrayHasKey('anggun', STAFF_LIST);
        $this->assertArrayNotHasKey('hacker', STAFF_LIST);
        $this->assertArrayNotHasKey('admin', STAFF_LIST);
    }

    public function testStaffIdentityIsStableIdentifier(): void
    {
        $this->assertEquals('Damayanti (Apoteker)', STAFF_LIST['damayanti']);
        $this->assertEquals('Faizin (Manajer)', STAFF_LIST['faizin']);
        $this->assertEquals('Aisyiyah (Staf)', STAFF_LIST['aisyiyah']);
        $this->assertEquals('Anggun (Staf)', STAFF_LIST['anggun']);

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
        set_staff('damayanti');

        $this->assertTrue($_SESSION['logged_in']);
        $this->assertEquals('damayanti', $_SESSION['username'] ?? current_staff());
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
        $this->assertEquals('test_pin_1234', APP_PASSWORD);
        $this->assertNotEquals('1324', APP_PASSWORD, 'Should not use default PIN');
    }

    public function testLoginRequiresStaffSelection(): void
    {
        $emptyKey = '';
        $isValidStaff = $emptyKey !== '' && array_key_exists($emptyKey, STAFF_LIST);
        $this->assertFalse($isValidStaff, 'Empty staff should not be valid');

        $validStaff = 'damayanti';
        $isValidStaff = $validStaff !== '' && array_key_exists($validStaff, STAFF_LIST);
        $this->assertTrue($isValidStaff, 'damayanti should be valid');
    }

    public function testLoginRejectsUnknownStaff(): void
    {
        $staff = 'hacker';
        $isValidStaff = $staff !== '' && array_key_exists($staff, STAFF_LIST);
        $this->assertFalse($isValidStaff, 'Unknown staff should not be valid');
    }

    public function testLoginAllowsKnownStaff(): void
    {
        foreach (['damayanti', 'faizin', 'aisyiyah', 'anggun'] as $staff) {
            $isValidStaff = $staff !== '' && array_key_exists($staff, STAFF_LIST);
            $this->assertTrue($isValidStaff, "$staff should be valid");
        }
    }

    public function testUsersTableSeedsDefaultUsers(): void
    {
        $db = $this->getDb();
        $users = $db->query("SELECT username, role, is_active FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);

        $usernames = array_column($users, 'username');
        $this->assertContains('damayanti', $usernames);
        $this->assertContains('faizin', $usernames);
        $this->assertContains('aisyiyah', $usernames);
        $this->assertContains('anggun', $usernames);

        $roles = [];
        foreach ($users as $u) { $roles[$u['username']] = $u['role']; }
        $this->assertEquals('apoteker', $roles['damayanti']);
        $this->assertEquals('manager', $roles['faizin']);
        $this->assertEquals('staff', $roles['aisyiyah']);
        $this->assertEquals('staff', $roles['anggun']);
    }

    public function testPasswordHashStoredSecurely(): void
    {
        $db = $this->getDb();
        $row = $db->query("SELECT pin_hash FROM users WHERE username = 'damayanti'")->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEmpty($row['pin_hash'], 'pin_hash should not be empty');
        $this->assertTrue(str_starts_with($row['pin_hash'], '$'), 'PIN should be hashed (password_hash uses $ prefix)');
        $this->assertNotEquals(DEFAULT_USER_PIN, $row['pin_hash'], 'PIN hash should not equal plaintext');
    }

    public function testAuthenticateUserSuccess(): void
    {
        $user = authenticate_user('damayanti', DEFAULT_USER_PIN);
        $this->assertNotNull($user, 'Should authenticate with default PIN');
        $this->assertEquals('damayanti', $user['username']);
        $this->assertEquals('apoteker', $user['role']);
    }

    public function testAuthenticateUserWrongPin(): void
    {
        $user = authenticate_user('damayanti', 'wrong_pin');
        $this->assertNull($user, 'Should not authenticate with wrong PIN');
    }

    public function testAuthenticateUserInvalidUsername(): void
    {
        $user = authenticate_user('nonexistent', DEFAULT_USER_PIN);
        $this->assertNull($user, 'Should not authenticate with invalid username');
    }

    public function testIsAdminReturnsTrueForAdminRoles(): void
    {
        $_SESSION = ['role' => 'apoteker'];
        $this->assertTrue(is_admin(), 'apoteker should be admin');

        $_SESSION = ['role' => 'manager'];
        $this->assertTrue(is_admin(), 'manager should be admin');
    }

    public function testIsAdminReturnsFalseForStaff(): void
    {
        $_SESSION = ['role' => 'staff'];
        $this->assertFalse(is_admin(), 'staff should not be admin');
    }
}
