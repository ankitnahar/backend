<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Backend\UserAudit;
use Illuminate\Support\Facades\Input;
use DB;

/**
 * This is a user class controller.
 * 
 */
class UserController extends Controller {

    protected $returnIds = array();

    /**
     * Get user detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
              // try {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'user.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];
            $user = User::userData()->groupby("user.id");  
            
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $allias = array("is_active" => "user", "id" => "user");
                $user = search($user, $search, $allias);
            }
            // for relation ship sorting
            if ($sortBy == 'shift_name') {
                $user = $user->leftjoin("hr_shift_master as s", "s.id", "user.shift_id");
            }
            if ($sortBy == 'location_name') {
                $user = $user->leftjoin("hr_location as l", "l.id", "user.location_id");
            }
            if ($sortBy == 'designation_name') {
                $user = $user->leftjoin("designation as d", "d.id", "uh.designation_id");
            }
            if ($sortBy == 'department_name') {
                $user = $user->leftjoin("department as dp", "dp.id", "uh.department_id");
            }
            if ($sortBy == 'team_name') {
                $user = $user->leftjoin("team as t", "t.id", "uh.team_id");
            }
            if ($sortBy == 'created_by' || $sortBy == 'first_approval_user' || $sortBy == 'second_approval_user') {
                $user = $user->leftjoin("user as u", "u.id", "user.$sortBy");
                $sortBy = 'u.userfullname';
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $user = $user->orderBy($sortBy, $sortOrder)->get(['user.*']);
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $user->get()->count();

                $user = $user->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $user = $user->get();

                $filteredRecords = count($user);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {
                //format data in array 
               // $usertype = config('constant.user_type');
                $data = $user->toArray();
                $column = array();
                $column[] = ['Sr.No', 'BioMatric ID', 'First Name', 'Last Name', 'Login Name','User Type','Probation completion date','Entity','Zoho Login Name', 'Email','Department', 'Designation', 'Login Access', 'Shift Name', 'Location Name','Food provide from Office', 'User Last Login', 'User Birthdate', 'First Approval', 'Second Approval', 'Created By', 'Created On'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['user_bio_id'];
                        $columnData[] = $data['user_fname'];
                        $columnData[] = $data['user_lname'];
                        $columnData[] = $data['user_login_name'];
                        $columnData[] = ($data['user_type'] == '1') ? 'Permanent': (($data['user_type'] == '0') ? 'Probation' : 'Contractual');
                        $columnData[] = $data['probation_date'];
                        $columnData[] = $data['Entity'];
                        $columnData[] = $data['zoho_login_name'];
                        $columnData[] = $data['email'];                        
                        $columnData[] = $data['department_id']['department_name'];
                        $columnData[] = $data['designation_id']['designation_name'];
                        $columnData[] = ($data['is_active'] == '1') ? 'Active' : 'Inactive';
                        $columnData[] = $data['shift_id']['shift_name'];
                        $columnData[] = $data['location_id']['location_name'];
                        $columnData[] = ($data['is_food'] == 1) ? 'Yes' : 'No';
                        $columnData[] = $data['user_lastlogin'];
                        $columnData[] = $data['user_birthdate'];
                        $columnData[] = $data['first_approval']['first_approval_user'];
                        $columnData[] = $data['second_approval']['second_approval_user'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['created_on'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'UserList', 'xlsx', 'A1:R1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "User list.", ['data' => $user], $pager);
        /*} catch (\Exception $e) {
            app('log')->error("User listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing user", ['error' => 'Server error.']);
        }*/
    }

    /**
     * Store user details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        // try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'user_bio_id' => 'required|numeric|unique:user,user_bio_id,',
            'user_fname' => 'required',
            'user_lname' => 'required',
            'user_login_name' => 'required|unique:user,user_login_name,',
            'email' => 'required|email|unique:user,email,',
            'password' => 'required_if:password,0|min:6',
            'shift_id' => 'required|numeric',
            'location_id' => 'required|numeric',
            'is_active' => 'required|in:1,0',
            'user_birthdate' => 'date',
            'user_joining_date' => 'date',
            'leave_allow' => 'required',
            'user_image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048'
                ], ['user_bio_id.unique' => 'Bio ID has already been taken.',
            'email.unique' => 'Email has already been taken',
            'user_login_name.unique' => 'User login name has already been taken.',
            'password.min' => 'Password minimum value should be 6.']);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        //Get current user detail
        $loginUser = loginUser();
        // store client details

        $password = generateRandomString(8);
        $probationMonth = date('Y-m-d', strtotime("+6 month"));
        $user = User::create([
                    'user_bio_id' => $request->input('user_bio_id'),
                    'user_fname' => $request->input('user_fname'),
                    'user_lname' => $request->input('user_lname'),
                    'userfullname' => $request->input('user_fname') . ' ' . $request->input('user_lname'),
                    'user_login_name' => $request->input('user_login_name'),
                    'email' => $request->input('email'),
                    'password' => password_hash($password, PASSWORD_BCRYPT),
                    'user_birthdate' => ($request->has('user_birthdate')) ? date("Y-m-d", strtotime($request->input('user_birthdate'))) : '',
                    'user_joining_date' => ($request->has('user_joining_date')) ? date("Y-m-d", strtotime($request->input('user_joining_date'))) : '',
                    'user_writeoff' => $request->input('user_writeoff'),
                    'user_timesheet_fillup_flag' => $request->input('user_timesheet_fillup_flag'),
                    'total_leave' => '1.5',
                    'user_type' => 0,
                    'probation_date' => $probationMonth,
                    'is_active' => $request->input('is_active'),
                    'is_food' => $request->input('is_food'),
                    'shift_id' => $request->input('shift_id'),
                    'location_id' => $request->input('location_id'),
                    'leave_allow' => $request->input('leave_allow'),
                    'created_by' => $loginUser,
                    'created_on' => date('Y-m-d H:i:s')]
        );

        $today = date('Y-m-d');
        $join_date = $request->input('user_join_date');

        $date_join = date_create($join_date);
        $date_today = date_create($today);

        $joiningDate = strtotime($request->input('user_join_date'));
        $now = strtotime($today);

        if ($joiningDate < $now) {
            $diff = $date_today->diff($date_join)->format("%a");
            for ($i = 0; $i <= $diff; $i++) {
                $hr_date = date('Y-m-d', strtotime("+" . $i . " day", strtotime($join_date)));

                // ADD value in Hr detail
                $shiftDetail = \App\Models\Backend\HrShift::where('id', $request->input('shift_id'))->first();
                $data['user_id'] = $user->id;
                $data['shift_id'] = $request->input('shift_id');
                $data['date'] = $hr_date;
                $data['shift_from_time'] = $shiftDetail->from_time;
                $data['shift_to_time'] = $shiftDetail->to_time;
                $data['grace_period'] = $shiftDetail->grace_period;
                $data['late_period'] = $shiftDetail->late_period;
                $data['late_allowed_count'] = $shiftDetail->late_allowed_count;
                $data['allow_break'] = $shiftDetail->break_time;
                $data['remark'] = 0;
                $data['created_by'] = 1;
                $data['created_on'] = date('Y-m-d H:i:s');
                \App\Models\Backend\HrDetail::insert($data);
            }
        }

        if ($request->has('user_image')) {
            $fileName = uploadUserImage($request, 'user_image', $user->id);
            $userImage = User::where("id", $user->id)->update(['user_image' => $fileName]);
        }
        $template = \App\Models\Backend\EmailTemplate::getTemplate('UWE');
        $data = array();
        if ($template->is_active) {
            $data['to'] = $user->email;
            $data['cc'] = $template->cc;
            $data['subject'] = $template->subject;
            $msg = html_entity_decode($template->content);

            $content = str_replace("[USER]", $user->userfullname, $msg);
            $content = str_replace("[USERNAME]", $user->user_login_name, $content);
            $content = str_replace("[PASSWORD]", $password, $content);
            $data['content'] = $content;
            $data['from'] = "noreply-bdms@befree.com.au";
            $data['fromName'] = "Befree noreply";

            $store = storeMail($request, $data);
        }
        return createResponse(config('httpResponse.SUCCESS'), 'User has been added successfully', ['data' => $user]);
        /* } catch (\Exception $e) {
          dd($e->getMessage());
          exit;
          app('log')->error("User creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add user', ['error' => 'Could not add user']);
          } */
    }

    /**
     * get particular user details
     *
     * @param  int  $id   //user id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $user = User::leftjoin('user_hierarchy as uh', 'uh.user_id', '=', 'user.id')
                    ->with('createdBy', 'shiftId:shift_name,id', 'locationId:location_name,id', 'designationId:designation_name,id')
                    ->find($id);

            if (!isset($user))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The user does not exist', ['error' => 'The user does not exist']);

            //send user information
            return createResponse(config('httpResponse.SUCCESS'), 'User data', ['data' => $user]);
        } catch (\Exception $e) {
            app('log')->error("User details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get user.', ['error' => 'Could not get user.']);
        }
    }

    /**
     * update user details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // user id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'user_bio_id' => 'numeric|unique:user,user_bio_id,' . $id,
            'user_login_name' => 'unique:user,user_login_name,' . $id,
            'email' => 'email|unique:user,email,' . $id,
            'user_birthdate' => 'date',
            'user_joining_date' => 'date',
            'shift_id' => 'numeric',
            'location_id' => 'numeric',
            'is_active' => 'in:0,1'], ['user_bio_id.unique' => 'Bio ID has already been taken.',
            'email.unique' => 'Email has already been taken',
            'user_login_name.unique' => 'User login name has already been taken.',
            'password.min' => 'Password minimum value should be 6.']);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        $parentUser = \App\Models\Backend\UserHierarchy::leftjoin("user as u", "u.id", "user_hierarchy.user_id")
                        ->where("user_hierarchy.parent_user_id", $id)->where("u.is_active", "1")->count();
        if ($parentUser > 0 && $request->input('is_active') == 0) {
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => 'Can not deactive user, if you want to deactive then please change this user child hierarchy']);
        }
        $user = User::find($id);

        if (!$user)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The User does not exist', ['error' => 'The User does not exist']);
        if ($user->user_type == 1 && ($request->get('user_type') && $request->input('user_type') != 1)) {
            return createResponse(config('httpResponse.NOT_FOUND'), 'You can change permanent user type to probation or contractual', ['error' => 'You can change permanent user type to probation or contractual']);
        }
        $updateData = array();
        
        // Filter the fields which need to be updated
        $updateData = filterFields(['user_bio_id', 'user_fname', 'user_lname', 'user_login_name', 'user_joining_date',
            'zoho_login_name', 'email', 'user_birthdate', 'user_lastlogin','send_email','is_food', 'shift_id', 'location_id', 'leave_allow', 'user_writeoff', 'user_timesheet_fillup_flag', 'writeoffstaff', 'is_active'], $request);
        if ($request->has('user_image')) {
            $fileName = uploadUserImage($request, 'user_image', $id);
            $updateData['user_image'] = $fileName;
        }
        if($request->has('user_left_date')!=NULL){
           $updateData['user_left_date'] = $request->input('user_left_date'); 
        }
        if($user->user_type == 0 && $request->has('probation_date')!=NULL){
            //$updateData['user_type'] = 1;
            $updateData['probation_date'] = $request->input('probation_date');
            
        }
        //update the details
        $user->update($updateData);

        $userDetail = User::find($id);
        User::where("id", $id)->update(["userfullname" => $userDetail->user_fname . ' ' . $userDetail->user_lname]);


        return createResponse(config('httpResponse.SUCCESS'), 'User has been updated successfully', ['message' => 'User has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("User updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update user details.', ['error' => 'Could not update user details.']);
          } */
    }

    /**
     * All User Right Report
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // user id
     * @return Illuminate\Http\JsonResponse
     */
    public function allUserRightReport() {
        // For Set Row Number   
        try {
            //check button right
            $right = checkButtonRights(12, 'user_right_report');
            if ($right) {
                $data = User::userRightReport();
                $column = array();
                $column[] = ['Sr.No', 'User Name', 'User Bio Id', 'Desigantion Name', 'Tab Name', 'View', 'Edit', 'Other'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['userfullname'];
                        $columnData[] = $data['user_bio_id'];
                        $columnData[] = $data['designation_name'];
                        $columnData[] = $data['tab_name'];
                        $columnData[] = ($data['view'] == 1) ? 'Yes' : 'No';
                        $columnData[] = ($data['add_edit'] == 1) ? 'Yes' : 'No';
                        $columnData[] = $data['buttonName'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'UserReport', 'xlsx', 'A1:H1');
            } else {
                return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
            }
        } catch (\Exception $e) {
            app('log')->error("User Right Report download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download user right report.', ['error' => 'Could not download user right report.']);
        }
    }

    public function changePassword(Request $request, $id) {
        try {
            //check add edit right
            $user = getLoginUserHierarchy();
            if ($user->designation_id != config('constant.SUPERADMIN')) {
                if ($id != loginUser()) {
                    return createResponse(config('httpResponse.UNAUTHORIZED'), config('message.UNAUTHORIZED'), ['error' => config('message.UNAUTHORIZED')]);
                }
            }
            $validator = app('validator')->make($request->all(), [
                'password' => 'required|min:6',
                'confirm_password' => 'required|min:6|same:password'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);


            $user = User::find($id);

            if (!$user)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The User does not exist', ['error' => 'The User does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData['password'] = password_hash($request->input('confirm_password'), PASSWORD_BCRYPT);
            //update the details
            $user->update($updateData);
            //Store value in history table
            return createResponse(config('httpResponse.SUCCESS'), 'User Password has been updated successfully', ['message' => 'User password has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("User password updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update user password details.', ['error' => 'Could not update user password details.']);
        }
    }

    /*
     * Created By - Pankaj
     * Created On - 25/04/2018
     * Common function for save history
     */

    public static function saveHistory($model, $col_name) {
        $ArrayYesNo = array('is_active', 'user_timesheet_fillup_flag','send_email');
        $ArrayDropdown = array('shift_id', 'location_id', 'other_right', 'team_id', 'designation_id', 'department_id', 'leave_allow','user_type');
        $userArray = array('user_id', 'first_approval_user', 'second_approval_user', 'writeoffstaff', 'timesheet_approval_user');

        if (!empty($model->getDirty())) {
            $diff_col_val = array();
            foreach ($model->getDirty() as $key => $value) {
                if ($key == 'user_image')
                    continue;

                $oldValue = $model->getOriginal($key);
                $colname = isset($col_name[$key]) ? $col_name[$key] : $key;
                if (in_array($key, $ArrayYesNo)) {
                    $oldval = ($oldValue == '1') ? 'Yes' : 'No';
                    $newval = ($value == '1') ? 'Yes' : 'No';
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldval,
                        'new_value' => $newval,
                    ];
                } else if (in_array($key, $ArrayDropdown)) {
                    if ($key == 'shift_id') {
                        $shift = \App\Models\Backend\HrShift::AllShift();
                        $oldval = ($oldValue != '') ? $shift[$oldValue] : '';
                        $newval = ($value != '') ? $shift[$value] : '';
                    }if ($key == 'location_id') {
                        $location = \App\Models\Backend\HrLocation::AllLocation();
                        $oldval = ($oldValue != '') ? $location[$oldValue] : '';
                        $newval = ($value != '') ? $location[$value] : '';
                    }if ($key == 'leave_allow') {
                        $leaveAllow = config('constant.leave_allow_month');
                        $oldval = ($oldValue > 0 ) ? $leaveAllow[$oldValue] : '';
                        $newval = ($value > 0) ? $leaveAllow[$value] : '';
                    } if ($key == 'user_type') {
                        $userType = config('constant.user_type');
                        $oldval = ($oldValue > 0 ) ? $userType[$oldValue] : '';
                        $newval = ($value > 0) ? $userType[$value] : '';
                    } else if ($key == 'other_right') {
                        $oldval = $newval = '';
                        if ($oldValue != '') {
                            $old_name = \App\Models\Backend\Services::whereRaw("id IN (" . $oldValue . ")")->select(DB::raw('group_concat(service_name) as service_name'))->first();
                            $oldval = $old_name->service_name;
                        }
                        if ($value != '') {
                            $new_name = \App\Models\Backend\Services::whereRaw("id IN (" . $value . ")")->select(DB::raw('group_concat(service_name) as service_name'))->first();
                            $newval = $new_name->service_name;
                        }
                    } else if ($key == 'team_id') {
                        $old_name = \App\Models\Backend\Team::whereRaw("id IN (" . $oldValue . ")")->select(DB::raw('group_concat(team_name) as team_name'))->first();
                        $new_name = \App\Models\Backend\Team::whereRaw("id IN (" . $value . ")")->select(DB::raw('group_concat(team_name) as team_name'))->first();
                        $oldval = $old_name->team_name;
                        $newval = $new_name->team_name;
                    } else if ($key == 'designation_id') {
                        $designation = \App\Models\Backend\Designation::allDesignation();
                        $oldval = ($oldValue != '') ? $designation[$oldValue] : '';
                        $newval = ($value != '') ? $designation[$value] : '';
                    } else if ($key == 'department_id') {
                        $department = \App\Models\Backend\Department::where('is_active', 1)->get()->pluck('department_name', 'id')->toArray();
                        $oldval = ($oldValue != '') ? $department[$oldValue] : '';
                        $newval = ($value != '') ? $department[$value] : '';
                    }
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldval,
                        'new_value' => $newval,
                    ];
                } else if (in_array($key, $userArray)) {
                    $old = \App\Models\User::find($oldValue);
                    $new = \App\Models\User::find($value);
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => ($oldValue != '' && isset($old->userfullname)) ? $old->userfullname : '',
                        'new_value' => ($value != '') ? $new->userfullname : '',
                    ];
                } else if ($key == 'parent_user_id') {
                    $oldval = ($oldValue != '0') ? getHistoryUserHierarchyDetail($oldValue) : array();
                    $newval = ($value != '0') ? getHistoryUserHierarchyDetail($value) : array();
                    if ($oldValue != '0' || $value != '0') {
                        $diff_col_val[$key] = [
                            'display_name' => ucfirst($colname),
                            'old_value' => (!empty($oldval)) ? $oldval : '',
                            'new_value' => (!empty($newval)) ? $newval : '',
                        ];
                    }
                } else if ($key == 'password') {
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => 'Old Password',
                        'new_value' => 'New Password',
                    ];
                } else {
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                }
            }
            return $diff_col_val;
        }
        return $diff_col_val;
    }

    /**
     * update user history details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $user_id,$type
     * @return Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $user_id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json',
                'type' => 'required|in:change_password,user_detail,user_hierarchy,user_field_right,user_tab_right,user_worksheet_right,user_button_right',
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'modified_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            if ($request->input('type') == 'change_password' || $request->input('type') == 'user_detail') {
                $history = UserAudit::with('modifiedBy')
                        ->select("modified_on", "modified_by", "changes")
                        ->where("user_id", "=", $user_id)
                        ->where("type", "=", $request->input('type'));
            }if ($request->input('type') == 'user_hierarchy') {
                $history = \App\Models\Backend\UserHierarchyAudit::with('modifiedBy')
                        ->select("modified_on", "modified_by", "changes")
                        ->where("user_id", "=", $user_id);
            } else if ($request->input('type') == 'user_tab_right' || $request->input('type') == 'user_button_right') {
                $history = \App\Models\Backend\UserTabRightAudit::with('modifiedBy')
                        ->select("modified_on", "modified_by", "changes")
                        ->where("user_id", "=", $user_id)
                        ->where("type", "=", $request->input('type'));
            } else if ($request->input('type') == 'user_field_right') {
                $history = \App\Models\Backend\UserFieldRightAudit::with('modifiedBy')
                        ->select("modified_on", "modified_by", "changes")
                        ->where("user_id", "=", $user_id);
            } else if ($request->input('type') == 'user_worksheet_right') {
                $history = \App\Models\Backend\WorksheetStatusUserRightAudit::with('modifiedBy')
                        ->select("modified_on", "modified_by", "changes")
                        ->where("user_id", "=", $user_id);
            }

            if (!isset($history))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The user history does not exist', ['error' => 'The user history does not exist']);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $history = search($history, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $history = $history->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $history->count();

                $history = $history->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $history = $history->get();

                $filteredRecords = count($history);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            if ($request->input('type') == 'user_tab_right' ||
                    $request->input('type') == 'user_button_right' ||
                    $request->input('type') == 'user_worksheet_right' || $request->input('type') == 'user_field_right') {
                return createResponse(config('httpResponse.SUCCESS'), 'User history', ['data' => $history, 'format' => 2], $pager);
            } else {
                return createResponse(config('httpResponse.SUCCESS'), 'User history', ['data' => $history], $pager);
            }
        } catch (\Exception $e) {
            app('log')->error("Could not load user history : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not load user history.', ['error' => 'Could not load user history.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: May 17, 2018
     * Purpose   : Bulk first and second approval set
     * $param    : Submit excel file
     */

    public function userApprovalAllocation(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'file' => 'required|mimes:xlsx'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            app('excel')->load(Input::file('file'), function ($reader) {
                $reader->each(function($sheet) {
                    foreach ($sheet as $key) {
                        $this->staffIds[$sheet->staff_bio_metric_id] = array($sheet->first_approval_bio_metric_id, $sheet->second_approval_bio_metric_id);
                        $this->firstApprovalIds[$sheet->first_approval_bio_metric_id] = $sheet->first_approval_bio_metric_id;
                        $this->secondApprovalIds[$sheet->second_approval_bio_metric_id] = $sheet->second_approval_bio_metric_id;
                    }
                });
            });
            $firstapproval = User::select('id', 'user_bio_id')->whereIn('user_bio_id', $this->firstApprovalIds)->get()->pluck('id', 'user_bio_id')->toArray();
            $secondapproval = User::select('id', 'user_bio_id')->whereIn('user_bio_id', $this->firstApprovalIds)->get()->pluck('id', 'user_bio_id')->toArray();

            $IDs = array();
            foreach ($this->staffIds as $key => $value) {
                if (isset($firstapproval[$value[0]]))
                    $setuserApprovaldata['first_approval_user'] = $firstapproval[$value[0]];

                if (isset($secondapproval[$value[1]]))
                    $setuserApprovaldata['second_approval_user'] = $secondapproval[$value[1]];

                if (!empty($setuserApprovaldata))
                    User::where('user_bio_id', $key)->update($setuserApprovaldata);

                $IDs[] = $key;
            }
            $user = User::with('firstApproval')->with('secondApproval')->whereIn('user_bio_id', $IDs)->get()->toArray();
            return createResponse(config('httpResponse.SUCCESS'), "Set all user approval staff", ['data' => $user]);
        } catch (\Exception $e) {
            app('log')->error("User bulk approval allocation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while allocation user approval", ['error' => 'Server error.']);
        }
    }

    /**
     * Get user detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function userList(Request $request) {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'user.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];
            if($request->has('field')){
            $field = $request->input('field');
            $user = User::leftjoin('user_hierarchy as uh', 'uh.user_id', '=', 'user.id')
                    ->where("user.is_active","1")
                    ->select(DB::raw($field),'uh.designation_id');
            }else{
            $user = User::userData()->groupby("user.id");
            }
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $allias = array("is_active" => "user", "id" => "user");
                $user = search($user, $search, $allias);
            }
            // for relation ship sorting
            if ($sortBy == 'shift_name') {
                $user = $user->leftjoin("hr_shift_master as s", "s.id", "user.shift_id");
            }
            if ($sortBy == 'location_name') {
                $user = $user->leftjoin("hr_location as l", "l.id", "user.location_id");
            }
            if ($sortBy == 'designation_name') {
                $user = $user->leftjoin("designation as d", "d.id", "uh.designation_id");
            }
            if ($sortBy == 'department_name') {
                $user = $user->leftjoin("department as dp", "dp.id", "uh.department_id");
            }
            if ($sortBy == 'team_name') {
                $user = $user->leftjoin("team as t", "t.id", "uh.team_id");
            }
            if ($sortBy == 'created_by' || $sortBy == 'first_approval_user' || $sortBy == 'second_approval_user') {
                $user = $user->leftjoin("user as u", "u.id", "user.$sortBy");
                $sortBy = 'u.userfullname';
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $user = $user->orderBy($sortBy, $sortOrder)->get(['user.*']);
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $user->get()->count();

                $user = $user->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $user = $user->get();

                $filteredRecords = count($user);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }


            return createResponse(config('httpResponse.SUCCESS'), "User list.", ['data' => $user], $pager);
        /*} catch (\Exception $e) {
            app('log')->error("User listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing user", ['error' => 'Server error.']);
        }*/
    }

    public function userDetailList($id) {
        // For Set Row Number   
        //try {
        $userDetail = \App\Models\Backend\UserZohoDetail::where("EmployeeID", $id)->first();
        return createResponse(config('httpResponse.SUCCESS'), "User list.", ['data' => $userDetail], '');
        /* } catch (\Exception $e) {
          app('log')->error("User Right Report download failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not download user right report.', ['error' => 'Could not download user right report.']);
          } */
    }

    public function userZohoDetail(Request $request, $id) {
        //try {
        $user = \App\Models\Backend\UserZohoDetail::where("EmployeeID", $id)->first();

        if (!isset($user))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The user does not exist', ['error' => 'The user does not exist']);

        //send user information
        return createResponse(config('httpResponse.SUCCESS'), 'User zoho data', ['data' => $user]);
        /* } catch (\Exception $e) {
          app('log')->error("User ZOho details api failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get user.', ['error' => 'Could not get user.']);
          } */
    }

    public function newuserZohoDetail(Request $request) {
        //try {
        //$todayDate = date('Y-m-d');
        $startDate = date('Y-m-01');
        $user = \App\Models\Backend\UserZohoDetail::whereRaw("Date_of_joining >= '" . $startDate . "'")->get();

        $column = array();
        $column[] = ['Sr.No', 'Emp ID', 'Ref. No.', 'Salutation', 'First Name', 'Middle Name', 'Last Name', 'Short Name', 'Fathers Name', 'Mothers Name', 'Date of Birth', 'Sex', 'Marital Status', 'Spouse Name', 'Designation', 'Occupation', 'Department', 'Grade', 'Branch', 'Division', 'Bank Account No.', 'Bank Name', 'Sal Structure', 'Attendance', 'Res. No.', 'Res. Name', 'Road/Street', 'Locality/Area', 'City/District', 'State', 'Pincode', 'Res. No.', 'Res. Name', 'Road/Street', 'Locality/Area', 'City/District', 'State', 'Pincode', 'E - Mail ID', 'STD Code', 'Phone', 'Mobile', 'Date of Joining', 'Salary calculate from', 'Date of leaving', 'Reason for leaving', 'ESI Applicable', 'ESI No', 'ESI Dispensary', 'PF Applicable', 'PF No', 'PF No for Dept File', 'Restrict PF', 'Zero Pension', 'Zero PT', 'PAN', 'Ward/Circle', 'Director', 'UAN NO', 'IFSC Code', 'Aadhar No.', 'Remarks', 'Rejoinee', 'Prev. Empid/Refno'];
        if (!empty($user)) {
            $columnData = array();
            $i = 1;
            foreach ($user as $data) {
                $columnData[] = $i;
                $columnData[] = '';
                $columnData[] = $data["EmployeeID"];
                $columnData[] = '';
                $columnData[] = $data["First_Name"];
                $columnData[] = '';
                $columnData[] = $data["Last_Name"];
                $columnData[] = '';
                $columnData[] = $data["Fathers_Name"];
                $columnData[] = $data["Mothers_Name"];
                $columnData[] = date("d-m-Y",strtotime($data["Birth_Date"]));
                $columnData[] = $data["Gender"];
                $columnData[] = $data["Marital_status"];
                $columnData[] = $data["Spouses_Name"];
                $columnData[] = $data["Designation"];
                $columnData[] = '';
                $columnData[] = $data["Department"];
                $columnData[] = $data["Grade"];
                $columnData[] = $data["Location"];
                $columnData[] = '';
                $columnData[] = $data["Account_No"];
                $columnData[] = $data["Bank"];
                $columnData[] = 'New joine July 2019';
                $columnData[] = 'Monthly';
                $columnData[] = '';
                $columnData[] = $data["Address_Line_1"];
                $columnData[] = $data["Address_Line_2"];
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = $data["States"];
                $columnData[] = $data["PINZIP_Code"];
                $columnData[] = '';
                $columnData[] = $data["Address_Line_1"];
                $columnData[] = $data["Address_Line_2"];
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = $data["States"];
                $columnData[] = $data["PINZIP_Code"];
                $columnData[] = $data["Other_Email"];
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = $data["Mobile_Phone"];
                $columnData[] = date("d-m-Y",strtotime($data["Date_of_joining"]));
                $columnData[] = date("d-m-Y",strtotime($data["Date_of_joining"]));
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = $data["PAN_Number"];
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $columnData[] = '';
                $column[] = $columnData;
                $columnData = array();
                $i++;
            }
        }
        return exportExcelsheet($column, 'UserZohoList', 'xlsx', 'A1:BL1');
        //send user information
        return createResponse(config('httpResponse.SUCCESS'), 'User zoho data', ['data' => $user]);
        /* } catch (\Exception $e) {
          app('log')->error("User ZOho details api failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get user.', ['error' => 'Could not get user.']);
          } */
    }

}

?>
