<?php

use League\Flysystem\GoogleCloudStorage\PortableVisibilityHandler;

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
    | Supported drivers: "local", "ftp", "sftp", "s3"
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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
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

        'gcs' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE'),
            'bucket' => env('GCS_BUCKET'),
            'path_prefix' => env('GCS_PATH_PREFIX', ''),
            'visibility' => PortableVisibilityHandler::NO_PREDEFINED_VISIBILITY,
            'throw' => true,
            'report' => false,
        ],

        'wordpress_gcs' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE'),
            'bucket' => env('WORDPRESS_GCS_BUCKET'),
            'path_prefix' => '',
            'visibility' => PortableVisibilityHandler::NO_PREDEFINED_VISIBILITY,
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Profile Photos Disk
    |--------------------------------------------------------------------------
    |
    | ローカル: public（storage/app/public）
    | 本番: gcs（Cloud Storage）
    |
    */

    'profile_photos_disk' => env('FILESYSTEM_PROFILE_PHOTOS_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Contents Disk
    |--------------------------------------------------------------------------
    |
    | Top Page の HTML / 画像
    | ローカル: public / 本番: gcs
    |
    */

    'dashboard_contents_disk' => env('FILESYSTEM_DASHBOARD_CONTENTS_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Site Branding Disk
    |--------------------------------------------------------------------------
    |
    | ログイン画面などのブランド画像
    | ローカル: public / 本番: gcs
    |
    */

    'branding_disk' => env('FILESYSTEM_BRANDING_DISK', 'public'),

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
