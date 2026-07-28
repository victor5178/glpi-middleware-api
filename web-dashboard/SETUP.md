# Web Dashboard — Setup Guide

Step-by-step install of the Laravel audit dashboard, e.g. on a Proxmox Ubuntu
LXC container. The dashboard is a **read-only viewer** — it has no database of
its own and calls the Node middleware API for all data.

## Network layout

| Host | Role | Talked to by |
|------|------|--------------|
| `10.0.0.11` | GLPI REST API (`/glpi/apirest.php/`) — login + inventory lookup | Android app only |
| `10.0.0.184:3003` | **Middleware** API (Node/Express) — audit data + photos | **This dashboard** and the Android app |
| `10.0.0.183` | MySQL database (middleware inserts here) | Only the middleware (`db.config.js`) |

> The dashboard talks **only** to the middleware (`10.0.0.184:3003`). It never
> contacts GLPI (`10.0.0.11`) or MySQL (`10.0.0.183`) directly.

## Prerequisite

The middleware must be running and reachable, or the dashboard loads but shows a
"Could not reach the middleware" banner. Verify:

```bash
curl http://10.0.0.184:3003/api/audits      # expect JSON
```

Photos load in the **browser** directly from the middleware, so the machine you
open the dashboard from must also reach `http://10.0.0.184:3003`.

---

## 1. Get the code from git

The dashboard lives in the `glpi-middleware-api` repo under `web-dashboard/`.
While it is still on the feature branch (not yet merged to `main`):

```bash
sudo apt update && sudo apt install -y git
cd /opt
git clone https://github.com/victor5178/glpi-middleware-api.git
cd glpi-middleware-api

git fetch origin
git checkout claude/android-asset-scanner-improvements-dn914o

ls -d web-dashboard          # must exist
```

The repo is private — when prompted, use your GitHub username and a Personal
Access Token (Settings → Developer settings → tokens, `repo` scope) as the
password.

After PR #1 is merged to `main`, use `git checkout main && git pull origin main`
instead.

To update later:

```bash
cd /opt/glpi-middleware-api
git pull origin claude/android-asset-scanner-improvements-dn914o
```

## 2. Install PHP + Composer

Laravel 11 needs **PHP ≥ 8.2**. Ubuntu 24.04 ships PHP 8.3 (fine).

```bash
sudo apt install -y php-cli php-mbstring php-xml php-curl php-bcmath php-zip php-intl unzip composer
php -v          # confirm 8.2+
```

<details>
<summary>Ubuntu 22.04 (PHP 8.1 — too old): add the PPA</summary>

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-bcmath php8.3-zip php8.3-intl
```
</details>

## 3. Install dependencies

Run Composer **inside `web-dashboard/`** (that's where `composer.json` is):

```bash
export COMPOSER_ALLOW_SUPERUSER=1     # silences the root warning (fine in a test LXC)
cd /opt/glpi-middleware-api/web-dashboard
composer install --no-dev --optimize-autoloader
```

`vendor/` is generated here and is intentionally not in git.

## 4. Configure

```bash
cp .env.example .env
php artisan key:generate
nano .env
```

Set at least:

```ini
APP_URL=http://<container-ip>:8000
MIDDLEWARE_BASE_URL=http://10.0.0.184:3003
```

Make the runtime dirs writable:

```bash
sudo chmod -R ug+rwX storage bootstrap/cache
```

## 5. Run (testing)

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

Open `http://<container-ip>:8000` from your PC (`hostname -I` shows the IP).

---

## Keep it running (beyond `php artisan serve`)

`php artisan serve` stops when you close the shell. Two ways to keep it up —
config files are in [`deploy/`](deploy/).

### Option A — systemd + `artisan serve` (simple, good for a LAN/test container)

```bash
sudo cp deploy/glpi-dashboard.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now glpi-dashboard
systemctl status glpi-dashboard          # runs on port 8000, restarts on reboot
```

### Option B — nginx + php-fpm (proper hosting)

```bash
sudo apt install -y nginx php-fpm
sudo cp deploy/nginx-glpi-dashboard.conf /etc/nginx/sites-available/glpi-dashboard
sudo ln -s /etc/nginx/sites-available/glpi-dashboard /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
ls /run/php/                              # confirm the fpm socket name, edit the conf if needed
sudo nginx -t && sudo systemctl reload nginx
sudo chown -R www-data:www-data storage bootstrap/cache
```

Then cache config for speed (re-run after any code/config change):

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Troubleshooting

| Symptom | Fix |
|---------|-----|
| `composer could not find a composer.json` | You're in the repo root — `cd web-dashboard` first. |
| `web-dashboard` folder missing | Wrong branch — `git checkout claude/android-asset-scanner-improvements-dn914o`. |
| "Do not run Composer as root" prompt | Harmless in a test LXC. `export COMPOSER_ALLOW_SUPERUSER=1` or answer `yes`. |
| "Could not reach the middleware" banner | `curl http://10.0.0.184:3003/api/audits` from the container; check the URL and that the middleware is up. |
| Photos show as broken/placeholder | Your **browser** can't reach `http://10.0.0.184:3003` — photos load client-side. |
| 500 error / "Database ... does not exist" | Ensure `.env` has `SESSION_DRIVER=file` (the shipped `.env.example` already sets it). |
| Page loads but unstyled | Confirm `/css/app.css` returns 200; check `APP_URL` and file permissions. |
