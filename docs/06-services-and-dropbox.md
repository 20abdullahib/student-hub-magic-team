# 06 — Dropbox Integration & Services

## `App\Services\DropboxService` (`app/Services/DropboxService.php`)

The single integration point with the Dropbox API (HTTP via Laravel `Http` facade / Guzzle). Every method operates on a `DropboxAccount` model row.

| Method | Visibility | What it does |
|--------|-----------|--------------|
| `verifyCredentials(array $credentials): bool` | public | Tries a refresh-token grant against `https://api.dropbox.com/oauth2/token` with basic auth (`client_id`/`client_secret`). Used when setting up an account in the dashboard. ⚠️ Uses `Http::withoutVerifying()` — SSL verification disabled. |
| `refreshAccessToken(DropboxAccount $account): void` | public | Requests a new access token with the stored refresh token; saves `access_token` + `token_expires_at = now() + expires_in` on the account row. Logs failures. |
| `ensureValidToken(DropboxAccount $account): void` | public | Calls `needsTokenRefresh()`; if needed, refreshes and `refresh()`es the model. Call before any API use. |
| `needsTokenRefresh(DropboxAccount): bool` | private | 1) Checks `token_expires_at` within 2 minutes from now → refresh. 2) Fallback: POSTs to `https://api.dropboxapi.com/2/check/user` with `{"query":"ping"}`; failure ⇒ refresh. |
| `getAccountFiles(DropboxAccount): array` | public | `files/list_folder` on root; returns only file entries (folders skipped) as `[['name', 'path', 'link'], …]` where `link` = temporary link. |
| `formatFiles(array, account): array` | private | Filters `.tag === 'file'`, fetches a temporary link per file. |
| `getTemporaryLink(account, path): string` | private | `files/get_temporary_link` → short-lived download URL (empty string on failure). |

**Token lifecycle:** access tokens are short-lived; `token_expires_at` tracks expiry. The scheduled command refreshes all accounts every 3 hours, and `ensureValidToken()` acts as a lazy on-demand refresh before each API call.

## Scheduled Command: `dropbox:refresh-tokens`

- Class: `app/Console/Commands/RefreshDropboxTokens.php`
- Instantiates `DropboxController` (no DI) and calls `refreshAllTokens()` (which refreshes every `DropboxAccount`).
- Scheduled in `app/Console/Kernel.php`: `->everyThreeHours()`. Requires a running scheduler (cron `php artisan schedule:run`).

## `Dashboard\DropboxController` (dashboard + API)

Constructor-injects `DropboxService`.

**Account management (dashboard views):**
- `listAccounts()` — paginated accounts (25/page) with email/department filters; computes `remaining_percentage` against a hard-coded 2 GB total; passes departments for filter dropdown.
- `showForm()` / `setupAccount()` — validate (email, client_id, client_secret, refresh_token, department_id), then `verifyCredentials()`; on success `updateOrCreate` by email.
- `updateDropbox()` — AJAX: updates `remaining_storage` for one account (JSON response).
- `deleteAccount($id)` — deletes account row (⚠️ `files` FK is cascade → file records disappear too).

**File records (metadata):**
- `showUploadForm()` — upload page with departments + subjects.
- `storeFileDetails()` — validates + creates `File` metadata row (name, path, size, subject_id, dropbox_account_id, link, file_id, rlkey). The actual bytes are uploaded by the browser directly to Dropbox; this only persists metadata.
- `listFiles()` — paginated file records (25/page) with `subject` and `dropboxAccount.department` eager-loaded; filters by file name / subject name / department name.
- `deleteFiles(File $file)` — AJAX delete of the metadata row (JSON `{success: true}`). Does **not** delete from Dropbox.
- `getAccountForUpload($subject_id)` — JSON list of Dropbox accounts belonging to the subject's department (refreshes tokens as a side effect).

**API endpoints (public routes, used by upload JS):**
- `getAccessToken($account_id)` — JSON `{access_token}` after `ensureValidToken()`.
- `refreshAllTokens()` — refreshes every account; JSON confirmation.
- `showFiles($departmentId)` — lists files across all Dropbox accounts of a department (via `getAccountFiles`) and renders the **website** resources view.

## Upload Flow (browser-driven)

```
Admin opens /dashboard/dropbox/upload
   └─ UploadFiles.js:
        1. POST /dashboard/dropbox/files/accounts   → pick account for subject's department
        2. GET  /dropbox/access-token?account_id=X  → short-lived access token
        3. Upload file in chunks/direct to Dropbox API from the browser
        4. POST /dashboard/dropbox/files/store-details → save File metadata (incl. shared-link file_id + rlkey)
```

Chunked upload support comes from the `pion/laravel-chunk-upload` composer package (installed for upload handling); `FileUploadRequest` form-request exists but `storeFileDetails()` validates inline instead.

## Download / Preview Flow (public)

```
Student clicks file on /resources/{subject}
   └─ GET /resources/file/preview/{file_id}  → 302 to dropbox.com/scl/fi/{file_id}?rlkey={rlkey}&e=1&dl=0
      GET /resources/file/download/{file_id} → 302 to ...&dl=1
```
No proxying — the visitor's browser talks to Dropbox directly via shared links.
