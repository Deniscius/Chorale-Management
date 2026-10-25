#!/bin/sh
set -eu
cd /var/www/html
case "${APP_KEY:-}" in
    '') echo 'APP_KEY is required' >&2; exit 1 ;;
    base64:*) ;;
    *) export APP_KEY="base64:$APP_KEY" ;;
esac
php artisan migrate --force --no-interaction
php artisan db:seed --class=ProductionSeeder --force --no-interaction
