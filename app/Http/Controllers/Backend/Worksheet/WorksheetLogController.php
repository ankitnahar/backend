<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\WorksheetLog;

class WorksheetLogController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: Aug 20, 2018
     * Purpose   : Worksheet log listing api
     */

    public function index(Request $request, $id) {
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'worksheet_status_log.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $worksheetLog = WorksheetLog::with('createdBy:id,userfullname')
                    ->with('statusId:id,status_name')
                    ->leftjoin('worksheet AS w', 'w.id', '=', 'worksheet_id')
                    ->leftjoin('master_activity AS ma', 'ma.id', '=', 'w.master_activity_id')
                    ->leftjoin('task AS t', 't.id', '=', 'w.task_id')
                    ->where('worksheet_id', $id);

            if ($request->has('search')) {
                $search = $request->get('search');
                $worksheetLog = search($worksheetLog, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $worksheetLog = $worksheetLog->orderBy($sortBy, $sortOrder)->get(['ma.name as masteractivity_name', 't.name as task_name', 'worksheet_status_log.*']);
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $worksheetLog->count();

                $worksheetLog = $worksheetLog->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $worksheetLog = $worksheetLog->get(['ma.name as masteractivity_name', 't.name as task_name', 'worksheet_status_log.*']);

                $filteredRecords = count($worksheetLog);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Worksheet log.", ['data' => $worksheetLog], $pager);
        } catch (\Exception $e) {
            app('log')->error("Client listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while worksheet log listing", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Aug 20, 2018
     * Purpose   : Store worksheet status log
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store holiday
            $worksheetLog = MasterChecklist::create(['worksheet_id' => $request->get('worksheet_id'),
                        'status_id' => $request->get('status_id'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet status log has been added successfully', ['data' => $worksheetLog]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet master checklist creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add worksheet status log.', ['error' => 'Could not add worksheet status log.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Fetch master checklist details
     */

    public function show(Request $request, $id) {
        try {
            $worksheetLog = MasterChecklist::with('master_activity_id:id,name')->with('task_id:id,name')->with('created_by:id,userfullname')->find($id);

            if (!isset($worksheetLog))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist does not exist', ['error' => 'The master checklist does not exist']);

            // set master checklist detail
            return createResponse(config('httpResponse.SUCCESS'), 'The master checklist data', ['data' => $worksheetLog]);
        } catch (\Exception $e) {
            app('log')->error("Master checklist details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not master checklist details fetch.', ['error' => 'Could not master checklist details fetch']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Update master checklist details
     */

    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'master_activity_id' => 'numeric',
                'task_id' => 'numeric',
                'is_active' => 'in:1,0'], ['name.required' => 'The checklist name field is required.',
                'master_activity_id.numeric' => 'The master activity field must be numeric.',
                'task_id.numeric' => 'The task field must be numeric.',
                'is_active.in' => 'The selected status is invali.']);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $worksheetLog = MasterChecklist::find($id);

            if (!$worksheetLog)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist does not exist', ['error' => 'The master checklist does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['name', 'master_activity_id', 'task_id', 'is_active'], $request);
            $worksheetLog->modified_by = app('auth')->guard()->id();
            $worksheetLog->modified_on = date('Y-m-d H:i:s');
            //update the details
            $worksheetLog->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Master checklist has been updated successfully', ['message' => 'Master checklist has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update master checklist details.', ['error' => 'Could not update master checklist details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate worksheet master checklist input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'name' => 'required',
            'master_activity_id' => 'required|numeric',
            'task_id' => 'required|numeric',
            'is_active' => 'required|in:1,0'], ['name.required' => 'The checklist name field is required.',
            'master_activity_id.required' => 'The master activity field is required.',
            'master_activity_id.numeric' => 'The master activity field must be numeric.',
            'task_id.required' => 'The task field is required.',
            'task_id.numeric' => 'The task field must be numeric.',
            'is_active.required' => 'The status field is required.',
            'is_active.in' => 'The selected status is invali.']);
        return $validator;
    }

}
