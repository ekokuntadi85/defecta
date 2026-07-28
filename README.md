SOFTWARE DEFECTA — DOCKER SETUP
===============================

This app is containerized so it runs on any machine that has Docker +
Docker Compose, with no manual PHP/MySQL install.

Files added:
  Dockerfile          - PHP + Apache image (the web server)
  docker-compose.yml  - two services: web ("app") + database ("db", MariaDB)
  .env.example        - config template (DB creds + app PIN)

HOW TO RUN
----------
1. Install Docker Desktop (Windows/Mac) or Docker Engine (Linux).
2. Create the env file:
       cp .env.example .env
   (Edit .env to set DB_PASS, APP_PIN, and MARIADB_PW. APP_PIN is the
    login PIN; MARIADB_PW is the database password.)
3. Build and start:
       docker compose up -d --build
4. Open http://localhost:8080 in a browser.
   Log in with the PIN you set as APP_PIN in .env (example default 1324).
   The database table is created automatically on the first API call.

STOP / REMOVE
-------------
- Stop (data is kept in the volume):
       docker compose down
- Stop AND erase all database data:
       docker compose down -v

WHERE THE DATA LIVES
--------------------
All data lives in the Docker volume "defecta_db_data" (MariaDB).
The app code stores no files, so a backup = a database dump.

NOTES
-----
- config.php reads credentials from the environment (DB_HOST, DB_USER,
  ...) with fallbacks to the old values, so it still runs in a
  non-Docker setup.
- In Docker, DB_HOST = "db" (the database service name in compose).
- Performance on small hardware: OPcache is enabled in the PHP image
  (faster responses, lower CPU) and MariaDB's buffer pool is capped at
  32M to save RAM. Both are safe for this tiny app.
