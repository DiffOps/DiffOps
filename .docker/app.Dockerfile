FROM php:8.3-fpm-alpine

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

# Camada de dependências em cache: instala vendor SEM scripts
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts

# Código completo + install final (scripts rodam com tudo presente)
COPY . .
RUN composer install --no-interaction --prefer-dist

# Entrypoint: ajusta permissões do storage no bind mount e executa o comando
COPY .docker/entrypoint.sh /usr/local/bin/diffops-entrypoint
RUN chmod +x /usr/local/bin/diffops-entrypoint

ENTRYPOINT ["/usr/local/bin/diffops-entrypoint"]

EXPOSE 9000

CMD ["php-fpm"]