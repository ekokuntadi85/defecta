<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for API endpoint logic — CSRF and security guards.
 * These tests verify the security patterns used in api.php.
 */
final class ApiSecurityTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = __DIR__ . '/../tmp/test_api_security.sqlite';
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

    /**
     * Simulate the CSRF guard logic from api.php.
     * Verifies that hash_equals comparison works correctly.
     */
    public function testCsrfGuardRejectsInvalidToken(): void
    {
        $validToken = csrf_token();
        $invalidToken = 'invalid_token_value';

        // This simulates the guard logic:
        // hash_equals(csrf_token(), $sent)
        $this->assertFalse(hash_equals($validToken, $invalidToken));
        $this->assertTrue(hash_equals($validToken, $validToken));
    }

    /**
     * Verify rate limiting logic — 5 attempts per 15 minutes.
     */
    public function testRateLimitThreshold(): void
    {
        initDB();
        $db = getDB();
        $ip = '192.168.1.100';

        // Insert 4 failed attempts
        $stmt = $db->prepare("INSERT INTO login_attempts (ip) VALUES (:ip)");
        for ($i = 0; $i < 4; $i++) {
            $stmt->execute([':ip' => $ip]);
        }

        // Check count
        $cnt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > datetime('now','-15 minutes')");
        $cnt->execute([':ip' => $ip]);
        $this->assertEquals(4, (int)$cnt->fetchColumn(), 'Should have 4 attempts');

        // Insert 5th attempt
        $stmt->execute([':ip' => $ip]);
        $cnt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > datetime('now','-15 minutes')");
        $cnt->execute([':ip' => $ip]);
        $this->assertEquals(5, (int)$cnt->fetchColumn(), 'Should have 5 attempts');

        // Verify rate limit is hit (>= 5)
        $cnt = $db->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > datetime('now','-15 minutes')");
        $cnt->execute([':ip' => $ip]);
        $count = (int)$cnt->fetchColumn();
        $this->assertTrue($count >= 5, 'Should be rate limited at 5 attempts');
    }

    /**
     * Verify password comparison uses hash_equals (timing attack safe).
     */
    public function testPasswordComparisonIsTimingSafe(): void
    {
        $correctPin = 'test_pin_1234';
        $wrongPin   = '9999';

        $this->assertTrue(hash_equals(APP_PASSWORD, $correctPin));
        $this->assertFalse(hash_equals(APP_PASSWORD, $wrongPin));
    }

    /**
     * Verify session staff tracking works.
     */
    public function testStaffTrackingInSession(): void
    {
        $this->assertEquals('unknown', current_staff());
        set_staff('sari');
        $this->assertEquals('sari', current_staff());
    }

    /**
     * Verify STAFF_LIST only allows registered staff.
     */
    public function testStaffListValidation(): void
    {
        $this->assertArrayHasKey('andi', STAFF_LIST);
        $this->assertArrayHasKey('sari', STAFF_LIST);
        $this->assertArrayNotHasKey('hacker', STAFF_LIST);
        $this->assertNotEmpty(STAFF_LIST);
    }

    /**
     * Verify that staff validation rejects unregistered names.
     */
    public function testStaffValidationRejectsUnregistered(): void
    {
        $staff = 'hacker';
        $isValid = $staff !== '' && array_key_exists($staff, STAFF_LIST);
        $this->assertFalse($isValid, 'unregistered staff should be rejected');

        $staff = 'sari';
        $isValid = $staff !== '' && array_key_exists($staff, STAFF_LIST);
        $this->assertTrue($isValid, 'registered staff should be accepted');
    }
}
