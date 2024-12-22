<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\EntityCrmNotes;

class CrmnotesController extends Controller {

    /**
     * Get Crm Notes detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_crm_notes.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $crmNotes = EntityCrmNotes::getEntityCrmNotes();
             //check client allocation
            $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids))
                $crmNotes = $crmNotes->whereRaw("entity_crm_notes.entity_id IN (". implode(",",$entity_ids).")");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $crmNotes = search($crmNotes, $search);
            }
            
            // for relation ship sorting
            if($sortBy =='name' || $sortBy =='billing_name' || $sortBy =='trading_name'){
                $sortBy =($sortBy =='name') ? 'e.name':$sortBy;
                $crmNotes =$crmNotes->leftjoin("entity as e","e.id","entity_crm_notes.entity_id");
            }
            
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $crmNotes =$crmNotes->leftjoin("user as u","u.id","entity_crm_notes.$sortBy");
                $sortBy = 'userfullname';
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $crmNotes = $crmNotes->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $crmNotes->count();

                $crmNotes = $crmNotes->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $crmNotes = $crmNotes->get(['entity_crm_notes.*']);

                $filteredRecords = count($crmNotes);

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
                $data = $crmNotes->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Entity Name','Trading Name','Billing Name', 'Date', 'Notes', 'Created on', 'Created by', 'Modified On', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['entity_id']['name'];
                        $columnData[] = $data['entity_id']['trading_name'];
                        $columnData[] = $data['entity_id']['billing_name'];
                        $columnData[] = $data['date'];
                        $columnData[] = $data['notes'];
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['modified_by'];                        
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'CrmNotesList', 'xlsx', 'A1:K1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Crm Notes list.", ['data' => $crmNotes], $pager);
        } catch (\Exception $e) {
            app('log')->error("Crm Notes listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing crm notes", ['error' => 'Server error.']);
        }
    }
    /**
     * Store crmnotes details
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
                'date' => 'required|date',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store client details
            $loginUser = loginUser();
            $crm = EntityCrmNotes::create([
                        'entity_id' => $request->input('entity_id'),
                        'date' => date("Y-m-d",strtotime($request->input('date'))),
                        'notes' => $request->input('notes'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity CRM Notes has been added successfully', ['data' => $crm]);
       } catch (\Exception $e) {
            app('log')->error("Entity CRM Notes creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity CRM Notes', ['error' => 'Could not add entity CRM Notes']);
        }
    }

    /**
     * update crmnotes details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // crmnotes id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'numeric',
                'date' => 'date',
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $crm = EntityCrmNotes::find($id);

            if (!$crm)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity CRM Notes does not exist', ['error' => 'The Entity CRM Notes does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['entity_id','notes'], $request);
            $updateData['date'] = date("Y-m-d",strtotime($request->input('date')));            
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $crm->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity CRM Notes has been updated successfully', ['message' => 'Entity CRM Notes has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity CRM Notes updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update entity CRM notes details.', ['error' => 'Could not update entity CRM notes details.']);
        }
    }
   /**
     * get particular crmnotes details
     *
     * @param  int  $id   //crmnotes id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $crm = EntityCrmNotes::getEntityCrmNotes()->find($id);

            if (!isset($crm))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The entity CRM notes does not exist', ['error' => 'The entity CRM notes does not exist']);

            //send crmnotes information
            return createResponse(config('httpResponse.SUCCESS'), 'Entity CRM Notes data', ['data' => $crm]);
        } catch (\Exception $e) {
            app('log')->error("Entity CRM Notes details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get entity CRM notes.', ['error' => 'Could not get entity CRM notes.']);
        }
    }
    
    public function destroy(Request $request, $id) {
        try {
            $crm = EntityCrmNotes::find($id);
            // Check weather dynamic group exists or not
            if (!isset($crm))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity CRM notes does not exist', ['error' => 'Entity CRM notes does not exist']);

            $crm->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Entity CRM notes has been deleted successfully', ['message' => 'Entity CRM notes has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity CRM notes deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete entity CRM notes.', ['error' => 'Could not delete entity CRM notes.']);
        }
    }   

}
