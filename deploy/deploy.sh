#!/usr/bin/env bash

set -Eeuo pipefail

DEPLOY_CONFIG="${DEPLOY_CONFIG:-/etc/karossy-deploy.env}"
if [[ -f "${DEPLOY_CONFIG}" ]]; then
    # shellcheck disable=SC1090
    source "${DEPLOY_CONFIG}"
fi

APP_DIR="${APP_DIR:-/home/forge/karossytravels.com}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
INITIAL_DEPLOY=false
[[ "${1:-}" == "--initial" ]] && INITIAL_DEPLOY=true

cd "${APP_DIR}"
exec 9>"/tmp/karossy-deploy.lock"
flock -n 9 || { printf 'Another deployment is already running.\n' >&2; exit 1; }
[[ -f artisan ]] || { printf 'Laravel was not found in %s.\n' "${APP_DIR}" >&2; exit 1; }

if [[ "${INITIAL_DEPLOY}" == "false" ]]; then
    git fetch origin "${DEPLOY_BRANCH}"
    git checkout "${DEPLOY_BRANCH}"
    git pull --ff-only origin "${DEPLOY_BRANCH}"
fi

composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

php artisan down --retry=30 || true
restore_application() { php artisan up >/dev/null 2>&1 || true; }
trap restore_application EXIT

php artisan migrate --force
php artisan storage:link || true
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart

# Supervisor and PHP-FPM may create runtime files between deployments. Only
# change files owned by the deployment account: attempting to chmod an older
# root-owned log would otherwise abort an otherwise successful release. The
# installer establishes the shared directory ownership and setgid permissions.
DEPLOY_USER="$(id -un)"
find storage bootstrap/cache -user "${DEPLOY_USER}" -exec chmod ug+rwX {} +
[[ -w storage && -w bootstrap/cache ]] || {
    printf 'Laravel runtime directories are not writable by %s.\n' "${DEPLOY_USER}" >&2
    exit 1
}

php artisan up
trap - EXIT
printf 'Karossy deployment completed at %s.\n' "$(date --iso-8601=seconds)"
