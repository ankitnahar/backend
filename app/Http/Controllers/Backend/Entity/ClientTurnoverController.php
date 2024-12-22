<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\EntityClientTurnover;

class ClientTurnoverController extends Controller {

    /**
     * Get Client turnover detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_client_turnover.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $turnover = EntityClientTurnover::getEntityClientTurnover();
             //check client allocation
            $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids))
                $turnover = $turnover->whereRaw("entity_client_turnover.entity_id IN (".implode(",",$entity_ids).")");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $turnover = search($turnover, $search);
            }
            
            // for relation ship sorting
            if($sortBy =='name' || $sortBy =='billing_name' || $sortBy =='trading_name'){
                $turnover =$turnover->leftjoin("entity as e","e.id","entity_client_turnover.entity_id");
            }
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $turnover =$turnover->leftjoin("user as u","u.id","entity_client_turnover.$sortBy");
                $sortBy = 'userfullname';
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $turnover = $turnover->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $turnover->count();

                $turnover = $turnover->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $turnover = $turnover->get(['entity_client_turnover.*']);

                $filteredRecords = count($turnover);

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
                $data = $turnover->toArray();
                $column = array();
                $column[] = ['Sr.No','Entity Name','Trading Name','Billing Name', 'year', 'march_qtr','june_qtr', 'sept_qtr','dec_qtr','total', 'Created on', 'Created by', 'Modified On', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['entity_id']['name'];
                        $columnData[] = $data['entity_id']['trading_name'];
                        $columnData[] = $data['entity_id']['billing_name'];
                        $columnData[] = $data['year'];
                        $columnData[] = '"'.$data['march_qtr'].'"';
                        $columnData[] = '"'.$data['june_qtr'].'"';
                        $columnData[] = '"'.$data['sept_qtr'].'"';
                        $columnData[] = '"'.$data['dec_qtr'].'"';
                        $columnData[] = '"'.$data['total'].'"';
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['modified_by'];                        
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'ClientTurnoverList', 'xlsx', 'A1:N1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Client Turnover list.", ['data' => $turnover], $pager);
        } catch (\Exception $e) {
            app('log')->error("Client Turnover listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Client Turnover", ['error' => 'Server error.']);
        }
    }
    /**
     * Store Client turnover details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id'=> 'required|numeric',
                'year' => 'required',
                'march_qtr'=> 'required',
                'june_qtr'=> 'required',
                'sept_qtr'=> 'required',
                'dec_qtr'=> 'required',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $total = $request->input('march_qtr') +$request->input('june_qtr') +$request->input('sept_qtr')+$request->input('dec_qtr');
            // store client details
            $turnover = EntityClientTurnover::where("year",$request->input('year'))->where("entity_id",$request->input('entity_id'));
            if ($turnover->count() > 0)
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => 'this Year already taken for this client']);
           
            $loginUser = loginUser();
            $turnover = EntityClientTurnover::create([
                        'entity_id' => $request->input('entity_id'),
                        'year' => $request->input('year'),
                        'march_qtr' => $request->input('march_qtr'),
                        'june_qtr' => $request->input('june_qtr'),
                        'sept_qtr' => $request->input('sept_qtr'),
                        'dec_qtr' => $request->input('dec_qtr'),
                        'total' => $total,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Client Turnover has been added successfully', ['data' => $turnover]);
       } catch (\Exception $e) {
            app('log')->error("Client Turnover creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add client turnover', ['error' => 'Could not add client turnover']);
        }
    }

    /**
     * update client turnover details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // cleint turnover id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'numeric',
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            
            $turnover = EntityClientTurnover::where("year",$request->input('year'))->where("entity_id",$request->input('entity_id'));
            if ($turnover->count() > 1)
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => 'this Year already taken for this client']);
           
            
            $turnover = EntityClientTurnover::find($id);

            if (!$turnover)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Client Turnover does not exist', ['error' => 'The Client Turnover does not exist']);
            
            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
             $total = $request->input('march_qtr') +$request->input('june_qtr') +$request->input('sept_qtr')+$request->input('dec_qtr');
            $updateData = filterFields(['entity_id','year','sept_qtr','march_qtr','june_qtr','dec_qtr'], $request);
             $updateData['total'] = $total;
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $turnover->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Client Turnover has been updated successfully', ['message' => 'Client Turnover has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client Turnover updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update client turnover details.', ['error' => 'Could not update client turnover details.']);
        }
    }
   /**
     * get particular client turnover details
     *
     * @param  int  $id   //client turnover id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $turnover = EntityClientTurnover::getEntityClientTurnover()->find($id);

            if (!isset($turnover))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity Client turnover does not exist', ['error' => 'Entity Client turnover does not exist']);

            //send client turnover information
            return createResponse(config('httpResponse.SUCCESS'), 'Entity Client turnover data', ['data' => $turnover]);
        } catch (\Exception $e) {
            app('log')->error("Entity Client turnover details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get client turnover.', ['error' => 'Could not get client turnover.']);
        }
    }
    
    public function yearList() {
        try {
            $j=2015;
            $currentYear  = date('Y')+2;
            for($i=2014;$i<=$currentYear;$i++){
                $year[] = $i.'-'.$j;
                $j++;
            }
            $yearList = $year;            
            return createResponse(config('httpResponse.SUCCESS'), 'Year List', ['data' => $yearList]);
        } catch (\Exception $e) {
            app('log')->error("Year List : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get year list.', ['error' => 'Could not get year list.']);
        }
    } 
    
    public function destroy(Request $request, $id) {
        try {
            $turnover = EntityClientTurnover::find($id);
            // Check weather dynamic group exists or not
            if (!isset($turnover))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity Client turnover does not exist', ['error' => 'Entity Client turnover does not exist']);

            $turnover->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Entity Client turnover has been deleted successfully', ['message' => 'Entity Client turnover has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity Client turnover deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete client turnover.', ['error' => 'Could not delete client turnover.']);
        }
    }   

}
