<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Worksheet;

ini_set('memory_limit', '-1');

class WorksheetController extends Controller {

    /**
     * Get Worksheet detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'type' => 'required|in:my,incompleted,completed,befree,reviewer,peerreviewer,reviewandknockback,multiplestatus',
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
        $user_id = app('auth')->guard()->id();
        $pager = [];

        $worksheet = Worksheet::getWorksheet();
        $designation = getLoginUserHierarchy();

        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('category_id' => 'bs', 'related_entity' => 'e', 'entity_id' => 'worksheet', 'parent_id' => 'e', 'end_date' => 'worksheet', 'start_date' => 'worksheet', 'task_id' => 'worksheet', 'master_activity_id' => 'worksheet', 'frequency_id' => 'worksheet', 'due_date' => 'worksheet');
            $worksheet = search($worksheet, $search, $alias);
        }

        if ($request->has('technical_account_manager'))
            $user_id = $request->get('technical_account_manager');
        if ($request->has('team_lead')) {
            $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.60')) = " . $request->get('team_lead') . ")");
        }
        if ($request->has('associate_team_lead')) {
            $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.61')) = " . $request->get('associate_team_lead') . ")");
        }
        if ($request->has('team_member'))
            $user_id = $request->get('team_member');

        $additional_assignee = $user_id;
        if ($request->has('additional_assignee'))
            $additional_assignee = $request->get('additional_assignee');

        if ($request->get('type') == 'my') {
            $worksheet = $worksheet->where("status_id", "!=", "4");
            $userTeam = explode(',', $designation->team_id);
            $internal = 1;
            foreach ($userTeam as $key) {
                if ($key == 1 || $key == 2 || $key == 6) {
                    $internal = 0;
                }
            }
            if ($internal == 0) {
                if (!$request->has('technical_account_manager') && !$request->has('team_member')) {
                    $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_SEARCH(allocation_json, 'all', " . $user_id . ")) IS NOT NULL OR FIND_IN_SET(" . $additional_assignee . ", other) OR worksheet.worksheet_additional_assignee = " . $additional_assignee . " OR worksheet.worksheet_reviewer = " . $additional_assignee . " OR worksheet.worksheet_peerreviewer = " . $additional_assignee . ")");
                }

                if ($request->has('technical_account_manager')) {
                    $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.9')) = " . $request->get('technical_account_manager') . ")");
                }
                if ($request->has('team_lead')) {
                    $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.60')) = " . $request->get('team_lead') . ")");
                }
                if ($request->has('associate_team_lead')) {
                    $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.61')) = " . $request->get('associate_team_lead') . ")");
                }
                if ($request->has('team_member')) {
                    $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.10')) = " . $request->get('team_member') . ")");
                }

                if ($request->has('additional_assignee')) {
                    $worksheet = $worksheet->whereRaw("worksheet.worksheet_additional_assignee = " . $additional_assignee);
                }
            } else if ($internal == 1) {
                $worksheet = $worksheet->whereRaw("(worksheet.worksheet_additional_assignee = " . $additional_assignee . " OR worksheet.worksheet_reviewer = " . $additional_assignee . " OR worksheet.worksheet_peerreviewer = " . $additional_assignee . ")");
            }
            if (!in_array($designation->designation_id, array(7))) {
                $userTeam = explode(',', $designation->team_id);
                $appendTeam = array();
                foreach ($userTeam as $key) {
                    $appendTeam[] = "FIND_IN_SET (" . $key . ", user_team_id)";
                }
                if (!empty($appendTeam))
                    $worksheet = $worksheet->whereRaw("(" . implode(" OR ", $appendTeam) . ")");
            }
            //echo getSQL($worksheet);exit;
            if ($request->get('type') == 'my') {
                if (in_array($designation->designation_id, array(7, 15))) {
                    $worksheet = array();
                    if ($request->has('counter') && $request->has('counter') == 1)
                        $worksheet = 0;

                    goto end;
                }
            }
        }

        if ($request->get('type') == 'incompleted') {
            $worksheet = $worksheet->where("status_id", "!=", "4");
            $condition = 'OR';
            if ($additional_assignee != $user_id)
                $condition = 'AND';
//            if (!in_array($designation->designation_id, array(7, 15))) {
            if ($designation->designation_id != 7 || ($designation->designation_id == 15 && !in_array($designation->department_id, array(1, 2, 6)))) {
                if (!$request->has('technical_account_manager') && !$request->has('team_member')) {
                    $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_SEARCH(allocation_json, 'all', " . $user_id . ")) IS NOT NULL OR FIND_IN_SET(" . $additional_assignee . ", other) " . $condition . " worksheet.worksheet_additional_assignee = " . $additional_assignee . " OR worksheet.worksheet_reviewer = " . $additional_assignee . " OR worksheet.worksheet_peerreviewer = " . $additional_assignee . ")");
                }
            }

            if ($request->has('technical_account_manager')) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.9')) = " . $request->get('technical_account_manager') . ")");
            }
            if ($request->has('team_lead')) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.60')) = " . $request->get('team_lead') . ")");
            }
            if ($request->has('associate_team_lead')) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.61')) = " . $request->get('associate_team_lead') . ")");
            }
            if ($request->has('team_member')) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.10')) = " . $request->get('team_member') . ")");
            }

            if ($request->has('additional_assignee')) {
                $worksheet = $worksheet->whereRaw("worksheet.worksheet_additional_assignee = " . $additional_assignee);
            }

            if (!in_array($designation->designation_id, array(7))) {
                $userTeam = explode(',', $designation->team_id);
                $appendTeam = array();
                foreach ($userTeam as $key) {
                    $appendTeam[] = "FIND_IN_SET (" . $key . ", user_team_id)";
                }
                if (!empty($appendTeam))
                    $worksheet = $worksheet->whereRaw("(" . implode(" OR ", $appendTeam) . ")");
            }
        }

        if ($request->get('type') == 'completed') {
            $worksheet = $worksheet->leftjoin("worksheet_status_log as wg", function($join) {
                        $join->on('wg.worksheet_id', '=', 'worksheet.id');
                        $join->on('wg.status_id', '=', app('db')->raw('4'));
                    })->leftjoin("user as ug", "ug.id", "wg.created_by");
            $worksheet = $worksheet->where("worksheet.status_id", "=", "4");
//            if (!in_array($designation->designation_id, array(7, 15))) {
            if ($designation->designation_id != 7 || ($designation->designation_id == 15 && !in_array($designation->department_id, array(1, 2, 6)))) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_SEARCH(allocation_json, 'all', " . $user_id . ")) IS NOT NULL OR FIND_IN_SET(" . $additional_assignee . ", other) OR worksheet.worksheet_additional_assignee = " . $additional_assignee . " OR worksheet.worksheet_reviewer = " . $additional_assignee . " OR worksheet.worksheet_peerreviewer = " . $additional_assignee . ")");
            }

            if ($request->has('technical_account_manager')) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.9')) = " . $request->get('technical_account_manager') . ")");
            }
            if ($request->has('team_lead')) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.60')) = " . $request->get('team_lead') . ")");
            }
            if ($request->has('associate_team_lead')) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.61')) = " . $request->get('associate_team_lead') . ")");
            }

            if ($request->has('team_member')) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(allocation_json, '$.10')) = " . $request->get('team_member') . ")");
            }

            if ($request->has('additional_assignee')) {
                $worksheet = $worksheet->whereRaw("worksheet.worksheet_additional_assignee = " . $additional_assignee);
            }

            if (!in_array($designation->designation_id, array(7))) {
                $userTeam = explode(',', $designation->team_id);
                $appendTeam = array();
                foreach ($userTeam as $key) {
                    $appendTeam[] = "FIND_IN_SET (" . $key . ", user_team_id)";
                }
                if (!empty($appendTeam))
                    $worksheet = $worksheet->whereRaw("(" . implode(" OR ", $appendTeam) . ")");
            }
        }

        if ($request->get('type') == 'befree') {
            $BefreeWorksheetClient = \App\Models\Backend\Constant::where("constant_name", "WORKSHEET_BEFREE_CLIENT")->first();
            $worksheet = $worksheet->whereIn("worksheet.entity_id", [$BefreeWorksheetClient->constant_value])->where("status_id", "!=", "4");


//            if (!in_array($designation->designation_id, array(7, 15))) {
            if ($designation->designation_id != 7 || ($designation->designation_id == 15 && !in_array($designation->department_id, array(1, 2, 6)))) {
                $worksheet = $worksheet->whereRaw("JSON_UNQUOTE(JSON_SEARCH(allocation_json, 'all', " . $user_id . ")) IS NOT NULL");
            }
        }

        if ($request->get('type') == 'reviewandknockback') {
            $worksheet->whereIn('status_id', array(2, 9));
        }

        if ($sortBy == 'master_name') {
            $worksheet = $worksheet->leftjoin("master_activity as m", "m.id", "worksheet.master_id");
            $sortBy = 'm.name';
        }

        if ($sortBy == 'task_name') {
            $worksheet = $worksheet->leftjoin("task as t", "t.id", "worksheet.task_id");
            $sortBy = 't.name';
        }

        if ($sortBy == 'status_id') {
            $worksheet = $worksheet->leftjoin("worksheet_status as ws", "ws.id", "worksheet.status_id");
            $sortBy = 't.name';
        }

        if ($request->get('type') == 'multiplestatus') {
            $worksheet->whereNotIn("frequency_id", [3, 4, 5]);
            $worksheet->where("due_date", '>=', date('Y-m-d'));
            $worksheet->whereNotIn("master_activity_id", [1, 2]);
        }
        if ($request->get('type') == 'completed') {
            $worksheet = $worksheet->select("e.name", "e.billing_name","e.trading_name","ug.userfullname as completed_name", "e.parent_id", "ep.trading_name as parent_name", "e.discontinue_stage", "f.frequency_name", "ea.id as allocation", "bs.category_id", "worksheet.*", "wm.is_repeat_task", "bs.entity_grouptype_id");
        } else {
            $worksheet = $worksheet->select("e.name", "e.billing_name","e.trading_name", "e.parent_id", "ep.trading_name as parent_name", "e.discontinue_stage", "f.frequency_name", "ea.id as allocation", "bs.category_id", "worksheet.*", "wm.is_repeat_task", "bs.entity_grouptype_id");
        }
        $worksheet = $worksheet->where("e.discontinue_stage", "!=", 2);
        //$worksheet->where("worksheet.master_activity_id", "!=","8");
        // echo $worksheet->toSql(); die;
        if ($request->has('statuscounter') && $request->has('statuscounter') == 1) {
            $worksheet = $worksheet->select('ws.id', 'ws.status_name', app('db')->raw('COUNT(status_id) AS count'));
            $worksheet = $worksheet->leftjoin('worksheet_status As ws', 'ws.id', '=', 'status_id');
            $worksheet = $worksheet->groupBy('status_id')->get();
            goto end;
        }

        if ($request->has('counter') && $request->has('counter') == 1) {
            $worksheet = $worksheet->count();
            goto end;
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

            $filteredRecords = count($worksheet);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        $worksheet = Worksheet::worksheetArrangeData($worksheet, $request->get('type'));
        end:
        //For download excel if excel =1
        if ($request->has('excel') && $request->get('excel') == 1) {
            $category = config('constant.category');
            //format data in array 
            $data = $worksheet->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Parent Trading Name', 'Client Name', 'Task', 'Critical Task', 'Category', 'Frequency', 'Start Date', 'End Date', 'Due Date', 'Notes', 'Status', 'Completed By', 'Technical Account Manager', 'Team Lead', 'Associate Team Lead', 'Team Member', 'Additional Assignee', 'Units', 'Lock Worksheet', 'Client status', 'Account Team Budgeted Unit'];
            if (!empty($data)) {
                $columnData = array();
                $i = 1;
                if (isset($data['discontinue_stage']) && $data['discontinue_stage'] == 1) {
                    $discontinueStage = 'Discontinue Process start';
                } else if (isset($data['discontinue_stage']) && $data['discontinue_stage'] == 2) {
                    $discontinueStage = 'Discontinue client';
                } else {
                    $discontinueStage = 'Active';
                }
                $j = 0;
                foreach ($data as $data) {
                    $j++;
                    $tam = $tm = $tl = $atl = $additionalAssignee = '-';
                    $allocation = $data['allocationId'] != '' ? explode(',', $data['allocationId']) : '';
                    $completedBy = isset($data['completed_name']) ? $data['completed_name'] : '';
                    if (!empty($allocation)) {
                        for ($i = 0; $i < count($allocation); $i++) {
                            $allocationDesignation = explode("-", $allocation[$i]);
                            if ($allocationDesignation[0] == 9) {
                                $tam = $allocationDesignation[1];
                            }
                            if ($allocationDesignation[0] == 60) {
                                $tl = $allocationDesignation[1];
                            }
                            if ($allocationDesignation[0] == 61) {
                                $atl = $allocationDesignation[1];
                            }
                            if ($allocationDesignation[0] == 10) {
                                $tm = $allocationDesignation[1];
                            }
                        }
                    }

                    $additionalAssignee = isset($data['worksheet_additional_assignee']['userfullname']) ? $data['worksheet_additional_assignee']['userfullname'] : '-';

                    $columnData[] = $j;
                    $columnData[] = $data['parent_name'];
                    $columnData[] = $data['name'];
                    $columnData[] = $data['task_id']['name'];
                    $columnData[] = ($data['critical_task'] == 1) ? 'Yes' : 'No';
                    $columnData[] = isset($category[$data['category_id']]) ? $category[$data['category_id']] : '-';
                    $columnData[] = $data['frequency_name'];
                    $columnData[] = dateFormat($data['start_date']);
                    $columnData[] = dateFormat($data['end_date']);
                    $columnData[] = dateFormat($data['due_date']);
                    $columnData[] = $data['notes'] != '' ? $data['notes'] : '-';
                    $columnData[] = $data['status_id']['status_name'];
                    $columnData[] = $completedBy;
                    $columnData[] = $tam;
                    $columnData[] = $tl;
                    $columnData[] = $atl;
                    $columnData[] = $tm;
                    $columnData[] = $additionalAssignee;
                    $columnData[] = $data['timesheet_total_unit'];
                    $columnData[] = $data['lock_worksheet'] == 0 ? 'Open' : 'Lock';
                    $columnData[] = $discontinueStage;
                    $columnData[] = $data['budgeted_unit'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'WorksheetList', 'xlsx', 'A1:V1');
        }
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
        //  try {
        //validate request parameters
        $validator = $this->validateInput($request);
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $exprtDay = $request->has('expert_day') ? $request->get('expert_day') : '';
        $exprtMonth = $request->has('expert_month') ? $request->get('expert_month') : '';
        $dueAfterDay = $request->has('due_after_day') ? $request->get('due_after_day') : '0';
        $dueMonthDay = $request->has('due_month_day') ? $request->get('due_month_day') : '0';
        $dueOnParticulerDate = $request->has('due_on_particular_date') ? $request->get('due_on_particular_date') : '0';
        $frequency = $request->get('frequency_id');
        $notes = $request->get('notes');
        $comfirm = $request->has('comfirm') ? $request->get('comfirm') : '0';
        $buttonType = $request->input('button_type');
        $worksheetData = array();
        $worksheetData = self::generateDailyWorksheet($startDate, $endDate, $frequency, $exprtDay, $exprtMonth, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);

        $entity_id = explode(',', $request->get('entity_id'));
        $entity = \App\Models\Backend\Entity::select(app('db')->raw('GROUP_CONCAT(trading_name) as trading_name'))->whereIn('id', $entity_id)->first();
        $task = \App\Models\Backend\TaskActivity::select('name')->find($request->get('task_id'));
        $masterActivity = \App\Models\Backend\MasterActivity::select('name')->find($request->get('master_activity_id'));

        $basicInfo['entity_name'] = $entity->trading_name;
        $basicInfo['master_activity_name'] = $masterActivity->name;
        $basicInfo['task_name'] = $task->name;
        $basicInfo['note'] = $notes;
        if ($comfirm == 1 && $buttonType == 1) {
            $worksheet = $this->addWorksheet($request, $worksheetData);
            //return createResponse(config('httpResponse.SUCCESS'), 'Worksheet has been added successfully', ['message' => 'Worksheet has been added successfully']);
        } else if ($buttonType == 2) {
            $this->setRule($request);
            //return createResponse(config('httpResponse.SUCCESS'), 'Worksheet Schedule been added successfully', ['message' => 'Worksheet Schedule has been added successfully']);
        } else if ($comfirm == 1 && $buttonType == 3) {
            $worksheet = $this->addWorksheet($request, $worksheetData);
            $this->setRule($request);
            //return createResponse(config('httpResponse.SUCCESS'), 'Worksheet and Worksheet Schedule has been added successfully', ['message' => 'Worksheet and Worksheet Schedule has been added successfully']);
        }


        return createResponse(config('httpResponse.SUCCESS'), 'Worksheet detail generated successfull', ['basicinfo' => $basicInfo, 'data' => $worksheetData]);
        /*  } catch (\Exception $e) {
          app('log')->error("Worksheet creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add worksheet', ['error' => 'Could not add worksheet']);
          } */
    }

    public function setRule(Request $request) {
        $entity_id = explode(',', $request->get('entity_id'));
        $epday = '';
        if ($request->has('expert_day') && !empty($request->get('expert_day')) && $request->get('expert_day') != '' && $request->get('expert_day') != null)
            $epday = $request->get('expert_day');

        $epmonth = '';
        if ($request->has('expert_month') && !empty($request->get('expert_month')) && $request->get('expert_month') != '' && $request->get('expert_month') != null)
            $epmonth = $request->get('expert_month');

        $queryData['master_activity_id'] = !empty($request->input('master_activity_id')) ? $request->input('master_activity_id') : 0;

        $queryData['task_id'] = !empty($request->input('task_id')) ? $request->input('task_id') : 0;
        $queryData['start_date'] = !empty($request->input('start_date')) ? $request->input('start_date') : '';
        $queryData['end_date'] = !empty($request->input('end_date')) ? $request->input('end_date') : '';
        $queryData['frequency_id'] = !empty($request->input('frequency_id')) ? $request->input('frequency_id') : 0;
        $queryData['expert_day'] = $epday;
        $queryData['expert_month'] = $epmonth;
        $queryData['due_date_type'] = $request->input('due_date_period');
        $queryData['due_after_day'] = !empty($request->input('due_after_day')) ? $request->input('due_after_day') : 0;
        $queryData['due_month_day'] = !empty($request->input('due_month_day')) ? $request->input('due_month_day') : 0;
        $queryData['due_on_particular_date'] = !empty($request->input('due_on_particular_date')) ? $request->input('due_on_particular_date') : 0;
        $queryData['notes'] = $request->input('notes');
        $queryData['created_by'] = !empty($request->input('created_by')) ? $request->input('created_by') : 0;
        $queryData['created_on'] = !empty($request->input('created_on')) ? $request->input('created_on') : 0;
        foreach ($entity_id as $key => $value) {
            $entityCheck = \App\Models\Backend\Entity::where("id", $value)->first();
            if ($entityCheck->discontinue_stage == 0) {
                $queryData['entity_id'] = $value;

                \App\Models\Backend\WorksheetSchedule::insert($queryData);
            }
        }
    }

    /**
     * UPdate worksheet status
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        // try {
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
        $canchangeperiodstartdate_enddate = checkButtonRights(80, 'canchangeperiodstartdate_enddate');
        if (($request->has('start_date') || $request->has('end_date')) && $canchangeperiodstartdate_enddate != 1) {
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.UNAUTHORIZEDBUTTONACCESS'), ['error' => 'Not right to update start or end date']);
        }

        $canchangeduedate = checkButtonRights(80, 'canchangeduedate');
        if ($request->has('due_date') && $canchangeduedate != 1) {
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.UNAUTHORIZEDBUTTONACCESS'), ['error' => 'Not right to update due date']);
        }

        $canchangereminderdate = checkButtonRights(80, 'canchangereminderdate');
        if ($request->has('reminder_date') && $canchangereminderdate != 1) {
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.UNAUTHORIZEDBUTTONACCESS'), ['error' => 'Not right to update remider date']);
        }

        $worksheetstatusrights = checkButtonRights(80, 'worksheetstatusrights');
        $statusId = $request->get('status_id');
        if ($request->has('status_id') && $worksheetstatusrights != 1) {
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.UNAUTHORIZEDBUTTONACCESS'), ['error' => 'Not right to update worksheet status']);
        } else if ($request->has('status_id') && $worksheetstatusrights == 1) {

            if ($statusId == 13)
                $worksheet->reportsent_count = 1;

            $worksheetLog = \App\Models\Backend\WorksheetLog::create(['worksheet_id' => $id,
                        'status_id' => $statusId,
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);
        }

        $updateData = filterFields(['status_id', 'start_date', 'end_date', 'reminder_date', 'due_date', 'notes', 'delay_from', 'delay_comment', 'delay_from_befree_action'], $request);
        if ($request->has('is_there_delay')) {
            if ($request->input('is_there_delay') == 2 || $request->input('is_there_delay') == 0)
                $worksheet->is_there_delay = 0;
            else {
                $worksheet->is_there_delay = 1;
            }
        }
        $worksheet->modified_by = app('auth')->guard()->id();
        $worksheet->modified_on = date('Y-m-d H:i:s');
        $worksheet->update($updateData);

        //for hold work all worksheet status should be hold
        // and can start work also change status to 
        if ($statusId == 7 || $statusId == 14) {
            $allworksheet = Worksheet::whereIn("status_id", [0, 1])->where("entity_id", $worksheet->entity_id)->get();
            if ($statusId == 7) {
                $statusId = 0;
                $allworksheet = Worksheet::where("status_id", 14)->where("entity_id", $worksheet->entity_id)->get();
                \App\Models\Backend\Entity::where("id", $worksheet->entity_id)->update(['hold' => 0]);
            }
            if ($statusId == 14) {
                $entityName = \App\Models\Backend\Entity::where("id", $worksheet->entity_id)->first();
                $emailTemplate = \App\Models\Backend\EmailTemplate::where('code', 'WORKSHEETHOLD')->first();
                $search = array('CLIENTNAME');
                $replace = array($entityName->trading_name);
                $subject = str_replace($search, $replace, $emailTemplate->subject);
                $content = str_replace($search, $replace, $emailTemplate->content);

                $entityAllocation = \App\Models\Backend\EntityAllocation::select('allocation_json')->where('entity_id', $worksheet->entity_id)
                                ->where('service_id', 1)->get();
                $allocationStaff = \GuzzleHttp\json_decode($entityAllocation[0]->allocation_json, true);
                $assigneeStaff = array();
                $teamMember[] = (isset($allocationStaff[9]) && $allocationStaff[9] != null) ? $allocationStaff[9] : '';
                $teamMember[] = (isset($allocationStaff[10]) && $allocationStaff[10] != null) ? $allocationStaff[10] : '';
                $teamMember[] = (isset($allocationStaff[60]) && $allocationStaff[60] != null) ? $allocationStaff[60] : '';
                $teamMember[] = (isset($allocationStaff[61]) && $allocationStaff[61] != null) ? $allocationStaff[61] : '';
                $allocationStaffEmail = \App\Models\User::select('email')->whereIn('id', $teamMember)->get();
                $email = '';
                $emailArray = array();
                if (!empty($allocationStaffEmail)) {
                    foreach ($allocationStaffEmail as $e) {
                        $emailArray[] = $e->email;
                    }
                }
                if (!empty($emailArray)) {
                    $email = implode(",", $emailArray);
                }
                if ($email != '') {
                    \App\Models\Backend\Entity::where("id", $worksheet->entity_id)->update(['hold' => 1]);
                    $data['to'] = $email;
                    $data['cc'] = $emailTemplate->cc;
                    $data['content'] = $content;
                    $data['subject'] = $subject;
                    storeMail($request, $data);
                }
            }



            foreach ($allworksheet as $work) {
                Worksheet::where("id", $work->id)->update(["status_id" => $statusId]);
                $worksheetLog = \App\Models\Backend\WorksheetLog::create(['worksheet_id' => $work->id,
                            'status_id' => $statusId,
                            'created_by' => app('auth')->guard()->id(),
                            'created_on' => date('Y-m-d H:i:s')]);
            }
        }

        return createResponse(config('httpResponse.SUCCESS'), 'Worksheet detail has been updated successfully', ['message' => 'Worksheet detail has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Entity special notes updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update worksheet details.', ['error' => 'Could not update worksheet details.']);
          } */
    }

    /**
     * Change multiple worksheet status details
     * @param  int  $id   //master activity id
     * @return Illuminate\Http\JsonResponse
     */
    public function mupltipleUpdate(Request $request) {
        //try {
        $is_right = checkButtonRights(80, 'multipleworksheetstatuschange');
        if ($is_right == '')
            return createResponse(config('httpResponse.UNPROCESSED'), 'Not right to change workshet multiple status', ['error' => 'Not right to change workshet multiple status']);

        $validator = app('validator')->make($request->all(), [
            'status_id' => 'required',
            'id' => 'required'], []);

        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        //$workSheetIds = \GuzzleHttp\json_decode($request->get('id'));
        $workSheetIds = $request->get('id');
        $worksheet = Worksheet::whereIn('id', $workSheetIds)->get()->toArray();

        // Check weather bank exists or not
        if (empty($worksheet))
            return createResponse(config('httpResponse.NOT_FOUND'), 'Worksheet does not exist', ['error' => 'Worksheet does not exist']);

        app('db')->table('worksheet')->whereIn('id', $workSheetIds)->update(['status_id' => $request->get('status_id')]);

        return createResponse(config('httpResponse.SUCCESS'), 'Worksheet status has been successfully', ['message' => 'Worksheet status has been successfully']);
//        } catch (\Exception $e) {
//            app('log')->error("Worksheet deletion failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update status worksheet.', ['error' => 'Could not update status worksheet.']);
//        }
    }

    /**
     * delete multiple worksheet details
     * @param  int  $id   //master activity id
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id) {
        try {
            $is_right = checkButtonRights(80, 'multipleworksheetdelete');
            if ($is_right == '')
                return createResponse(config('httpResponse.UNPROCESSED'), 'Not right to change workshet multiple status', ['error' => 'Not right to change workshet multiple status']);

            $workSheetIds = explode(',', $id);
            // If validation fails then return error response
            if (empty($workSheetIds))
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => 'Provide worksheet ids']);

            $worksheet = Worksheet::whereIn('id', $workSheetIds)->get()->toArray();

            if (empty($worksheet))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Worksheet does not exist', ['error' => 'Worksheet does not exist']);

            $taskChecklist_id = \App\Models\Backend\WorksheetTaskChecklist::select('id')->wherein('worksheet_id', $workSheetIds)->get()->toArray();
            Worksheet::wherein('id', $workSheetIds)->delete();
            \App\Models\Backend\WorksheetTaskChecklist::wherein('worksheet_id', $workSheetIds)->delete();
            \App\Models\Backend\WorksheetTaskChecklistComment::wherein('worksheet_task_checklist_id', $taskChecklist_id)->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet has been deleted successfully', ['message' => 'Worksheet has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Worksheet deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete worksheet.', ['error' => 'Could not delete worksheet.']);
        }
    }

    /**
     * 
     * @param array $request
     * @param array $data
     * @return array
     */
    public function addWorksheet(Request $request, $data) {
        $entity_id = explode(",", $request->get('entity_id'));
        $master_activity_id = $request->get('master_activity_id');
        $task_id = $request->get('task_id');
        $frequency_id = $request->get('frequency_id');
        $notes = $request->get('notes');
        $criticalTask = $request->get('critical_task');
        $budgetedUnit = $request->has('budgeted_unit') ? $request->get('budgeted_unit') : 0;
        $taskData = \App\Models\Backend\TaskActivity::select('ask_repeat_task')->find($task_id);
        $masterActivity = \App\Models\Backend\MasterActivity::select('service_id')->find($master_activity_id);

        $worksheet = $worksheetData = array();
        foreach ($entity_id as $kay => $value) {
            $worksheetMaster = new \App\Models\Backend\WorksheetMaster;
            $worksheetMaster->master_activity_id = $master_activity_id;
            $worksheetMaster->entity_id = $value;
            $worksheetMaster->task_id = $task_id;
            $worksheetMaster->is_repeat_task = $taskData->ask_repeat_task;
            $worksheetMaster->start_date = $request->get('start_date');
            $worksheetMaster->end_date = $request->get('end_date');
            $worksheetMaster->frequency_id = $frequency_id;

            if ($request->has('expert_day'))
                $worksheetMaster->expert_day = $request->get('expert_day');

            if ($request->has('expert_month'))
                $worksheetMaster->expert_month = $request->get('expert_month');

            if ($request->has('due_after_day'))
                $worksheetMaster->due_after_day = $request->get('due_after_day');

            if ($request->has('due_month_day'))
                $worksheetMaster->due_month_day = $request->get('due_month_day');

            if ($request->has('due_on_particular_date'))
                $worksheetMaster->due_on_particular_date = $request->get('due_on_particular_date');

            $worksheetMaster->notes = $notes;
            $worksheetMaster->created_by = app('auth')->guard()->id();
            $worksheetMaster->created_on = date('Y-m-d H:i:s');
            $worksheetMaster->save();
            $worksheetMasterid = $worksheetMaster->id;

            $entityAllocation = \App\Models\Backend\EntityAllocation::select('allocation_json', 'service_id')->where('entity_id', $value)->get();
            $serviceViseAllocation = array();
            foreach ($entityAllocation as $keyAllocation => $valueAllocation) {
                $serviceViseAllocation[$valueAllocation->service_id] = $valueAllocation->allocation_json;
            }

            foreach ($data as $worksheetKey => $worksheetValue) {
                $worksheet['worksheet_master_id'] = $worksheetMasterid;
                $worksheet['master_activity_id'] = $master_activity_id;
                $worksheet['entity_id'] = $value;
                $worksheet['task_id'] = $task_id;
                $worksheet['frequency_id'] = $frequency_id;
                $worksheet['service_id'] = $masterActivity->service_id;
                $worksheet['status_id'] = 0;
                //if ($masterActivity->service_id != 2) {
                $worksheet['start_date'] = $worksheetValue['start_date'];
                $worksheet['end_date'] = $worksheetValue['end_date'];
                $worksheet['due_date'] = $worksheetValue['due_date'];
                $worksheet['critical_task'] = $criticalTask;
                //}
                $worksheet['budgeted_unit'] = $budgetedUnit;
                $worksheet['frequency_id'] = $frequency_id;
                $worksheet['notes'] = $notes;
                $worksheet['worksheet_additional_assignee'] = $request->get('worksheet_additional_assignee');
                $worksheet['team_json'] = isset($serviceViseAllocation[$masterActivity->service_id]) ? $serviceViseAllocation[$masterActivity->service_id] : '';
                $worksheet['reminder_date'] = $request->get('due_on_particular_date');
                $worksheet['created_by'] = app('auth')->guard()->id();
                $worksheet['created_on'] = date('Y-m-d H:i:s');
                $worksheetData[] = $worksheet;
            }
        }
        Worksheet::insert($worksheetData);
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Aug 20, 2018
     * @param array $request
     * @return array
     */
    public function show(Request $request, $id) {
        try {
            $worksheet = Worksheet::select('bb.category_id', 'worksheet.*', 'tm.userfullname AS team_member', 'tam.userfullname AS technical_account_manager', 'tl.userfullname AS team_lead', 'f.frequency_name', 'ws.id as status_id', 'ws.status_name')
                    ->with('masterActivityId:id,name', 'entityId:id,name,billing_name,trading_name,reviewer_budgeted_unit,dynamic_json', 'taskId:id,name')
                    ->leftjoin('billing_basic as bb', 'bb.entity_id', '=', 'worksheet.entity_id')
                    ->leftjoin('worksheet_status as ws', 'ws.id', '=', 'worksheet.status_id')
                    ->leftjoin('frequency as f', 'f.id', '=', 'worksheet.frequency_id')
                    ->leftJoin('entity_allocation as ea', function($query) {
                        $query->on('ea.entity_id', '=', 'worksheet.entity_id');
                        $query->on('ea.service_id', '=', 'worksheet.service_id');
                    })
                    ->leftjoin('user as tm', 'tm.id', '=', app('db')->raw('JSON_EXTRACT(ea.allocation_json, "$.10")'))
                    ->leftjoin('user as tl', 'tl.id', '=', app('db')->raw('JSON_EXTRACT(ea.allocation_json, "$.60")'))
                    ->leftjoin('user as tam', 'tam.id', '=', app('db')->raw('JSON_EXTRACT(ea.allocation_json, "$.9")'))
                    ->find($id);

            if (!$worksheet)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The worksheet does not exist', ['error' => 'The worksheet does not exist']);

            $service_id = $worksheet->service_id;
            $entity_id = $worksheet->entity_id;
            $assigneeStaff = $ticketRaised = array();

            if ($request->has('taskchecklistview')) {
                $todayDate = date("Y-m-d");
                $endDate = date("Y-m-d", strtotime("-6 Months"));
                $ticket = \App\Models\Backend\Ticket::where('entity_id', $entity_id)->where('type_id', 1)->whereIn('team_id', array(1, 2))->whereBetween('created_on', array($endDate, $todayDate))->get()->toArray();
                $bkTicket = $payrollTicket = $problemOurSideBk = $problemOurSidePayroll = 0;
                foreach ($ticket as $key => $value) {
                    if ($value['team_id'] == 1) {
                        $bkTicket = $bkTicket + 1;
                        if ($value['problem_our_side'] == 1)
                            $problemOurSideBk = $problemOurSideBk + 1;
                    }

                    if ($value['team_id'] == 2) {
                        $payrollTicket = $payrollTicket + 1;
                        if ($value['problem_our_side'] == 1)
                            $problemOurSidePayroll = $problemOurSidePayroll + 1;
                    }
                }
                $ticketRaised['bkTicket'] = $bkTicket;
                $ticketRaised['problemOurSideBk'] = $problemOurSideBk;
                $ticketRaised['payrollTicket'] = $payrollTicket;
                $ticketRaised['problemOurSidePayroll'] = $problemOurSidePayroll;

                if ($worksheet->entityId->dynamic_json != '') {
                    $dynamicField = \GuzzleHttp\json_decode($worksheet->entityId->dynamic_json, true);
                    $worksheet->entityId->dynamic_json = $software = isset($dynamicField[2][28]) ? $dynamicField[2][28] : '-';
                }
                /* $processingBy = \App\Models\Backend\WorksheetLog::select('u.userfullname')
                  ->leftjoin('user as u', 'u.id', '=', 'worksheet_status_log.created_by')
                  ->where('worksheet_id', $id)->where('worksheet_status_log.status_id', 2)
                  ->orderBy('worksheet_status_log.created_on', 'desc')->first(); */
                if ($worksheet->worksheet_additional_assignee != 0 && $worksheet->worksheet_additional_assignee != null) {
                    $processingBy = \App\Models\User::find($worksheet->worksheet_additional_assignee);
                    $processingBy = $processingBy->userfullname;
                } else {
                    $processingBy = $worksheet->team_member;
                }
            } else {
                $worksheet->entityId->dynamic_json = '';
            }
            $worksheet->process_by = isset($processingBy) ? $processingBy : '';
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

            if ($request->has('taskchecklistemailpreview') && $request->get('taskchecklistemailpreview') == 1)
                return ['data' => $worksheet, 'assigneeStaff' => $assigneeStaff, 'ticket' => $ticketRaised];

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet detail', ['data' => $worksheet, 'assigneeStaff' => $assigneeStaff, 'ticket' => $ticketRaised]);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet details.', ['error' => 'Could not get worksheet details.']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Aug 20, 2018
     * @param array $request
     * @return array
     */
    public function additionalAssign(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'user_id' => 'required|numeric',
                'is_remove' => 'in:0,1'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $worksheet = Worksheet::with('entityId:id,trading_name')->find($id);
            if (!$worksheet)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The worksheet does not exist', ['error' => 'The worksheet does not exist']);

            if ($request->get('is_remove') == 1) {
                $worksheet->worksheet_additional_assignee = 0;
                $worksheet->modified_by = app('auth')->guard()->id();
                $worksheet->modified_on = date('Y-m-d H:i:s');
                $worksheet->save();
                return createResponse(config('httpResponse.SUCCESS'), 'Worksheet assignee has been successfully removed', ['message' => 'Worksheet assignee has been successfully removed']);
            }

            $user_id = $request->get('user_id');
            if ($request->get('type') == 1) {
                $worksheet->worksheet_additional_assignee = $user_id;
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('WTAA');
            }

            if ($request->get('type') == 2) {
                $worksheet->worksheet_reviewer = $user_id;
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('WTAR');
            }

            if ($request->get('type') == 3) {
                $worksheet->worksheet_peerreviewer = $user_id;
                $emailTemplate = \App\Models\Backend\EmailTemplate::getTemplate('WTAA');
            }

            $worksheet->modified_by = app('auth')->guard()->id();
            $worksheet->modified_on = date('Y-m-d H:i:s');
            $worksheet->save();

            $userDetail = \App\Models\User::userDetail($user_id);
            $assigneeStaff = $userDetail->userfullname;
            $assigneeByStaff = app('auth')->guard()->user()->userfullname;
            $worksheet = $worksheet->toArray();
            $entityName = $worksheet['entity_id']['trading_name'];
            $period = 'Period - ' . dateFormat($worksheet['start_date']) . ' To ' . dateFormat($worksheet['end_date']);
            $link = "http://google.com/" . $id;
            $anchor = "<a href='" . $link . "'><b>here</b></a>";

            $search = array('STAFFNAME', 'CLIENT_NAME', 'PERIOD_TASK', 'UPDATEDBY', 'HERELINK');
            $replace = array($assigneeStaff, $entityName, $period, $assigneeByStaff, $anchor);
            $subject = str_replace($search, $replace, $emailTemplate['subject']);
            $content = str_replace($search, $replace, $emailTemplate['content']);

            $data['to'] = $userDetail->email;
            $data['content'] = $content;
            $data['subject'] = $subject;
            storeMail($request, $data);
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet has been assign successfully', ['message' => 'Worksheet has been assign successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet details.', ['error' => 'Could not get worksheet details.']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Aug 11, 2018
     * @param date $startDate
     * @param date $endDate
     * @param int $frequency
     * @param str $exprtDay
     * @param str $exprtMonth
     * @param int $dueAfterDay
     * @param int $dueMonthDay
     * @param date $dueOnParticulerDate
     * @return Illuminate\Http\JsonResponse
     */
    public static function generateDailyWorksheet($startDate, $endDate, $frequency, $exprtDay, $exprtMonth, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate) {
        $firstDate = strtotime($startDate);
        $lastDate = strtotime($endDate);
        $frequencyList = \App\Models\Backend\Frequency::find($frequency);
        $frequency_day = $frequencyList->days;
        $generatedWorksheet = array();
        $i = $monthnumberOfday = 0;
        $countEnddate = '';
        $temp = true;

        switch ($frequency) {
            case 1:// For Weekly frequency
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);
                    $dayFlag = false;
                    if (isset($exprtDay) && $exprtDay != '') {
                        $dayFlag = true;
                        $startDate = date('Y-m-d', strtotime("next " . $exprtDay, $firstDate));
                        $firstDate = strtotime($startDate);
                    }

                    if ($firstDate <= $lastDate) {

                        $endDate = date('Y-m-d', strtotime("+" . ($frequency_day - 1) . " days", $firstDate));
                        if ($dayFlag != true) {
                            $firstDate = strtotime("+" . ($frequency_day) . " days", $firstDate);
                        }
                        $generatedWorksheet[$i]['frequency'] = $frequencyList->frequency_name;
                        $generatedWorksheet[$i]['start_date'] = $startDate;
                        $generatedWorksheet[$i]['end_date'] = $endDate;
                        $generatedWorksheet[$i]['due_date'] = self::generateDueDate($endDate, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
                    }
                    $i++;
                }
                break;
            case 2:// For Fortnightly
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);
                    if (isset($exprtDay) && $exprtDay != '') {
                        $startDate = date('Y-m-d', strtotime("next " . $exprtDay, $firstDate));
                        $firstDate = strtotime($startDate);
                    }

                    if ($firstDate <= $lastDate) {
                        $endDate = date('Y-m-d', strtotime("+" . ($frequency_day - 1) . " days", $firstDate));
                        $firstDate = strtotime("+" . ($frequency_day) . " days", $firstDate);
                        $generatedWorksheet[$i]['frequency'] = $frequencyList->frequency_name;
                        $generatedWorksheet[$i]['start_date'] = $startDate;
                        $generatedWorksheet[$i]['end_date'] = $endDate;
                        $generatedWorksheet[$i]['due_date'] = self::generateDueDate($endDate, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
                    }
                    $i++;
                }
                break;
            case 3:// For monthly frequency
                $exprtMonthExplode = array();
                if (isset($exprtMonth) && $exprtMonth != '')
                    $exprtMonthExplode = explode(',', $exprtMonth);
                else {
                    $exprtMonthExplode = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
                }

                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);
                    $currentMonth = date("F", $firstDate);

                    if (is_array($exprtMonthExplode) && !empty($exprtMonthExplode) && in_array($currentMonth, $exprtMonthExplode)) {
                        $startDate = date('Y-m-d', strtotime($currentMonth, $firstDate));
                        $firstDate = strtotime($startDate);
                    } else {
                        // $firstDate = strtotime("+" . ($frequency_day) . " days", $firstDate);
                        // goto notmatchmonth;
                    }

                    $monthnumberOfday = date("t", $firstDate);
                    if ($firstDate <= $lastDate) {
                        $endDate = date('Y-m-d', strtotime("+" . ($monthnumberOfday - 1) . " days", $firstDate));
                        $firstDate = strtotime("+" . ($monthnumberOfday) . " days", $firstDate);
                        if (in_array($currentMonth, $exprtMonthExplode)) {
                            $generatedWorksheet[$i]['frequency'] = $frequencyList->frequency_name;
                            $generatedWorksheet[$i]['start_date'] = $startDate;
                            $generatedWorksheet[$i]['end_date'] = $endDate;
                            $generatedWorksheet[$i]['due_date'] = self::generateDueDate($endDate, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
                        }
                    }
                    //notmatchmonth:
                    $i++;
                }
                break;
            case 4:// For quartely frequency
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);
                    if (isset($exprtDay) && $exprtDay != '') {
                        $startDate = date('Y-m-d', strtotime("next " . $exprtDay, $firstDate));
                        $firstDate = strtotime($startDate);
                    }

                    if ($firstDate <= $lastDate) {
                        $countEnddate = strtotime("+ 3 month", $firstDate);
                        $endDate = date('Y-m-d', strtotime("- 1 days", $countEnddate));
                        $firstDate = $countEnddate;
                        $generatedWorksheet[$i]['frequency'] = $frequencyList->frequency_name;
                        $generatedWorksheet[$i]['start_date'] = $startDate;
                        $generatedWorksheet[$i]['end_date'] = $endDate;
                        $generatedWorksheet[$i]['due_date'] = self::generateDueDate($endDate, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
                    }
                    $i++;
                }
                break;
            case 5:// For quartely frequency
                $leapYear = date("L", strtotime($endDate));
                if ($leapYear == 1)
                    $frequency_day = 366;

                while ($firstDate <= $lastDate) {
                    if (isset($exprtDay) && $exprtDay != '') {
                        $startDate = date('Y-m-d', strtotime("next " . $exprtDay, $firstDate));
                        $firstDate = strtotime($startDate);
                    }

                    if ($firstDate <= $lastDate) {
                        $endDate = date('Y-m-d', strtotime("+" . ($frequency_day - 1) . " days", $firstDate));
                        $firstDate = strtotime("+" . $frequency_day . " days", $firstDate);
                        $generatedWorksheet[$i]['frequency'] = $frequencyList->frequency_name;
                        $generatedWorksheet[$i]['start_date'] = $startDate;
                        $generatedWorksheet[$i]['end_date'] = $endDate;
                        $generatedWorksheet[$i]['due_date'] = self::generateDueDate($endDate, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
                    }
                    $i++;
                }
                break;
            case 6:// For half monthly frequency
                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);
                    if (isset($exprtDay) && $exprtDay != '') {
                        $startDate = date('Y-m-d', strtotime("next " . $exprtDay, $firstDate));
                        $firstDate = strtotime($startDate);
                    }

                    if ($firstDate <= $lastDate) {
                        $monthnumberOfday = date("t", $firstDate);
                        if ($monthnumberOfday % 2 == 0) {
                            $startDate = date('Y-m-d', $firstDate);
                            $countEnddate = $TaskEndDate = strtotime("+" . ($monthnumberOfday / 2) . " days", $firstDate);
                            $firstDate = strtotime("+" . ($monthnumberOfday - $monthnumberOfday / 2) . " days", $firstDate);
                        } else {
                            if ($temp) {
                                $startDate = date('Y-m-d', $firstDate);
                                $countEnddate = $TaskEndDate = strtotime("+" . $frequency_day . " days", $firstDate);
                                $firstDate = strtotime("+" . ($monthnumberOfday - $frequency_day) . " days", $firstDate);
                                $temp = false;
                            } else {
                                $startDate = date('Y-m-d', $firstDate - 1);
                                $countEnddate = $TaskEndDate = strtotime("+" . ($monthnumberOfday - $frequency_day - 1) . " days", $firstDate);
                                $firstDate = strtotime("+" . ($monthnumberOfday - $frequency_day - 1) . " days", $firstDate);
                                $temp = true;
                            }
                        }

                        $endDate = date('Y-m-d', strtotime("-1 days", $countEnddate));
                        $generatedWorksheet[$i]['frequency'] = $frequencyList->frequency_name;
                        $generatedWorksheet[$i]['start_date'] = $startDate;
                        $generatedWorksheet[$i]['end_date'] = $endDate;
                        $generatedWorksheet[$i]['due_date'] = self::generateDueDate($endDate, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
                    }
                    $i++;
                }
                break;
            case 10:// For deail frequency
                $exprtDayExplode = array();
                if (isset($exprtDay) && $exprtDay != '')
                    $exprtDayExplode = explode(',', $exprtDay);
                else {
                    $exprtDayExplode = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
                }

                while ($firstDate <= $lastDate) {
                    $startDate = date('Y-m-d', $firstDate);
                    $todayDay = date("l", $firstDate);
                    if (is_array($exprtDayExplode) && !empty($exprtDayExplode) && in_array($todayDay, $exprtDayExplode)) {
                        $startDate = date('Y-m-d', strtotime($todayDay, $firstDate));
                        $firstDate = strtotime($startDate);
                    } else {
//                        $firstDate = strtotime("+" . ($frequency_day) . " days", $firstDate);
//                        goto notmatchday;
                    }

                    if ($firstDate <= $lastDate) {
                        $endDate = date('Y-m-d', strtotime("+" . ($frequency_day - 1) . " days", $firstDate));
                        $firstDate = strtotime("+" . ($frequency_day) . " days", $firstDate);
                        if (in_array($todayDay, $exprtDayExplode)) {
                            $generatedWorksheet[$i]['frequency'] = $frequencyList->frequency_name;
                            $generatedWorksheet[$i]['start_date'] = $startDate;
                            $generatedWorksheet[$i]['end_date'] = $endDate;
                            $generatedWorksheet[$i]['due_date'] = self::generateDueDate($endDate, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
                        }
                    }
                    notmatchday:
                    $i++;
                }
                break;
        }
        return $generatedWorksheet;
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Aug 12, 2018
     * @param date $startDate
     * @param int  $dueAfterDay
     * @param int  $dueMonthDay
     * @param date $dueOnParticulerDate
     */

    public static function generateDueDate($endDate, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate) {
        $due_date = '';
        if (isset($dueAfterDay) && $dueAfterDay == 0 && $dueAfterDay == '' && $dueAfterDay == 0) {
            $endDate = strtotime($endDate);
            $due_date = date('Y-m-d', strtotime("+" . $dueAfterDay . " days", $endDate));
        } else if (isset($dueAfterDay) && $dueAfterDay != 0) {
            $endDate = strtotime($endDate);
            $due_date = date('Y-m-d', strtotime("+" . $dueAfterDay . " days", $endDate));
        } else if (isset($dueMonthDay) && $dueMonthDay != 0) {
            $month = date('m', strtotime($endDate));
            $due_date = date('Y-' . $month . '-' . $dueMonthDay);
        } else if (isset($dueOnParticulerDate) && $dueOnParticulerDate != '') {
            $due_date = $dueOnParticulerDate;
        }
        return $due_date;
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Sept 19, 2018
     * @param  Illuminate\Http\Request  $request
     */

    public function worksheetAdditionalAssignee(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required',
                'master_activity_id' => 'required|numeric'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $masterActivityId = $request->get('master_activity_id');
            $entity_id = $request->get('entity_id');
            $masterActivityService = \App\Models\Backend\MasterActivity::select('service_id', 'user_team_id')->find($masterActivityId);
            if ($masterActivityService->service_id == 0 && $masterActivityService->user_team_id > 0) {
                $teamId = explode(",", $masterActivityService->user_team_id);
                $userTeamList = \App\Models\Backend\UserHierarchy::leftjoin("user as u", "u.id", "user_hierarchy.user_id")
                        ->select('u.userfullname', 'u.id');
                for ($t = 0; $t < count($teamId); $t++) {
                    $userTeamList = $userTeamList->whereRaw("FIND_IN_SET($teamId[$t],team_id)");
                }
                $userTeamList = $userTeamList->where("u.is_active", "1")->get();

                $assigneeList['teamMember'] = $userTeamList;

                //$assigneeList['teamMember'] = \App\Models\User::whereIn('id', [71, 930, 847, 613, 463, 216])->select('user.userfullname', 'user.id')->get();
            } else {
                $assigneeList = array();
                $masterActivity = \App\Models\Backend\MasterActivity::select('service_id')->find($masterActivityId);
                $serviceId = $masterActivity->service_id;
                $entityData = \App\Models\Backend\EntityAllocation::select(app('db')->raw("REPLACE(CONCAT(json_Extract(allocation_json, '$.9'),',', json_Extract(allocation_json, '$.61'), ',', json_Extract(allocation_json, '$.10'),',',json_Extract(allocation_json, '$.60')), ',,',',') AS assignUser"), 'other')
                                ->leftjoin('entity_allocation_other AS eao', function($query) use($entity_id) {
                                    $query->where('eao.entity_id', '=', $entity_id);
                                })
                                ->where('entity_allocation.entity_id', $entity_id)
                                ->where('entity_allocation.service_id', $serviceId)->get();

                if (count($entityData) > 0) {
                    $assigneeID = explode(',', $entityData[0]->assignUser);
                    $otherId = explode(',', $entityData[0]->other);
                    $allUserId = array_unique($assigneeID + $otherId);
                    if (!empty($assigneeID)) {
                        $teamMember = \App\Models\User::leftjoin('user_hierarchy AS uh', 'uh.user_id', '=', 'user.id')
                                        ->whereIn('user.id', $allUserId)
                                        ->whereIn('uh.designation_id', array(9, 10, 60, 61))->select('user.userfullname', 'user.id')->get()->toArray();
                        if (!empty($teamMember))
                            foreach ($teamMember as $keyActual => $valueActual) {
                                $assigneeList['teamMember'][] = $valueActual;
                            }
                    }

                    $otherMember = \App\Models\Backend\UserHierarchy::select('u.id', 'userfullname')->whereRaw('FIND_IN_SET(' . $serviceId . ', team_id)')->where('designation_id', 10)->whereNotIn('user_id', $allUserId)->leftJoin('user AS u', 'u.id', '=', 'user_id')->orderBy('userfullname', 'asc')->get()->toArray();

                    if (!empty($otherMember))
                        foreach ($otherMember as $keyOther => $valueOther)
                            $assigneeList['otherMember'][] = $valueOther;
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet additional assign load successfully', ['data' => $assigneeList]);
        } catch (Exception $ex) {
            app('log')->error("Worksheet additional assign load failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet additionalu assignee details.', ['error' => 'Could not get worksheet additionalu assignee details.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Sept 19, 2018
     * @param  Illuminate\Http\Request  $request
     */

    public function worksheetReviewerAssignee(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'master_activity_id' => 'required|numeric'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $masterActivityId = $request->get('master_activity_id');
            $entity_id = $request->get('entity_id');
            $masterActivity = \App\Models\Backend\MasterActivity::select('service_id', 'user_team_id')->find($masterActivityId);
            $serviceId = $masterActivity->service_id;
            $assigneeList = array();

            // Account team only
            $masterActivityService = \App\Models\Backend\MasterActivity::select('service_id', 'user_team_id')->find($masterActivityId);
            if ($masterActivityService->service_id == 0 && $masterActivityService->user_team_id > 0) {
                $teamId = explode(",", $masterActivityService->user_team_id);

                $userTeamList = \App\Models\Backend\UserHierarchy::leftjoin("user as u", "u.id", "user_hierarchy.user_id")
                        ->select('u.userfullname', 'u.id');
                for ($t = 0; $t < count($teamId); $t++) {
                    $userTeamList = $userTeamList->whereRaw("FIND_IN_SET($teamId[$t],team_id)");
                }
                $reviewerList = $userTeamList->where("u.is_active", "1")->get();

                //$assigneeList['teamMember'] = $userTeamList;
                //$assigneeList['teamMember'] = \App\Models\User::whereIn('id', [71, 930, 847, 613, 463, 216])->select('user.userfullname', 'user.id')->get();
            } else {
                if ($serviceId != 0) {

                    $entityData = \App\Models\Backend\EntityAllocation::select('allocation_json', 'other')
                                    ->leftjoin('entity_allocation_other AS eao', function($query) use($entity_id) {
                                        $query->where('eao.entity_id', '=', $entity_id);
                                    })
                                    ->where('entity_allocation.entity_id', $entity_id)
                                    ->where('entity_allocation.service_id', $serviceId)->get();

                    $actualAssignee = array_filter(json_decode($entityData[0]->allocation_json, true));
                    $otherAssignee = explode(',', $entityData[0]->other);
                    $allAssignee = array_unique($actualAssignee + $otherAssignee);
                    if ($masterActivityId == 8) {
                        $reviewerList = \App\Models\User::leftjoin('user_hierarchy AS uh', 'uh.user_id', '=', 'user.id')
                                        ->whereIn('user.id', $allAssignee)
                                        ->whereIn('uh.designation_id', [11, 40, 42, 52, 54, 56, 58, 64, 65, 66, 67, 72, 9, 10, 60, 61])->select('user.userfullname', 'user.id')->get();
                    } else {
                        if (!empty($allAssignee)) {
                            $reviewerList = \App\Models\User::leftjoin('user_hierarchy AS uh', 'uh.user_id', '=', 'user.id')
                                            ->whereIn('user.id', $allAssignee)
                                            ->whereRaw('FIND_IN_SET(' . $serviceId . ', uh.team_id)')->select('user.userfullname', 'user.id')->get();
                        }
                    }
                } else {
                    if (isset($masterActivity->user_team_id) && $masterActivity->user_team_id != '') {
                        $teamArray = explode(',', $masterActivity->user_team_id);
                        $findInSet = array();
                        foreach ($teamArray as $key) {
                            $findInSet[] = " FIND_IN_SET(" . $key . ", uh.team_id)";
                        }
                        $findInSetOr = implode(' OR ', $findInSet);

                        $reviewerList = \App\Models\User::leftjoin('user_hierarchy AS uh', 'uh.user_id', '=', 'user.id')
                                        ->whereNotIn('uh.designation_id', [1, 7])
                                        ->whereRaw('(' . $findInSetOr . ')')->select('user.userfullname', 'user.id')->get();
                    }
                }
            }

            foreach ($reviewerList as $keyActual => $valueActual)
                $assigneeList[] = $valueActual;

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet reviewer load successfully', ['data' => $assigneeList]);
        } catch (Exception $ex) {
            app('log')->error("Worksheet additional assign load failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet reviewer details.', ['error' => 'Could not get worksheet reviewer details.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Sept 19, 2018
     * @param  Illuminate\Http\Request  $request
     */

    public function worksheetPeerAssignee(Request $request) {
        try {
            $assigneeList = array();
            $peerreviewerList = \App\Models\User::leftjoin('user_hierarchy AS uh', 'uh.user_id', '=', 'user.id')
                            ->whereIn('uh.designation_id', [63, 62])->select('user.userfullname', 'user.id')->get();

            foreach ($peerreviewerList as $keyActual => $valueActual)
                $assigneeList[] = $valueActual;

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet peer reviewer load successfully', ['data' => $assigneeList]);
        } catch (Exception $ex) {
            app('log')->error("Worksheet peer reviewer assign load failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet reviewer details.', ['error' => 'Could not get worksheet reviewer details.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Sept 20, 2018
     */

    public function status() {
        try {
            $user = getLoginUserHierarchy();
            $worksheetStatus = \App\Models\Backend\WorksheetStatus::where('is_active', 1)->select('status_name', 'id')->get();
            $allowStatus = array();
            if ($user->designation_id != 7)
                $allowStatus = \App\Models\Backend\WorksheetStatus::
                                leftjoin('worksheet_status_user_right AS wsur', 'wsur.worksheet_status_id', '=', 'worksheet_status.id')
                                ->where('wsur.user_id', app('auth')->guard()->id())->where('wsur.right', 1)->pluck('worksheet_status.status_name', 'worksheet_status.id')->toArray();

            $statusArray = array();
            if (!empty($worksheetStatus)) {
                foreach ($worksheetStatus as $key => $value) {
                    if (isset($allowStatus[$value->id]) && $allowStatus[$value->id] != '')
                        $value['is_right'] = 1;
                    else if (empty($allowStatus))
                        $value['is_right'] = 1;
                    else
                        $value['is_right'] = 0;

                    $statusArray[] = $value;
                }
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet status load successfully', ['data' => $statusArray]);
        } catch (Exception $ex) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet details.', ['error' => 'Could not get worksheet details.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Aug 11, 2018
     * @param  Illuminate\Http\Request  $request
     */

    public function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required',
            'master_activity_id' => 'required|numeric',
            'task_id' => 'required|numeric',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d',
            'frequency_id' => 'required|numeric',
            'due_date_period' => 'required|numeric',
            'due_after_day' => 'required_if:due_date_period,1',
            'due_month_day' => 'required_if:due_date_period,2',
            'due_on_particular_date' => 'required_if:due_date_period,3',
            'search' => 'json'
                ], ['entity_id.required' => 'The client name field is required.',
            'entity_id.numeric' => 'The client name field is numeric.',
            'master_activity_id.required' => 'The master activity field is required.',
            'master_activity_id.numeric' => 'The master activity field is numeric.',
            'task_id.required' => 'The task field is required.',
            'task_id.numeric' => 'The task field is numeric.',
            'frequency_id.required' => 'The frequency field is required.',
            'frequency_id.numeric' => 'The frequency field is numeric.',
            'due_date_period.required' => 'The due date field is required.',
            'due_date_period.numeric' => 'The due date field is numeric.',
            'due_after_day.required_if' => 'When due date "After days" then days should be required',
            'due_month_day.required_if' => 'When due date "On Date of month" then days should be required',
            'due_on_particular_date.required_if' => 'When due date "On Particular Date" then date should be required']);

        return $validator;
    }

    public function worksheetStatusCounter(Request $request) {
        //try {
        $worksheet = Worksheet::leftjoin('frequency as f', 'f.id', '=', 'worksheet.frequency_id')
                ->leftjoin('entity as e', 'e.id', '=', 'worksheet.entity_id')
                ->leftjoin('master_activity as ma', 'ma.id', '=', 'worksheet.master_activity_id')
                ->leftjoin("billing_basic as bs", "bs.entity_id", "e.id");

        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('category_id' => 'bs', 'related_entity' => 'e');
            $worksheet = search($worksheet, $search);
        }

        $user_id = app('auth')->guard()->id();

        if ($request->get('type') == 'my') {
            $worksheet = $worksheet->where("status_id", "!=", "4");
            $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_SEARCH(allocation_json, 'all', " . $user_id . ")) IS NOT NULL OR worksheet.worksheet_additional_assignee = " . $additional_assignee . " OR worksheet.worksheet_reviewer = " . $additional_assignee . " OR worksheet.worksheet_peerreviewer = " . $additional_assignee . ")");

            if ($request->get('type') == 'my') {
                if (in_array($designation->designation_id, array(7, 15))) {
                    $worksheet = array();
                    goto end;
                }
            }
        }

        if ($request->get('type') == 'incompleted') {
            $worksheet = $worksheet->where("status_id", "!=", "4");
            $condition = 'OR';
            if ($additional_assignee != $user_id)
                $condition = 'AND';
            if (!in_array($designation->designation_id, array(7, 15))) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_SEARCH(allocation_json, 'all', " . $user_id . ")) IS NOT NULL " . $condition . " worksheet.worksheet_additional_assignee = " . $additional_assignee . " OR worksheet.worksheet_reviewer = " . $additional_assignee . " OR worksheet.worksheet_peerreviewer = " . $additional_assignee . ")");
            }
        }

        if ($request->get('type') == 'completed') {
            $worksheet = $worksheet->where("status_id", "=", "4");
            if (!in_array($designation->designation_id, array(7, 15))) {
                $worksheet = $worksheet->whereRaw("(JSON_UNQUOTE(JSON_SEARCH(allocation_json, 'all', " . $user_id . ")) IS NOT NULL OR worksheet.worksheet_additional_assignee = " . $additional_assignee . " OR worksheet.worksheet_reviewer = " . $additional_assignee . " OR worksheet.worksheet_peerreviewer = " . $additional_assignee . ")");
            }
        }

        if ($request->get('type') == 'befree') {
            $worksheet = $worksheet->where("bs.entity_grouptype_id", "17")->where("status_id", "!=", "4");
            if (!in_array($designation->designation_id, array(7, 15))) {
                $worksheet = $worksheet->whereRaw("JSON_UNQUOTE(JSON_SEARCH(allocation_json, 'all', " . $user_id . ")) IS NOT NULL");
            }
        }

        $worksheet = $worksheet->select('ws.id', 'ws.status_name', app('db')->raw('COUNT(status_id) AS count'))->leftjoin('worksheet_status As ws', 'ws.id', '=', 'status_id')->groupBy('status_id')->get();
        end:
        return createResponse(config('httpResponse.SUCCESS'), "Worksheet status count list.", ['data' => $worksheet]);
//        } catch (\Exception $e) {
//            app('log')->error("Worksheet listing failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Worksheet", ['error' => 'Server error.']);
//        }
    }

    public function worksheetRepeattask($id) {
        try {
            $getWorksheet = Worksheet::with('frequencyId:id,frequency_name')->find($id);
            $frequency = $getWorksheet->frequency_id;
            $newStartDate = date('Y-m-d', strtotime($getWorksheet->end_date . ' +1 day'));
            $strtotimeStartDate = strtotime($newStartDate);

            switch ($frequency) {
                case 1:// For Weekly
                    $newEnddate = date('Y-m-d', strtotime($newStartDate . ' +7 days'));
                    break;
                case 2:// For Fortnightly
                    $newEnddate = date('Y-m-d', strtotime($newStartDate . ' +14 days'));
                    break;
                case 3:// For Monthly
                    $monthnumberOfday = date("t", $strtotimeStartDate) - 1;
                    $newEnddate = date('Y-m-d', strtotime($newStartDate . ' +' . $monthnumberOfday . ' days'));
                    break;
                case 4:// For Quartely
                    $newEnddate = date('Y-m-d', strtotime($newStartDate . ' +90 days'));
                    break;
                case 5:// For Yearly
                    $newEnddate = date('Y-m-d', strtotime($newStartDate . ' +365 days'));
                    break;
                case 6:// For Half Monthly
                    $newEnddate = date('Y-m-d', strtotime($newStartDate . ' +15 days'));
                    break;
                case 10:// For Daily frequency
                    $newEnddate = date('Y-m-d', strtotime($newStartDate . ' +0 days'));
                    break;
            }

            $currentDueDateDay = (strtotime($getWorksheet->due_date) - strtotime($getWorksheet->end_date)) / 86400;
            $newDueDate = date('Y-m-d', strtotime($newEnddate . '+' . $currentDueDateDay . ' days'));


            $getWorksheet->new_worksheet_master_id = $getWorksheet->worksheet_master_id;
            $getWorksheet->new_master_activity_id = $getWorksheet->master_activity_id;
            $getWorksheet->new_task_id = $getWorksheet->task_id;
            $getWorksheet->new_frequency_id = $getWorksheet->frequency_id;
            $getWorksheet->new_start_date = $newStartDate;
            $getWorksheet->new_end_date = $newEnddate;
            $getWorksheet->new_due_date = $newDueDate;

            return createResponse(config('httpResponse.SUCCESS'), 'Repeat worksheet task load successfully', ['data' => $getWorksheet]);
        } catch (Exception $ex) {
            app('log')->error("Worksheet repeat task get failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet repeat task details.', ['error' => 'Could not get worksheet repeat task  details.']);
        }
    }

    public function worksheetRepeatTaskAdd(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'worksheet_master_id' => 'required|numeric',
                'master_activity_id' => 'required|numeric',
                'entity_id' => 'required',
                'task_id' => 'required|numeric',
                'frequency_id' => 'required|numeric',
                'service_id' => 'required|numeric',
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d',
                'due_date' => 'required|date_format:Y-m-d'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $worksheet['worksheet_master_id'] = $request->get('worksheet_master_id');
            $worksheet['master_activity_id'] = $request->get('master_activity_id');
            $worksheet['entity_id'] = $request->get('entity_id');
            $worksheet['task_id'] = $request->get('task_id');
            $worksheet['frequency_id'] = $request->get('frequency_id');
            $worksheet['service_id'] = $request->get('service_id');
            $worksheet['status_id'] = 0;
            $worksheet['start_date'] = $request->get('start_date');
            $worksheet['end_date'] = $request->get('end_date');
            $worksheet['due_date'] = $request->get('due_date');
            $worksheet['notes'] = $request->get('notes');
            $worksheet['created_by'] = app('auth')->guard()->id();
            $worksheet['created_on'] = date('Y-m-d H:i:s');
            $worksheetData[] = $worksheet;

            Worksheet::insert($worksheetData);
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet repeat task has been added successfully', ['message' => 'Worksheet repeat task has been added successfully']);
        } catch (Exception $ex) {
            app('log')->error("Worksheet add repeat task failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet repeat task details.', ['error' => 'Could not get worksheet repeat task  details.']);
        }
    }

    public static function worksheetCount() {
        try {
            $currentUser = 747; //app('auth')->guard()->id();
            $userList = \App\Models\User::leftjoin("user_hierarchy as uh", "uh.user_id", "user.id")
                            ->select("user.id", "uh.designation_id", "user.userfullname")
                            ->whereRaw("user.first_approval_user = $currentUser OR user.second_approval_user = $currentUser")
                            ->where("user.is_active", "1")->get();

            $todayDate = date('Y-m-d');
            $worksheetCount = array();
            foreach ($userList as $u) {
                $userId = $u->id;
                $Wcon = Worksheet::where("status_id", "!=", "4")->where("status_id", "!=", "13")
                                ->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(team_json, '$." . $u->designation_id . "')) = $userId AND worksheet_additional_assignee = $userId) OR (worksheet_reviewer = $userId OR worksheet_peerreviewer = $userId)")
                                ->where("due_date", $todayDate)->count();
                $worksheetCount[$u->userfullname] = $Wcon;
            }
            //ShowArray($worksheetCount);
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet count data', ['data' => $worksheetCount]);
        } catch (Exception $ex) {
            app('log')->error("Worksheet add repeat task failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get worksheet repeat task details.', ['error' => 'Could not get worksheet repeat task  details.']);
        }
    }

}

?>