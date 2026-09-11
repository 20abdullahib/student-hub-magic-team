# 📚 Student Hub — Documentation Index (AI Entry Point)

> **⚠️ RULE FOR AI:** Read this file **first** every time you work on this project. It tells you which doc file to open for each topic so you never need to re-scan the whole codebase.
>
> **⚠️ KEEP IT IN SYNC:** After every code change, update the relevant doc file AND the [Recent Changes](#-recent-changes-changelog) section below.

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

---

## 🕒 Recent Changes (Changelog)

> Add an entry here (newest first) after every code change, and update the matching topic doc.

### 2026-09-11 — Shared-link creation 400 fixed (scope + error surfacing)

**Files touched:** `public/assets/Dashboard/scripts/UploadFiles.js`.

**Diagnosed (server-side with the live token):** `sharing/create_shared_link_with_settings` returned 400 because **the Dropbox app (ID 8267315) lacks the `sharing.write` scope** — Dropbox rejects the endpoint for every path, with or without settings. This is app configuration, not a code bug.

1. **`createSharedLink()` now creates links with DEFAULT settings** (no `requested_visibility: 'public'` payload — explicit `public` can 400 on restricted account/app configs; defaults are public anyway).
2. **Real Dropbox reasons surfaced** — on non-409 failures the SDK's `error.error.error_summary` is thrown (`"Dropbox: Your app ... does not have the required scope 'sharing.write' ..."`), and the per-file status now shows it (`Failed ❌ <reason>`) instead of a bare 400.
3. **Uploads are retry-safe** — `filesUpload` and `filesUploadSessionFinish` commit use `mode: {'.tag': 'overwrite'}` (was `add`/`autorename: true`), so retrying a file that reached Dropbox but failed at the shared-link/store-details stage overwrites it instead of failing with a 409 conflict.

**⚠️ ADMIN ACTION REQUIRED (once, per Dropbox app):** enable the **`sharing.write`** scope in the [Dropbox App Console → Permissions tab](https://www.dropbox.com/developers/apps), then **re-link the account**: run the OAuth authorize flow again to get a NEW refresh token (existing refresh tokens keep the old scopes) and re-save the account in Dashboard → Dropbox accounts (setup now stores a working access token immediately). The file from the failed attempt is already in Dropbox; re-uploading it after re-linking will overwrite it and complete the flow.

### 2026-09-11 — Upload/token fixes + standardized upload folder structure

**Files touched:** `app/Services/DropboxService.php`, `app/Http/Controllers/Dashboard/DropboxController.php`, `app/Http/Controllers/Website/ResourcesController.php`, `app/Console/Commands/RefreshDropboxTokens.php`, `public/assets/Dashboard/scripts/UploadFiles.js`, `resources/views/dashboard/includes/footer.blade.php`, `database/migrations/2026_09_11_000000_fix_dropbox_accounts_token_columns.php` (new).

1. **Footer crash fixed** — `dashboard/includes/footer.blade.php` referenced a nonexistent `#copyright2` element (`appendChild on null` on every dashboard page). The year `<span>` now carries `id="copyright2"`; the throwing inline script was removed.
2. **`access_token` was never saved — ROOT CAUSE: schema.** Dropbox short-lived tokens (`sl.u.…`, ~1500–2000 chars) did not fit `access_token VARCHAR(255)`; every save failed with MySQL 1406 *“Data too long for column 'access_token'”* and the column stayed NULL. New migration `2026_09_11_000000_fix_dropbox_accounts_token_columns.php` changes `access_token` → `TEXT NULL` and creates the previously missing `token_expires_at` (guarded with `hasColumn`; raw `ALTER TABLE` used because `doctrine/dbal` is not installed).
3. **Setup now persists tokens** — `DropboxService::verifyCredentials()` changed from `: bool` to `: ?array` returning `['access_token' => …, 'expires_in' => …]`; `DropboxController@setupAccount()` merges them into `updateOrCreate`, so `access_token` + `token_expires_at` are saved the moment an account is registered (never NULL after successful setup).
4. **Live token validation** — new `DropboxService::validateToken()` (calls `users/get_current_account` with an explicit `'{}'` JSON body — Dropbox RPC rejects `post($url, [])` because Laravel sends a JSON *array*) and `getValidToken()` (validate → force refresh → re-validate). `DropboxController@getAccessToken()` now returns HTTP **502 JSON** naming the broken account instead of a dead token.
5. **Honest upload errors** — `UploadFiles.js`: `fetchWithRetry()` fails fast on 4xx (except 429) with the parsed server error; `selectAccountWithSpace()` reports the real per-account failure reasons and only says “Insufficient space” when an account was genuinely checked but lacks free space.
6. **SSL quirk extended** — `Http::withoutVerifying()` (already used by `verifyCredentials`) now also applies to `refreshAccessToken()` and `validateToken()` so a broken local CA bundle can't silently kill token refreshes. See 09 quirk #6.
7. **Standardized upload structure** — uploads now go to **`/{Department}/{Subject}/[subfolders]/{file}`** via new `buildDropboxPath()` + `sanitizePathSegment()` in `UploadFiles.js` (strips `/:?*"<>|`, collapses whitespace; Arabic names safe). `getAccountForUpload()` returns `email` + `department_name` (the old `name` key never existed on the table).
8. **Public tree stays clean** — `ResourcesController@buildTree()` accepts `$stripPrefixes` (department/subject names); both legacy (`/Subject/…`) and new (`/Department/Subject/…`) paths render the identical per-subject folder tree. No data migration needed.
9. **Scheduler fix** — `RefreshDropboxTokens` used `new DropboxController()` (ArgumentCountError, constructor needs DI); now uses `app(DropboxController::class)`.

**Verified live:** `refreshAccessToken()` → true, `token_len=1459`, `token_expires_at` saved, `users/get_current_account` / `users/get_space_usage` / `2/check/user` all 200, `dropbox:refresh-tokens` runs clean.
