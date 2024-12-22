<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class PendingWorksheetSechdule extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pending:worksheetsechdule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert Pending worksheet sechdule one time in a year';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle($date = null) {
        try {
            \App\Http\Controllers\Backend\PendingWorksheetSchedule\PendingWorksheetSchedule::pendingWorksheetScheduler();
            
        } catch (Exception $ex) {
             $cronName = "Pending Worksheet Sechdule cron not working";
             $message = $ex->getMessage();
             cronNotWorking($cronName,$message);
        }
    }

}
