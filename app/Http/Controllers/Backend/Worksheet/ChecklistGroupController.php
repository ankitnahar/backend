<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Checklistgroup;

class ChecklistGroupController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Worksheet master checklist group listing api
     */

    public function index(Request $request) {
       // try {
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'master_checklist_group.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $checklistGroup = Checklistgroup::with('master_checklist_id:id,name')->with('created_by:id,userfullname')->with('subactivity_id:id,subactivity_name');

            if ($sortBy == 'created_by') {
                $checklistGroup = $checklistGroup->leftjoin("user as u", "u.id", "master_checklist_group.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($sortBy == 'master_activity_id') {
                $checklistGroup = $checklistGroup->leftjoin("master_activity as ma", "ma.id", "master_checklist_group.$sortBy");
                $sortBy = 'ma.name';
            }

            if ($sortBy == 'task_id') {
                $checklistGroup = $checklistGroup->leftjoin("task as t", "t.id", "master_checklist_group.$sortBy");
                $sortBy = 't.name';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $checklistGroup = search($checklistGroup, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $checklistGroup = $checklistGroup->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $checklistGroup->count();

                $checklistGroup = $checklistGroup->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $checklistGroup = $checklistGroup->get(['master_checklist_group.*']);

                $filteredRecords = count($checklistGroup);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $checklistGroup->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Checklist Group', 'Timesheet Validation', 'Subactivity','Active', 'Created on', 'Created By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['name'];
                        $columnData[] = ($data['is_require_timesheet'] == 0) ? 'No' : 'Yes';;
                        $columnData[] = $data['subactivity_id']['subactivity_name'];
                        $columnData[] = ($data['is_active'] == 0) ? 'No' : 'Yes';
                        $columnData[] = dateFormat($data['created_on']);
                        $columnData[] = $data['created_by']['userfullname'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Worksheet master checklist group', 'xlsx', 'A1:G1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Worksheet master checklist group.", ['data' => $checklistGroup], $pager);
      /*  } catch (\Exception $e) {
            app('log')->error("Worksheet master checklist listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while worksheet master checklist group", ['error' => 'Server error.']);
        }*/
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
            $checklistGroup = Checklistgroup::create([
                        'name' => $request->get('name'),
                        'sort_order' => $request->get('sort_order'),
                        'is_active' => $request->get('is_active'),
                        'subactivity_id' => $request->get('subactivity_id'),
                        'is_require_timesheet' => $request->get('is_require_timesheet'),
                        'email_content' => $request->get('email_content'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet master checklist group has been added successfully', ['data' => $checklistGroup]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet master checklist group creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add worksheet master checklist group.', ['error' => 'Could not add worksheet master checklist group.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Fetch master checklist details
     */

    public function show(Request $request, $id) {
        try {
            $checklistGroup = Checklistgroup::with('master_checklist_id:id,name')->with('subactivity_id:id,subactivity_full_name')->with('created_by:id,userfullname')->with('modified_by:id,userfullname')->find($id);

            if (!isset($checklistGroup))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist does not exist', ['error' => 'The master checklist does not exist']);

            // set master checklist detail
            return createResponse(config('httpResponse.SUCCESS'), 'The master checklist data', ['data' => $checklistGroup]);
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
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $checklistGroup = Checklistgroup::find($id);

            if (!$checklistGroup)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist group does not exist', ['error' => 'The master checklist group does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['master_checklist_id', 'name', 'subactivity_id', 'is_require_timesheet', 'email_content', 'sort_order'], $request);
            $checklistGroup->modified_by = app('auth')->guard()->id();
            $checklistGroup->modified_on = date('Y-m-d H:i:s');
            //update the details
            $checklistGroup->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Master checklist group has been updated successfully', ['message' => 'Master checklist group has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update master checklist group details.', ['error' => 'Could not update master checklist group details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Fetch master checklist details
     */

    public function updatestatus(Request $request, $id) {
        try {
            $checklistGroup = Checklistgroup::find($id);

            if (!$checklistGroup)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist group does not exist', ['error' => 'The master checklist group does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['is_active'], $request);
            $checklistGroup->modified_by = app('auth')->guard()->id();
            $checklistGroup->modified_on = date('Y-m-d H:i:s');
            //update the details
            $checklistGroup->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Master checklist group has been updated successfully', ['message' => 'Master checklist group has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update master checklist group details.', ['error' => 'Could not update master checklist group details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Fetch master activity listing with task
     */

    public function getSubactivity(Request $request) {
        try {
            // get master activity 
            $subActivity = \App\Models\Backend\SubActivity::select('subactivity.id', 'subactivity.subactivity_full_name', 't.name as taskName', 't.id as task_id')
                            ->leftJoin('task as t', 't.id', '=', 'subactivity.task_id');

            if ($request->has('task_id'))
                $subActivity = $subActivity->where ('task_id', $request->get ('task_id'));
            else if ($request->has('subcategory_code'))
                $subActivity = $subActivity->where ('subcategory_code', $request->get ('subcategory_code'));
            else
                $subActivity = $subActivity->where('t.master_activity_id', 2);
            
            $subActivity = $subActivity->get()->toArray();
            $subActivitylist = array();
            foreach ($subActivity as $key => $value) {
                $subActivitylist[$value['taskName']][] = $value;
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet master activity listing', ['data' => $subActivitylist]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet master activity listing failed." . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not listing worksheet master activity.', ['error' => 'Could not listing worksheet master activity.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate worksheet master checklist group input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'name' => 'required',
            'sort_order' => 'numeric',
            'subactivity_id' => 'required_if:is_require_timesheet,1'], ['name.required' => 'The group name field is required.',
            'master_checklist_id.required' => 'The master checklist field is required.',
            'master_checklist_id.numeric' => 'The master checklist field must be numeric.',
            'subactivity_id.required_if' => 'The subactivity field is required.']);
        return $validator;
    }

}
