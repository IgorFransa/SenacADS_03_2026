#!/bin/bash
set -e

echo "Aguardando MySQL em ${DB_HOST:-db}..."
until php -r "
  require '/var/www/html/backend/config/conexao.php';
  conectarBanco();
" 2>/dev/null; do
  sleep 2
done

php /var/www/html/backend/scripts/seed-database.php

exec apache2-foreground
