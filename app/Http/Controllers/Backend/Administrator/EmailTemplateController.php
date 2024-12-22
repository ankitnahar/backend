<?php

namespace App\Http\Controllers\Backend\Administrator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailTemplateController extends Controller {
    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 24, 2018
     * Purpose   : Fetch email template details
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

            $emailTemplate = \App\Models\Backend\EmailTemplate::with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by");
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $emailTemplate = $emailTemplate->leftjoin("user as u", "u.id", "email_template.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $emailTemplate = search($emailTemplate, $search);
            }
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $emailTemplate = $emailTemplate->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $emailTemplate->count();
                $emailTemplate = $emailTemplate->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $emailTemplate->toSql(); die;
                $emailTemplate = $emailTemplate->get();
                $filteredRecords = count($emailTemplate);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Email template list.", ['data' => $emailTemplate], $pager);
        } catch (\Exception $e) {
            app('log')->error("Email template listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing email template list", ['error' => 'Server error.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 24, 2018
     * Purpose   : Show email template details
     */

    public function show($id) {
        try {
            $emailTemplate = \App\Models\Backend\EmailTemplate::with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by")->find($id);

            if (!isset($emailTemplate))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The email template does not exist', ['error' => 'The email template does not exist']);

            return createResponse(config('httpResponse.SUCCESS'), 'Email template detail', ['data' => $emailTemplate]);
        } catch (\Exception $e) {
            app('log')->error("Email template details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get email template detail.', ['error' => 'Could not get email template detail.']);
        }
    }

    /*
     * Created by: Jayesh Shingrakhiya
     * Created on: Dec 24, 2018
     * Purpose   : Update email template details
     */

    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'is_detail' => 'required',
                'subject' => 'required_if:is_detail,1',
                'content' => 'required_if:is_detail,1',
                'is_active' => 'required'], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $emailTemplate = \App\Models\Backend\EmailTemplate::find($id);
            if (!$emailTemplate)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Email template does not exist', ['error' => 'The Email template does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $emailTemplate->modified_on = date('Y-m-d H:i:s');
            $emailTemplate->modified_by = app('auth')->guard()->id();
            $updateData = filterFields(['subject', 'content', 'cc', 'is_active'], $request);
            $emailTemplate->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Email template has been updated successfully', ['message' => 'Email template has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Email template updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update email template details.', ['error' => 'Could not update email template details.']);
        }
    }

}
