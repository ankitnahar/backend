<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Worksheet;

class PeerReviewWorksheetController extends Controller {

    /**
     * Get Review knock back worksheet detail
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        try {
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
            $user_id = app('auth')->guard()->id();
            $pager = [];

            $worksheet = Worksheet::getWorksheet();
            $worksheet = $worksheet->select("e.name", "e.billing_name", "e.trading_name", "e.discontinue_stage", "f.frequency_name", "ea.id as allocation", "bs.category_id", "worksheet.*", "bs.entity_grouptype_id");
            $worksheet = $worksheet->with('worksheetPeerreviewer:userfullname as created_by,id');
            $worksheet = $worksheet->whereIn('status_id', array(22, 23));
            $designation = \App\Models\Backend\UserHierarchy::where('user_id', $user_id)->get();
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array('category_id' => 'bs', 'related_entity' => 'e');
                $worksheet = search($worksheet, $search);
            }

            if ($request->has('technical_head'))
                $user_id = $request->get('technical_head');

            if ($designation[0]->designation_id != 7 && $designation[0]->user_id != 39 && $designation[0]->user_id != 48) {
                $worksheet = $worksheet->where("worksheet.worksheet_peerreviewer", $user_id);
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

            $worksheet = Worksheet::worksheetArrangeData($worksheet);

            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {
                //format data in array 
                $data = $worksheet->toArray();
                app('excel')->create('Report', function($excel) use($data) {
                    $excel->sheet('Sheet 1', function($sheet) use($data) {

                        $sheet->row(1, array('Client Name', 'Task', 'Category', 'Frequency', 'Start Date', 'End Date', 'Due Date', 'Notes', 'Status', 'Technical Account Manager', 'Units', 'Client status'));
                        $sheet->cell('A1:M1', function($cell) {
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

                            $reviewer = isset($data['worksheet_reviewer']['userfullname']) ? $data['worksheet_reviewer']['userfullname'] : 'Not specified';
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
                                $cleans['task_id']['name'],
                                isset($category[$cleans['category_id']]) ? $category[$cleans['category_id']] : '-',
                                $cleans['frequency_name'],
                                dateFormat($cleans['start_date']),
                                dateFormat($cleans['end_date']),
                                dateFormat($cleans['due_date']),
                                $cleans['notes'],
                                $cleans['status_id']['status_name'],
                                $tam,
                                $cleans['timesheet_total_unit'],
                                $discontinueStage));
                            $i++;
                        }
                    });
                })->export('xlsx',['Access-Control-Allow-Origin'=>'*']);
            }

            return createResponse(config('httpResponse.SUCCESS'), "Peer review worksheet list.", ['data' => $worksheet], $pager);
        } catch (\Exception $e) {
            app('log')->error("Worksheet listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Worksheet", ['error' => 'Server error.']);
        }
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

}

?>