<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DB;
use Exception;
class WriteoffBefreeCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'writeoff:befree';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Writeoff Befree';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $startDt = date("Y-m-d", mktime(0, 0, 0, date("m") - 1, 1));
            $endDt = date("Y-m-d", mktime(0, 0, 0, date("m"), 0));


            $nonCharge = \App\Models\Backend\Timesheet::leftjoin("user as u", "u.id", "timesheet.user_id")
                            ->leftjoin("user_hierarchy as uh", "uh.user_id", "timesheet.user_id")
                            ->leftjoin("worksheet as w", "w.id", "timesheet.worksheet_id")
                            ->select(DB::raw("SUM(timesheet.units) AS noncharge"), "timesheet.user_id")
                            ->whereBetween("timesheet.date", [$startDt, $endDt])
                            ->where("u.is_active", "1")
                            ->where("w.master_activity_id", "3")
                            ->whereIn("w.entity_id", [1410, 1412, 1414, 1416, 1418, 385, 1126, 2620, 3320, 3554])
                            ->whereIn("uh.designation_id", [22, 9, 10])
                            ->where("u.user_writeoff", "!=", "100")
                            ->whereRaw("FIND_IN_SET(1,uh.team_id)")
                            ->where("timesheet.subactivity_code", "!=", "1020")
                            ->groupBy("timesheet.user_id")
                            ->get()->pluck('noncharge', 'user_id')->toArray();

            $totalUnits = \App\Models\Backend\Timesheet::leftjoin("user as u", "u.id", "timesheet.user_id")
                    ->leftjoin("user_hierarchy as uh", "uh.user_id", "timesheet.user_id")
                    ->select(DB::raw("ROUND(SUM(timesheet.units)*(u.user_writeoff/100)) as total,SUM(timesheet.units) as total_unit,timesheet.user_id", "u.user_writeoff"), "u.email", "u.userfullname")
                    ->whereBetween("timesheet.date", [$startDt, $endDt])
                    ->where("u.is_active", "1")
                    ->whereIn("uh.designation_id", [22, 9, 10])
                    ->where("u.user_writeoff", "!=", "100")
                    ->whereRaw("FIND_IN_SET(1,uh.team_id)")
                    ->where("timesheet.subactivity_code", "!=", "1020")
                    ->groupBy("timesheet.user_id")
                    ->get();
            //showArray($nonCharge);
            //showArray($totalUnits);exit;
            $userArray = array();
            $TAM = $DVI = array();
            $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", "BEW")->first();
            $sDate = date('d-m-Y', strtotime($startDt));
            $eDate = date('d-m-Y', strtotime($endDt));
            foreach ($totalUnits as $row) {
                if (!isset($nonCharge[$row->user_id])) {
                    continue;
                }
                if ($nonCharge[$row->user_id] > $row->total) {

                    $user = getUserDetails($row->user_id);

                    $befreeArray[] = array(
                        "from_date" => $startDt,
                        "to_date" => $endDt,
                        "user_id" => $row->user_id,
                        "technical_account_manager" => isset($user[$row->user_id][9]) ? $user[$row->user_id][9] : 0,
                        "division_head" => isset($user[$row->user_id][15]) ? $user[$row->user_id][15] : 0,
                        "total_unit" => $row->total_unit,
                        "nonchargeable_unit" => $nonCharge[$row->user_id],
                        "writeoff_percentage" => $row->user_writeoff,
                        "created_on" => date('Y-m-d H:i:s')
                    );
                    if (isset($user[$row->user_id][9]))
                        $TAM[$user[$row->user_id][9]][] = $row->userfullname;
                    if (isset($user[$row->user_id][15]))
                        $TAM[$user[$row->user_id][15]][] = $row->userfullname;

                    // send mail to user

                    if ($emailTemplate->is_active) {
                        $to = $row->email;
                        $cc = $emailTemplate->event_cc;
                        $subject = html_entity_decode(str_replace(array('[PERIOD]'), array($sDate . ' - ' . $eDate), $emailTemplate->subject));
                        $content = html_entity_decode(str_replace(array('[USERNAME]', '[PERIOD]', '[TOTUNIT]', '[NONUNIT]', '[HERE]'), array($row->userfullname, $sDate . ' - ' . $eDate, $row->total_unit, $nonCharge[$row->user_id], 'befreecrm.com.au'), $emailTemplate->content));


                        $emailData['to'] = strtolower($to);
                        $emailData['cc'] = strtolower($cc);
                        $emailData['bcc'] = strtolower($emailTemplate->bcc);
                        $emailData['subject'] = $subject;
                        $emailData['content'] = $content;

                        $sendMail = cronStoreMail($emailData);
                        if (!$sendMail) {
                            app('log')->channel('befreewriteoff')->error("Befree Writeoff user mail send failed");
                        }
                    }
                }
            }
            //showArray($TAM);exit;
            if(!empty($befreeArray))
                \App\Models\Backend\WriteoffBefree::insert($befreeArray);
            
            
            $emailTemp = \App\Models\Backend\EmailTemplate::where("code", "BEWTM")->first();
            
            //send mail to tam and division head
            foreach ($TAM as $key => $value) {
                
                $user = \App\Models\User::select("userfullname","email")->where("id",$key)->first();
                
                $to = $user->email;
                $cc = $emailTemp->event_cc;
                $subject = $emailTemp->subject;
                $table = '<div class="table_template">
            <table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                foreach($value as $val){
                    $table .= '<tr><td>'.$val.'</td></tr>';
                }
                $table .= '</table>';
                $content = html_entity_decode(str_replace(array('[USERNAME]', '[USERLIST]'), array($user->userfullname, $table), $emailTemp->content));


                $emailData['to'] = strtolower($to);
                $emailData['cc'] = strtolower($cc);
                $emailData['bcc'] = strtolower($emailTemplate->bcc);
                $emailData['subject'] = $subject;
                $emailData['content'] = $content;

                $sendMail = cronStoreMail($emailData);
                if (!$sendMail) {
                    app('log')->channel('befreewriteoff')->error("Befree Writeoff user mail send failed");
                }
            }
        } catch (Exception $e) {
            $cronName = "Write Off Befree";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
