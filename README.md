SOFTWARE DEFECTA — DOCKER SETUP
===============================

This app is containerized so it runs on any machine that has Docker +
Docker Compose, with no manual PHP install.

Files:
  Dockerfile            - Alpine + Apache + mod_php image (the web server)
  docker-compose.yml    - one service: the web app (no separate DB server)
  docker/httpd-*.conf   - Apache tweaks (docroot, .htaccess, rewrite, logs)
  .env.example          - config template (app PIN + SQLite path)

WHY SQLITE + ALPINE?
--------------------
- One container, one file as the whole database. No DB server to run,
  secure, or password-protect. Ideal for this single-pharmacy app.
- Alpine Linux makes the image ~5x smaller than the old PHP+MariaDB stack
  (about 150 MB vs 715 MB) and much lighter on RAM at runtime.

HOW TO RUN
----------
1. Install Docker Desktop (Windows/Mac) or Docker Engine (Linux).
2. Create the env file:
       cp .env.example .env
   Edit .env to set APP_PIN to a strong PIN.
3. Build and start:
       docker compose up -d --build
4. Open http://localhost:8080 in a browser.
   Log in with the PIN you set as APP_PIN in .env.
   The database table is created automatically on the first API call.

IMPORTANT: There is NO default PIN. If APP_PASSWORD / APP_PIN is not set
in the environment, login will fail with an error message directing the
user to contact the administrator.

STOP / REMOVE
-------------
- Stop (data is kept in the volume):
       docker compose down
- Stop AND erase all data:
       docker compose down -v

WHERE THE DATA LIVES
--------------------
The whole database is one SQLite file: /var/www/html/data/defecta.sqlite,
inside the Docker volume "defecta_sqlite_data". WAL mode + busy_timeout
keep concurrent writes safe (SQLite = 1 writer, fine for this app).

USER IDENTITY & AUDIT TRAIL
---------------------------
- Each staff member logs in by selecting their name from a dropdown and
  entering the app PIN. The stable user identifier (andi, sari, budi, lina)
  is stored in the session and used for audit attribution.
- All defecta records have `created_by` and `updated_by` columns that
  store the staff identifier at the time of the operation.
- A dedicated `audit_log` table records every business action including
  the actor, action type, entity, and before/after values.
- Audit records are written server-side within database transactions —
  the business change is rolled back if the audit write fails.

AUDIT LOG ACTIONS
-----------------
- LOGIN    — staff login (rate-limited to 5 attempts per 15 min per IP)
- LOGOUT   — staff logout
- CREATE   — new defecta item added
- UPDATE   — defecta item edited
- STATUS_CHANGE — status toggled between defecta/tersedia
- DELETE   — defecta item deleted
- BULK_UPDATE    — bulk status change
- BACKUP   — backup file created
- RESTORE  — database restored from backup

Session security: sessions use HttpOnly + SameSite=Lax cookies.
Set `cookie_secure = true` under HTTPS in production.

BACKUP
------
- The web UI provides a "Backup & Restore" modal with:
  * Create backup (VACUUM INTO for a consistent WAL-safe snapshot, then
    bzip2 compressed into the backups/ directory)
  * Download existing backups
  * Restore from a backup file (with full integrity_check + schema validation)
  * Upload and restore from a .sqlite.bz2 file downloaded elsewhere
- Before restore, the current database is automatically backed up to
  backups/defecta-pre-restore-*.sqlite.bz2
- The daily server-side backup (cron at 02:15 in the entrypoint, if enabled)
  uses `VACUUM INTO` to produce a consistent snapshot.

Restore validation:
- SQLite header magic check
- PRAGMA integrity_check
- Required tables (defecta) and columns verified
- WAL/SHM files cleared before atomic rename replacement

TIMESTAMP CONVENTION
-------------------
- PHP timezone: Asia/Jakarta (set in config.php and Dockerfile)
- SQLite CURRENT_TIMESTAMP: UTC (SQLite internal)
- PHP now(): returns local Asia/Jakarta time as Y-m-d H:i:s
- All stored timestamps use a consistent format; display converts to
  Asia/Jakarta. No historical timestamp data is migrated.

DEVELOPMENT
-----------
- PHP 8.2+ is required for local testing (the Docker image uses Alpine PHP 8.2).
- Install dev dependencies: composer install
- Run tests: vendor/bin/phpunit
- Tests use an in-memory-like SQLite file in tests/tmp/ (auto-created/cleaned)

PRODUCTION CONSIDERATIONS
-------------------------
- Set APP_PASSWORD (or APP_PIN via docker-compose env_file) to a strong,
  unique PIN. There is no default fallback.
- If using HTTPS, set `cookie_secure = true` in the session_start() call
  in config.php (currently false for HTTP dev convenience).
- The SQLite file, config.php, and backups directory are blocked from
  web access via Apache config (.htaccess) and httpd-defecta.conf.
- OPcache is enabled for performance (32MB, 10k files, 60s revalidate).

NOTES
-----
- The SQLite file (and config.php) are blocked from web access, both by
  .htaccess and by the Apache config.
- Prepared statements are used everywhere (no SQL injection).
- CSRF tokens protect all POST endpoints.
- XSS prevention: escHtml() in JS, htmlspecialchars in PHP.
