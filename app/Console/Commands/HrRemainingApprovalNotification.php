<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrRemainingApprovalNotification extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:remainingapprovalnotification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notification sent to staff if approval pending';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $firstDayofMonth = 01; //date("d");
            if ($firstDayofMonth == "01") {

                $hrDetail = \App\Models\Backend\HrDetail::with('assignee:id,userfullname,email')->where('monthly_email_send', 0)->whereIn('status', [2, 3, 4])->where(app('db')->raw("DATE_FORMAT(date, '%Y-%m')"), date('Y-m', strtotime(date("Y-m-d") . ' -1 month')));
                if ($hrDetail->count() == 0) {
                    goto end;
                } else {
                    $hrStatus = convertcamalecasetonormalcase(config('constant.hrstatus'));
                    $hrRemark = convertcamalecasetonormalcase(config('constant.hrRemark'));
                    $hrDetail = $hrDetail->get()->toArray();
                    $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('REMAININGAPPROVALNOTIFICATION');
                    $remainingApproval = $hrDetailId = array();
                    foreach ($hrDetail as $keyRemainApproval => $valueRemainApproval)
                        $remainingApproval[$valueRemainApproval['user_id']][] = $valueRemainApproval;

                    if ($emailTemplate->is_active == 1) {
                        foreach ($remainingApproval as $key => $value) {
                            $table = '';
                            $table .= '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                            $table .= '<tr>';
                            $table .= '<th>Date</th>';
                            $table .= '<th>Remark</th>';
                            $table .= '<th>Status</th>';
                            $table .= '</tr>';
                            for ($i = 0; $i < count($value); $i++) {
                                $remark = $value[$i]['remark'] != 0?$hrRemark[$value[$i]['remark']]:'-';
                                $status = $value[$i]['status'] != 0?$hrStatus[$value[$i]['status']]:'-';
                                
                                $table .= '<tr>';
                                $table .= '<td>' . dateFormat($value[$i]['date']) . '</td>';
                                $table .= '<td>'.$remark.'</td>';
                                $table .= '<td>'.$status.'</td>';
                                $table .= '</tr>';
                            }
                            $table .= '</div></table>';

                            $find = array('USERNAME', '[TABLE-ACTION]');
                            $replace = array($value[0]['assignee']['userfullname'], $table);
                            $data['to'] = $value[0]['assignee']['email'];
                            $data['cc'] = $emailTemplate->cc;
                            $data['bcc'] = $emailTemplate->bcc;
                            $data['subject'] = $emailTemplate->subject;
                            $data['content'] = str_replace($find, $replace, $emailTemplate->content);
                            $hrDetailId[] = $value[0]['id'];
                            storeMail('', $data);
                        }
                    }
                    $updateData['monthly_email_send'] = 1;
                    \App\Models\Backend\HrDetail::whereIn('id', $hrDetailId)->update($updateData);
                }
                end:
            }
        } catch (Exception $ex) {
           $cronName = "HR Remaning Approval Notification";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
