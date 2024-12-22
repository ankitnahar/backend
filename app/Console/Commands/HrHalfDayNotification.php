<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrHalfDayNotification extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:halfdaynotification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Half day notification go to hr';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $yesterday = date('Y-m-d', strtotime(date('Y-m-d') . '-1 day'));
            $hrDetail = \App\Models\Backend\HrDetail::with('assignee:id,userfullname,email')->whereIn('remark', [3, 4])->where('status', 2)->where('date', $yesterday);


            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('HALFDAYNOTIFICATION');
            $hrDetail = $hrDetail->get();

            if (count($hrDetail) > 0 && $emailTemplate->is_active == 1) {
                $hrDetailId = $hrDetail->pluck('id', 'id')->toArray();
                $timesheetUnit = \App\Models\Backend\Timesheet::whereIn('hr_detail_id', $hrDetailId)->pluck('units', 'hr_detail_id')->toArray();
                $hrRemark = convertcamalecasetonormalcase(config('constant.hrRemark'));
                $url = config('constant.url.base');
                
                foreach ($hrDetail as $key => $value) {
                    $timesheeetUnit = !empty($timesheetUnit) && isset($timesheetUnit[$value['id']]) ? $timesheetUnit[$value['id']] : 0;
                    $table = '';
                    $table .= '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                    $table .= '<tr>
                        <th>Date</th>
                        <th>Punch in</th>
                        <th>Working time</th>
                        <th>Unit</th>
                        <th>Remark</th>
                    </tr>';
                    $table .= '<tr>
                        <td>' . dateFormat($value['date']) . '</td>
                        <td>' . $value['punch_in'] . '</td>
                        <td>' . $value['working_time'] . '</td>
                        <td>' . $timesheeetUnit . '</td>
                        <td>' . $hrRemark[$value['remark']] . '</td>
                    </tr>';
                    $table .= '</table></div>';

                    $rawUrl = array('remark' => $value['remark'], 'user_id' => $value['assignee']['id'], 'view' => 'attendanceSummaryReport');
                    $queryString = urlEncrypting($rawUrl);
                    $hrefLink = $url . "hrms/attendance-summary?" . $queryString;

                    $data['to'] = $value['assignee']['email'];
                    $data['cc'] = $emailTemplate->cc;
                    $data['bcc'] = $emailTemplate->bcc;
                    $data['subject'] = str_replace('REMARKTYPE', $hrRemark[$value['remark']], $emailTemplate->subject);
                    $data['content'] = str_replace(array('USERNAME', '[TABLE-ACTION]', 'REMARKTYPE', 'HREFLINK'), array($value['assignee']['userfullname'], $table, $hrRemark[$value['remark']], $hrefLink), $emailTemplate->content);
                    storeMail('', $data);
                }
            }
        } catch (Exception $ex) {
           $cronName = "HR Half Day Notification";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
