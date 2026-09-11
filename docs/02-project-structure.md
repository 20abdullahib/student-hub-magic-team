# 02 — Project Structure (Annotated Tree)

Legend: `📁` directory · `📄` file. Descriptions are one line each. `vendor/` and `node_modules/` are omitted (third-party packages).

```
student-hub/
├── 📁 app/                          # Application code (PSR-4: App\)
│   ├── 📁 Console/
│   │   ├── 📄 Kernel.php            # Schedules dropbox:refresh-tokens every 3 hours
│   │   └── 📁 Commands/
│   │       └── 📄 RefreshDropboxTokens.php  # Artisan `dropbox:refresh-tokens` — refreshes all Dropbox tokens
│   ├── 📁 Exceptions/
│   │   └── 📄 Handler.php           # Exception handling (default Laravel)
│   ├── 📁 Http/
│   │   ├── 📄 Kernel.php            # Global middleware + web/api groups; aliases: admin, session.timeout
│   │   ├── 📁 Controllers/
│   │   │   ├── 📄 Controller.php    # Base controller
│   │   │   ├── 📄 HomeController.php# Legacy scaffold home (auth middleware); route '/' uses Website\HomeController instead
│   │   │   ├── 📁 Auth/             # laravel/ui auth controllers
│   │   │   │   ├── LoginController.php   # Handles web + admin (dashboard) login
│   │   │   │   ├── RegisterController.php
│   │   │   │   ├── ForgotPasswordController.php
│   │   │   │   ├── ResetPasswordController.php
│   │   │   │   ├── ConfirmPasswordController.php
│   │   │   │   └── VerificationController.php
│   │   │   ├── 📁 Dashboard/        # Admin-only controllers (routes: auth:admin + session.timeout)
│   │   │   │   ├── DashboardController.php  # Overview page (index only; other resource methods empty)
│   │   │   │   ├── AdminController.php      # Admin CRUD (list w/ search+filters, create, delete), searchSuggestions(), updateRole()
│   │   │   │   ├── PermissionController.php # create()/store() permissions, storeRole() create-or-update Spatie roles
│   │   │   │   ├── SettingsController.php   # Settings page: branches list + team members
│   │   │   │   ├── TeamMemberController.php # CRUD Generation rows (team members) for settings page
│   │   │   │   └── DropboxController.php    # Dropbox accounts CRUD, upload forms, file records CRUD, token API endpoints
│   │   │   └── 📁 Website/          # Public website controllers (no auth)
│   │   │       ├── HomeController.php       # Home page: published generations + departments/branches
│   │   │       ├── AboutController.php      # About page: generation years, per-year generation listing
│   │   │       └── ResourcesController.php  # Resources: index, show (folder tree), search, suggestions, filter, preview, download
│   │   ├── 📁 Middleware/
│   │   │   ├── SessionTimeout.php           # Logs out admin guard after 60 min inactivity (alias: session.timeout)
│   │   │   ├── AdminMiddleware.php          # Admin guard check → redirect to dashboard login (alias: admin)
│   │   │   └── ... (Authenticate, EncryptCookies, TrimStrings, TrustProxies, etc. — standard Laravel)
│   │   └── 📁 Requests/
│   │       ├── DropboxAccountRequest.php    # Validation rules for Dropbox account form
│   │       └── FileUploadRequest.php        # File metadata rules (currently unused — DropboxController validates inline)
│   ├── 📁 Models/
│   │   ├── Admin.php            # Admin authenticatable (guard 'admin'), HasRoles, belongsTo Department & Branch
│   │   ├── User.php             # Default web user (Sanctum tokens, not used by the site)
│   │   ├── Department.php       # hasMany Subjects, Branches, DropboxAccounts; hasManyThrough Files
│   │   ├── Branch.php           # belongsTo Department; belongsToMany Subjects (branch_subject)
│   │   ├── Generation.php       # Team member: name, year_joined, patch, image, publish, role; belongsTo Branch
│   │   ├── Subject.php          # belongsTo Department; belongsToMany Branches; hasMany Files
│   │   ├── DropboxAccount.php   # Dropbox OAuth creds per department; hasMany Files
│   │   └── File.php             # File metadata record; belongsTo Subject & DropboxAccount
│   ├── 📁 Providers/            # AppServiceProvider, AuthServiceProvider, EventServiceProvider, RouteServiceProvider, BroadcastServiceProvider
│   └── 📁 Services/
│       └── 📄 DropboxService.php# All Dropbox API calls (verify creds, refresh/check tokens, list files, temp links)
├── 📁 bootstrap/
│   └── 📁 cache/                # Framework cache (routes, services)
├── 📁 config/                   # Framework config. Notable: auth.php (web + admin guards), permission.php (Spatie)
├── 📁 database/
│   ├── 📁 factories/
│   │   ├── UserFactory.php
│   │   └── SubjectFactory.php
│   ├── 📁 migrations/           # 15 migrations (see 03-database.md for the full list)
│   └── 📁 seeders/
│       ├── DatabaseSeeder.php           # Seeds departments (6), branches (18), generations 2020-2030, default super-admin, calls other seeders
│       ├── RolesAndPermissionsSeeder.php# Spatie permissions + roles (super admin / admin / editor) with guard 'admin'
│       ├── SubjectSeeder.php            # ~200 real subjects (Arabic names + codes) mapped to departments
│       └── BranchSubjectSeeder.php      # Attaches branches to subjects in branch_subject pivot
├── 📁 public/                   # Web root
│   ├── 📄 .htaccess / index.php / favicon.ico / robots.txt
│   └── 📁 assets/               # Hand-written frontend assets (NOT compiled by Vite)
│       ├── 📁 Dashboard/        # Admin dashboard assets: css/ (style.css, custom-style-form.css), images/, scripts/ (script.js, UploadFiles.js, DeleteFiles.js, settings.js)
│       ├── 📁 Website/          # Public site assets: css/ (bootstrap, frame, bootsnav + header/body/footer custom), fonts/, images/, scripts/ (bootnav, custom-script, handel-search-requset.js, components/)
│       ├── 📁 login-singup/     # Auth pages assets (style.css, signin.svg) — "singup" typo is real
│       └── 📁 errors/           # Error page assets
├── 📁 resources/
│   ├── 📁 js/ (app.js, bootstrap.js)        # Vite JS scaffold (axios/bootstrap setup)
│   ├── 📁 sass/ (app.scss, _variables.scss) # Vite SCSS scaffold
│   ├── 📁 css/ (app.css)                    # Scaffold CSS
│   └── 📁 views/                # Blade templates (see 07-views-and-frontend.md)
│       ├── auth/                # Login/register/verify/passwords
│       ├── dashboard/           # layout + includes (sidebar, header…) + pages (admin, dropbox, overview, settings)
│       ├── website/             # layout + includes (header, footer…) + pages (home, about, resource, contact_us)
│       └── errors/              # 401 402 403 404 419 429 500 503 minimal
├── 📁 routes/
│   ├── web.php                  # All HTTP routes (public, dashboard, dropbox API, auth)
│   ├── api.php                  # API routes (default/empty)
│   ├── console.php              # Artisan closures
│   └── channels.php             # Broadcast channels
├── 📁 storage/                  # Logs (storage/logs), caches, sessions, compiled views
├── 📁 tests/
│   ├── Feature/ExampleTest.php  # Default example tests only
│   ├── Unit/ExampleTest.php
│   └── TestCase.php / CreatesApplication.php
├── 📄 .env                       # Environment (never commit) — DB, mail, session, etc.
├── 📄 .env.example               # Template env file
├── 📄 artisan                    # Artisan CLI entry
├── 📄 composer.json / composer.lock
├── 📄 package.json / vite.config.js
├── 📄 phpunit.xml                # Test config
├── 📄 README.md                  # Project readme (install/setup instructions)
├── 📁 docs/                     # ← This documentation
└── 📄 .editorconfig / .gitattributes / .gitignore
```

## Conventions observed

- **Namespaces mirror folders:** `App\Http\Controllers\Dashboard`, `App\Http\Controllers\Website`, `App\Models`, `App\Services`.
- **Views mirror controllers:** `dashboard.pages.admin.admins` → `resources/views/dashboard/pages/admin/admins.blade.php`; `website.pages.home` → `resources/views/website/pages/home.blade.php`.
- **Assets per area:** each UI area (Website, Dashboard, login-singup, errors) has its own `css/images/scripts` folders under `public/assets/<Area>`.
- **Typo paths that are real and used in code (do not "fix" blindly):** `public/assets/login-singup` (sic) and `resources/views/website/pages/about/genration` (sic).

