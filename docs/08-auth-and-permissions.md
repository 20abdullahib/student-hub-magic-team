# 08 — Authentication & Permissions

## Dual Auth Guards (`config/auth.php`)

| Guard | Driver | Provider / Model | Used for |
|-------|--------|------------------|----------|
| `web` (default) | session | `users` table → `App\Models\User` | laravel/ui auth pages (login/register/reset) — not used by the public site |
| `admin` | session | `admins` table → `App\Models\Admin` | **Everything that matters**: dashboard routes use `auth:admin` |

- Password reset config exists only for `users` (`password_reset_tokens`, expire 60 min).
- Default guard: `web`.

## Dashboard Login

- `GET/POST /dashboard/login` → `Auth\LoginController@showLoginForm` / `login` (dashboard-specific Blade view).
- `POST /dashboard/logout` (requires `auth:admin`).
- Seeded default admin: `admin@admin.com` / `#bom123456`, role `super admin` (see `DatabaseSeeder`).

## Middleware

Aliases registered in `app/Http/Kernel.php`:

| Alias | Class | Behavior |
|-------|-------|----------|
| `admin` | `App\Http\Middleware\AdminMiddleware` | Checks `Auth::check() && Auth::admin()`; otherwise redirect to `dashboard.login.form` with error. ⚠️ `Auth::admin()` is not a real Auth method — middleware exists but is effectively broken/unused; routes rely on `auth:admin` instead. |
| `session.timeout` | `App\Http\Middleware\SessionTimeout` | Tracks `lastActivityTime` in session; logs out the `admin` guard after **60 min** (`60*60` s) of inactivity, invalidates session, regenerates CSRF token, redirects to dashboard login with "session expired" error. |
| `auth`, `guest`, etc. | Standard Laravel (`Authenticate`, `RedirectIfAuthenticated`, …) | |

Applied pattern on all dashboard routes: `->middleware(['auth:admin', 'session.timeout'])`.

## Roles & Permissions (Spatie `laravel-permission` v6)

- Config: `config/permission.php`. Tables created by `2025_02_08_015335_create_permission_tables.php`.
- **Guard for all roles/permissions: `admin`.**

### Permissions (seeded in `RolesAndPermissionsSeeder`)
`upload files`, `delete files`, `add admins`, `delete admins`, `edit admins`, `add roles`, `delete roles`

### Roles
| Role | Permissions |
|------|-------------|
| `super admin` | all of the above |
| `admin` | upload files, delete files, add admins |
| `editor` | upload files, delete files |

### Runtime role/permission management (dashboard)
- `PermissionController@store()` — creates a new permission (unique name).
- `PermissionController@storeRole()` (`POST /dashboard/permission/role`) — creates a role or, if it exists, **adds** permissions to it (`givePermissionTo`, never removes).
- `AdminController@store()` — assigns role to a new admin (`assignRole`).
- `AdminController@updateRole()` (`PATCH /dashboard/admin/{admin}/update-role`) — AJAX role change (`syncRoles`), returns `{role_label, message}`.
- `AdminController@destroy()` — `removeRole()` then delete the admin.
- `Admin` model uses `HasRoles` trait with `$guard_name = 'admin'` — Spatie resolves roles/permissions against the `admin` guard.

## Session Configuration
- `.env`: `SESSION_DRIVER=file`, `SESSION_LIFETIME=120` (Laravel native lifetime; the stricter 60-min inactivity cutoff comes from the custom `SessionTimeout` middleware).
