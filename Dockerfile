# Software Defecta - Alpine + Apache + mod_php (SQLite only, small footprint)
FROM alpine:3.20

# Apache + PHP 8.2 (mod_php) + SQLite + OPcache.
# php82-apache2 auto-loads mod_php via /etc/apache2/conf.d/php82-module.conf
RUN apk add --no-cache \
        apache2 \
        php82 php82-apache2 \
        php82-pdo_sqlite php82-sqlite3 \
        php82-session php82-opcache \
        php82-bz2 \
    && ln -sf /usr/bin/php82 /usr/bin/php

# Apache tweaks: docroot, .htaccess overrides, rewrite, docker-friendly logs,
# and a hard block on the SQLite data directory.
COPY docker/httpd-defecta.conf /etc/apache2/conf.d/defecta.conf

# Simple entrypoint (no cron)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# OPcache tuning for low-memory machines (big speed win, tiny RAM cost)
RUN printf 'opcache.enable=1\nopcache.memory_consumption=32\nopcache.interned_strings_buffer=8\nopcache.max_accelerated_files=10000\nopcache.revalidate_freq=60\nopcache.validate_timestamps=1\n' > /etc/php82/conf.d/opcache.ini

# Local time (the old MySQL server used +07:00; SQLite CURRENT_TIMESTAMP is UTC
# but PHP now() uses local Asia/Jakarta for display timestamps)
RUN printf 'date.timezone=Asia/Jakarta\n' > /etc/php82/conf.d/timezone.ini

# Copy the app (public/ is the document root)
COPY public/ /var/www/html/

# SQLite data dir (persisted via volume); owned by the web user
RUN mkdir -p /var/www/html/data/backups \
 && chown -R apache:apache /var/www/html

EXPOSE 80

CMD ["entrypoint.sh"]
