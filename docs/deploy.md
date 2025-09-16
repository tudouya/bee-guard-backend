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
Filament 依赖 Livewire/Alpine 脚本与 Livewire 路由（含上传/预览）。需要确保：
1) 资源可访问（可发布到 `public/vendor`）
2) 无论是否发布资源，`/livewire/*` 路由都必须进入 PHP（用于临时上传与预览）

Recommended (publish local static assets):
- `php artisan livewire:publish --assets --force`
- `php artisan filament:assets`

Then clear/prime caches:
- `php artisan optimize:clear`
- Optional: `php artisan config:cache && php artisan route:cache && php artisan view:cache`

Verify in browser/network:
- `GET /vendor/livewire/livewire.js` returns 200
- Filament assets under `/vendor/filament/...` return 200

Important:
- 即便发布了 Livewire 资源，临时文件“预览”仍依赖路由 `/livewire/preview-file/*`。因此生产环境务必保证 `/livewire/*` 能回退到 `index.php`（见下一节 Nginx 配置）。

## 5) Nginx reference config（务必放行 /livewire/* 到 PHP）
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

    # 强烈建议显式放行 /livewire/* 到 Laravel（用于 /livewire/upload-file 与 /livewire/preview-file）
    # 注意：该块必须位于任何静态资源正则块之前（否则 .png 等会被静态规则拦截为 404）
    location ^~ /livewire/ {
        try_files $uri /index.php?$query_string;
    }

    # 若存在静态资源正则块，请确保失败回退到 PHP（避免拦截 livewire 预览链接）
    location ~* \.(?:css|js|map|jpg|jpeg|gif|png|svg|webp|ico)$ {
        try_files $uri /index.php?$query_string;
        expires 30d;
        access_log off;
        add_header Cache-Control public;
    }

    location ~ \.php$ {
        include        fastcgi_params;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass   unix:/run/php/php8.2-fpm.sock; # adjust to your PHP-FPM
    }
}
```

### Livewire 临时上传与预览自检
- 无签名预览应返回 401：`GET /livewire/preview-file/test.png`
- 通过 Tinker 生成带签名的预览 URL 应返回 200：
  ```php
  URL::temporarySignedRoute('livewire.preview-file', now()->addMinutes(30)->endOfHour(), [
      'filename' => 'your-temp-file-name.png',
  ]);
  ```
- 若返回 404，说明 Nginx 未回退到 PHP，请按上面的 `location ^~ /livewire/` 与静态块顺序修正。

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
