<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrHoliday;

class HRController extends Controller {

    public static function addManualInOut(Request $request) {
        // try {
        $validator = app('validator')->make($request->all(), [
            'user_id' => 'required',
            'type' => 'required|in:1,0',
                ], []);

        if ($validator->fails()) { // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
        }
        $userId = $request->input('user_id');
        $type = $request->input('type');
        $todayDate = date("Y-m-d");
        $checkPunch = \App\Models\Backend\HrUserInOuttime::where("user_id", $userId)->where("date", $todayDate)
                ->where("punch_type", 1);
        if ($checkPunch->count() > 0 && $type == 1) {
            return createResponse(config('httpResponse.UNPROCESSED'), "Already Enter Punch In", ['error' => "Already Enter Punch In"]);
        }
        if ($type == 0) {
            $day = date('d');
            if ($day > 25) {
                $yearMonth = date('Y-m', strtotime("-1 month"));
            } else {
                $yearMonth = date('Y-m');
            }
            $checkRequest = DashboardController::pendingRequestCalculated($yearMonth);
            if ((isset($checkRequest['myView']['pendingRequest']) && $checkRequest['myView']['pendingRequest'] > 0) || (isset($checkRequest['teamView']['pendingForApproval']) && $checkRequest['teamView']['pendingForApproval'] > 0)) {
                return createResponse(config('httpResponse.UNPROCESSED'), "First complete your pending request or approval then logout", ['error' => "First complete your pending request or approval"]);
            }
        }
        $inOut = self::addInOutDetail($userId, $type);
        // $hrDetailAfterUpdated = \App\Models\Backend\HrDetail::where("user_id", $request->input('user_id'))->where('date', $todayDate)->first();
        return createResponse(config('httpResponse.SUCCESS'), "Punch Add Sucessfully", ['message' => 'Punch Add Sucessfully']);

        /* } catch (Exception $ex) {
          app('log')->error("Hr listing failed : " . $ex->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Hr", ['error' => 'Server error.']);
          } */
    }

    public static function addInOutDetail($userId, $type) {
        $todayDate = date("Y-m-d");
        $checkhrDetail = \App\Models\Backend\HrDetail::where("date", $todayDate);
        $checkuser = \App\Models\User::where("is_active", "1")->where('shift_id', '!=', 0)->count();
        if ($checkhrDetail->count() != $checkuser) {
            self::addHrDetail($todayDate);
        }
        $checkPunch = \App\Models\Backend\HrUserInOuttime::where("user_id", $userId)->where("date", $todayDate);
        if ($checkPunch->count() == 0 && $type == 0) { // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "First Enter Punch In", ['error' => "First Enter Punch In"]);
        } else {
            $checkPunch = $checkPunch->where("punch_type", $type);
            if ($checkPunch->count() > 0) {
                $types = ($type == 1) ? 'Punch In' : 'Punch Out';
                return createResponse(config('httpResponse.UNPROCESSED'), "Already Enter " . $types, ['error' => "Already Enter " . $types]);
            }
        }
        $punchTime = date('H:i:s');
        /* if ($request->input('type') == 1) {
          $punchTime = date('H:i:s', strtotime("-10 minutes"));
          } */

        $user = \App\Models\User::where('id', $userId)->first();
        $hrDetail = \App\Models\Backend\HrDetail::where("user_id", $userId)->where('date', $todayDate)->first();
        $checkPunch = $checkPunch->where("punch_type", $type);

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if ($ip == '::1') {
            $ip = '127.0.0.1';
        }

        if ($checkPunch->count() == 0) {
            $insertArray = array('user_id' => $userId,
                'hr_detail_id' => $hrDetail->id,
                'date' => $todayDate,
                'punch_time' => $punchTime,
                'punch_type' => $type,
                'office_location' => $user->location_id,
                'type' => 1,
                'ip_address' => $ip,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => loginUser());
            \App\Models\Backend\HrUserInOuttime::create($insertArray);
        }

        if ($type == 1) {
            $remark = self::lateComingRemark($punchTime, $hrDetail->id);
            $updateInData = array();
            if ($remark == 3) {
                $updateInData['daily_email_send'] = 0;
            }
            $updateInData['punch_in'] = $punchTime;
            $updateInData['office_location'] = $user->location;
            $updateInData['remark'] = $remark;
            $updateInData['status'] = 0;
            $updateInData['modified_by'] = loginUser();
            $updateInData['modified_on'] = date('Y-m-d H:i:s');
            \App\Models\Backend\HrDetail::where('id', $hrDetail->id)->update($updateInData);
        }
        if ($type == 0) {
            $WorkingDetail = getWorkingTime($user->id, $todayDate);
            $updateOutData = array();
            $updateOutData['is_exception'] = 1;
            $updateOutData['punch_out'] = $WorkingDetail['punch_out'];
            $updateOutData['working_time'] = $WorkingDetail['working_time'];
            $updateOutData['break_time'] = $WorkingDetail['break_time'];
            $updateOutData['modified_by'] = loginUser();
            $updateOutData['modified_on'] = date('Y-m-d H:i:s');
            \App\Models\Backend\HrDetail::where('id', $hrDetail->id)->update($updateOutData);
        }
        return true;
    }

    public static function getHrDetail() {
        try {
            $ip = $_SERVER['REMOTE_ADDR'];
            if ($ip == '::1') {
                $ip = '127.0.0.1';
            }
            $checkIP = \App\Models\Backend\IpAddress::whereRaw("from_ip = INET_ATON('$ip') OR (from_ip <= INET_ATON('$ip') AND to_ip >= INET_ATON('$ip'))");

            if ($checkIP->count() == 0) {
                app('auth')->logout();
                return createResponse(config('httpResponse.SUCCESS'), 'Valid IP Address.', ["success" => "0", "data" => $ip]);
            }
            $userId = loginUser();
            $date = date('Y-m-d');
            $unit = \App\Models\Backend\Timesheet::where("user_id", $userId)->where('date', $date)->sum('units');
            $leaveBalance = \App\Models\Backend\HrLeaveBalance::where("user_id", $userId)->first();
            $HrDetail = \App\Models\Backend\HrDetail::where("user_id", $userId)->where('date', $date)->first();
            if (!empty($HrDetail)) {
                $HrDetail['units'] = $unit;
                $HrDetail['leave_balance'] = \App\Models\Backend\HrLeaveBalance::where("user_id", $userId)->orderBy('id', 'desc')->skip(0)->take(5)->get();
            }
            if ($HrDetail->punch_in == null) {
                \App\Http\Controllers\AuthController::logout();
            }

            return createResponse(config('httpResponse.SUCCESS'), "Hr Detail", ['data' => $HrDetail]);
        } catch (Exception $ex) {
            app('log')->error("Hr Detail failed : " . $ex->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while Detail Hr", ['error' => 'Server error.']);
        }
    }

    public static function addHrDetail($addDate) {
        try {
            //check user list
            $activeUser = \App\Models\User::leftjoin("hr_shift_master as s", "s.id", "user.shift_id")
                            ->select('user.id', 'user.user_bio_id', 's.id as shift_id', "s.from_time", "s.to_time", "s.grace_period", "s.late_period", "s.late_allowed_count", "s.break_time")
                            ->where("user.is_active", 1)->where('user.shift_id', '!=', 0)
                            ->where("user.user_joining_date", "<=", $addDate)
                            ->orderBy('user.id', 'asc')->get();
            $exceptionShift = \App\Models\Backend\HrExceptionshift::
                            where('start_date', '<=', $addDate)->where('end_date', '>=', $addDate)
                            ->where("is_active", 1)->orderBy('shift_id', 'asc');

            $exceptionShiftData = $userDetails = array();
            if ($exceptionShift->count() > 0) {
                foreach ($exceptionShift->get() as $value) {

                    $exceptionShiftData[$value->shift_id] = $value;
                    if ($value->user_id != '') {
                        $exceptionShiftData[$value->shift_id]['user_ids'] = $value->user_id;
                    }
                }
            }

            foreach ($activeUser as $valueUser) {
                $checkUser = \App\Models\Backend\HrDetail::where("user_id", $valueUser->id)->where('date', $addDate)->count();
                if ($checkUser == 0) {
                    $data = array();
                    $shiftId = $valueUser->shift_id;
                    $data['user_id'] = $valueUser->id;
                    $data['shift_id'] = $valueUser->shift_id;
                    $data['date'] = $addDate;
                    if (isset($exceptionShiftData[$shiftId]['user_ids']) && $exceptionShiftData[$shiftId]['user_ids'] != '') {
                        $userDetails = explode(",", $exceptionShiftData[$shiftId]['user_ids']);
                    }

                    if (isset($exceptionShiftData[$shiftId]) && !empty($exceptionShiftData[$shiftId]) && ($exceptionShiftData[$shiftId]['user_ids'] == '') && !in_array($valueUser->id, $userDetails)) {


                        /* if (($exceptionShiftData['user_ids'] != '') && in_array($valueUser->id, $userDetails)) {
                          $data['shift_from_time'] = $exceptionShiftData[$shiftId]['from_time'];
                          $data['shift_to_time'] = $exceptionShiftData[$shiftId]['to_time'];
                          $data['grace_period'] = $exceptionShiftData[$shiftId]['grace_period'];
                          $data['late_period'] = $exceptionShiftData[$shiftId]['late_period'];
                          $data['late_allowed_count'] = $exceptionShiftData[$shiftId]['late_allowed_count'];
                          $data['allow_break'] = $exceptionShiftData[$shiftId]['break_time'];
                          } else { */
                        $data['shift_from_time'] = $exceptionShiftData[$shiftId]['from_time'];
                        $data['shift_to_time'] = $exceptionShiftData[$shiftId]['to_time'];
                        $data['grace_period'] = $exceptionShiftData[$shiftId]['grace_period'];
                        $data['late_period'] = $exceptionShiftData[$shiftId]['late_period'];
                        $data['late_allowed_count'] = $exceptionShiftData[$shiftId]['late_allowed_count'];
                        $data['allow_break'] = $exceptionShiftData[$shiftId]['break_time'];
                        $data['shift_exception'] = 1;
                        //}
                    } else if (isset($exceptionShiftData[$shiftId]['user_ids']) && ($exceptionShiftData[$shiftId]['user_ids'] != '') && in_array($valueUser->id, $userDetails)) {
                        $data['shift_id'] = $value->shift_id;
                        $data['shift_from_time'] = $exceptionShiftData[$value->shift_id]['from_time'];
                        $data['shift_to_time'] = $exceptionShiftData[$value->shift_id]['to_time'];
                        $data['grace_period'] = $exceptionShiftData[$value->shift_id]['grace_period'];
                        $data['late_period'] = $exceptionShiftData[$value->shift_id]['late_period'];
                        $data['late_allowed_count'] = $exceptionShiftData[$value->shift_id]['late_allowed_count'];
                        $data['allow_break'] = $exceptionShiftData[$value->shift_id]['break_time'];
                        $data['shift_exception'] = 1;
                    } else {
                        $data['shift_from_time'] = $valueUser->from_time;
                        $data['shift_to_time'] = $valueUser->to_time;
                        $data['grace_period'] = $valueUser->grace_period;
                        $data['late_period'] = $valueUser->late_period;
                        $data['late_allowed_count'] = $valueUser->late_allowed_count;
                        $data['allow_break'] = $valueUser->break_time;
                    }
                    $holiday = 0;
                    $isSundayOrHoliday = todayisSundayOrHoliday($addDate, $valueUser->shift_id);
                    if (in_array($isSundayOrHoliday, ['Sunday', 'Holiday'])) {
                        $holiday = 1;
                    }
                    $data['is_holiday'] = $holiday;
                    $data['remark'] = 0;
                    $data['created_by'] = 1;
                    $data['created_on'] = date('Y-m-d H:i:s');
                    \App\Models\Backend\HrDetail::insert($data);
                }
            }
        } catch (Exception $ex) {
            app('log')->error("Hr listing failed : " . $ex->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Hr", ['error' => 'Server error.']);
        }
    }

    public static function addHRDetailForUser($userId, $date, $shiftId) {
        $valueUser = \App\Models\Backend\HrShift::where("id", $shiftId)->first();
        $data = array();
        $data['user_id'] = $userId;
        $data['shift_id'] = $shiftId;
        $data['date'] = $date;
        $data['shift_from_time'] = $valueUser->from_time;
        $data['shift_to_time'] = $valueUser->to_time;
        $data['grace_period'] = $valueUser->grace_period;
        $data['late_period'] = $valueUser->late_period;
        $data['late_allowed_count'] = $valueUser->late_allowed_count;
        $data['allow_break'] = $valueUser->break_time;

        $data['remark'] = 0;
        $data['created_by'] = 1;
        $data['created_on'] = date('Y-m-d H:i:s');
        \App\Models\Backend\HrDetail::insert($data);
    }

    public static function updateRemarkPreviousDay(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'remarkDate' => 'required|date_format:Y-m-d'
                    ], []);

            if ($validator->fails()) { // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
            }
            $remark = self::updateRemark($request->input('remarkDate'));
            return createResponse(config('httpResponse.SUCCESS'), "Update Remark Sucessfully", ['message' => 'Update Remark Sucessfully']);
        } catch (Exception $ex) {
            app('log')->error("Hr listing failed : " . $ex->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Hr", ['error' => 'Server error.']);
        }
    }

    public static function updateRemark($remarkDate) {
        try {
            $tDate = date("Y-m-d");
            if ($remarkDate > $tDate) {
                return;
            }
            $updateDate = date('Y-m-d', strtotime($remarkDate));
            /* $user = \App\Models\User::where("is_active", "1")->get();
              foreach ($user as $u) {
              $checkHR = \App\Models\Backend\HrDetail::where("user_id", $u->id)->where("date", $remarkDate)->count();
              if ($checkHR == 0) { */
            // self::addHRDetailForUser($u->id,$remarkDate,$u->shift_id);
            self::addHrDetail($remarkDate);
            /* }
              } */
            $userInOutCounter = array();
            // for user not fill timesheet
            $userException = \App\Models\Backend\Constant::select('constant_value')->where('constant_name', 'NOT_INCLUDE_FILL_TIMESHEET')->get();
            $userExceptionId = explode(',', $userException[0]->constant_value);

            $hrDetail = \App\Models\Backend\HrDetail::getUserDataOnDate($updateDate);
            if (!empty($userExceptionId)) {
                $hrDetail = $hrDetail->whereNotIn('hr_detail.user_id', $userExceptionId);
            }
            $hrDetail = $hrDetail->groupby("hr_detail.user_id")->get();
            if (count($hrDetail) != 0) {
                foreach ($hrDetail as $value) {
                    $updateData = $hrDetailHistory = array();
                    $updateData['remark'] = $value->remark;
                    $isSundayOrHoliday = todayisSundayOrHoliday($value->date, $value->shift_id);
                    if (in_array($isSundayOrHoliday, ['Sunday', 'Holiday']) && $value->remark == 6) {
                        continue;
                    }
                    $workingTime = '00:00:00';
                    $workingUnit = 0;
                    if ($value->working_time != '') {
                        $workingTime = $value->working_time;
                        $explodeWorkingTime = explode(':', $value->working_time);
                        if (!empty($explodeWorkingTime)) {
                            $tempOperation = ((($explodeWorkingTime[0] * 60) + $explodeWorkingTime[1]) / 60) * 10;
                            $workingUnit = floor($tempOperation);
                        }
                    }
                    $shiftUnit = 0;
                    $shiftTime = '00:00:00';
                    if ($value->shiftTime != '') {
                        $shiftTime = $value->shiftTime;
                        $explodeTime = explode(':', $value->shiftTime);
                        if (!empty($explodeTime)) {
                            $tempOperation = (($explodeTime[0] * 60) + $explodeTime[1]) / 60 * 10;
                            $shiftUnit = floor($tempOperation);
                        }
                    }

                    //$workingUnit = isset($timesheetUnit[$value->id]) ? $timesheetUnit[$value->id] : 0;
                    $timesheetUnit = $value->TimesheetUnit;
                    $updateData['unit'] = $timesheetUnit;

                    if ($value->shift_exception == 1 && $timesheetUnit >= $shiftUnit) {
                        $updateData['hr_final_remark'] = 0;
                        \App\Models\Backend\HrDetail::where('hr_detail.id', $value->id)->update($updateData);
                    } else {
                        if (!in_array($isSundayOrHoliday, ['Sunday', 'Holiday'])) {
                            if ($timesheetUnit >= 80) {
                                $updateData['hr_final_remark'] = 0;
                            } else if ($timesheetUnit < 80 && $timesheetUnit >= 70) {

                                $updateData['hr_final_remark'] = 0;
                            } else if (($timesheetUnit >= 40 && $timesheetUnit < 70)) {

                                $updateData['hr_final_remark'] = 1;
                            } else {
                                $updateData['hr_final_remark'] = 3;
                            }
                        } else {
                            if ($timesheetUnit >= 80) {
                                $updateData['hr_final_remark'] = 0;
                            } else if ($timesheetUnit < 80 && $timesheetUnit >= 70) {

                                $updateData['hr_final_remark'] = 0;
                            } else if (($timesheetUnit >= 40 && $timesheetUnit < 70)) {
                                $updateData['hr_final_remark'] = 1;
                            } else {
                                $updateData['hr_final_remark'] = 0;
                            }
                        }
                        \App\Models\Backend\HrDetail::where("id", $value->id)->update($updateData);
                        /* if ($value->punch_out == null && $updateData['hr_final_remark'] == 3) {
                          checkSandwich($value->id, $value->user_id, $value->date);
                          } */
                        if ($value->status > 2) {
                            continue;
                        }
                        $hrDetailHistory['hr_detail_id'] = $value->id;
                        $hrDetailHistory['user_id'] = $value->user_id;
                        $hrDetailHistory['date'] = $value->date;
                        $hrDetailHistory['created_by'] = 1;
                        $hrDetailHistory['created_on'] = date('Y-m-d');
                        $hrDetailHistory['stage_id'] = 0;
                        $updateData['remark'] = $value->remark;
                        //if sunday and holiday working
                        if (in_array($isSundayOrHoliday, ['Sunday', 'Holiday'])) {
                            $isHoliday = true;
                            if ($isSundayOrHoliday == 'Sunday') {
                                $updateData['remark'] = 2;
                            } else if ($isSundayOrHoliday == 'Holiday') {
                                $updateData['remark'] = 1;
                            }
                            if ($value->location_id == 7 && $timesheetUnit == 0) { // work from home person not working 
                                \App\Models\Backend\HrDetail::where("id", $value->id)->update(["remark" => 0, "status" => 0, "final_remark" => 0]);
                                continue;
                            } else {
                                if ($value->punch_in == null && $value->punch_out == null) {// user not working on sunday and holiday
                                    \App\Models\Backend\HrDetail::where("id", $value->id)->update(["remark" => 0, "status" => 0, "final_remark" => 0]);
                                    continue;
                                } else {
                                    \App\Models\Backend\HrDetail::where("id", $value->id)->update(["remark" => $updateData['remark']]);
                                }
                            }
                        }
                        if ($value->location_id == 7) {
                            // Working unit greater than 80 then consider as full day
                            $updateData['status'] = 0;
                            $workingTime = $shiftTime;
                        } else {
                            $isLateComing = 0;
                            $updateData['status'] = 0;
                            // Late coming
                            if (!in_array($isSundayOrHoliday, ['Sunday', 'Holiday'])) {
                                $isHoliday = false;
                                $updateData['remark'] = 5;
                                $updateData['status'] = 0;
                                $updateData['final_remark'] = 3;
                                if ($value->remark == 3) {
                                    $isLateComing = 1;
                                    $day = date('d');
                                    if ($day > 25) {
                                        $startDate = date('Y-m-26');
                                        $endDate = date('Y-m-25', strtotime("+1 month", strtotime($remarkDate)));
                                    } else {
                                        $startDate = date('Y-m-26', strtotime("-1 month", strtotime($remarkDate)));
                                        $endDate = date('Y-m-25');
                                    }
                                    $lateCommingAlready = \App\Models\Backend\HrDetailHistory::where("date", $value->date)->where('stage_id', 1)->where('user_id', $value->user_id)->count();
                                    if ($lateCommingAlready == 0) {
                                        $lateComingCount = \App\Models\Backend\HrDetailHistory::whereBetween('date', [$startDate, $endDate])
                                                        ->where('stage_id', 1)->where('user_id', $value->user_id)->count();
                                        if ($value->late_allowed_count > $lateComingCount) {
                                            $updateData['remark'] = 3;
                                            $updateData['status'] = 1;
                                            $hrDetailHistory['stage_id'] = 1;
                                        } else {
                                            $updateData['remark'] = 3;
                                            $updateData['status'] = 2;
                                            $hrDetailHistory['stage_id'] = 2;
                                        }
                                    } else {
                                        $updateData['remark'] = 3;
                                        $updateData['status'] = 1;
                                        $hrDetailHistory['stage_id'] = 1;
                                    }
                                    \App\Models\Backend\HrDetail::where('hr_detail.id', $value->id)->update(["remark" => 3]);
                                }
                                if ($isLateComing == 0) {
                                    $updateData['remark'] = 0;
                                }
                            }
                            /* if ($timesheetUnit > $workingUnit) {
                              $timesheetUnit = $workingUnit;
                              } */
                        }

                        // check unit wise Remark  

                        if ($timesheetUnit >= 80 && $workingTime >= $shiftTime) {
                            $updateData['final_remark'] = 0;
                            $isHoliday = false;
                            if (in_array($isSundayOrHoliday, ['Sunday', 'Holiday'])) {
                                $isHoliday = true;
                            }
                            if ($isHoliday) {
                                $updateData['final_remark'] = 2;
                            }
                        } else if ($timesheetUnit >= 80 && $value->punch_out == null) {
                            //$updateData['remark'] = 5;
                            $updateData['final_remark'] = 3;
                            $hrDetailHistory['stage_id'] = 3;
                            if ($isHoliday) {
                                $updateData['final_remark'] = 2;
                            }
                        } else if ($timesheetUnit >= 70 && ($workingTime < $shiftTime || $workingTime >= $shiftTime)) {
                            $cutofftime = '07:00:00';
                            if ($timesheetUnit >= 70 || $cutofftime <= $workingTime) {
                                // Working unit greater than 70 and $workingTime < $shiftTime then consider as early leaving                   
                                if ($isHoliday) {
                                    $updateData['status'] = 0;
                                    $updateData['final_remark'] = 2;
                                    $hrDetailHistory['stage_id'] = 0;
                                } else {
                                    $earlyAlready = \App\Models\Backend\HrDetailHistory::where("date", $value->date)->where('stage_id', 2)->where('user_id', $value->user_id)->count();
                                    if ($earlyAlready == 0) {
                                        $startDate = date('Y-m-01');
                                        $endDate = date('Y-m-t');
                                        $earlyAlready = \App\Models\Backend\HrDetailHistory::whereBetween('date', [$startDate, $endDate])
                                                        ->where('stage_id', 2)->where('user_id', $value->user_id)->count();
                                        if ($earlyAlready > 2) {
                                            $updateData['status'] = 1;
                                            $updateData['final_remark'] = 0;
                                        } else {
                                            $updateData['remark'] = 4;
                                            $updateData['status'] = 2;
                                            $updateData['final_remark'] = 0;
                                            $hrDetailHistory['stage_id'] = 2;
                                        }
                                    } else {
                                        $updateData['remark'] = 4;
                                        $updateData['status'] = 2;
                                        $updateData['final_remark'] = 0;
                                        $hrDetailHistory['stage_id'] = 2;
                                    }
                                }
                            }
                        } else if (($timesheetUnit >= 40 && $timesheetUnit < 70) || ($value->location_id != 7 && $workingTime >= '04:00:00')) {
                            // Working unit greater than or equal 40 and less than 70 then consider as early leaving 
                            if ($timesheetUnit < 40) {
                                //$updateData['remark'] = 0;
                                $updateData['final_remark'] = 3;
                                $hrDetailHistory['stage_id'] = 0;
                            } else {
                                $updateData['final_remark'] = 1;
                                $hrDetailHistory['stage_id'] = 0;
                            }
                        } else {
                            //$updateData['remark'] = 5;
                            $updateData['final_remark'] = 3;
                            $hrDetailHistory['stage_id'] = 5;
                        }

                        if ($isSundayOrHoliday == 'Sunday') {
                            $updateData['remark'] = 2;
                        } else if ($isSundayOrHoliday == 'Holiday') {
                            $updateData['remark'] = 1;
                        }
                        if (in_array($isSundayOrHoliday, ['Sunday', 'Holiday']) && $updateData['final_remark'] == 3) {
                            $updateData['final_remark'] = 0;
                        }
                        if ($value->remark == 3) {
                            $updateData['remark'] = 3;
                        }
                        if (!empty($updateData)) {// if already aaprove then data not update
                            /* if ($value->status == 5) {
                              $updateData['status'] = 5;
                              $updateData['final_remark'] = $value->final_remark;
                              } */

                            if ($updateData['status'] == 2) {
                                \App\Models\Backend\HrDetail::where('hr_detail.id', $value->id)->update($updateData);
                            } else if (isset($updateData['final_remark']) && $value->final_remark != $updateData['final_remark']) {
                                \App\Models\Backend\HrDetail::where('hr_detail.id', $value->id)->update($updateData);
                            } else if ($value->remark == 3 && $value->status != $updateData['status'] && $value->status == 0) {
                                \App\Models\Backend\HrDetail::where('hr_detail.id', $value->id)->update($updateData);
                            }
                        }

                        if (!empty($hrDetailHistory)) {
                            $hrDetail = \App\Models\Backend\HrDetailHistory::where("hr_detail_id", $value->id)
                                            ->where("date", $updateDate)->where("stage_id", $hrDetailHistory['stage_id']);
                            if ($hrDetail->count() == 0) {
                                \App\Models\Backend\HrDetailHistory::insert($hrDetailHistory);
                            }
                        }
                    }
                }
            }
            return true;
        } catch (Exception $ex) {
            app('log')->error("Hr listing failed : " . $ex->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Hr", ['error' => 'Server error.']);
        }
    }

    public static function monthEndUpdateRemark() {
        // try {      

        $userException = \App\Models\Backend\Constant::select('constant_value')->where('constant_name', 'NOT_INCLUDE_FILL_TIMESHEET')->get();
        $userExceptionId = explode(',', $userException[0]->constant_value);
        $date = date('Y-m-d');
        $day = date("d");

        $startDate = date('Y-m-26', strtotime("-1 month", strtotime($date)));
        $endDate = date('Y-m-25');

        // Add condition for if unit is zero then add leave for that day
        \App\Models\Backend\HrDetail::where('date', '>=', $startDate)->where('date', '<=', $endDate)
                ->where("is_holiday", "=", "0")->where("unit", "<", "40")->update(["final_remark" => "3", "hr_final_remark" => "3"]);

        //Add condition for holiday
        \App\Models\Backend\HrDetail::where('date', '>=', $startDate)->where('date', '<=', $endDate)
                ->where("is_holiday", "=", "1")->where("unit", "<", "40")->update(["remark" => "0"]);

        \App\Models\Backend\HrDetail::where('date', '>=', $startDate)->where('date', '<=', $endDate)
                ->where("is_holiday", "=", "1")->where("unit", ">=", "40")->where("unit", "<", "70")->update(["remark" => "1", "final_remark" => "1"]);

        \App\Models\Backend\HrDetail::where('date', '>=', $startDate)->where('date', '<=', $endDate)
                ->where("is_holiday", "=", "1")->where("unit", ">=", "70")->update(["remark" => "1", "final_remark" => "2"]);

        /* \App\Models\Backend\HrDetail::where('date','>=', $startDate)->where('date','<=',$endDate)
          ->where("is_exception","=","1")->where("unit",">=","70")->update(["remark"=>"1","final_remark" => "2"]); */


        $hrDetail = \App\Models\Backend\HrDetail::getMonthlyUserDataOnDate();
        if (!empty($userExceptionId)) {
            $hrDetail = $hrDetail->whereNotIn('hr_detail.user_id', $userExceptionId);
        }
        //$hrDetail = $hrDetail->where("hr_detail.user_id",1088);
        $hrDetail = $hrDetail->where("hr_detail.date", ">=", $startDate)->where("hr_detail.date", "<=", $endDate);

        $hrDetail = $hrDetail->where("hr_detail.hr_final_remark", "3")->get();
        if (count($hrDetail) != 0) {
            foreach ($hrDetail as $value) {
                if ($value->punch_out == null && $value->hr_final_remark == 3) {
                    $userId = $value->user_id;
                    $date = $value->date;
                    $oldHrDetail = \App\Models\Backend\HrDetail::where("user_id", $userId)->where("hr_final_remark", "3")
                            ->whereRaw("date < '" . $date . "'")
                            ->where("date", ">=", $startDate)->where("date", "<=", $endDate)
                            ->orderBy("date", "desc");
                    if ($oldHrDetail->count() > 0) {
                        $oldHrDetail = $oldHrDetail->first();
                        $fdate = $oldHrDetail->date;
                        $tdate = $date;
                        $date_join = date_create($fdate);
                        $date_today = date_create($tdate);
                        $days = $date_today->diff($date_join)->format("%a");
                        if ($days > 2) {
                            $hdetail = \App\Models\Backend\HrDetail::where("user_id", $userId)
                                            ->whereRaw("date > '" . $fdate . "' && date < '" . $tdate . "'")->get();
                            $hrIds = array();
                            foreach ($hdetail as $h) {
                                $isSundayOrHoliday = todayisSundayOrHoliday($h->date, $h->shift_id);
                                if ($isSundayOrHoliday == '') {
                                    $hrIds = array();
                                    break;
                                } else {
                                    $hrIds[] = $h->id;
                                }
                            }
                            if (!empty($hrIds) && count($hrIds) > 1) {
                                $hrIds = implode(",", $hrIds);
                                \App\Models\Backend\HrDetail::whereRaw("id IN ($hrIds)")->update(["remark" => "6", "final_remark" => "3", "hr_final_remark" => 3]);
                                $hrIds = array();
                            }
                        }
                    }
                } //exit;
            }
        }

        // For New joinee remove holiday for first 2 days

        $firstDate = date("Y-m-01");
        $lastDate = date("Y-m-28");
        $newjoin = \App\Models\User::where("is_active", "1")
                ->whereRaw("user_joining_date >= '" . $firstDate . "' and user_joining_date <='" . $lastDate . "'");
        if ($newjoin->count() > 0) {
            foreach ($newjoin->get() as $n) {
                $enddate = date("Y-m-d", strtotime("+1 days", strtotime($n->user_joining_date)));
                $hrDetail = \App\Models\Backend\HrDetail::where("user_id", $n->id)
                        ->where("date", ">=", $n->user_joining_date)
                        ->where("date", "<=", $enddate)
                        ->update(["final_remark" => "0", "hr_final_remark" => "0"]);
            }
        }
        //return true;
        /* }
          catch (Exception $ex) {
          app('log')->error("Hr listing failed : " . $ex->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Hr", ['error' => 'Server error.']);
          } */
    }

    public static function fetchInOut(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'currentDate' => 'required|date_format:Y-m-d',
                'location' => 'numric',
                    ], []);

            if ($validator->fails()) { // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);
            }
            $todayDate = date("Y-m-d", strtotime($request->input('currentDate')));

            $obj = new \App\Console\Commands\HrBioTime();
            $punchUser = $obj->fetchQuery($todayDate);

            self::addInOut($punchUser, $todayDate);
            self::updateRemark($todayDate);

            return createResponse(config('httpResponse.SUCCESS'), "In out and Remark Added Sucessfully", ['message' => 'In out and Remark Added Sucessfully']);
        } catch (Exception $ex) {
            app('log')->error("Hr listing failed : " . $ex->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Hr", ['error' => 'Server error.']);
        }
    }

    public static function addInOut($punchUser, $todayDate) {
        ini_set('max_execution_time', '0');

        $hrDetailId = \App\Models\Backend\HrDetail::where('date', $todayDate)->pluck('id', 'user_id')->toArray();
        $userDetail = \App\Models\User::where("is_active", "1")->pluck('id', 'user_bio_id')->toArray();

        foreach ($punchUser as $bioId => $userValue) {
            if (isset($userDetail[$bioId])) {
                $userId = $userDetail[$bioId];
                $i = 0;
                $isExpection = 0;
                $insertArray = array();
                foreach ($userValue as $key => $value) {

                    //Store previous array 
                    $userKey = $key;
                    if ($key != 0) {
                        $userKey = $key - 1;
                    }
                    $previousArray = $userValue[$userKey];
                    $dateTime = explode(" ", $value['DateT']);

                    $userArray['hr_detail_id'] = isset($hrDetailId[$userId]) ? $hrDetailId[$userId] : '0';
                    $userArray['user_id'] = $userId;
                    $userArray['entry_from'] = 2;
                    $userArray['date'] = $dateTime[0];
                    $userArray['office_location'] = $value['location'];
                    $tDate = date('Y-m-d');
                    $time = date('H:i:s');
                    if ($key == 0 && isset($hrDetailId[$userId])) {
                        // insert first Punch in IN Hr detail Table
                        $remark = self::lateComingRemark($dateTime[1], $hrDetailId[$userId]);
                        $userDetailHr = \App\Models\Backend\HrDetail::where("id", $hrDetailId[$userId])->first();
                        if ($userDetailHr->punch_in != $dateTime[1]) {
                            $updateInData = array();
                            if ($remark == 3 && $userDetailHr->daily_email_send != 1) {
                                $updateInData['daily_email_send'] = 0;
                            }
                            $updateInData['punch_in'] = $dateTime[1];
                            $updateInData['office_location'] = $value['location'];
                            $updateInData['remark'] = $remark;
                            $updateInData['status'] = 0;
                            $updateInData['modified_by'] = 1;
                            $updateInData['modified_on'] = date('Y-m-d H:i:s');
                            \App\Models\Backend\HrDetail::where('id', $hrDetailId[$userId])->update($updateInData);
                        }
                        if ($value['Mode'] == 0) {
                            $isExpection = 1;
                            //if user IN time missing then system add automatic
                            $userArray['punch_time'] = $dateTime[1];
                            $userArray['type'] = 0;
                            $userArray['punch_type'] = 1;
                            $insertArray[$i] = $userArray;
                            $i++;
                        }
                    } else if ($key != 0 && $previousArray['Mode'] == $value['Mode']) {
                        $isExpection = 1;
                        $userArray['punch_time'] = $dateTime[1];
                        $userArray['type'] = 0;
                        $userArray['punch_type'] = ($value['Mode'] == 0) ? 1 : 0;

                        if ($value['Mode'] == 1) {
                            $priousdateTime = explode(" ", $previousArray['DateT']);
                            $userArray['punch_time'] = $priousdateTime[1];
                        }
                        $insertArray[$i] = $userArray;
                        $i++;
                    } else if (count($userValue) == $key && $value['Mode'] == 1) {
                        $isExpection = 1;
                        if (($tDate == $todayDate && $time == '10:00:00') || $tDate != $todayDate) {
                            $userArray['punch_time'] = $dateTime[1];
                            $userArray['type'] = 0;
                            $userArray['punch_type'] = 0;
                        }
                    }

                    $userArray['punch_time'] = $dateTime[1];
                    $userArray['type'] = 1;
                    $userArray['punch_type'] = $value['Mode'];

                    $insertArray[$i] = $userArray;
                    $i++;
                }
                //showArray($insertArray);exit;
                // insert user wise data
                $checkInOut = \App\Models\Backend\HrUserInOuttime::where("user_id", $userId)->where('date', $todayDate);

                if (count($insertArray) >= $checkInOut->count()) {
                    $checkInOut = $checkInOut->get()->toArray();
                    foreach ($insertArray as $key => $row) {
                        if (isset($checkInOut[$key])) {
                            $row['modified_on'] = date('Y-m-d H:i:s');
                            $row['modified_by'] = 1;
                            \App\Models\Backend\HrUserInOuttime::where("id", $checkInOut[$key]['id'])->update($row);
                        } else {
                            $row['created_on'] = date('Y-m-d H:i:s');
                            $row['created_by'] = 1;
                            \App\Models\Backend\HrUserInOuttime::create($row);
                        }
                    }
                } else {
                    $checkInOut = $checkInOut->get()->toArray();
                    foreach ($checkInOut as $key => $row) {
                        if (isset($insertArray[$key])) {
                            $row['modified_on'] = date('Y-m-d H:i:s');
                            $row['modified_by'] = 1;
                            \App\Models\Backend\HrUserInOuttime::where("id", $row['id'])->update($insertArray[$key]);
                        } else {
                            \App\Models\Backend\HrUserInOuttime::where("id", $row['id'])->delete();
                        }
                    }
                }
                $WorkingDetail = getWorkingTime($userId, $todayDate);
                if (isset($hrDetailId[$userId])) {
                    $updateOutData = array();
                    $updateOutData['is_exception'] = $isExpection;
                    $updateOutData['punch_out'] = $WorkingDetail['punch_out'];
                    $updateOutData['working_time'] = $WorkingDetail['working_time'];
                    $updateOutData['break_time'] = $WorkingDetail['break_time'];
                    $updateOutData['modified_by'] = 1;
                    $updateOutData['modified_on'] = date('Y-m-d H:i:s');
                    \App\Models\Backend\HrDetail::where('id', $hrDetailId[$userId])->update($updateOutData);
                }
            }
        }
    }

    public static function lateComingRemark($punchInTime, $hrDetailId) {
        $hrDetail = \App\Models\Backend\HrDetail::select('shift_id', 'shift_from_time', 'grace_period', 'date')->find($hrDetailId);
        $lateComingAllowTime = self::addTime(date("H:i", strtotime($hrDetail->shift_from_time)), date("H:i", strtotime($hrDetail->grace_period)));
        $lateComingAllowTime = strtotime(date("H:i:59", strtotime($lateComingAllowTime)));
        $punchInTime = strtotime($punchInTime);
        $isSundayOrHoliday = todayisSundayOrHoliday($hrDetail->date, $hrDetail->shift_id);
        if ($isSundayOrHoliday == 'Sunday') {
            $remark = 2;
        } else if ($isSundayOrHoliday == 'Holiday') {
            $remark = 1;
        } else {
            $remark = 0;
            if ($punchInTime > $lateComingAllowTime) {
                $remark = 3;
            }
        }
        return $remark;
    }

    public static function addTime($start, $end) {
        $start = explode(":", $start);
        $end = explode(":", $end);

        $h = ((int) ($end[0]) + (int) ($start[0]));
        $m = ((int) ($end[1]) + (int) ($start[1]));

        if ($m == 60) {
            $h = (int) ($h) + 1;
            $m = 0;
        }
        if ($m > 60) {
            $h = (int) ($h) + 1;
            $m = (int) ($m) - 60;
        }

        $h = ($h < 10 ) ? "0" . $h : $h;
        $m = ($m < 10 ) ? "0" . $m : $m;

        $datetext = $h . ":" . $m;
        return $datetext;
    }

}
