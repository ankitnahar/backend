<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class GoogleDriveFolderCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google:folder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Google Drive Folder Auto create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {            
        ini_set('max_execution_time', '0');
       // $entityId = 10;
        $val = \App\Http\Controllers\Backend\GoogleDrive\GoogleDriveFolderController::yearFolderCreate();
        
         } catch (Exception $e) {
          $cronName = "Google Drive Folder";
          $message = $e->getMessage();
          cronNotWorking($cronName,$message);
          } 
    }

}
