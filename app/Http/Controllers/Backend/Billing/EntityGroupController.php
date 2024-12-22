<?php

namespace App\Http\Controllers\Backend\Billing;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class EntityGroupController extends Controller {

    /**
     * Get Client Group detail
     *
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_groupclient_belognto.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $clientBelong = \App\Models\Backend\EntityGroupclientBelongs::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')
                    ->where("is_active", "1");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $clientBelong = search($clientBelong, $search);
            }
            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $clientBelong = $clientBelong->leftjoin("user as u", "u.id", "entity_groupclient_belognto.$sortBy");
                $sortBy = 'userfullname';
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $clientBelong = $clientBelong->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $clientBelong->count();

                $clientBelong = $clientBelong->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $clientBelong = $clientBelong->get();

                $filteredRecords = count($clientBelong);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Client Group list.", ['data' => $clientBelong], $pager);
        } catch (\Exception $e) {
            app('log')->error("Client Group listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Client Group", ['error' => 'Server error.']);
        }
    }

    /**
     * Store client group details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'name' => 'required|unique:entity_groupclient_belognto',
                'is_active' => 'required|in:0,1',
                    ], ['name.unique' => "Client Group Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store client group details
            $loginUser = loginUser();
            $clientBelong = \App\Models\Backend\EntityGroupclientBelongs::create([
                        'name' => $request->input('name'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Client Group has been added successfully', ['data' => $clientBelong]);
        } catch (\Exception $e) {
            app('log')->error("Client Group creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add client group', ['error' => 'Could not add client group']);
        }
    }

    /**
     * update Client Group details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // client group id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'name' => 'unique:entity_groupclient_belognto,name,' . $id,
                'is_active' => 'in:0,1',
                    ], ['name.unique' => "Client Group Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $clientBelong = \App\Models\Backend\EntityGroupclientBelongs::find($id);

            if (!$clientBelong)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Client Group does not exist', ['error' => 'The Client Group does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['name', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $clientBelong->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Client Group has been updated successfully', ['message' => 'Client Group has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client Group updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update client group details.', ['error' => 'Could not update client group details.']);
        }
    }

    /**
     * get particular client group details
     *
     * @param  int  $id   //client group id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $clientBelong = \App\Models\Backend\EntityGroupclientBelongs::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($clientBelong))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The client group does not exist', ['error' => 'The client group does not exist']);

            //send client group information
            return createResponse(config('httpResponse.SUCCESS'), 'Client Group data', ['data' => $clientBelong]);
        } catch (\Exception $e) {
            app('log')->error("Client Group details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get client group.', ['error' => 'Could not get client group.']);
        }
    }

}
