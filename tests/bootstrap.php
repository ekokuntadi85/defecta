<?php
/**
 * Test bootstrap — sets up environment for unit tests.
 */

// Set up environment for tests
putenv('APP_PASSWORD=test_pin_1234');
putenv('DB_SQLITE_PATH=' . __DIR__ . '/../tmp/test_defecta.sqlite');

// Create tmp dir for test database
@mkdir(__DIR__ . '/../tmp', 0777, true);

// Remove any existing test db
$dbPath = __DIR__ . '/../tmp/test_defecta.sqlite';
@unlink($dbPath);
@unlink($dbPath . '.wal');
@unlink($dbPath . '-shm');

// Initialize session superglobal (needed for csrf_token, staff tracking)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION = [];

// Include the config file under test
require_once __DIR__ . '/../public/config.php';
