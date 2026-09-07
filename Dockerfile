# Runs the app on Render (or any Docker host). Base image matches the PHP
# version this was developed against (8.2). No Composer step — the app has
# zero third-party PHP packages (confirmed: no composer.json/vendor/ anywhere).
FROM php:8.2-apache

# pdo_pgsql needs libpq's headers to build against; mbstring needs
# oniguruma's headers (libonig-dev) to build its regex support — without it,
# "docker-php-ext-install mbstring" fails at ./configure with "Package
# requirements (oniguruma) were not met". mbstring itself is used by
# ajax/verify-payment.php. curl's extension ships enabled by default on this
# base image already. Keeping the -dev packages installed (not purging them
# after the build) trades a slightly larger image for certainty — pulling
# them back out safely would need auto-remove to not also take a runtime lib
# with it, which isn't worth the risk to verify without a way to test the
# build locally.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libonig-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring \
    && rm -rf /var/lib/apt/lists/*

# mod_rewrite: needed for .htaccess's "RewriteRule ^database/ - [F,L]".
# AllowOverride All: needed for .htaccess (dotfile/.sql blocking) to take
# effect at all — the base image ships with AllowOverride None by default.
RUN a2enmod rewrite \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Matches this app's actual upload path: per-requirement document uploads up
# to 5MB each, sometimes several in one submission. The base image's PHP
# defaults (2M upload / 8M post) are well below that.
COPY docker/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
COPY . .

# Belt-and-suspenders: .dockerignore already excludes .env from the build
# context, and .htaccess blocks serving it either way, but there's no
# reason for it to exist inside the image at all — Render provides config
# as real environment variables, not a file.
RUN rm -f .env

COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
