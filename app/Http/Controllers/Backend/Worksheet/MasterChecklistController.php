<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\MasterChecklist;

class MasterChecklistController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Worksheet master checklist listing api
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $masterChecklist = MasterChecklist::with('master_activity_id:id,name')->with('task_id:id,name')->with('created_by:id,userfullname');

            if ($sortBy == 'created_by') {
                $masterChecklist = $masterChecklist->leftjoin("user as u", "u.id", "master_checklist.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($sortBy == 'master_activity_id') {
                $masterChecklist = $masterChecklist->leftjoin("master_activity as ma", "ma.id", "master_checklist.$sortBy");
                $sortBy = 'ma.name';
            }

            if ($sortBy == 'task_id') {
                $masterChecklist = $masterChecklist->leftjoin("task as t", "t.id", "master_checklist.$sortBy");
                $sortBy = 't.name';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array('is_active' => 'master_checklist');
                $masterChecklist = search($masterChecklist, $search, $alias);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $masterChecklist = $masterChecklist->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $masterChecklist->count();

                $masterChecklist = $masterChecklist->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $masterChecklist = $masterChecklist->get(['master_checklist.*']);

                $filteredRecords = count($masterChecklist);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $masterChecklist->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Master checklist', 'Master activity', 'Task','Active', 'Created on', 'Created By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['name'];
                        $columnData[] = $data['master_activity_id']['name'];
                        $columnData[] = $data['task_id']['name'];
                        $columnData[] = ($data['is_active'] == 0) ? 'No' : 'Yes';
                        $columnData[] = dateFormat($data['created_on']);
                        $columnData[] = $data['created_by']['userfullname'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Worksheet master checklist', 'xlsx', 'A1:G1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Worksheet master checklist.", ['data' => $masterChecklist], $pager);
        } catch (\Exception $e) {
            app('log')->error("Client listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while worksheet master checklist", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 24, 2018
     * Purpose   : Store worksheet checklist
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store holiday
            $masterChecklist = MasterChecklist::create(['master_activity_id' => $request->get('master_activity_id'),
                        'task_id' => $request->get('task_id'),
                        'name' => $request->get('name'),
                        'is_active' => $request->get('is_active'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet master checklist has been added successfully', ['data' => $masterChecklist]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet master checklist creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add worksheet master checklist.', ['error' => 'Could not add worksheet master checklist.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Fetch master checklist details
     */

    public function show(Request $request, $id) {
        try {
            $masterChecklist = MasterChecklist::with('master_activity_id:id,name')->with('task_id:id,name')->with('created_by:id,userfullname')->find($id);

            if (!isset($masterChecklist))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist does not exist', ['error' => 'The master checklist does not exist']);

            // set master checklist detail
            return createResponse(config('httpResponse.SUCCESS'), 'The master checklist data', ['data' => $masterChecklist]);
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

            $masterChecklist = MasterChecklist::find($id);

            if (!$masterChecklist)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist does not exist', ['error' => 'The master checklist does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['name', 'master_activity_id', 'task_id', 'is_active'], $request);
            $masterChecklist->modified_by = app('auth')->guard()->id();
            $masterChecklist->modified_on = date('Y-m-d H:i:s');
            //update the details
            $masterChecklist->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Master checklist has been updated successfully', ['message' => 'Master checklist has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update master checklist details.', ['error' => 'Could not update master checklist details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Fetch master activity listing with task
     */

    public function getMasterActivity() {
        try {
            // get master activity 
            $data = \App\Models\Backend\MasterActivity::masterActivity();
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet master activity listing', ['data' => $data]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet master activity listing failed." . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not listing worksheet master activity.', ['error' => 'Could not listing worksheet master activity.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate worksheet master checklist input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
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
