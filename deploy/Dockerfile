# syntax=docker/dockerfile:1.7
#
# Production image for the whole application: the web server (FrankenPHP, which
# embeds Caddy for automatic HTTPS), Reverb, the queue workers and the scheduler all
# run from this one image with different commands. See deploy/README.md.
#
# Build from the repository root:
#   docker build -f deploy/Dockerfile -t influx-project .

ARG PHP_VERSION=8.4

# --- Base: PHP with the extensions the app needs ----------------------------------------
FROM dunglas/frankenphp:1-php${PHP_VERSION}-bookworm AS base

RUN install-php-extensions \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        redis \
        zip \
    # `ping` is used by Ping (ICMP) service checks.
    && apt-get update \
    # `setcap` lets the web server bind ports 80/443 without running as root.
    && apt-get install -y --no-install-recommends iputils-ping libcap2-bin \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY deploy/php.ini "$PHP_INI_DIR/conf.d/zz-app.ini"

WORKDIR /app

# --- PHP dependencies and application source --------------------------------------------
FROM base AS source

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi

# --- Front-end assets -------------------------------------------------------------------
# Built with PHP available, because the Wayfinder Vite plugin generates the typed routes
# by running `php artisan wayfinder:generate`.
FROM source AS assets

COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=oven/bun:1 /usr/local/bin/bun /usr/local/bin/bun

RUN bun install --frozen-lockfile
# The WebSocket connection is configured at runtime (see resources/js/app.tsx), so no
# domain, port or key is baked into the build.
RUN bun run build

# --- Runtime ----------------------------------------------------------------------------
FROM base AS app

ARG USER=app
ARG UID=1000

COPY --from=source /app /app
COPY --from=assets /app/public/build /app/public/build
COPY deploy/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=755 deploy/entrypoint.sh /usr/local/bin/app-entrypoint

# Run as an unprivileged user, still able to bind ports 80 and 443.
RUN useradd --uid "${UID}" --create-home "${USER}" \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && chown -R "${USER}:${USER}" /app/storage /app/bootstrap/cache /data/caddy /config/caddy

USER ${USER}

ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
