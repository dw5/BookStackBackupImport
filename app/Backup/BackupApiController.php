<?php

namespace BookStack\Backup;

use BookStack\Http\ApiController;
use Illuminate\Http\JsonResponse;

class BackupApiController extends ApiController
{
    public function __construct(
        protected BackupService $backups,
    ) {
    }

    /**
     * List all available backup files.
     */
    public function list(): JsonResponse
    {
        return response()->json([
            'data' => $this->backups->listBackups(),
        ]);
    }

    /**
     * Create a new backup.
     */
    public function create(): JsonResponse
    {
        $output = $this->backups->createBackup();
        $success = !preg_match('/failed/i', $output);

        return response()->json([
            'success' => $success,
            'output' => $output,
        ], $success ? 200 : 500);
    }

    /**
     * Download a specific backup file.
     */
    public function downloadFile(string $filename)
    {
        $path = $this->backups->getBackupPath($filename);
        if (!$path) {
            return $this->jsonError('Backup file not found', 404);
        }

        return response()->download($path, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Download the most recent backup file.
     */
    public function downloadLatest()
    {
        $backups = $this->backups->listBackups();
        if (empty($backups)) {
            return $this->jsonError('No backups found', 404);
        }

        return $this->downloadFile($backups[0]['filename']);
    }

    /**
     * Delete a backup file.
     */
    public function delete(string $filename): JsonResponse
    {
        if (!$this->backups->deleteBackup($filename)) {
            return $this->jsonError('Backup file not found', 404);
        }

        return response()->json(['success' => true]);
    }
}
