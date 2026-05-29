#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
fi

if [ "${DB_CONNECTION:-}" = "pgsql" ]; then
    php -r '
        $host = getenv("DB_HOST") ?: "pgsql";
        $port = getenv("DB_PORT") ?: "5432";
        $db = getenv("DB_DATABASE") ?: "stimergie_image_hub";
        $user = getenv("DB_USERNAME") ?: "stimergie";
        $pass = getenv("DB_PASSWORD") ?: "stimergie";

        for ($i = 0; $i < 60; $i++) {
            try {
                new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass);
                exit(0);
            } catch (Throwable $e) {
                usleep(500000);
            }
        }

        fwrite(STDERR, "Postgres is not reachable\n");
        exit(1);
    '
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
