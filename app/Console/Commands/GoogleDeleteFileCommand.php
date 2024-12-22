<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class GoogleDeleteFileCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drive:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete File Permission';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            \App\Http\Controllers\Backend\GoogleDrive\GoogleDriveFileController::deleteFilePermanent();
        } catch (Exception $e) {
            $cronName = "Google Drive Revoke Permission";
            $message = $e->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
