# 07 — Views & Frontend

## Blade View Layout (`resources/views/`)

### `website/` — public site (assets from `public/assets/Website`)
```
website/
├── layout/layout.blade.php          # Master layout (site frame)
├── includes/  head · header · footer · scripts
└── pages/
    ├── home.blade.php               # Home: hero + published team members + departments/branches
    ├── contact_us.blade.php
    ├── about/about.blade.php        # Team page (generation years + all members)
    ├── about/genration/generation.blade.php  # Members of one year (note the "genration" typo path)
    └── resource/
        ├── resources.blade.php      # Subject grid/list (also reused by dropbox.api.files)
        ├── includes/header-search.blade.php  # Search bar partial
        └── partials/nested-folders.blade.php # Folder tree + breadcrumbs for a subject's files
```

### `dashboard/` — admin panel (assets from `public/assets/Dashboard`)
```
dashboard/
├── layout/layout.blade.php
├── includes/  head · header · sidebar · mobile-navbar · alerts · footer · scripts
└── pages/
    ├── overview/overview.blade.php              # Dashboard home
    ├── admin/admins.blade.php                   # Admin list (search/filter table)
    ├── admin/AddNewAdmin.blade.php              # Create admin + role select
    ├── admin/AddNewPermission.blade.php         # Create permission/role form
    ├── dropbox/accounts.blade.php               # Dropbox accounts list (storage %)
    ├── dropbox/AddNewAccount.blade.php          # Connect a Dropbox account
    ├── dropbox/files.blade.php                  # Uploaded file records table
    ├── dropbox/UploadFiles.blade.php            # Upload page (chunked upload JS)
    ├── settings/settings.blade.php              # Settings: branches + team members
    └── settings/includes/AddNewMember.blade.php # Add team member form
```

### `auth/` — laravel/ui auth pages (assets from `public/assets/login-singup`)
`login`, `register`, `verify`, `passwords/confirm`, `passwords/email`, `passwords/reset`.

### `errors/` — custom error pages
`401, 402, 403, 404, 419, 429, 500, 503, minimal` (assets in `public/assets/errors`).

## Public Assets (`public/assets/` — plain files, **not** compiled by Vite)

### `Website/`
- `css/`: `bootstrap.min.css`, `frame.css`, `bootsnav.css`, `header_animate.css`, plus custom per-component folders `header/`, `body/` (`hero_custom.css`, `middel_custom.css`), `footer/` (`footer_custom.css`).
- `scripts/`: `bootstrap.min.js`, `bootnav.js` (nav), `custom-script.js`, `handel-search-requset.js` (search AJAX — name typo is real), `components/input-clear-handler.js`, `components/type.js`.
- `fonts/`, `images/`.

### `Dashboard/`
- `css/`: `style.css`, `custom-style-form.css`.
- `scripts/`: `script.js` (dashboard general), `UploadFiles.js` (chunk upload flow: fetch account → get access token → upload → store details), `DeleteFiles.js` (AJAX deletes), `settings.js` (settings/team member inline updates).
- `images/`.

### `login-singup/` (sic)
- `css/style.css`, `images/signin.svg`.

## Vite / Node (`vite.config.js`, `resources/`)

- Vite compiles only the scaffold entrypoints: `resources/sass/app.scss` + `resources/js/app.js` (Bootstrap + axios wiring), via `laravel-vite-plugin` with HMR refresh.
- The actual site and dashboard do **not** load Vite bundles — they link their CSS/JS files directly from `public/assets/...`.
- npm scripts: `npm run dev` (vite), `npm run build`. Dev deps: vite 4, laravel-vite-plugin, sass, bootstrap 5, @popperjs/core, axios.
- When editing dashboard/website styles or scripts, edit files under `public/assets/<Area>/` — Vite is irrelevant for them.
