<?php

namespace App\Console\Commands;

use App\User;
use App\DripEmailer;
use Illuminate\Console\Command;
use Exception;

class HrMonthlyAttendanceSummary extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hr:monthlyattendancesummary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monthly attendance summary send to staff';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $yearMonth = date('Y-m', strtotime('last month'));
            $userList = \App\Models\User::select('first_approval_user', 'id')->where('first_approval_user', '!=', 0)->get();
            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('MONTHLYREPORTATTENDANCE');
            if ($emailTemplate->is_active == 0)
                return;

            $userId = $userList->pluck('id', 'id')->toArray();

            $firstApprovalUser = array();
            foreach ($userList as $key => $value) {
                $firstApprovalUser[$value->first_approval_user][] = $value->id;
                $firstApprovalPerson[] = $value->first_approval_user;
            }

            $firstApprovalUserRawData = \App\Models\User::select('id', 'user_bio_id', 'userfullname', 'email')->whereIn('id', $firstApprovalPerson)->where('is_active', 1)->get()->toArray();
            $firstApprovalUserArrangeData = array();
            foreach ($firstApprovalUserRawData as $keyFirstApproval => $valueFirstApproval)
                $firstApprovalUserArrangeData[$valueFirstApproval['id']] = $valueFirstApproval;

            $hrfinalRemark = convertcamalecasetonormalcase(config('constant.hrfinalRemark'));
            $hrRemark = convertcamalecasetonormalcase(config('constant.hrRemark'));
            $hrStatus = convertcamalecasetonormalcase(config('constant.hrstatus'));

            $hrDetail = \App\Models\Backend\HrDetail::with('hrDetailId:id,hr_detail_id,status,type,comment', 'shift_id:id,shift_name', 'assignee:id,userfullname,user_bio_id')->where(app('db')->raw('DATE_FORMAT(date, "%Y-%m")'), $yearMonth)->whereIn('status', [2, 3, 4, 5, 6])->whereIn('user_id', $userId)->get()->toArray();

            $days = $hrDetailId = array();
            foreach ($hrDetail as $keyHrDetail => $valueDetail) {
                $days[$valueDetail['user_id']][] = $valueDetail;
                $hrDetailId[] = $valueDetail['id'];
            }

            $timesheetUnit = array();
            if (count($hrDetailId) > 0)
                $timesheetUnit = \App\Models\Backend\Timesheet::select(app('db')->raw('SUM(units) AS totalUnit'), 'hr_detail_id')->whereIn('hr_detail_id', $hrDetailId)->groupBy('hr_detail_id')->pluck('totalUnit', 'hr_detail_id')->toArray();

            if (!empty($days)) {
                foreach ($firstApprovalUser as $keyDay => $valueDay) {
                    $data = $columnData = array();
                    $i = 1;
                    $data[0][] = 'Sr.No';
                    $data[0][] = 'Staff name';
                    $data[0][] = 'Shift name';
                    $data[0][] = 'Date';
                    $data[0][] = 'Working time';
                    $data[0][] = 'Break time';
                    $data[0][] = 'Units';
                    $data[0][] = 'First approval comment';
                    $data[0][] = 'Second approval comment';
                    $data[0][] = 'Status';
                    $data[0][] = 'Remark';
                    $data[0][] = 'Final remark';
                    foreach ($valueDay as $keyFirstApprovalUser => $valueFirstApprovalUser) {
                        if (isset($days[$valueFirstApprovalUser])) {
                            foreach ($days[$valueFirstApprovalUser] as $keyDays => $valueDays) {
                                $columnData[] = $i;
                                $columnData[] = $valueDays['assignee']['userfullname'];
                                $columnData[] = $valueDays['shift_id']['shift_name'];
                                $columnData[] = dateFormat($valueDays['date']);
                                $columnData[] = $valueDays['working_time'];
                                $columnData[] = $valueDays['break_time'];
                                $columnData[] = isset($timesheetUnit[$valueDays['id']])?$timesheetUnit[$valueDays['id']]:0;
                                $columnData[] = isset($valueDays['hr_detail_id'][0]['comment']) ? $valueDays['hr_detail_id'][0]['comment'] : '-';
                                $columnData[] = isset($valueDays['hr_detail_id'][1]['comment']) ? $valueDays['hr_detail_id'][1]['comment'] : '-';
                                $columnData[] = isset($hrStatus[$valueDays['status']]) ? $hrStatus[$valueDays['status']] : '-';
                                $columnData[] = isset($hrRemark[$valueDays['remark']]) ? $hrRemark[$valueDays['remark']] : '-';
                                $columnData[] = isset($hrfinalRemark[$valueDays['final_remark']]) ? $hrfinalRemark[$valueDays['final_remark']] : '-';
                                $data[] = $columnData;
                                $columnData = array();
                                $i++;
                            }
                        }
                    }

                    if (isset($firstApprovalUserArrangeData[$keyDay]['email'])) {
                        $monthYear = date('M-Y', strtotime($yearMonth));
                        app('excel')->create('Monthly attendance ' . $monthYear . '-' . $firstApprovalUserArrangeData[$keyDay]['userfullname'] . '-' . $firstApprovalUserArrangeData[$keyDay]['user_bio_id'], function($excel) use ($data) {
                            $excel->sheet('Monthly Request', function($sheet) use ($data) {
                                $sheet->cell('A1:L1', function($cell) {
                                    $cell->setFontColor('#ffffff');
                                    $cell->setBackground('#0c436c');
                                });

                                $sheet->getAllowedStyles();
                                $sheet->fromArray($data, null, 'A1', false, false);
                            });
                        })->store('xlsx', storage_path('templocation/monthlyAttendanceReport'));
                        $monthlyReportAttechment = storage_path('templocation/monthlyAttendanceReport/Monthly attendance ' . date('M-Y', strtotime($yearMonth)) . '-' . $firstApprovalUserArrangeData[$keyDay]['userfullname'] . '-' . $firstApprovalUserArrangeData[$keyDay]['user_bio_id'] . '.xlsx');


                        $dataEmail['to'] = $firstApprovalUserArrangeData[$keyDay]['email'];
                        $dataEmail['cc'] = $emailTemplate->cc;
                        $dataEmail['bcc'] = $emailTemplate->bcc;
                        $dataEmail['subject'] = $emailTemplate->subject;
                        $dataEmail['attachment'] = array($monthlyReportAttechment);
                        $dataEmail['content'] = str_replace(array('USERNAME', '[MONTH-YEAR]'), array($firstApprovalUserArrangeData[$keyDay]['userfullname'], $monthYear), $emailTemplate->content);
                        storeMail('', $dataEmail);
                    }
                }
            }
        } catch (Exception $ex) {
            $cronName = "HR Monthly Attendance Summary";
            $message = $ex->getMessage();
            cronNotWorking($cronName,$message);
        }
    }

}
