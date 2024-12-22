<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrAutoRejectedMissedTimesheet extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:autorejectmisstimesheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto reject missed timesheet';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $firstDayofMonth = date("d");
            $today = date("Y-m-d");

            $ids = array();
            if ($firstDayofMonth == "29") {
                $startDate = date('Y-m-26', strtotime("-1 month", strtotime($today)));
                $endDate = date('Y-m-25', strtotime($today));                
               // $toDate = date('Y-m-25', strtotime('+1 month -1 second', strtotime(date('Y-m-01'))));
                $ids = \App\Models\Backend\PendingTimesheet::whereRaw("created_on BETWEEN '".$startDate."' AND '".$endDate."'")->whereIn('stage_id', [0, 1, 2])->pluck('id','id')->toArray();
            } else {
                $ids = \App\Models\Backend\PendingTimesheet::whereRaw("DATE_FORMAT(created_on, '%Y-%m-%d') = '" . $today . "'")->whereIn('stage_id', [0, 1])->pluck('id','id')->toArray();
            }
            
            if(!empty($ids))
                \App\Models\Backend\PendingTimesheet::whereIn('id', $ids)->update(['stage_id' => 4, 'approval_person' => 1, 'approval_comment' => 'Rejected by system']);
            
        } catch (Exception $ex) {
            $cronName = "HR Auto Reject Missed Timesheet";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
