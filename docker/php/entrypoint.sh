#!/usr/bin/env sh
set -eu

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan package:discover --ansi >/dev/null 2>&1 || true

exec "$@"
