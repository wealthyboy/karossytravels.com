#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/home/forge/karossytravels.online}"
DEPLOY_USER="${DEPLOY_USER:-forge}"
DOMAIN="${DOMAIN:-karossytravels.online}"
GITHUB_REPOSITORY="${GITHUB_REPOSITORY:-wealthyboy/karossytravels.com}"
SITE_CONFIG="/etc/nginx/sites-available/${DOMAIN}"

[[ "${EUID}" -eq 0 ]] || { printf 'Run as root.\n' >&2; exit 1; }
[[ -f "${APP_DIR}/deploy/webhook-server.py" ]] || {
    printf 'Webhook server source was not found in %s.\n' "${APP_DIR}" >&2
    exit 1
}
[[ -f "${SITE_CONFIG}" ]] || { printf 'Nginx site was not found.\n' >&2; exit 1; }

install -d -m 755 /opt/karossy-webhook
install -m 755 "${APP_DIR}/deploy/webhook-server.py" /opt/karossy-webhook/server.py

if [[ ! -f /etc/karossy-webhook.env ]]; then
    webhook_secret="$(openssl rand -hex 32)"
    cat > /etc/karossy-webhook.env <<ENV
WEBHOOK_SECRET=${webhook_secret}
WEBHOOK_REPOSITORY=${GITHUB_REPOSITORY}
WEBHOOK_REF=refs/heads/main
WEBHOOK_PORT=9100
WEBHOOK_DEPLOY_COMMAND=/usr/local/bin/deploy-karossy
WEBHOOK_DEPLOY_LOG=${APP_DIR}/storage/logs/deploy.log
ENV
fi
chown root:"${DEPLOY_USER}" /etc/karossy-webhook.env
chmod 640 /etc/karossy-webhook.env

cat > /etc/systemd/system/karossy-webhook.service <<SERVICE
[Unit]
Description=Karossy GitHub deployment webhook
After=network.target

[Service]
Type=simple
User=${DEPLOY_USER}
Group=${DEPLOY_USER}
EnvironmentFile=/etc/karossy-webhook.env
ExecStart=/usr/bin/python3 /opt/karossy-webhook/server.py
Restart=always
RestartSec=3
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=read-only
# The signed push handler launches the forge-owned deployment script. Grant it
# write access only to this application checkout and the deployment lock path.
ReadWritePaths=${APP_DIR} /tmp

[Install]
WantedBy=multi-user.target
SERVICE

cat > /etc/nginx/snippets/karossy-webhook.conf <<'NGINX'
location = /deploy/webhook {
    client_max_body_size 2m;
    proxy_pass http://127.0.0.1:9100/github;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
NGINX

if ! grep -q 'karossy-webhook.conf' "${SITE_CONFIG}"; then
    sed -i '/^[[:space:]]*location ~ \\.php\$/i\    include /etc/nginx/snippets/karossy-webhook.conf;' "${SITE_CONFIG}"
fi

nginx -t
systemctl daemon-reload
systemctl enable --now karossy-webhook
# `enable --now` does not reload an already running listener. Restart it so
# parser and security updates are active immediately after reinstallation.
systemctl restart karossy-webhook
systemctl reload nginx

printf 'Webhook installed at: http://%s/deploy/webhook\n' "${DOMAIN}"
printf 'Copy its secret with: sudo cat /etc/karossy-webhook.env\n'
