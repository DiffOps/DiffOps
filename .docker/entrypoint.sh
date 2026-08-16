#!/bin/sh
set -e

# No bind mount os diretórios pertencem ao UID do host (1000) e o php-fpm
# roda como www-data (82). Permissões universais de escrita garantem que
# ambos (host e container) consigam gravar em storage e bootstrap/cache.
chmod -R a+rwX /var/www/storage /var/www/bootstrap/cache || true

exec "$@"