# Software Defecta - PHP + Apache image (OPcache enabled for speed)
FROM php:8.2-apache

# PDO MySQL driver + OPcache (bundled, just enable it)
RUN docker-php-ext-install pdo pdo_mysql \
 && docker-php-ext-enable opcache

# Apache modules required by public/.htaccess (rewrite + headers)
RUN a2enmod rewrite headers

# OPcache tuning for low-memory machines (big speed win, tiny RAM cost)
RUN printf 'opcache.enable=1\nopcache.memory_consumption=32\nopcache.interned_strings_buffer=8\nopcache.max_accelerated_files=10000\nopcache.revalidate_freq=60\nopcache.validate_timestamps=1\n' > /usr/local/etc/php/conf.d/opcache.ini

# Allow .htaccess overrides in the web root
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy the app (public/ is the document root)
COPY public/ /var/www/html/

# Apache worker ownership
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
