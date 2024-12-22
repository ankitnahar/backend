<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class GoogleRevokePermissionCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drive:revoke';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoke File Permission';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            \App\Http\Controllers\Backend\GoogleDrive\GoogleDriveFileController::revokePermission();
        } catch (Exception $e) {
            $cronName = "Google Drive Revoke Permission";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
