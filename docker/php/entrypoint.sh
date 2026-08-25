#!/bin/sh
set -e

mkdir -p \
    storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

composer_lock_hash_file="vendor/.composer-lock-hash"

composer_lock_hash() {
    if [ ! -f composer.lock ]; then
        echo ""
        return 0
    fi

    md5sum composer.lock | awk '{print $1}'
}

vendor_needs_composer_install() {
    if [ "${COMPOSER_INSTALL:-false}" = "true" ]; then
        return 0
    fi

    if [ ! -f vendor/autoload.php ]; then
        return 0
    fi

    if [ ! -f composer.lock ]; then
        return 1
    fi

    current_hash="$(composer_lock_hash)"
    stored_hash=""
    if [ -f "${composer_lock_hash_file}" ]; then
        stored_hash="$(cat "${composer_lock_hash_file}")"
    fi

    [ "${current_hash}" != "${stored_hash}" ]
}

mkdir -p vendor

if vendor_needs_composer_install; then
    (
        flock -w 180 9
        if vendor_needs_composer_install; then
            composer install --no-interaction --prefer-dist --no-ansi
            composer_lock_hash > "${composer_lock_hash_file}"
        fi
    ) 9>vendor/.composer-install.lock
fi

# Vite HMR file would make Laravel skip built assets inside containers.
rm -f public/hot

if [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force --no-interaction --no-ansi
fi

wait_for_mysql() {
    if [ "${DB_CONNECTION:-}" != "mysql" ] && [ "${DB_CONNECTION:-}" != "mariadb" ]; then
        return 0
    fi

    echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
    i=0
    while [ "$i" -lt 60 ]; do
        if php -r '
            $host = getenv("DB_HOST") ?: "mysql";
            $port = getenv("DB_PORT") ?: "3306";
            $user = getenv("DB_USERNAME") ?: "root";
            $password = getenv("DB_PASSWORD") ?: "";
            try {
                new PDO("mysql:host={$host};port={$port}", $user, $password);
            } catch (Throwable $e) {
                exit(1);
            }
        '; then
            return 0
        fi
        i=$((i + 1))
        sleep 2
    done

    echo "MySQL did not become ready in time." >&2
    exit 1
}

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    wait_for_mysql
    php artisan config:clear --no-ansi
    php artisan migrate --force --no-ansi
    php artisan db:import-sqlite --if-empty --no-ansi
    php artisan storage:link --force --no-ansi >/dev/null 2>&1 || true
fi

exec "$@"
