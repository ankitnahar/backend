<?php

namespace App\Http\Controllers\Backend\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\UserTabRight,
    App\Models\Backend\UserAudit,
    App\Models\Backend\Team,
    App\Models\Backend\UserHierarchy,
    App\Models\User;
use DB;

class UserHierarchyController extends Controller {

    /**
     * Display index page.
     * created by Pankaj
     * @return \BladeView|bool|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function update(REQUEST $request, $id) {
        // try {
        $validator = app('validator')->make($request->all(), [
            'designation_id' => 'required|numeric',
            'department_id' => 'required|numeric',
            'team_id' => 'required|array',
            'other_right' => 'array'
                ], []);
        //check designation wise all mandatory designation
        $designationRequire = $this->checkDesignation($request, 1);
        if (!empty($designationRequire)) {
            // check value if         
            foreach ($designationRequire as $key => $value) {
                if ($request->input($key) == "")
                    return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $value . ' can not blank!!']);
            }
        }

        if (in_array($request->input('team_id'), array(1, 2, 6))) {
            if ($request->input('other_right') == "")
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => 'Other Right can not blank!!']);
        }
        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        $userHierarchy = UserHierarchy::where("user_id", $id)->first();
        $user = User::find($id);
        // save data in user table 
        if ($user->first_approval_user != $request->input('first_approval_user') ||
                ($user->second_approval_user != $request->input('second_approval_user')) ||
                ($user->writeoffstaff != $request->input('writeoffstaff')) ||
                ($user->timesheet_approval_user != $request->input('timesheet_approval_user'))) {
            $updateData = array();
            \App\Models\Backend\HrLeaveRequest::where("user_id",$id)->whereIn("status_id",[3,4])->update(["first_approval" => $request->input('first_approval_user') ,
                "second_approval" => $request->input('second_approval_user')]);
            \App\Models\Backend\HrHolidayRequest::where("user_id",$id)->whereIn("status_id",[3,4])->update(["first_approval" => $request->input('first_approval_user') ,
                "second_approval" => $request->input('second_approval_user')]);
            // Filter the fields which need to be updated
            $updateData = filterFields(['first_approval_user', 'second_approval_user', 'writeoffstaff', 'timesheet_approval_user'], $request);
            $user->update($updateData);
        }
        //check all designation hierarchy for get parent user id
        $parentUserId = 0;
        $designationHier = $this->checkDesignation($request, 0);
        $designationHierarchy = array_reverse($designationHier, true);

        if (!empty($designationHierarchy)) {
            // check value if         
            foreach ($designationHierarchy as $key => $value) {
                if ($request->input($key) != "" && $request->input($key) != '0') {
                    $parentUserId = $request->input($key);
                    $parentKey = $key;
                    break;
                }
            }
        }

        if (!empty($designationHier)) {
            foreach ($designationHier as $key => $value) {
                if ($key == $parentKey) {
                    break;
                } else {
                    if ($request->input($key) == "" || $request->input($key) == '0') {
                        return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $value . ' can not blank!!']);
                    }
                }
            }
        }
        if ($id == $parentUserId) {
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => 'You can not assign yourself in Parent']);
        }
        //showArray($userHierarchy);exit;
        //First Time insert value in database
        if (empty($userHierarchy)) {
            $userHierarchy = new UserHierarchy;

            $UserHierarchy = UserHierarchy::create([
                        'user_id' => $id,
                        'other_right' => ($request->has('other_right')) ? implode(",", $request->input('other_right')) : '',
                        'department_id' => $request->input('department_id'),
                        'team_id' => ($request->has('team_id')) ? implode(",", $request->input('team_id')) : '',
                        'designation_id' => $request->input('designation_id'),
                        'parent_user_id' => $parentUserId,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => loginUser()]);


            //store tab right designation wise
            $designationRight = DB::table("designation_tab_right")->where("designation_id", $request->input('designation_id'))->get();
            $userTabRight = UserTabRight::where("user_id", $id);
            if ($userTabRight->count() == 0) {
                foreach ($designationRight as $desrow) {
                    //Check Value already exiest or not
                    UserTabRight::create([
                        'tab_id' => $desrow->tab_id,
                        'user_id' => $id,
                        'view' => $desrow->view,
                        'add_edit' => $desrow->add_edit,
                        'delete' => $desrow->delete,
                        'export' => $desrow->export,
                        'download' => $desrow->download,
                        'other_right' => $desrow->other_right,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => loginUser()
                    ]);
                }
            }
            $designationWorksheetRight = \App\Models\Backend\DesignationWorksheetRight::where("designation_id", $request->input('designation_id'))->get();
            $userworksheetRight = \App\Models\Backend\WorksheetStatusUserRight::where("user_id", $id);
            if ($userworksheetRight->count() == 0) {
                foreach ($designationWorksheetRight as $desrow) {
                    //Check Value already exiest or not
                    \App\Models\Backend\WorksheetStatusUserRight::create([
                        'worksheet_status_id' => $desrow->worksheet_status_id,
                        'user_id' => $id,
                        'right' => $desrow->right,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => loginUser()
                    ]);
                }
            }

            $designationFieldRight = \App\Models\Backend\DesignationFieldRight::where("designation_id", $request->input('designation_id'))->get();
            $userFieldRight = \App\Models\Backend\UserFieldRight::where("user_id", $id);
            if ($userFieldRight->count() == 0) {
                foreach ($designationFieldRight as $desrow) {
                    //Check Value already exiest or not
                    \App\Models\Backend\UserFieldRight::create([
                        'field_id' => $desrow->field_id,
                        'user_id' => $id,
                        'view' => $desrow->view,
                        'add_edit' => $desrow->add_edit,
                        'delete' => $desrow->delete,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => loginUser()
                    ]);
                }
            }

            return createResponse(config('httpResponse.SUCCESS'), 'User Hierarchy has been added successfully', ['message' => 'User Hierarchy has been added successfully']);
        } else {
            $updateData = array();
            //echo $parentUserId;exit;
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['designation_id', 'department_id'], $request);
            $updateData['parent_user_id'] = $parentUserId;
            $updateData['other_right'] = ($request->has('other_right')) ? implode(",", $request->input('other_right')) : '';
            $updateData['team_id'] = ($request->has('team_id')) ? implode(",", $request->input('team_id')) : '';

            //update the details
            $userHierarchy->update($updateData);
            return createResponse(config('httpResponse.SUCCESS'), 'User Hierarchy has been updated successfully', ['message' => 'User Hierarchy has been updated successfully']);
        }
        /* } catch (\Exception $e) {
          app('log')->error("User Hierarchy updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update user hierarchy details.', ['error' => 'Could not update user hierarchy details.']);
          } */
    }

    /**
     * get particular user hierarchy details
     *
     * @param  int  $id   //user id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($user_id) {
        try {
            $userHier = UserHierarchy::where("user_id", $user_id);
            //Check PArent User 
            
           

            if ($userHier->count() > 0) {
                 $userHierarchyDetail = DB::select("CALL get_hierarchy_of_user($user_id)");
                $userHierarchy = getUserDetails($user_id);
                $userArray = array_reverse($userHierarchy[$user_id], true);

                $hierarchy = UserHierarchy::with('createdBy:userfullname as created_by,id')
                                ->leftjoin("user as u", "u.id", "user_hierarchy.user_id")
                                ->select("user_hierarchy.*", "u.first_approval_user", "u.second_approval_user", "u.writeoffstaff", "u.timesheet_approval_user")
                                ->where("user_id", $user_id)->get();
                $hierarchy['parent_user_id'] = $userArray;
                if (!isset($hierarchy))
                    return createResponse(config('httpResponse.NOT_FOUND'), 'User Hierarchy does not exist', ['error' => 'User Hierarchy does not exist']);

                return createResponse(config('httpResponse.SUCCESS'), 'User Hierarchy data', ['data' => $hierarchy, 'parent' => $userHierarchyDetail]);
            }else {
                return createResponse(config('httpResponse.SUCCESS'), '', ['data' => '']);
            }
        } catch (\Exception $e) {
            app('log')->error("User Hierarchy details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get User Hierarchy.', ['error' => 'Could not get User Hierarchy.']);
        }
    }

    //send user information

    function checkDesignation(REQUEST $request, $mandatory) {
        //check all parent designation hierarchy
        $designationList = \App\Models\Backend\Designation::designationData($request->input('designation_id'))->get();

        $desArray = array();
        $designationList = $designationList[0];
        while (isset($designationList->parent)) {
            if ($mandatory == 1 && $designationList->parent->is_mandatory == 'No') {
                //continue; 
            } else {
                $desArray[$designationList->parent->id] = $designationList->parent->designation_name;
            }
            $designationList = $designationList->parent;
        }
        $desArray = array_reverse($desArray, true);
        return $desArray;
    }

    /**
     * get all department
     */
    function getDepartment() {
        try {
            $department = \App\Models\Backend\Department::where("is_active", "1")->get();
            return createResponse(config('httpResponse.SUCCESS'), 'Department List', ['data' => $department]);
        } catch (\Exception $e) {
            app('log')->error("Department List failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Department Details not found ', ['error' => 'Department Details not found.']);
        }
    }

    /**
     * get team department wise
     *
     * @param  int  $id   //deaprtment id
     * @return Illuminate\Http\JsonResponse
     */
    function getTeamDepartmentWise(Request $request, $departmentId) {
        try {
            $team = Team::whereRaw("FIND_IN_SET($departmentId,department_id)")->get();
            return createResponse(config('httpResponse.SUCCESS'), 'Team List', ['data' => $team]);
        } catch (\Exception $e) {
            app('log')->error("Team list failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Team List not found ', ['error' => 'Team List not found.']);
        }
    }

    /**
     * get team designation wise
     *
     * @param  int  $id   //designation id
     * @return Illuminate\Http\JsonResponse
     */
    function getDesignationTeamWise(Request $request, $designationId) {
        try {
            $designationList = DB::select("CALL get_designation_hierarchy($designationId)");
            return createResponse(config('httpResponse.SUCCESS'), 'Team Detail', ['data' => $designationList]);
        } catch (\Exception $e) {
            app('log')->error("Team Detail failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Team Details not found ', ['error' => 'Team Details not found.']);
        }
    }

    /**
     * get all team
     */
    function getTeam() {
        try {
            $team = \App\Models\Backend\Team::where("is_active", "1")->get();
            return createResponse(config('httpResponse.SUCCESS'), 'Team List', ['data' => $team]);
        } catch (\Exception $e) {
            app('log')->error("Team List failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Team Details not found ', ['error' => 'Team Details not found.']);
        }
    }

    function getUserDesignationWise(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'team_id' => 'required',
                'designation_id' => 'required|numeric'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $arrRole = array();
            $team_id = explode(",", $request->input('team_id'));
            $designation_id = $request->input('designation_id');
            $userList = User::getDesignationwiseUser($designation_id);
            if (!empty($request->input('team_id'))) {
                $query = "(";
                foreach ($team_id as $id)
                    $query .= "FIND_IN_SET (" . $id . ", uh.team_id) OR ";

                $query = rtrim($query, 'OR ');
                $query .= ")";
                $userList = $userList->whereRaw($query);
            }
            if (!empty($request->input('parent_user_id'))) {
                $userList = $userList->where("uh.parent_user_id", $request->input('parent_user_id'));
            }

            $userList = $userList->get(['user.userfullname', 'user.id']);
            return createResponse(config('httpResponse.SUCCESS'), 'Designation wise user list', ['data' => $userList]);
        } catch (\Exception $e) {
            app('log')->error("Designation wise user list failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not show designation wise user list.', ['error' => 'Could not show designation wise user list.']);
        }
    }

}

?>