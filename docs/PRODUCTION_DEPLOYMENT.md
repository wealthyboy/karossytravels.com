# Karossy production deployment

This setup targets a fresh Ubuntu 24.04 DigitalOcean Droplet. Every push to
`main` runs tests and a frontend build, then connects to the server and runs a
locked deployment: `git pull`, dependency installation, migrations, Laravel
cache rebuilding, and queue restart.

## 1. Domain and Droplet

1. Buy the domain and create an Ubuntu 24.04 Droplet. Use at least 2 GB RAM;
   4 GB is safer while Composer and Vite build alongside queue workers.
2. Add DNS `A` records for `@` and `www` pointing to the Droplet IPv4 address.
3. Add your SSH public key while creating the Droplet.
4. Enable DigitalOcean backups. Before taking real bookings, use a managed
   database or configure encrypted, off-server MySQL backups.

## 2. Run the one-time installer

Copy `deploy/bootstrap-ubuntu.sh` to the server and run it as root:

```bash
DOMAIN=karossytravels.com \
SITE_FOLDER=karossytravels.com \
REPO_URL=git@github.com:YOUR_ACCOUNT/YOUR_REPOSITORY.git \
DB_PASSWORD='GENERATE-A-LONG-RANDOM-PASSWORD' \
LETSENCRYPT_EMAIL=admin@karossytravels.com \
bash bootstrap-ubuntu.sh
```

For a private repository, the first attempt may print the server's public key.
Add that key under **GitHub repository settings → Deploy keys** with read-only
access and run the installer again.

It installs Nginx, PHP-FPM, MySQL, Node, Composer, Supervisor and Certbot. It
also creates or uses the `forge` user, puts the application in
`/home/forge/{site_folder}`, configures two queue workers, the Laravel scheduler
cron, the firewall, the database, and the HTTPS certificate.

## 3. Configure production `.env`

Edit `/home/forge/karossytravels.com/.env` directly on the server. Never commit it.
At minimum confirm:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://karossytravels.com
TRAVEL_PROVIDER=travel_api
TRAVEL_LOCAL_CALLBACK_FINALIZATION=false
```

Add the production database values, mail credentials, Paystack live keys,
travel-provider production credentials, agency/IATA details, and production
callback/webhook URLs. Apply the configuration with:

```bash
sudo -u forge /usr/local/bin/deploy-karossy
```

## 4. Connect GitHub Actions

Generate a dedicated deployment key on your computer:

```bash
ssh-keygen -t ed25519 -C karossy-github-actions -f karossy_github_actions
```

Append the public `.pub` key to `/home/forge/.ssh/authorized_keys` on the
Droplet. Add these GitHub repository secrets:

| Secret | Value |
| --- | --- |
| `PRODUCTION_HOST` | Droplet IPv4 address or hostname |
| `PRODUCTION_SSH_PORT` | `22` unless it was changed |
| `PRODUCTION_SSH_USER` | `forge` |
| `PRODUCTION_SSH_PRIVATE_KEY` | Complete contents of the private key |

The workflow uses GitHub's `production` environment. Add required reviewers to
that environment if production deployment should need approval. A successful
push to `main` will deploy automatically.

## 5. Booking and payment safety before launch

- Keep the local callback bypass disabled in production.
- Verify Paystack transactions server-side and process signed webhooks.
- Ensure payment and supplier booking handlers are idempotent so retries cannot
  create duplicate charges or reservations.
- Make a controlled low-value end-to-end production booking before launch.
- Add uptime monitoring, log alerts and automated database restore tests.

## Useful commands

```bash
sudo -u forge /usr/local/bin/deploy-karossy
sudo supervisorctl status
sudo tail -f /home/forge/karossytravels.com/storage/logs/laravel.log
sudo nginx -t
sudo certbot renew --dry-run
```
