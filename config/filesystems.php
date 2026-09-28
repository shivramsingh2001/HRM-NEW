<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3", "gcs"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Local fallback for App\Services\Storage\FileStorageService (config/file_storage.php).
        // Root is public/ because every path it writes starts with "uploads/" — the same
        // layout the app used before the service existed.
        'uploads' => [
            'driver' => 'local',
            'root' => public_path(),
            'url' => env('APP_URL'),
            'visibility' => 'public',
            'throw' => true,
            'report' => false,
        ],

        // Google Cloud Storage. Everything comes from .env — see docs/file-storage.md.
        // Credentials: GOOGLE_CLOUD_KEY_FILE (path to the service-account JSON) or
        // GOOGLE_CLOUD_KEY_JSON_BASE64 (the JSON, base64-encoded). With neither set, the
        // Google SDK uses Application Default Credentials (attached service account on GCP).
        'gcs' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE') ?: null,
            'key_file' => (function () {
                $b64 = env('GOOGLE_CLOUD_KEY_JSON_BASE64');
                $decoded = $b64 ? json_decode((string) base64_decode($b64, true), true) : null;

                return is_array($decoded) ? $decoded : null;
            })(),
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
            'path_prefix' => env('GOOGLE_CLOUD_STORAGE_PATH_PREFIX', ''),
            'storage_api_uri' => env('GOOGLE_CLOUD_STORAGE_API_URI') ?: null,
            'api_endpoint' => env('GOOGLE_CLOUD_STORAGE_API_ENDPOINT') ?: null,
            // Bucket uses uniform bucket-level access: no per-object ACLs.
            'visibility' => 'private',
            'visibility_handler' => \League\Flysystem\GoogleCloudStorage\UniformBucketLevelAccessVisibility::class,
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
