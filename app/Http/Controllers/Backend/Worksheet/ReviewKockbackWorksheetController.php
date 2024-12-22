<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Worksheet;
use DB;
class ReviewKockbackWorksheetController extends Controller {

    /**
     * Get Review knock back worksheet detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
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
        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'worksheet.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $displayAll = array(7, 15);
        $user_id = app('auth')->guard()->id();
        $pager = [];

        if ($request->has('technical_account_manager'))
            $user_id = $request->get('technical_account_manager');

        if ($request->has('team_member'))
            $user_id = $request->get('team_member');

        $worksheet = Worksheet::getWorksheet();
        $worksheet = $worksheet->select("e.name", "e.billing_name", "e.trading_name", "e.discontinue_stage", "f.frequency_name", "ea.id as allocation", "bs.category_id", "worksheet.*", "bs.entity_grouptype_id", "egb.name as entity_grouptypename");
        $worksheet = $worksheet->with('worksheetReviewer:userfullname,id', 'worksheetPeerreviewer:userfullname as created_by,id');
        $designation = \App\Models\Backend\UserHierarchy::where('user_id', $user_id)->get();

        $statusId = array(2, 9);
        if ($request->get('type') == 'knockback')
            $statusId = array(9);

        if ($request->get('type') == 'readyforreview')
            $statusId = array(2);

        if ($request->get('type') == 'allocate' && in_array($designation[0]->designation_id, $displayAll))
            $worksheet->whereRaw('worksheet.worksheet_reviewer  != ""');

        if ($request->get('type') == 'unallocate' && in_array($designation[0]->designation_id, $displayAll))
            $worksheet->where('worksheet.worksheet_reviewer', '');

        $additional_assignee = $user_id;
        if ($request->has('additional_assignee'))
            $additional_assignee = $request->get('additional_assignee');

        $worksheet->whereIn('status_id', $statusId);
        //if (!in_array($designation[0]->designation_id, $displayAll)) {
        $checkAllocation = checkUserClientAllocation($user_id);
        if ($checkAllocation != 1) {
            $entity_id = 0;
            if (!empty($checkAllocation))
                $entity_id = implode(',', $checkAllocation);

            $worksheet = $worksheet->whereRaw("(worksheet.worksheet_reviewer = " . $user_id . " OR worksheet.entity_id IN (" . $entity_id . "))");
            //$worksheet = $worksheet->where("worksheet.worksheet_reviewer", $user_id );
            $userTeamId = $designation[0]->team_id;
            $internal = 1;
            if (!empty($userTeamId))
                $teamIds = explode(',', $userTeamId);
            for ($i = 0; $i < count($teamIds); $i++) {
                if ($teamIds[$i] == 1 || $teamIds[$i] == 2 || $teamIds[$i] == 6) {
                    $internal = 0;
                }
            }
            if($internal ==0){
            $worksheet = $worksheet->whereRaw("worksheet.service_id IN(" . $designation[0]->team_id . ")");
            }else{
                $worksheet = $worksheet->where("worksheet.service_id","0");
                for ($i = 0; $i < count($teamIds); $i++) {
                    if($i!=0){
                     $q1 .=" OR ";   
                    }
                   $q1="FIND_IN_SET(user_team_id,$teamIds[$i])";
                }
                $masterId = \App\Models\Backend\MasterActivity::select(DB::raw("GROUP_CONCAT(id) as masterIds"))->whereRaw($q1)->first();
                
                $worksheet = $worksheet->whereRaw("worksheet.master_activity_id IN($masterId->masterIds)");
            }
        }
        //echo getSQL($worksheet);exit;
        //}git 

        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('category_id' => 'bs', 'related_entity' => 'e', 'entity_id' => 'worksheet', 'end_date' => 'worksheet', 'start_date' => 'worksheet', 'task_id' => 'worksheet', 'master_activity_id' => 'worksheet', 'frequency_id' => 'worksheet', 'due_date' => 'worksheet');
            $worksheet = search($worksheet, $search, $alias);
        }

        if ($sortBy == 'master_name') {
            $worksheet = $worksheet->leftjoin("master_activity as m", "m.id", "worksheet.master_id");
            $sortBy = 'm.name';
        }

        /* if ($sortBy == 'task_name') {
          $worksheet = $worksheet->leftjoin("task as t", "t.id", "worksheet.task_id");
          $sortBy = 't.name';
          }

          if ($sortBy == 'status_id') {
          $worksheet = $worksheet->leftjoin("worksheet_status as ws", "ws.id", "worksheet.status_id");
          $sortBy = 't.name';
          } */
        ////for task 23-04-2019
        if ($sortBy == 'task_id') {
            $worksheet = $worksheet->leftjoin("task as t", "t.id", "worksheet.task_id");
            $sortBy = 't.name';
        }

        if ($sortBy == 'status_id') {
            $worksheet = $worksheet->leftjoin("worksheet_status as ws", "ws.id", "worksheet.status_id");
            $sortBy = 'ws.status_name';
        }

        if ($request->get('action') == 'count') {
            $totalRecords['totalRecords'] = $worksheet->count();
            $worksheet = $totalRecords;
            goto countEnd;
        }

        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $worksheet = $worksheet->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $worksheet->count();

            $worksheet = $worksheet->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $worksheet = $worksheet->get();
            //echo $worksheet = $worksheet->toSql();
            $filteredRecords = count($worksheet);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        $worksheet = Worksheet::worksheetArrangeData($worksheet, 'reviewer');
        end:
        //For download excel if excel =1
        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $data = $worksheet->toArray();
            app('excel')->create('Report', function($excel) use($data) {
                $excel->sheet('Sheet 1', function($sheet) use($data) {

                    $sheet->row(1, array('Client Name', 'Entity Group', 'Task', 'Category', 'Frequency', 'Start Date', 'End Date', 'Due Date', 'Notes', 'Status', 'Technical Account Manager', 'Team Member', 'Reviewer', 'Units', 'Client status'));
                    $sheet->cell('A1:N1', function($cell) {
                        $cell->setFontColor('#ffffff');
                        $cell->setBackground('#0c436c');
                    });
                    $i = 2;
                    foreach ($data as $cleans) {
                        $tam = $tm = $reviewer = '-';
                        $allocation = $cleans['allocation'] != '' ? explode(',', $cleans['allocation']) : '';

                        if (!empty($allocation)) {
                            if (isset($allocation[0]) && strstr($allocation[0], 'Technical Account Manager-')) {
                                $tam = str_replace('Technical Account Manager-', '', $allocation[0]);
                            } else if (isset($allocation[1]) && strstr($allocation[1], 'Technical Account Manager-')) {
                                $tam = str_replace('Technical Account Manager-', '', $allocation[1]);
                            }

                            if (isset($allocation[0]) && strstr($allocation[0], 'Team Member-')) {
                                $tm = str_replace('Team Member-', '', $allocation[0]);
                            } else if (isset($allocation[1]) && strstr($allocation[1], 'Team Member-')) {
                                $tm = str_replace('Team Member-', '', $allocation[1]);
                            }
                        }

                        $reviewer = isset($cleans['worksheet_reviewer']['userfullname']) ? $cleans['worksheet_reviewer']['userfullname'] : 'Not specified';
                        if (isset($cleans['discontinue_stage']) && $cleans['discontinue_stage'] == 1) {
                            $discontinueStage = 'Discontinue Process start';
                        } else if (isset($cleans['discontinue_stage']) && $cleans['discontinue_stage'] == 2) {
                            $discontinueStage = 'Discontinue client';
                        } else {
                            $discontinueStage = 'Active';
                        }

                        $category = config('constant.category');
                        $todayDate = strtotime(date('Y-m-d'));
                        $dueDate = strtotime($cleans['due_date']);
                        if ($todayDate > $dueDate) {
                            $sheet->row($i, function($color) {
                                $color->setBackground('#FABABA');
                            });
                        }
                        $sheet->row($i, array($cleans['name'],
                            $cleans['entity_grouptypename'],
                            isset($cleans['task_id']['name']) ? $cleans['task_id']['name'] : '',
                            isset($category[$cleans['category_id']]) ? $category[$cleans['category_id']] : '-',
                            $cleans['frequency_name'],
                            dateFormat($cleans['start_date']),
                            dateFormat($cleans['end_date']),
                            dateFormat($cleans['due_date']),
                            $cleans['notes'],
                            $cleans['status_id']['status_name'],
                            $tam,
                            $tm,
                            $reviewer,
                            $cleans['timesheet_total_unit'],
                            $discontinueStage));
                        $i++;
                    }
                });
            })->export('xlsx', ['Access-Control-Allow-Origin' => '*']);
        }
        countEnd:
        return createResponse(config('httpResponse.SUCCESS'), "Worksheet list.", ['data' => $worksheet], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Worksheet listing failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Worksheet", ['error' => 'Server error.']);
//        }
    }

    /**
     * Store worksheet details
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');
            $exprtMode = $request->has('expert_day') ? $request->get('expert_day') : '';
            $dueAfterDay = $request->has('due_after_day') ? $request->get('due_after_day') : '0';
            $dueMonthDay = $request->has('due_month_day') ? $request->get('due_month_day') : '0';
            $dueOnParticulerDate = $request->has('due_on_particular_date') ? $request->get('due_on_particular_date') : '0';
            $frequency = $request->get('frequency_id');
            $comfirm = $request->has('comfirm') ? $request->get('comfirm') : '0';

            $worksheetData = array();
            $worksheetData = $this->generateDailyWorksheet($startDate, $endDate, $frequency, $exprtMode, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);

            if ($comfirm == 1) {
                $worksheet = $this->addWorksheet($request, $worksheetData);
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet has been added successfully', ['data' => $worksheetData]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add worksheet', ['error' => 'Could not add worksheet']);
        }
    }

    /**
     * UPdate worksheet status
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'status_id' => 'numeric',
                'start_date' => 'date_format:Y-m-d',
                'end_date' => 'date_format:Y-m-d',
                'reminder_date' => 'date_format:Y-m-d',
                'due_date' => 'date_format:Y-m-d'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $worksheet = Worksheet::find($id);

            if (!$worksheet)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The worksheet does not exist', ['error' => 'The worksheet does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['status_id', 'start_date', 'end_date', 'reminder_date', 'due_date', 'notes'], $request);
            if (!$request->has('status_id')) {
                if ($request->has('status_id') && $request->get('status_id') == 4) {
                    $worksheet->completed_by = app('auth')->guard()->id();
                    $worksheet->completed_on = date('Y-m-d H:i:s');
                }

                $worksheet->modified_by = app('auth')->guard()->id();
                $worksheet->modified_on = date('Y-m-d H:i:s');
            }
            $worksheet->update($updateData);

            if ($request->has('status_id')) {
                $worksheetLog = \App\Models\Backend\WorksheetLog::create(['worksheet_id' => $id,
                            'status_id' => $request->get('status_id'),
                            'created_by' => app('auth')->guard()->id(),
                            'created_on' => date('Y-m-d H:i:s')]);
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet detail has been updated successfully', ['message' => 'Worksheet detail has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update worksheet details.', ['error' => 'Could not update worksheet details.']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Aug 20, 2018
     * @param array $request
     * @return array
     */
    public function show(Request $request, $id) {
        try {
            $worksheet = Worksheet::select('worksheet.*', 'u.userfullname AS team_member')
                    ->with('masterActivityId:id,name', 'entityId:id,name,billing_name,trading_name', 'taskId:id,name')
                    ->leftjoin('entity_allocation as ea', 'ea.entity_id', '=', 'worksheet.entity_id')
                    ->leftjoin('user as u', 'u.id', '=', app('db')->raw('JSON_EXTRACT(ea.allocation_json, "$.10")'))
                    ->whereRaw('worksheet.service_id = ea.service_id')
                    ->find($id);

            if (!$worksheet)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The worksheet does not exist', ['error' => 'The worksheet does not exist']);

            $service_id = $request->get('service_id');
            $entity_id = $request->get('entity_id');

            // Fetch additional assignee data
            if ($request->has('is_assignee') && $request->get('type') == 1) {
                $entityAllocation = \App\Models\Backend\EntityAllocation::select('allocation_json')->where('entity_id', $entity_id)
                                ->where('service_id', $service_id)->get();
                $allocationStaff = \GuzzleHttp\json_decode($entityAllocation[0]->allocation_json, true);
                $assigneeStaff = array();
                $teamMember[] = (isset($allocationStaff[9]) && $allocationStaff[9] != null) ? $allocationStaff[9] : '';
                $teamMember[] = (isset($allocationStaff[10]) && $allocationStaff[10] != null) ? $allocationStaff[10] : '';
                $allocationStaff = \App\Models\User::select('id', 'userfullname')->whereIn('id', $teamMember)->pluck('userfullname', 'id')->toArray();

                if (!empty($allocationStaff))
                    $assigneeStaff['Teammember'] = $allocationStaff;

                $otherStaff = \App\Models\User::select('user.id', 'user.userfullname')
                                ->leftJoin('user_hierarchy as uh', 'uh.user_id', '=', 'user.id')
                                ->where('user.is_active', 1)
                                ->where('uh.designation_id', 10)
                                ->whereRaw('FIND_IN_SET(team_id, ' . $service_id . ')')
                                ->pluck('user.userfullname', 'user.id')->toArray();

                if (!empty($otherStaff))
                    $assigneeStaff['Otherteammember'] = $otherStaff;
            }

            // Fetch reviewer data
            if ($request->has('is_assignee') && $request->get('type') == 2) {
                $entityAllocation = \App\Models\Backend\EntityAllocation::select('allocation_json')->where('entity_id', $entity_id)
                                ->where('service_id', $service_id)->get();
                $allocationStaff = \GuzzleHttp\json_decode($entityAllocation[0]->allocation_json, true);
                $staticStaff = array(15, 59, 9, 60, 62, 63, 68, 69, 70, 71, 73);
                $prepareList = array();
                foreach ($staticStaff as $key => $value) {
                    if (isset($allocationStaff[$value]) && $allocationStaff[$value] != null)
                        $prepareList[] = $allocationStaff[$value];
                }

                $entityOtherAllocation = \App\Models\Backend\EntityAllocationOther::select('other')->where('entity_id', $entity_id)->get();
                $entityOtherAllocation = explode(',', $entityOtherAllocation[0]->other);
                $serviceBasedotherstaff = \App\Models\Backend\UserHierarchy::whereIn('user_id', $entityOtherAllocation)->whereRaw('FIND_IN_SET(' . $service_id . ',team_id)')->get();

                foreach ($serviceBasedotherstaff as $keys => $values)
                    $prepareList[] = $values->user_id;

                $assigneeStaff = \App\Models\User::whereIn('id', $prepareList)->pluck('userfullname', 'id')->toArray();
            }

            // Fetch peer reviewer data
            if ($request->has('is_assignee') && $request->get('type') == 3) {
                $assigneeStaff = \App\Models\Backend\UserHierarchy::leftjoin('user as u', 'u.id', '=', 'user_hierarchy.user_id')
                                ->whereIn('designation_id', array(62, 63))->pluck('u.userfullname', 'u.id')->toArray();
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet detail has been updated successfully', ['date' => $worksheet, 'assigneeStaff' => $assigneeStaff]);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet details.', ['error' => 'Could not get worksheet details.']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Feb 13, 2018
     * @param array $request
     * @return array
     */
    public function reviewerUnit(Request $request) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required|numeric',
            'worksheet_id' => 'required|numeric',
                // 'master_activity_id' => 'required|numeric'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $userId = app('auth')->guard()->id();
        $loginUserDetail = getLoginUserHierarchy();
        $entityId = $request->get('entity_id');
        $worksheetId = $request->get('worksheet_id');
        $masterActiviryId = $request->get('master_activity_id');

        $reviewerBudgetedUnit = \App\Models\Backend\Entity::where('id', $entityId)->pluck('reviewer_budgeted_unit', 'id')->toArray();
        $writeoffReviewer = \App\Models\Backend\WriteoffReviewer::where('worksheet_id', $worksheetId)->where('entity_id', $entityId)->count();

        $timesheet = \App\Models\Backend\UserHierarchy::select(app('db')->raw('SUM(units) AS totalUnit'))->join('timesheet AS t', 't.user_id', 'user_hierarchy.user_id')->whereIn('designation_id', [68, 69, 70, 71])->where('worksheet_id', $worksheetId)->get()->toArray();

        $loginUserDetail['designation_id'] = 70;
        $reviewerReason = \App\Models\Backend\WriteoffReasonManagement::where('is_active', 1)->where('is_deleted', 0)->where('category_id', 3)->whereRaw('FIND_IN_SET(' . $loginUserDetail['designation_id'] . ',designation_id)')->get()->toArray();

        $resultData['budgeted_unit'] = $reviewerBudgetedUnit[$entityId];
        $resultData['filled_unit'] = isset($timesheet[0]) ? $timesheet[0]['totalUnit'] : 0;
        $resultData['reviwerReason'] = $reviewerReason;
        $resultData['writeoff'] = $writeoffReviewer;
        return createResponse(config('httpResponse.SUCCESS'), 'Worksheet detail has been get successfully', ['data' => $resultData]);

//        } catch (\Exception $e) {
//            app('log')->error("Fetch reviewer unit failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get reviewer unit details.', ['error' => 'Could not get reviewer unit details.']);
//        }
    }

}

?>