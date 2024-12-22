<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * This is a Other Account Class controller.
 * 
 */
class BefreeCommentController extends Controller {

    /**
     * Get Other Account Listing
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder'     => 'in:asc,desc',
                'pageNumber'    => 'numeric|min:1',
                'recordsPerPage'=> 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'bank_comment.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $otherAccount = \App\Models\Backend\BankComment::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id');
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $otherAccount = search($otherAccount, $search);
            }
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $otherAccount =$otherAccount->leftjoin("user as u","u.id","bank_comment.$sortBy");
                $sortBy = 'userfullname';
            }
            
           //$otherAccount = $otherAccount->where('is_active', 1);
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $otherAccount = $otherAccount->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $otherAccount->count();

                $otherAccount = $otherAccount->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $otherAccount = $otherAccount->get();

                $filteredRecords = count($otherAccount);

                $pager = ['sortBy'   => $sortByName,
                    'sortOrder'      => $sortOrder,
                    'pageNumber'     => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords'   => $totalRecords,
                    'filteredRecords'=> $filteredRecords];
            }   

            return createResponse(config('httpResponse.SUCCESS'), "Befree Comment list.", ['data' => $otherAccount], $pager);
        } catch (\Exception $e) {
            dd($e->getMessage());
            exit;
            app('log')->error("Befree Comment listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Befree Comment", ['error' => 'Server error.']);
        }
    }

    /**
     * Store Other Account details
     *
     * ;
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'comment' => 'required',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store Other Account details
            $loginUser = loginUser();
            $account = \App\Models\Backend\BankComment::create([
                        'comment' => $request->input('comment'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Account has been added successfully', ['data' => $account]);
       } catch (\Exception $e) {
            app('log')->error("Account creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add account', ['error' => 'Could not add account']);
        }
    }

    /**
     * get particular account details
     *
     * @param  int  $id   //account id
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id) {
        try {
            $otherAccount = \App\Models\Backend\BankComment::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($otherAccount))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Other Account does not exist', ['error' => 'The Other Account does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Other Account data', ['data' => $otherAccount]);
        } catch (\Exception $e) {
            app('log')->error("Other Account details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Other Account.', ['error' => 'Could not get Other Account.']);
        }
    }

    /**
     * update other account details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Other Account id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'is_active' =>  'in:0,1',
                    ], ['other_account.unique' => "Other Account Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $otherAccount = \App\Models\Backend\BankComment::find($id);

            if (!$otherAccount)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Other Account does not exist', ['error' => 'BEfree Comment does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['comment', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $otherAccount->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'BEfree Comment has been updated successfully', ['message' => 'BEfree Comment has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Other Account updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update befree comment details.', ['error' => 'Could not update befree comment details.']);
        }
    }

}
