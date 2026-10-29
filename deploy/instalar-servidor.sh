#!/bin/bash
# En el VPS, como root:
#   apt-get update && apt-get install -y git
#   git clone https://github.com/BryanCoronado/belly-api.git /var/www/belly/api
#   /var/www/belly/api/deploy/instalar-servidor.sh
#
# Si el repo es privado:
#   git clone https://USUARIO:TOKEN@github.com/BryanCoronado/belly-api.git /var/www/belly/api

set -euo pipefail

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Corre este script como root."
  exit 1
fi

REPO_API="${REPO_API:-https://github.com/BryanCoronado/belly-api.git}"
REPO_FRONT="${REPO_FRONT:-https://github.com/BryanCoronado/belly-front.git}"
RAMA="${RAMA:-main}"

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y nginx mysql-server unzip curl git software-properties-common ffmpeg

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

mkdir -p /var/www/belly

if [[ ! -f /var/www/belly/api/spark ]]; then
  git clone --branch "$RAMA" "$REPO_API" /var/www/belly/api
else
  git -C /var/www/belly/api fetch origin
  git -C /var/www/belly/api checkout "$RAMA"
  git -C /var/www/belly/api pull --ff-only origin "$RAMA"
fi

if [[ ! -d /var/www/belly/front-src/.git ]]; then
  git clone --branch "$RAMA" "$REPO_FRONT" /var/www/belly/front-src
else
  git -C /var/www/belly/front-src fetch origin
  git -C /var/www/belly/front-src checkout "$RAMA"
  git -C /var/www/belly/front-src pull --ff-only origin "$RAMA"
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
if [[ ! -L public/uploads ]]; then
  ln -sfn /var/www/belly/api/writable/uploads /var/www/belly/api/public/uploads
fi

cd /var/www/belly/front-src
npm ci
VITE_API_URL=https://api.micrm.space VITE_SOCKET_URL=https://api.micrm.space npm run build
mkdir -p /var/www/belly/front
rm -rf /var/www/belly/front/*
cp -r dist/. /var/www/belly/front/

chown -R www-data:www-data /var/www/belly
find /var/www/belly/api/writable -type d -exec chmod 775 {} \;

SOCK="$(ls /run/php/php8.3-fpm.sock /run/php/php*-fpm.sock 2>/dev/null | head -n1)"
if [[ -z "${SOCK}" ]]; then
  echo "No está el socket de PHP-FPM."
  exit 1
fi

cp /var/www/belly/api/deploy/nginx-belly.conf /etc/nginx/sites-available/belly
sed -i "s#unix:/run/php/php8.3-fpm.sock#unix:${SOCK}#" /etc/nginx/sites-available/belly
ln -sfn /etc/nginx/sites-available/belly /etc/nginx/sites-enabled/belly
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

cp /var/www/belly/api/deploy/belly-socket.service /etc/systemd/system/belly-socket.service
systemctl daemon-reload
systemctl enable --now belly-socket
systemctl restart belly-socket

echo
echo "Listo."
echo "Panel: https://app.micrm.space"
echo "API:   https://api.micrm.space"
echo "Certificados: apt-get install -y certbot python3-certbot-nginx && certbot --nginx -d api.micrm.space -d app.micrm.space"
