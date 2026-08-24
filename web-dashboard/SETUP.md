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

---

## Enable HTTPS (required for the camera "Scan" page)

The **Scan** menu uses the phone camera. Browsers only allow camera access
(`getUserMedia`) on a **secure context** — i.e. `https://` or `localhost`. Over
a plain `http://10.0.0.x` LAN address the camera is blocked and the page falls
back to a type-in box. To scan, serve the dashboard over HTTPS.

This needs the **nginx + php-fpm** hosting from *Option B* above (`php artisan
serve` cannot terminate TLS). Config files are in [`deploy/`](deploy/).

### 1. Make a certificate for your container's IP

`make-selfsigned-cert.sh` writes a self-signed cert (with the IP baked in as a
SAN) to `/etc/ssl/glpi-dashboard/`:

```bash
hostname -I                                   # find the container IP, e.g. 10.0.0.184
cd /opt/glpi-middleware-api/web-dashboard
sudo bash deploy/make-selfsigned-cert.sh 10.0.0.184
```

### 2. Switch nginx to the HTTPS site

```bash
sudo cp deploy/nginx-glpi-dashboard-ssl.conf /etc/nginx/sites-available/glpi-dashboard
sudo ln -sf /etc/nginx/sites-available/glpi-dashboard /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
ls /run/php/                                   # confirm the fpm socket, edit the conf if it isn't php8.3
sudo nginx -t && sudo systemctl reload nginx
```

Set `APP_URL=https://10.0.0.184` in `.env`, then `php artisan config:cache`.
HTTP is now redirected to HTTPS automatically. Open **`https://10.0.0.184`**.

### 3. Trust the certificate on the phone

A self-signed cert makes the browser warn once. Two choices:

- **Quick:** on the phone, open `https://10.0.0.184`, tap **Advanced → Proceed
  anyway**. The camera works immediately (bypassing the warning still counts as
  a secure context). Chrome may re-warn after a while.
- **No warnings (recommended for daily use):** copy the cert to the phone and
  install it as a trusted CA.
  ```bash
  # from the container, serve the cert briefly, or scp it to your PC:
  sudo cp /etc/ssl/glpi-dashboard/dashboard.crt /opt/glpi-middleware-api/web-dashboard/public/dashboard.crt
  ```
  On the phone open `https://10.0.0.184/dashboard.crt` to download it, then
  **Settings → Security → Encryption & credentials → Install a certificate → CA
  certificate** and pick the file. **Delete the public copy afterwards:**
  `sudo rm /opt/glpi-middleware-api/web-dashboard/public/dashboard.crt`.

> **Cleaner alternative — [`mkcert`](https://github.com/FiloSottile/mkcert):**
> generates a locally-trusted CA so no device shows warnings.
> ```bash
> sudo apt install -y libnss3-tools && \
>   curl -L -o mkcert https://dl.filippo.io/mkcert/latest?for=linux/amd64 && \
>   chmod +x mkcert && sudo mv mkcert /usr/local/bin/
> mkcert -install
> mkcert -cert-file /etc/ssl/glpi-dashboard/dashboard.crt \
>        -key-file  /etc/ssl/glpi-dashboard/dashboard.key 10.0.0.184
> ```
> Install `mkcert -CAROOT`/rootCA.pem on the phone once (as above) and every
> cert it issues is trusted with no per-page warning.

---

## User Access & Roles (RBAC)

By default the dashboard shows every module to every logged-in user. Role-based
access control lets an administrator decide **who can view the audit asset
records, who can use the Forms module, and who can create / edit / delete**. If a
user has no access to a module, its menu link simply does not appear.

Access is stored in the **middleware** database (tables `rbac_roles` and
`rbac_user_roles`), created automatically the next time the middleware starts.

### 1. Set the first administrator (do this before anything else)

So you can reach the **User Access** page — and never get locked out — name
yourself a *super admin* in the dashboard `.env`. Super admins are always full
Administrators regardless of the database:

```ini
RBAC_SUPER_ADMINS=your.glpi.username      # comma-separated for several people
RBAC_DEFAULT_ROLE=Viewer                  # role for anyone not yet assigned
```

Then reload config:

```bash
php artisan config:clear   # or: php artisan config:cache  (if you cache config)
```

Restart the middleware once so the RBAC tables are created and seeded with four
starter roles: **Administrator, Editor, Viewer, Forms Operator**.

### 2. Assign people to roles

Log in as a super admin → **User Access** (left menu, admin-only):

- **Assign a user to a role** — enter the person's **GLPI username** (the one
  they log in with) and pick a role. Anyone not assigned gets `RBAC_DEFAULT_ROLE`.
- **Roles** — tick, per module (*Audit asset records*, *Forms Tracking*), which
  actions the role allows: **View / Execute / Edit / Delete**. *Execute* means
  create/scan (and, for Forms, advance the status). An **Administrator** role has
  full access and can manage this page; its checkboxes are ignored.

> `Viewer` = read-only audit records, no Forms. `Forms Operator` = Forms only.
> `Editor` = audit + forms, no delete. Adjust or add roles as you like.

### 3. What each permission gates

| Module | View | Execute | Edit | Delete |
|--------|------|---------|------|--------|
| **Audit asset records** | Dashboard, Asset Review, Discrepancy, Audit Trail, Report | Scan + Manual entry | Edit a scanned record | Delete a scanned record |
| **Forms Tracking** | Forms list + detail | Scan/upload a form + change its status | Edit form fields | Delete a form |

Denied pages return **403** even if a link is reached directly — the check is
enforced on the server, not just hidden in the menu.

---

## Forms OCR Tracking

The **Forms (OCR)** module logs forms you receive: scan/upload each one, it is
read automatically (OCR) so the text is searchable, and it moves through
**Pending Approval → Approved → Completed** with a permanent status history.
Visibility and actions are governed by the *Forms Tracking* permission above.

Using it (with the `forms` permission): **Forms (OCR)** → **Scan / upload form**,
attach a photo/scan (phone camera or file, multiple pages allowed), fill any
known fields, and save. It is logged as *Pending Approval*; open it to correct
fields, advance the status, read the OCR text, or delete it (deletes are archived
to the Audit Trail).

### Enable offline OCR (Tesseract in the middleware)

The middleware degrades gracefully — forms **still upload and track without OCR**;
follow this to turn on automatic text extraction. It runs entirely on the LAN.

On the **middleware** host (where `server.js` lives, inside the
`glpi-middleware-api` repo), from the folder containing `package.json`:

```bash
npm install tesseract.js      # one-time; needs internet during install only
```

Then bundle the English language data so it works **offline** (the default fetch
is from a CDN and fails on a disconnected LAN):

```bash
mkdir -p ocr-lang
# download eng.traineddata into ocr-lang/ (fast model):
curl -L -o ocr-lang/eng.traineddata \
  https://github.com/tesseract-ocr/tessdata_fast/raw/main/eng.traineddata
```

Restart the middleware. Full details, options (`OCR_LANG`, `OCR_LANG_PATH`) and
other languages are in [`../deploy/ocr/README.md`](../deploy/ocr/README.md).

---

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
| Scan page says "camera only works over HTTPS" | You opened it over `http://`. Enable HTTPS (section above) and open `https://…`. |
| Scan page says "cannot decode QR codes" | The browser lacks `BarcodeDetector` — use **Chrome or Edge on Android**, or the Android app. |
| Camera warning won't clear on the phone | Trust the cert (SETUP §3) or use `mkcert`; or just tap **Advanced → Proceed**. |
| No **User Access** menu / can't reach `/access` | Add your GLPI username to `RBAC_SUPER_ADMINS` in `.env`, then `php artisan config:clear`. |
| All menu links missing after login | The user has no role and `RBAC_DEFAULT_ROLE` is empty/invalid — assign them a role, or set `RBAC_DEFAULT_ROLE=Viewer`. |
| Roles/Forms pages error or empty | Restart the **middleware** so the `rbac_*` / `forms*` tables are created; check `curl http://10.0.0.184:3003/api/rbac/roles`. |
| Forms save but OCR text is empty | OCR not enabled — `npm install tesseract.js` on the middleware and add `ocr-lang/eng.traineddata` (see [`deploy/ocr/README.md`](../deploy/ocr/README.md)). Also depends on scan quality. |
