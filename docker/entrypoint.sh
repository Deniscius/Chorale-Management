#!/bin/sh
set -eu
cd /var/www/html
case "${APP_KEY:-}" in
    '') echo 'APP_KEY is required' >&2; exit 1 ;;
    base64:*) ;;
    *) export APP_KEY="base64:$APP_KEY" ;;
esac
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
if [ -n "${PRIVATE_FILES_ROOT:-}" ]; then
    mkdir -p "$PRIVATE_FILES_ROOT"
    chown www-data:www-data "$PRIVATE_FILES_ROOT"
fi
php artisan config:cache
case "${1:-web}" in
    web)
        if [ "${RUN_MIGRATIONS_ON_START:-false}" = 'true' ]; then
            # release.sh also honors RUN_MIGRATIONS_ON_RELEASE=false for a data-only move.
            sh docker/release.sh
        fi
        if [ "${PLATFORM_BOOTSTRAP_ENABLED:-false}" = 'true' ]; then
            php artisan platform:bootstrap --no-interaction
        fi
        case "${PORT:-10000}" in *[!0-9]*|'') echo 'Invalid PORT' >&2; exit 1;; esac
        printf 'Listen %s\n' "${PORT:-10000}" > /etc/apache2/ports.conf
        exec apache2-foreground
        ;;
    worker) exec php artisan queue:work database --sleep=3 --timeout=60 --max-time=3600 ;;
    schedule) exec php artisan schedule:run --no-interaction ;;
    *) exec "$@" ;;
esac
