<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrUpdateRemarkPreviousDay extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:UpdateRemarkPreviousDay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update hr detail table based on user';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle($date = null) {
        try {
            $today = date('Y-m-d');
            $yesterDay = date('Y-m-d', strtotime($today . ' -1 day'));
            $day = date('d');
            $oldUserList = \App\Models\User::where("is_active", "0")->update(["email" => '']);

            \App\Models\User::where("user_left_date", "<", $today)->where("user_left_date", "!=", '0000-00-00')->where("is_active", "1")->update(['is_active' => 0]);
            $userProbation = \App\Models\User::where("probation_date", "<=", $today)->where("is_active", "1")->where("user_type", "0");
            if ($userProbation->count() >= 0) {
                foreach ($userProbation->get() as $uprobation) {
                     $month = date('m');
                    if ($month >= 1) {
                        $month = monthsBetween(date('Y-m-d'), date('Y-06-30'));
                    } else {
                        $year = date('Y') + 1;
                        $month = monthsBetween(date('Y-m-d'), date($year . '-06-30'));
                    }
                    $remaningLeave = $month * 1.5;
                    \App\Models\Backend\HrLeaveBalance::create([
                        'user_id' => $uprobation->id,
                        "month" => date('M-Y', strtotime($today)),
                        "cl" => $remaningLeave,
                        "co" => '0.00',
                        "la" => '0.00',
                        "created_on" => date("y-m-d H:i:s"),
                        "created_by" => 1
                    ]);
                    \App\Models\User::where("id", $uprobation->id)->update(["user_type" => 1]);
                }
            }
            
            \App\Http\Controllers\Backend\Hr\HRController::updateRemark($yesterDay);
            if ($day >= 28) {
                \App\Http\Controllers\Backend\Hr\AttendanceController::HRLeaveBal($today);
            }
            if ($day == 28) {               
                //Add condition unit = 0 then update add leave 
                 $startDate = date('Y-m-26', strtotime("-1 month", strtotime($today)));
                 $endDate = date('Y-m-25');
                $hrDetailformonth = \App\Models\Backend\HrDetail::where("hr_detail.date", ">=", $startDate)->where("hr_detail.date", "<=", $endDate)
                        ->get();
                foreach($hrDetailformonth as $h){                    
                    $checkForHolidayWorking = \App\Http\Controllers\Backend\Hr\AttendanceController::isHolidayOrNot($h->date, $h->shift_id);
                   
                    if ($checkForHolidayWorking != '') {
                        \App\Models\Backend\HrDetail::where("id",$h->id)->update(["is_holiday" => "1"]);
                    }
                 }
                //Add condition unit = 0 then update add leave 
                
                 $hrDetailForUnit = \App\Models\Backend\HrDetail::where("hr_detail.date", ">=", $startDate)->where("hr_detail.date", "<=", $endDate)
                        ->where("unit", "0")->whereRaw("(punch_in IS NOT NULL and punch_out is not NULL)")
                        ->where("is_holiday","0")
                        ->where("final_remark", "0")->update(['final_remark' => 3,'hr_final_remark' => 3]);
                
                 $hrDetailHoliday = \App\Models\Backend\HrDetail::where("hr_detail.date", ">=", $startDate)->where("hr_detail.date", "<=", $endDate)
                        ->where("is_holiday","1")
                         ->where("remark","!=","6")
                        ->where("final_remark", "3")->update(['final_remark' => 0,'hr_final_remark' => 0]);

                \App\Http\Controllers\Backend\Hr\HRController::monthEndUpdateRemark($yesterDay);
            }
        } catch (Exception $ex) {
            $cronName = "HR Update Remark cron not working";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
