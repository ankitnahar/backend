<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrDetail;
use App\Models\Backend\PendingTimesheet;
use App\Models\Backend\Timesheet;

//use confi
//use App\Models\User;

class AttendanceController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Fetch attendance summary data
     */

    public function summary(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'sortOrder' => 'in:asc,desc',
            'pageNumber' => 'numeric|min:1',
            'recordsPerPage' => 'numeric|min:0',
            'search' => 'json'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        // define soring parameters
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_detail.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        $teamUserRight = checkButtonRights(157, 'attendancesummaryteamright');
        $allUserRight = checkButtonRights(157, 'attendancesummaryallright');
        $userList = $userId = array();
        $isSingle = 0;
        if ($allUserRight == 1) {
            //$userData = \App\Models\User::where('is_active', 1)->select(app('db')->raw('id as user_id'), 'userfullname')->get()->toArray();
            $userData = \App\Models\User::select(app('db')->raw('id as user_id'), 'userfullname', 'location_id', 'leave_allow')->get()->toArray();
        } else if ($teamUserRight == 1) {
            $id = app('auth')->guard()->id();
            $userData = \App\Models\User::select(app('db')->raw('id as user_id'), 'userfullname', 'location_id', 'leave_allow')->where('first_approval_user', $id)->Orwhere('second_approval_user', $id)->Orwhere('id', $id)->get()->toArray();
            $userId[] = $id;
            $userList[] = array('id' => $id, 'userfullname' => app('auth')->guard()->user()->userfullname);
        } else {
            $id = app('auth')->guard()->id();
            $userData = \App\Models\User::select(app('db')->raw('id as user_id'), 'userfullname', 'location_id', 'leave_allow')->where('first_approval_user', $id)->Orwhere('second_approval_user', $id)->Orwhere('id', $id)->get()->toArray();
            $userId[] = $id;
            $userList[] = array('id' => $id, 'userfullname' => app('auth')->guard()->user()->userfullname);
            //$userData[] = array('user_id' => app('auth')->guard()->id(), 'userfullname' => app('auth')->guard()->user()->userfullname);
            //$isSingle = 1;
        }

        foreach ($userData as $key => $value) {
            $value = (array) $value;
            if (!in_array($value['user_id'], $userId)) {
                // Work from home user
                if (isset($value['location_id']) && $value['location_id'] == 7) {
                    $userId[] = $value['user_id'];
                    $userList[] = array('id' => $value['user_id'], 'userfullname' => $value['userfullname']);
                }
                // Regular user
                else if ($isSingle == 1 || (isset($value['location_id']) && $value['location_id'] != 7)) {
                    $userId[] = $value['user_id'];
                    $userList[] = array('id' => $value['user_id'], 'userfullname' => $value['userfullname']);
                }
            }
        }

        // It will be manage by condition after front end development start
        $attendance = HrDetail::select('hr_detail.*','h.location_name', app('db')                
                ->raw('CAST(TIMEDIFF((TIMEDIFF(hr_detail.shift_to_time,hr_detail.shift_from_time)),hr_detail.allow_break) AS TIME) AS shiftTime,(SELECT SUM(units) FROM timesheet WHERE hr_detail_id = `hr_detail`.`id` ) AS total_timesheet_unit'))->with('assignee:id,userfullname,user_bio_id,user_image,first_approval_user,second_approval_user')
                ->leftjoin("hr_location as h","h.id","hr_detail.office_location")
                ->with('shift_id:id,shift_name')
                ->with('firstApproval')
                ->with('secondApproval');
        if ($allUserRight != 1) {
            $attendance = $attendance->with('rejectionDetail')->whereIn('hr_detail.user_id', $userId);
        }
        
        if ($sortBy == 'shift_id') {
            $attendance = $attendance->leftjoin("hr_shift_master as hsm", "hsm.id", "hr_detail.$sortBy");
            $sortBy = 'shift_name';
        }

        if ($sortBy == 'user_id') {
            $attendance = $attendance->leftjoin("user as u", "u.id", "hr_detail.$sortBy");
            $sortBy = 'userfullname';
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array("status" => "hr_detail");
            $attendance = search($attendance, $search,$alias);
        }
        $attendance = $attendance->whereIn('hr_detail.status', [1, 2, 3, 4, 5, 6, 7, 0, NULL]);
        $attendance = $attendance->orderBy(app('db')->raw('FIELD(hr_detail.status,4,3,2,1,5,6,0,NULL), hr_detail.date'), 'DESC');

        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $attendance = $attendance->orderBy(app('db')->raw('FIELD(hr_detail.status,4,3,2,1,5,6,0,NULL), hr_detail.date'), 'DESC')->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $attendance->count();
            //$attendance = $attendance->orderBy($sortBy, $sortOrder)
            $attendance = $attendance->skip($skip)->take($take);

            //echo $attendance->toSql(); die;
            $attendance = $attendance->get();
            $filteredRecords = count($attendance);

            $pager = ['sortBy' => $request->get('sortBy'),
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        //$attendance = HrDetail::arrangeData($attendance);            

        if ($request->has('excel') && $request->get('excel') == 1) {
            $status = convertcamalecasetonormalcase(config('constant.hrstatus'));
            $remark = convertcamalecasetonormalcase(config('constant.hrRemark'));
            $finalremark = convertcamalecasetonormalcase(config('constant.hrfinalRemark'));
            $data = $attendance->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Bio Metric ID', 'Staff name', 'Office Location','Shift Name', 'Date', 'First In time', 'Last Out time', 'Working time', 'Break time', 'Units', 'Late coming reason', 'Remark', 'First approval', 'First approval comment', 'Second approval', 'Second approval comment', 'Status', 'Final Remark'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $value) {
                    $columnData[] = $i;
                    $columnData[] = $value['assignee']['user_bio_id'];
                    $columnData[] = $value['assignee']['userfullname'];
                    $columnData[] = $value['location_name'];
                    $columnData[] = $value['shift_id']['shift_name'];
                    $columnData[] = $value['date'];
                    $columnData[] = $value['punch_in'];
                    $columnData[] = $value['punch_out'];
                    $columnData[] = $value['working_time'];
                    $columnData[] = $value['break_time'];
                    $columnData[] = $value['total_timesheet_unit'];
                    $columnData[] = isset($value['reason']) ? $value['reason'] : '-';
                    $columnData[] = $value['remark'] != 0 ? $remark[$value['remark']] : '-';
                    //$columnData[] = isset($value['first_approval']['comment_by']['userfullname']) ? $value['first_approval']['comment_by']['userfullname'] : '-';
                    $columnData[] = isset($value['assignee']['first_approval']['userfullname']) ? $value['assignee']['first_approval']['userfullname'] : '-';
                    $columnData[] = isset($value['first_approval']['comment']) ? $value['first_approval']['comment'] : '-';
                    //$columnData[] = isset($value['second_approval']['comment_by']['userfullname']) ? $value['second_approval']['comment_by']['userfullname'] : '-';
                    $columnData[] = isset($value['assignee']['second_approval']['userfullname']) ? $value['assignee']['second_approval']['userfullname'] : '-';
                    $columnData[] = isset($value['second_approval']['comment']) ? $value['second_approval']['comment'] : '-';
                    $columnData[] = $value['status'] != 0 ? $status[$value['status']] : '-';
                    $columnData[] = $value['final_remark'] != '' ? $finalremark[$value['final_remark']] : '-';
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'Attendance summary', 'xlsx', 'A1:S1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Attendance summary list.", ['data' => $attendance, 'userList' => $userList], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Attendance summary listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing attendance summary list", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 01, 2018
     * Purpose   : Fetch attance summary fields
     */

    public function summaryReport(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'sortOrder' => 'in:asc,desc',
            'pageNumber' => 'numeric|min:1',
            'recordsPerPage' => 'numeric|min:0',
            'search' => 'json'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        // define soring parameters
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_leave_bal.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];
// app('db')->raw('SUM(CASE WHEN remark=5 THEN 1 ELSE 0 END) AS absent'),

        $currentmonth = date('m');
        $month = $yearNew = 0;
        $day = date('d');
        $search = json_encode(array('dateformat' => array('year' => array('date' => date('Y')), 'month' => array('date' => date('m')))));
        if ($request->has('search')) {
            $decode = json_decode($request->get('search'), true);
            if (!isset($decode['dateformat']['yearmonth']) && !isset($decode['dateformat']['year']))
                $decode['dateformat']['year'] = array('date' => date('Y'));

            if (!isset($decode['dateformat']['yearmonth']) && !isset($decode['dateformat']['month']))
                $decode['dateformat']['month'] = array('date' => date('m'));

            if (!isset($decode['dateformat']['year']) && !isset($decode['dateformat']['month']) && !isset($decode['dateformat']['yearmonth']))
                $decode['dateformat']['yearmonth'] = array('date' => date('Y-m'));

            if (isset($decode['dateformat']['month']['date']) && isset($decode['dateformat']['year']->date) && $decode['dateformat']['month']['date'] != '' && $decode['dateformat']['year']['date'] != '')
                $duration = date('M', strtotime($decode['dateformat']['month']['date'])) . '-' . $decode['dateformat']['year']['date'];
            else {
                $duration = date('M-Y', strtotime($decode['dateformat']['yearmonth']['date']));
                $month = date('m', strtotime($decode['dateformat']['yearmonth']['date']));
                $yearNew = date('Y', strtotime($decode['dateformat']['yearmonth']['date']));
                unset($decode['dateformat']['yearmonth']['date']);
            }
            $search = json_encode($decode);
        }
        if ($month == $currentmonth && $day <= 28) {
            $today = date('Y-m-d');
            self::HRLeaveBal($today);
        }
        $dayd = date('d');
        if ($dayd == 31) {
            $monthDate = date('Y-' . $month . '-30');
        } else {
            $monthDate = date('Y-' . $month . '-d');
        }
        $currentYear = date('Y');

        if ($month == $currentmonth && $yearNew == $currentYear) {
            $startDate = date('Y-m-26', strtotime("-1 month", strtotime($monthDate)));
            $endDate = date('Y-m-25', strtotime($monthDate));
        } else if ($month == '01') {
            $startDate = date(($yearNew - 1) . '-m-26', strtotime("-1 month", strtotime($monthDate)));
            $endDate = date($yearNew . '-m-25', strtotime($monthDate));
        } else {
            $startDate = date($yearNew . '-m-26', strtotime("-1 month", strtotime($monthDate)));
            $endDate = date($yearNew . '-m-25', strtotime($monthDate));
        }
        $attendance = \App\Models\Backend\HrLeaveBal::with('assignee:id,userfullname,user_bio_id')->with('shift_id:id,shift_name')
                ->leftjoin("user as ud", "ud.id", "hr_leave_bal.user_id")
                ->select("ud.Entity", "hr_leave_bal.*", "hr_leave_bal.leave as userAbsent", "hr_leave_bal.holiday_working as userHolidayworking")
                ->whereRaw("hr_leave_bal.start_date  >='" . $startDate . "' and hr_leave_bal.end_date <= '" . $endDate . "'");

        if ($sortBy == 'shift_id') {
            $attendance = $attendance->leftjoin("hr_shift_master as hsm", "hsm.id", "hr_leave_bal.$sortBy");
            $sortBy = 'shift_name';
        }

        if ($sortBy == 'user_id') {
            $attendance = $attendance->leftjoin("user as u", "u.id", "hr_leave_bal.$sortBy");
            $sortBy = 'u.userfullname';
        }
        $alias = array('shift_id' => 'hr_leave_bal');

        $attendance = search($attendance, $search, $alias);

        $groupBy = ($request->has('groupBy')) ? $request->get('groupBy') : 'user_id';
        $attendance = $attendance->groupBy($groupBy);
        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $attendance = $attendance->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = count($attendance->get()->toArray());
            $attendance = $attendance->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);
           // echo $attendance->toSql();die;
            $attendance = $attendance->get();
            $filteredRecords = count($attendance);

            $pager = ['sortBy' => $request->get('sortBy'),
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        /* if ($month == $currentmonth && $day < 28) {
          $attendance = HrDetail::arrangeReportData($attendance, $duration);
          } */
        if ($request->has('excel') && $request->get('excel') == 1) {
            // $data = $attendance;
            $column = array();
            $column[] = ['Sr.No', 'Bio Metric ID', 'Staff name', 'Shift Name', 'Entity', 'Month-Year', 'Holiday Working', "Holiday Adjustment", "Total Holiday", 'Leave', 'Adjustment', "Total Leave", 'Reason',];
            if (!empty($attendance)) {
                $columnData = array();
                $i = 1;
                foreach ($attendance as $data) {
                    $columnData[] = $i;
                    $columnData[] = $data['assignee']['user_bio_id'];
                    $columnData[] = $data['assignee']['userfullname'];
                    $columnData[] = $data['shift_id']['shift_name'];
                    $columnData[] = $data['Entity'];
                    $columnData[] = date('M-Y', strtotime($duration));
                    $columnData[] = $data['userHolidayworking'] > 0 ? $data['userHolidayworking'] : '0';
                    $columnData[] = $data['holiday_adjustment'] > 0 ? $data['holiday_adjustment'] : '0';
                    $columnData[] = $data['total_holiday'] > 0 ? $data['total_holiday'] : '0';
                    $columnData[] = $data['userAbsent'] > 0 ? $data['userAbsent'] : '0';
                    $columnData[] = $data['adjustment'] > 0 ? $data['adjustment'] : '0';
                    $columnData[] = $data['total_leave'] > 0 ? $data['total_leave'] : '0';
                    $columnData[] = $data['reason'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'Attendance summary Report', 'xlsx', 'A1:M1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Attendance summary list.", ['data' => $attendance], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Attendance summary listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing attendance summary list", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 04, 2018
     * Purpose   : Fetch late coming data
     * @param  data array
     */

    public function updateAdjustment(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'adjustment' => 'required',
            'reason' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $hrLeaveBal = \App\Models\Backend\HrLeaveBal::where("id", $id)->first();

        if ($hrLeaveBal->adjustment > 0 && $hrLeaveBal->adjustment != null)
            return createResponse(config('httpResponse.UNPROCESSED'), "You can't adjust leave again.", ['error' => 'You can not adjust leave again']);
        $aL = $request->input('adjustment');
        if ($hrLeaveBal->leave >= $aL) {
            $balance = $hrLeaveBal->leave - $aL;
        }
        \App\Models\Backend\HrLeaveBal::where("id", $id)->update(
                ["adjustment" => $request->input('adjustment'),
                    "reason" => $request->input('reason'),
                    "total_leave" => $balance]);
        return createResponse(config('httpResponse.SUCCESS'), "Leave Adjustment Done.", ['Message' => 'Leave Adjustment Done']);
        /* } catch (\Exception $e) {
          app('log')->error("Update adjusment failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while update adjusment", ['error' => 'Server error.']);
          } */
    }

    public function uploadleavecsv(Request $request) {
         try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'upload' => 'required|mimes:csv,vnd.ms-excel,txt',
        ]);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $day = date('d');
        if ($day > 5 && $day < 25) {
            return createResponse(config('httpResponse.UNPROCESSED'), 'You can not update leave adjustment after 4th of every month', ['error' => 'You can not update leave adjustment after 4th of every month']);
        }
        //Get current user detail
        $loginUser = loginUser();

        $file = $request->file('upload');
        if (!empty($file)) {
            $filename = $file->getPathname();
            $row = 1;
            if (($handle = fopen($filename, "r")) !== FALSE) {
                $i = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $num = count($data);
                    $row++;
                    for ($c = 0; $c < $num; $c++) {
                        if ($c == 0) {
                            continue;
                        }
                        $user_bio_id = "";
                        if (isset($data[1])) {
                            $user_bio_id = rtrim($data[1]);
                            $userId = \App\Models\User::select("id")->where("user_bio_id", $user_bio_id)->first();
                        }
                        $crmonth = "";
                        if (isset($data[5])) {
                            $crmonth = rtrim($data[5]);
                        }
                        /* $userHolidayworking = "";
                          if (isset($data[2])) {
                          $userHolidayworking = rtrim($data[2]);
                          } */
                        $holiday_adjustment = "";
                        if (isset($data[7])) {
                            $holiday_adjustment = rtrim($data[7]);
                        }

                        $total_holiday = "";
                        if (isset($data[8])) {
                            $total_holiday = rtrim($data[8]);
                        }

                        $adjustment = "";
                        if (isset($data[10])) {
                            $adjustment = rtrim($data[10]);
                        }
                        $total_leave = "";
                        if (isset($data[11])) {
                            $total_leave = rtrim($data[11]);
                        }
                        $reason = "";
                        if (isset($data[12])) {
                            $reason = rtrim($data[12]);
                        }
                    }
                    if ($userId->id > 0) {
                        if ($crmonth != 'Month-Year') {
                            $crmonth = date('M-Y',strtotime("-1 month"));
                            $checkUser = \App\Models\Backend\HrLeaveBal::where("user_id", $userId->id)->where("month", $crmonth);

                            if ($checkUser->count() > 0) {
                                $checkUser = $checkUser->first();
                                \App\Models\Backend\HrLeaveBal::where("id", $checkUser->id)->update([
                                    'adjustment' => $adjustment,
                                    'reason' => $reason,
                                    'holiday_adjustment' => $holiday_adjustment,
                                    'total_holiday' => $total_holiday,
                                    'total_leave' => $total_leave,
                                    'modified_on' => date('Y-m-d H:i:s'),
                                    'modified_by' => $loginUser
                                ]);
                            }
                        }
                    }
                }
                fclose($handle);
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Leave Report has been added successfully.', ['message' => 'Leave Report has been added successfully.']);
         } catch (\Exception $e) {
          app('log')->error("Email List failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add leave excel.', ['error' => 'Could not add leave excel']);
          } 
    }

    public function latecomingException(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_detail.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $attendance = HrDetail::with('assignee:id,userfullname,user_bio_id')
                    ->with('shift_id:id,shift_name')
                    ->with('firstApproval')
                    ->with('secondApproval')
                    ->with('rejectionDetail');

            if ($sortBy == 'shift_id') {
                $attendance = $attendance->leftjoin("hr_shift_master as hsm", "hsm.id", "hr_detail.$sortBy");
                $sortBy = 'shift_name';
            }

            if ($sortBy == 'user_id') {
                $attendance = $attendance->leftjoin("user as u", "u.id", "hr_detail.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $attendance = search($attendance, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $attendance = $attendance->orderByRaw($sortBy . ' ' . $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $attendance->count();
                $attendance = $attendance->orderByRaw($sortBy . ' ' . $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $attendance->toSql(); die;
                $attendance = $attendance->get(['hr_detail.*']);
                $filteredRecords = count($attendance);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            //$attendance = HrDetail::arrangeData($attendance);
            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $attendance->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Bio Metric ID', 'Staff name', 'Shift Name', 'Date', 'First In time', 'Last Out time', 'Working time', 'Break time', 'Remark', 'First approval', 'First approval comment', 'Second approval', 'Second approval comment'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        if (isset($data['rejectionDetail'])) {
                            
                        }
                        $columnData[] = $i;
                        $columnData[] = $data['assignee']['user_bio_id'];
                        $columnData[] = $data['assignee']['userfullname'];
                        $columnData[] = $data['shift_id']['shift_name'];
                        $columnData[] = $data['date'];
                        $columnData[] = $data['punch_in'];
                        $columnData[] = $data['punch_out'];
                        $columnData[] = $data['working_time'];
                        $columnData[] = $data['break_time'];
                        $columnData[] = 'Remark data';
                        $columnData[] = isset($value['first_approval']['comment_by']['userfullname']) ? $value['first_approval']['comment_by']['userfullname'] : '-';
                        $columnData[] = isset($value['first_approval']['comment']) ? $value['first_approval']['comment'] : '-';
                        $columnData[] = isset($value['second_approval']['comment_by']['userfullname']) ? $value['second_approval']['comment_by']['userfullname'] : '-';
                        $columnData[] = isset($value['second_approval']['comment']) ? $value['second_approval']['comment'] : '-';
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Late coming exception', 'xlsx', 'A1:M1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Late coming exception list.", ['data' => $attendance], $pager);
        } catch (\Exception $e) {
            app('log')->error("Late coming exception list failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while Late coming exception list", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show user in-out timedata
     */

    public function show($id) {
        try {
            $hrDetail = HrDetail::with('shift_id:id,shift_name')
                            ->with('firstApproval')
                            ->with('secondApproval')
                            ->with('rejectionDetail')
                            ->with('timesheet')
                            ->with('inout')->where('id', $id)->get();
            if (empty($hrDetail->toArray()))
                return createResponse(config('httpResponse.NOT_FOUND'), 'User late coming detail not exist', ['error' => 'User late coming detail not exist']);

            //send user late coming information
            return createResponse(config('httpResponse.SUCCESS'), 'User late coming detail', ['data' => $hrDetail]);
        } catch (\Exception $e) {
            app('log')->error("User in-outt ime details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get late coming detail.', ['error' => 'Could not late coming detail.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 04, 2018
     * @param  $request array
     * Purpose: Send late coming approval to first approval
     */

    public function approvalRequest(Request $request, $id) {
        //try {
        $hrDetail = HrDetail::select('date', 'remark')->find($id);
        $url = config('constant.url.base');
        $status = convertcamalecasetonormalcase(config('constant.hrstatus'));
        $remark = convertcamalecasetonormalcase(config('constant.hrRemark'));

        $validator = app('validator')->make($request->all(), [
            'first_approval_email' => 'required|email',
            'first_approval_name' => 'required',
            'reason' => 'required'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $type = $hrDetail->remark;
        $reason = $request->get('reason');
        $userData = app('auth')->user();


        $name = $userData->userfullname;
        $first_approval_name = $request->get('first_approval_name');

        $notify_staff = '';
        if ($request->get('notify_staff') && $request->get('notify_staff') != '') {
            $userDetail = \App\Models\User::find($request->get('notify_staff'));
            $notify_staff = $userDetail->email;
        }
        /* Prepare request send staff mail */
        $userEmail = \App\Models\Backend\EmailTemplate::getTemplate('LATEREQUESTUSERMAIL');
        $requestStaff = array();
        $requestStaff['to'] = $userData->email;
        $requestStaff['from'] = '';
        $requestStaff['bcc'] = $userEmail->bcc;
        $requestStaff['subject'] = str_replace(array('REMARKTYPE', 'REQUEST_TYPE'), array(strtolower($remark[$type]), 'request'), $userEmail->subject);
        //echo $queryString = urlencode(base64_encode('remark')).'='.urlencode(base64_encode(3)).'&'.urlencode(base64_encode('month_year')).'='.urlencode(base64_encode('2019-03')).'&'.urlencode(base64_encode('view')).'='.urlencode(base64_encode('adminView'));

        $rawUrl = array('id' => $id, 'view' => 'email');
        $queryString = urlEncrypting($rawUrl);

        $linkHref = $url . "hrms/attendance-summary?" . $queryString;
        $requestStaff['content'] = html_entity_decode(str_replace(array('REMARKTYPE', 'REQUEST_TYPE', 'STAFF_NAME', 'USERNAME', 'REASON', 'DATE', 'HREFLINK'), array(strtolower($remark[$type]), 'request', $first_approval_name, $name, $reason, date("d-m-Y", strtotime($hrDetail->date)), $linkHref), $userEmail->content));
        $requestMailsent = storeMail($request, $requestStaff);

        /* Prepare first approval staff mail */
        $approvalStaffEmail = \App\Models\Backend\EmailTemplate::getTemplate('LATEAPPROVAL');
        $approvalStaff = array();
        $approvalStaff['to'] = $request->get('first_approval_email');
        //$approvalStaff['from'] = $userData->email;
        $approvalStaff['from'] = '';
        $approvalStaff['cc'] = $approvalStaffEmail->cc != '' ? $approvalStaffEmail->cc . ',' . $notify_staff : $notify_staff;
        $approvalStaff['bcc'] = $approvalStaffEmail->bcc;
        $approvalStaff['subject'] = str_replace(array('REMARKTYPE', 'USER_NAME'), array(strtolower($remark[$type]), $name), $approvalStaffEmail->subject);
        $approvalStaff['content'] = html_entity_decode(str_replace(array('REMARKTYPE', 'REQUEST_TYPE', 'STAFF_NAME', 'USER_NAME', 'REASON', 'DATE', 'HREFLINK', 'APPROVALPERSON'), array(strtolower($remark[$type]), 'request', $first_approval_name, $name, $reason, date('d-m-Y'), $linkHref, ''), $approvalStaffEmail->content));
        $approvalMailsent = storeMail($request, $approvalStaff);

        HrDetail::where('id', $id)->update(['status' => 3, 'reason' => $reason]);
        return createResponse(config('httpResponse.SUCCESS'), 'Approval has been sent', ['data' => 'Approval has been sent']);
        /* } catch (\Exception $e) {
          app('log')->error("Approval sent failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not sent approval.', ['error' => 'Could not sent approval.']);
          } */
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 11, 2018
     * @param  $request array
     * Purpose: approved request send by staff first & second approval will be used
     */

    public function approvedRequest(Request $request, $id) {
        //try {
        $statusLabel = convertcamalecasetonormalcase(config('constant.hrstatus'));
        $remarkLabel = convertcamalecasetonormalcase(config('constant.hrRemark'));

        $validator = app('validator')->make($request->all(), [
            'id' => 'required|numeric',
            'comment' => 'required',
            'approval_type' => 'required|in:1,2,3'], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $id = $request->get('id');
        $hrDetail = HrDetail::with('assignee:id,userfullname,user_bio_id,email,first_approval_user,second_approval_user')->with('firstApproval')->where('id', $id)->get()->toArray();
        $day = date("d");
        $date = $hrDetail[0]['date'];
        if ($day > 27) {
            $startDate = date('Y-m-26');
            $endDate = date('Y-m-25', strtotime("+1 month", strtotime($date)));
        } else {
            $startDate = date('Y-m-26', strtotime("-1 month", strtotime($date)));
            $endDate = date('Y-m-28');
        }
        if ($date >= $startDate && $date <= $endDate) {
            $remark = isset($remarkLabel[$hrDetail[0]['remark']]) ? $remarkLabel[$hrDetail[0]['remark']] : '';
            $status = strtolower($statusLabel[$request->get('status')]);
            $reason = $hrDetail[0]['reason'];
            $date = date('d-m-Y', strtotime($hrDetail[0]['date']));
            $notify_staff = '';
            if ($request->get('notify_staff') && $request->get('notify_staff') != '') {
                $userDetail = \App\Models\User::find($request->get('notify_staff'));
                $notify_staff = $userDetail->email;
            }

            $requester_name = $hrDetail[0]['assignee']['userfullname'];
            $requester_email = $hrDetail[0]['assignee']['email'];

            $first_approval_name = isset($hrDetail[0]['assignee']['first_approval']['userfullname']) ? $hrDetail[0]['assignee']['first_approval']['userfullname'] : '-';
            $first_approval_email = isset($hrDetail[0]['assignee']['first_approval']['email']) ? $hrDetail[0]['assignee']['first_approval']['email'] : '';
            $first_approval_comnt = isset($hrDetail[0]['first_approval']['comment']) ? $hrDetail[0]['first_approval']['comment'] : '-';

            $second_approval_name = isset($hrDetail[0]['assignee']['second_approval']['userfullname']) ? $hrDetail[0]['assignee']['second_approval']['userfullname'] : '';
            $second_approval_email = isset($hrDetail[0]['assignee']['second_approval']['email']) ? $hrDetail[0]['assignee']['second_approval']['email'] : '';

            $secondApproval = $ApprovedStatus = $rejectComment = '';
            $hrDetailData = array();
            // Checkout if request approved
            if ($request->get('status') == config('constant.hrstatus.approved')) {
                // If approved by supper admin superadminApproval
                if ($request->get('approval_type') == config('constant.approvaltype.superadminApproval')) {
                    $ApprovedStatus = ' approved by super admin';
                    /* Prepare first approval staff mail */
                    $approvalStaffEmail = \App\Models\Backend\EmailTemplate::getTemplate('LATESECONDAPPROVAL');
                    $approvalStaff = array();
                    $approvalStaff['to'] = $second_approval_email;
                    //$approvalStaff['from'] = $first_approval_email;
                    $approvalStaff['from'] = '';
                    $approvalStaff['cc'] = $approvalStaffEmail->cc != '' ? $approvalStaffEmail->cc . ',' . $notify_staff : $notify_staff;
                    $approvalStaff['bcc'] = $approvalStaffEmail->bcc;
                    $approvalStaff['subject'] = str_replace(array('REMARKTYPE', 'USER_NAME'), array($remark, $requester_name), $approvalStaffEmail->subject);
                    $comment = 'Supper admin comment : ' . $request->get('comment');
                    $searchArray = array('Please approve the ', 'REMARKTYPE', 'SECOND_STAFF', 'USER_NAME', 'REASON', 'DATE', 'STATUS', 'FIRST_SECOND_COMMENT');
                    $replaceArray = array('', $remark, $second_approval_name, $requester_name, $reason, $date, $ApprovedStatus, $comment);
                    $approvalStaff['content'] = html_entity_decode(str_replace($searchArray, $replaceArray, $approvalStaffEmail->content));
                    //storeMail($request, $approvalStaff);
                    if (in_array($request->get('finalremark'), [1, 2])) { // Full and half day
                        $hrDetailData['status'] = 5;
                        $hrDetailData['final_remark'] = $request->get('finalremark');
                    } else if ($request->get('finalremark') == 3) { // Early leaving
                        $hrDetailData['status'] = 2;
                        $hrDetailData['remark'] = 4;
                        $hrDetailData['final_remark'] = 0;
                        $hrDetailData['hr_final_remark'] = 0;
                    }
                    $secondApproval = '';
                }
                // If first approval approved
                else if ($request->get('approval_type') == config('constant.approvaltype.firstApproval')) {
                    $secondApproval = 'An approval from ' . $second_approval_name . ' is pending.';
                    $ApprovedStatus = ' approved by ' . $first_approval_name;
                    /* Prepare first approval staff mail */
                    $approvalStaffEmail = \App\Models\Backend\EmailTemplate::getTemplate('LATESECONDAPPROVAL');
                    $approvalStaff = array();
                    $approvalStaff['to'] = $second_approval_email;
                    //$approvalStaff['from'] = $first_approval_email;
                    $approvalStaff['from'] = '';
                    $approvalStaff['cc'] = $approvalStaffEmail->cc != '' ? $approvalStaffEmail->cc . ',' . $notify_staff : $notify_staff;
                    $approvalStaff['bcc'] = $approvalStaffEmail->bcc;
                    $approvalStaff['subject'] = str_replace(array('REMARKTYPE', 'USER_NAME'), array($remark, $requester_name), $approvalStaffEmail->subject);
                    $comment = 'First approval comment : ' . $request->get('comment');
                    $searchArray = array('REMARKTYPE', 'SECOND_STAFF', 'USER_NAME', 'REASON', 'DATE', 'STATUS', 'FIRST_SECOND_COMMENT');
                    $replaceArray = array($remark, $second_approval_name, $requester_name, $reason, $date, $ApprovedStatus, $comment);
                    $approvalStaff['content'] = html_entity_decode(str_replace($searchArray, $replaceArray, $approvalStaffEmail->content));
                    storeMail($request, $approvalStaff);
                    $hrDetailData['status'] = 4;
                    if ($second_approval_name == '') {
                        $hrDetailData['status'] = 5;
                        $hrDetailData['final_remark'] = 2;
                        $secondApproval = '';
                    }
                }
                // If second approval approved
                else if ($request->get('approval_type') == config('constant.approvaltype.secondApproval')) {
                    $ApprovedStatus = $ApprovedStatus = ' approved by ' . $second_approval_name;
                    $comment = 'First approval comment : ' . $first_approval_comnt;
                    $comment .= '<br>Second approval comment : ' . $request->get('comment') . '<br>';
                    $hrDetailData['status'] = 5;
                    $hrDetailData['final_remark'] = 2;
                }
            } else {
                $rejectorName = '';
                if ($request->get('approval_type') == config('constant.approvaltype.superadminApproval')) {
                    $rejectorName = 'super admin';
                } else if ($request->get('approval_type') == config('constant.approvaltype.firstApproval')) {
                    $rejectorName = $first_approval_name;
                } else if ($request->get('approval_type') == config('constant.approvaltype.secondApproval')) {
                    $rejectorName = $first_approval_name;
                }
                $ApprovedStatus = ' rejected by ' . $rejectorName;
                $rejectComment = 'Rejection comment : ' . $request->get('comment');
                $comment = '';
                $hrDetailData['status'] = 6;
                $hrDetailData['final_remark'] = 1;
            }

            /* Prepare request send staff mail */
            $userEmail = \App\Models\Backend\EmailTemplate::getTemplate('LATEAPPROVALRESPONSE');
            $requestStaff = array();
            $requestStaff['to'] = $requester_email;
            //$requestStaff['from'] = $first_approval_email;
            $requestStaff['from'] = '';
            $requestStaff['cc'] = $userEmail->cc != '' ? $userEmail->cc . ',' . $notify_staff : $notify_staff;
            $requestStaff['bcc'] = $userEmail->bcc;
            $requestStaff['subject'] = str_replace(array('REMARKTYPE', 'USER_NAME'), array(strtolower($remark), $requester_name), $userEmail->subject);
            $searchArray = array('REMARKTYPE', 'REQUEST_TYPE', 'SECOND_APPROVAL', 'USER_NAME', 'REASON', 'FIRST_SECOND_COMMENT', 'DATE', 'STATUS', 'REJECT_COMMENT');
            $replaceArray = array(strtolower($remark), 'reponse', $secondApproval, $requester_name, $reason, $comment, $date, $ApprovedStatus, $rejectComment);
            $requestStaff['content'] = html_entity_decode(str_replace($searchArray, $replaceArray, $userEmail->content));

            if ($request->get('approval_type') != config('constant.approvaltype.superadminApproval'))
                storeMail($request, $requestStaff);

            if (!empty($hrDetailData)) {
                HrDetail::where('id', $id)->update($hrDetailData);

                \App\Models\Backend\Hrdetailcomment::insert(['hr_detail_id' => $id,
                    'status' => $status,
                    'type' => $request->get('approval_type'),
                    'comment' => $request->get('comment'),
                    'comment_by' => app('auth')->id(),
                    'comment_on' => date('Y-m-d H:i:s')]);
            }
        } else {
            return createResponse(config('httpResponse.UNPROCESSED'), 'You can not add previous month data', ['error' => 'You can not add previous month data']);
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Request action has been done', ['data' => 'Request action has been done']);
        /* } catch (\Exception $e) {
          app('log')->error("Approval sent failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not approval done.', ['error' => 'Could not approval done.']);
          } */
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 24, 2018
     * Purpose   : Cron file to checkout whether timesheet is fillup or not
     */

    public function checkPendingtimesheet(Request $request) {

        $validator = app('validator')->make($request->all(), [
            'date' => 'required|date|date_format:Y-m-d',
                ], ['date.date_format' => 'The date format is not valid']);


        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $date = $request->get('date');
        $timesheet = Timesheet::select('hr_details_id', 'id')->where('date', $date)->get()->pluck('hr_details_id', 'id')->toArray();
        $hr_detail = HrDetail::select('id as hr_detail_id', 'user_id', 'date as date')
                        ->where('date', $date)
                        //->where('working_time', '>', '0')
                        ->whereNotin('id', $timesheet)->get()->toArray();

        $pendingTimesheet = new PendingTimesheet;
        $pendingTimesheet->insert($hr_detail);
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 24, 2018
     * Purpose   : Cron file to checkout whether timesheet is fillup or not
     */

    /* public function pendingTimesheet(Request $request) {
      try {
      //validate request parameters
      $validator = app('validator')->make($request->all(), [
      'sortOrder' => 'in:asc,desc',
      'pageNumber' => 'numeric|min:1',
      'recordsPerPage' => 'numeric|min:0',
      'search' => 'json'
      ], []);

      if ($validator->fails()) // Return error message if validation fails
      return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

      // define soring parameters
      $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
      $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
      $pager = [];

      $pendingTimesheet = PendingTimesheet::with('assignee:id,userfullname,user_bio_id')->select('user_id', app('db')->raw('GROUP_CONCAT(DATE) as date'), app('db')->raw('count(user_id) as total_days'));
      if ($sortBy == 'user_id') {
      $pendingTimesheet = $pendingTimesheet->leftjoin("user as u", "u.id", "hr_pendingtimesheet.$sortBy");
      $sortBy = 'userfullname';
      }

      // $search = '{"dateformat":{"year":{"date":"' . date('Y') . '"},"month":{"date":"' . date('m') . '"}}}';
      if ($request->has('search')) {
      $decode = json_decode($request->get('search'));
      if (!isset($decode->dateformat->yearmonth) && !isset($decode->dateformat->year))
      $decode->dateformat->year = array('date' => date('Y'));

      if (!isset($decode->dateformat->yearmonth) && !isset($decode->dateformat->month))
      $decode->dateformat->month = array('date' => date('m'));

      if (!isset($decode->dateformat->year) && !isset($decode->dateformat->month) && !isset($decode->dateformat->yearmonth))
      $decode->dateformat->yearmonth = array('date' => date('Y-m'));

      if (isset($decode->dateformat->month->date) && isset($decode->dateformat->year->date) && $decode->dateformat->month->date != '' && $decode->dateformat->year->date != '')
      $duration = date('M', strtotime($decode->dateformat->month->date)) . '-' . $decode->dateformat->year->date;
      else
      $duration = date('M-Y', strtotime($decode->dateformat->yearmonth->date));

      $search = json_encode($decode);
      }
      $pendingTimesheet = search($pendingTimesheet, $search);

      //            echo $pendingTimesheet->groupBy('user_id')->toSql();
      //            die;
      // Check if all records are requested
      if ($request->has('records') && $request->get('records') == 'all') {
      $pendingTimesheet = $pendingTimesheet->orderBy($sortBy, $sortOrder)->get();
      } else { // Else return paginated records
      // Define pager parameters
      $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
      $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
      $skip = ($pageNumber - 1) * $recordsPerPage;
      $take = $recordsPerPage;

      //count number of total records
      $totalRecords = count($pendingTimesheet->get());
      $pendingTimesheet = $pendingTimesheet->orderBy($sortBy, $sortOrder)
      ->skip($skip)
      ->take($take);
      //echo $pendingTimesheet->toSql(); die;
      $pendingTimesheet = $pendingTimesheet->get();
      $filteredRecords = count($pendingTimesheet);

      $pager = ['sortBy' => $sortBy,
      'sortOrder' => $sortOrder,
      'pageNumber' => $pageNumber,
      'recordsPerPage' => $recordsPerPage,
      'totalRecords' => $totalRecords,
      'filteredRecords' => $filteredRecords];
      }

      if ($request->has('excel') && $request->get('excel') == 1) {
      $data = $pendingTimesheet->toArray();
      $column = array();
      $column[] = ['Sr.No', 'Bio Metric ID', 'Staff name', 'Duration', 'Date', 'No fo days'];
      if (!empty($data)) {
      $columnData = array();
      $i = 1;
      foreach ($data as $value) {
      $columnData[] = $i;
      $columnData[] = $value['assignee']['user_bio_id'] != '' ? $value['assignee']['user_bio_id'] : '-';
      $columnData[] = $value['assignee']['userfullname'];
      $columnData[] = $value['date'];
      $columnData[] = $duration;
      $columnData[] = $value['total_days'];
      $column[] = $columnData;
      $columnData = array();
      $i++;
      }
      }
      return exportExcelsheet($column, 'Attendance summary', 'xlsx', 'A1:F1');
      }

      return createResponse(config('httpResponse.SUCCESS'), "Pending timesheet summary list.", ['data' => $pendingTimesheet], $pager);
      } catch (\Exception $e) {
      app('log')->error("Attendance summary listing failed : " . $e->getMessage());

      return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing pendign timesheet summary list", ['error' => 'Server error.']);
      }
      } */

    public function pendingTimesheet(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $pendingTimesheet = PendingTimesheet::select('hr_pendingtimesheet.*', app('db')->raw('(SELECT SUM(units) FROM timesheet WHERE hr_detail_id = `hr_pendingtimesheet`.`hr_detail_id` ) AS total_timesheet_unit'))->with('assignee:id,userfullname,user_bio_id,user_image,timesheet_approval_user', 'hrDetailId:id,punch_in,punch_out,working_time');

            $user = getLoginUserHierarchy();
            if ($user->designation_id != 7) {
                $id = app('auth')->guard()->id();
                //$userData = \App\Models\User::select('userfullname', 'id')->where('is_active', 1)->where('timesheet_approval_user', $id)->get()->toArray();
                $userData = \App\Models\User::select('userfullname', 'id')->where('timesheet_approval_user', $id)->get()->toArray();
                $ids[] = app('auth')->guard()->id();
                foreach ($userData as $key => $value) {
                    $ids[] = $value['id'];
                }

                $pendingTimesheet = $pendingTimesheet->whereIn('user_id', $ids);
                $userData[] = array('id' => $id, 'userfullname' => app('auth')->guard()->user()->userfullname);
            } else {
                $userData = \App\Models\User::select('userfullname', 'id')->where('is_active', 1)->get()->toArray();
            }

            if ($sortBy == 'user_id') {
                $pendingTimesheet = $pendingTimesheet->leftjoin("user as u", "u.id", "hr_pendingtimesheet.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $pendingTimesheet = search($pendingTimesheet, $search);
            }


            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $pendingTimesheet = $pendingTimesheet->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = count($pendingTimesheet->get());
                $pendingTimesheet = $pendingTimesheet->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $pendingTimesheet->toSql(); die;
                $pendingTimesheet = $pendingTimesheet->get(['hr_pendingtimesheet.*']);
                $filteredRecords = count($pendingTimesheet);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $pendingTimesheet->toArray();
                $stage = config('constant.pendingTimesheetStage');
                $column = array();
                $column[] = ['Sr.No', 'Bio ID', 'Staff name', 'Date', 'In time', 'Out time', 'Working time', 'Stage', 'Approval person', 'Approval Comment'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $value) {
                        $columnData[] = $i;
                        $columnData[] = $value['assignee']['user_bio_id'] != '' ? $value['assignee']['user_bio_id'] : '-';
                        $columnData[] = $value['assignee']['userfullname'];
                        $columnData[] = dateFormat($value['date']);
                        $columnData[] = $value['hr_detail_id']['punch_in'];
                        $columnData[] = $value['hr_detail_id']['punch_out'];
                        $columnData[] = $value['hr_detail_id']['working_time'];
                        $columnData[] = $stage[$value['stage_id']];
                        $columnData[] = isset($value['assignee']['timesheet_approval_user']) ? $value['assignee']['timesheet_approval_user']['userfullname'] : '-';
                        $columnData[] = $value['approval_comment'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Pending timesheet', 'xlsx', 'A1:J1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Pending timesheet summary list.", ['data' => $pendingTimesheet, 'userlist' => $userData], $pager);
        } catch (\Exception $e) {
            app('log')->error("Attendance summary listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing pendign timesheet summary list", ['error' => 'Server error.']);
        }
    }

    public function requestForApproval(Request $request, $id) {
        try {
            $url = config('constant.url.base');
            $emaiTemplate = \App\Models\Backend\EmailTemplate::getTemplate('MISSTIMESHEETSTAFFREQUEST');
            if (!empty($emaiTemplate)) {
                $data = array();
                $approvalUser = $request->get('approvalusername');
                $requestUser = $request->get('requestusername');
                $date = $request->get('date');

                $data['to'] = $request->get('approvalemail');
                $data['subject'] = $content = str_replace(array('STAFFNAME', 'DATE'), array($requestUser, date('d/m/Y', strtotime($date))), $emaiTemplate->subject);
                $find = array('STAFFNAME', 'USER_NAME', 'DATE', 'HREFLINK');
                $rawUrl = array('id' => $id, 'view' => 'email');
                $queryString = urlEncrypting($rawUrl);

                $linkHref = $url . "hrms/attendance-summary/user-pending-timesheet?" . $queryString;
                $replace = array($requestUser, $approvalUser, date('jS M Y l', strtotime($date)), $linkHref);
                $data['content'] = str_replace($find, $replace, $emaiTemplate->content);

                \App\Models\Backend\PendingTimesheet::where('id', $id)->update(['is_hide' => 0, "stage_id" => 2, 'approval_person' => $request->get('approvalperson_id')]);
                storeMail($request, $data);
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Request has been sent successfully', ['data' => 'Request has been sent successfully']);
        } catch (\Exception $e) {
            app('log')->error("Approval sent failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not approval done.', ['error' => 'Could not approval done.']);
        }
    }

    public function approved(Request $request, $id) {
        try {
            $url = config('constant.url.base');
            $comment = $request->get('approval_comment');
            $action = $request->get('action');

            $emaiTemplate = \App\Models\Backend\EmailTemplate::getTemplate('MISSTIMESHEETSTAFFREQUEST');
            $hrRemark = convertcamalecasetonormalcase(config('constant.hrRemark'));
            $hrDetail = PendingTimesheet::select(app('db')->raw('(SELECT SUM(units) from timesheet where hr_detail_id = hr_pendingtimesheet.hr_detail_id) as totalUnit'), app('db')->raw('hr_pendingtimesheet.*'))->with('hrDetailId:id,date,shift_id,remark,punch_in,punch_out,working_time', 'assignee:id,userfullname,email')->find($id);

            switch ($action) {
                case 1:
                    $checkForHolidayWorking = self::isHolidayOrNot($hrDetail->hrDetailId->date, $hrDetail->hrDetailId->shift_id);
                    $remark = 0;
                    if ($checkForHolidayWorking == 'Sunday') {
                        $remark = 2;
                    } else if ($checkForHolidayWorking == 'Holiday') {
                        $remark = 1;
                    }

                    $updateHrDetail = $hrDetailHistiory = array();
                    $hrDetailHistiory['hr_detail_id'] = $hrDetail->hrDetailId->id;
                    $hrDetailHistiory['date'] = $hrDetail->hrDetailId->date;
                    $hrDetailHistiory['user_id'] = $hrDetail->user_id;
                    $hrDetailHistiory['stage_id'] = 0;
                    $hrDetailHistiory['created_on'] = date("Y-m-d H:i:s");
                    $hrDetailHistiory['created_by'] = 1;

                    // Full working day scenario
                    if ((strtotime($hrDetail->hrDetailId->working_time) >= strtotime('08:00:00')) && ($hrDetail->totalUnit >= 80)) {
                        $updateHrDetail['remark'] = $remark;
                        $updateHrDetail['status'] = 0;
                        $updateHrDetail['final_remark'] = 2;
                    } else if ((strtotime($hrDetail->hrDetailId->working_time) >= strtotime('07:00:00')) && ($hrDetail->totalUnit >= 70)) { // Early leaving scenario
                        $updateHrDetail['remark'] = 4;
                        $updateHrDetail['status'] = 2;
                        $updateHrDetail['final_remark'] = 0;
                        $updateHrDetail['hr_final_remark'] = 0;
                        $template = \App\Models\Backend\EmailTemplate::getTemplate('HALFDAYNOTIFICATION');
                        if ($template->is_active == 1) {
                            $tblDesign = '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                            $tblDesign .= '<tr><th>Date</th><th>Punch in</th><th>Working time</th><th>Unit</th><th>Remark</th></tr>';
                            $tblDesign .= '<tr><td>' . dateFormat($hrDetail->hrDetailId->date) . '</td><td>' . $hrDetail->hrDetailId->punch_in . '</td><td>' . $hrDetail->hrDetailId->working_time . '</td><td>' . $hrDetail->totalUnit . '</td><td>' . $hrRemark[4] . '</td></tr>';
                            $tblDesign .= '</table></div>';

                            $rawUrl = array('id' => $id, 'view' => 'email');
                            $queryString = urlEncrypting($rawUrl);

                            $linkHref = $url . "hrms/attendance-summary?" . $queryString;
                            $data = array();
                            $data['to'] = $hrDetail->assignee->email;
                            $data['cc'] = $template->cc != "" ? $template->cc : '';
                            $data['subject'] = str_replace('REMARKTYPE', $hrRemark[4], $template->subject);
                            $findArray = array('USERNAME', '[TABLE-ACTION]', 'HREFLINK', 'REMARKTYPE');
                            $replaceArray = array($hrDetail->assignee->userfullname, $tblDesign, $linkHref, $hrRemark[4]);
                            $data['content'] = str_replace($findArray, $replaceArray, $template->content);
                            storeMail($request, $data);

                            $updateHrDetail['daily_email_send'] = 1;
                        }
                    } else if ((strtotime($hrDetail->hrDetailId->working_time) >= strtotime('04:00:00')) && ($hrDetail->totalUnit >= 40) && (strtotime($hrDetail->hrDetailId->working_time) < strtotime('07:00:00'))) { // Half day scenario
                        $updateHrDetail['remark'] = $remark;
                        $updateHrDetail['status'] = 0;
                        $updateHrDetail['final_remark'] = 1;
                    } else {
                        $updateHrDetail['remark'] = $remark;
                        $updateHrDetail['status'] = 0;
                        $updateHrDetail['final_remark'] = 3;
                        $updateHrDetail['is_exception'] = 1;
                    }

                    $data = array();
                    $template = \App\Models\Backend\EmailTemplate::getTemplate('MISSTIMESHEETAPPROVAL');
                    if ($template->is_active == 1) {
                        $data['to'] = $hrDetail->assignee->email;
                        $data['subject'] = str_replace('DATE', date('jS M Y l', strtotime($hrDetail->date)), $template->subject);
                        $rawUrl = array('id' => $id, 'stage_id' => 3, 'view' => 'email');
                        $queryString = urlEncrypting($rawUrl);

                        $linkHref = $url . "hrms/attendance-summary/user-pending-timesheet?" . $queryString;
                        $find = array('USER_NAME', 'STATUS', 'COMMENT', 'HREFLINK', 'DATE');
                        $replace = array($hrDetail->assignee->userfullname, 'approved', $comment, $linkHref, date('jS M Y l', strtotime($hrDetail->date)));
                        $data['content'] = str_replace($find, $replace, $template->content);
                    }


                    $updatePendingTimesheet['stage_id'] = 3;
                    $updatePendingTimesheet['approval_person'] = app('auth')->guard()->id();
                    $updatePendingTimesheet['approval_comment'] = $comment;
                    $hrDetail->where('id', $hrDetail->id)->update($updatePendingTimesheet);
                    HrDetail::where('id', $hrDetail->hrDetailId->id)->update($updateHrDetail);

                    $message = 'Miss timesheet has been approved';
                    break;
                case 0:
                    $data = array();
                    $template = \App\Models\Backend\EmailTemplate::getTemplate('MISSTIMESHEETAPPROVAL');

                    if ($template->is_active == 1) {
                        $data['to'] = $hrDetail->assignee->email;
                        $data['subject'] = str_replace('DATE', date('jS M Y l', strtotime($hrDetail->date)), $template->subject);
                        $rawUrl = array('id' => $id, 'stage_id' => 4, 'view' => 'email');
                        $queryString = urlEncrypting($rawUrl);
                        $linkHref = $url . "hrms/attendance-summary/user-pending-timesheet?" . $queryString;

                        $find = array('USER_NAME', 'STATUS', 'COMMENT', 'HREFLINK', 'DATE');
                        $replace = array($hrDetail->assignee->userfullname, 'approved', $comment, $linkHref, date('jS M Y l', strtotime($hrDetail->date)));
                        $data['content'] = str_replace($find, $replace, $template->content);
                        storeMail($request, $data);

                        $updatePendingTimesheet['stage_id'] = 4;
                        $updatePendingTimesheet['approval_person'] = app('auth')->guard()->id();
                        $updatePendingTimesheet['approval_comment'] = $comment;
                        $hrDetail->where('id', $hrDetail->id)->update($updatePendingTimesheet);
                    }
                    $message = 'Miss timesheet has been rejected';
                    break;
                default :
                    $message = 'Taken invalid action';
            }
            return createResponse(config('httpResponse.SUCCESS'), $message, ['data' => $message]);
        } catch (\Exception $e) {
            app('log')->error("Approval sent failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not done approval process.', ['error' => 'Could not done approval process.']);
        }
    }

    public static function isHolidayOrNot($date, $shift_id) {
        $isSunday = date('l', strtotime($date));
        $isHoliday = '';
        if ($isSunday == "Sunday") {
            $isHoliday = "Sunday";
        } else {
            $isRecord = \App\Models\Backend\HrHoliday::leftjoin('hr_holiday_detail AS hhd', 'hhd.hr_holiday_id', '=', 'hr_holiday.id')->where('date', $date)->where('hhd.shift_id', $shift_id)->count();
            if ($isRecord > 0) {
                $isHoliday = "Holiday";
            }
        }
        return $isHoliday;
    }

    public function followupMail(Request $request, $id) {
        try {
            $hrDetail = HrDetail::select('date', 'remark')->find($id);
            $url = config('constant.url.base');
            $status = convertcamalecasetonormalcase(config('constant.hrstatus'));
            $remarkOption = convertcamalecasetonormalcase(config('constant.hrRemark'));

            $validator = app('validator')->make($request->all(), [
                'approval_email' => 'required|email',
                'approval_name' => 'required',
                'reason' => 'required',
                'type' => 'required',
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $reason = $request->get('reason');
            $userData = app('auth')->user();
            $remark = $hrDetail->remark;
            $type = $request->get('type');

            $name = $userData->userfullname;
            $approval_name = $request->get('approval_name');
            $rawUrl = array('id' => $id, 'view' => 'email');
            $queryString = urlEncrypting($rawUrl);

            $linkHref = $url . "hrms/attendance-summary?" . $queryString;
            /* Prepare request send staff mail */
            /* $userEmail = \App\Models\Backend\EmailTemplate::getTemplate('LATEREQUESTUSERMAIL');
              $requestStaff = array();
              $requestStaff['to'] = $userData->email;
              $requestStaff['from'] = '';
              $requestStaff['bcc'] = $userEmail->bcc;
              $requestStaff['subject'] = str_replace(array('REMARKTYPE', 'REQUEST_TYPE'), array('early leaving', 'request'), $userEmail->subject);
              $rawUrl = array('id' => $id, 'view' => 'email');
              $queryString = urlEncrypting($rawUrl);

              $linkHref = $url . "hrms/attendance-summary?" . $queryString;
              $requestStaff['content'] = html_entity_decode(str_replace(array('REMARKTYPE', 'REQUEST_TYPE', 'STAFF_NAME', 'USERNAME', 'REASON', 'DATE', 'HREFLINK'), array(strtolower($remarkOption[$remark]), 'follow up', $approval_name, $name, $reason, dateFormat($hrDetail->date), $linkHref), $userEmail->content));
              $requestMailsent = storeMail($request, $requestStaff); */

            /* Prepare first approval staff mail */
            $approvalStaffEmail = \App\Models\Backend\EmailTemplate::getTemplate('LATEAPPROVAL');
            $approvalStaff = array();
            $approvalStaff['to'] = $request->get('approval_email');
            //$approvalStaff['from'] = $userData->email;
            $approvalStaff['from'] = '';
            //$approvalStaff['cc'] = $approvalStaffEmail->cc != '' ? $approvalStaffEmail->cc . ',' . $notify_staff : $notify_staff;
            $approvalStaff['bcc'] = $approvalStaffEmail->bcc;
            $approvalStaff['subject'] = str_replace(array('REMARKTYPE', 'USER_NAME'), array(strtolower($remarkOption[$remark]), $name), $approvalStaffEmail->subject);
            $approvalPerson = '';
            if ($type == 4) {
                $approvalPerson = '<p>First approval comment: ' . $request->get('first_approval_comment') . '</p>';
            }

            $approvalStaff['content'] = html_entity_decode(str_replace(array('REMARKTYPE', 'REQUEST_TYPE', 'STAFF_NAME', 'USER_NAME', 'REASON', 'DATE', 'HREFLINK', 'APPROVALPERSON'), array(strtolower($remarkOption[$remark]), 'request', $approval_name, $name, $reason . ' on ' . dateFormat($hrDetail->date), dateFormat($hrDetail->date), $linkHref, $approvalPerson), $approvalStaffEmail->content));

            $approvalMailsent = storeMail($request, $approvalStaff);

            return createResponse(config('httpResponse.SUCCESS'), 'Follow up has been sent successfully', ['data' => 'Follow up has been sent successfully']);
        } catch (\Exception $e) {
            app('log')->error("Approval sent failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not sent approval.', ['error' => 'Could not sent approval.']);
        }
    }

    public static function autoApprove($id) {
        $url = config('constant.url.base');
        $comment = 'Auto Approved';

        $emaiTemplate = \App\Models\Backend\EmailTemplate::getTemplate('MISSTIMESHEETSTAFFREQUEST');
        $hrRemark = convertcamalecasetonormalcase(config('constant.hrRemark'));
        $hrDetail = PendingTimesheet::leftjoin("hr_detail as h", "h.id", "hr_pendingtimesheet.hr_detail_id")
                        ->leftjoin("user as u", "u.id", "h.user_id")
                        ->leftjoin("timesheet as t", "t.hr_detail_id", "h.id")
                        ->select("h.*", "u.userfullname", "u.email", app('db')->raw("sum(t.units) as totalUnit"))
                        ->groupBy("t.hr_detail_id")->get();
        $hrDetail = $hrDetail[0];
        $checkForHolidayWorking = self::isHolidayOrNot($hrDetail->date, $hrDetail->shift_id);
        $remark = 0;
        if ($checkForHolidayWorking == 'Sunday') {
            $remark = 2;
        } else if ($checkForHolidayWorking == 'Holiday') {
            $remark = 1;
        }

        $updateHrDetail = $hrDetailHistiory = array();
        $hrDetailHistiory['hr_detail_id'] = $hrDetail->id;
        $hrDetailHistiory['date'] = $hrDetail->date;
        $hrDetailHistiory['user_id'] = $hrDetail->user_id;
        $hrDetailHistiory['stage_id'] = 0;
        $hrDetailHistiory['created_on'] = date("Y-m-d H:i:s");
        $hrDetailHistiory['created_by'] = 1;

        // Full working day scenario
        $timesheetUnit = $hrDetail->totalUnit;
        $updateHrDetail['unit'] = $timesheetUnit;

        if ($timesheetUnit >= 80) {
            $updateData['hr_final_remark'] = 0;
        } else if ($timesheetUnit < 80 && $timesheetUnit >= 70) {

            $updateData['hr_final_remark'] = 0;
        } else if (($timesheetUnit >= 40 && $timesheetUnit < 70)) {

            $updateData['hr_final_remark'] = 1;
        } else {
            if ($remark == 0) {
                $updateData['hr_final_remark'] = 3;
            } else {
                $updateData['hr_final_remark'] = 0;
            }
        }

        if ((strtotime($hrDetail->working_time) >= strtotime('08:00:00')) && ($hrDetail->totalUnit >= 80)) {
            $updateHrDetail['remark'] = $remark;
            $updateHrDetail['status'] = 0;
            $updateHrDetail['final_remark'] = 2;
        } else if ((strtotime($hrDetail->working_time) >= strtotime('07:00:00')) && ($hrDetail->totalUnit >= 70)) { // Early leaving scenario
            $updateHrDetail['remark'] = 4;
            $updateHrDetail['status'] = 2;
            $updateHrDetail['final_remark'] = 0;
            $template = \App\Models\Backend\EmailTemplate::getTemplate('HALFDAYNOTIFICATION');
            if ($template->is_active == 1) {
                $tblDesign = '<div class="table_template"><table style="font-size: 13px; width: 100%; text-align: left;  border-spacing: 0;   padding: 15px 0 15px 0;padding-bottom: 20px">';
                $tblDesign .= '<tr><th>Date</th><th>Punch in</th><th>Working time</th><th>Unit</th><th>Remark</th></tr>';
                $tblDesign .= '<tr><td>' . dateFormat($hrDetail->date) . '</td><td>' . $hrDetail->punch_in . '</td><td>' . $hrDetail->working_time . '</td><td>' . $hrDetail->totalUnit . '</td><td>' . $hrRemark[4] . '</td></tr>';
                $tblDesign .= '</table></div>';

                $rawUrl = array('id' => $id, 'view' => 'email');
                $queryString = urlEncrypting($rawUrl);

                $linkHref = $url . "hrms/attendance-summary?" . $queryString;
                $data = array();
                $data['to'] = $hrDetail->email;
                $data['cc'] = $template->cc != "" ? $template->cc : '';
                $data['subject'] = str_replace('REMARKTYPE', $hrRemark[4], $template->subject);
                $findArray = array('USERNAME', '[TABLE-ACTION]', 'HREFLINK', 'REMARKTYPE');
                $replaceArray = array($hrDetail->userfullname, $tblDesign, $linkHref, $hrRemark[4]);
                $data['content'] = str_replace($findArray, $replaceArray, $template->content);
                storeMail($request, $data);

                $updateHrDetail['daily_email_send'] = 1;
            }
        } else if ((strtotime($hrDetail->working_time) >= strtotime('04:00:00')) && ($hrDetail->totalUnit >= 40) && (strtotime($hrDetail->working_time) < strtotime('07:00:00'))) { // Half day scenario
            $updateHrDetail['remark'] = $remark;
            $updateHrDetail['status'] = 0;
            $updateHrDetail['final_remark'] = 1;
        } else {
            $updateHrDetail['remark'] = $remark;
            $updateHrDetail['status'] = 0;
            $updateHrDetail['final_remark'] = 3;
            $updateHrDetail['is_exception'] = 1;
        }

        $data = array();
        $template = \App\Models\Backend\EmailTemplate::getTemplate('MISSTIMESHEETAPPROVAL');
        if ($template->is_active == 1) {
            $data['to'] = $hrDetail->email;
            $data['subject'] = str_replace('DATE', date('jS M Y l', strtotime($hrDetail->date)), $template->subject);
            $rawUrl = array('id' => $id, 'stage_id' => 3, 'view' => 'email');
            $queryString = urlEncrypting($rawUrl);

            $linkHref = $url . "hrms/attendance-summary/user-pending-timesheet?" . $queryString;
            $find = array('USER_NAME', 'STATUS', 'COMMENT', 'HREFLINK', 'DATE');
            $replace = array($hrDetail->userfullname, 'approved', $comment, $linkHref, date('jS M Y l', strtotime($hrDetail->date)));
            $data['content'] = str_replace($find, $replace, $template->content);
        }


        $updatePendingTimesheet['stage_id'] = 3;
        $updatePendingTimesheet['approval_person'] = app('auth')->guard()->id();
        $updatePendingTimesheet['approval_comment'] = $comment;
        $hrDetail->where('id', $hrDetail->id)->update($updatePendingTimesheet);
        HrDetail::where('id', $hrDetail->id)->update($updateHrDetail);

        $message = 'Miss timesheet has been approved';
    }

    public static function updateRemarkTimesheetAdd($date, $userId) {
        $hrDetail = HrDetail::leftjoin("timesheet as t", "t.hr_detail_id", "hr_detail.id")
                        ->select(app('db')->raw("sum(units) as totalUnit"), "hr_detail.*")
                        ->where("hr_detail.user_id", $userId)->where("hr_detail.date", $date)->first();
        $checkForHolidayWorking = self::isHolidayOrNot($hrDetail->date, $hrDetail->shift_id);
        $remark = 0;
        if ($checkForHolidayWorking == 'Sunday') {
            $updateHrDetail['remark'] = 2;
            $remark = 2;
        } else if ($checkForHolidayWorking == 'Holiday') {
            $updateHrDetail['remark'] = 1;
            $remark = 1;
        }

        $updateHrDetail = $hrDetailHistiory = array();
        $hrDetailHistiory['hr_detail_id'] = $hrDetail->id;
        $hrDetailHistiory['date'] = $hrDetail->date;
        $hrDetailHistiory['user_id'] = $hrDetail->user_id;
        $hrDetailHistiory['stage_id'] = 0;
        $hrDetailHistiory['created_on'] = date("Y-m-d H:i:s");
        $hrDetailHistiory['created_by'] = 1;
        $timesheetUnit = $hrDetail->totalUnit;
        $updateHrDetail['unit'] = $timesheetUnit;
        $updateHrDetail['hr_final_remark'] = 3;
        if ($timesheetUnit >= 80) {
            $updateHrDetail['hr_final_remark'] = 0;
        } else if ($timesheetUnit < 80 && $timesheetUnit >= 70) {
            $updateHrDetail['hr_final_remark'] = 0;
        } else if (($timesheetUnit >= 40 && $timesheetUnit < 70)) {
            $updateHrDetail['hr_final_remark'] = 1;
        } else if ($remark == 0 && $timesheetUnit < 30) {
            $updateHrDetail['hr_final_remark'] = 3;
        } else if ($remark > 0){
            $updateHrDetail['hr_final_remark'] = 0;
        }
        // \App\Models\Backend\HrDetail::where("id",$hrDetail->id)->update($updateHrDetail);
        // Full working day scenario
        if ((strtotime($hrDetail->working_time) >= strtotime('08:00:00')) && ($hrDetail->totalUnit >= 80)) {
            //$updateHrDetail['remark'] = $remark;
            $updateHrDetail['status'] = 0;
            $updateHrDetail['final_remark'] = 2;
        } else if ((strtotime($hrDetail->working_time) >= strtotime('07:00:00')) && ($hrDetail->totalUnit >= 70)) { // Early leaving scenario
            $updateHrDetail['remark'] = 4;
            $updateHrDetail['status'] = 2;
            $updateHrDetail['final_remark'] = 0;
        } else if ((strtotime($hrDetail->working_time) >= strtotime('04:00:00')) && ($hrDetail->totalUnit >= 40) && (strtotime($hrDetail->working_time) < strtotime('07:00:00'))) { // Half day scenario
            //$updateHrDetail['remark'] = $remark;
            $updateHrDetail['status'] = 0;
            $updateHrDetail['final_remark'] = 1;
        } else {
            //$updateHrDetail['remark'] = $remark;
            $updateHrDetail['status'] = 0;
            $updateHrDetail['final_remark'] = 3;
            $updateHrDetail['is_exception'] = 1;
        }
        HrDetail::where('id', $hrDetail->id)->update($updateHrDetail);
    }

    public static function HRLeaveBal($today) {
        $userException = \App\Models\Backend\Constant::select('constant_value')->where('constant_name', 'NOT_INCLUDE_FILL_TIMESHEET')->get();
        $userExceptionId = explode(',', $userException[0]->constant_value);

        $startDate = date('Y-m-26', strtotime("-1 month", strtotime($today)));
        $endDate = date('Y-m-25', strtotime($today));
        $duration = date('M-Y', strtotime($endDate));
        //$startDate = '2022-01-26';
        //$endDate = '2022-02-25';

        $hrleaveCalculation = \App\Models\Backend\HrDetail::select('hr_detail.user_id', 'hr_detail.shift_id', app('db')
                        ->raw('SUM(CASE WHEN ((remark != 1 and remark != 2) and hr_final_remark=3) THEN 1 ELSE 0 END) AS absent'), app('db')->raw('SUM(CASE WHEN ((remark != 1 and remark != 2) and hr_final_remark=1) THEN 0.5 ELSE 0 END) AS half_day_absent'), app('db')->raw('SUM(CASE WHEN((remark = 1 OR remark = 2) AND unit >= 70) AND hr_final_remark = 0 THEN 1 ELSE 0 END) AS holiday_working'), app('db')->raw('SUM(CASE WHEN((remark = 1 OR remark = 2)  AND unit >= 40) AND hr_final_remark = 1 THEN 0.5 ELSE 0 END) AS half_holiday_working'))
                ->whereRaw("hr_detail.date >= '" . $startDate . "' and hr_detail.date <= '" . $endDate . "'");
        if (!empty($userExceptionId)) {
            $hrleaveCalculation = $hrleaveCalculation->whereNotIn('hr_detail.user_id', $userExceptionId);
        }
        $hrleaveCalculation = $hrleaveCalculation->groupBy('hr_detail.user_id')->get();
        foreach ($hrleaveCalculation as $value) {
            $userAbsent = 0;
            $userHoliday = 0;
            if ($value->absent != 0 || $value->half_day_absent != 0) {
                $userAbsent = $value->absent + $value->half_day_absent;
            }
            if ($value->holiday_working != 0 || $value->half_holiday_working != 0) {
                $userHoliday = $value->holiday_working + $value->half_holiday_working;
            }

            $leaveDetail = \App\Models\Backend\HrLeaveBal::where("user_id", $value['user_id'])->where("month", $duration);
            if ($leaveDetail->count() == 0) {
                \App\Models\Backend\HrLeaveBal::create(['user_id' => $value['user_id'],
                    'shift_id' => $value['shift_id'],
                    'start_date' => $startDate,
                    'month' => $duration,
                    'end_date' => $endDate,
                    'leave' => $userAbsent,
                    'holiday_working' => $userHoliday,
                    'total_holiday' => $userHoliday,
                    'total_leave' => $userAbsent,
                    'created_on' => date('Y-m-d H:i:s')]);
            } else {
                $leaveDetail = $leaveDetail->first();
                \App\Models\Backend\HrLeaveBal::where("id", $leaveDetail->id)->update(["leave" => $userAbsent,
                    "holiday_working" => $userHoliday, 'total_holiday' => $userHoliday, 'total_leave' => $userAbsent]);
            }
        }
    }

}
