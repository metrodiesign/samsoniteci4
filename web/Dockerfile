# syntax=docker/dockerfile:1
#
# CI3 rehearsal runtime. Two build contexts:
#   default : the PINNED, READ-ONLY CI3 repo  ($CI3_SOURCE_ROOT)
#   ws      : this workspace's web/ directory (config overrides)
#
#   DOCKER_BUILDKIT=1 docker build -f web/Dockerfile \
#     --build-context ws=web -t <tag> "$CI3_SOURCE_ROOT"
#
# The exclude list lives in web/Dockerfile.dockerignore because the context root is
# read-only and cannot receive a .dockerignore of its own.
#
# PHP 7.4, not 8.x: lib/PHPExcel has 153 curly-brace string offsets across 19 files, a fatal
# parse error from PHP 8.0 on, and CI 3.1.6 predates PHP 8 support (3.1.12+). This image is
# a rehearsal runtime for legacy CI3 only. CI4 targets PHP 8.5.
FROM php:7.4-apache

# mysqli: the only driver CI3 is configured for.
# zip + gd: PHPExcel needs them the moment Upload_excel.php requires it.
# Everything else CI3 touches (mbstring, iconv, curl, openssl, json, dom/xml, fileinfo)
# is already enabled in the official image.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libzip-dev libpng-dev libjpeg62-turbo-dev; \
    docker-php-ext-configure gd --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" mysqli zip gd; \
    a2enmod rewrite; \
    rm -rf /var/lib/apt/lists/*

COPY --chown=www-data:www-data . /var/www/html/

# Config overrides. Upstream database.php is gitignored and full of placeholders, and
# config.php hardcodes the old server's session path, which every request touches because
# autoload.php loads the session library.
COPY --from=ws config/database.php /var/www/html/application/config/database.php
RUN set -eux; \
    sed -i \
      -e "s#^\$config\['sess_save_path'\].*#\$config['sess_save_path'] = '/var/www/html/application/sess/';#" \
      -e "s#^\$config\['log_threshold'\].*#\$config['log_threshold'] = 4;#" \
      /var/www/html/application/config/config.php; \
    grep -q "'/var/www/html/application/sess/'" /var/www/html/application/config/config.php; \
    grep -q "log_threshold'\] = 4" /var/www/html/application/config/config.php

# Directories the app writes to at runtime. sess/ and uploads/ are gitignored upstream so
# they are absent from the context; logs/ is created by CI_Log on every request regardless
# of log_threshold.
RUN set -eux; \
    mkdir -p /var/www/html/application/sess \
             /var/www/html/application/logs \
             /var/www/html/application/cache \
             /var/www/html/uploads/web \
             /var/www/html/uploads/excel \
             /var/www/html/uploads/cms \
             /var/www/html/uploads/sess; \
    chown -R www-data:www-data /var/www/html/application/sess \
                               /var/www/html/application/logs \
                               /var/www/html/application/cache \
                               /var/www/html/uploads

COPY --from=ws apache/ci3.conf /etc/apache2/sites-available/000-default.conf

# Asserts Apache is serving. Application correctness is what the smoke suite is for, so this
# deliberately does not depend on the database being reachable.
HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=6 \
  CMD php -r '$f=@fsockopen("127.0.0.1", 80, $e, $s, 3); exit($f ? 0 : 1);'
