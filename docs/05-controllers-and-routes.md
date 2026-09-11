# 05 — Controllers & Routes

All HTTP routes are in `routes/web.php`. `routes/api.php`, `channels.php`, `console.php` are default/empty scaffolds.

## Public Website Routes (no auth)

| Method | URI | Action | Name |
|--------|-----|--------|------|
| GET | `/` | `Website\HomeController@index` | `home.index` |
| GET | `/about-teem` | `Website\AboutController@index` | `about.index` |
| GET | `/about-teem/generation/{year}` | `Website\AboutController@showGeneration` | `about.showGeneration` |
| GET | `/resources` | `Website\ResourcesController@index` | `resources.index` |
| GET | `/resources/search` | `Website\ResourcesController@search` | `resources.search` |
| GET | `/resources/suggestions` | `Website\ResourcesController@suggestions` | `resources.suggestions` |
| GET | `/resources/filter` | `Website\ResourcesController@filterData` | `resources.filter` |
| GET | `/resources/file/download/{fileId}` | `Website\ResourcesController@download` | `file.download` |
| GET | `/resources/file/preview/{fileId}` | `Website\ResourcesController@preview` | `file.preview` |
| GET | `/resources/{id}` | `Website\ResourcesController@show` | `resources.subjects.show` |

## Dashboard — Dropbox (middleware: `auth:admin`, `session.timeout`)

Prefix `/dashboard/dropbox`:

| Method | URI | Action | Name |
|--------|-----|--------|------|
| GET | `/account` | `DropboxController@showForm` | `dropbox.account.form` |
| GET | `/upload` | `DropboxController@showUploadForm` | `dropbox.upload.form` |
| GET | `/accounts` | `DropboxController@listAccounts` | `dropbox.account.index` |
| POST | `/account/setup` | `DropboxController@setupAccount` | `dropbox.account.setup` |
| POST | `/account/update` | `DropboxController@updateDropbox` | `dropbox.account.update` |
| DELETE | `/account/{id}` | `DropboxController@deleteAccount` | `dropbox.account.delete` |
| POST | `/files/store-details` | `DropboxController@storeFileDetails` | `dropbox.files.store` |
| GET | `/files` | `DropboxController@listFiles` | `dropbox.files.index` |
| DELETE | `/files/{file}` | `DropboxController@deleteFiles` | `dropbox.files.delete` |
| GET | `/files/accounts` | `DropboxController@getAccountForUpload` | `dropbox.files.accounts` |

## Dropbox API Endpoints (public, no auth — used by the upload JS)

Prefix `/dropbox`:

| Method | URI | Action | Name |
|--------|-----|--------|------|
| GET | `/access-token` | `DropboxController@getAccessToken` | `dropbox.api.token` |
| POST | `/refresh-tokens` | `DropboxController@refreshAllTokens` | `dropbox.api.refresh` |
| GET | `/files/{departmentId}` | `DropboxController@showFiles` | `dropbox.api.files` |

## Dashboard — Settings / Team Members (middleware: `auth:admin`, `session.timeout`)

| Method | URI | Action | Name |
|--------|-----|--------|------|
| * | `/settings/team-members` (resource) | `TeamMemberController` (index, create, store, show, edit, update, destroy) | `team-members.*` |
| * | `/settings` (resource) | `SettingsController` (index is the only implemented action) | `settings.*` |

## Dashboard — Admins / Permissions / Overview (middleware: `auth:admin`, `session.timeout`)

| Method | URI | Action | Name |
|--------|-----|--------|------|
| * | `/dashboard/admin` (resource) | `AdminController` | `admin.*` |
| * | `/dashboard/permission` (resource) | `PermissionController` | `permission.*` |
| POST | `/dashboard/permission/role` | `PermissionController@storeRole` | `permission.store.role` |
| PATCH | `/dashboard/admin/{admin}/update-role` | `AdminController@updateRole` | `admin.updateRole` |
| GET | `/admin/search-suggestions` | `AdminController@searchSuggestions` | `admin.search-suggestions` |
| * | `/dashboard` (resource) | `DashboardController` (index implemented) | `dashboard.*` |

## Auth Routes

`Auth::routes()` — standard laravel/ui auth (login, register, logout, password reset, email verification) for the `web` guard. Plus:

| Method | URI | Action | Name |
|--------|-----|--------|------|
| GET | `/dashboard/login` | `Auth\LoginController@showLoginForm` | `dashboard.login.form` |
| POST | `/dashboard/login` | `Auth\LoginController@login` | `dashboard.login` |
| POST | `/dashboard/logout` | `Auth\LoginController@logout` (auth:admin) | `dashboard.logout` |

## Controller Responsibilities

### Website (public)
- **`Website\HomeController`** — `index()`: home page; fetches published `generations` (first 10) + all departments/branches (raw DB queries).
- **`Website\AboutController`** — `index()`: distinct `year_joined` list + all generations + branches. `showGeneration($year)`: members of one year. `getSuggestions()` exists but is dead code (`dd()` inside, no route).
- **`Website\ResourcesController`** — the resource browser:
  - `index()` — paginated subjects (30/page) with files count; only subjects that have files.
  - `show($id)` — builds a **nested folder tree** from `files.path` (mirrors Dropbox folders); supports `?folder=` navigation with breadcrumbs.
  - `search($query)` / `suggestions($query)` — search by subject `name` or `code` (suggestions = JSON autocomplete, limit 10).
  - `filterData($query, department, branch)` — combined filtering (department OR branch logic to avoid over-restrictive AND).
  - `download($fileId)` / `preview($fileId)` — redirect to `https://www.dropbox.com/scl/fi/{file_id}?rlkey={rlkey}&dl=1|0`; 404 if no `rlkey`.
  - Private helpers: `buildTree()`, `getCurrentNode()`, `buildBreadcrumbs()`, `getSubjectsQuery()`, `handleResponse()` (JSON for AJAX requests), `getDepartmentAndBranchData()`.

### Dashboard (admin)
- **`DashboardController`** — `index()`: overview page only; all other resource methods empty.
- **`AdminController`** — `index()`: paginated admins (10/page) with search (name/email/department/branch) + branch/department filters; `create()`/`store()`: create admin (validates, hashes password, `assignRole`); `destroy()`: removes role then deletes; `updateRole()`: AJAX role change (`syncRoles`), returns JSON; `searchSuggestions()`: JSON autocomplete for admins/departments/branches.
- **`PermissionController`** — `create()`: permission form view; `store()`: create permission; `storeRole()`: create role or add permissions to existing role (`givePermissionTo` without removing existing ones).
- **`SettingsController`** — `index()`: settings page (branches + team members). Other methods empty.
- **`TeamMemberController`** — `store()`: create Generation member; `update(Generation $teamMember)`: AJAX single-field update (whitelisted fields incl. `publish`); `destroy()`: delete member.
- **`DropboxController`** — see 06-services-and-dropbox.md.

### Auth
- `Auth\LoginController` (laravel/ui) also serves the **dashboard login** (`/dashboard/login`) for the `admin` guard. `HomeController` (root namespace) is legacy scaffold with `view('home')` and is not routed.

