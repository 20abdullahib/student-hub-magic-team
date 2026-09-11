# 04 — Models (Eloquent)

All models live in `app/Models/` (namespace `App\Models`).

## Admin (`app/Models/Admin.php`)
- Extends `Illuminate\Foundation\Auth\User` (authenticatable). **Guard: `admin`**.
- Traits: `HasFactory`, `HasRoles` (Spatie).
- Fillable: `name`, `email`, `password`, `department_id`, `branch_id`, `role`.
- Hidden: `password`, `remember_token`.
- Relationships: `department()` → belongsTo Department · `branch()` → belongsTo Branch.
- Roles: assigned via Spatie (`assignRole`, `syncRoles` in AdminController); the `role` column is a denormalized copy of the role name.

## User (`app/Models/User.php`)
- Default Laravel web user. Guard: `web`. Traits: `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`.
- Fillable: `name`, `email`, `password`. Hidden: `password`, `remember_token`. Casts: `email_verified_at` datetime.
- No relationships. Not used by any active feature (registration exists via laravel/ui but the site doesn't depend on it).

## Department (`app/Models/Department.php`)
- Fillable: `name`. Timestamps on.
- Relationships: `subjects()` hasMany Subject · `branches()` hasMany Branch · `dropboxAccounts()` hasMany DropboxAccount · `files()` hasManyThrough File via DropboxAccount.

## Branch (`app/Models/Branch.php`)
- Fillable: `name`, `department_id`. Timestamps on.
- Relationships: `department()` belongsTo Department · `subjects()` belongsToMany Subject (pivot `branch_subject`).

## Generation (`app/Models/Generation.php`) — team members
- Fillable: `name`, `year_joined`, `branch_id`, `patch`, `image`, `publish`, `role`. Timestamps on.
- Relationships: `branch()` belongsTo Branch.
- Used by: Website HomeController (published members on home), AboutController (by year), dashboard Settings/TeamMemberController (CRUD).

## Subject (`app/Models/Subject.php`)
- Fillable: `name`, `description`, `department_id`, `code`.
- Relationships: `department()` belongsTo Department · `branches()` belongsToMany Branch (pivot `branch_subject`) · `files()` hasMany File.

## DropboxAccount (`app/Models/DropboxAccount.php`)
- Fillable: `client_id`, `client_secret`, `access_token`, `refresh_token`, `email`, `timestamp`, `token_expires_at`, `department_id`, `remaining_storage`.
- Casts: `token_expires_at` → datetime.
- Relationships: `department()` belongsTo Department · `files()` hasMany File.
- ⚠️ `token_expires_at` is not in the migration — see 03-database.md *Known Gaps*.

## File (`app/Models/File.php`)
- Metadata row for one file stored in a Dropbox account.
- Fillable: `name`, `path`, `link`, `file_id`, `size`, `rlkey`, `subject_id`, `dropbox_account_id`, `created_at`, `updated_at`.
- Relationships: `subject()` belongsTo Subject · `dropboxAccount()` belongsTo DropboxAccount.
- Consumed by: dashboard `DropboxController` (list/delete records), website `ResourcesController` (folder tree + preview/download links).

## Relationship Map

```
Department 1─* Branch 1─* Generation
Department 1─* Subject 1─* File
Branch *─* Subject            (branch_subject)
Department 1─* DropboxAccount 1─* File
Department 1─* Admin (also Branch 1─* Admin)
```
