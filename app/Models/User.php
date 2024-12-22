<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;
use DB;

class User extends Model implements AuthenticatableContract, JWTSubject {

    use Authenticatable,
        Authorizable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
    protected $table = 'user';

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];
    public $timestamps = false;

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier() {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims() {
        return [];
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 25, 2018
     * Purpose: get all user listing
     */

    public static function getUser() {
        return User::where('is_active', 1)->get()->pluck('userfullname', 'id')->toArray();
    }

    // get list user wise
    public static function userData() {
        return User::leftjoin('user_hierarchy as uh', 'uh.user_id', '=', 'user.id')
                        ->with('createdBy:userfullname as created_by,id', 'timesheetApproval:userfullname,id,email', 'shiftId:shift_name,id', 'locationId:location_name,id', 'designationId:designation_name,id', 'firstApproval:userfullname as first_approval_user,id', 'secondApproval:userfullname as second_approval_user,id', 'departmentId:department_name,id')
                        ->leftjoin('team as t', function($join) {
                            $join->whereRaw('FIND_IN_SET(t.id,uh.team_id)');
                        })
                        ->select('user.*', 'uh.designation_id', 'uh.team_id', DB::raw('GROUP_CONCAT(t.team_name) as team_name'), 'uh.other_right', 'uh.department_id', 'uh.parent_user_id');
    }

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function timesheetApproval() {
        return $this->belongsTo(\App\Models\User::class, 'timesheet_approval_user', 'id');
    }

    public function shiftId() {
        return $this->belongsTo(Backend\HrShift::class, 'shift_id', 'id');
    }

    public function locationId() {
        return $this->belongsTo(Backend\HrLocation::class, 'location_id', 'id');
    }

    public function designationId() {
        return $this->belongsTo(Backend\Designation::class, 'designation_id', 'id');
    }

    public function departmentId() {
        return $this->belongsTo(Backend\Department::class, 'department_id', 'id');
    }

    //Get user designation wise
    public static function getDesignationwiseUser($designation_id) {
        return User::leftjoin('user_hierarchy as uh', 'uh.user_id', 'user.id')
                        ->where("uh.designation_id", "=", $designation_id)
                        ->where("user.is_active", "=", 1);
    }

    //User right report
    public static function userRightReport() {
        return User::leftjoin('user_hierarchy as uh', 'uh.user_id', '=', 'user.id')
                        ->leftjoin('designation as d', 'd.id', '=', 'uh.designation_id')
                        ->leftjoin('user_tab_right as ut', 'ut.user_id', '=', 'user.id')
                        ->leftjoin('tabs as t', 't.id', '=', 'ut.tab_id')
                        ->leftjoin('tab_button as tb', function($join) {
                            $join->whereRaw('tb.id IN (ut.other_right)');
                        })
                        ->select(['user.userfullname', 'user.user_bio_id', 'd.designation_name', 't.tab_name', 'ut.view', 'ut.add_edit', DB::raw("GROUP_CONCAT(tb.button_name) as buttonName")])
                        ->where("uh.designation_id", "!=", "7")
                        ->groupby("user.id", "t.id")
                        ->get()->toArray();
    }

    public function assignee() {
        return $this->belongsTo(\App\Models\User::class, 'user_id', 'id');
    }

    /*
     * Created by - Pankaj
     * save history when user information update 
     */

    public static function boot() {
        parent::boot();
        self::updating(function($user) {
            $col_name = [
                'user_fname' => 'first name',
                'user_lname' => 'last name',
                'user_login_name' => 'Login name',
                'user_bio_id' => 'Bio matric id',
                'user_writeoff' => 'Befree writeoff',
                'is_active' => 'Login Access',
                'shift_id' => 'Shift name',
                'location_id' => 'Location',
                'user_timesheet_fillup_flag' => 'user timesheet fillup flag',
                'user_birthdate' => 'user birthdate',
                'user_image' => 'user image',
                'leave_allow' => 'Leave allow for',
                'first_approval_user' => 'First approval user',
                'second_approval_user' => 'Second approval user',
                'writeoffstaff' => 'Befree writeoff approval staff',
                'timesheet_approval_user' => 'Missed timesheet approval staff',
                'user_joning_date' => 'User joining date',
                'user_type' => 'User type',
                'user_left_date' => 'User Left Date',
                'probation_date' => 'Probation Date',
                'send_email' => 'Send Email'
            ];
            $changesArray = \App\Http\Controllers\Backend\User\UserController::saveHistory($user, $col_name);
            $type = 'user_detail'; //default
            if (isset($changesArray['password'])) { //when update password
                $type = 'change_password';
            }
            $updatedBy = loginUser();
            if (isset($changesArray['first_approval_user']) || isset($changesArray['second_approval_user']) || isset($changesArray['writeoffstaff']) || isset($changesArray['timesheet_approval_user'])) {
                DB::table("user_hierarchy_audit")->insert([
                    'user_id' => $user->id,
                    'changes' => json_encode($changesArray),
                    'modified_on' => date('Y-m-d H:i:s'),
                    'modified_by' => $updatedBy
                ]);
            } else {

                //Insert value in audit table
                if (!empty($changesArray)) {
                    Backend\UserAudit::create([
                        'user_id' => $user->id,
                        'changes' => json_encode($changesArray),
                        'type' => $type,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $updatedBy
                    ]);
                }
            }
        });
    }

    public function firstApproval() {
        return $this->belongsTo(\App\Models\User::class, 'first_approval_user', 'id');
    }

    public function secondApproval() {
        return $this->belongsTo(\App\Models\User::class, 'second_approval_user', 'id');
    }

    public function timesheetApprovalUser() {
        return $this->belongsTo(\App\Models\User::class, 'timesheet_approval_user', 'id');
    }

    public static function getAllUserName($user_ids) {
        //echo $user_ids;exit;
        if ($user_ids != '') {
            $user = User::select(DB::raw("GROUP_CONCAT(userfullname) as username"))->whereRaw("id IN ($user_ids)")
                    ->where("is_active","1")->where("send_email","1")->first();
            return $user->username;
        } else {
            return;
        }
    }

    /*
     * Creaetd by: Jayesh Shingrakhiya
     * Created on: Aug 21, 2018
     * Reason: To get single or multiple user details.
     * @$param int $user_id
     */

    public static function userDetail($user_id) {
        $user = User::find($user_id);
        return $user;
    }

    /*
     * Creaetd by: Jayesh Shingrakhiya
     * Created on: Sept 21, 2018
     * Reason: To checkout user designation
     * @$param int $user_id
     */

    public static function userDegignation($user_id) {
        $user = Backend\UserHierarchy::where('user_id', $user_id)->get();
        return $user;
    }

}
