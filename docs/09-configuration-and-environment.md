# 09 — Configuration, Environment & Workflow

## Requirements

- PHP **≥ 8.1** (composer.json: `"php": "^8.1"`; README says ≥7.4 but is outdated)
- Composer, Node.js + npm, MySQL, Git

## Composer Packages

| Package | Purpose |
|---------|---------|
| `laravel/framework ^10.0` | Framework |
| `guzzlehttp/guzzle ^7.9` | HTTP client (used by `Http` facade for Dropbox calls) |
| `laravel/sanctum ^3.2` | API tokens (on `User`; not actively used) |
| `laravel/tinker ^2.8` | REPL |
| `laravel/ui ^4.6` | Auth scaffolding (controllers + auth Blade views) |
| `pion/laravel-chunk-upload ^1.5` | Chunked file upload handling |
| `spatie/laravel-permission ^6.13` | Roles & permissions |
| Dev: `phpunit ^10`, `faker`, `mockery`, `laravel/pint`, `laravel/sail`, `nunomaduro/collision`, `spatie/laravel-ignition` | Testing & DX |

## Key `.env` Variables

Standard Laravel set (see `.env.example`):

```env
APP_NAME= / APP_ENV=local / APP_KEY= / APP_DEBUG=true / APP_URL=
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
SESSION_DRIVER=file
SESSION_LIFETIME=120
MAIL_MAILER=smtp / MAIL_HOST=... / MAIL_PORT=... (password resets use mail)
CACHE_DRIVER=file / QUEUE_CONNECTION=sync / FILESYSTEM_DISK=local
```

Notes:
- Dropbox credentials are **not** stored in `.env` — each Dropbox account lives in the `dropbox_accounts` DB table (client_id, client_secret, refresh_token) and is added via the dashboard.
- No dedicated Dropbox service keys in `config/services.php`.

## Config Files (`config/`)

`app, auth, broadcasting, cache, cors, database, filesystems, hashing, logging, mail, permission, queue, sanctum, services, session, view`.
- `config/auth.php` — defines the `web` + `admin` guards/providers (see 08).
- `config/permission.php` — Spatie settings.

## Artisan Commands (everyday)

```bash
composer install              # PHP deps
npm install && npm run build  # Node deps + Vite build
cp .env.example .env          # then edit DB vars
php artisan key:generate      # app key
php artisan migrate           # create schema
php artisan db:seed           # seed (departments, branches, subjects, roles, default admin)
php artisan serve             # http://localhost:8000
php artisan schedule:run      # needed in cron for dropbox token refresh (every 3h)
php artisan dropbox:refresh-tokens   # manual token refresh
php artisan test              # PHPUnit (only example tests exist)
php artisan pint              # code style (Laravel Pint)
```

First-time setup shortcut: `php artisan migrate --seed` (seeds everything including the default super admin).

## Scheduler (`app/Console/Kernel.php`)

- `$schedule->command('dropbox:refresh-tokens')->everyThreeHours();`
- Requires cron: `* * * * * php /path/to/artisan schedule:run`

## Testing (`phpunit.xml`)

- `tests/Unit` + `tests/Feature` — currently only Laravel's `ExampleTest` scaffolds. `TestCase`/`CreatesApplication` present. No domain tests yet.

## Coding Conventions Observed in This Codebase

1. Controllers are plain (no constructor injection except `DropboxService` in `DropboxController`); inline `$request->validate(...)` is preferred over FormRequest classes.
2. Resource controllers keep scaffolded empty methods — only implemented actions do work.
3. Blade views live under `dashboard.pages.*` / `website.pages.*` matching controllers.
4. UI logic lives in per-area vanilla JS files under `public/assets/` (AJAX via fetch/axios).
5. Flash messages via `->with('success'|'error', ...)` and an `alerts.blade.php` include in the dashboard.
6. Known quirks (do not silently change): typo folders (`login-singup`, `genration`, `handel-search-requset.js`), `Auth::admin()` in AdminMiddleware, dead `AboutController::getSuggestions()` with `dd()`, `DropboxService::verifyCredentials` disabling SSL verification.
