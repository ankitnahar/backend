<?php

namespace App\Http\Controllers\Backend\Worksheet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\WorksheetTraining;

class TrainingController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: July 18, 2018
     * Purpose   : Fetch worksheet training data
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

            $worksheetTraining = WorksheetTraining::with('created_by:id,userfullname,email');

            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $worksheetTraining = $worksheetTraining->leftjoin("user as u", "u.id", "worksheet_traning.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $worksheetTraining = search($worksheetTraining, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $worksheetTraining = $worksheetTraining->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $worksheetTraining->count();

                $worksheetTraining = $worksheetTraining->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $worksheetTraining = $worksheetTraining->get(['worksheet_traning.*']);
                $filteredRecords = count($worksheetTraining);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $worksheetTraining->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Training name', 'Create by', 'Created on'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['traning_name'];
                        $columnData[] = $data['created_by']['userfullname'];
                        $columnData[] = dateFormat($data['created_on']);
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'worksheetTraining', 'xlsx', 'A1:D1');
            }

            return createResponse(config('httpResponse.SUCCESS'), "Worksheet training list.", ['data' => $worksheetTraining], $pager);
        } catch (\Exception $e) {
            app('log')->error("Worksheet training listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing worksheet training", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July  18, 2018
     * Purpose   : Store worksheet training data
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = $this->validateInput($request);
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store worksheet training  details
            $nojob = WorksheetTraining::create(['traning_name' => $request->get('traning_name'),
                        'is_active' => $request->get('is_active'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')]);
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet training  has been added successfully', ['data' => $nojob]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet training  creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add worksheet training ', ['error' => 'Could not add worksheet training ']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 18, 2018
     * Purpose   : Show worksheet training data
     */

    public function show(Request $request, $id) {
        try {
            $worksheetTraining = WorksheetTraining::with('created_by:id,userfullname,email')->with('modified_by:id,userfullname,email')->where('id', $id)->get();

            if (!isset($worksheetTraining))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The software  does not exist', ['error' => 'The software  does not exist']);

            //send software  information
            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet training  data', ['data' => $worksheetTraining]);
        } catch (\Exception $e) {
            app('log')->error("Worksheet training  details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get software .', ['error' => 'Could not get software .']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 18, 2018
     * Purpose   : Show worksheet training data
     */

    public function update(Request $request, $id) {
        try {

            $validator = $this->validateInput($request);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $worksheetTraining = WorksheetTraining::find($id);

            if (!$worksheetTraining)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The worksheet training does not exist', ['error' => 'The worksheet training does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['traning_name', 'is_active'], $request);
            $worksheetTraining->modified_by = app('auth')->guard()->id();
            $worksheetTraining->modified_on = date('Y-m-d H:i:s');
            //update the details
            $worksheetTraining->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Worksheet training has been updated successfully', ['message' => 'Worksheet training has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Worksheet training updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update software details.', ['error' => 'Could not update software details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Validate user input
     */

    public static function validateInput($request) {
        $validator = app('validator')->make($request->all(), [
            'traning_name' => 'required',
            'is_active' => 'required|in:0,1'
                ], ['is_active.required' => 'The status field is required',
            'is_active.in' => 'The selected is status is invalid']);
        return $validator;
    }

}
