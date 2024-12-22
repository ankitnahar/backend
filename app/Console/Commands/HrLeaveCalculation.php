<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrLeaveCalculation extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:LeaveCalculcation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Leave Calculation';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
        ini_set('max_execution_time', '0');
        $todayDay = date("d");
        $month = date('m');
        $lastMonth = date('M-Y', strtotime("-1 month"));
        $yearMonth = date('M-Y');
        if ($todayDay == '06') {
            $firstDate = date("Y-m-d", strtotime("first day of previous month"));
            $lastDate = date("Y-m-d", strtotime("last day of previous month"));
            $newjoin = \App\Models\User::where("is_active", "1")
                    ->whereRaw("user_joining_date >= '" . $firstDate . "' and user_joining_date <='" . $lastDate . "'");
            if ($newjoin->count() > 0) {
                foreach ($newjoin->get() as $n) {
                    $day = date("d", strtotime($n->user_joining_date));

                    if ($day <= 10) {
                        $cl = '1.5';
                    } else if ($day > 10 && $day <= 20) {
                        $cl = '1.0';
                    } else {
                        $cl = '0.5';
                    }
                    //$userAr=array("day"=>$day,"user_id" => $n->userfullname,"cl"=>$cl);
                    // showArray($userAr);
                    \App\Models\Backend\HrLeaveBalance::create([
                        'user_id' => $n->id,
                        "month" => date('M-Y', strtotime($firstDate)),
                        "cl" => $cl,
                        "co" => '0.00',
                        "la" => '0.00',
                        "created_on" => date("y-m-d H:i:s"),
                        "created_by" => 1
                    ]);
                }
            }
            $userException = \App\Models\Backend\Constant::select('constant_value')->where('constant_name', 'NOT_INCLUDE_FILL_TIMESHEET')->get();
            $userExceptionId = explode(',', $userException[0]->constant_value);
            $userDetail = \App\Models\User::where("is_active", "1");
            if (!empty($userExceptionId)) {
                $userDetail = $userDetail->whereNotIn('id', $userExceptionId);
            }
            //$userDetail = $userDetail->where("id", 40);
            $userDetail = $userDetail->orderBy("id", "desc")->get();
            foreach ($userDetail as $user) {
                // echo $user->id;
                $CL = $CO = $LA = 0;
                $leaveBalanceLastMonth = \App\Models\Backend\HrLeaveBalance::where("user_id", $user->id)->orderBy("id", "desc");
                $leaveDetail = \App\Models\Backend\HrLeaveBal::where("user_id", $user->id)->where("month", $lastMonth)->orderBy("id", "desc");
                if ($leaveBalanceLastMonth->count() > 0 && $leaveDetail->count() > 0) {
                    $leaveBalanceLastMonth = $leaveBalanceLastMonth->first();
                    //showArray($leaveBalanceLastMonth);     
                    //  echo $user->id;
                    $leaveDetail = $leaveDetail->first();

                    $tCO = $leaveBalanceLastMonth->co + $leaveDetail->total_holiday;

                    if ($month == '08' && $user->user_type != 1) {
                        $CL = $leaveBalanceLastMonth->cl;
                        $LA = $leaveBalanceLastMonth->la;
                        $lastyearLeave = $LA;
                        $LA = ($lastyearLeave > 45) ? 45 : $lastyearLeave;
                        $CL = $CL + 1.5;
                        //$LA = $leaveBalanceLastMonth->la;
                        $CO = $tCO;
                    } else if ($month != '08' && $user->user_type != 1) {
                        $CL = $leaveBalanceLastMonth->cl + 1.5;
                        $LA = $leaveBalanceLastMonth->la;
                        $CO = $tCO;
                    } else if ($month == '08' && $user->user_type == 1) {
                        $CL = $leaveBalanceLastMonth->cl;
                        $LA = $leaveBalanceLastMonth->la;
                        if ($CL > 8) {
                            $CL = 8;
                        }
                        $lastyearLeave = $CL + $LA;
                        $LA = ($lastyearLeave > 45) ? 45 : $lastyearLeave;
                        $CL = 18;
                        $CO = $leaveBalanceLastMonth->co;
                    } else if ($month != '08' && $user->user_type == 1) {
                        $CL = $leaveBalanceLastMonth->cl;
                        $LA = $leaveBalanceLastMonth->la;
                        $CO = $tCO;
                    }
                    if ($leaveDetail->total_leave > 0) {
                        $totalLeave = $tCO + $CL + $LA;
                        $coLeave = $tCO - $leaveDetail->total_leave;
                        if ($leaveDetail->total_leave > $totalLeave) {
                            $CL = $CO = $LA = 0;
                        } else if ($coLeave >= 0) {
                            $CL = $CL;
                            $CO = $coLeave;
                            $LA = $LA;
                        } else {
                            $clLeave = ($CL) - abs($coLeave);
                            if ($clLeave >= 0) {
                                $CL = ($clLeave > 0) ? $clLeave : 0;
                                $CO = ($coLeave > 0) ? $coLeave : 0;
                                $LA = $LA;
                            } else {
                                $laLeave = $LA - abs($clLeave);
                                if ($laLeave >= 0) {
                                    $CL = ($clLeave > 0) ? $clLeave : 0;
                                    $CO = ($coLeave > 0) ? $coLeave : 0;
                                    $LA = $laLeave;
                                }
                            }
                        }
                    }/* else {
                      if ($user->user_type != 1) {
                      $CL = $leaveBalanceLastMonth->cl + 1.5;
                      $LA = $leaveBalanceLastMonth->la;
                      $CO = $tCO;
                      } else {
                      $CL = $leaveBalanceLastMonth->cl;
                      $CO = $tCO;
                      $LA = $leaveBalanceLastMonth->la;
                      }
                      } */

                    $currentMonth = \App\Models\Backend\HrLeaveBalance::where("user_id", $user->id)->where("month", $lastMonth);
                    //echo getSQL($currentMonth);
                    if ($currentMonth->count() > 0) {
                        $currentMonth = $currentMonth->first();
                        \App\Models\Backend\HrLeaveBalance::where("id", $currentMonth->id)->update(["cl" => $CL,
                            "co" => $CO,
                            "la" => $LA]);
                    } else {
                        \App\Models\Backend\HrLeaveBalance::create([
                            'user_id' => $user->id,
                            "user_bio_id" => $user->user_bio_id,
                            "month" => $lastMonth,
                            "cl" => $CL,
                            "co" => $CO,
                            "la" => $LA,
                            "created_on" => date("Y-m-d H:i:s"),
                            "created_by" => 1
                        ]);
                    }
                }
            }
        }
        /* } catch (Exception $ex) {
          $cronName = "HR Bio Time";
          $message = $ex->getMessage();
          cronNotWorking($cronName, $message);
          } */
    }

}
