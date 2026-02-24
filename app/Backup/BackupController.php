<?php

namespace BookStack\Backup;

use BookStack\Activity\ActivityType;
use BookStack\Http\Controller;
use BookStack\Permissions\Permission;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backups,
    ) {
    }

    /**
     * List all available backups.
     */
    public function index()
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->setPageTitle(trans('settings.backup_title'));

        $files = $this->backups->listBackups();

        return view('settings.backups', [
            'files' => $files,
        ]);
    }

    /**
     * Create a new backup.
     */
    public function create()
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->preventAccessInDemoMode();

        $output = $this->backups->createBackup();

        if (!preg_match('/failed/i', $output)) {
            $this->logActivity(ActivityType::MAINTENANCE_ACTION_RUN, 'backup-create');
            $this->showSuccessNotification(trans('settings.backup_create_success'));
        } else {
            $this->showErrorNotification(trans('settings.backup_create_failure'));
        }

        return redirect('/settings/backups');
    }

    /**
     * Download a backup file.
     */
    public function downloadFile(string $filename)
    {
        $this->checkPermission(Permission::SettingsManage);

        $path = $this->backups->getBackupPath($filename);
        if (!$path) {
            $this->showErrorNotification(trans('settings.backup_file_not_found'));

            return redirect('/settings/backups');
        }

        $this->logActivity(ActivityType::MAINTENANCE_ACTION_RUN, 'backup-download');

        return $this->download()->streamedFileDirectly($path, $filename);
    }

    /**
     * Delete a backup file.
     */
    public function delete(string $filename)
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->preventAccessInDemoMode();

        if (!$this->backups->deleteBackup($filename)) {
            $this->showErrorNotification(trans('settings.backup_file_not_found'));

            return redirect('/settings/backups');
        }

        $this->logActivity(ActivityType::MAINTENANCE_ACTION_RUN, 'backup-delete');
        $this->showSuccessNotification(trans('settings.backup_delete_success'));

        return redirect('/settings/backups');
    }

    /**
     * Upload a backup ZIP file.
     */
    public function upload(Request $request)
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->preventAccessInDemoMode();

        $this->validate($request, [
            'file' => ['required', 'mimes:zip', 'max:' . (config('app.upload_limit', 50) * 1000)],
        ]);

        $this->backups->storeUploadedBackup($request->file('file'));

        $this->logActivity(ActivityType::MAINTENANCE_ACTION_RUN, 'backup-upload');
        $this->showSuccessNotification(trans('settings.backup_upload_success'));

        return redirect('/settings/backups');
    }

    /**
     * Restore from a backup file.
     */
    public function restore(string $filename)
    {
        $this->checkPermission(Permission::SettingsManage);
        $this->preventAccessInDemoMode();

        $this->logActivity(ActivityType::MAINTENANCE_ACTION_RUN, 'backup-restore');

        $result = $this->backups->restoreFromBackup($filename);

        if ($result['success']) {
            $this->showSuccessNotification(trans('settings.backup_restore_success'));
        } else {
            $this->showErrorNotification($result['message']);
        }

        return redirect('/settings/backups');
    }
}
