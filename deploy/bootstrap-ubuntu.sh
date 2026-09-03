#!/usr/bin/env bash

set -Eeuo pipefail

# One-time Ubuntu 24.04 setup. Required variables: DOMAIN, REPO_URL,
# DB_PASSWORD and LETSENCRYPT_EMAIL (unless ENABLE_SSL=false).
DOMAIN="${DOMAIN:-}"
REPO_URL="${REPO_URL:-}"
DB_NAME="${DB_NAME:-karossy}"
DB_USER="${DB_USER:-karossy}"
DB_PASSWORD="${DB_PASSWORD:-}"
DEPLOY_USER="${DEPLOY_USER:-forge}"
SITE_FOLDER="${SITE_FOLDER:-${DOMAIN}}"
APP_DIR="${APP_DIR:-/home/${DEPLOY_USER}/${SITE_FOLDER}}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
LETSENCRYPT_EMAIL="${LETSENCRYPT_EMAIL:-}"
ENABLE_SSL="${ENABLE_SSL:-true}"

fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
[[ "${EUID}" -eq 0 ]] || fail "Run this script as root."
[[ "${DOMAIN}" =~ ^[A-Za-z0-9.-]+$ ]] || fail "Set DOMAIN to a valid hostname."
[[ -n "${REPO_URL}" ]] || fail "Set REPO_URL to the GitHub SSH URL."
[[ "${DB_NAME}" =~ ^[A-Za-z0-9_]+$ ]] || fail "DB_NAME is invalid."
[[ "${DB_USER}" =~ ^[A-Za-z0-9_]+$ ]] || fail "DB_USER is invalid."
[[ "${DB_PASSWORD}" =~ ^[A-Za-z0-9_.@%+=:-]{20,}$ ]] || \
    fail "DB_PASSWORD must be at least 20 characters using letters, numbers, and ._@%+=:- only."
[[ "${DEPLOY_USER}" =~ ^[a-z_][a-z0-9_-]*$ ]] || fail "DEPLOY_USER is invalid."
[[ "${SITE_FOLDER}" =~ ^[A-Za-z0-9._-]+$ ]] || fail "SITE_FOLDER is invalid."
[[ "${DEPLOY_BRANCH}" =~ ^[A-Za-z0-9._/-]+$ ]] || fail "DEPLOY_BRANCH is invalid."

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y ca-certificates curl git nginx mysql-server supervisor certbot \
    python3-certbot-nginx unzip acl ufw fail2ban nodejs npm \
    php-cli php-fpm php-mysql php-curl php-mbstring php-xml php-zip php-bcmath \
    php-intl php-gd php-redis

if ! command -v composer >/dev/null 2>&1; then
    expected="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    actual="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "${expected}" == "${actual}" ]] || fail "Composer installer signature mismatch."
    php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
fi

id "${DEPLOY_USER}" >/dev/null 2>&1 || adduser --disabled-password --gecos '' "${DEPLOY_USER}"
usermod -aG www-data "${DEPLOY_USER}"
install -d -m 700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
if [[ ! -f "/home/${DEPLOY_USER}/.ssh/id_ed25519" ]]; then
    sudo -u "${DEPLOY_USER}" ssh-keygen -q -t ed25519 -N '' \
        -C "${DEPLOY_USER}@${DOMAIN}" -f "/home/${DEPLOY_USER}/.ssh/id_ed25519"
fi
sudo -u "${DEPLOY_USER}" ssh-keyscan -H github.com >> "/home/${DEPLOY_USER}/.ssh/known_hosts" 2>/dev/null || true
sort -u "/home/${DEPLOY_USER}/.ssh/known_hosts" -o "/home/${DEPLOY_USER}/.ssh/known_hosts"
chown "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh/known_hosts"
chmod 600 "/home/${DEPLOY_USER}/.ssh/known_hosts"

escaped_db_password="${DB_PASSWORD//\'/\'\'}"
mysql --protocol=socket <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${escaped_db_password}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${escaped_db_password}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

install -d -m 775 -o "${DEPLOY_USER}" -g www-data "${APP_DIR}"
if [[ ! -d "${APP_DIR}/.git" ]]; then
    if ! sudo -u "${DEPLOY_USER}" git clone --branch "${DEPLOY_BRANCH}" --single-branch "${REPO_URL}" "${APP_DIR}"; then
        printf '\nAdd this as a read-only GitHub Deploy Key, then rerun the installer:\n\n'
        cat "/home/${DEPLOY_USER}/.ssh/id_ed25519.pub"
        exit 2
    fi
fi
install -m 755 "${APP_DIR}/deploy/deploy.sh" /usr/local/bin/deploy-karossy
cat > /etc/karossy-deploy.env <<DEPLOY_ENV
APP_DIR=${APP_DIR}
DEPLOY_BRANCH=${DEPLOY_BRANCH}
DEPLOY_ENV
chmod 644 /etc/karossy-deploy.env

php_fpm_socket="$(find /run/php -maxdepth 1 -name 'php*-fpm.sock' -print -quit)"
[[ -n "${php_fpm_socket}" ]] || fail "PHP-FPM socket was not found."
cat > "/etc/nginx/sites-available/${DOMAIN}" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN} www.${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 20M;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt { access_log off; log_not_found off; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${php_fpm_socket};
        fastcgi_read_timeout 120;
    }
    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sfn "/etc/nginx/sites-available/${DOMAIN}" "/etc/nginx/sites-enabled/${DOMAIN}"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable --now nginx mysql supervisor fail2ban
systemctl reload nginx

cat > /etc/supervisor/conf.d/karossy-worker.conf <<SUPERVISOR
[program:karossy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=${DEPLOY_USER}
numprocs=2
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/worker.log
stopwaitsecs=3600
SUPERVISOR
printf '* * * * * %s cd %s && php artisan schedule:run >> /dev/null 2>&1\n' \
    "${DEPLOY_USER}" "${APP_DIR}" > /etc/cron.d/karossy-scheduler
chmod 644 /etc/cron.d/karossy-scheduler

ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

if [[ ! -f "${APP_DIR}/.env" ]]; then
    cp "${APP_DIR}/.env.example" "${APP_DIR}/.env"
    sed -i -e 's/^APP_ENV=.*/APP_ENV=production/' -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
        -e "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" -e 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' \
        -e 's/^# DB_HOST=.*/DB_HOST=127.0.0.1/' -e 's/^# DB_PORT=.*/DB_PORT=3306/' \
        -e "s/^# DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" -e "s/^# DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" \
        -e "s/^# DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" "${APP_DIR}/.env"
fi
chown -R "${DEPLOY_USER}:www-data" "${APP_DIR}"
chmod 640 "${APP_DIR}/.env"
chmod -R ug+rwX "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
sudo -u "${DEPLOY_USER}" env APP_DIR="${APP_DIR}" DEPLOY_BRANCH="${DEPLOY_BRANCH}" \
    /usr/local/bin/deploy-karossy --initial
supervisorctl reread
supervisorctl update
supervisorctl restart karossy-worker:*

if [[ "${ENABLE_SSL}" == "true" ]]; then
    [[ -n "${LETSENCRYPT_EMAIL}" ]] || fail "Set LETSENCRYPT_EMAIL or ENABLE_SSL=false."
    certbot --nginx --non-interactive --agree-tos --redirect \
        --email "${LETSENCRYPT_EMAIL}" -d "${DOMAIN}" -d "www.${DOMAIN}"
fi
printf '\nKarossy setup complete: https://%s\n' "${DOMAIN}"
