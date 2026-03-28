<?php

return [

    'max_upload_size' => env('DATABASE_IMPORT_EXPORT_MAX_SIZE', 10240),


    'export_temp_dir' => storage_path('app/temp-exports'),


    'import_timeout' => env('DATABASE_IMPORT_EXPORT_TIMEOUT', 300),


    'export_settings' => [
        'no-create-db' => true,
        'add-drop-table' => true,
        'add-drop-trigger' => true,
        'skip-definer' => true,
        'single-transaction' => true,
        'lock-tables' => false,
        'add-locks' => true,
        'extended-insert' => true,
        'disable-keys' => true,
        'default-character-set' => 'utf8mb4',
    ],

    'blocked_statements' => [
        'CREATE DATABASE',
        'DROP DATABASE',
        'CREATE USER',
        'DROP USER',
        'GRANT',
        'REVOKE',
        'USE ',
    ],
];
