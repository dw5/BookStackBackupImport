<?php

/**
 * Backup configuration for BookStack.
 * Uses spatie/laravel-backup package.
 *
 * Configuration should be altered via the `.env` file or environment variables.
 */

$included_dirs = [
    storage_path('uploads'),
    public_path('uploads'),
];

if (env('BACKUP_ENV') == 'true') {
    $included_dirs[] = base_path('.env');
}

return [

    'backup' => [

        'name' => 'bookstack-backups',

        'source' => [

            'files' => [

                'include' => $included_dirs,

                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('backups'),
                    storage_path('framework'),
                    storage_path('logs'),
                    storage_path('debugbar'),
                ],

                'follow_links' => false,

                'ignore_unreadable_directories' => false,

                'relative_path' => base_path(),
            ],

            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => null,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [

            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => 'bookstack-',

            'disks' => [
                'backup',
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        'tries' => 1,

        'retry_delay' => 0,
    ],

    'notifications' => [

        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => [],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('MAIL_BACKUP_NOTIFICATION_ADDRESS', 'backup@example.com'),

            'from' => [
                'address' => env('MAIL_FROM', 'bookstack@example.com'),
                'name' => env('MAIL_FROM_NAME', 'BookStack'),
            ],
        ],
    ],

    'monitor_backups' => [],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => env('BACKUP_KEEP_ALL_DAYS', 7),
            'keep_daily_backups_for_days' => env('BACKUP_KEEP_DAILY_DAYS', 16),
            'keep_weekly_backups_for_weeks' => env('BACKUP_KEEP_WEEKLY_WEEKS', 8),
            'keep_monthly_backups_for_months' => env('BACKUP_KEEP_MONTHLY_MONTHS', 4),
            'keep_yearly_backups_for_years' => env('BACKUP_KEEP_YEARLY_YEARS', 2),
            'delete_oldest_backups_when_using_more_megabytes_than' => env('BACKUP_MAX_SIZE_MB', 5000),
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],
];
