<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\EntityCallManagement;

class CallManagementController extends Controller {

    /**
     * Get callManagment detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_management_call.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $call = EntityCallManagement::getEntityCallManagement();
            
            //check client allocation
            $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids))
                $call = $call->whereRaw("entity_management_call.entity_id IN (". implode(",",$entity_ids).")");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $call = search($call, $search);
            }
             // for relation ship sorting
            if($sortBy =='name' || $sortBy =='billing_name' || $sortBy =='trading_name'){
                $call =$call->leftjoin("entity as e","e.id","entity_management_call.entity_id");
            }
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $call =$call->leftjoin("user as u","u.id","entity_management_call.$sortBy");
                $sortBy = 'userfullname';
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $call = $call->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $call->count();

                $call = $call->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $call = $call->get(['entity_management_call.*']);

                $filteredRecords = count($call);

                $pager = ['sortBy'   => $sortByName,
                    'sortOrder'      => $sortOrder,
                    'pageNumber'     => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords'   => $totalRecords,
                    'filteredRecords'=> $filteredRecords];
            }    
               //For download excel if excel =1
            if ($request->has('excel') && $request->get('excel') == 1) {
                 //format data in array 
                $data = $call->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Entity Name','Trading Name','Billing Name','Date of called', 'Call detail','Any other comment', 'Created on', 'Created by', 'Modified On', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['entity_id']['name'];
                        $columnData[] = $data['entity_id']['trading_name'];
                        $columnData[] = $data['entity_id']['billing_name'];
                        $columnData[] = $data['date_of_called'];
                        $columnData[] = $data['call_detail'];
                        $columnData[] = $data['any_other_comment'];
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['modified_by'];                        
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'CallManagementList', 'xlsx', 'A1:L1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Call Management list.", ['data' => $call], $pager);
        } catch (\Exception $e) {
            app('log')->error("Call Management listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Call Management", ['error' => 'Server error.']);
        }
    }
    /**
     * Store call management details
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
                'date_of_called' => 'required|date',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store client details
            $loginUser = loginUser();
            $call = EntityCallManagement::create([
                        'entity_id' => $request->input('entity_id'),
                        'date_of_called' => date("Y-m-d",strtotime($request->input('date_of_called'))),
                        'call_detail' => $request->input('call_detail'),
                        'any_other_comment' => $request->input('any_other_comment'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity Call Management has been added successfully', ['data' => $call]);
       } catch (\Exception $e) {
            app('log')->error("Entity Call Management creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity call management', ['error' => 'Could not add entity call management']);
        }
    }

    /**
     * update Call Management details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // call management id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id'      => 'numeric',
                'date_of_called' => 'date',
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $call = EntityCallManagement::find($id);

            if (!$call)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity Call Management does not exist', ['error' => 'The Entity Call Management does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['entity_id', 'date_of_called','call_detail','any_other_comment'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $call->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity Call Management has been updated successfully', ['message' => 'Entity Call Management has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity Call Management updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Call Management details.', ['error' => 'Could not update entity call management details.']);
        }
    }
   /**
     * get particular call management details
     *
     * @param  int  $id   //call id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $call = EntityCallManagement::getEntityCallManagement()->find($id);

            if (!isset($call))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Entity Call Management does not exist', ['error' => 'The Entity Call Management does not exist']);

            //send Call Management information
            return createResponse(config('httpResponse.SUCCESS'), 'Entity Call Management data', ['data' => $call]);
       } catch (\Exception $e) {
            app('log')->error("Entity Call Management details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get call management detail.', ['error' => 'Could not get call management detail.']);
        }
    }
    
    public function destroy(Request $request, $id) {
        try {
            $call = EntityCallManagement::find($id);
            // Check weather dynamic group exists or not
            if (!isset($call))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity Call Management does not exist', ['error' => 'Entity Call Management does not exist']);

            $call->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Entity Call Management has been deleted successfully', ['message' => 'Entity Call Management has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity Call Management deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete call management detail.', ['error' => 'Could not delete call management detail.']);
        }
    }   

}
