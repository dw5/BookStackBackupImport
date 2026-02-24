<?php

namespace BookStack\Backup;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class BackupService
{
    protected string $disk = 'backup';
    protected string $backupDir = 'bookstack-backups';

    /**
     * List all backup ZIP files with metadata.
     */
    public function listBackups(string $sort = 'date', string $order = 'desc'): array
    {
        $files = Storage::disk($this->disk)->files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            $basename = basename($file);

            if (str_starts_with($basename, '.')) {
                continue;
            }

            if (!str_ends_with(strtolower($basename), '.zip')) {
                continue;
            }

            $backups[] = [
                'filename' => $basename,
                'filesize' => $this->formatFileSize(Storage::disk($this->disk)->size($file)),
                'filesize_raw' => Storage::disk($this->disk)->size($file),
                'modified_at' => Storage::disk($this->disk)->lastModified($file),
            ];
        }

        $sortField = match ($sort) {
            'name' => 'filename',
            'size' => 'filesize_raw',
            default => 'modified_at',
        };

        usort($backups, function ($a, $b) use ($sortField, $order) {
            $cmp = $a[$sortField] <=> $b[$sortField];
            return $order === 'asc' ? $cmp : -$cmp;
        });

        return $backups;
    }

    /**
     * Create a new backup by calling spatie's backup:run command.
     */
    public function createBackup(?string $filename = null): string
    {
        $filename = $filename ?? 'manual-backup-' . date('Y-m-d-H-i-s') . '.zip';

        if (!str_ends_with($filename, '.zip')) {
            $filename .= '.zip';
        }

        ini_set('max_execution_time', (string) env('BACKUP_TIME_LIMIT', 600));
        Artisan::call('backup:run', ['--filename' => $filename]);

        return Artisan::output();
    }

    /**
     * Get the full filesystem path for a backup file.
     */
    public function getBackupPath(string $filename): ?string
    {
        if (!$this->backupExists($filename)) {
            return null;
        }

        return storage_path('backups/' . $this->backupDir . '/' . $filename);
    }

    /**
     * Check if a backup file exists on disk.
     */
    public function backupExists(string $filename): bool
    {
        return Storage::disk($this->disk)->exists($this->backupDir . '/' . $filename);
    }

    /**
     * Delete a backup file from disk.
     */
    public function deleteBackup(string $filename): bool
    {
        if (!$this->backupExists($filename)) {
            return false;
        }

        return Storage::disk($this->disk)->delete($this->backupDir . '/' . $filename);
    }

    /**
     * Store an uploaded backup ZIP file.
     */
    public function storeUploadedBackup(UploadedFile $file): string
    {
        $uploadFilename = 'uploaded-' . date('U') . '-' . Str::slug(
            pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)
        ) . '.zip';

        Storage::disk($this->disk)->putFileAs($this->backupDir, $file, $uploadFilename);

        return $uploadFilename;
    }

    /**
     * Restore from a backup ZIP file.
     * Finds the SQL dump inside the ZIP, pipes it through the mysql CLI,
     * then extracts upload files to their correct locations.
     */
    public function restoreFromBackup(string $filename): array
    {
        $path = $this->getBackupPath($filename);
        if (!$path) {
            return ['success' => false, 'message' => 'Backup file not found.'];
        }

        $za = new ZipArchive();
        if ($za->open($path) !== true) {
            return ['success' => false, 'message' => 'Could not open backup ZIP file.'];
        }

        $sqlFile = $this->findSqlFile($za);
        if (!$sqlFile) {
            $za->close();

            return ['success' => false, 'message' => 'No SQL dump file found in backup.'];
        }

        // Restore database via mysql CLI
        $dbResult = $this->restoreDatabase($za, $sqlFile);
        if (!$dbResult['success']) {
            $za->close();

            return $dbResult;
        }

        // Restore uploaded files
        $filesRestored = $this->restoreFiles($za);

        $za->close();

        // Run migrations to ensure schema is current
        Artisan::call('migrate', ['--force' => true]);
        $migrationOutput = Artisan::output();

        return [
            'success' => true,
            'message' => 'Restore completed successfully.',
            'files_restored' => $filesRestored,
            'migration_output' => $migrationOutput,
        ];
    }

    /**
     * Find the SQL dump file within the ZIP archive.
     */
    protected function findSqlFile(ZipArchive $za): ?string
    {
        for ($i = 0; $i < $za->numFiles; $i++) {
            $name = $za->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.sql')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Restore the database from the SQL file inside the ZIP.
     * Uses the mysql CLI tool to pipe the SQL directly.
     */
    protected function restoreDatabase(ZipArchive $za, string $sqlFile): array
    {
        $sqlStream = $za->getStream($sqlFile);
        if ($sqlStream === false) {
            return ['success' => false, 'message' => 'Could not read SQL file from backup.'];
        }

        $dbConfig = config('database.connections.mysql');
        $mysqlBinary = env('DB_DUMP_PATH', '/usr/bin') . '/mysql';

        if (!file_exists($mysqlBinary)) {
            $mysqlBinary = trim(shell_exec('which mysql') ?? '');
            if (empty($mysqlBinary)) {
                fclose($sqlStream);

                return ['success' => false, 'message' => 'mysql binary not found. Set DB_DUMP_PATH in .env.'];
            }
        }

        $env = getenv();
        $env['MYSQL_PWD'] = $dbConfig['password'];

        $cmd = escapeshellarg($mysqlBinary)
            . ' -h ' . escapeshellarg($dbConfig['host'])
            . ' -u ' . escapeshellarg($dbConfig['username']);

        if (!empty($dbConfig['port'])) {
            $cmd .= ' -P ' . escapeshellarg((string) $dbConfig['port']);
        }

        $cmd .= ' ' . escapeshellarg($dbConfig['database']);

        $pipes = [];
        $proc = proc_open(
            $cmd,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env
        );

        if ($proc === false) {
            fclose($sqlStream);

            return ['success' => false, 'message' => 'Unable to invoke mysql CLI.'];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $bytesRead = 0;
        while (($buffer = fgets($sqlStream, 1024 * 1024)) !== false) {
            $bytesRead += strlen($buffer);
            $written = fwrite($pipes[0], $buffer);
            if ($written === false) {
                break;
            }
        }

        fclose($pipes[0]);
        fclose($sqlStream);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);

        if ($exitCode !== 0) {
            Log::error("Database restore failed. Exit code: {$exitCode}. stderr: {$stderr}");

            return ['success' => false, 'message' => 'Database import failed: ' . $stderr];
        }

        Log::info("Database restore complete. {$bytesRead} bytes read.");

        return ['success' => true];
    }

    /**
     * Restore uploaded files from the ZIP archive.
     * Maps ZIP paths back to BookStack's upload directories.
     */
    protected function restoreFiles(ZipArchive $za): int
    {
        $restoreDirs = [
            'storage/uploads/' => storage_path('uploads'),
            'public/uploads/'  => public_path('uploads'),
        ];

        $count = 0;

        for ($i = 0; $i < $za->numFiles; $i++) {
            $name = $za->getNameIndex($i);

            // Skip SQL files and directories
            if (str_ends_with(strtolower($name), '.sql') || str_ends_with($name, '/')) {
                continue;
            }

            foreach ($restoreDirs as $zipPrefix => $localBase) {
                $pos = strpos($name, $zipPrefix);
                if ($pos !== false) {
                    $relativePath = substr($name, $pos + strlen($zipPrefix));
                    $destPath = $localBase . '/' . $relativePath;
                    $destDir = dirname($destPath);

                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }

                    $stream = $za->getStream($name);
                    if ($stream !== false) {
                        $destFile = fopen($destPath, 'w');
                        while (($buffer = fgets($stream, 1024 * 1024)) !== false) {
                            fwrite($destFile, $buffer);
                        }
                        fclose($destFile);
                        fclose($stream);
                        $count++;
                    }

                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Format file size into a human-readable string.
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
