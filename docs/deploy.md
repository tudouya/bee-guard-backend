# Bee Guard Backend — Deployment Guide

This checklist covers local and production deployments for the Laravel 12 + Filament v4 backend, ensuring Filament/Livewire assets and routes function correctly behind Nginx.

## 1) Prerequisites
- PHP 8.2 with extensions (mbstring, curl, openssl, pdo_mysql, etc.)
- Composer 2.x
- MySQL database created (name/user/pass/host ready)
- Nginx + PHP-FPM (root points to `public/`)
- Correct `.env` with `APP_KEY`, DB, SESSION, APP_URL

## 2) First-time setup
1. Clone code and install deps
   - `composer install --no-dev -o`
2. Environment
   - Copy `.env.example` → `.env` and set:
     - `APP_ENV=production`
     - `APP_URL=https://your-domain`
     - `APP_KEY=` (leave empty if unknown; generate below)
     - DB settings (mysql)
     - SESSION settings (see section 6)
3. App key (first time only if empty)
   - `php artisan key:generate`
4. Storage symlink (first time only)
   - `php artisan storage:link`

## 3) Database migrate/seed
- `php artisan migrate --force`
- Optional seed (for initial admin): `php artisan db:seed --force`

## 4) Front-end assets for Filament/Livewire
Filament login relies on Livewire/Alpine scripts. Ensure assets are available (either publish to `public/vendor` or route `/livewire/*` to PHP).

Recommended (publish local static assets):
- `php artisan livewire:publish --assets --force`
- `php artisan filament:assets`

Then clear/prime caches:
- `php artisan optimize:clear`
- Optional: `php artisan config:cache && php artisan route:cache && php artisan view:cache`

Verify in browser/network:
- `GET /vendor/livewire/livewire.js` returns 200
- Filament assets under `/vendor/filament/...` return 200

Alternative (if not publishing Livewire assets):
- Add an Nginx rule to pass `/livewire/*` to PHP (see section 5). Not needed if you publish assets.

## 5) Nginx reference config
Ensure `root` points to the Laravel `public/` directory.

```
server {
    server_name  your-domain.com;
    root         /path/to/bee-guard-backend/public;
    index        index.php index.html;

    # Primary rewrite
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Only needed if you DO NOT publish Livewire assets
    # location ^~ /livewire/ {
    #     try_files $uri /index.php?$query_string;
    # }

    location ~ \.php$ {
        include        fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass   unix:/run/php/php8.2-fpm.sock; # adjust to your PHP-FPM
    }
}
```

## 6) Sessions and cookies
- For HTTP (non-HTTPS) environments during development:
  - `SESSION_SECURE_COOKIE=false`
  - `SESSION_DOMAIN=null`
- Recommended `SESSION_DRIVER=file` to rule out DB session issues initially; switch to `database` after verifying.
- After changing session settings, run `php artisan optimize:clear` and clear browser cookies for the domain.

## 7) Admin/Enterprise panels
- Admin: `/admin` (requires user with `role=super_admin`)
- Enterprise: `/enterprise` (requires user with `role=enterprise_admin`)
- Seeder (optional): set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env`, then `php artisan db:seed --force`.

## 8) Common pitfalls & fixes
- Symptom: Clicking login does nothing; no network request.
  - Cause: Livewire/Filament JS not loaded.
  - Fix: Run `livewire:publish --assets` and `filament:assets`, or route `/livewire/*` to PHP.
- Symptom: Login keeps returning to login page.
  - Causes: `APP_KEY` missing, session misconfigured, or canAccessPanel() denies access.
  - Fix: `php artisan key:generate`, verify session driver/domain/secure cookie, and confirm `users.role` is correct.
- Symptom: 404 at `/admin`.
  - Causes: Panel providers not registered; Nginx root not `public/`; route cache stale.
  - Fix: Register providers in `bootstrap/providers.php`, set Nginx root, `php artisan optimize:clear`.

## 9) Deploy script example (production)
```
#!/usr/bin/env bash
set -euo pipefail

php -v
composer install --no-dev -o

# first time if empty
php artisan key:generate || true

php artisan migrate --force

php artisan livewire:publish --assets --force
php artisan filament:assets

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# optional: queue/reload services here
```

## 10) Rollback quick notes
- Keep DB backups/snapshots before `migrate --force`.
- Use `php artisan migrate:rollback --step=1` on failure (if safe).
- Revert code to previous tag/commit; rerun `optimize:clear`.

