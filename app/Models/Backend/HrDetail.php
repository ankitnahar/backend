<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class HrDetail extends Model {

    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'hr_detail';
    protected $hidden = [];
    public $timestamps = false;

    public function assignee() {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id')->with('firstApproval:id,userfullname,email,user_image')->with('secondApproval:id,userfullname,email,user_image');
    }

//    public function firstApprovalBy() {
//        return $this->belongsTo(\App\Models\User::class, 'first_approval_by', 'id');
//    }
//
//    public function secondApprovalBy() {
//        return $this->belongsTo(\App\Models\User::class, 'second_approval_by', 'id');
//    }
//
//    public function userAssignee() {
//        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id')->with('firstApprovalBy:id,userfullname,email')->with('secondApprovalBy:id,userfullname,email');
//    }

    public function timesheet() {
        return $this->hasMany(\App\Models\Backend\Timesheet::class, 'hr_detail_id', 'id');
    }

    public function inout() {
        $designation = getLoginUserHierarchy();
        if ($designation->designation_id == 7)
            return $this->hasMany(\App\Models\Backend\HrUserInOuttime::class, 'hr_detail_id', 'id')->orderBy('punch_time', 'asc')->orderBy('id', 'asc');
        else
            return $this->hasMany(\App\Models\Backend\HrUserInOuttime::class, 'hr_detail_id', 'id')->where('type', 1)->orderBy('punch_time', 'asc')->orderBy('id', 'asc');
    }

    public function maxmininout() {
        return $this->belongsTo(\App\Models\Backend\HrUserInOuttime::class, 'id', 'hr_detail_id')->orderBy('punch_time', 'asc');
    }

    public function timesheetTotalUnit() {
        return $this->belongsTo(\App\Models\Backend\Timesheet::class, 'id', 'hr_detail_id')->select(app('db')->raw('SUM(units) as totalUnit'));
    }

    public function shift_id() {
        return $this->belongsTo(\App\Models\Backend\HrShift::class, 'shift_id', 'id');
    }

    public function firstApproval() {
        return $this->belongsTo(\App\Models\Backend\Hrdetailcomment::class, 'id', 'hr_detail_id')->with('comment_by:id,userfullname,email')->where('type', 1);
    }

    public function secondApproval() {
        return $this->belongsTo(\App\Models\Backend\Hrdetailcomment::class, 'id', 'hr_detail_id')->with('comment_by:id,userfullname,email')->where('type', 2);
    }

    public function rejectionDetail() {
        return $this->belongsTo(\App\Models\Backend\Hrdetailcomment::class, 'id', 'hr_detail_id')->with('comment_by:id,userfullname,email')->where('status', 0)->where('type', 3);
    }

    public function hrDetailId() {
        return $this->hasMany(\App\Models\Backend\Hrdetailcomment::class, 'hr_detail_id', 'id');
    }

    public static function arrangeReportData($data,$month) {
        $i = 0;
        $userData = array();
        
        foreach ($data->toArray() as $key => $value) {
            $userAbsent = $userHoliday = '';
            if ($value['absent'] != 0 || $value['half_day_absent'] != 0) {
                $userAbsent = $value['absent'] + $value['half_day_absent'];
            }
            if ($value['holiday_working'] != 0 || $value['half_holiday_working'] != 0) {
                $userHoliday = $value['holiday_working'] + $value['half_holiday_working'];
            }
            $value['month'] = $month;
            $value['userHolidayworking'] = $userHoliday;
            $value['userAbsent'] = $userAbsent;
            //$userData[$value['user_id']] = $value;
            $userData[] = $value;            
            $i++;
        }
        return $userData;
    }

    public static function arrangeDailyReportData($data) {
        $i = 0;
        $userData = array();
        foreach ($data->toArray() as $key => $value) {
            if (!empty($value['inout'])) {
                $userData[] = $value;
            }
            $i++;
        }
        return $userData;
    }

    public static function getUserDataOnDate($updateDate) {
        
        return \App\Models\Backend\HrDetail::select('hr_detail.*', 'u.location_id', 
                app('db')->raw("sum(t.units) as TimesheetUnit,CAST(TIMEDIFF((TIMEDIFF(hr_detail.shift_to_time,hr_detail.shift_from_time)),hr_detail.allow_break) AS TIME) AS shiftTime"))
                        ->leftJoin('timesheet as t', function($query) {
                            $query->on('t.user_id', '=', 'hr_detail.user_id');
                            $query->on('t.hr_detail_id', '=', 'hr_detail.id');
                        })
                        ->leftjoin('user as u', 'u.id', '=', 'hr_detail.user_id')->where('hr_detail.date', $updateDate);
    }
    
    public static function getMonthlyUserDataOnDate() {
        
        return \App\Models\Backend\HrDetail::select('hr_detail.*', 'u.location_id', 
                app('db')->raw("CAST(TIMEDIFF((TIMEDIFF(hr_detail.shift_to_time,hr_detail.shift_from_time)),hr_detail.allow_break) AS TIME) AS shiftTime"))
                        ->leftjoin('user as u', 'u.id', '=', 'hr_detail.user_id');
    }

}
