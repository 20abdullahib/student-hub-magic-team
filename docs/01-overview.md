# 01 — Project Overview

## What is Student Hub?

Student Hub is a web application that helps university (science faculty) students find, preview and download academic resources (PDFs, videos, documents) organized per **Department → Branch → Subject → Files**. A student team ("Magic Team", members tracked as "Generations") manages the content through an admin dashboard.

**Main actors:**

| Actor | What they do |
|-------|-------------|
| **Visitor / Student** (public website) | Views home page, team/generation pages, searches & filters resources, opens subject folders, previews or downloads files (via Dropbox shared links) |
| **Admin** (dashboard, guard `admin`) | Logs in at `/dashboard/login`; manages admins & roles/permissions, team members, Dropbox accounts, uploads files, browses/deletes uploaded file records |
| **Scheduler** (cron) | Refreshes all Dropbox access tokens every 3 hours |

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP ≥ 8.1, Laravel 10 |
| Database | MySQL (Eloquent ORM, migrations, seeders) |
| Auth | Laravel guards (`web` + `admin`), `laravel/ui` scaffolding |
| Roles/Permissions | `spatie/laravel-permission` v6 |
| File storage | Dropbox API v2 (OAuth 2.0 refresh-token flow, multiple accounts), `guzzlehttp/guzzle` for HTTP |
| Uploads | `pion/laravel-chunk-upload` (chunked upload support) + direct browser→Dropbox uploads |
| API tokens | `laravel/sanctum` (on `User`, not actively used) |
| Frontend | Blade templates, plain CSS/JS in `public/assets`, Bootstrap 5, bootsnav |
| Build tool | Vite 4 + `laravel-vite-plugin` (compiles `resources/sass/app.scss`, `resources/js/app.js`) |
| Testing | PHPUnit 10 (`tests/Feature`, `tests/Unit` — currently only example tests) |
| Dev tooling | Laravel Pint (code style), Mockery, Faker, Laravel Sail (optional Docker) |

## Architecture (3 Layers)

```
┌─────────────────────────────────────────────────────────────┐
│  PUBLIC WEBSITE (no auth)                                   │
│  Home  ·  About team/generations  ·  Resources              │
│  Controllers: Website\HomeController, AboutController,      │
│               ResourcesController                           │
│  Views: resources/views/website/*                           │
└──────────────┬──────────────────────────────────────────────┘
               │  file links (file_id + rlkey) → dropbox.com
               ▼
┌─────────────────────────────────────────────────────────────┐
│  ADMIN DASHBOARD (auth:admin + session.timeout)             │
│  Overview · Admins · Permissions/Roles · Settings/Team      │
│  Dropbox accounts · File uploads/records                    │
│  Controllers: Dashboard\{Dashboard, Admin, Permission,      │
│               Settings, TeamMember, Dropbox}Controller      │
│  Views: resources/views/dashboard/*                         │
└──────────────┬──────────────────────────────────────────────┘
               │ OAuth refresh tokens → access tokens
               ▼
┌─────────────────────────────────────────────────────────────┐
│  DROPBOX LAYER                                              │
│  Service: App\Services\DropboxService                       │
│  • verifyCredentials()   • refreshAccessToken()             │
│  • ensureValidToken()    • getAccountFiles() + temp links   │
│  Scheduled: dropbox:refresh-tokens (every 3h)               │
│  Accounts: many Dropbox accounts (2 GB each) mapped 1:many  │
│  to departments; each account = storage bucket              │
└─────────────────────────────────────────────────────────────┘
```

## How a File Reaches a Student (happy path)

1. Admin uploads a file (browser uploads directly to a Dropbox account's app folder using a fresh access token fetched from `GET /dropbox/access-token`), then the browser calls `POST /dashboard/dropbox/files/store-details` to save metadata (name, path, size, `file_id`, `rlkey`, subject, dropbox account).
2. Public visitor opens `/resources` — `ResourcesController@index` lists subjects (paginated, with file counts).
3. Visitor opens a subject — `ResourcesController@show` builds a **nested folder tree** from each file's `path` (mirrors the Dropbox folder structure).
4. Visitor clicks preview/download — `resources/file/preview/{fileId}` / `resources/file/download/{fileId}` redirect to `https://www.dropbox.com/scl/fi/{file_id}?rlkey={rlkey}&dl=0|1`.

## Data Model at a Glance

```
Department 1─┬─* Branch 1──* Generation (team member)
             ├─* Subject 1─* File
             │      *  ⇄  *  (branch_subject pivot)
             └─* DropboxAccount 1─* File
Admins (separate auth table) belong to a Department + Branch, have Spatie roles.
```

See [03-database.md](03-database.md) and [04-models.md](04-models.md) for full details.
