#!/usr/bin/env bash
# bin/deploy.sh — corre EN EL SERVIDOR, lo invoca GitHub Actions.
# Sirve igual en pruebas (Nginx + php-fpm) y en produccion (Apache + mod_php).

set -euo pipefail
export COMPOSER_ALLOW_SUPERUSER=1

cd "$(dirname "$0")/.."
echo "==> Desplegando en $(pwd) — $(date '+%Y-%m-%d %H:%M:%S')"

trap 'php artisan up >/dev/null 2>&1 || true' EXIT

echo "==> Modo mantenimiento"
php artisan down --retry=15 || true

echo "==> Dependencias PHP"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Migraciones"
php artisan migrate --force

# La versión sale de las fusiones (PRs) desde la etiqueta v2.0, sin commits:
#   main    -> v2.N            (N = fusiones a main)
#   develop -> v2.(N+1)-beta.K (la próxima, con K fusiones aún no en main)
# Va antes de config:cache, que es quien la lee de VERSION.
echo "==> Versión"
if git rev-parse -q --verify "refs/tags/v2.0" >/dev/null; then
  N=$(git rev-list --count --merges v2.0..origin/main)
  if [ "$(git branch --show-current)" = "main" ]; then
    VERSION="v2.$N"
  else
    K=$(git rev-list --count --merges origin/main..HEAD)
    VERSION="v2.$((N + 1))-beta.$K"
  fi
else
  VERSION="v2.0"
fi
echo "$VERSION" > VERSION
echo "   $VERSION"

echo "==> Enlace de storage"
php artisan storage:link 2>/dev/null || true

echo "==> Regenerando caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Si CHANGELOG.md trae la sección de esta versión, se anuncia una sola vez
# por la campana. Que falle no tumba el despliegue.
echo "==> Aviso de versión"
php artisan avisos:version || true

echo "==> Reiniciando trabajadores de cola"
php artisan queue:restart || true

# El deploy corre como root: sin esto www-data no puede escribir y el sitio
# revienta con 500 en la siguiente subida de documento.
echo "==> Devolviendo la propiedad a www-data"
chown -R www-data:www-data .
# ug+rwX: la X mayuscula da permiso de ejecucion SOLO a las carpetas.
chmod -R ug+rwX storage bootstrap/cache

# Recarga solo lo que este corriendo en este servidor.
echo "==> Recargando servicios web"
systemctl is-active --quiet php8.3-fpm && systemctl reload php8.3-fpm && echo "   php-fpm" || true
systemctl is-active --quiet apache2    && systemctl reload apache2    && echo "   apache2" || true
systemctl is-active --quiet nginx      && systemctl reload nginx      && echo "   nginx"   || true

echo "==> Despliegue completado"
