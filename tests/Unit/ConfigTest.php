<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for config.php helper functions.
 */
final class ConfigTest extends TestCase
{
    public function testCsrfTokenIsGeneratedAndConsistent(): void
    {
        $token1 = csrf_token();
        $token2 = csrf_token();
        $this->assertSame($token1, $token2, 'CSRF token should be consistent within session');
        $this->assertEquals(64, strlen($token1), 'CSRF token should be 64 hex chars');
    }

    public function testStaffListIsDefined(): void
    {
        $this->assertIsArray(STAFF_LIST);
        $this->assertArrayHasKey('andi', STAFF_LIST);
        $this->assertArrayHasKey('sari', STAFF_LIST);
        $this->assertEquals('Andi (Apoteker)', STAFF_LIST['andi']);
    }

    public function testIsNotLoggedInByDefault(): void
    {
        $this->assertFalse(is_logged_in());
    }

    public function testSetAndGetStaff(): void
    {
        set_staff('sari');
        $this->assertEquals('sari', current_staff());
    }

    public function testDefaultStaffIsUnknown(): void
    {
        // Reset session
        $_SESSION = [];
        $this->assertEquals('unknown', current_staff());
    }

    public function testNowReturnsValidDateTime(): void
    {
        $now = now();
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $now,
            'now() should return Y-m-d H:i:s format'
        );
        // Verify it's a valid date
        $dt = date_create($now);
        $this->assertInstanceOf(DateTimeInterface::class, $dt);
    }

    public function testAppPasswordFromEnv(): void
    {
        $this->assertEquals('test_pin_1234', APP_PASSWORD);
    }
}
