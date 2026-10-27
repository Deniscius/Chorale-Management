#!/bin/sh
set -eu
cd /var/www/html
case "${APP_KEY:-}" in
    '') echo 'APP_KEY is required' >&2; exit 1 ;;
    base64:*) ;;
    *) export APP_KEY="base64:$APP_KEY" ;;
esac
# A database-provider move must not also change the restored schema/data.
# Disable migrations during that controlled cutover, then re-enable for later releases.
case "${RUN_MIGRATIONS_ON_RELEASE:-true}" in
    true) php artisan migrate --force --no-interaction ;;
    false) echo 'Migrations skipped for this release' ;;
    *) echo 'RUN_MIGRATIONS_ON_RELEASE must be true or false' >&2; exit 1 ;;
esac
# No automatic seeding: ProductionSeeder can add reference data and instrument units.
