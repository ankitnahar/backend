<?php

namespace App\Http\Controllers;

use App\Example; // Use model class

/**
 * This is a example class controller.
 * This controller can be referred as very basic REST API example
 */

class ExampleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

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
            
            $examples = new Example;

            if ($request->header('search')) 
            {
                // Decode json in to php array
                $search = json_decode($request->header('search'),true);
               
                // Get only required params
                $search = array_filter($search, function($k){
                    return $k == 'id' || $k == 'field1' || $k == 'field2' || $k == 'field3';
                }, ARRAY_FILTER_USE_KEY);

                foreach ($search as $field => $value)
                   $examples = $examples->where($field, 'like', "%$value%");
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') 
            {
                $examples = $examples->orderBy($sortBy, $sortOrder)->get();

            } else { // Else return paginated records

                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');

                $skip = ($pageNumber-1) * $recordsPerPage;
                $take = $recordsPerPage;
              
                //count number of total records
                $totalRecords = $examples->count();

                $examples = $examples->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);
                
                $examples = $examples->get();
                
                $filteredRecords = count($examples);

                $pager = ['sortBy'      => $sortBy,
                    'sortOrder'         => $sortOrder,
                    'pageNumber'        => $pageNumber,
                    'recordsPerPage'    => $recordsPerPage,
                    'totalRecords'      => $totalRecords,
                    'filteredRecords'   => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), 
                "examples list.",
                ['data' => $examples], 
                $pager);

        } catch (\Exception $e) {
            \Log::error("Example listing failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                "Error while listing examples",
                ['error' => 'Server error.']);
        }        

    }

    public function store(Request $request)
    {
        try 
        {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'field1'        => 'required|unique:tableName,fieldName',
                'field2'        => 'required|unique:numeric',
                'field3'        => 'required_if:field2,somevalue',
                ], []);

            if ($validator->fails()) 
                return createResponse(config('httpResponse.UNPROCESSED'),
                'Please enter valid input',
                ['error' => $validator->errors()->first()]);
          
            // store entity details
            $example = Example::create([
                'field1'      => $request->get('field1'),
                'field2'      => $request->get('field2'),
                'field3'      => $request->get('field3')
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 
                'Example has been added successfully',
                ['data' => $example->id]);

        } catch (\Exception $e) {
            \Log::error("Exception creation failed ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
               'Could not add example',
                ['error' => 'Could not add example']);
        }
    }

    public function show($id)
    {
        try 
        {
            $example = Example::find($id);

            if(!count($example)) 
                return createResponse(config('httpResponse.NOT_FOUND'), 
                    'The example does not exist',
                    ['error' => 'The example does not exist' ]);

            //send example information
            return createResponse(config('httpResponse.SUCCESS'), 
                'Example data',
                ['data' => $example]);
           
        } catch (\Exception $e) {
            \Log::error("Example details api failed : ".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                'Could not get example',
                ['error' => 'Could not get example' ]);
        }
    }

    public function update(Request $request, $id)
    {
        try
        {
            $validator = app('validator')->make($request->all(),[
                'field1'   => 'unique:examples,tableName,'.$id.',id',
                'field2'   => 'unique:examples,tableName,'.$id.',id'
                ],[]);

            // If validation fails then return error response
            if($validator->fails()) 
                return createResponse(config('httpResponse.UNPROCESSED'), 
                'Please enter valid input',
                ['error' => $validator->errors()->first()]);
        
            //check if entiry exist or not
            $example = Example::find($id);

            if(!$example) 
                return createResponse(config('httpResponse.NOT_FOUND'), 
                    'The example does not exist',
                    ['error' => 'The example does not exist' ]);
          
            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['field1' ,'field2' ,'field3'], $request);
           
            //update entity name
            $entity->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 
                'Example has been updated successfully',
                ['message' => 'Example has been updated successfully']);
        }
        catch(\Exception $e) 
        {
            \Log::error("Example updation failed : ". $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                'Could not update example',
                ['error' => 'Could not update example' ]);
        }
    }

    public function destroy(Request $request,$id)
    {
        try 
        {
            $example = Example::find($id);
            // Check weather entity exists or not
            if(!count($example)) 
                return createResponse(config('httpResponse.NOT_FOUND'), 
                    'Example does not exist',
                    ['error' => 'Example does not exist' ]);

            $example->delete();

            return createResponse(config('httpResponse.SUCCESS'), 
                'Example has been deleted successfully',
                ['message' => 'Example has been deleted successfully']);
        }
        catch(\Illuminate\Database\QueryException $e)
        { 
            \Log::error("Example deletion failed : ". $e->getMessage());
            return createResponse(config('httpResponse.FORBIDDEN'), 
                'Could not delete example. The example details are already in use.',
                ['error' => 'Could not delete example. The example details are already in use.' ]);
        }
        catch(\Exception $e) 
        {
            \Log::error("Example deletion failed : ". $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 
                'Could not delete example',
                ['error' =>  'Could not delete example' ]);
        }
    }
}
