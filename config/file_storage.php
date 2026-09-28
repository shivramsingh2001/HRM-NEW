<?php

/*
|--------------------------------------------------------------------------
| File storage — the single source of truth for every upload in the app
|--------------------------------------------------------------------------
|
| Every controller/service stores, deletes and links files through
| App\Services\Storage\FileStorageService using a module key from the
| `modules` list below. Nothing else in the codebase should call
| $file->move(), public_path('uploads/...') or Storage::disk() for uploads.
|
| Where files live is decided ONLY by .env:
|   FILE_STORAGE_DISK=gcs      -> Google Cloud Storage (config/filesystems.php `gcs` disk,
|                                 credentials/bucket/project from GOOGLE_CLOUD_* vars)
|   FILE_STORAGE_DISK=uploads  -> local public/uploads (default; dev / before GCS is set up)
|
| Changing Google project, bucket, service account or key = edit .env and run
| `php artisan config:clear`. No code change. See docs/file-storage.md.
|
*/

return [

    // Disk new uploads go to: 'gcs' (cloud) or 'uploads' (local public/). Any disk
    // name from config/filesystems.php works.
    'disk' => env('FILE_STORAGE_DISK', 'uploads'),

    // Disks that are "cloud": URLs are signed + expiring, and files that still exist
    // on the local disk (not yet migrated) keep being served from there.
    'cloud_disks' => ['gcs'],

    // Local disk used when not on a cloud disk, and as the read-fallback for files
    // uploaded before the move to the cloud. A module can override with 'local_disk'.
    'local_disk' => 'uploads',

    // Signed URL lifetime (minutes) for private modules.
    'signed_url_ttl' => (int) env('FILE_STORAGE_SIGNED_URL_TTL', 60),

    // Signed URL lifetime (minutes) for modules flagged 'public' => true (avatars,
    // announcement images, logos). GCS V4 signed URLs cannot exceed 7 days (10080).
    'public_url_ttl' => min((int) env('FILE_STORAGE_PUBLIC_URL_TTL', 10080), 10080),

    // Extensions that are never stored, whatever a module allows.
    'blocked_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'pht', 'html', 'htm', 'shtml', 'xhtml',
        'js', 'mjs', 'svg', 'svgz', 'xml', 'exe', 'msi', 'bat', 'cmd', 'com', 'sh', 'bash', 'ps1',
        'vbs', 'jar', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'htaccess', 'dll', 'scr',
    ],

    /*
    | Module registry.
    |   folder     path pattern; placeholders {tenant} {user} {id} {year} {month}
    |              are filled from the $context passed to upload().
    |   ext        allowed extensions (checked against the file's real content).
    |   max        max size in KB.
    |   public     true = long-lived URL (public_url_ttl), still signed. Default false.
    |   local_disk disk to use when FILE_STORAGE_DISK is not a cloud disk.
    |
    | ext/max mirror what each controller validated before this service existed.
    */
    'modules' => [
        'profile_photo' => [
            'folder' => 'uploads/users/{tenant}/profile',
            'ext' => ['jpg', 'jpeg', 'png', 'webp'],
            'max' => 2048,
            'public' => true,
        ],
        'employee_document' => [
            'folder' => 'uploads/users/{tenant}/{user}/documents',
            'ext' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
            'max' => 5120,
        ],
        'announcement_image' => [
            'folder' => 'uploads/announcement/{tenant}/image',
            'ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'max' => 2048,
            'public' => true,
        ],
        'announcement_file' => [
            'folder' => 'uploads/announcement/{tenant}/file',
            'ext' => ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],
            'max' => 5120,
        ],
        'leave' => [
            'folder' => 'uploads/leave/{tenant}',
            'ext' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'doc', 'docx'],
            'max' => 10240,
        ],
        'loan' => [
            'folder' => 'uploads/loan/{tenant}',
            'ext' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'doc', 'docx'],
            'max' => 10240,
        ],
        'request' => [
            'folder' => 'uploads/requests/{tenant}',
            'ext' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
            'max' => 5120,
        ],
        'regularization' => [
            'folder' => 'uploads/regularizations/{tenant}',
            'ext' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
            'max' => 2048,
        ],
        'task_document' => [
            'folder' => 'uploads/task/{tenant}/document',
            'ext' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
            'max' => 5120,
        ],
        'task_voice' => [
            'folder' => 'uploads/task/{tenant}/voice',
            'ext' => ['mp3', 'm4a', 'mp4', 'aac', 'wav', 'ogg', 'oga', 'opus', 'webm', 'weba', '3gp', 'amr'],
            'max' => 20480,
        ],
        'task_attachment' => [
            'folder' => 'uploads/task/{tenant}/attachments',
            'ext' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'],
            'max' => 10240,
        ],
        'project_attachment' => [
            'folder' => 'uploads/projects/{tenant}/{id}/attachments',
            'ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip'],
            'max' => 10240,
        ],
        'asset_attachment' => [
            'folder' => 'uploads/assets/{tenant}/{id}/attachments',
            'ext' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip'],
            'max' => 10240,
        ],
        'candidate_resume' => [
            'folder' => 'uploads/candidate_resumes/{tenant}',
            'ext' => ['pdf', 'doc', 'docx'],
            'max' => 5120,
        ],
        'candidate_document' => [
            'folder' => 'uploads/candidate_documents/{tenant}/{id}',
            'ext' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'],
            'max' => 5120,
        ],
        // Expense receipts stay PRIVATE on the local disk when not on the cloud
        // (served only through the signed expense.file / expense.attachment routes).
        'expense' => [
            'folder' => 'expense/{tenant}/{year}',
            'ext' => ['jpg', 'jpeg', 'png', 'pdf'],
            'max' => 5120,
            'local_disk' => 'local',
        ],
        // Written by hrm-superadmin (same bucket). No {tenant}: a logo is uploaded
        // while provisioning, before the tenant row exists.
        'tenant_logo' => [
            'folder' => 'uploads/tenants/logos',
            'ext' => ['jpg', 'jpeg', 'png', 'webp'],
            'max' => 2048,
            'public' => true,
        ],
    ],

    /*
    | Where files written BEFORE this service existed may still sit on local disk,
    | as root => URL base. url()/stream()/delete() look here (cheap is_file check)
    | before the active disk, so old DB paths keep working until
    | `php artisan files:migrate-to-cloud` has copied them.
    | 'url' => null means the root is private (streamed only, never linked).
    */
    'legacy_roots' => [
        ['root' => public_path(), 'url' => '', 'prefixes' => ['uploads/']],
        ['root' => storage_path('app/public'), 'url' => 'storage/', 'prefixes' => ['attendance_files/', 'tenant-logos/']],
        ['root' => storage_path('app/private'), 'url' => null, 'prefixes' => ['expense/']],
    ],
];
