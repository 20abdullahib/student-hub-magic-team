# 📚 Student Hub — Documentation Index (AI Entry Point)

> **⚠️ RULE FOR AI:** Read this file **first** every time you work on this project. It tells you which doc file to open for each topic so you never need to re-scan the whole codebase.

---

## Project One-Line Summary

**Student Hub** is a web platform (built by a student team, "Magic Team") where university students browse, search, preview and download academic course files. Files are stored in multiple Dropbox accounts (2 GB each) and organized by **Department → Branch → Subject → Files**. An **admin dashboard** manages admins, roles, team members and Dropbox accounts/uploads.

- **Framework:** Laravel 10 (PHP ≥ 8.1) · Blade templates · MySQL
- **Auth:** Dual guards — `web` (Users) and `admin` (Admins), roles/permissions via Spatie
- **Storage:** Dropbox (OAuth 2.0 refresh tokens, multiple accounts)
- **Frontend:** Plain CSS/JS in `public/assets` + Vite/Bootstrap 5 for base scaffolding

---

## 📖 Documentation Map

| File | What it covers | When to read it |
|------|----------------|-----------------|
| [01-overview.md](01-overview.md) | What the project is, tech stack, architecture, data flow | First time on the project; understanding the big picture |
| [02-project-structure.md](02-project-structure.md) | Full annotated directory tree of every folder/file | Finding where a file lives; adding new files |
| [03-database.md](03-database.md) | All tables, columns, FKs, ER diagram, migration list | Working on DB schema, migrations, queries |
| [04-models.md](04-models.md) | All 8 Eloquent models: fields, casts, relationships | Working with models/Eloquent/relationships |
| [05-controllers-and-routes.md](05-controllers-and-routes.md) | Complete route table (method/URI/middleware/name) + controller responsibilities | Adding/changing routes or controller logic |
| [06-services-and-dropbox.md](06-services-and-dropbox.md) | DropboxService, OAuth token lifecycle, upload/download flows, scheduled command | Dropbox integration, tokens, file upload |
| [07-views-and-frontend.md](07-views-and-frontend.md) | Blade view structure, public assets (CSS/JS), Vite setup | Building UI, styling, frontend scripts |
| [08-auth-and-permissions.md](08-auth-and-permissions.md) | Dual auth guards, middleware, Spatie roles & permissions, seeders | Auth, admin guard, roles, session timeout |
| [09-configuration-and-environment.md](09-configuration-and-environment.md) | Config files, `.env` variables, artisan commands, testing, conventions | Setup, deployment, configuration changes |

---

## 🧭 Quick Task Routing

| Task | Read first | Then touch these files |
|------|-----------|------------------------|
| Add a new table | `03-database.md` | `database/migrations/*`, `app/Models/*` |
| Add a public page | `05` + `07` | `routes/web.php`, `app/Http/Controllers/Website/*`, `resources/views/website/pages/*` |
| Add a dashboard page | `05` + `07` | `routes/web.php` (inside `auth:admin` group), `app/Http/Controllers/Dashboard/*`, `resources/views/dashboard/pages/*` |
| Add a new admin role/permission | `08` | `database/seeders/RolesAndPermissionsSeeder.php` or dashboard `PermissionController` |
| Change file upload behavior | `06` | `app/Http/Controllers/Dashboard/DropboxController.php`, `public/assets/Dashboard/scripts/UploadFiles.js` |
| Change download/preview links | `06` | `app/Http/Controllers/Website/ResourcesController.php` (`getDropboxLink`) |
| Change Dropbox token handling | `06` | `app/Services/DropboxService.php`, `app/Console/Commands/RefreshDropboxTokens.php` |
| Add frontend CSS/JS | `07` | `public/assets/<Area>/css|scripts` |
| Change session/timeout/auth | `08` | `app/Http/Middleware/*`, `config/auth.php` |
| Set up a fresh environment | `09` | `.env`, `composer install`, `php artisan migrate --seed` |

---

## 🔑 Key Facts Cheat-Sheet

- **Admin dashboard login:** `/dashboard/login` (guard `admin`), default seeded account `admin@admin.com` / `#bom123456` (role: `super admin`).
- **Scheduled job:** `dropbox:refresh-tokens` runs **every 3 hours** (`app/Console/Kernel.php`) to refresh all Dropbox access tokens.
- **Searchable entities:** Subjects are searchable by `name` or `code`; files belong to subjects; files reference Dropbox shared-link data (`file_id`, `rlkey`).
- **Storage model:** Each `dropbox_accounts` row = one Dropbox app + account with ~2 GB quota (`remaining_storage`).
- **Views never use Vite assets for the site** — the website/dashboard load hand-written CSS/JS from `public/assets/...`; Vite only compiles `resources/sass/app.scss` + `resources/js/app.js` for the Laravel scaffold.
