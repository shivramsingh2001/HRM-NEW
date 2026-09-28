# File storage (uploads) — Google Cloud Storage

Every upload in the app (profile photos, employee documents, announcements, leave / loan /
request / regularization attachments, task documents + voice notes, project / asset
attachments, candidate resumes / documents, expense receipts) and hrm-superadmin's tenant
logos goes through **one** service:

| Piece | Where |
|---|---|
| Service | `app/Services/Storage/FileStorageService.php` (singleton; `file_storage()` helper) |
| Result DTO | `app/Services/Storage/StoredFile.php` |
| Error | `app/Exceptions/FileStorageException.php` (safe message + `httpStatus()` 422/500) |
| Helpers | `app/Helpers/file_storage.php` — `file_storage()`, `file_url($path, $module)` (composer `files` autoload) |
| Config | `config/file_storage.php` (disk, TTLs, module registry, legacy roots) |
| Disks | `config/filesystems.php` → `gcs` (Google Cloud Storage) and `uploads` (local `public/`) |
| Safety-net route | `GET /uploads/{path}` → `FileController@uploads` (auth) |
| Migration | `php artisan files:migrate-to-cloud` (`app/Console/Commands/MigrateFilesToCloud.php`) |
| Tests | `tests/Feature/Storage/FileStorageServiceTest.php` |

hrm-superadmin carries the same service/exception/helper/command classes (keep them identical)
and its own `config/file_storage.php` with only the `tenant_logo` module.

**Rule:** controllers never call `$file->move()`, `->store()`, `public_path('uploads/…')`,
`unlink()` or `asset($storedPath)` for uploaded files. Use the service.

## Configuration lives only in `.env`

```dotenv
FILE_STORAGE_DISK=gcs                    # gcs = Google Cloud Storage; uploads = local public/uploads (hrm), public (superadmin)
GOOGLE_CLOUD_PROJECT_ID=my-hrm-project
GOOGLE_CLOUD_STORAGE_BUCKET=my-hrm-files
GOOGLE_CLOUD_KEY_FILE=D:/secrets/hrm-gcs.json   # path to the service-account JSON key…
GOOGLE_CLOUD_KEY_JSON_BASE64=                    # …or the key JSON base64-encoded (hosts without files)
GOOGLE_CLOUD_STORAGE_PATH_PREFIX=                # optional folder inside the bucket, e.g. prod / staging
GOOGLE_CLOUD_STORAGE_API_URI=                    # optional custom domain / CDN in front of the bucket
FILE_STORAGE_SIGNED_URL_TTL=60                   # minutes — private files
FILE_STORAGE_PUBLIC_URL_TTL=10080                # minutes — avatars, announcement images, logos (GCS max 7 days)
```

- Leave **both** key variables empty when running on Google Cloud (Cloud Run / GCE / GKE): the
  SDK then uses the attached service account (Application Default Credentials).
- Change project / bucket / service account / key → edit `.env`, then `php artisan config:clear`
  (or `php artisan config:cache` in production). No code changes.
- Use the same `GOOGLE_CLOUD_*` values in `hrm (3)/.env` and `hrm-superadmin/.env` — both apps
  read the same bucket (logos written by the panel are shown on payslips/emails in the app).
- Until `FILE_STORAGE_DISK=gcs` is set, everything keeps working on local disk exactly as before.

## One-time Google Cloud Console setup

1. **Project** — pick/create a project; note its *Project ID* → `GOOGLE_CLOUD_PROJECT_ID`.
2. **APIs** — *APIs & Services → Enable APIs*: **Cloud Storage API**, and **IAM Service Account
   Credentials API** (only needed for signed URLs when running keyless on GCP).
3. **Bucket** — *Cloud Storage → Buckets → Create*:
   - Name → `GOOGLE_CLOUD_STORAGE_BUCKET` (globally unique, e.g. `acme-hrm-files`)
   - Location: Region `asia-south1` (Mumbai) — or the region the app runs in
   - Storage class: Standard
   - Access control: **Uniform** (bucket-level access ON)
   - **Enforce public access prevention: ON** — nothing is ever world-readable; the app hands out
     signed URLs
   - Protection: soft delete (default 7 days) recommended; object versioning optional
4. **CORS** (so browsers can load signed URLs from the web panel) — save as `cors.json`:
   ```json
   [{"origin": ["https://hrm.example.com"], "method": ["GET", "HEAD"],
     "responseHeader": ["Content-Type", "Content-Disposition"], "maxAgeSeconds": 3600}]
   ```
   then `gcloud storage buckets update gs://<bucket> --cors-file=cors.json`.
5. **Service account** — *IAM & Admin → Service Accounts → Create*: `hrm-storage`. Give it **no
   project-wide role**. Then on the bucket: *Bucket → Permissions → Grant access* →
   principal `hrm-storage@<project>.iam.gserviceaccount.com`, role **Storage Object Admin**
   (`roles/storage.objectAdmin` = read/write/delete objects in this bucket only).
   - Keyless on GCP only: also grant the service account **Service Account Token Creator** on
     itself (lets it sign URLs without a key file).
6. **Key** — *Service account → Keys → Add key → JSON*. Store it **outside** the project folder
   (e.g. `D:\secrets\hrm-gcs.json`, on Linux `/etc/hrm/gcs.json` with `chmod 600`, owned by the
   web-server user) → `GOOGLE_CLOUD_KEY_FILE`. Never commit it. To use the base64 variant instead:
   `base64 -w0 hrm-gcs.json` (PowerShell: `[Convert]::ToBase64String([IO.File]::ReadAllBytes('hrm-gcs.json'))`).
7. **Rotate a key** — create a new key, update `.env`, `php artisan config:clear`, verify, delete
   the old key in the console.

### Go-live checklist

1. Fill the `.env` values in both apps; `php artisan config:clear` in both.
2. `php artisan tinker --execute="dump(Storage::disk('gcs')->put('healthcheck.txt','ok'), file_url('healthcheck.txt'))"`
   → open the URL, then `Storage::disk('gcs')->delete('healthcheck.txt')`.
3. `php artisan files:migrate-to-cloud --dry-run` → review → `php artisan files:migrate-to-cloud`
   (in hrm-superadmin too, for `tenant-logos/`). Re-runnable; skips objects already present.
4. Set `FILE_STORAGE_DISK=gcs` (if not already) and `config:clear`. New uploads now go to the bucket.
5. After checking the app for a while: `php artisan files:migrate-to-cloud --delete-local` removes
   local copies (each only after its upload is verified by size). Until then local copies are
   served first, so the migration is never a cut-over risk.

## Using the service

```php
// upload (module decides folder, allowed types, max size)
$path = file_storage()->upload($request->file('file'), 'leave')->path;          // save $path in the DB column
$stored = file_storage()->upload($file, 'project_attachment', ['id' => $project->id]);
$stored->path; $stored->originalName; $stored->mimeType; $stored->size; $stored->extension;

// replace: new file first, old one deleted after the DB transaction commits
$model->file = file_storage()->replace($model->file, $request->file('file'), 'leave')->path;

// several files, all-or-nothing
$files = file_storage()->uploadMany($request->file('files'), 'task_attachment');

// raw bytes (e.g. base64 voice note from the browser)
$path = file_storage()->storeContents($binary, 'task_voice', 'wav')->path;

// delete (null-safe, never throws); inside a transaction prefer deleteAfterCommit()
file_storage()->delete($model->file, 'leave');
file_storage()->deleteAfterCommit($model->file, 'leave');

// links
file_url($model->file, 'leave');                        // Blade / API — signed URL on GCS, asset() locally, null-safe
file_storage()->mapUrls($rows, ['file_url' => 'leave']); // query results: replaces CONCAT('$baseUrl/', col) in SQL
file_storage()->stream($path, 'request', $downloadName, attachment: true); // permission-checked download routes
file_storage()->dataUri($company->logo, 'tenant_logo'); // dompdf (cannot fetch signed URLs)
```

- `{tenant}` in a folder defaults to the current tenant (web session / `current_tenant` / API user);
  pass `['tenant' => $id]` explicitly in API code and jobs.
- Errors: `FileStorageException` — 422 (bad type/size, safe message) or 500 (disk failure; the real
  cloud error is logged, never shown). Existing `catch (Exception $e)` blocks still catch it; add a
  `catch (FileStorageException $e)` first when the user should see the exact message.
- Stored file name is always `<uuid>.<ext>`; the extension comes from the file's real content
  (a PNG named `x.html` is stored as `.png`). The client name is kept only as display metadata.
- To add a module: add a key to `modules` in `config/file_storage.php`
  (`folder` with placeholders `{tenant} {user} {id} {year} {month}`, `ext`, `max` KB, optional
  `public` = long-lived URL, optional `local_disk`).

## Modules

| Key | Folder | Types | Max | Used by |
|---|---|---|---|---|
| `profile_photo` (public) | `uploads/users/{tenant}/profile` | jpg jpeg png webp | 2 MB | User wizard/update, API profile |
| `employee_document` | `uploads/users/{tenant}/{user}/documents` | pdf jpg jpeg png doc docx | 5 MB | Employee documents |
| `announcement_image` (public) / `announcement_file` | `uploads/announcement/{tenant}/image` · `/file` | images · pdf/doc/docx/txt/xls/xlsx/img | 2 / 5 MB | Announcements (web + API) |
| `leave` | `uploads/leave/{tenant}` | pdf, images, doc/docx | 10 MB | Leave (web + API) |
| `loan` | `uploads/loan/{tenant}` | pdf, images, doc/docx | 10 MB | API loan request |
| `request` | `uploads/requests/{tenant}` | jpg jpeg png pdf doc docx | 5 MB | Requests (web + API) |
| `regularization` | `uploads/regularizations/{tenant}` | jpg jpeg png pdf doc docx | 2 MB | Regularization (web + API) |
| `task_document` / `task_voice` / `task_attachment` | `uploads/task/{tenant}/document` · `/voice` · `/attachments` | office/pdf/images · audio | 5 / 20 / 10 MB | Tasks (web + API), MoM |
| `project_attachment` / `asset_attachment` | `uploads/projects|assets/{tenant}/{id}/attachments` | office/pdf/images/zip | 10 MB | Projects, Assets |
| `candidate_resume` / `candidate_document` | `uploads/candidate_resumes/{tenant}` · `uploads/candidate_documents/{tenant}/{id}` | pdf/doc(x) · +images | 5 MB | Recruitment, Onboarding |
| `expense` (local fallback = private `local` disk) | `expense/{tenant}/{year}` | jpg jpeg png pdf | 5 MB | `ExpenseAttachmentService` (signed routes unchanged) |
| `tenant_logo` (public) | `uploads/tenants/logos` | jpg jpeg png webp | 2 MB | hrm-superadmin; payslip PDF + emails in the app |

## How old paths keep working

- DB columns keep storing a relative path; nothing in the schema changed.
- `config('file_storage.legacy_roots')` lists where pre-cloud files live (`public/uploads/…`,
  `storage/app/public/attendance_files|tenant-logos/…`, `storage/app/private/expense/…`). Every
  read (`url`, `stream`, `exists`, `contents`, `delete`) checks there first with a cheap `is_file`,
  so a file is served locally until it is migrated, then from the bucket — same path either way.
- `files:migrate-to-cloud` copies files to the bucket **under the same key**.
- Links still built from a raw path (`asset($path)`, `'/' + path` in JS) hit
  `GET /uploads/{path}` once the local file is gone; for a logged-in user it 302-redirects to a
  signed URL. New code should use `file_url()` instead.
- `ExpenseAttachmentService` keeps its own legacy-path rules (`isLegacy()`,
  `expense:migrate-uploads`) and signed `expense.file` / `expense.attachment` routes.
