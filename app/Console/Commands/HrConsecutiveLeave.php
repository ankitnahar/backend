<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrConsecutiveLeave extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:ConsecutiveLeave';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update and add staff consecutive leave counter and date';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {

            $today = date("Y-m-d", strtotime(date('Y-m-d') . " -1 Day "));
            $hrDetail = \App\Models\Backend\HrDetail::where('date', $today)->get();
            if (isset($hrDetail) && !empty($hrDetail)) {
                foreach ($hrDetail as $hrDetailTemp) {

                    $isSundayOrHoliday = todayisSundayOrHoliday($today, $hrDetailTemp->shift_id);
                    if (in_array($isSundayOrHoliday, ['Sunday', 'Holiday'])) {
                        continue;
                    }
                    $totalDay = '';
                    $late_coming_date = $updateLeavedata = array();
                    $getUser = \App\Models\User::select('consucative_leave', 'consucative_leave_date', 'leave_allow')->find($hrDetailTemp->user_id);
                    if ($hrDetailTemp->hr_final_remark == 3 && $hrDetailTemp->punch_in ==null) {
                        $late_coming_date = ($getUser->consucative_leave_date != '') ? explode(',', $getUser->consucative_leave_date) : array();
                        $late_coming_date[] = $hrDetailTemp->date;

                        $totalDay = $getUser->consucative_leave + 1;
                        //if ($getUser->leave_allow == 0) {
                        $updateLeavedata['consucative_leave'] = $totalDay;
                        $updateLeavedata['consucative_leave_date'] = implode(',', $late_coming_date);
                        \App\Models\User::where('id', $hrDetailTemp->user_id)->update($updateLeavedata);
                        //}
                    } else {
                        if ($hrDetailTemp->hr_final_remark != 3) {
                            $updateLeavedata['consucative_leave'] = 0;
                            $updateLeavedata['consucative_leave_date'] = '';
                            \App\Models\User::where('id', $hrDetailTemp->user_id)->update($updateLeavedata);
                        }
                    }
                }
            }

            $userData = \App\Models\User::with('firstApproval:id,userfullname,email','secondApproval:id,userfullname,email')
                    ->select('id', 'email', 'userfullname', 'first_approval_user','second_approval_user', 'consucative_leave', 'consucative_leave_date', 'leave_allow')
                    ->where('consucative_leave', '>', 3)->where('is_active', 1)->where("send_email","1")->get();

            $consecutiveData = $userName = $userList = array();
            foreach ($userData as $key => $value) {
                if ($value->consucative_leave >= 3 && $value->consucative_leave_date != '') {
                    if (isset($value->firstApproval->id)) {
                        $consecutiveData[$value->firstApproval->id]['leave'][] = $value;
                        // $userList[$value->user_id] = $value->user_email;
                    }
                }

                if (isset($value->firstApproval->id))
                    $userName[$value->firstApproval->id] = array($value->firstApproval->userfullname, $value->firstApproval->email);
            }

            $html = '';
            $consecutiveHRmail = $html;
            $j = 1;
            $consecutiveHRmail .= '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
            $consecutiveHRmail .= '<tr><th>Sr.no</th><th>Staff name</th><th>First Approval Name</th><th>No of days</th></tr>';
            if (!empty($consecutiveData)) {
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('CONSUCATIVELEAVE');
                foreach ($consecutiveData as $keyll => $valuell) {
                    $content = '';
                    $mailsent = array();
                    foreach ($valuell as $keyMail => $valueMail) {
                        $consecutive = '';
                        $isStaff = array();
                        $consecutive .= '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                        $consecutive .= '<tr><th>Sr.no</th><th>Staff name</th><th>No of days</th></tr>';
                        $trtd = '';
                        $i = 1;
                        foreach ($valueMail as $keyuserData => $valueuserData) {
                            //if ($valueuserData->leave_allow == 0) {
                            $label = 'consucative_' . $keyMail;
                            $trtd .= '<tr>';
                            $trtd .= '<td>' . $i . '</td>';
                            $trtd .= '<td>' . $valueuserData->userfullname . '</td>';
                            $trtd .= '<td>' . $valueuserData->$label . '</td>';
                            $trtd .= '</tr>';
                            $firstApprovalname = isset($valueuserData->firstApproval->userfullname) ? $valueuserData->firstApproval->userfullname : '-';
                            $consecutiveHRmail .= '<tr>';
                            $consecutiveHRmail .= '<td>' . $j . '</td>';
                            $consecutiveHRmail .= '<td>' . $valueuserData->userfullname . '</td>';
                            $consecutiveHRmail .= '<td>' . $firstApprovalname . '</td>';
                            $consecutiveHRmail .= '<td>' . $valueuserData->$label . '</td>';
                            $consecutiveHRmail .= '</tr>';
                            $i++;
                            $j++;
                            $isStaff[] = 1;
                            //}
                        }
                        $consecutive .= $trtd;
                        $consecutive .= '</tbody>';
                        $consecutive .= '</table>';
                        if (in_array(1, $isStaff)) {
                            $mailsent[] = $consecutive;
                        }
                    }

                    /* if (count($mailsent) > 0) {
                      $content = $html;
                      foreach ($mailsent as $keymailContent => $valuemailContent) {
                      $content .= $valuemailContent;
                      }

                      $data = array();
                      if ($keyll != '') {
                      $data['to'] = (isset($userName[$keyll][1]) && $userName[$keyll][1] != '') ? $userName[$keyll][1] : 'hr@befree.com.au,salary@befree.com.au';
                      $data['subject'] = $emailTemplate->subject;
                      $data['cc'] = $emailTemplate->cc != '' ? $emailTemplate->cc : '';
                      $data['bcc'] = $emailTemplate->bcc != '' ? $emailTemplate->bcc : '';
                      $data['content'] = html_entity_decode(str_replace(array('[USERNAME]', '[TABLE-ACTION]'), array($userName[$keyll][0], $content), $emailTemplate->content));
                      storeMail('', $data);
                      }
                      } */
                }

                $hrData = array();
                /* Final mail send to hr */
                $consecutiveHRmail .= '</tbody>';
                $consecutiveHRmail .= '</table>';
                $hrData['to'] = 'hr@befree.com.au';
                $hrData['subject'] = $emailTemplate->subject;
                $hrData['cc'] = $emailTemplate->cc != '' ? 'manish.p@befree.com.au,' . $emailTemplate->cc : 'manish.p@befree.com.au';
                $hrData['bcc'] = $emailTemplate->bcc != '' ? $emailTemplate->bcc : '';
                $hrData['content'] = html_entity_decode(str_replace(array('[USERNAME]', '[TABLE-ACTION]'), array('HR Team', $consecutiveHRmail), $emailTemplate->content));
                storeMail('', $hrData);
            }
        } catch (Exception $ex) {
            $cronName = "HR Consecutive Leave";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
