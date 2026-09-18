# syntax=docker/dockerfile:1

# The official php:*-apache images are built on debian:*-slim. No alpine
# variant ships Apache with mod_php, and public/.htaccess needs mod_rewrite.
ARG PHP_VERSION=8.4

FROM composer:2 AS composer

# Production dependencies only, resolved without running any package scripts.
FROM composer AS vendor
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts \
        --prefer-dist --optimize-autoloader --ignore-platform-reqs

FROM php:${PHP_VERSION}-apache AS base
# The installer removes its build dependencies itself; the bind mount keeps
# the installer binary out of the image layers.
RUN --mount=type=bind,from=mlocati/php-extension-installer:2,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
    install-php-extensions ldap pdo_mysql pdo_pgsql
RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/zz-iredpanel.conf \
    && a2enconf zz-iredpanel
WORKDIR /var/www/html

# Development: the repository is bind-mounted over /var/www/html at runtime.
FROM base AS dev
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

FROM base AS prod
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php = Off\n' > "$PHP_INI_DIR/conf.d/zz-iredpanel.ini"
COPY --from=vendor /app/vendor ./vendor
COPY composer.json ./
COPY public ./public
COPY src ./src
COPY templates ./templates
COPY locales ./locales
COPY cli ./cli
