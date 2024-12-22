<?php

namespace App\Http\Controllers\Backend\Worksheet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WorksheetScheduleController extends Controller {

    public function index(Request $request, $id) {
        //try{
        $validator = app('validator')->make($request->all(), [
            'sortOrder' => 'in:asc,desc',
            'pageNumber' => 'numeric|min:1',
            'recordsPerPage' => 'numeric|min:0',
            'search' => 'json'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'worksheet_schedule.id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $worsheetSchedule = \App\Models\Backend\WorksheetSchedule::leftjoin("master_activity as m", "m.id", "worksheet_schedule.master_activity_id")
                ->leftjoin("task as t", "t.id", "worksheet_schedule.task_id")
                ->leftjoin("frequency as f", "f.id", "worksheet_schedule.frequency_id")
                ->leftjoin("entity as e", "e.id", "worksheet_schedule.entity_id")
                ->leftjoin("entity as ep", "ep.id", "e.parent_id")
                ->select("e.code","e.trading_name","f.frequency_name","m.name","t.name as task_name","worksheet_schedule.*")
                ->where("worksheet_schedule.entity_id",$id);
        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array("parent_id" => 'e','master_activity_id' => 'worksheet_schedule');
            $worsheetSchedule = search($worsheetSchedule, $search, $alias);
        }
        // for relation ship sorting
        if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
            $worsheetSchedule = $worsheetSchedule->leftjoin("user as u", "u.id", "worksheet_schedule.$sortBy");
            $sortBy = 'userfullname';
        }        
        //echo getSQL($worsheetSchedule);exit;
        // Check if all records are requested
        if ($request->has('records') && $request->get('records') == 'all') {
            $worsheetSchedule = $worsheetSchedule->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $worsheetSchedule->get()->count();

            $worsheetSchedule = $worsheetSchedule->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $worsheetSchedule = $worsheetSchedule->get();

            $filteredRecords = count($worsheetSchedule);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }

        return createResponse(config('httpResponse.SUCCESS'), ' detail', ['data' => $worsheetSchedule], $pager);
        //}   
        //catch (\Exception $e) {
        /* app('log')->error("list creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add list', ['error' => 'Could not add list']);
          } */
    }

    public function store(Request $request, $id) {
        //  try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'worksheet_sechdule' => 'required'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $Worksheetschedule = \GuzzleHttp\json_decode($request->input('worksheet_sechdule'), true);
        foreach ($Worksheetschedule as $row1) {
            $task = $row1['sectionData'];
            foreach ($task as $row) {
                if ($row['start_date'] == '0000-00-00' || $row['start_date'] == null) {
                    if ($row['id'] > 0) {
                        $worksheetSechdule = \App\Models\Backend\WorksheetSchedule::where("id", $row['id'])->delete();
                    }
                    continue;
                }

                $epday = '';
                if (isset($row['expert_day']) && !empty($row['expert_day']) && $row['expert_day'] != '' && $row['expert_day'] != null)
                    $epday = $row['expert_day'];

                $epmonth = '';
                if (isset($row['expert_month']) && !empty($row['expert_month']) && $row['expert_month'] != '' && $row['expert_month'] != null)
                    $epmonth = $row['expert_month'];

                $queryData['id'] = !empty($row['id']) ? $row['id'] : 0;
                $queryData['master_activity_id'] = !empty($row['master_activity_id']) ? $row['master_activity_id'] : 0;
                $queryData['entity_id'] = $id;
                $queryData['task_id'] = !empty($row['task_id']) ? $row['task_id'] : 0;
                $queryData['start_date'] = !empty($row['start_date']) ? $row['start_date'] : '';
                $queryData['end_date'] = !empty($row['end_date']) ? $row['end_date'] : '';
                $queryData['frequency_id'] = !empty($row['frequency_id']) ? $row['frequency_id'] : 0;
                $queryData['expert_day'] = $epday;
                $queryData['expert_month'] = $epmonth;
                $queryData['due_date_type'] = $row['due_date_type'];
                $queryData['due_after_day'] = !empty($row['due_after_day']) ? $row['due_after_day'] : 0;
                $queryData['due_month_day'] = !empty($row['due_month_day']) ? $row['due_month_day'] : 0;
                $queryData['due_on_particular_date'] = !empty($row['due_on_particular_date']) ? $row['due_on_particular_date'] : 0;
                $queryData['notes'] = $row['notes'];
                $queryData['created_by'] = !empty($row['created_by']) ? $row['created_by'] : 0;
                $queryData['created_on'] = !empty($row['created_on']) ? $row['created_on'] : 0;
                $queryData['is_display_schedule'] = !empty($row['is_display_schedule']) ? $row['is_display_schedule'] : 0;
                //$queryData['is_display_schedule'] = !empty($row['is_display_schedule']) ? $row['is_display_schedule'] : 0;                   
                $worksheetSechdule = \App\Models\Backend\WorksheetSchedule::where("id", $row['id'])->count();
                if ($row['generate_now'] == 1) {
                    /* $worksheet = \App\Models\Backend\Worksheet::where("entity_id", $id)
                      ->where("master_activity_id", $row['master_activity_id'])
                      ->where("task_id", $row['task_id'])->where("status_id", 0);
                      if ($worksheet->count() > 0)
                      foreach ($worksheet->get() as $w) {
                      \App\Models\Backend\WorksheetMaster::where("id", $w->worksheet_master_id)->delete();
                      $w->delete();
                      } */

                    $startDate = $queryData['start_date'];
                    $endDate = $queryData['end_date'];
                    $exprtDay = $queryData['expert_day'];
                    $exprtMonth = $queryData['expert_month'];
                    $dueAfterDay = $queryData['due_after_day'];
                    $dueMonthDay = $queryData['due_month_day'];
                    $dueOnParticulerDate = $queryData['due_on_particular_date'];
                    $frequency = $queryData['frequency_id'];

                    $worksheetData = array();
                    $worksheetData = WorksheetController::generateDailyWorksheet($startDate, $endDate, $frequency, $exprtDay, $exprtMonth, $dueAfterDay, $dueMonthDay, $dueOnParticulerDate);
                    self::addWorksheet($id, $row, $worksheetData);
                }
                if ($worksheetSechdule == 0) {
                    \App\Models\Backend\WorksheetSchedule::insert($queryData);
                } else {
                    \App\Models\Backend\WorksheetSchedule::where("id", $row['id'])->update($queryData);
                }
            }
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Worksheet Sechdule has been updated successfully', ['message' => 'Worksheet Sechdule has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Worksheet Sechdule updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update software details.', ['error' => 'Could not update software details.']);
          } */
    }

    public function destroy($id) {
        try {
            $worksheetSchedule = \App\Models\Backend\WorksheetSchedule::find($id);
            if (!$worksheetSchedule)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Worksheet Schedule does not exist', ['error' => 'The Worksheet Schedule does not exist']);

            \App\Models\Backend\WorksheetSchedule::where("id", $id)->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet Schedule has been deleted successfully', ['message' => 'Worksheet Schedule has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Worksheet Schedule download failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete Worksheet Schedule.', ['error' => 'Could not delete Worksheet Schedule.']);
        }
    }

    public static function addWorksheet($entityId, $row, $data) {
        $master_activity_id = $row['master_activity_id'];
        $task_id = $row['task_id'];
        $frequency_id = $row['frequency_id'];
        $notes = $row['notes'];
        $budgetedUnit = 0;
        $taskData = \App\Models\Backend\TaskActivity::select('ask_repeat_task')->find($task_id);
        $masterActivity = \App\Models\Backend\MasterActivity::select('service_id')->find($master_activity_id);
        $worksheet = $worksheetData = array();
        $worksheetMaster = new \App\Models\Backend\WorksheetMaster;
        $worksheetMaster->master_activity_id = $master_activity_id;
        $worksheetMaster->entity_id = $entityId;
        $worksheetMaster->task_id = $task_id;
        $worksheetMaster->is_repeat_task = $taskData->ask_repeat_task;
        $worksheetMaster->start_date = $row['start_date'];
        $worksheetMaster->end_date = $row['end_date'];
        $worksheetMaster->frequency_id = $frequency_id;
        if (isset($row['expert_day']) && !empty($row['expert_day']) && $row['expert_day'] != '' && $row['expert_day'] != null)
            $worksheetMaster->expert_day = $row['expert_day'];

        if (isset($row['expert_month']) && !empty($row['expert_month']) && $row['expert_month'] != '' && $row['expert_month'] != null)
            $worksheetMaster->expert_month = $row['expert_month'];

        if (isset($row['due_after_day']))
            $worksheetMaster->due_after_day = $row['due_after_day'];

        if (isset($row['due_month_day']))
            $worksheetMaster->due_month_day = $row['due_month_day'];

        if (isset($row['due_on_particular_date']))
            $worksheetMaster->due_on_particular_date = $row['due_on_particular_date'];

        $worksheetMaster->created_by = 1;
        $worksheetMaster->created_on = date('Y-m-d H:i:s');
        $worksheetMaster->save();
        $worksheetMasterid = $worksheetMaster->id;

        $entityAllocation = \App\Models\Backend\EntityAllocation::select('allocation_json', 'service_id')->where('entity_id', $entityId)->get();
        $serviceViseAllocation = array();
        foreach ($entityAllocation as $keyAllocation => $valueAllocation) {
            $serviceViseAllocation[$valueAllocation->service_id] = $valueAllocation->allocation_json;
        }
        $worksheetArray = array();
        foreach ($data as $worksheetKey => $worksheetValue) {
            $worksheet['worksheet_master_id'] = $worksheetMasterid;
            $worksheet['master_activity_id'] = $master_activity_id;
            $worksheet['entity_id'] = $entityId;
            $worksheet['task_id'] = $task_id;
            $worksheet['frequency_id'] = $frequency_id;
            $worksheet['service_id'] = $masterActivity->service_id;
            $worksheet['status_id'] = 0;
            //if ($masterActivity->service_id != 2) {
            $worksheet['start_date'] = $worksheetValue['start_date'];
            $worksheet['end_date'] = $worksheetValue['end_date'];
            $worksheet['due_date'] = $worksheetValue['due_date'];
            //}
            $worksheet['budgeted_unit'] = $budgetedUnit;
            $worksheet['frequency_id'] = $frequency_id;
            $worksheet['notes'] = $notes;
            $worksheet['team_json'] = isset($serviceViseAllocation[$masterActivity->service_id]) ? $serviceViseAllocation[$masterActivity->service_id] : '';
            $worksheet['reminder_date'] = $row['due_on_particular_date'];
            $worksheet['created_by'] = app('auth')->guard()->id();
            $worksheet['created_on'] = date('Y-m-d H:i:s');
            \App\Models\Backend\Worksheet::insert($worksheet);
        }
    }

}
