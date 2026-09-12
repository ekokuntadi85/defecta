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
   (Edit .env to set APP_PIN, the login PIN.)
3. Build and start:
       docker compose up -d --build
4. Open http://localhost:8080 in a browser.
   Log in with the PIN you set as APP_PIN in .env (example default 1324).
   The database table is created automatically on the first API call.

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

BACKUP
------
Because everything is one file, a backup is just copying that file out:
   docker compose exec app cp /var/www/html/data/defecta.sqlite ./backup.sqlite
To restore, copy it back in (stop the container first).

The container also takes an automatic snapshot every day at 02:15 into
/var/www/html/data/backups/ (keeps the last 14, safe WAL snapshot via
`VACUUM INTO`).

NOTES
-----
- The SQLite file (and config.php) are blocked from web access, both by
  .htaccess and by the Apache config.
- Timestamps (created_at / updated_at) use SQLite CURRENT_TIMESTAMP, which
  is UTC. The PHP timezone is set to Asia/Jakarta (was +07:00 on MySQL).
- Performance on small hardware: OPcache is enabled (faster responses,
  lower CPU). Safe for this tiny app.
