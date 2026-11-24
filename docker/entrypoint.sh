#!/bin/sh
# Entrypoint: setup data dir, perms, cron, then run Apache in foreground.

set -e

# Ensure data directory exists with correct permissions
mkdir -p /var/www/html/data
chown -R apache:apache /var/www/html/data

# Setup crontab for daily cleanup at 02:15
echo "15 2 * * * php /var/www/html/cleanup_login_attempts.php" > /etc/crontabs/root
chmod 600 /etc/crontabs/root

# Start crond in background
crond -b -L /dev/stdout

# Start Apache in foreground
exec httpd -DFOREGROUND