<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Backup Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default disk that should be used for storing
    | backup files. This can be any of the disks configured in the
    | filesystems.php configuration file.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Backup Storage Path
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default path where backup files will be stored.
    | This path is relative to the root of the selected disk.
    |
    */

    'path' => env('BACKUP_PATH', 'backups'),

    /*
    |--------------------------------------------------------------------------
    | Backup Retention Policy
    |--------------------------------------------------------------------------
    |
    | Configure how long different types of backups should be retained.
    | The cleanup command will automatically remove old backups based on
    | these settings.
    |
    */

    'retention' => [
        'daily' => env('BACKUP_RETENTION_DAILY', 30),      // Keep daily backups for 30 days
        'weekly' => env('BACKUP_RETENTION_WEEKLY', 12),    // Keep weekly backups for 12 weeks
        'monthly' => env('BACKUP_RETENTION_MONTHLY', 12),  // Keep monthly backups for 12 months
        'yearly' => env('BACKUP_RETENTION_YEARLY', 5),     // Keep yearly backups for 5 years
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Compression
    |--------------------------------------------------------------------------
    |
    | Configure compression settings for backup files. Enabling compression
    | will reduce file size but may increase processing time.
    |
    */

    'compression' => [
        'enabled' => env('BACKUP_COMPRESSION_ENABLED', true),
        'level' => env('BACKUP_COMPRESSION_LEVEL', 6), // 0-9, where 9 is maximum compression
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Encryption
    |--------------------------------------------------------------------------
    |
    | Configure encryption settings for backup files. When enabled, backup
    | files will be encrypted using the specified key.
    |
    */

    'encryption' => [
        'enabled' => env('BACKUP_ENCRYPTION_ENABLED', false),
        'key' => env('BACKUP_ENCRYPTION_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Notifications
    |--------------------------------------------------------------------------
    |
    | Configure notification settings for backup operations. Notifications
    | will be sent to the specified channels when backups complete or fail.
    |
    */

    'notifications' => [
        'enabled' => env('BACKUP_NOTIFICATIONS_ENABLED', true),
        'channels' => [
            'mail' => env('BACKUP_MAIL_NOTIFICATIONS_ENABLED', true),
            'database' => env('BACKUP_DATABASE_NOTIFICATIONS_ENABLED', true),
        ],
        'on_success' => env('BACKUP_NOTIFY_ON_SUCCESS', true),
        'on_failure' => env('BACKUP_NOTIFY_ON_FAILURE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue settings for backup jobs. Using a queue allows
    | backups to be processed in the background without blocking the
    | application.
    |
    */

    'queue' => [
        'connection' => env('BACKUP_QUEUE_CONNECTION', 'default'),
        'queue' => env('BACKUP_QUEUE_NAME', 'backups'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Tables
    |--------------------------------------------------------------------------
    |
    | Configure which tables should be included in different types of backups.
    | You can specify tables to include or exclude from backups.
    |
    */

    'tables' => [
        'include' => [
            // Tables to include in full backups
            'full' => [
                'users',
                'stores',
                'products',
                'customers',
                'suppliers',
                'orders',
                'order_items',
                'payments',
                'purchases',
                'purchase_items',
                'supplier_payments',
                'stock_adjustments',
                'cashier_shifts',
                'sales_returns',
                'sales_return_items',
                'store_stocks',
                'store_orders',
                'store_order_items',
                'store_payments',
                'store_cashier_shifts',
                'settings',
            ],
            
            // Tables to include in selective backups
            'selective' => [
                'products',
                'customers',
                'suppliers',
                'orders',
                'order_items',
                'payments',
            ],
            
            // Tables to include in structure-only backups
            'structure' => [
                'users',
                'stores',
                'products',
                'customers',
                'suppliers',
                'orders',
                'order_items',
                'payments',
                'purchases',
                'purchase_items',
                'supplier_payments',
                'stock_adjustments',
                'cashier_shifts',
                'sales_returns',
                'sales_return_items',
                'store_stocks',
                'store_orders',
                'store_order_items',
                'store_payments',
                'store_cashier_shifts',
                'settings',
            ],
        ],
        
        'exclude' => [
            // Tables to exclude from all backups
            'all' => [
                'migrations',
                'failed_jobs',
                'password_reset_tokens',
                'personal_access_tokens',
                'sessions',
            ],
            
            // Tables to exclude from incremental backups
            'incremental' => [
                'backups',
                'import_export_jobs',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Schedules
    |--------------------------------------------------------------------------
    |
    | Configure automatic backup schedules. These schedules will be
    | registered with the Laravel scheduler when the scheduler runs.
    |
    */

    'schedules' => [
        'daily' => [
            'enabled' => env('BACKUP_DAILY_ENABLED', false),
            'time' => env('BACKUP_DAILY_TIME', '02:00'),
            'type' => env('BACKUP_DAILY_TYPE', 'incremental'),
        ],
        
        'weekly' => [
            'enabled' => env('BACKUP_WEEKLY_ENABLED', false),
            'day' => env('BACKUP_WEEKLY_DAY', 0), // 0 = Sunday
            'time' => env('BACKUP_WEEKLY_TIME', '03:00'),
            'type' => env('BACKUP_WEEKLY_TYPE', 'selective'),
        ],
        
        'monthly' => [
            'enabled' => env('BACKUP_MONTHLY_ENABLED', false),
            'day' => env('BACKUP_MONTHLY_DAY', 1), // 1st of the month
            'time' => env('BACKUP_MONTHLY_TIME', '04:00'),
            'type' => env('BACKUP_MONTHLY_TYPE', 'full'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Performance
    |--------------------------------------------------------------------------
    |
    | Configure performance settings for backup operations. These settings
    | can help optimize backup performance for large databases.
    |
    */

    'performance' => [
        'chunk_size' => env('BACKUP_CHUNK_SIZE', 1000), // Number of records to process at once
        'memory_limit' => env('BACKUP_MEMORY_LIMIT', '512M'), // PHP memory limit for backups
        'timeout' => env('BACKUP_TIMEOUT', 3600), // Maximum execution time in seconds
        'max_concurrent' => env('BACKUP_MAX_CONCURRENT', 1), // Maximum concurrent backup jobs
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Security
    |--------------------------------------------------------------------------
    |
    | Configure security settings for backup operations. These settings
    | help ensure that backup files are stored securely.
    |
    */

    'security' => [
        'file_permissions' => env('BACKUP_FILE_PERMISSIONS', '0600'), // File permissions for backup files
        'directory_permissions' => env('BACKUP_DIR_PERMISSIONS', '0700'), // Directory permissions for backup directory
        'validate_integrity' => env('BACKUP_VALIDATE_INTEGRITY', true), // Validate backup file integrity
        'sanitize_filenames' => env('BACKUP_SANITIZE_FILENAMES', true), // Sanitize backup filenames
    ],

    /*
    |--------------------------------------------------------------------------
    | Backup Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging settings for backup operations. Detailed logs can
    | help with troubleshooting backup issues.
    |
    */

    'logging' => [
        'enabled' => env('BACKUP_LOGGING_ENABLED', true),
        'level' => env('BACKUP_LOG_LEVEL', 'info'), // debug, info, warning, error
        'channel' => env('BACKUP_LOG_CHANNEL', 'default'),
        'include_queries' => env('BACKUP_LOG_QUERIES', false), // Include SQL queries in logs
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Store Support
    |--------------------------------------------------------------------------
    |
    | Configure multi-store backup settings. When enabled, backups can be
    | created for specific stores or for all stores.
    |
    */

    'multi_store' => [
        'enabled' => env('BACKUP_MULTI_STORE_ENABLED', true),
        'include_store_data' => env('BACKUP_INCLUDE_STORE_DATA', true),
        'separate_store_backups' => env('BACKUP_SEPARATE_STORE_BACKUPS', false),
    ],

];