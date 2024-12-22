<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrUserInOuttime;

class PunchInOutController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: May 05, 2018
     * Purpose   : Fetch in-out data
     */

    public function index(Request $request) {
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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'punch_time';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        $id = app('auth')->guard()->id();
        $userId = array();
        $userList = array();
        $user = getLoginUserHierarchy();
        if ($user->designation_id != 7) {
            $secondApproval = \App\Models\User::select(app('db')->raw('id'), 'userfullname')->where('second_approval_user', $id)->where('is_active', 1)->get()->toArray();
            foreach ($secondApproval as $keySecond => $valueSecond) {
                $userId[] = $valueSecond['id'];
                $userList[] = $valueSecond;
            }
            $firstApproval = \App\Models\User::select(app('db')->raw('id'), 'userfullname')->where('first_approval_user', $id)->where('is_active', 1)->get()->toArray();
        } else {
            $firstApproval = \App\Models\User::select(app('db')->raw('id'), 'userfullname')->where('is_active', 1)->get()->toArray();
        }
        foreach ($firstApproval as $keyFirst => $valueFirst) {
            $userId[] = $valueFirst['id'];
            $userList[] = $valueFirst;
        }

        $punchinout = HrUserInOuttime::select('hr_user_in_out_time.*')
                        ->with('user_id:id,userfullname')->whereIn('user_id', $userId);

        if ($sortBy == 'created_by' || $sortBy == 'user_id') {
            $punchinout = $punchinout->leftjoin("user as u", "u.id", "hr_user_in_out_time.$sortBy");
            $sortBy = 'userfullname';
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $punchinout = search($punchinout, $search);
        }

        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $punchinout = $punchinout->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $punchinout->count();
            $punchinout = $punchinout->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $punchinout = $punchinout->get();
            $filteredRecords = count($punchinout);

            $pager = ['sortBy' => $request->get('sortBy'),
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        if ($request->has('excel') && $request->get('excel') == 1) {
            $data = $punchinout->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Staff Name', 'Date', 'Punch Type', 'Time', 'Changed By Staff'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $columnData[] = $i;
                    $columnData[] = isset($data['user_id']['userfullname']) ? $data['user_id']['userfullname'] : '-';
                    $columnData[] = $data['date'];
                    $columnData[] = $data['punch_type'] == 1 ? 'In' : 'Out';
                    $columnData[] = $data['punch_time'];
                    $columnData[] = $data['is_manually_change'] == 1 ? 'Yes' : 'No';
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'UserInOuttime', 'xlsx', 'A1:F1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "UserInOuttime list.", ['data' => $punchinout, 'userlist' => $userList], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("UserInOuttime listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing user in/out timelist", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 05, 2018
     * Purpose   : Store in-out data
     */

    public function store(Request $request) {
        //  try {
        //validate request parameters
        $validator = $this->validateInput($request);
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
        $userId = $request->get('user_id');
        $date = $request->get('date');
        $punchType = $request->get('punch_type');
        $day = date("d");
        $todayDate = date('Y-m-d');
        if ($day > 27) {
            $startDate = date('Y-m-26');
            $endDate = date('Y-m-25', strtotime("+1 month", strtotime($todayDate)));
        } else {
            $startDate = date('Y-m-26', strtotime("-1 month", strtotime($todayDate)));
            $endDate = date('Y-m-28');
        }
        if ($date >= $startDate && $date <= $endDate) {           
        $hrDetail = \App\Models\Backend\HrDetail::where('user_id', $userId)->where('date', $date)->first();
        // store user in/out time details
        $checkPunch = \App\Models\Backend\HrUserInOuttime::where("user_id", $userId)->where("date", $date)
                ->where("punch_type", $punchType);
        if ($checkPunch->count() > 0) {
            return createResponse(config('httpResponse.UNPROCESSED'), "Already Enter for this date", ['error' => "Already Enter for this date"]);
        }
        $punchinout = HrUserInOuttime::create([
                    'hr_detail_id' => $hrDetail->id,
                    'user_id' => $request->get('user_id'),
                    'date' => $request->get('date'),
                    'punch_type' => $request->get('punch_type'),
                    'punch_time' => $request->get('punch_time'),
                    'office_location' => $request->get('office_location'),
                    'reason' => $request->get('reason'),
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => app('auth')->guard()->id()]);
        $this->updateDetail($request->get('date'), $request->get('user_id'));


        $inOutTime = \App\Models\Backend\HrUserInOuttime::where('user_id', $userId)->where('date', $date)->orderBy('punch_time', 'dec')->get()->toArray();
        $arrangeData = array();
        foreach ($inOutTime as $key => $value) {
            $arrangeData[$value['user_id']][] = $value;
        }

        $hrModifiedTime = getWorkingTime($userId, $date);
        if (isset($hrModifiedTime)) {
            $updateData = $amendmentData = $rawData = array();

            $eightHour = strtotime('08:00:00');
            $fourHour = strtotime('04:00:00');
            $ealryHour = strtotime('07:00:00');
            $actualWorkingTime = strtotime($hrModifiedTime['working_time']);

            if ($actualWorkingTime >= $eightHour) {
                $updateData['remark'] = 0;
                $updateData['status'] = 0;
                $updateData['final_remark'] = 0;
            } else if ($actualWorkingTime >= $fourHour && $actualWorkingTime < $ealryHour) {
                $updateData['remark'] = 0;
                $updateData['status'] = 0;
                $updateData['final_remark'] = 1;
            } else if ($actualWorkingTime >= $ealryHour && $actualWorkingTime < $eightHour) {
                $updateData['remark'] = 4;
                $updateData['status'] = 2;
                $updateData['final_remark'] = 0;
            }

            $updateData['punch_in'] = $hrModifiedTime['punch_in'];
            $updateData['punch_out'] = $hrModifiedTime['punch_out'];
            $updateData['working_time'] = $hrModifiedTime['working_time'];
            $updateData['break_time'] = $hrModifiedTime['break_time'];
            $updateData['modified_by'] = 1;
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            \App\Models\Backend\HrDetail::where('id', $hrDetail->id)->update($updateData);
           
        }

        return createResponse(config('httpResponse.SUCCESS'), 'User In/Out time  has been added successfully', ['data' => $punchinout]);
        } else{
            return createResponse(config('httpResponse.UNPROCESSED'), 'You can not add previous month data', ['error' => 'You can not add previous month data']);
        }
        /* } catch (\Exception $e) {
          app('log')->error("User In/Out time  creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add user in/out time', ['error' => 'Could not add user in/out time']);
          } */
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show user in-out timedata
     */

    public function show($id) {
        try {
            $punchinout = HrUserInOuttime::where('hr_user_in_out_time.id', $id)->with('assignee:id,userfullname')->get();
            if (!isset($punchinout))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The user in-out timedoes not exist', ['error' => 'The user in-out timedoes not exist']);

            //send user in/out timeinformation
            return createResponse(config('httpResponse.SUCCESS'), 'User in-out time data', ['data' => $punchinout]);
        } catch (\Exception $e) {
            app('log')->error("User in-outt ime details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get user in-out time.', ['error' => 'Could not get user in-out time.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Show user in-out timedata
     */

    public function update(Request $request, $id) {
        try {
            $validator = $this->validateInput($request);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $punchinout = HrUserInOuttime::find($id);

            if (!$punchinout)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The User in-out time does not exist', ['error' => 'The User in-out time does not exist']);

            $updateData = array();
            $updateData = filterFields(['user_id', 'date', 'punch_type', 'punch_time', 'office_location', 'reason'], $request);

            //update the details
            $updateData['is_manually_change'] = 1;
            $punchinout->update($updateData);
            $userId = $request->get('user_id');
            $date = $request->get('date');
            $punchType = $request->get('punch_type');
            $day = date("d");
            $todayDate = date('Y-m-d');
        if ($day > 27) {
            $startDate = date('Y-m-26');
            $endDate = date('Y-m-25', strtotime("+1 month", strtotime($todayDate)));
        } else {
            $startDate = date('Y-m-26', strtotime("-1 month", strtotime($todayDate)));
            $endDate = date('Y-m-28');
        }
        if ($date >= $startDate && $date <= $endDate) {  
            self::updateDetail($date, $userId);

            $inOutTime = \App\Models\Backend\HrUserInOuttime::where('user_id', $userId)->where('date', $date)->orderBy('punch_time', 'dec')->get()->toArray();
            $arrangeData = array();
            foreach ($inOutTime as $key => $value) {
                $arrangeData[$value['user_id']][] = $value;
            }

            $hrModifiedTime = getWorkingTime($userId, $date);
            if (isset($hrModifiedTime)) {
                $updateData = $amendmentData = $rawData = array();
                $hrDetail = \App\Models\Backend\HrDetail::where('user_id', $userId)->where('date', $date)->first();
                $eightHour = strtotime('08:00:00');
                $fourHour = strtotime('04:00:00');
                $ealryHour = strtotime('07:00:00');
                $actualWorkingTime = strtotime($hrModifiedTime['working_time']);

                if ($actualWorkingTime >= $eightHour) {
                    $updateData['remark'] = 0;
                    $updateData['status'] = 0;
                    $updateData['final_remark'] = 0;
                } else if ($actualWorkingTime >= $fourHour && $actualWorkingTime < $ealryHour) {
                    $updateData['remark'] = 0;
                    $updateData['status'] = 0;
                    $updateData['final_remark'] = 1;
                } else if ($actualWorkingTime >= $ealryHour && $actualWorkingTime < $eightHour) {
                    $updateData['remark'] = 4;
                    $updateData['status'] = 2;
                    $updateData['final_remark'] = 0;
                }

                $updateData['punch_in'] = $hrModifiedTime['punch_in'];
                $updateData['punch_out'] = $hrModifiedTime['punch_out'];
                $updateData['working_time'] = $hrModifiedTime['working_time'];
                $updateData['break_time'] = $hrModifiedTime['break_time'];
                $updateData['modified_by'] = 1;
                $updateData['modified_on'] = date('Y-m-d H:i:s');
                \App\Models\Backend\HrDetail::where('id', $hrDetail->id)->update($updateData);


                $user = \App\Models\User::with('timesheetApproval:id,userfullname,email')->find($userId);
                $username = app('auth')->guard()->user()->userfullname;
                $oldValue = date('h:i A', strtotime($request->get('old_value')));
                $newValue = date('h:i A', strtotime($request->get('punch_time')));
                $staffName = $user->userfullname;
                $entryType = $punchType == 1 ? 'IN' : 'OUT';
                $reason = $request->get('reason');

                $rawData['username'] = $username;
                $rawData['staffname'] = $staffName;
                $rawData['working_time'] = $hrModifiedTime['working_time'];
                $rawData['break_time'] = $hrModifiedTime['break_time'];
                $rawData['old_remark'] = $hrDetail->remark;
                $rawData['old_status'] = $hrDetail->status;
                $rawData['old_final_remark'] = $hrDetail->final_remark;
                $rawData['punch_in'] = date('h:i A', strtotime($hrDetail['punch_in']));
                $rawData['punch_out'] = date('h:i A', strtotime($hrDetail['punch_out']));
                $rawData[$punchType]['punch_in_out_id'] = $punchinout->id;
                $rawData[$punchType]['reason'] = $reason;
                $rawData[$punchType]['old_value'] = $oldValue;
                $rawData[$punchType]['new_value'] = $newValue;
                $rawData[$punchType]['punchtype'] = $entryType;
                $rawData[$punchType]['punchtypeid'] = $punchType;
                $amendmentData['user_id'] = $userId;
                $amendmentData['hr_detail_id'] = $punchinout->hr_detail_id;
                $amendmentData['date'] = $date;
                $amendmentData['rawdata'] = \GuzzleHttp\json_encode($rawData);
                $amendmentData['created_by'] = app('auth')->guard()->id();
                $amendmentData['created_on'] = date('Y-m-d H:i:s');
                \App\Models\Backend\HrUserInOuttimeAmendment::insert($amendmentData);

                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('CHANGEINOUTENTRY');
                if ($emailTemplate->is_active == 1) {
                    $find = array('DATE', 'APPROVALUSER', 'USERNAME', 'OLDVALUE', 'NEWVALUE', 'STAFFNAME', 'ENTRYTYPE', 'CHANGEREASON');
                    $replace = array(dateFormat($date), $user->timesheetApproval->userfullname, $username, '<b>' . $oldValue . '</b>', '<b>' . $newValue . '</b>', $staffName, '<span style="color:red"><b>' . $entryType . '</b></span>', $reason);
                    $data['to'] = $user->timesheetApproval->email;
                    $data['cc'] = $emailTemplate->cc;
                    $data['subject'] = str_replace($find, $replace, $emailTemplate->subject);
                    $data['content'] = str_replace($find, $replace, $emailTemplate->content);
                    storeMail($request, $data);
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'UserInOuttime has been updated successfully', ['message' => 'UserInOuttime has been updated successfully']);
        }else{
            return createResponse(config('httpResponse.UNPROCESSED'), 'You can not update previous month data', ['error' => 'You can not update previous month data']);
        }
        } catch (\Exception $e) {
            app('log')->error("User in-out time updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update user in/out timedetails.', ['error' => 'Could not update user in/out timedetails.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Make user in/out time In active.
     */

    public function destroy(Request $request, $id) {
        try {
            $punchinout = HrUserInOuttime::find($id);
            if (!$punchinout)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The User in-out time does not exist', ['error' => 'The User in-out time does not exist']);

            //delete the details
            $punchinout->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'User in-out time has been deleted successfully', ['message' => 'UserInOuttime has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("User in-out time updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted user in-out time details.', ['error' => 'Could not deleted user in-out time details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 05, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'user_id' => 'required|numeric',
            'date' => 'required|date_format:Y-m-d',
            'punch_type' => 'required|in:0,1',
            'punch_time' => 'required|date_format:H:i'
                ], ['date.date_format' => 'Date format invalid it should be D-M-Y',
            'punch_time.date_format' => 'Punch time invalid it should be Hour:Minutes']);
        return $validator;
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: May 15, 2018
     * Purpose   : Comman function for store and update punch time
     * @param    : 
     */

    public static function updateDetail($date, $user_id) {
        $punchData = HrUserInOuttime::where('date', $date)->where('user_id', $user_id);
        $punchIn = $punchData->where('punch_type', 1)->count();
        $punchOut = $punchData->where('punch_type', 0)->count();
        if ($punchOut == $punchIn) {
            $lateComingdetail = \App\Models\Backend\HrDetail::where('user_id', $user_id)->where('date', $date)->orderBy('id', 'DESC')->first();
            if (!empty($lateComingdetail)) {
                if (isset($lateComingdetail[0]->firs_punch_in) && $lateComingdetail[0]->firs_punch_in != '' && $lateComingdetail[0]->remark == 5) {
                    $exception = 1;
                } else {
                    $exception = 0;
                }
                $lateComingdetail->is_exception = $exception;
                $lateComingdetail->update();
            }
        }
    }

    public static function setUserInOutEntryData($arrangeData) {
        foreach ($arrangeData as $key => $value) {
            for ($i = 0; $i < count($value); $i++) {
                $userData[$key][] = $value[$i];
                if (count($value) == 1) {
                    $value[$i]['id'] = '';
                    $value[$i]['punch_type'] = $value[$i]['punch_type'] == 1 ? 0 : 1;
                    $value[$i]['punch_time'] = $value[$i]['punch_time'];
                    $userData[$key][] = $value[$i];
                } else if (isset($value[$i + 1]['punch_type']) && $value[$i + 1]['punch_type'] == $value[$i]['punch_type']) {
                    $j = $i + 1;
                    $previous = strtotime($value[$i]['punch_time']);
                    $next = strtotime($value[$j]['punch_time']);
                    $diff = strtotime($next - $previous);
                    $minutes = round(((($diff % 604800) % 86400) % 3600) / 60);

                    if ($minutes < 2) {
                        $value[$i]['id'] = '';
                        $value[$i]['punch_type'] = $value[$j]['punch_type'] == 1 ? 0 : 1;
                        $userData[$key][] = $value[$i];
                    } else {
                        $value[$i]['id'] = '';
                        $value[$i]['punch_type'] = $value[$j]['punch_type'] == 1 ? 0 : 1;
                        $value[$i]['punch_time'] = $value[$j]['punch_time'];
                        $userData[$key][] = $value[$i];
                    }
                } else if ($value[$i]['punch_type'] == 1 && (count($value) - 1) == $i) {
                    $value[$i]['id'] = '';
                    $value[$i]['punch_type'] = 0;
                    $value[$i]['punch_time'] = $value[$i]['punch_time'];
                    $userData[$key][] = $value[$i];
                }
            }
        }

        return $userData;
    }

    public function listAmedmentinout(Request $request) {
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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];

        $id = app('auth')->guard()->id();
        $userId = array();
        $userList = array();
        $user = getLoginUserHierarchy();
        if ($user->designation_id != 7) {
            $secondApproval = \App\Models\User::select(app('db')->raw('id'), 'userfullname')->whereRaw('(second_approval_user = ' . $id . ' OR timesheet_approval_user = ' . $id . ')')->where('is_active', 1)->get()->toArray();
            foreach ($secondApproval as $keySecond => $valueSecond) {
                $userId[] = $valueSecond['id'];
                $userList[] = $valueSecond;
            }
            $firstApproval = \App\Models\User::select(app('db')->raw('id'), 'userfullname')->where('first_approval_user', $id)->where('is_active', 1)->where('second_approval_user', 0)->get()->toArray();
        } else {
            $firstApproval = \App\Models\User::select(app('db')->raw('id'), 'userfullname')->where('is_active', 1)->get()->toArray();
        }

        foreach ($firstApproval as $keyFirst => $valueFirst) {
            $userId[] = $valueFirst['id'];
            $userList[] = $valueFirst;
        }

        $superiorStaff = \App\Models\User::select('first_approval_user', 'second_approval_user')->where('is_active', 1)->get();
        $superiorStaffIds = array();
        foreach ($superiorStaff as $key => $value) {
            if ($value->second_approval_user == Null && $value->second_approval_user == 0 && $value->first_approval_user != 0) {
                if (!in_array($value->first_approval_user, $superiorStaffIds))
                    $superiorStaffIds[] = $value->first_approval_user;
            }

            if ($value->second_approval_user != Null && $value->second_approval_user != 0) {
                if (!in_array($value->second_approval_user, $superiorStaffIds))
                    $superiorStaffIds[] = $value->second_approval_user;
            }
        }

        $superiorStaffList = \App\Models\User::select('userfullname', 'id')->whereIn('id', $superiorStaffIds)->where('is_active', 1)->get()->toArray();
        $approvalStaff = \App\Models\User::select('u.userfullname', 'u.id')->leftjoin('user AS u', 'u.id', 'user.timesheet_approval_user')->where('user.is_active', 1)->where('user.timesheet_approval_user', '!=', '')->groupBy('u.id')->get('u.id')->toArray();
        $punchinoutamedment = \App\Models\Backend\HrUserInOuttimeAmendment::select('hr_user_in_out_time_amendment.*')->with('created_by:id,userfullname', 'approved_by:id,userfullname', 'user_id:id,userfullname')->leftjoin('user as u', 'u.id', 'user_id');

        $user = getLoginUserHierarchy();
        if ($user->designation_id != 7)
            $punchinoutamedment = $punchinoutamedment->whereRaw('(u.timesheet_approval_user = ' . $id . ' OR user_id IN (' . implode(',', $userId) . '))');


        if ($sortBy == 'created_by' || $sortBy == 'user_id' || $sortBy == 'approved_by') {
            $punchinoutamedment = $punchinoutamedment->leftjoin("user as u1", "u1.id", "hr_user_in_out_time_amendment.$sortBy");
            $sortBy = 'u1.userfullname';
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('created_by' => 'hr_user_in_out_time_amendment');
            $punchinoutamedment = search($punchinoutamedment, $search, $alias);
        }

        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $punchinoutamedment = $punchinoutamedment->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $punchinoutamedment->count();
            $punchinoutamedment = $punchinoutamedment->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $punchinoutamedment = $punchinoutamedment->get();
            $filteredRecords = count($punchinoutamedment);

            $pager = ['sortBy' => $request->get('sortBy'),
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        $punchinoutamedment = \App\Models\Backend\HrUserInOuttimeAmendment::arrangeData($punchinoutamedment);
        if ($request->has('excel') && $request->get('excel') == 1) {
            $data = $punchinoutamedment->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Superior Staff', 'Staff Name', 'Approval Staff', 'Date', 'Reason for rejection', 'Status'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $value) {
                    $columnData[] = $i;
                    if ($value['status'] == 1)
                        $status = 'Approved';
                    else if ($value['status'] == 2)
                        $status = 'Rejected';
                    else
                        $status = 'Pending For Approval';

                    $columnData[] = isset($value['created_by']['userfullname']) ? $value['created_by']['userfullname'] : '-';
                    $columnData[] = isset($value['user_id']['userfullname']) ? $value['user_id']['userfullname'] : '-';
                    $columnData[] = isset($value['approved_by']['userfullname']) ? $value['approved_by']['userfullname'] : '-';
                    $columnData[] = dateFormat($value['date']);
                    $columnData[] = $value['reason_for_rejection'] != '' ? $value['reason_for_rejection'] : '-';
                    $columnData[] = $status;
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'UserInOuttime', 'xlsx', 'A1:G1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "User Amedment InOut time list.", ['data' => $punchinoutamedment, 'userlist' => $userList, 'superiorstafflist' => $superiorStaffList, 'approvalstafflist' => $approvalStaff], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("User In Out time amedment listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing amedment in/out timelist", ['error' => 'Server error.']);
//        }
    }

    public function approveAmedmentRequest(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'status' => 'in:1,2'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $updateAmenmentData = array();
            $status = $request->get('status');
            $updateAmenmentData['status'] = $status;
            $reasonForRejection = $request->get('reason_for_rejection');
            $updateAmenmentData['approved_by'] = app('auth')->guard()->id();
            if ($status == 2) {
                $updateAmenmentData['reason_for_rejection'] = $reasonForRejection;
            }
            \App\Models\Backend\HrUserInOuttimeAmendment::where('id', $id)->update($updateAmenmentData);

            $amedmentDetail = \App\Models\Backend\HrUserInOuttimeAmendment::find($id);
            $userID[] = $amedmentDetail->created_by;
            $userID[] = $amedmentDetail->user_id;
            $status = $request->get('status') == 1 ? 'approved' : 'rejected';
            $reason = '-';
            if ($request->get('status') == 2) {
                $rawData = \GuzzleHttp\json_decode($amedmentDetail->rawdata, true);
                $i = 1;
                if (isset($rawData[0])) {
                    $updateOut = array();
                    $updateOut['punch_time'] = date("H:i:s", strtotime($rawData[0]['old_value']));
                    HrUserInOuttime::where('id', $rawData[0]['punch_in_out_id'])->update($updateOut);
                    $reason = $rawData[0]['reason'];
                }

                if (isset($rawData[1])) {
                    $updateIn = array();
                    $updateIn['punch_time'] = date("H:i:s", strtotime($rawData[1]['old_value']));
                    HrUserInOuttime::where('id', $rawData[1]['punch_in_out_id'])->update($updateIn);
                    $reason = $rawData[1]['reason'];
                }

                $hrModifiedTime = getWorkingTime($amedmentDetail->user_id, $amedmentDetail->date);
                $updateData['remark'] = $rawData['old_remark'];
                $updateData['status'] = $rawData['old_status'];
                $updateData['final_remark'] = $rawData['old_final_remark'];
                $updateData['punch_in'] = $hrModifiedTime['punch_in'];
                $updateData['punch_out'] = $hrModifiedTime['punch_out'];
                $updateData['working_time'] = $hrModifiedTime['working_time'];
                $updateData['break_time'] = $hrModifiedTime['break_time'];
                $updateData['modified_by'] = 1;
                $updateData['modified_on'] = date('Y-m-d H:i:s');
                \App\Models\Backend\HrDetail::where('id', $amedmentDetail->hr_detail_id)->update($updateData);
                $userDetail = \App\Models\User::whereIn('id', $userID)->pluck('email', 'id')->toArray();
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('APPROVALAMEDMENTREQUEST');
                if ($emailTemplate->is_active == 1) {
                    $rawData = \GuzzleHttp\json_decode($amedmentDetail->rawdata, true);
                    $find = array('STATUS', 'USERNAME', 'APPROVAL', 'DATE', 'CHANGEREASON', 'STAFFNAME', 'REASONFORREJECTION');
                    $replace = array($status, $rawData['username'], app('auth')->guard()->user()->userfullname, dateFormat($amedmentDetail->date), $reason, $rawData['staffname'], $reasonForRejection);
                    $data['to'] = $userDetail[$amedmentDetail->created_by];
                    $data['cc'] = $userDetail[$amedmentDetail->user_id];
                    $data['subject'] = ucwords(str_replace($find, $replace, $emailTemplate->subject));
                    $data['content'] = str_replace($find, $replace, $emailTemplate->content);
                    storeMail($request, $data);
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'User In Out time amedment has been updated successfully', ['message' => 'User In Out time amedment has been updated successfully']);
        } catch (Exception $e) {
            app('log')->error("User In/Out time amedment approval failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not amedement user in/out time', ['error' => 'Could not amedement user in/out time']);
        }
    }

    public function userList() {
        try {
            $user = getLoginUserHierarchy();
            if ($user->designation_id != 7) {
                $loginUser = loginUser();
                $userList = \App\Models\User::where("is_active", "1")->whereRaw("second_approval_user = $loginUser OR ((second_approval_user = null OR second_approval_user = 0) and first_approval_user =$loginUser)")->get();
            } else {
                $userList = \App\Models\User::where("is_active", "1")->get();
            }
            return createResponse(config('httpResponse.SUCCESS'), 'User list successfully', ['data' => $userList]);
        } catch (Exception $e) {
            app('log')->error("User list failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not fetch user list', ['error' => 'Could not fetch user list']);
        }
    }

}
