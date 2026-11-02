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
   Edit .env to set DEFAULT_USER_PIN to a strong PIN (default users start with 123456).
3. Build and start:
        docker compose up -d --build
4. Open http://localhost:8080 in a browser.
   Select a username from the dropdown and enter the PIN (default: 123456
   for all seeded users; change after first login via the Staff page).
   The database tables (including `users` with seeded accounts) are created
   automatically on the first initDB() call.

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
- Each staff member has an individual account in the `users` table with:
  - `username` (unique, e.g. `damayanti`, `faizin`, `aisyiyah`, `anggun`)
  - `display_name` (e.g. "Damayanti (Apoteker)")
  - `role` (`apoteker`, `manager`, or `staff`)
  - `pin_hash` (bcrypt-hashed 6-digit PIN)
  - `is_active` (soft-delete flag)
- Login: select username from dropdown + enter individual 6-digit PIN.
  - Default PIN for seeded users: `123456` (change after first login).
  - Legacy shared PIN (APP_PASSWORD / APP_PIN) is still supported as a fallback.
- Role-based access control:
  - **Apoteker** and **Manajer**: can manage (add/edit/delete) other staff.
  - **Staf**: can only view their own profile and change their own PIN.
- The session stores the user ID, username, display name, and role. The stable
  username is used for audit attribution via `current_staff()`.
- All defecta records have `created_by` and `updated_by` columns that store the
  staff identifier at the time of the operation.
- A dedicated `audit_log` table records every business action including the
  actor, action type, entity, and before/after values.
- Audit records are written server-side within database transactions.

USER MANAGEMENT
----------------
- Visit `/staff.php` to view, add, edit, and deactivate staff accounts.
- Admin-only actions (add, delete, edit role/status of others):
  - `POST api.php?action=staff_create`
  - `POST api.php?action=staff_update`
  - `POST api.php?action=staff_delete`
- Self-service actions (staff updating own PIN):
  - `POST api.php?action=staff_update` with old PIN verification
- List all users: `GET api.php?action=staff_list`
- Default staff list (seeded on first run):

  | Username   | Display Name             | Role     | Default PIN |
  |------------|--------------------------|----------|-------------|
  | damayanti  | Damayanti (Apoteker)     | apoteker | 123456      |
  | faizin     | Faizin (Manajer)         | manager  | 123456      |
  | aisyiyah   | Aisyiyah (Staf)          | staff    | 123456      |
  | a.nggun    | Anggun (Staf)            | staff    | 123456      |

AUDIT LOG ACTIONS
-----------------
- LOGIN    — staff login (rate-limited to 5 attempts per 15 min per IP)
- LOGOUT   — staff logout
- USER_CREATE — new staff user added
- USER_UPDATE — staff user edited (profile or PIN)
- USER_DELETE — staff user deactivated (soft delete)
- PIN_CHANGE  — staff changed their own PIN
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
