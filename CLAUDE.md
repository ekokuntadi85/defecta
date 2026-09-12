# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Software Defecta** — A pharmacy inventory tracking system for "Apotek Mentari Farma Bondowoso" that monitors "defecta" (unavailable/defective stock) and tracks when items become available again.

**Tech Stack:**
- **Backend:** PHP 8.2 (mod_php) + SQLite (single file, WAL mode)
- **Frontend:** Vanilla JS (ES6 modules pattern, no build step), CSS custom properties
- **Container:** Alpine Linux + Apache + mod_php (single container, ~150 MB)
- **Auth:** Simple PIN-based session auth (stored in `.env` as `APP_PIN`, default `1324`)
- **Database:** SQLite with `defecta` table (id, tanggal, nama_obat, keterangan, status, created_at, updated_at) + `login_attempts` for rate limiting

## Common Development Commands

```bash
# Start the application (builds if needed)
docker compose up -d --build

# View logs
docker compose logs -f

# Stop (keeps data volume)
docker compose down

# Stop AND erase all data (including SQLite DB)
docker compose down -v

# Open a shell in the container
docker compose exec app sh

# Manual backup (copies SQLite file out)
docker compose exec app cp /var/www/html/data/defecta.sqlite ./backup.sqlite
```

**Access:** http://localhost:8080 (login with PIN from `.env`)

## Architecture

### File Structure
```
/home/faizin/Projects/defecta/
├── Dockerfile                 # Alpine + Apache + PHP 8.2 + OPcache
├── docker-compose.yml         # Single service 'app' with SQLite volume
├── docker/
│   ├── entrypoint.sh          # Simple startup (no cron)
│   └── httpd-defecta.conf     # Apache config (docroot, .htaccess, rewrite, blocks /data)
├── .env                       # APP_PIN (login PIN), DB_SQLITE_PATH
├── public/                    # Document root (copied to /var/www/html in container)
│   ├── index.php              # Main page (defecta list + riwayat tabs)
│   ├── laporan.php            # Report page (date-range filter, summary stats)
│   ├── api.php                # Single API endpoint (all actions via ?action=)
│   ├── config.php             # DB connection, session, CSRF, helpers
│   ├── .htaccess              # Blocks .sqlite, .php in /data, rewrite rules
│   ├── assets/
│   │   ├── css/style.css      # All styles (CSS custom properties, mobile-first)
│   │   ├── js/app.js          # Main app logic (~1200 lines, vanilla ES6)
│   │   └── js/laporan.js      # Report page specific logic
│   └── includes/              # PHP partials (header, modals, tables, etc.)
└── data/                      # SQLite DB + backups (persisted via Docker volume)
```

### API Actions (`public/api.php`)
All actions are POST except `list`, `export`, `search_obat`, `report`, `report_summary`, `backup_list`, `backup_download` (GET).

| Action | Method | Description |
|--------|--------|-------------|
| `login` | POST | PIN auth, rate-limited (5/15min/IP), sets session |
| `logout` | POST | Destroys session |
| `list` | GET | Paginated list (mode: defecta\|riwayat, search, page) |
| `export` | GET | All rows for print/PDF (mode, search) |
| `search_obat` | GET | Autocomplete for drug names (shows status) |
| `tambah` | POST | Add new defecta item (validates no dup in either status) |
| `bulk_tersedia` | POST | Mark multiple defecta → tersedia |
| `bulk_defecta` | POST | Mark multiple tersedia → defecta |
| `bulk_delete` | POST | Delete multiple items (confirm required) |
| `edit` | POST | Edit single item (validates no dup in same status) |
| `tersedia` | POST | Single defecta → tersedia |
| `report` | GET | Paginated report (mode, date_from, date_to, search) |
| `report_summary` | GET | Stats + top 5 defecta drugs |
| `backup_create` | POST | Creates bzip2-compressed SQLite backup |
| `backup_list` | GET | Lists backup files |
| `backup_download` | GET | Streams backup file download |
| `backup_restore` | POST | Restores from backup (validates SQLite integrity) |

### Key Frontend Patterns (app.js)
- **State:** `currentPage`, `currentSearch`, `currentMode`, `selectedIds` (Set), `defectaLock`
- **Data fetching:** `loadList(page, search)` → calls `api.php?action=list`
- **Rendering:** `renderTable(rows)` builds both desktop `<tr>` and mobile `.mobile-card` HTML
- **Event delegation:** Single click listener on `tbody` + `mobileCardList` using `data-action` attributes
- **Modals:** Add, Edit, Confirm, Backup — all toggle `.open` class on overlay
- **Autocomplete:** Debounced fetch to `search_obat`, shows warning if drug already in defecta list
- **CSRF:** Token from `<meta name="csrf-token">` included in every POST
- **Toasts:** `showToast(msg, type)` for success/error feedback

### Database Schema (`config.php:initDB()`)
```sql
CREATE TABLE defecta (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tanggal TEXT NOT NULL,           -- date defecta recorded
    nama_obat TEXT NOT NULL,
    keterangan TEXT NOT NULL,        -- 'Stock Menipis' | 'Stock Habis' | 'Penolakan' | custom
    status TEXT NOT NULL DEFAULT 'defecta' CHECK(status IN ('defecta','tersedia')),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,  -- UTC
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP   -- UTC
);
CREATE INDEX idx_status ON defecta(status);
CREATE INDEX idx_tanggal ON defecta(tanggal);
CREATE INDEX idx_nama ON defecta(nama_obat);

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL,
    attempted_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_login_ip ON login_attempts(ip, attempted_at);
```

### Security Notes
- SQLite file + config.php blocked from web access (Apache config + .htaccess)
- CSRF tokens on all POST endpoints
- PIN rate limiting (5 attempts per 15 min per IP)
- Session fixation protection (`session_regenerate_id` on login)
- Prepared statements everywhere (no SQL injection)
- XSS prevention: `escHtml()` used for all dynamic content in JS, `htmlspecialchars` in PHP

### Timezone Handling
- PHP: `Asia/Jakarta` (set in Dockerfile + config.php)
- SQLite: `CURRENT_TIMESTAMP` = UTC
- `tanggal` column = date only (no time), user-entered
- `updated_at` = timestamp when status changed to tersedia
- Report page: `defecta` mode filters by `tanggal`, `tersedia` mode filters by `DATE(updated_at)`

### Backup System
- Daily automatic snapshot at 02:15 via `VACUUM INTO` (handled in entrypoint.sh cron)
- Manual backup: `backup_create` action → bzip2 compressed `.sqlite.bz2`
- Restore: `backup_restore` validates SQLite integrity before atomic `rename()`

## Common Tasks

### Add a new API action
1. Add `case 'action_name':` in `api.php` switch
2. Add frontend handler in `app.js` (or `laporan.js`)
3. Follow existing patterns: CSRF check, auth check, validation, prepared statements, JSON response

### Modify UI
- Styles: `public/assets/css/style.css` (CSS custom properties at top for theming)
- Main logic: `public/assets/js/app.js`
- Report page: `public/assets/js/laporan.php`
- Partials: `public/includes/` (included in `index.php` / `laporan.php`)

### Database changes
- Modify `initDB()` in `config.php` — runs on first API call
- For migrations, consider version tracking or manual SQL in container

### Change PIN
Edit `.env` → `APP_PIN=newpin` → `docker compose restart app`

## Docker Details
- Base: `alpine:3.20`
- PHP: `php82`, `php82-apache2`, `php82-pdo_sqlite`, `php82-sqlite3`, `php82-opcache`, `php82-session`
- OPcache tuned for low memory (32MB, 10k files)
- Entrypoint: `docker/entrypoint.sh` (creates data dir, sets perms, starts Apache)
- Healthcheck: `wget -q -O /dev/null http://localhost/`
- Volume: `sqlite_data` → `/var/www/html/data` (owned by `apache:apache`)