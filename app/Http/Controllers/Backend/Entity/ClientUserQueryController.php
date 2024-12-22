<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\ClientUserQuery;

/**
 * This is a client class controller.
 * 
 */
class ClientUserQueryController extends Controller {

    /**
     * Get clients detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
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

            $clientuserquery = ClientUserQuery::with('created_by:id,userfullname')->where('entity_id', $id);
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $clientuserquery = $clientuserquery->leftjoin("user as u", "u.id", "client_user_query.$sortBy");
                $sortBy = 'userfullname';
            }

            if ($request->has('search')) {
                $search = $request->get('search');
                $clientuserquery = search($clientuserquery, $search);
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $clientuserquery = $clientuserquery->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $clientuserquery->count();

                $clientuserquery = $clientuserquery->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $clientuserquery = $clientuserquery->get(['client_user_query.*']);

                $filteredRecords = count($clientuserquery);

                $pager = ['sortBy' => $request->get('sortBy'),
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Client User Query list.", ['data' => $clientuserquery], $pager);
        } catch (\Exception $e) {
            app('log')->error("query listing failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing query", ['error' => 'Server error.']);
        }
    }

    /**
     * Store document details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
            'entity_id' => 'required|numeric',
            'comment' => 'required',
            ]);
            

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

                $clientuserquery = ClientUserQuery::create([
                    'entity_id' => $request->get('entity_id'),
                    'comment' => $request->get('comment'),
                    'type' => 0,                                        
                    'created_by' => app('auth')->guard()->id(),
                    'created_on' => date('Y-m-d H:i:s'),
                ]);
            return createResponse(config('httpResponse.SUCCESS'), 'Client user query has been added successfully', ['data' => $clientuserquery]);
        } catch (\Exception $e) {
            app('log')->error("Client creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add client user query', ['error' => 'Could not add clientuserquery']);
        }
    }

    /**
     * get particular document details
     *
     * @param  int  $id   //Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
          //  $clientuserquery = ClientuserQuery::with('created_by:entity_id,comment,type')->find($id);
          $clientuserquery = ClientUserQuery::find($id);

            if (!isset($clientuserquery))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The client user query does not exist', ['error' => 'The client user query does not exist']);

            //send client information
            return createResponse(config('httpResponse.SUCCESS'), 'client user query data', ['data' => $clientuserquery]);
        } catch (\Exception $e) {
            app('log')->error("Query details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get client user query.', ['error' => 'Could not get client user query.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: April 20, 2018
     * Purpose   : Update entity software
     */

    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'numeric',
                'comment' => 'string',
                'type' => 'numeric'
            ]);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

            $clientuserquery = ClientUserQuery::find($id);

            if (!$clientuserquery)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The client user query does not exist', ['error' => 'The client user query does not exist']);
            
            $clientuserqueryDuplication = ClientUserQuery::where('id', '!=', $id)->where('entity_id', $request->get('entity_id'))->count();
            if($clientuserqueryDuplication > 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Duplication user query', ['error' => 'Duplication user query']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['entity_id', 'comment', 'type'], $request);
            $clientuserquery->created_by = app('auth')->guard()->id();
              $clientuserquery->created_on = date('Y-m-d H:i:s');
            //update the details
            $clientuserquery->update($updateData);


            return createResponse(config('httpResponse.SUCCESS'), 'client user query has been updated successfully', ['message' => 'client user query has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client user query updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update query details.', ['error' => 'Could not update query details.']);
        }
    }
           
    

   

}
