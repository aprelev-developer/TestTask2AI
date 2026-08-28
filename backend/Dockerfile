# syntax=docker/dockerfile:1

FROM php:8.2-cli-alpine

# System packages + PHP extensions required by Laravel / Postgres / l5-swagger / Sentry.
# mbstring, xml, dom, curl, tokenizer, ctype, fileinfo, openssl already ship with the base image.
RUN apk add --no-cache \
        postgresql-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Composer/PHPStan/Pest need more than the distro's 128M default when
# analysing/running inside a container.
RUN { \
        echo 'memory_limit=512M'; \
        echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/zz-scamtest.ini

WORKDIR /var/www/html

# Install dependencies first (layer caching) using the lockfile already committed
# to the skeleton — no build agent runs `composer require` here.
COPY composer.json composer.lock ./
RUN composer install \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader

COPY . .

# .env itself is gitignored/dockerignored (no secrets baked into the image) —
# actual config comes from docker-compose's env_file at runtime. But some
# packages stat/read the file directly during bootstrap and fail hard if it's
# entirely absent, so seed a secret-free baseline from .env.example; real
# environment variables from env_file still take precedence over its contents.
RUN cp .env.example .env

RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 8000

# Runs migrations then serves the app — docker-compose up must not require any
# manual follow-up command. Sentry stays a no-op when SENTRY_LARAVEL_DSN is empty.
CMD ["sh", "-c", "php artisan migrate --force --seed && php artisan serve --host=0.0.0.0 --port=8000"]
