<?php

namespace App\Http\Controllers\Backend\DiscontinueEntity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DiscontinueQuestionController extends Controller {
    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 03, 2018
     * Purpose: Get discontinue question detail
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

            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $discontinueQuestion = new \App\Models\Backend\DiscontinueQuestion();
            if ($request->has('search')) {
                $search = $request->get('search');
                $discontinueQuestion = search($discontinueQuestion, $search);
            }

            $discontinueQuestion = $discontinueQuestion->with('parentId:id,name', 'createdBy:id,userfullname', 'modifiedBy:id,userfullname');
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $discontinueQuestion = $discontinueQuestion->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $discontinueQuestion->count();

                $discontinueQuestion = $discontinueQuestion->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $discontinueQuestion = $discontinueQuestion->get();

                $filteredRecords = count($discontinueQuestion);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            if ($request->has('excel') && $request->get('excel') == 1) {
                $data = $discontinueQuestion->toArray();
                $column = array();
                $column[0][] = 'Sr.No';
                $column[0][] = 'Parent question';
                $column[0][] = 'Question';
                $column[0][] = 'Type';
                $column[0][] = 'Status';
                $column[0][] = 'Created by';
                $column[0][] = 'Created on';
                $column[0][] = 'Modified on';
                $column[0][] = 'Modified by';

                if (!empty($data)) {
                    $columnData = array();
                    $type = config('constant.questionType');
                    $i = 1;
                    foreach ($data as $value) {
                        $columnData[] = $i;
                        $columnData[] = isset($value['parent_id']['name']) ? $value['parent_id']['name'] : '-';
                        $columnData[] = $value['name'];
                        $columnData[] = $type[$value['type']];
                        $columnData[] = $value['is_active'] == 1 ? 'Active' : 'Inactive';
                        $columnData[] = $value['created_by']['userfullname'];
                        $columnData[] = dateFormat($value['created_on']);
                        $columnData[] = isset($value['modified_by']['userfullname']) ? $value['modified_by']['userfullname'] : '-';
                        $columnData[] = $value['modified_on'] != '' ? dateFormat($value['modified_on']) : '-';
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Discontinue reason', 'xlsx', 'A1:I1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Discontinue reason list.", ['data' => $discontinueQuestion], $pager);
        } catch (\Exception $e) {
            app('log')->error("Discontinue reason listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing discontinue question", ['error' => 'Server error.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 03, 2018
     * Purpose: Store discontinue question details
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */

    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'name' => 'required',
                'who_fillup' => 'required|numeric',
                'type' => 'required|in:0,1',
                'is_active' => 'required|in:0,1'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $parentId = $request->get('parent_id') != ''?$request->get('parent_id'):0;
            $discontinueQuestion = \App\Models\Backend\DiscontinueQuestion::create(["parent_id" => $parentId,
                        "name" => $request->get('name'),
                        "who_fillup" => $request->get('who_fillup'),
                        "type" => $request->get('type'),
                        "is_active" => $request->get('is_active'),
                        "created_by" => app('auth')->guard()->id(),
                        "created_on" => date('Y-m-d h:m:i')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Discontinue reason has been added successfully', ['data' => $discontinueQuestion]);
        } catch (\Exception $e) {
            app('log')->error("Discontinue reason creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add discontinue question', ['error' => 'Could not add discontinue question']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 03, 2018
     * Purpose: Get particular discontinue question details
     * @param  int  $id   //timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function show($id) {
        try {
            $discontinueQuestionDetail = \App\Models\Backend\DiscontinueQuestion::with('parentId:id,name', 'createdBy:id,userfullname', 'modifiedBy:id,userfullname')->where('id', $id)->get();

            if (empty($discontinueQuestionDetail))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Discontinue reason does not exist', ['error' => 'Discontinue reason does not exist']);

            return createResponse(config('httpResponse.SUCCESS'), 'Discontinue reason detail successfully load.', ['data' => $discontinueQuestionDetail]);
        } catch (\Exception $e) {
            app('log')->error("Discontinue reason details api failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get discontinue question detail.', ['error' => 'Could not get discontinue question detail.']);
        }
    }

    /*
     * Created by: Jayesh Shigrakhiya
     * Created on: Dec 03, 2018 
     * Purpose: update discontinue question details
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // timesheet id
     * @return Illuminate\Http\JsonResponse
     */

    public function update(Request $request, $id) {
        try {
            $discontinueQuestion = \App\Models\Backend\DiscontinueQuestion::find($id);
            $validator = app('validator')->make($request->all(), [
                'parent_id' => 'numeric',
                'who_fillup' => 'numeric',
                'type' => 'in:0,1',
                'is_active' => 'in:0,1'], []);

            if (!$discontinueQuestion)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Reason does not exist', ['error' => 'Reason does not exist']);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $updateData = array();
            // Filter the fields which need to be updated            
            $updateData = filterFields(['parent_id', 'name', 'who_fillup', 'type', 'is_active'], $request);

            $discontinueQuestion['modified_by'] = app('auth')->guard()->id();
            $discontinueQuestion['modified_on'] = date('Y-m-d H:i:s');
            $discontinueQuestion->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Reason has been updated successfully', ['message' => 'Reason has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Timesheet updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update discontinue question details.', ['error' => 'Could not update discontinue question details.']);
        }
    }

}
