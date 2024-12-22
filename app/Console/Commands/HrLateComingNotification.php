<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrLateComingNotification extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:latecomingnotification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Staff come late then notify to associated staff';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $hrDetail = \App\Models\Backend\HrDetail::with('assignee:id,userfullname,email')->where('daily_email_send', 0)->where('remark', 3)->get()->toArray();

            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('HALFDAYNOTIFICATION');
            $url = config('constant.url.base');
            if (!empty($hrDetail) && $emailTemplate->is_active == 1) {
                foreach ($hrDetail as $key => $value) {
                    $table = '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                    $table .= '<tr>
                        <th>Date</th>
                        <th>Punch Time</th>
                        <th>Remark</th>
                    </tr>';
                    $table .= '<tr>';
                    $table .= '<td>' . dateFormat($value['date']) . '</td>';
                    $table .= '<td align="center">' . date('g:i: A', strtotime($value['punch_in'])) . '</td>';
                    $table .= '<td>Late coming</td>';
                    $table .= '</tr>';
                    $table .= '</table></div>';

                    $rawUrl = array('remark' => $value['remark'], 'user_id' => $value['assignee']['id'], 'view' => 'attendanceSummaryReport');
                    $queryString = urlEncrypting($rawUrl);
                    $hrefLink = $url . "hrms/attendance-summary?" . $queryString;
                    
                    $find = array('[TABLE-ACTION]', 'USERNAME', 'REMARKTYPE', 'HREFLINK');
                    $replace = array($table, $value['assignee']['userfullname'], 'late coming', $hrefLink);
                    $data['to'] = $value['assignee']['email'];
                    $data['cc'] = $emailTemplate->cc;
                    $data['bcc'] = $emailTemplate->bcc;
                    $data['subject'] = str_replace('REMARKTYPE', 'late coming', $emailTemplate->subject);
                    $data['content'] = str_replace($find, $replace, $emailTemplate->content);
                    storeMail('', $data);
                    
                    \App\Models\Backend\HrDetail::where('id', $value['id'])->update(['daily_email_send' => 1]);
                }
            }
        } catch (Exception $ex) {
            $cronName = "HR Late Coming Notification";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}