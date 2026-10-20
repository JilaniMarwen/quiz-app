# Production image for Railway (or any Docker host).
# FrankenPHP = PHP + a production web server in one process (handles a whole class at once,
# unlike `php artisan serve`).
FROM dunglas/frankenphp:1-php8.3

RUN install-php-extensions pdo_mysql intl zip opcache pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies first (cached layer while only the code changes).
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr

EXPOSE 8080

# On every start: migrate, (re)create the professor account from ADMIN_USERNAME / ADMIN_PASSWORD,
# cache the config, then serve public/ on the port given by the host ($PORT on Railway).
CMD ["sh", "-c", "php artisan migrate --force && php artisan db:seed --class=AdminSeeder --force && php artisan config:cache && php artisan route:cache && exec frankenphp php-server --root public/ --listen :${PORT:-8080}"]
