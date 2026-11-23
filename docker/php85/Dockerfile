# Local development image for EspoCRM on PHP 8.5.
#
# Based on the official image (github.com/espocrm/espocrm-docker, latest/apache, PHP 8.4), with:
# - PHP 8.5 instead of 8.4;
# - no EspoCRM download and no docker-entrypoint.sh: the repo is mounted at /var/www/html;
# - without the PECL extensions zmq, ev and redis: only needed for Espo's WebSocket/daemon, which we don't use locally;
# - without pdo_pgsql and ldap: we use MariaDB and no LDAP login.
#
# Espo 10.1.4 requires json, openssl, mbstring, zip, gd, iconv and recommends curl, xml, xmlwriter, exif, bcmath
# (application/Espo/Resources/defaults/systemConfig.php). The php image has json, openssl, mbstring, iconv, curl,
# xml and xmlwriter built in; zip, gd, exif, bcmath and pdo_mysql are installed below.
FROM php:8.5-apache-trixie

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        unzip \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
        libfreetype6-dev \
        libzip-dev \
        libxml2-dev \
        zlib1g-dev \
    ; \
    docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg=/usr \
        --with-webp \
    ; \
    docker-php-ext-install -j$(nproc) \
        gd \
        pdo_mysql \
        zip \
        exif \
        pcntl \
        bcmath \
    ; \
    rm -rf /var/lib/apt/lists/*

# Same php.ini settings as the official image.
RUN { \
    echo 'expose_php = Off'; \
    echo 'error_reporting = E_ALL & ~E_NOTICE & ~E_DEPRECATED'; \
    echo 'display_errors = Off'; \
    echo 'display_startup_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'memory_limit=256M'; \
    echo 'max_execution_time=180'; \
    echo 'max_input_time=180'; \
    echo 'post_max_size=50M'; \
    echo 'upload_max_filesize=50M'; \
    echo 'date.timezone=UTC'; \
} > ${PHP_INI_DIR}/conf.d/espocrm.ini

# Espo's .htaccess rewrites to public/; the php image already allows .htaccess (AllowOverride All) in /var/www.
RUN a2enmod rewrite headers

WORKDIR /var/www/html

CMD ["apache2-foreground"]
