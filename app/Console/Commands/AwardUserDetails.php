<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class AwardUserDetails extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'award:user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'add all user details';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //try {
            $todayDate = date('Y-m-d');
            if (date('d') == '21') {
               echo $monthYear = date('M-Y', strtotime("-1 month"));
                 $startDate = date('Y-m-01', strtotime("-1 month"));
                 $endDate = date('Y-m-d', strtotime("last day of previous month"));                
                 //$sixmonthdate = date("Y-m-d", strtotime("-6 months"));
                 $hrstartDate = date('Y-m-26', strtotime("-2 month", strtotime($todayDate)));
                $hrendDate = date('Y-m-25', strtotime($startDate));
                $hrleaveCalculation = \App\Models\Backend\HrDetail::select('hr_detail.user_id', app('db')
                        ->raw('SUM(CASE WHEN (hr_final_remark=0) THEN 1 ELSE 0 END) AS days'), app('db')->raw('SUM(CASE WHEN ((remark != 1 and remark != 2) and hr_final_remark=1) THEN 0.5 ELSE 0 END) AS half_day_prsent'))
                ->whereRaw("hr_detail.date >= '" . $hrstartDate . "' and hr_detail.date <= '" . $hrendDate . "'")
                        ->whereRaw("hr_detail.punch_in IS NOT NULL and hr_detail.punch_out IS NOT NULL")
                        ->groupBy("hr_detail.user_id")->get();
                $hrLeave = array();
                foreach($hrleaveCalculation as $hr){
                   $hrLeave[$hr->user_id] =  $hr->days + $hr->half_day_prsent;
                }                
                $issueticketDetails = \App\Models\Backend\Ticket::leftjoin("ticket_assignee as ta", "ta.ticket_id", "ticket.id")
                        ->select("ta.ticket_assignee", app('db')->raw("count(ticket.type_id) as issueTicket"))
                        ->whereRaw("DATE(ticket.created_on) >= '".$startDate."' and DATE(ticket.created_on) <= '".$endDate."'")
                        ->where("ticket.type_id", 1)
                        ->groupBy("ta.ticket_assignee")->get()->pluck("issueTicket","ticket_assignee")->toArray();
                //showArray($issueticketDetails);
                $apprisalticketDetails = \App\Models\Backend\Ticket::leftjoin("ticket_assignee as ta", "ta.ticket_id", "ticket.id")
                        ->select("ta.ticket_assignee", app('db')->raw("count(ticket.type_id) as apprisalTicket"))
                        ->whereRaw("DATE(ticket.created_on) >= '".$startDate."' and DATE(ticket.created_on) <= '".$endDate."'")
                        ->where("ticket.type_id", 7)
                        ->groupBy("ta.ticket_assignee")->get()->pluck("apprisalTicket","ticket_assignee")->toArray();
                //showArray($apprisalticketDetails);exit;
                $userDetails = \App\Models\User::leftjoin("hr_leave_bal as hb", "hb.user_id", "user.id")
                        ->leftjoin("user_hierarchy as uh", "uh.user_id", "user.id")
                        ->select("uh.user_id", "uh.designation_id", "uh.department_id","uh.parent_user_id","hb.*")
                        ->where("user.is_active", "1")
                        ->where("uh.designation_id","!=",7)
                        //->where("user.user_joining_date", "<=", $sixmonthdate)
                        ->whereRaw("hb.start_date >= '" . $hrstartDate . "' and hb.end_date <= '" . $hrendDate . "'")
                        ->groupBy("user.id")
                        ->get();
                foreach ($userDetails as $u) {
                    if($u->user_id ==0)
                        continue;
                    echo $u->user_id;
                    $issueTicket = isset($issueticketDetails[$u->user_id]) ? $issueticketDetails[$u->user_id] : 0;
                    $apprisalTicket = isset($apprisalticketDetails[$u->user_id]) ? $apprisalticketDetails[$u->user_id] : 0;
                    $userArray = '';
                    $manager = $tl = '';
                    if($u->parent_user_id != 0){                    
                    $userHierarchy = getUserHierarchyDetails($u->user_id);                   
                    $userArray = implode(",",array_reverse($userHierarchy['userArray'], true));
                    $manager =$userHierarchy['manager'];
                    $tl = $userHierarchy['tl'];
                    }
                    $presentDay = isset($hrLeave[$u->user_id]) ? $hrLeave[$u->user_id] : 0;
                    \App\Models\Backend\AwardUserDetails::create([
                        "user_id" => $u->user_id,
                        "month" => $monthYear,
                        "designation_id" => $u->designation_id,
                        "department_id" => $u->department_id,
                        "present_day" => $presentDay,
                        "absent" => $u->leave,
                        "issue_ticket" => $issueTicket,
                        "apprisal_ticket" => $apprisalTicket,
                        "team_detail" => $userArray,
                        "assign_manager" => $manager,
                        'tl' => $tl,
                        "created_on" => date("Y-m-d")
                    ]);
                }
            }
        /*} catch (Exception $ex) {
            $cronName = "Award user Deatils";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }*/
    }

}
