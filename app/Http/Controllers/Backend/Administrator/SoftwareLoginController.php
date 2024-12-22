<?php

namespace App\Http\Controllers\Backend\Administrator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SoftwareLoginController extends Controller {
    /* Created by: Pankaj
     * Created on: 28 -02 -2019
     * Purpose   : Fetch softare login details
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

            $ipAddress = \App\Models\Backend\SoftwareLogin::with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by","usedBy:id,userfullname as used_by");
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $ipAddress = $ipAddress->leftjoin("user as u", "u.id", "ip_address.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $ipAddress = search($ipAddress, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $ipAddress = $ipAddress->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $ipAddress->count();
                $ipAddress = $ipAddress->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $ipAddress->toSql(); die;
                $ipAddress = $ipAddress->get();
                $filteredRecords = count($ipAddress);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Software login list.", ['data' => $ipAddress], $pager);
        } catch (\Exception $e) {
            app('log')->error("Software login listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Software login list", ['error' => 'Server error.']);
        }
    }

    public function store(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'software_name' => 'required|unique:software_login,software_name,'], []);

            //validate request parameters
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            // store ip address
            $software = \App\Models\Backend\SoftwareLogin::create([
                        'software_name' => $request->get('software_name'),
                        'is_active' => 1,
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s'),
                        'modified_by' => app('auth')->guard()->id(),
                        'modified_on' => date('Y-m-d H:i:s')]);

            return createResponse(config('httpResponse.SUCCESS'), 'Software has been added successfully', ['data' => $software]);
        } catch (\Exception $e) {
            app('log')->error("Software creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Software', ['error' => 'Could not add Software']);
        }
    }

    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'software_name' => 'required|unique:software_login,software_name,' . $id], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $software = \App\Models\Backend\SoftwareLogin::find($id);
            if (!$software)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Software does not exist', ['error' => 'The Software does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            if ($request->has('used_by')) {
                if($request->input('used_by') == 0){
                    if(loginUser() != $software->used_by){
                        return createResponse(config('httpResponse.NOT_FOUND'), 'You can not change Status', ['error' => 'You can not change Status']);
                    }
                    $updateData['used_by'] = 0;
                }else{

                    if(!empty($software->used_by) || ($software->used_by == $request->input('used_by'))){
                        return createResponse(config('httpResponse.NOT_FOUND'), 'Oops... something went wrong. Please reload the page to try again', ['error' => 'Oops... something went wrong. Please reload the page to try again']);
                    }
                    $updateData['used_by'] = loginUser();
                }
                $updateData['login_time'] = ($request->input('used_by') > 0) ? date('Y-m-d H:i:s') : '';
            } else {
                $updateData = filterFields(['software_name'], $request);
                $updateData['modified_on'] = date('Y-m-d H:i:s');
                $updateData['modified_by'] = loginUser();
            }
            $software->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Software has been updated successfully', ['message' => 'Software has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Software updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Software details.', ['error' => 'Could not update Software details.']);
        }
    }

    public function destroy(Request $request, $id) {
        try {
            // If validation fails then return error response
            if (!is_numeric($id))
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => 'Please provice numeric id']);

            $software = \App\Models\Backend\SoftwareLogin::find($id);
            if (!$software)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Software does not exist', ['error' => 'The Software does not exist']);

            $software->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Software has been deleted successfully', ['message' => 'Software has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Software deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted Software details.', ['error' => 'Could not deleted Software details.']);
        }
    }

}
