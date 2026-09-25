#!/bin/sh
# Runs before every container's command.
set -e

# Cache config, routes, views and events for the environment this container was started
# with. Done at start rather than at build time, since the config comes from .env.
php artisan optimize --no-interaction

exec "$@"
