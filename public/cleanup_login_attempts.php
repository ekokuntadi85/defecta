<?php
/**
 * Cleanup old login_attempts (older than 1 hour).
 * Run via cron at 02:15 daily.
 */
require_once __DIR__ . '/config.php';

try {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE attempted_at < datetime('now','-1 hour')");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    error_log("[defecta] Cleaned up $deleted old login_attempts");
} catch (Throwable $e) {
    error_log("[defecta] Cleanup failed: " . $e->getMessage());
}