<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrPunchBetween8PMTO4AM extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:PunchBetween8PMto4AM';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send intimation email to HR if punch in between 8PM to 4AM';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $today = date("Y-m-d") . ' 04:30:00';
            $yesterday = date("Y-m-d", strtotime($today . ' -1 day')) . ' 20:00:00';

            $punchInOut = app('db')->select("SELECT hr_user_in_out_time.*, u.userfullname FROM hr_user_in_out_time LEFT JOIN user AS u ON u.id = user_id WHERE CONCAT(DATE,' ',punch_time) BETWEEN '" . $yesterday . "' AND '" . $today . "' ORDER BY userfullname, punch_time DESC");
            if (!empty($punchInOut)) {
                $table = '<div class="table_template">';
                $table .= '<table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                $table .= '<tr>';
                $table .= '<th>Username</th>';
                $table .= '<th>Date</th>';
                $table .= '<th>Punch action</th>';
                $table .= '<th>Punch time</th>';
                $table .= '</tr>';
                foreach ($punchInOut as $key => $value) {
                    $type = $value->punch_type == 1?'IN':'OUT';
                    $table .= '<tr>';
                    $table .= '<td>'.$value->userfullname.'</td>';
                    $table .= '<td>'.dateFormat($value->date).'</td>';
                    $table .= '<td>'.$type.'</td>';
                    $table .= '<td>'.date('g:i A', strtotime($value->punch_time)).'</td>';
                    $table .= '</tr>';
                }
                $table .= '</table></div>';
                
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('PUNCHBETWEEN8PMT430AM');
                if($emailTemplate->is_active == 1){
                    $data['to'] = $emailTemplate->cc;
                    $data['bcc'] = $emailTemplate->bcc;
                    $data['subject'] = $emailTemplate->subject;
                    $data['content'] = str_replace('TABLE-CONTENT', $table, $emailTemplate->content);
                    storeMail('', $data);
                }
            }
        } catch (Exception $ex) {
            $cronName = "HR Punch Between 8 to 4";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
