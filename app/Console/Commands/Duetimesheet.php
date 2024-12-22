<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class Duetimesheet extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timesheet:due';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'If timesheet not fill by eod then notification should be goes to user';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $url = config('constant.url.base');
            $exclueUserForTimesheet = \App\Models\Backend\Constant::select('constant_value')->where('constant_name', 'NOT_INCLUDE_FILL_TIMESHEET')->get();
            $todayDate = date('Y-m-d');

            $hrDetail = \App\Models\Backend\HrDetail::select('u.userfullname', 'u.email', 'hr_detail.id', 'punch_in', 'punch_out', 'working_time')->where('date', $todayDate)->join('user as u', 'u.id', '=', 'hr_detail.user_id');

            if ($exclueUserForTimesheet[0]->constant_value != '') {
                $userId = explode(",", $exclueUserForTimesheet[0]->constant_value);
                $hrDetail = $hrDetail->whereNotIn('user_id', $userId);
            }

            $hrDetail = $hrDetail->get()->toArray();
            $hrDetail_id = array();
            foreach ($hrDetail as $keyHrDetail => $valueHrDetail) {
                $hrDetail_id[] = $valueHrDetail['id'];
            }

            $missTimesheet = \App\Models\Backend\Timesheet::whereIn('hr_detail_id', $hrDetail_id)->where('date', $todayDate)->groupBy('user_id')->pluck('hr_detail_id', 'user_id')->toArray();

            $notFilltimesheet = array_diff($hrDetail_id, $missTimesheet);
            if (count($notFilltimesheet) > 0) {
                $allowStaffForTimesheet = \App\Models\User::whereRaw('((leave_allow = 13 AND location_id = 7) OR (location_id != 7 AND leave_allow != 13))')->get()->pluck('id', 'id')->toArray();
                $hrDetail = \App\Models\Backend\HrDetail::select('u.userfullname', 'u.email', 'punch_in', 'punch_out', 'working_time', 'hr_detail.id', 'hr_detail.user_id', 'user_id')->leftjoin('user as u', 'u.id', '=', 'hr_detail.user_id')->whereIn('hr_detail.id', $notFilltimesheet)->where('date', $todayDate)->where('working_time', '>', '00:30:00')->get()->toArray();
                if (count($hrDetail) > 0) {
                    $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('MISSTIMESHEETNOTIFICATION');
                    if ($emailTemplate->is_active == 1) {
                        $find = array('USERNAME', 'FULLDATE', 'HREF');
                        $hrUserPendingTimesheet = array();
                        foreach ($hrDetail as $key => $value) {
                            if (in_array($value['user_id'], $allowStaffForTimesheet)) {
                                $rawUrl = array('id' => $value['id']);
                                $queryString = urlEncrypting($rawUrl);
                                $linkHref = $url . "hrms/attendance-summary/user-pending-timesheet?" . $queryString;

                                $data = array();
                                $replace = array($value['userfullname'], date('jS M Y l', strtotime($todayDate)), $linkHref);
                                $data['to'] = $value['email'];
                                $data['cc'] = $emailTemplate->cc;
                                $data['bcc'] = $emailTemplate->bcc;
                                $data['subject'] = str_replace('DATE', date('d-m-Y', strtotime($todayDate)), $emailTemplate->subject);
                                $data['content'] = str_replace($find, $replace, $emailTemplate->content);
                                storeMail($request = null, $data);

                                $data = array();
                                $data['hr_detail_id'] = $value['id'];
                                $data['user_id'] = $value['user_id'];
                                $data['stage_id'] = 0;
                                $data['date'] = $todayDate;
                                $hrUserPendingTimesheet[] = $data;
                            }
                        }
                        \App\Models\Backend\PendingTimesheet::insert($hrUserPendingTimesheet);
                    }
                }
            }
        } catch (Exception $ex) {
            $cronName = "Due Timesheet";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
