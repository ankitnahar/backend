<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrRejectedRemainingApproval extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:rejectedremainingapproval';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto reject remaining approval';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $firstDayofMonth = date("d");
            $today = date("Y-m-d");
            if ($firstDayofMonth == "28") {
                $startDate = date('Y-m-26', strtotime("-1 month", strtotime($today)));
                $endDate = date('Y-m-25', strtotime($today));
                $userDetail = \App\Models\Backend\HrDetail::where("date",">=",$startDate)
                        ->where("date","<=",$endDate)
                        ->whereIn("status",[2,3,4])
                        ->update(['status'=>6]);                             

              
            }
        } catch (Exception $ex) {
            $data['to'] = 'bdmsdeveloper@befree.com.au';
            $data['subject'] = 'Auto rejection Early and Late comming cron not run dated: ' . date('d-m-Y H:i:s');
            $data['content'] = '<h3 style="font-family:sans-serif;">Hello Team,</h3><p style="font-family:sans-serif;">Update remark previous day cron does not execute due to below mentioned exception.</p><p style="font-family:sans-serif;">' . $ex->getMessage() . '</p>';
            storeMail('', $data);
        }
    }

}
