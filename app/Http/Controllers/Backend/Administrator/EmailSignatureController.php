<?php

namespace App\Http\Controllers\Backend\Administrator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailSignatureController extends Controller {
    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Fetch email signature details
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
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'order_by';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $emailSignature = \App\Models\Backend\EmailSignature::select('id', 'user_id', 'bk_user_id', app('db')->raw('IF(show_in_welcomemail = 1,"Yes","No") as show_in_welcomemail'), app('db')->raw('IF(show_in_quote = 1,"Yes","No") as show_in_quote'), 'bcc', 'designation_id', 'email')->with('userId:id,userfullname,email', 'bkUserId:id,userfullname,email', 'designationId:id,designation_name');
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $emailSignature = $emailSignature->leftjoin("user as u", "u.id", "ip_address.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $emailSignature = search($emailSignature, $search);
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $emailSignature = $emailSignature->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $emailSignature->count();
                $emailSignature = $emailSignature->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                //echo $emailSignature->toSql(); die;
                $emailSignature = $emailSignature->get();
                $filteredRecords = count($emailSignature);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Email signature list.", ['data' => $emailSignature], $pager);
        } catch (\Exception $e) {
            app('log')->error("Email signature listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing email signature list", ['error' => 'Server error.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Store email signature details
     */

    public function store(Request $request) {
        try {
            $signatureOption = config('constant.manageSignatureOption');
            $validator = app('validator')->make($request->all(), [
                'user_id' => 'required_if:signatureOption,1,2|numeric',
                'order_by' => 'required_if:signatureOption,1,2|numeric',
                'signature' => 'required_if:signatureOption,1,2',
                'signatureOption' => 'required|in:' . implode(",", $signatureOption),
                'email' => 'required_if:signatureOption,2|email',
                'crm' => 'required_if:signatureOption,2|numeric',
                'designation_id' => 'required_if:signatureOption,2|numeric',
                'show_in_welcomemail' => 'required_if:signatureOption,2|in:0,1',
                'is_imap_configured' => 'required_if:signatureOption,2|in:0,1',
                'imap_username' => 'required_if:is_imap_configured,1',
                'imap_password' => 'required_if:is_imap_configured,1'], []);

            //validate request parameters
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $emailSignature = array();
            if ($signatureOption['user'] == $request->get('signatureOption')) {
                $emailSignature['user_id'] = $request->get('user_id');
                $emailSignature['order_by'] = $request->get('order_by');
                $emailSignature['signature'] = $request->get('signature');
                $emailSignature['created_by'] = app('auth')->guard()->id();
                $emailSignature['created_on'] = date('Y-m-d H:i:s');
                $emailSignature = \App\Models\Backend\EmailSignature::create($emailSignature);
            } else if ($signatureOption['team'] == $request->get('signatureOption')) {
                $password = '';
                if ($request->has('imap_password'))
                    $password = \Illuminate\Support\Facades\Crypt::encryptString($request->get('imap_password'));

                $emailSignature['user_id'] = $request->get('user_id');
                $emailSignature['order_by'] = $request->get('order_by');
                $emailSignature['signature'] = $request->get('signature');
                $emailSignature['email'] = $request->get('email');
                $emailSignature['crm'] = $request->get('crm');
                $emailSignature['designation_id'] = $request->get('designation_id');
                $emailSignature['show_in_welcomemail'] = $request->get('show_in_welcomemail');
                $emailSignature['show_in_quote'] = $request->get('show_in_quote');
                $emailSignature['is_imap_configured'] = $request->get('is_imap_configured');
                $emailSignature['imap_username'] = $request->get('imap_username');
                $emailSignature['imap_password'] = $password;
                $emailSignature['created_by'] = app('auth')->guard()->id();
                $emailSignature['created_on'] = date('Y-m-d H:i:s');
                $emailSignature = \App\Models\Backend\EmailSignature::create($emailSignature);
            } else if ($signatureOption['imap'] == $request->get('signatureOption')) {
                
                
            } else if ($signatureOption['imap'] == $request->get('signatureOption')) {
                
            } else {
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);
            }


            return createResponse(config('httpResponse.SUCCESS'), 'Email signature has been added successfully', ['data' => $emailSignature]);
        } catch (\Exception $e) {
            app('log')->error("Email signature  creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add email signature', ['error' => 'Could not email signature']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Show email signature details
     */

    public function show($id) {
        try {
            $emailSignature = \App\Models\Backend\EmailSignature::with("createdBy:id,userfullname as created_by", "modifiedBy:id,userfullname as modified_by")->find($id);

            if (!isset($emailSignature))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The email signature does not exist', ['error' => 'The email signature does not exist']);

            //send email signature information
            return createResponse(config('httpResponse.SUCCESS'), 'Email signature  detail', ['data' => $emailSignature]);
        } catch (\Exception $e) {
            app('log')->error("Email signature details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get email signature detail.', ['error' => 'Could not get email signature detail.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Update email signature details
     */

    public function update(Request $request, $id) {
        try {
            $signatureOption = config('constant.manageSignatureOption');
            $validator = app('validator')->make($request->all(), [
                'user_id' => 'required_if:signatureOption,1,2|numeric',
                'order_by' => 'required_if:signatureOption,1,2|numeric',
                'signature' => 'required_if:signatureOption,1,2',
                'signatureOption' => 'required|in:' . implode(",", $signatureOption),
                'email' => 'required_if:signatureOption,2|email',
                'crm' => 'required_if:signatureOption,2|numeric',
                'designation_id' => 'required_if:signatureOption,2|numeric',
                'show_in_welcomemail' => 'required_if:signatureOption,2|in:0,1',
                'is_imap_configured' => 'required_if:signatureOption,2|in:0,1',
                'imap_username' => 'required_if:is_imap_configured,1',
                'imap_password' => 'required_if:is_imap_configured,1'], []);

            //validate request parameters
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $emailSignature = \App\Models\Backend\EmailSignature::find($id);
            if (!$emailSignature)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Email signature does not exist', ['error' => 'The Email signature does not exist']);

            $updateData = array();
            if ($signatureOption['user'] == $request->get('signatureOption')) {
                $updateData['user_id'] = $request->get('user_id');
                $updateData['show_in_quote'] = $request->get('show_in_quote');
                $updateData['order_by'] = $request->get('order_by');
                $updateData['signature'] = $request->get('signature');
            } else if ($signatureOption['team'] == $request->get('signatureOption')) {
                $password = '';
                if ($request->has('imap_password'))
                    $password = \Illuminate\Support\Facades\Crypt::encryptString($request->get('imap_password'));

                $updateData['user_id'] = $request->get('user_id');
                $updateData['order_by'] = $request->get('order_by');
                $updateData['signature'] = $request->get('signature');
                $updateData['email'] = $request->get('email');
                $updateData['bcc'] = $request->has('bcc') ? implode(",",$request->get('bcc')):'';
                $updateData['crm'] = $request->get('crm');
                $updateData['designation_id'] = $request->get('designation_id');
                $updateData['show_in_welcomemail'] = $request->get('show_in_welcomemail');
                $updateData['is_imap_configured'] = $request->get('is_imap_configured');
                $updateData['imap_username'] = $request->get('imap_username');
                $updateData['imap_password'] = $password;
            }
            $emailSignature->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Email signature has been updated successfully', ['message' => 'Email signature has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Email signature updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update email signature details.', ['error' => 'Could not update email signature details.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: Dec 22, 2018
     * Purpose   : Email signature details permanently removed.
     */

    public function destroy(Request $request, $id) {
        try {
            // If validation fails then return error response
            if (!is_numeric($id))
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => 'Please provice numeric id']);

            $emailSignature = \App\Models\Backend\EmailSignature::find($id);
            if (!$emailSignature)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Email signature does not exist', ['error' => 'The Email signature does not exist']);

            $emailSignature->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Email signature has been deleted successfully', ['message' => 'Email signature has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not deleted email signature details.', ['error' => 'Could not deleted email signature details.']);
        }
    }

}
