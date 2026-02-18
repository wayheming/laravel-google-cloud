#!/bin/sh
set -e

# Substitute PORT into nginx config (Cloud Run sets this, default 8080)
sed -i "s/\${PORT}/${PORT:-8080}/g" /etc/nginx/nginx.conf

# Laravel optimization caches (must run at boot — env vars are injected at runtime)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optional: run migrations on deploy
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

# Start supervisord (manages nginx + php-fpm)
exec /usr/bin/supervisord -c /etc/supervisord.conf
