<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Module1\Client;

/**
 * This is a client class controller.
 * 
 */

class ClientController extends Controller
{

    /**
     * Get clients detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try 
        {
            //validate request parameters
            $validator = app('validator')->make($request->all(),[
                'sortOrder'      => 'in:asc,desc',
                'pageNumber'     => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search'         => 'json'
            ],[]);   
            
            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), 
                    "Request parameter missing.",
                    ['error' => $validator->errors()->first()]);
        
            // define soring parameters
            $sortBy     = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder  = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager      = [];
            
            $clients = new Client;

            if ($request->has('search')) 
            {
                // Decode json in to php array
                $search = json_decode($request->get('search'),true);
               
                // Get only required params
                $search = array_filter($search, function($k){
                    return $k == 'id' || $k == 'client_code' || $k == 'legal_name' || $k == 'website' || $k == 'assigned_to';
                }, ARRAY_FILTER_USE_KEY);

                // Filter clients by it's assignee
                if(isset($search['assigned_to']))
                {
                    $clients = $clients->where('assigned_to', $search['assigned_to']);
                    unset($search['assigned_to']);
                }

                foreach ($search as $field => $value)
                   $clients = $clients->where($field, 'like', "%$value%");
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') 
            {
                $clients = $clients->orderBy($sortBy, $sortOrder)->get();

            } else { // Else return paginated records

                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber-1) * $recordsPerPage;
                $take = $recordsPerPage;
              
                //count number of total records
                $totalRecords = $clients->count();

                $clients = $clients->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                
                $clients = $clients->get();
                
                $filteredRecords = count($clients);

                $pager = ['sortBy'      => $sortBy,
                    'sortOrder'         => $sortOrder,
                    'pageNumber'        => $pageNumber,
                    'recordsPerPage'    => $recordsPerPage,
                    'totalRecords'      => $totalRecords,
                    'filteredRecords'   => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), 
                "Clients list.",
                ['data' => $clients], 
                $pager);

        } catch (\Exception $e) 
        {
            app('log')->error("Client listing failed : ".$e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Error while listing clients",
                ['error' => 'Server error.']);
        }        
    }

    /**
     * Store client details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try 
        {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'client_code' => 'required|unique:clients,client_code',
                'legal_name'  => 'required',
                'website'     => 'url'
                ], []);

            if ($validator->fails()) 
                return createResponse(config('httpResponse.UNPROCESSED'),
                'Please enter valid input',
                ['error' => $validator->errors()->first()]);
          
            // store client details
            $client = Client::create([
                'client_code'  => $request->get('client_code'),
                'legal_name'   => $request->get('legal_name'),
                'website'      => $request->get('website')
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 
                'Client has been added successfully',
                ['data' => $client]);

        } catch (\Exception $e) 
        {
            app('log')->error("Client creation failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
               'Could not add client',
                ['error' => 'Could not add client']);
        }
    }

    /**
     * get particular client details
     *
     * @param  int  $id   //Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try 
        {
            $client = Client::with('assignee')->find($id);

            if(! isset($client)) 
                return createResponse(config('httpResponse.NOT_FOUND'), 
                    'The client does not exist',
                    ['error' => 'The client does not exist' ]);

            //send client information
            return createResponse(config('httpResponse.SUCCESS'), 
                'Client data',
                ['data' => $client]);
           
        } catch (\Exception $e) 
        {
            app('log')->error("Client details api failed : ".$e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 
                'Could not get client.',
                ['error' => 'Could not get client.' ]);
        }
    }

     /**
     * update client details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try
        {
            $validator = app('validator')->make($request->all(),[
                'client_code'   => 'unique:clients,client_code,'.$id.',id',
                'website'       => 'url',
                'assigned_to'   => 'numeric'
                ],[]);

            // If validation fails then return error response
            if($validator->fails()) 
                return createResponse(config('httpResponse.UNPROCESSED'), 
                'Please enter valid input',
                ['error' => $validator->errors()->first()]);

            $client = Client::find($id);

            if(!$client) 
                return createResponse(config('httpResponse.NOT_FOUND'), 
                    'The Client does not exist',
                    ['error' => 'The Client does not exist' ]);
          
            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['client_code' ,'legal_name' ,'website', 'assigned_to'], $request);
           
            //update the details
            $client->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 
                'Client has been updated successfully',
                ['message' => 'Client has been updated successfully']);
        }
        catch(\Exception $e) 
        {
            app('log')->error("Client updation failed : ". $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                'Could not update client details.',
                ['error' => 'Could not update client details.' ]);
        }
    }

    public function destroy(Request $request,$id)
    {
        try 
        {
            $client = Client::find($id);
            // Check weather client exists or not
            if(!isset($client)) 
                return createResponse(config('httpResponse.NOT_FOUND'), 
                    'Client does not exist',
                    ['error' => 'Client does not exist' ]);

            $client->delete();

            return createResponse(config('httpResponse.SUCCESS'), 
                'Client has been deleted successfully',
                ['message' => 'Client has been deleted successfully']);
        } catch(\Exception $e) 
        {
            app('log')->error("Client deletion failed : ". $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                'Could not delete client.',
                ['error' =>  'Could not delete client.' ]);
        }
    }
}
