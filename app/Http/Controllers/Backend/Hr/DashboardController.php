<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrDetail;
use App\Models\Backend\PendingTimesheet;

//use confi
//use App\Models\User;

class DashboardController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Fetch attendance summary data
     */

    public function index(Request $request) {
        //try {
        $dashboardData = array();
        $user = getLoginUserHierarchy();
        $yearMonth = $request->get('yearMonth');
        $id = app('auth')->guard()->id();

        $pendingTimesheet = $teamPendingTimesheet = $approvePendingTimesheet = $teamApprovePendingTimesheet = 0;
        $month = $yearMonth . '-25';
        $currentYearMonth = date("Y-m");
        if ($currentYearMonth == $yearMonth) {
            $day = date("d");
            $date = date('Y-m-d');
            if ($day > 27) {
                $startDate = date('Y-m-26');
                $endDate = date('Y-m-25', strtotime("+1 month", strtotime($date)));
            } else {
                $startDate = date('Y-m-26', strtotime("-1 month", strtotime($date)));
                $endDate = date('Y-m-28');
            }
        } else {
            $startDate = date('Y-m-26', strtotime("-1 month", strtotime($month)));
            $endDate = date('Y-m-25', strtotime($yearMonth));
        }
        if ($user->designation_id == 7) {
            $notIn = \App\Models\User::whereRaw('((leave_allow = 13 AND location_id = 7) OR (location_id != 7 AND leave_allow != 13))')->get()->pluck('id', 'id')->toArray();
            $hrDetail = HrDetail::whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')->whereRaw('user_id IN(' . implode(",", $notIn) . ")")->get();
            $pendingTimsheet = PendingTimesheet::select(app('db')->raw('stage_id, COUNT(stage_id) as stageCounter'))->whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')->whereIn('stage_id', [0, 3])->groupBy('stage_id')->get()->toArray();

            foreach ($pendingTimsheet as $keyAdmin => $valueAdmin) {
                if (isset($valueAdmin['stage_id'])) {
                    if ($valueAdmin['stage_id'] == 3)
                        $teamApprovePendingTimesheet = isset($valueAdmin['stageCounter']) ? $valueAdmin['stageCounter'] : 0;

                    if ($valueAdmin['stage_id'] == 0)
                        $teamPendingTimesheet = isset($valueAdmin['stageCounter']) ? $valueAdmin['stageCounter'] : 0;
                }
            }

            $view = 'adminView';
        } else {
            $userDetail = \App\Models\User::select('id')->whereRaw('((leave_allow = 13 AND location_id = 7) OR (location_id != 7 AND leave_allow != 13))')->where('first_approval_user', $id)->Orwhere('second_approval_user', $id)->Orwhere('id', $id)->pluck('id', 'id')->toArray();
            $pendingTimsheet = PendingTimesheet::select(app('db')->raw('stage_id,COUNT(stage_id) as stageCounter'))->whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')->whereIn('stage_id', [0, 3])->groupBy('stage_id')->where('user_id', app('auth')->guard()->id())->get()->toArray();

            foreach ($pendingTimsheet as $keyMy => $valueMy) {
                if (isset($valueMy['stage_id'])) {
                    if ($valueMy['stage_id'] == 3)
                        $approvePendingTimesheet = isset($valueMy['stageCounter']) ? $valueMy['stageCounter'] : 0;

                    if ($valueMy['stage_id'] == 0)
                        $pendingTimesheet = isset($valueMy['stageCounter']) ? $valueMy['stageCounter'] : 0;
                }
            }

            $hrDetail = HrDetail::whereRaw('user_id IN(' . implode(",", $userDetail) . ')')->whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')->get();
            $userTimesheetApproval = \App\Models\User::where('timesheet_approval_user', $id)->pluck('id', 'id')->toArray();

            $tpendingTimsheet = PendingTimesheet::select(app('db')->raw('stage_id, COUNT(stage_id) as stageCounter'))->whereIn('user_id', $userTimesheetApproval)->whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')->whereIn('stage_id', [0, 3])->groupBy('stage_id')->get()->toArray();

            foreach ($tpendingTimsheet as $keyTeam => $valueTeam) {
                if (isset($valueTeam['stage_id'])) {
                    if ($valueTeam['stage_id'] == 3)
                        $teamApprovePendingTimesheet = isset($valueTeam['stageCounter']) ? $valueTeam['stageCounter'] : 0;

                    if ($valueTeam['stage_id'] == 0)
                        $teamPendingTimesheet = isset($valueTeam['stageCounter']) ? $valueTeam['stageCounter'] : 0;
                }
            }
            $view = 'teamView';
        }



        $totalHoliday = $totalLeave = $pendingRequest = $pendingForApproval = $approved = $rejected = $lateComing = $earlyLeaving = $halfDay = $leave = $teamPendingRequest = $teamPendingForApproval = $teamApproved = $teamRejected = $teamLateComing = $teamEarlyLeaving = $teamHalfDay = $teamLeave = 0;

        $leaveRequest = \App\Models\Backend\HrLeaveRequest::where('first_approval', $id)
                        ->where("status_id", "3")->count();
        $leaveRequest1 = \App\Models\Backend\HrLeaveRequest::where('second_approval', $id)
                        ->where("status_id", "4")->count();

        $holidayRequest = \App\Models\Backend\HrHolidayRequest::where('first_approval', $id)
                        ->whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')
                        ->where("status_id", "3")->count();
        $holidayRequest1 = \App\Models\Backend\HrHolidayRequest::where('second_approval', $id)
                        ->whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')
                        ->where("status_id", "4")->count();

        $totalLeave = $leaveRequest + $leaveRequest1;
        $totalHoliday = $holidayRequest + $holidayRequest1;

        if (count($hrDetail) > 0) {
            foreach ($hrDetail as $key => $value) {
                $userApproval = \App\Models\User::where("id", $value->user_id)->first();
                if ($id == $value->user_id && $user->designation_id != 7) {
                    if ($value->status == 2)
                        $pendingRequest = $pendingRequest + 1;
                    else if ($value->status == 3 && $userApproval->first_approval_user == $id) {
                        $pendingForApproval = $pendingForApproval + 1;
                    } else if ($value->status == 4 && $userApproval->second_approval_user == $id) {
                        $pendingForApproval = $pendingForApproval + 1;
                    } else if ($value->status == 5)
                        $approved = $approved + 1;
                    else if ($value->status == 6)
                        $rejected = $rejected + 1;

                    if ($value->final_remark == 1)
                        $halfDay = $halfDay + 1;
                    else if ($value->remark == 4)
                        $earlyLeaving = $earlyLeaving + 1;
                    else if ($value->remark == 3)
                        $lateComing = $lateComing + 1;
                    else if ($value->final_remark == 3)
                        $leave = $leave + 1;

//                    if (isset($setPendingTimesheet[$id][$value->date]['request']))
//                        $pendingTimesheet = $pendingTimesheet + 1;
//                    else if (isset($setPendingTimesheet[$id][$value->date]['approved']))
//                        $approvePendingTimesheet = $approvePendingTimesheet + 1;

                    $dashboardData['myView']['pendingRequest'] = $pendingRequest;
                    $dashboardData['myView']['pendingForApproval'] = $pendingForApproval;
                    $dashboardData['myView']['approved'] = $approved;
                    $dashboardData['myView']['rejected'] = $rejected;
                    $dashboardData['myView']['latecoming'] = $lateComing;
                    $dashboardData['myView']['earlyleaving'] = $earlyLeaving;
                    $dashboardData['myView']['halfday'] = $halfDay;
                    $dashboardData['myView']['leave'] = $leave;
                    $dashboardData['myView']['pendingTimesheet'] = $pendingTimesheet;
                    $dashboardData['myView']['approvePendingTimesheet'] = $approvePendingTimesheet;
                    $dashboardData['myView']['LeaveRequest'] = $totalLeave;
                    $dashboardData['myView']['HoildayRequest'] = $totalHoliday;
                }else {
                    if ($value->status == 2)
                        $teamPendingRequest = $teamPendingRequest + 1;

                    if ($value->status == 3 && $userApproval->first_approval_user == $id) {
                        $teamPendingForApproval = $teamPendingForApproval + 1;
                    }
                    if ($value->status == 4 && $userApproval->second_approval_user == $id) {
                        $teamPendingForApproval = $teamPendingForApproval + 1;
                    }
                    if ($value->status == 5)
                        $teamApproved = $teamApproved + 1;

                    if ($value->status == 6)
                        $teamRejected = $teamRejected + 1;

                    if ($value->final_remark == 1)
                        $teamHalfDay = $teamHalfDay + 1;

                    if ($value->remark == 4)
                        $teamEarlyLeaving = $teamEarlyLeaving + 1;

                    if ($value->remark == 3)
                        $teamLateComing = $teamLateComing + 1;

                    if ($value->final_remark == 3)
                        $teamLeave = $teamLeave + 1;

                    $dashboardData[$view]['pendingRequest'] = $teamPendingRequest;
                    $dashboardData[$view]['pendingForApproval'] = $teamPendingForApproval;
                    $dashboardData[$view]['approved'] = $teamApproved;
                    $dashboardData[$view]['rejected'] = $teamRejected;
                    $dashboardData[$view]['latecoming'] = $teamLateComing;
                    $dashboardData[$view]['earlyleaving'] = $teamEarlyLeaving;
                    $dashboardData[$view]['halfday'] = $teamHalfDay;
                    $dashboardData[$view]['leave'] = $teamLeave;
                    $dashboardData[$view]['pendingTimesheet'] = $teamPendingTimesheet;
                    $dashboardData[$view]['approvePendingTimesheet'] = $teamApprovePendingTimesheet;
                    $dashboardData[$view]['LeaveRequest'] = $totalLeave;
                    $dashboardData[$view]['HoildayRequest'] = $totalHoliday;
                }
            }
        }else {
            if ($user->designation_id != 7) {
                $dashboardData['myView']['pendingRequest'] = $pendingRequest;
                $dashboardData['myView']['pendingForApproval'] = $pendingForApproval;
                $dashboardData['myView']['approved'] = $approved;
                $dashboardData['myView']['rejected'] = $rejected;
                $dashboardData['myView']['latecoming'] = $lateComing;
                $dashboardData['myView']['earlyleaving'] = $earlyLeaving;
                $dashboardData['myView']['halfday'] = $halfDay;
                $dashboardData['myView']['leave'] = $leave;
                $dashboardData['myView']['pendingTimesheet'] = $pendingTimesheet;
                $dashboardData['myView']['approvePendingTimesheet'] = $approvePendingTimesheet;
                $dashboardData['myView']['LeaveRequest'] = $totalLeave;
                $dashboardData['myView']['HoildayRequest'] = $totalHoliday;
            }

            if ((isset($userDetail) && count($userDetail) > 0 ) || $user->designation_id == 7) {
                $dashboardData[$view]['pendingRequest'] = $teamPendingRequest;
                $dashboardData[$view]['pendingForApproval'] = $teamPendingForApproval;
                $dashboardData[$view]['approved'] = $teamApproved;
                $dashboardData[$view]['rejected'] = $teamRejected;
                $dashboardData[$view]['latecoming'] = $teamLateComing;
                $dashboardData[$view]['earlyleaving'] = $teamEarlyLeaving;
                $dashboardData[$view]['halfday'] = $teamHalfDay;
                $dashboardData[$view]['leave'] = $teamLeave;
                $dashboardData[$view]['pendingTimesheet'] = $teamPendingTimesheet;
                $dashboardData[$view]['approvePendingTimesheet'] = $teamApprovePendingTimesheet;
                $dashboardData[$view]['LeaveRequest'] = $totalLeave;
                $dashboardData[$view]['HoildayRequest'] = $totalHoliday;
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), "Dashboard loaded.", ['data' => $dashboardData]);
        /* } catch (\Exception $e) {
          app('log')->error("Attendance summary listing failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing attendance summary list", ['error' => 'Server error.']);
          } */
    }

    public static function pendingRequestCalculated($yearMonth) {
        $dashboardData = array();
        $user = getLoginUserHierarchy();
        $id = app('auth')->guard()->id();

        $pendingTimesheet = $teamPendingTimesheet = $approvePendingTimesheet = $teamApprovePendingTimesheet = 0;
        $month = $yearMonth . '-25';
        $day = date("d");
         $date = date('Y-m-d');
        if ($day > 27) {           
            $startDate = date('Y-m-26');
            $endDate = date('Y-m-25', strtotime("+1 month", strtotime($date)));
        } else {
            $startDate = date('Y-m-26', strtotime("-1 month", strtotime($date)));
            $endDate = date('Y-m-28');
        }
        $userDetail = \App\Models\User::select('id')->whereRaw('((leave_allow = 13 AND location_id = 7) OR (location_id != 7 AND leave_allow != 13))')->where('first_approval_user', $id)->Orwhere('second_approval_user', $id)->Orwhere('id', $id)->pluck('id', 'id')->toArray();

        $hrDetail = HrDetail::whereRaw('user_id IN(' . implode(",", $userDetail) . ')')->whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')->get();
        $userTimesheetApproval = \App\Models\User::where('timesheet_approval_user', $id)->pluck('id', 'id')->toArray();

        $tpendingTimsheet = PendingTimesheet::select(app('db')->raw('stage_id, COUNT(stage_id) as stageCounter'))->whereIn('user_id', $userTimesheetApproval)->whereRaw('date  >= "' . $startDate . '"  and date <="' . $endDate . '"')->whereIn('stage_id', [0, 3])->groupBy('stage_id')->get()->toArray();

        foreach ($tpendingTimsheet as $keyTeam => $valueTeam) {
            if (isset($valueTeam['stage_id'])) {
                if ($valueTeam['stage_id'] == 3)
                    $teamApprovePendingTimesheet = isset($valueTeam['stageCounter']) ? $valueTeam['stageCounter'] : 0;

                if ($valueTeam['stage_id'] == 0)
                    $teamPendingTimesheet = isset($valueTeam['stageCounter']) ? $valueTeam['stageCounter'] : 0;
            }
        }
        $view = 'teamView';


        $totalHoliday = $totalLeave = $pendingRequest = $pendingForApproval = $approved = $rejected = $lateComing = $earlyLeaving = $halfDay = $leave = $teamPendingRequest = $teamPendingForApproval = $teamApproved = $teamRejected = $teamLateComing = $teamEarlyLeaving = $teamHalfDay = $teamLeave = 0;

        if (count($hrDetail) > 0) {
            foreach ($hrDetail as $key => $value) {
                $userApproval = \App\Models\User::where("id", $value->user_id)->first();
                if ($id == $value->user_id && $user->designation_id != 7) {
                    if ($value->status == 2)
                        $pendingRequest = $pendingRequest + 1;

                    $dashboardData['myView']['pendingRequest'] = $pendingRequest;
                }else {
                    if ($value->status == 3 && $userApproval->first_approval_user == $id) {
                        $teamPendingForApproval = $teamPendingForApproval + 1;
                    }
                    if ($value->status == 4 && $userApproval->second_approval_user == $id) {
                        $teamPendingForApproval = $teamPendingForApproval + 1;
                    }

                    $dashboardData[$view]['pendingForApproval'] = $teamPendingForApproval;
                }
            }
        } else {
            if ($user->designation_id != 7) {
                $dashboardData['myView']['pendingRequest'] = $pendingRequest;
            }

            if ((isset($userDetail) && count($userDetail) > 0 ) || $user->designation_id == 7) {
                $dashboardData[$view]['pendingForApproval'] = $teamPendingForApproval;
            }
        }
        return $dashboardData;
    }

}
