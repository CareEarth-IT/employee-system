#!/bin/sh
set -e

PORT="${PORT:-8080}"

envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

cd /var/www/html

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    export DB_DATABASE="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
    mkdir -p storage/database
    if [ ! -f "$DB_DATABASE" ]; then
        touch "$DB_DATABASE"
    fi
    chown www-data:www-data "$DB_DATABASE"
    chmod 664 "$DB_DATABASE"
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force || echo "WARN: migrate failed (DB not ready?)"
fi

if [ "$RUN_SEED" = "true" ]; then
    php artisan db:seed --force || echo "WARN: seed failed"
fi

if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY is not set. Set it in Cloud Run env vars and redeploy." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# WordPress on /wordpress (Cloud SQL, no GCS)
if [ -x /usr/local/bin/wordpress-bootstrap.sh ]; then
    /usr/local/bin/wordpress-bootstrap.sh || echo "WARN: WordPress bootstrap failed"
fi

php-fpm -D
exec nginx -g 'daemon off;'
