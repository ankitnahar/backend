<?php

namespace App\Http\Controllers\Backend\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\HrNojob;

class NojobController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: April 22, 2018
     * Purpose   : Fetch no job data
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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'hr_nojob.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $noJob = HrNojob::with('noJobStaff:id,userfullname')->leftjoin("user_hierarchy as uh", "uh.user_id", "hr_nojob.user_id");
        if ($sortBy == 'user_id') {
            $noJob = $noJob->leftjoin("user as u", "u.id", "hr_nojob.$sortBy");
            $sortBy = 'userfullname';
        }
        $user = getLoginUserHierarchy();
         $userTeam = explode(',', $user->team_id);
            if (in_array(1, $userTeam))
                $teamId = 1;
            else if (in_array(2, $userTeam))
                $teamId = 2;
        if(in_array($user->designation_id,array(9,60,61))){
            $noJob = $noJob->whereRaw("FIND_IN_SET(" . $teamId . ", uh.team_id)");
        }      
        $userId = '';
        if ($request->has('search')) {
            $search = \GuzzleHttp\json_decode($request->get('search'), true);
            if (isset($search['compare']['equal']['tam']) && $search['compare']['equal']['tam'] != '') {
                $userId = $search['compare']['equal']['tam'];

                unset($search['compare']['equal']['tam']);
            }

            if (isset($search['compare']['equal']['division_head']) && $search['compare']['equal']['division_head'] != '') {
                $userId = $search['compare']['equal']['division_head'];
                unset($search['compare']['equal']['division_head']);
            }
            $search = \GuzzleHttp\json_encode($search);
            $alias = array('is_active' => 'hr_nojob','user_id'=>'hr_nojob');
            $noJob = search($noJob, $search, $alias);
        }

        if ($userId != '') {
            $userDetail = \App\Models\Backend\UserHierarchy::where('parent_user_id', $userId)->pluck('user_id', 'id')->toArray();
            $noJob = $noJob->whereIn('hr_nojob.user_id', $userDetail);
        }

        $user = getLoginUserHierarchy();
        if ($user->designation_id != config('constant.SUPERADMIN') && !in_array($user->designation_id,array(9,15,60))) {
            $noJob = $noJob->where('hr_nojob.user_id', app('auth')->guard()->id());
        }
        
        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $noJob = $noJob->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $noJob->count();

            $noJob = $noJob->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $noJob = $noJob->get(['hr_nojob.*']);
            $filteredRecords = count($noJob);

            $pager = ['sortBy' => $request->get('sortBy'),
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        $noJob = arrangeData($noJob);
        if ($request->has('excel') && $request->get('excel') == 1) {
            $data = $noJob->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Date', 'Staff name', 'Technical account manager', 'Division head', 'Start time', 'End time'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                foreach ($data as $data) {
                    $columnData[] = $i;
                    $columnData[] = dateFormat($data['date']);
                    $columnData[] = isset($data['no_job_staff']['userfullname']) ? $data['no_job_staff']['userfullname'] : '-';
                    $columnData[] = isset($data['assignee']['technical_account_manager']['username']) ? $data['assignee']['technical_account_manager']['username'] : '-';
                    $columnData[] = isset($data['assignee']['division_head']['username']) ? $data['assignee']['division_head']['username'] : '-';
                    $columnData[] = $data['start_time'];
                    $columnData[] = $data['end_time'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'noJob', 'xlsx', 'A1:G1');
        }

        return createResponse(config('httpResponse.SUCCESS'), "No job list.", ['data' => $noJob], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("No job listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing no job", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Store no job data
     */

    public function store(Request $request) {
        //try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store no job  details
            $startTime = $request->get('start_time');
            $endTime = $request->get('end_time');
            $id = app('auth')->guard()->id();
            $nojob = HrNojob::create(['user_id' => app('auth')->guard()->id(),
                        'date' => $request->get('date'),
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'is_active' => 1]);

            $user = \App\Models\User::where("id", loginUser())->select('first_approval_user','second_approval_user')->first();
           /* $userTeam = explode(',', $user->team_id);
            if (in_array(1, $userTeam))
                $teamId = 1;
            else if (in_array(2, $userTeam))
                $teamId = 2;
            else

            $staffList = \App\Models\User::select('email')->leftjoin('user_hierarchy AS uh', 'uh.user_id', '=', 'user.id')
                    ->whereIn('uh.designation_id', [9, 60, 61])
                    ->where('is_active', 1)->where("send_email","1")
                    ->get();*/
            $staffList = \App\Models\User::select('email')
                    ->whereRaw("id in ($user->first_approval_user, $user->second_approval_user)")
                    ->get();
            
            $emailID = array();
            foreach ($staffList as $key => $value) {
                $emailID[] = $value->email;
            }

            $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('NOJOB');
            if ($emailTemplate->is_active == 1 && count($emailID) > 0) {
                $search = array('USERNAME', 'DATE', 'STARTTIME', 'ENDTIME');
                $replace = array(app('auth')->guard()->user()->userfullname, date('d-m-Y'), date('h:i a', strtotime($startTime)), date('h:i a', strtotime($endTime)));

                $data['to'] = implode(',', $emailID);
                $data['cc'] = $emailTemplate->cc;
                $data['bcc'] = $emailTemplate->bcc;
                $data['subject'] = str_replace($search, $replace, $emailTemplate->subject);
                $data['content'] = str_replace($search, $replace, $emailTemplate->content);
                storeMail($request, $data);
            }

            return createResponse(config('httpResponse.SUCCESS'), 'No job  has been added successfully', ['data' => $nojob]);
        /*} catch (\Exception $e) {
            app('log')->error("No job  creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add no job ', ['error' => 'Could not add no job ']);
        }*/
    }

    public function update(Request $request, $id) {
        try {
            $noJob = HrNojob::find($id);
            $noJob->is_active = 0;
            $noJob->save();
            return createResponse(config('httpResponse.SUCCESS'), 'No job  has been update successfully', ['data' => 'No job  has been update successfully']);
        } catch (\Exception $e) {
            app('log')->error("No job updation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update no job ', ['error' => 'Could not update no job ']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'date' => 'required|date|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i'
                ], ['start_time.required' => 'The start time field is required',
            'start_time.date_format' => 'The start time format is not valid',
            'end_time.required' => 'The end time field is required',
            'end_time.date_format' => 'The end time format is not valid']);
        return $validator;
    }

}
