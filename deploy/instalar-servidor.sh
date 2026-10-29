#!/bin/bash
# Corre en el VPS como root, Ubuntu 22/24, sin Docker.
#   chmod +x /var/www/belly/api/deploy/instalar-servidor.sh
#   /var/www/belly/api/deploy/instalar-servidor.sh

set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Corre este script como root."
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y nginx mysql-server unzip curl git software-properties-common

if ! apt-cache show php8.3-fpm >/dev/null 2>&1; then
  add-apt-repository -y ppa:ondrej/php
  apt-get update
fi

apt-get install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-intl php8.3-gd php8.3-zip php8.3-bcmath

if ! command -v composer >/dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v node >/dev/null; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
fi

mkdir -p /var/www/belly/api /var/www/belly/front

if [[ ! -f /var/www/belly/api/spark ]]; then
  echo "Sube el API a /var/www/belly/api y vuelve a correr este script."
  exit 1
fi

cd /var/www/belly/api
composer install --no-dev --optimize-autoloader --no-interaction

if [[ -d socket ]]; then
  cd socket
  npm ci --omit=dev
  cd ..
fi

if [[ ! -f .env ]]; then
  cp deploy/env.produccion.ejemplo .env
  php spark key:generate --force
  CLAVE_SOCKET="$(openssl rand -hex 24)"
  CLAVE_DB="$(openssl rand -hex 16)"
  sed -i "s/SOCKET_CLAVE = CAMBIA_ESTA_CLAVE_LARGA/SOCKET_CLAVE = ${CLAVE_SOCKET}/" .env
  sed -i "s/CAMBIA_ESTA_CLAVE/${CLAVE_DB}/" .env
  echo "Clave MySQL del usuario belly: ${CLAVE_DB}"
  mysql -e "CREATE DATABASE IF NOT EXISTS crm_belly CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  mysql -e "CREATE USER IF NOT EXISTS 'belly'@'localhost' IDENTIFIED BY '${CLAVE_DB}';"
  mysql -e "GRANT ALL PRIVILEGES ON crm_belly.* TO 'belly'@'localhost'; FLUSH PRIVILEGES;"
fi

php spark migrate --all
php spark db:seed "Belly\\Database\\Seeds\\InitSeeder"

mkdir -p writable/uploads writable/cache writable/logs writable/session
chown -R www-data:www-data /var/www/belly
find /var/www/belly/api/writable -type d -exec chmod 775 {} \;

if [[ ! -L public/uploads ]]; then
  ln -sfn /var/www/belly/api/writable/uploads /var/www/belly/api/public/uploads
fi

SOCK="$(ls /run/php/php8.3-fpm.sock /run/php/php*-fpm.sock 2>/dev/null | head -n1)"
if [[ -z "${SOCK}" ]]; then
  echo "No está el socket de PHP-FPM."
  exit 1
fi

cp deploy/nginx-belly.conf /etc/nginx/sites-available/belly
sed -i "s#unix:/run/php/php8.3-fpm.sock#unix:${SOCK}#" /etc/nginx/sites-available/belly
ln -sfn /etc/nginx/sites-available/belly /etc/nginx/sites-enabled/belly
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

cp deploy/belly-socket.service /etc/systemd/system/belly-socket.service
systemctl daemon-reload
systemctl enable --now belly-socket

echo
echo "Listo. Edita /var/www/belly/api/.env y pon tu dominio en app.baseURL y SOCKET_ORIGENES."
echo "El front compilado va en /var/www/belly/front"
echo "Cuando tengas dominio: certbot --nginx -d TU_DOMINIO"
