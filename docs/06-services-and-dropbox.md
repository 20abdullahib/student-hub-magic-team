# 06 — Dropbox Integration & Services

## `App\Services\DropboxService` (`app/Services/DropboxService.php`)

The single integration point with the Dropbox API (HTTP via Laravel `Http` facade / Guzzle). Every method operates on a `DropboxAccount` model row.

| Method | Visibility | What it does |
|--------|-----------|--------------|
| `verifyCredentials(array $credentials): ?array` | public | Exchanges a refresh token for a fresh access token (`https://api.dropbox.com/oauth2/token`, basic auth). Returns `['access_token' => string, 'expires_in' => int]` or `null` on failure — the payload is persisted by `setupAccount()` so `access_token` is never NULL after setup. ⚠️ Uses `Http::withoutVerifying()` — SSL verification disabled (see 09 quirk #6). |
| `refreshAccessToken(DropboxAccount $account): bool` | public | Requests a new access token with the stored refresh token; saves `access_token` + `token_expires_at = now() + expires_in` on the account row. Returns `true`/`false` (false = e.g. `invalid_grant` → account must be re-linked); logs failures. Also uses `withoutVerifying()`. |
| `ensureValidToken(DropboxAccount $account): void` | public | Calls `needsTokenRefresh()`; if needed, refreshes and `refresh()`es the model. Call before any API use. |
| `validateToken(DropboxAccount $account): bool` | public | Live-checks the stored token via `users/get_current_account` (with explicit `'{}'` JSON body — Dropbox RPC rejects `post($url, [])`, which Laravel sends as a JSON array). Also uses `withoutVerifying()`. |
| `getValidToken(DropboxAccount $account): bool` | public | Validate → force refresh if dead → re-validate. Used by `DropboxController@getAccessToken()`; `false` ⇒ the controller returns a 502 JSON naming the account. |
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
- `showForm()` / `setupAccount()` — validate (email, client_id, client_secret, refresh_token, department_id), then `verifyCredentials()`; on success `updateOrCreate` by email **including `access_token` + `token_expires_at` from the verification response** (tokens are stored immediately at setup, never NULL).
- `updateDropbox()` — AJAX: updates `remaining_storage` for one account (JSON response).
- `deleteAccount($id)` — deletes account row (⚠️ `files` FK is cascade → file records disappear too).

**File records (metadata):**
- `showUploadForm()` — upload page with departments + subjects.
- `storeFileDetails()` — validates + creates `File` metadata row (name, path, size, subject_id, dropbox_account_id, link, file_id, rlkey). The actual bytes are uploaded by the browser directly to Dropbox; this only persists metadata.
- `listFiles()` — paginated file records (25/page) with `subject` and `dropboxAccount.department` eager-loaded; filters by file name / subject name / department name.
- `deleteFiles(File $file)` — AJAX delete of the metadata row (JSON `{success: true}`). Does **not** delete from Dropbox.
- `getAccountForUpload($subject_id)` — JSON list of Dropbox accounts belonging to the subject's department (refreshes tokens as a side effect).

**API endpoints (public routes, used by upload JS):**
- `getAccessToken($account_id)` — validates the token live (`getValidToken()`); on failure returns HTTP **502 JSON** `{error: "Dropbox account {email} …"}` naming the broken account instead of a dead token.
- `refreshAllTokens()` — refreshes every account; JSON confirmation.
- `showFiles($departmentId)` — lists files across all Dropbox accounts of a department (via `getAccountFiles`) and renders the **website** resources view.

## Upload Flow (browser-driven)

```
Admin opens /dashboard/dropbox/upload
   └─ UploadFiles.js:
        1. POST /dashboard/dropbox/files/accounts   → accounts of the subject's department
           (JSON: id, email, department_id, department_name)
        2. GET  /dropbox/access-token?account_id=X  → validated short-lived access token (502 + reason if dead)
        3. Space pre-check per account (usersGetSpaceUsage), then upload
        4. POST /dashboard/dropbox/files/store-details → save File metadata (incl. shared-link file_id + rlkey)
```

### Standardized Dropbox folder structure

Uploads are written to **`/{Department}/{Subject}/[subfolders]/{file}`** (e.g. `/Physics/Calculus 1/Lectures/ch2.pdf`):
- Built in `UploadFiles.js` by `buildDropboxPath()` + `sanitizePathSegment()` (strips `/:?*"<>|`, collapses whitespace — Arabic names are safe).
- **Legacy files** uploaded before this change have `/Subject/...` paths; both schemes are supported.
- `ResourcesController@buildTree()` strips leading segments equal to the department/subject names, so the public folder tree looks the same for both.
- `DeleteFiles.js` empty-folder cleanup works on stored paths — unaffected.

### Shared links & retry semantics (UploadFiles.js)

- Shared links are created with **default settings** (`sharingCreateSharedLinkWithSettings({ path })` — no explicit `requested_visibility`), falling back to `sharingListSharedLinks` on 409 (link already exists). Failures throw the exact `error_summary`, shown in the per-file status.
- ⚠️ The Dropbox **app must have the `sharing.write` scope** (App Console → Permissions) — and since scopes are baked into tokens at authorize time, the account must be **re-linked** (fresh OAuth flow → new refresh token → re-save in the dashboard) after enabling a new scope.
- Uploads use `mode: {'.tag': 'overwrite'}` (both direct and session-commit), so retrying a file that reached Dropbox but failed later overwrites it instead of raising a 409 conflict.

Chunked upload support comes from the `pion/laravel-chunk-upload` composer package (installed for upload handling); `FileUploadRequest` form-request exists but `storeFileDetails()` validates inline instead.

## Download / Preview Flow (public)

```
Student clicks file on /resources/{subject}
   └─ GET /resources/file/preview/{file_id}  → 302 to dropbox.com/scl/fi/{file_id}?rlkey={rlkey}&e=1&dl=0
      GET /resources/file/download/{file_id} → 302 to ...&dl=1
```
No proxying — the visitor's browser talks to Dropbox directly via shared links.
