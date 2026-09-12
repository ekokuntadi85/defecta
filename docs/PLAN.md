# Defecta — Future Development Plan

> Last updated: 2026-09-12
> Project: Software Defecta — Pharmacy stock-out tracking system
> GitHub: https://github.com/ekokuntadi85/defecta

---

## Current State

### Infrastructure
- **Local dev**: Docker container running at `localhost:8080`
- **Database**: SQLite 387 records (68 defecta, 319 tersedia) + 3 bz2 backups
- **GitHub**: `ekokuntadi85/defecta` — 3 commits, SSH remote connected

### Recent Work Completed
1. **Security hardening** — CSRF protection, rate limiting, XSS prevention, session fixation protection
2. **File upload + restore** — Upload `.sqlite.bz2`, auto-backup current DB, atomic replace
3. **Docker improvements** — Alpine + PHP 8.2 + OPcache, bz2 support, entrypoint script
4. **Fixed restore button** — Uses existing confirmation pattern (was calling non-existent `confirmAction`)

### Tech Stack Summary
```
Backend:  PHP 8.2 (mod_php) + SQLite (WAL mode)
Frontend: Vanilla JS (ES6), CSS custom properties
Container: Alpine Linux + Apache (single container, ~150 MB)
Auth:   Single shared PIN (APP_PIN from .env)
```

---

## Comparative Analysis

### Similar Applications Reviewed

| Application | Stack | Key Strength |
|-------------|-------|--------------|
| **Grocy** | PHP + SQLite | User accounts, plugins, extensive integrations |
| **Inventree** | Python/Django + React | REST API, testing, modular architecture |
| **Snipel** | PHP/Laravel | Multi-user, audit trail, full lifecycle tracking |

### Defecta vs Competitors

| Feature | Defecta | Grocy | Inventree | Snipel |
|---------|---------|-------|-----------|--------|
| Multi-user | ❌ Single PIN | ✅ Roles/permissions | ✅ Accounts | ✅ Roles |
| Audit trail | ❌ None | ✅ Full log | ✅ History | ✅ Audit |
| API | Internal only | ✅ REST + Barcode | ✅ REST API | ✅ REST API |
| Mobile-first | ✅ Responsive | ✅ PWA | Responsive | Responsive |
| Containerization | ✅ Single container | ✅ Docker | ✅ Compose | ✅ Docker |
| Tests | ❌ None | ✅ Test suite | ✅ Unit tests | ✅ Tests |

### Key Strengths (Keep Doing)
1. **Perfect scope** — Narrowly focused on stock-out tracking, not bloated ERP
2. **Lightweight deployment** — 150MB Alpine image, SQLite single-file DB
3. **Mobile-first UX** — Card-based layout for pharmacy counter workflows
4. **Production-grade security** — CSRF, rate limiting, XSS prevention
5. **Backup system** — Daily snapshots + manual create/restore + upload restore

---

## Development Plan

### High Priority

#### 1. Add User Identity Tracking
```bash
# Goal: Track who makes changes
# Effort: 2-3 hours
#
# Changes:
# - Add "staff" login concept (simple name field before entering main app)
# - Store current user in session: $_SESSION['staff_name']
# - Add 'entered_by' field to form modals
# - Display current user in header
```

#### 2. Add Audit Trail Columns
```bash
# Goal: Track who created/modified each defecta item
# Effort: 30-60 minutes
#
# Migration:
# ALTER TABLE defecta ADD COLUMN created_by TEXT;
# ALTER TABLE defecta ADD COLUMN modified_by TEXT;
#
# Update: api.php 'tambah' and 'edit' cases to capture $_SESSION['staff_name']
```

#### 3. Test Coverage
```bash
# Goal: Prevent breaking changes in backup/restore, auth, CRUD
# Effort: 2-4 hours
#
# Setup:
# 1. Install PHPUnit locally: composer require --dev phpunit/phpunit
# 2. Create tests/ directory
# 3. Write tests for:
#    - backup_restore_upload flow
#    - login rate limiting (5 attempts → 429)
#    - duplicate drug name validation
#    - CSRF token validation
```

#### 4. Fix Docker Compose Volume Warning
```yaml
# Current (warning):
volumes:
  sqlite_data:

# Fix: Add external: true
volumes:
  sqlite_data:
    external: true
```

### Medium Priority

#### 5. Modular API Endpoints
```bash
# Goal: Split 789-line api.php into modular endpoints
# Effort: 4-6 hours
#
# Structure:
# public/api.php  →  Router that dispatches to:
#   api/auth.php      (login, logout, rate limiting)
#   api/items.php     (list, tambah, edit, delete, status changes)
#   api/reports.php   (report, report_summary, export)
#   api/backup.php    (backup_create, list, download, restore, restore_upload)
```

#### 6. JavaScript Modularization
```bash
# Goal: Split 1344-line app.js
# Effort: Ongoing refactoring
#
# Modules:
#   js/api.js        - fetch wrappers, CSRF handling
#   js/state.js      - currentPage, currentSearch, currentMode, selectedIds
#   js/render.js     - renderTable, escHtml
#   js/backup.js     - backup/restore UI functions
#   js/auth.js       - login/logout UI
#   js/main.js       - event listeners, initialization
```

#### 7. Database Migration System
```bash
# Goal: Track schema versions, support upgrades
# Effort: 2-3 hours
#
# Add migrations table:
# CREATE TABLE migrations (id INTEGER PRIMARY KEY, version TEXT, applied_at TEXT);
# Store current schema version, run incremental migrations on startup.
```

### Long-term Considerations

| Feature | Why | Complexity |
|---------|-----|------------|
| Multi-user accounts | Multiple staff members | High |
| REST API | Mobile app, external integrations | High |
| CSV Export | Accounting reports | Low |
| Barcode scanning | Faster drug entry at pharmacy counter | Medium |
| Dark mode (full) | Already partially implemented | Low |

---

## Architecture Decision

```
Current:  [Browser] ←→ [PHP + Apache + SQLite (single container)]

✅ KEEP THIS. Single-container SQLite architecture is correct for a
single pharmacy. Do NOT migrate to microservices or add a separate
database server.