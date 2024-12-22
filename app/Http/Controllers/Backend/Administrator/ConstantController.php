<?php

namespace App\Http\Controllers\Backend\Administrator;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ConstantController extends Controller {

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 19, 2018
     * Purpose: List out constant
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $constant = \App\Models\Backend\Constant::with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $constant = search($constant, $search);
            }
            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $constant = $constant->leftjoin("user as u", "u.id", "services.$sortBy");
                $sortBy = 'userfullname';
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $constant = $constant->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $constant->count();

                $constant = $constant->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $constant = $constant->get();

                $filteredRecords = count($constant);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Constant list.", ['data' => $constant], $pager);
        } catch (\Exception $e) {
            app('log')->error("Constant listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Constant", ['error' => 'Constant error.']);
        }
    }

    /**
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 19, 2018
     * Purpose: Update constant setting
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'constant_value' => 'required'], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $constant = \App\Models\Backend\Constant::find($id);

            if (!$constant)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact does not exist', ['error' => 'The Contact does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['constant_value'], $request);

            $constant->modified_by = app('auth')->guard()->id();
            $constant->modified_on = date('Y-m-d H:i:s');
            $constant->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Constant has been updated successfully', ['message' => 'Constant has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Address updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update constant details.', ['error' => 'Could not update constant details.']);
        }
    }

}
