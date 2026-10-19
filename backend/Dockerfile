# Blood Donor Network API on Render's free tier (research R12).
# Production-tuned PHP-FPM + Nginx, running as the unprivileged www-data user.
FROM serversideup/php:8.3-fpm-nginx

# The image's Laravel automations cache config/routes/views at container start
# (after Render injects the env vars). There is no database: never migrate.
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=false \
    AUTORUN_LARAVEL_STORAGE_LINK=false \
    PHP_OPCACHE_ENABLE=1 \
    LOG_CHANNEL=stderr

WORKDIR /var/www/html

# Install dependencies first so this layer is cached between code changes.
COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . .
RUN composer dump-autoload --optimize --no-dev

# Nginx in this image listens on 8080; render.yaml sets PORT=8080 to match.
EXPOSE 8080
