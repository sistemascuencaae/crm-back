<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        // 'servidor' => [
        //     'driver' => 'local',
        //     'root' => 'Z:/dist2',
        // ],

        'nas' => [
            'driver' => 'ftp',
            // Credenciales únicas: SIN fallback. Deben estar en .env. Si faltan, falla limpio.
            'host'     => env('NAS_FTP_HOST'),
            'username' => env('NAS_FTP_USER'),
            'password' => env('NAS_FTP_PASSWORD'),
            'root'     => env('NAS_FTP_ROOT'),
            // Flags técnicos: defaults genéricos seguros.
            'port'     => (int) env('NAS_FTP_PORT', 21),
            'passive'  => (bool) env('NAS_FTP_PASSIVE', true),
            'ssl'      => (bool) env('NAS_FTP_SSL', false),
            'timeout'  => (int) env('NAS_FTP_TIMEOUT', 30),
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/public/crm'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL') . '/storage',
            'visibility' => 'public',
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