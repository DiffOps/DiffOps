FROM php:8.3-cli-alpine

# Dependências de sistema para pdo_pgsql/pdo_sqlite
RUN apk add --no-cache \
        libpq-dev \
        sqlite-dev \
    && docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        pdo_sqlite \
        pcntl \
        posix

# Composer (copiado da imagem oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Sem COPY do código: usa o volume bind do compose.
# O comando do compose executa `composer install` + `php artisan horizon`.