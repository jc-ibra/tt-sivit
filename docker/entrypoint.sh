#!/bin/sh
set -e
cd /var/www/html
# espera a MySQL, aplica migraciones y crea el Operador Virtual si no existe (ambos son idempotentes)
i=0
until php spark migrate >/tmp/migrate.log 2>&1; do
  i=$((i+1)); [ "$i" -ge 30 ] && { cat /tmp/migrate.log; echo "No se pudo migrar"; exit 1; }
  echo "Esperando a MySQL... ($i)"; sleep 2
done
cat /tmp/migrate.log | tail -3
php spark db:seed OperadorSeeder | tail -1
chown -R www-data:www-data writable
exec docker-php-entrypoint "$@"
