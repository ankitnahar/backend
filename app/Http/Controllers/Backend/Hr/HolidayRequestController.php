<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class HolidayRequestController extends Controller {
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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_hw_request.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];
        $id = loginUser();
        $user = \App\Models\Backend\UserHierarchy::where("user_id", $id)->first();
        $userData = '';
        if ($user->designation_id != config('constant.SUPERADMIN')) {
            $userData = \App\Models\User::select(DB::raw("GROUP_CONCAT(id) as user_id"))->where('first_approval_user', $id)->Orwhere('second_approval_user', $id)->Orwhere('id', $id)->first();
        }
        $holiday = \App\Models\Backend\HrHolidayRequest::with('created_by:id,userfullname')
                ->with('firstApproval:id,userfullname')
                ->with('secondApproval:id,userfullname')->select("hr_hw_request.*", "e.trading_name", "h.location_name")
                ->leftjoin("entity as e", "e.id", "hr_hw_request.entity_id")
                ->leftjoin("hr_location as h", "h.id", "hr_hw_request.location_id");
        if ($userData != '') {
            $holiday = $holiday->whereRaw("hr_hw_request.user_id IN ($userData->user_id)");
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $holiday = search($holiday, $search);
        }
        if ($sortBy == 'created_by') {
            $holiday = $holiday->leftjoin("user as u", "u.id", "hr_leave_request.$sortBy");
            $sortBy = 'userfullname';
        }

        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $holiday = $holiday->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $holiday->count();

            $holiday = $holiday->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $holiday = $holiday->get();

            $filteredRecords = count($holiday);

            $pager = ['sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        if ($request->has('excel') && $request->get('excel') == 1) {
            $data = $holiday->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Date', 'Staff name', 'Start Time', 'End Time', 'Location', 'Entity', 'First Approval', "second Approval","Status","Notes"];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                 $status = config('constant.leavestatus');
                foreach ($data as $data) {
                    $columnData[] = $i;
                    $columnData[] = dateFormat($data['date']);
                    $columnData[] = isset($data['created_by']['userfullname']) ? $data['created_by']['userfullname'] : '-';
                    $columnData[] = $data['start_time'];
                    $columnData[] = $data['end_time'];
                    $columnData[] = $data['location_name'];
                    $columnData[] = $data['trading_name'];
                    $columnData[] = isset($data['first_approval']['userfullname']) ? $data['first_approval']['userfullname'] : '-';
                    $columnData[] = isset($data['second_approval']['userfullname']) ? $data['second_approval']['userfullname'] : '-';
                    $columnData[] = isset($status[$data['status_id']]) ? $status[$data['status_id']] : '-';
                    $columnData[] = $data['notes'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'HolidayRequest', 'xlsx', 'A1:K1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "Holiday Request list.", ['data' => $holiday], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Holiday Request listing failed : " . $e->getMessage());
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
            'date' => 'required',
            'location_id' => 'required',
            'start_time' => 'required',
            'end_time' => 'required',
            'entity_id' => 'required'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

        // store leave Request  details
        $holiday = \App\Models\Backend\HrHolidayRequest::create(['user_id' => app('auth')->guard()->id(),
                    'date' => date("Y-m-d", strtotime($request->get('date'))),
                    'start_time' => $request->get('start_time'),
                    'end_time' => $request->get('end_time'),
                    'location_id' => $request->get('location_id'),
                    'entity_id' => $request->get('entity_id'),
                    'notes' => $request->get('notes'),
                    'status_id' => 3,
                    'created_on' => date('Y-m-d'),
                    'created_by' => loginUser(),
                    'first_approval' => $request->get('first_approval'),
                    'second_approval' => $request->get('second_approval')]);

        $user = \App\Models\User::where("id", $request->get('first_approval'))->first();
        $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('LATEAPPROVAL');
        if ($emailTemplate->is_active == 1 && $user->email != '') {
            $search = array('USER_NAME', 'REMARKTYPE');
            $replace = array(app('auth')->guard()->user()->userfullname, 'Holiday Working');
            $locationName = \App\Models\Backend\HrLocation::where("id", $request->get('location_id'))->first();
            $entityName = \App\Models\Backend\Entity::where("id", $request->get('entity_id'))->first();
            $table = '<table><tr><td>Date:</td><td>' . $request->get('date') . '</td></tr>
                         <tr><td>Start Time:</td><td>' . $request->get('start_time') . '</td></tr>
                         <tr><td>End Time:</td><td>' . $request->get('end_time') . '</td></tr>
                         <tr><td>Location:</td><td>' . $locationName->location_name . '</td></tr>
                         <tr><td>Client Name:</td><td>' . $entityName->trading_name . '</td></tr>
                         <tr><td>Notes:</td><td>' . $request->get('notes') . '</td></tr>
                        </table>';

            $search1 = array('USER_NAME', 'REMARKTYPE', 'STAFF_NAME', 'REASON', 'APPROVALPERSON');
            $replace1 = array(app('auth')->guard()->user()->userfullname, 'Holiday Working', $user->userfullname, $request->get('notes'), $table);

            $data['to'] = $user->email;
            $data['cc'] = $emailTemplate->cc;
            $data['bcc'] = $emailTemplate->bcc;
            $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
            $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
            storeMail($request, $data);
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Holiday Request  has been added successfully', ['data' => $holiday]);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday Request  creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Holiday Request', ['error' => 'Could not add Holiday Request']);
          } */
    }

    public function approved(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'status_id' => 'required',
            'comment' => 'required',
            'approval_type' => 'required|in:1,2'], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $holiday = \App\Models\Backend\HrHolidayRequest::find($id);
        $statusId = $request->input('status_id');
        if ($request->input('approval_type') == 1 && $statusId !=6) {
            if ($holiday->second_approval == null ) {
                $statusId = 5;
            }
            \App\Models\Backend\HrHolidayRequest::where("id", $id)->update(['first_approval_comment' => $request->input('comment'),
                'first_approval_on' => date('Y-m-d H:i:s'),
                'status_id' => $statusId]);
            if ($holiday->second_approval == null) {
                //$seconduser = \App\Models\User::where("id", $holiday->second_approval)->first();
                $user = \App\Models\User::where("id", $holiday->user_id)->first();
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('LATESECONDAPPROVAL');
                if ($emailTemplate->is_active == 1 && $user->email != '' && $status_id == 4) {
                    $search = array('USER_NAME', 'REMARKTYPE');
                    $replace = array(app('auth')->guard()->user()->userfullname, 'Holiday Working');
                    $locationName = \App\Models\Backend\HrLocation::where("id", $holiday->location_id)->first();
                    $entityName = \App\Models\Backend\Entity::where("id", $holiday->entity_id)->first();
                    $table = '<table><tr><td>Date:</td><td>' . $holiday->date . '</td></tr>
                                 <tr><td>Start Time:</td><td>' . $holiday->start_time . '</td></tr>
                                 <tr><td>End Time:</td><td>' . $holiday->end_time . '</td></tr>
                                 <tr><td>Location:</td><td>' . $locationName->location_name . '</td></tr>
                                 <tr><td>Client Name:</td><td>' . $entityName->trading_name . '</td></tr>
                                 <tr><td>Notes:</td><td>' . $holiday->notes . '</td></tr>
                                 <tr><td>First Approval Comment:</td><td>' . $holiday->first_approval_comment . '</td></tr>
                                </table>';

                    $search1 = array('USER_NAME', 'REMARKTYPE', 'STATUS', 'SECOND_STAFF', 'REASON', 'FIRST_SECOND_COMMENT');
                    $replace1 = array($user->userfullname, 'Pending For Second Approval', 'Holiday Working', $user->userfullname, $holiday->notes, $table);

                    $data['to'] = $user->email;
                    $data['cc'] = $emailTemplate->cc;
                    $data['bcc'] = $emailTemplate->bcc;
                    $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                    $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
                    storeMail($request, $data);
                }
            }
        } else {
            \App\Models\Backend\HrHolidayRequest::where("id", $id)->update(['second_approval_comment' => $request->input('comment'),
                'second_approval_on' => date('Y-m-d H:i:s'),
                'status_id' => $statusId]);
        }

        if ($statusId == 5) {
            $user = \App\Models\User::where("id", $holiday->user_id)->first();
            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('REQUESTFINALSTATUS');
            $search = array('USER_NAME', 'REMARKTYPE');
            $replace = array(app('auth')->guard()->user()->userfullname, 'Holiday Working');
            $locationName = \App\Models\Backend\HrLocation::where("id", $holiday->location_id)->first();
            $entityName = \App\Models\Backend\Entity::where("id", $holiday->entity_id)->first();
            $table = '<table><tr><td>Date:</td><td>' . $holiday->date . '</td></tr>
                                 <tr><td>Start Time:</td><td>' . $holiday->start_time . '</td></tr>
                                 <tr><td>End Time:</td><td>' . $holiday->end_time . '</td></tr>
                                 <tr><td>Location:</td><td>' . $locationName->location_name . '</td></tr>
                                 <tr><td>Client Name:</td><td>' . $entityName->trading_name . '</td></tr>
                                 <tr><td>Notes:</td><td>' . $holiday->notes . '</td></tr>
                                 <tr><td>First Approval Comment:</td><td>' . $holiday->first_approval_comment . '</td></tr>
                                 <tr><td>Second Approval Comment:</td><td>' . $holiday->second_approval_comment . '</td></tr>
                                </table>';

            $search1 = array('USER_NAME', 'REMARKTYPE', 'STATUS', 'FIRST_SECOND_COMMENT');
            $replace1 = array($user->userfullname, 'Holiday Working', 'Approved', $table);

            $data['to'] = $user->email;
            $data['cc'] = $emailTemplate->cc;
            $data['bcc'] = $emailTemplate->bcc;
            $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
            $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
            storeMail($request, $data);
        }

        if ($statusId == 6) {
            $user = \App\Models\User::where("id", $holiday->user_id)->first();
            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('REQUESTFINALSTATUS');
            $search = array('USER_NAME', 'REMARKTYPE');
            $replace = array(app('auth')->guard()->user()->userfullname, 'Holiday Working');
            $locationName = \App\Models\Backend\HrLocation::where("id", $holiday->location_id)->first();
            $entityName = \App\Models\Backend\Entity::where("id", $holiday->entity_id)->first();
            $table = '<table><tr><td>Date:</td><td>' . $holiday->date . '</td></tr>
                                 <tr><td>Start Time:</td><td>' . $holiday->start_time . '</td></tr>
                                 <tr><td>End Time:</td><td>' . $holiday->end_time . '</td></tr>
                                 <tr><td>Location:</td><td>' . $locationName->location_name . '</td></tr>
                                 <tr><td>Client Name:</td><td>' . $entityName->trading_name . '</td></tr>
                                 <tr><td>Notes:</td><td>' . $holiday->notes . '</td></tr>
                                 <tr><td>First Approval Comment:</td><td>' . $holiday->first_approval_comment . '</td></tr>
                                 <tr><td>Second Approval Comment:</td><td>' . $holiday->first_approval_comment . '</td></tr>
                                </table>';

            $search1 = array('USER_NAME', 'REMARKTYPE', 'STATUS', 'FIRST_SECOND_COMMENT');

            $replace1 = array($user->userfullname, 'Holiday Working', 'Reject', $table);

            $data['to'] = $user->email;
            $data['cc'] = $emailTemplate->cc;
            $data['bcc'] = $emailTemplate->bcc;
            $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
            $data['content'] = str_replace($search1, $replace1, $emailTemplate->content);
            storeMail($request, $data);
        }


        return createResponse(config('httpResponse.SUCCESS'), 'Holiday Request has been approve successfully', ['data' => 'Holiday Request has been approve successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Holiday Request approve failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not approve leave Request ', ['error' => 'Could not approve leave Request ']);
          } */
    }

    public function destroy($id) {
        try {
            $hrHolidayRequest = \App\Models\Backend\HrHolidayRequest::find($id);
            if (!$hrHolidayRequest)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Holiday does not exist', ['error' => 'The Holiday does not exist']);

            $hrHolidayRequest->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Holiday Request has been deleted successfully', ['message' => 'Holiday Request has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Leave Request download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Holiday Request.', ['error' => 'Could not delete Holiday Request.']);
        }
    }

}
