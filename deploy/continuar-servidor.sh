#!/bin/bash
# Si el instalador se cayó por TU_DOMINIO, corre esto en el VPS:
#   bash /var/www/belly/api/deploy/continuar-servidor.sh

set -euo pipefail

ENV=/var/www/belly/api/.env
if [[ ! -f "$ENV" ]]; then
  echo "No está el .env. Corre primero instalar-servidor.sh"
  exit 1
fi

sed -i "s#app.baseURL = 'https://TU_DOMINIO/api/'#app.baseURL = 'https://api.micrm.space/'#" "$ENV"
sed -i "s#app.baseURL = \"https://TU_DOMINIO/api/\"#app.baseURL = 'https://api.micrm.space/'#" "$ENV"
sed -i "s#SOCKET_ORIGENES = https://TU_DOMINIO#SOCKET_ORIGENES = https://app.micrm.space#" "$ENV"
sed -i "s#SOCKET_API_URL = http://127.0.0.1/api#SOCKET_API_URL = http://127.0.0.1:8080#" "$ENV"

grep -E 'baseURL|SOCKET_' "$ENV"

cd /var/www/belly/api
php spark migrate --all
php spark db:seed "Belly\\Database\\Seeds\\InitSeeder"

mkdir -p writable/uploads writable/cache writable/logs writable/session
if [[ ! -L public/uploads ]]; then
  ln -sfn /var/www/belly/api/writable/uploads /var/www/belly/api/public/uploads
fi

if [[ ! -d /var/www/belly/front-src/.git ]]; then
  git clone --branch main https://github.com/BryanCoronado/belly-front.git /var/www/belly/front-src
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
echo "HTTP listo. Ahora el certificado:"
echo "apt-get install -y certbot python3-certbot-nginx"
echo "certbot --nginx -d api.micrm.space -d app.micrm.space"
