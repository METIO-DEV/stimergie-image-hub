#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

has_app_key() {
    if [ -n "${APP_KEY:-}" ]; then
        return 0
    fi

    if [ -f .env ] && grep -Eq '^APP_KEY=.+$' .env && ! grep -Eq '^APP_KEY=$' .env; then
        return 0
    fi

    return 1
}

if [ ! -d vendor ] && command -v composer >/dev/null 2>&1; then
    composer install --no-interaction --prefer-dist
fi

if ! has_app_key && [ -f .env ]; then
    php artisan key:generate --force --no-interaction
fi

if ! has_app_key; then
    echo "APP_KEY is not configured. Set it in Dokploy environment variables." >&2
    exit 1
fi

if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
    php -r '
        $host = getenv("DB_HOST") ?: "pgsql";
        $port = getenv("DB_PORT") ?: "5432";
        $db = getenv("DB_DATABASE") ?: "stimergie_image_hub";
        $user = getenv("DB_USERNAME") ?: "stimergie";
        $pass = getenv("DB_PASSWORD") ?: "stimergie";
        $lastError = "unknown error";

        for ($i = 0; $i < 60; $i++) {
            try {
                new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass);
                exit(0);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                usleep(500000);
            }
        }

        fwrite(STDERR, "Postgres is not reachable: host={$host} port={$port} db={$db} user={$user} error={$lastError}\n");
        exit(1);
    '
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

php artisan storage:link --force --no-interaction >/dev/null 2>&1 || true

if [ "${RUN_OPTIMIZE:-false}" = "true" ]; then
    php artisan optimize --no-interaction
fi

exec "$@"
