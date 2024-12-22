<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrNoJobArchive extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nojob:archive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'End of day no job move to archive if no body take action';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $todayDate = date('Y-m-d');
            \App\Models\Backend\HrNojob::where('date', '<=',$todayDate)->update(array('is_active' => 0));
        } catch (Exception $ex) {
            $cronName = "HR No Job Archive";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
