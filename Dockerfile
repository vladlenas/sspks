# syntax=docker/dockerfile:1
FROM php:8.4-apache

ARG BRANCH=""
ARG COMMIT=""

ENV SSPKS_BRANCH=${BRANCH} \
    SSPKS_COMMIT=${COMMIT} \
    SSPKS_PACKAGES_DIR=/packages \
    SSPKS_CACHE_DIR=/cache

LABEL org.opencontainers.image.title="SSpkS" \
      org.opencontainers.image.description="Simple Synology package server" \
      org.opencontainers.image.source="https://github.com/vladlenas/sspks" \
      org.opencontainers.image.licenses="GPL-3.0-or-later" \
      org.opencontainers.image.revision="${COMMIT}"

RUN set -eux; \
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    docker-php-ext-enable opcache; \
    a2enmod headers; \
    a2dismod -f autoindex status; \
    sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
    mkdir -p /packages /cache; \
    chown www-data:www-data /cache

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-sspks.ini"
COPY src /app/src
COPY templates /app/templates
COPY public /app/public

# Apache can run as any user here, so `user: UID:GID` in compose works too.
USER www-data
EXPOSE 8080
VOLUME ["/packages", "/cache"]

HEALTHCHECK --interval=1m --timeout=5s --start-period=10s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/?health") === "ok\n" ? 0 : 1);'
