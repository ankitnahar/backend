<?php
namespace App\Http\Controllers\Backend\Worksheet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\SubClient;

class SubClientController extends Controller {

   /**
     * Get Sub Client detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'sub_client.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $subClient = SubClient::with('entity_id:id,code,name,billing_name,trading_name', 'created_by:id,userfullname,user_image','modified_by:id,userfullname,user_image');
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $subClient = search($subClient, $search);
            }
            
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy == 'modified_by'){                
                $subClient = $subClient->leftjoin("user as u","u.id","sub_client.$sortBy");
                $sortBy = 'userfullname';
            }
            
            if($sortBy == 'name' || $sortBy == 'billing_name' || $sortBy == 'trading_name'){
                $subClient = $subClient->leftjoin("entity as e","e.id","sub_client.entity_id");
            }
           
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $subClient = $subClient->orderBy($sortBy, $sortOrder)->get(['sub_client.*']);
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $subClient->count();

                $subClient = $subClient->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $subClient = $subClient->get(['sub_client.*']);

                $filteredRecords = count($subClient);

                $pager = ['sortBy'   => $request->get('sortBy'),
                    'sortOrder'      => $sortOrder,
                    'pageNumber'     => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords'   => $totalRecords,
                    'filteredRecords'=> $filteredRecords];
            }   
            //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {               
                //format data in array 
                $data = $subClient->toArray();
                $column = array();
                $column[] = ['Sr.No','Client Code','Entity Name','Billing Name','Trading Name','Sub Client','Created By','Created on','Modified By','Modified on'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['entity_id']['code'];
                        $columnData[] = $data['entity_id']['name'];
                        $columnData[] = $data['entity_id']['billing_name'];
                        $columnData[] = $data['entity_id']['trading_name'];
                        $columnData[] = $data['subclient'];
                        $columnData[] = $data['created_by']['userfullname']; 
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['modified_by']['userfullname']!=''?$data['modified_by']['userfullname']:'-';
                        $columnData[] = $data['modified_on']!=''?$data['modified_on']:'-'; 
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'Sub client', 'xlsx', 'A1:J1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Sub Client list.", ['data' => $subClient], $pager);
        } catch (\Exception $e) {
            app('log')->error("Sub Client listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Sub Client", ['error' => 'Server error.']);
        }
    }
    
    /**
     * Store sub client details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'subclient' => 'required|unique:sub_client',
                'entity_id' =>'numeric',
                'is_active' => 'in:0,1',
                    ], ['subclient.unique' => "Sub Client Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store bank details
            $subClient = SubClient::create([
                        'subclient'  => $request->get('subclient'),
                        'entity_id'  => $request->get('entity_id'),
                        'is_active'  => $request->get('is_active'),
                        'created_by' => app('auth')->guard()->id(),
                        'created_on' => date('Y-m-d H:i:s')
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Sub Client has been added successfully', ['data' => $subClient]);
       } catch (\Exception $e) {
            app('log')->error("Sub Client creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add sub client', ['error' => 'Could not add sub client']);
        }
    }
    
     
   /**
     * get particular bank details
     *
     * @param  int  $id   //bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $subClient = SubClient::with('entity_id:id,code,name,billing_name,trading_name', 'created_by:id,userfullname,user_image','modified_by:id,userfullname,user_image')->find($id);

            if (!isset($subClient))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Sub Client does not exist', ['error' => 'The Sub Client does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Sub Client data', ['data' => $subClient]);
        } catch (\Exception $e) {
            app('log')->error("Sub Client details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get sub client.', ['error' => 'Could not get sub client.']);
        }
    }   
    
    /**
     * update sub client details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // sub client id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'subclient' => 'unique:sub_client,subclient,'.$id,
                'entity_id' => 'numeric',                
                'is_active'  =>  'in:0,1',
                    ], ['subclient.unique' => "Sub Client name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $subClient = SubClient::find($id);

            if (!$subClient)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Sub Client does not exist', ['error' => 'The Sub Client does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['subclient','entity_id', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = app('auth')->guard()->id();
            //update the details
            $subClient->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Sub Client has been updated successfully', ['message' => 'Sub Client has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Sub Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update sub client details.', ['error' => 'Could not update sub client details.']);
        }
    }
       
    
    /* Created by: Jayesh Shingrakhiya
     * Created on: July 24, 2018
     * Purpose   : Fetch master checklist details
     */

    public function updatestatus(Request $request, $id) {
        try {
            $subClient = SubClient::find($id);

            if (!$subClient)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The sub Client does not exist', ['error' => 'The sub Client does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['is_active'], $request);
            $subClient->modified_by = app('auth')->guard()->id();
            $subClient->modified_on = date('Y-m-d H:i:s');
            //update the details
            $subClient->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Sub Client has been updated successfully', ['message' => 'Sub Client has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Sub Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update master checklist group details.', ['error' => 'Could not update sub Client details.']);
        }
    }
    
    /* Created by: Jayesh Shingrakhiya
     * Created on: March 12, 2019
     * Purpose   : Entity listing 
     */
    
    public function getEntityList(){
        try {
            $entityList = \App\Models\Backend\Billing::select('e.id','name','billing_name','trading_name','discontinue_stage')->leftjoin('entity as e', 'e.id', '=', 'entity_id')->whereIn('discontinue_stage', [0,1])->get()->toArray();
            
            return createResponse(config('httpResponse.SUCCESS'), 'Sub Client data', ['data' => $entityList]);
        } catch (\Exception $e) {
            app('log')->error("Client list failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get client listing.', ['error' => 'Could not get client listing.']);
        }
    }
}
?>