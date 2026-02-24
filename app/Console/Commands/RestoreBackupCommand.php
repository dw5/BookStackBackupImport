<?php

namespace BookStack\Console\Commands;

use BookStack\Backup\BackupService;
use Illuminate\Console\Command;

class RestoreBackupCommand extends Command
{
    protected $signature = 'bookstack:restore
                            {filename : The backup ZIP file path or filename in storage/backups/}
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Restore from a previously created BookStack backup file.';

    public function handle(BackupService $backups): int
    {
        $filename = $this->argument('filename');

        // If it's an absolute path, use the basename to find in storage
        if (str_starts_with($filename, '/')) {
            $filename = basename($filename);
        }

        if (!$backups->backupExists($filename)) {
            $this->error("Backup file not found: {$filename}");

            return 1;
        }

        if (!$this->option('force') && !$this->confirm('Are you sure? This will overwrite the current database and uploaded files. This cannot be undone!')) {
            $this->info('Restore cancelled.');

            return 0;
        }

        $this->info('Restoring from backup: ' . $filename);

        $result = $backups->restoreFromBackup($filename);

        if ($result['success']) {
            $this->info('Restore completed successfully.');
            $this->info('Files restored: ' . ($result['files_restored'] ?? 0));

            if (!empty($result['migration_output'])) {
                $this->line($result['migration_output']);
            }

            return 0;
        }

        $this->error('Restore failed: ' . $result['message']);

        return 1;
    }
}
