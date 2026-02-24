<?php

namespace BookStack\Console\Commands;

use Illuminate\Console\Command;

class SystemBackupCommand extends Command
{
    protected $signature = 'bookstack:backup {--filename= : Custom filename for the backup ZIP}';

    protected $description = 'Create a full system backup including the database and all uploaded files.';

    public function handle(): int
    {
        ini_set('max_execution_time', (string) env('BACKUP_TIME_LIMIT', 600));

        $params = [];

        if ($filename = $this->option('filename')) {
            if (!str_ends_with($filename, '.zip')) {
                $filename .= '.zip';
            }
            $params['--filename'] = $filename;
        }

        $this->info('Starting backup...');
        $this->call('backup:run', $params);

        return 0;
    }
}
