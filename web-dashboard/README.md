# GLPI Audit Dashboard (Laravel)

A read-only web dashboard for reviewing asset audits. It **consumes the Node/Express
middleware API** in the parent repo — it does not touch the database directly — and
displays scanned items together with the photos captured during the audit.

## Features

- **Audit selector** — pick any audit from the middleware.
- **Summary cards** — scanned / found / missing / with-photo counts.
- **Scanned items grid** — asset tag, serial, found/missing badge, auditor + timestamp,
  and a photo thumbnail.
- **Detail view** — full record with the large photo and the audit checklist rendered
  as Yes/No.
- Degrades gracefully: if the middleware is unreachable, the page still loads with a
  clear warning instead of erroring.

## How it talks to the middleware

| Dashboard needs | Middleware endpoint |
|-----------------|---------------------|
| Audit list | `GET /api/audits` |
| Scanned items for an audit | `GET /api/audit/:auditId/scanned-items` |
| Audit photo | `GET /api/audit/result/:auditResultId/image` |

The photo `GET /api/audit/result/:id/image` endpoint was added to the middleware to make
this dashboard (and the Android review screen) possible.

## Requirements

- PHP 8.2+
- Composer
- A running instance of the GLPI middleware API

## Setup

```bash
cd web-dashboard
composer install
cp .env.example .env
php artisan key:generate

# Point the dashboard at your middleware (must be reachable from the PHP
# server AND from the browser, since photos load as <img> tags):
#   MIDDLEWARE_BASE_URL=http://your-middleware-host:3003

php artisan serve
# open http://localhost:8000
```

## Configuration

| .env key | Purpose | Default |
|----------|---------|---------|
| `MIDDLEWARE_BASE_URL` | Base URL of the Node middleware | `http://localhost:3003` |
| `MIDDLEWARE_TIMEOUT` | HTTP timeout (seconds) | `15` |

The app is stateless/DB-less: `SESSION_DRIVER`, `CACHE_STORE` and `QUEUE_CONNECTION`
default to `file`/`sync` so no database is required.

## Structure

```
app/Services/MiddlewareClient.php      HTTP client for the middleware API
app/Http/Controllers/DashboardController.php  index (dashboard) + show (detail)
resources/views/                       Blade views (layout, dashboard, detail)
routes/web.php                         / and /audit/{auditId}/result/{resultId}
config/services.php                    middleware base_url / timeout
```
