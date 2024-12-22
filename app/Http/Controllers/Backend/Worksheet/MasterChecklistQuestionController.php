<?php

namespace App\Http\Controllers\Backend\Worksheet;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\MasterChecklistQuestion;

class MasterChecklistQuestionController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: July 26, 2018
     * Purpose   : Worksheet master checklist question listing api
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'master_checklist_question.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $masterChecklistQuestion = MasterChecklistQuestion::arrangeData();

            if ($sortBy == 'created_by') {
                $masterChecklistQuestion = $masterChecklistQuestion->leftjoin("user as u", "u.id", "master_checklist_question.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array('master_checklist_id' => 'master_checklist_question', 'master_activity_id' => 'mc', 'task_id' => 'mc', 'checklist_group_id' => 'master_checklist_question');
                $masterChecklistQuestion = search($masterChecklistQuestion, $search, $alias);
            }

            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $checklistGroup = $checklistGroup->leftjoin("user as u", "u.id", "master_checklist_question.$sortBy");
                $sortBy = 'userfullname';
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $masterChecklistQuestion = $masterChecklistQuestion->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $masterChecklistQuestion->count();

                $masterChecklistQuestion = $masterChecklistQuestion->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $masterChecklistQuestion = $masterChecklistQuestion->get(['master_checklist_question.*']);

                $filteredRecords = count($masterChecklistQuestion);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $masterChecklistQuestion->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Master checklist', 'Master activity', 'Task', 'Group', 'Question name', 'Help text','Active', 'Created on', 'Created By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['checklistName'];
                        $columnData[] = $data['activityName'];
                        $columnData[] = $data['taskName'];
                        $columnData[] = $data['groupName'];
                        $columnData[] = $data['question_name'];
                        $columnData[] = $data['help_text'];
                        $columnData[] = ($data['is_active'] == 0) ? 'No' : 'Yes';
                        $columnData[] = dateFormat($data['created_on']);
                        $columnData[] = $data['created_by']['userfullname'];
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Worksheet master checklist question question', 'xlsx', 'A1:J1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Worksheet master checklist question question.", ['data' => $masterChecklistQuestion], $pager);
//        } catch (\Exception $e) {
//            app('log')->error("Client listing failed : " . $e->getMessage());
//
//            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while worksheet master checklist question question", ['error' => 'Server error.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 26, 2018
     * Purpose   : Store worksheet checklist question
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store holiday
            $masterChecklistQuestion = MasterChecklistQuestion::create([
                        'master_checklist_id' => $request->get('master_checklist_id'),
                        'checklist_group_id' => $request->get('checklist_group_id'),
                        'question_name' => $request->get('question_name'),
                        'help_text' => $request->get('help_text'),
                        'is_active' => $request->get('is_active'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet master checklist question question has been added successfully', ['data' => $masterChecklistQuestion]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet master checklist question creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add worksheet master checklist question.', ['error' => 'Could not add worksheet master checklist question.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 26, 2018
     * Purpose   : Fetch master checklist question details
     */

    public function show(Request $request, $id) {
        try {
            $masterChecklistQuestion = MasterChecklistQuestion::arrangeData()->with('modified_by:id,userfullname')->find($id);

            if (!isset($masterChecklistQuestion))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist question does not exist', ['error' => 'The master checklist question does not exist']);

            // set master checklist question detail
            return createResponse(config('httpResponse.SUCCESS'), 'The master checklist question data', ['data' => $masterChecklistQuestion]);
        } catch (\Exception $e) {
            app('log')->error("Master checklist details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not master checklist question details fetch.', ['error' => 'Could not master checklist question details fetch']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 26, 2018
     * Purpose   : Update master checklist question details
     */

    public function update(Request $request, $id) {
        try {
            $validator = $this->validateInput($request);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $masterChecklistQuestion = MasterChecklistQuestion::find($id);

            if (!$masterChecklistQuestion)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist question does not exist', ['error' => 'The master checklist question does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['master_checklist_id', 'checklist_group_id', 'question_name', 'help_text', 'is_active'], $request);
            $masterChecklistQuestion->modified_by = app('auth')->guard()->id();
            $masterChecklistQuestion->modified_on = date('Y-m-d H:i:s');
            //update the details
            $masterChecklistQuestion->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Master checklist has been updated successfully', ['message' => 'Master checklist has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity special notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update master checklist question details.', ['error' => 'Could not update master checklist question details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 26, 2018
     * Purpose   : Fetch master activity listing with task
     */

    public function updatestatus(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'is_active' => 'required|in:1,0'], ['is_active.required' => 'The master checklist name field is required.',
                'is_active.required' => 'The status field is required.', 'is_active.in' => 'The selected status is invalid.']);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $masterChecklistQuestion = MasterChecklistQuestion::find($id);
            
            if (!$masterChecklistQuestion)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The master checklist question does not exist', ['error' => 'The master checklist question does not exist']);
            
            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['is_active'], $request);
            $masterChecklistQuestion->modified_by = app('auth')->guard()->id();
            $masterChecklistQuestion->modified_on = date('Y-m-d H:i:s');
            //update the details
            $masterChecklistQuestion->update($updateData);
            
            return createResponse(config('httpResponse.SUCCESS'), 'Master checklist has been updated successfully', ['message' => 'Master checklist has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Worksheet master activity listing failed." . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not listing worksheet master activity.', ['error' => 'Could not listing worksheet master activity.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 26, 2018
     * Purpose   : Validate worksheet master checklist question input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'master_checklist_id' => 'required|numeric',
            'checklist_group_id' => 'required|numeric',
            'question_name' => 'required',
            'is_active' => 'required|in:1,0'], ['is_active.required' => 'The master checklist name field is required.',
            'master_checklist_id.numeric' => 'The master checklist name field must be numeric.',
            'checklist_group_id.required' => 'The checklist group field is required.',
            'checklist_group_id.numeric' => 'The checklist group field must be numeric.',
            'is_active.required' => 'The status field is required.',
            'is_active.in' => 'The selected status is invalid.']);
        return $validator;
    }

}
