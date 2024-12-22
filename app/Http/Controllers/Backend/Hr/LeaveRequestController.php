<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class LeaveRequestController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 22, 2018
     * Purpose   : Fetch leave Request data
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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_leave_request.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];
        $id = loginUser();
        $user = \App\Models\Backend\UserHierarchy::where("user_id", $id)->first();
        $userData = '';
        if ($user->designation_id != config('constant.SUPERADMIN')) {
            $userData = \App\Models\User::select(DB::raw("GROUP_CONCAT(id) as user_id"))->where('first_approval_user', $id)->Orwhere('second_approval_user', $id)->Orwhere('id', $id)->first();
        }
        $leave = \App\Models\Backend\HrLeaveRequest::with('created_by:id,userfullname')
                ->with('firstApproval:id,userfullname')
                ->with('secondApproval:id,userfullname')->select('hr_leave_request.*', 'ht.leave_type')
                ->leftjoin("hr_leave_type as ht", "ht.id", "hr_leave_request.leave_type");

        if ($userData != '') {
            $leave = $leave->whereRaw("hr_leave_request.user_id IN ($userData->user_id)");
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $allias = array("leave_type" => "hr_leave_request");
            $leave = search($leave, $search, $allias);
        }
        if ($sortBy == 'created_by') {
            $leave = $leave->leftjoin("user as u", "u.id", "hr_leave_request.$sortBy");
            $sortBy = 'userfullname';
        }

        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $leave = $leave->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $leave->count();

            $leave = $leave->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $leave = $leave->get();

            $filteredRecords = count($leave);

            $pager = ['sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        if ($request->has('excel') && $request->get('excel') == 1) {
            $data = $leave->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Staff name', 'From Date', 'To Date', 'Leave Type', 'Days', 'First Approval', 'Second Approval', 'Status', 'Reason'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                $status = config('constant.leavestatus');
                foreach ($data as $data) {
                    $columnData[] = $i;
                    $columnData[] = isset($data['created_by']['userfullname']) ? $data['created_by']['userfullname'] : '-';
                    $columnData[] = $data['from_date'];
                    $columnData[] = $data['to_date'];
                    $columnData[] = $data['leave_type'];
                    $columnData[] = $data['days'];
                    $columnData[] = isset($data['first_approval']['userfullname']) ? $data['first_approval']['userfullname'] : '-';
                    $columnData[] = isset($data['second_approval']['userfullname']) ? $data['second_approval']['userfullname'] : '-';
                    $columnData[] = isset($status[$data['status_id']]) ? $status[$data['status_id']] : '';
                    $columnData[] = $data['leave_reason'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'Leave Request', 'xlsx', 'A1:J1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Leave Request list.", ['data' => $leave], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Leave Request listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing leave Request", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Store leave Request data
     */

    public function store(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'from_date' => 'required',
            'to_date' => 'required',
            'leave_type' => 'required',
            'inform_team' => 'required',
            'anything_due' => 'required',
            'first_approval' => 'required',
            'date_select' => 'required_if:1,2,3|json'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        // store leave Request  details
        $leave = \App\Models\Backend\HrLeaveRequest::create(['user_id' => app('auth')->guard()->id(),
                    'leave_type' => $request->get('leave_type'),
                    'inform_team' => $request->get('inform_team'),
                    'anything_due' => $request->get('anything_due'),
                    'anything_due_comments' => $request->get('anything_due_comments'),
                    'weekly_task' => $request->get('weekly_task'),
                    'leave_reason' => $request->get('leave_reason'),
                    'from_date' => date("Y-m-d", strtotime($request->get('from_date'))),
                    'to_date' => date("Y-m-d", strtotime($request->get('to_date'))),
                    'status_id' => 3,
                    'created_on' => date('Y-m-d'),
                    'created_by' => loginUser(),
                    'first_approval' => $request->get('first_approval'),
                    'second_approval' => $request->get('second_approval')]);
        $totalLeave = 0;
        if ($request->get('leave_type') != 4) {
            $dateRange = \GuzzleHttp\json_decode($request->input('date_select'), true);
            foreach ($dateRange as $row) {
                \App\Models\Backend\HrLeaveRequestDetail::create(['hr_leave_request_id' => $leave->id,
                    'date' => $row['leave_date'],
                    'leave_type' => $row['date_value'],
                    'half_day_type' => $row['half_day_type']]);
                if ($row['date_value'] > 0) {
                    $totalLeave = $totalLeave + 1.00;
                } else {
                    $totalLeave = $totalLeave + 0.50;
                }
            }
        } else {
            //$date1 = date("Y-m-d", strtotime($request->get('from_date')));
            //$date2 = date("Y-m-d", strtotime($request->get('to_date')));
            $date1= strtotime($request->get('from_date'));
            $date2= strtotime($request->get('to_date'));
            $diff = $date1 - $date2;

            $totalLeave = floor($diff / (60 * 60 * 24));;
        }
        \App\Models\Backend\HrLeaveRequest::where("id", $leave->id)->update(['days' => $totalLeave]);
        $user = \App\Models\User::where("id", $request->get('first_approval'))->first();
        $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('LATEAPPROVAL');
        if ($emailTemplate->is_active == 1 && $user->email != '') {
            $fromDate = date("d-m-Y", strtotime($request->get('from_date')));
            $toDate = date("d-m-Y", strtotime($request->get('to_date')));
            $search = array('USER_NAME', 'REMARKTYPE');
            $replace = array(app('auth')->guard()->user()->userfullname, 'Leave');
            $due = ($request->get('anything_due') == 1) ? 'Yes' : 'No';
            $informTeam = ($request->get('informe_team') == 1) ? 'Yes' : 'No';
            $table = '<table><tr><td>From Date:</td><td>' . $fromDate . '</td></tr>
                         <tr><td>To Date:</td><td>' . $toDate . '</td></tr>
                         <tr><td>Inform Team:</td><td>' . $informTeam . '</td></tr>
                         <tr><td>Anything due:</td><td>' . $due . '</td></tr>
                         <tr><td>Anything due comment:</td><td>' . $request->get('anything_due_comments') . '</td>
                         <tr><td>Reason:</td><td>' . $request->get('leave_reason') . '</td></tr>
                        </table>';

            $search1 = array('USER_NAME', 'REMARKTYPE', 'STAFF_NAME', 'REASON', 'APPROVALPERSON');
            $replace1 = array(app('auth')->guard()->user()->userfullname, 'Leave Request', $user->userfullname, $request->get('leave_reason'), $table);

            $data['to'] = $user->email;
            $data['cc'] = $emailTemplate->cc;
            $data['bcc'] = $emailTemplate->bcc;
            $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
            $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
            storeMail($request, $data);
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Leave Request  has been added successfully', ['data' => $leave]);
        /* } catch (\Exception $e) {
          app('log')->error("Leave Request  creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Leave Request', ['error' => 'Could not add Leave Request']);
          } */
    }

    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'from_date' => 'required',
                'to_date' => 'required',
                'leave_type' => 'required',
                'informe_team' => 'required',
                'anything_due' => 'required',
                'first_approval' => 'required',
                'second_approval' => 'required'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $leave = \App\Models\Backend\HrLeaveRequest::find($id);

            if (!$leave)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Leave Request does not exist', ['error' => 'The Leave Request does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['leave_type', 'from_date', 'to_date', 'remark_type', 'informe_team', 'anything_due', 'notes'], $request);
            $leave->update($updateData);
            $user = \App\Models\User::where("id", $leave->first_approval)->first();
            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('LATEAPPROVAL');
            if ($emailTemplate->is_active == 1 && $user->email != '') {
                $fromDate = date("d-m-Y", strtotime($request->get('from_date')));
                $toDate = date("d-m-Y", strtotime($request->get('to_date')));
                $search = array('USER_NAME', 'REMARKTYPE');
                $replace = array(app('auth')->guard()->user()->userfullname, 'Leave');
                $due = ($request->get('anything_due') == 1) ? 'Yes' : 'No';
                $informTeam = ($request->get('informe_team') == 1) ? 'Yes' : 'No';
                $table = '<table><tr><td>From Date:</td><td>' . $fromDate . '</td></tr>
                         <tr><td>To Date:</td><td>' . $toDate . '</td></tr>
                         <tr><td>Inform Team:</td><td>' . $informTeam . '</td></tr>
                         <tr><td>Anything due:</td><td>' . $due . '</td></tr>
                         <tr><td>Anything due comments:</td><td>' . $request->get('anything_due_comments') . '</td></tr>
                         <tr><td>Reason:</td><td>' . $request->get('leave_reason') . '</td></tr>
                        </table>';

                $search1 = array('USER_NAME', 'REMARKTYPE', 'STAFF_NAME', 'REASON', 'APPROVALPERSON');
                $replace1 = array(app('auth')->guard()->user()->userfullname, 'Leave Request', $user->userfullname, $request->get('leave_reason'), $table);

                $data['to'] = $user->email;
                $data['cc'] = $emailTemplate->cc;
                $data['bcc'] = $emailTemplate->bcc;
                $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
                storeMail($request, $data);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Leave Request  has been update successfully', ['data' => 'Leave Request  has been update successfully']);
        } catch (\Exception $e) {
            app('log')->error("Leave Request updation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update leave Request ', ['error' => 'Could not update leave Request ']);
        }
    }

    public function approved(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'status_id' => 'required',
                'comment' => 'required',
                'approval_type' => 'required|in:1,2'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $leave = \App\Models\Backend\HrLeaveRequest::find($id);
            $statusId = $request->input('status_id');
            if ($request->input('approval_type') == 1 && $statusId != 6) {
                if ($leave->second_approval == null) {
                    $statusId = 5;
                }
                \App\Models\Backend\HrLeaveRequest::where("id", $id)->update(['first_approval_comment' => $request->input('comment'),
                    'first_approval_on' => date('Y-m-d H:i:s'),
                    'status_id' => $statusId]);
                if ($leave->second_approval > 0) {
                    $user = \App\Models\User::where("id", $leave->user_id)->first();
                    $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('LATESECONDAPPROVAL');
                    if ($emailTemplate->is_active == 1 && $user->email != '' && $request->input('status_id') == 4) {
                        $search = array('USER_NAME', 'REMARKTYPE');
                        $replace = array(app('auth')->guard()->user()->userfullname, 'Leave');
                        $fromDate = date("d-m-Y", strtotime($leave->from_date));
                        $toDate = date("d-m-Y", strtotime($leave->to_date));
                        $due = ($leave->anything_due == 1) ? 'Yes' : 'No';
                        $informTeam = ($leave->informe_team == 1) ? 'Yes' : 'No';
                        $table = '<table><tr><td>From Date:</td><td>' . $fromDate . '</td></tr>
                         <tr><td>To Date:</td><td>' . $toDate . '</td></tr>
                         <tr><td>Inform Team:</td><td>' . $informTeam . '</td></tr>
                         <tr><td>Anything due:</td><td>' . $due . '</td></tr>
                         <tr><td>Anything due Comments:</td><td>' . $leave->anything_due_comments . '</td></tr>
                         <tr><td>First Approval Comment:</td><td>' . $leave->first_approval_comment . '</td></tr>
                         <tr><td>Reason:</td><td>' . $leave->leave_reason . '</td></tr>
                        </table>';

                        $search1 = array('USER_NAME', 'REMARKTYPE', 'STATUS', 'SECOND_STAFF', 'REASON', 'FIRST_SECOND_COMMENT');
                        $replace1 = array($user->userfullname, 'Pending For Second Approval', 'Leave', $user->userfullname, $leave->leave_reason, $table);

                        $data['to'] = $user->email;
                        $data['cc'] = $emailTemplate->cc;
                        $data['bcc'] = $emailTemplate->bcc;
                        $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                        $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
                        storeMail($request, $data);
                    }
                }
            } else {
                \App\Models\Backend\HrLeaveRequest::where("id", $id)->update(['second_approval_comment' => $request->input('comment'),
                    'second_approval_on' => date('Y-m-d H:i:s'),
                    'status_id' => $statusId]);
            }
            if ($statusId == 5) {
                $user = \App\Models\User::where("id", $leave->user_id)->first();
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('REQUESTFINALSTATUS');
                $search = array('USER_NAME', 'REMARKTYPE');
                $replace = array(app('auth')->guard()->user()->userfullname, 'Leave');
                $due = ($leave->anything_due == 1) ? 'Yes' : 'No';
                $informTeam = ($leave->informe_team == 1) ? 'Yes' : 'No';
                $fromDate = date("d-m-Y", strtotime($leave->from_date));
                $toDate = date("d-m-Y", strtotime($leave->to_date));
                $table = '<table><tr><td>From Date:</td><td>' . $fromDate . '</td></tr>
                         <tr><td>To Date:</td><td>' . $toDate . '</td></tr>
                         <tr><td>Inform Team:</td><td>' . $informTeam . '</td></tr>
                         <tr><td>Anything due:</td><td>' . $due . '</td></tr>
                         <tr><td>Reason:</td><td>' . $leave->leave_reason . '</td></tr>                         
                         <tr><td>Anything due Comments:</td><td>' . $leave->anything_due_comments . '</td></tr>
                         <tr><td>First Approval Comment:</td><td>' . $leave->first_approval_comment . '</td></tr>
                         <tr><td>Second Approval Comment:</td><td>' . $leave->second_approval_comment . '</td></tr>
                        </table>';

                $search1 = array('USER_NAME', 'REMARKTYPE', 'STATUS', 'FIRST_SECOND_COMMENT');
                $replace1 = array($user->userfullname, 'Leave', 'Approved', $table);

                $data['to'] = $user->email;
                $data['cc'] = $emailTemplate->cc;
                $data['bcc'] = $emailTemplate->bcc;
                $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
                storeMail($request, $data);
            }
            if ($statusId == 6) {
                $user = \App\Models\User::where("id", $leave->user_id)->first();
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('REQUESTFINALSTATUS');
                $search = array('USER_NAME', 'REMARKTYPE');
                $replace = array(app('auth')->guard()->user()->userfullname, 'Leave');
                $due = ($leave->anything_due == 1) ? 'Yes' : 'No';
                $informTeam = ($leave->informe_team == 1) ? 'Yes' : 'No';
                $table = '<table><tr><td>From Date:</td><td>' . $leave->from_date . '</td></tr>
                         <tr><td>To Date:</td><td>' . $leave->to_date . '</td></tr>
                         <tr><td>Inform Team:</td><td>' . $informTeam . '</td></tr>
                         <tr><td>Anything due:</td><td>' . $due . '</td></tr>
                         <tr><td>Anything due Comments:</td><td>' . $leave->anything_due_comments . '</td></tr>
                         <tr><td>Reason:</td><td>' . $leave->leave_reason . '</td></tr>
                         <tr><td>First Approval Comment:</td><td>' . $leave->first_approval_comment . '</td></tr>
                         <tr><td>Second Approval Comment:</td><td>' . $leave->second_approval_comment . '</td></tr>
                        </table>';
                $search1 = array('USER_NAME', 'REMARKTYPE', 'STATUS', 'FIRST_SECOND_COMMENT');
                $replace1 = array($user->userfullname, 'Leave', 'Reject', $table);

                $data['to'] = $user->email;
                $data['cc'] = $emailTemplate->cc;
                $data['bcc'] = $emailTemplate->bcc;
                $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
                storeMail($request, $data);
            }


            return createResponse(config('httpResponse.SUCCESS'), 'Leave Request has been approve successfully', ['data' => 'Leave Request has been approve successfully']);
        } catch (\Exception $e) {
            app('log')->error("Leave Request approve failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not approve leave Request ', ['error' => 'Could not approve leave Request ']);
        }
    }

    public function destroy($id) {
        try {
            $hrLeaveRequest = \App\Models\Backend\HrLeaveRequest::find($id);
            if (!$hrLeaveRequest)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Leave does not exist', ['error' => 'The Leave does not exist']);

            $hrLeaveRequest->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Leave Request has been deleted successfully', ['message' => 'Leave Request has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Leave Request download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Leave Request.', ['error' => 'Could not delete Leave Request.']);
        }
    }

}
