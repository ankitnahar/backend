<?php

namespace App\Http\Controllers\Backend\Contact;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\ContactRemark;

class ClientUserController extends Controller {

    /**
     * Get contact remark detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        $validator = app('validator')->make($request->all(), [
            'sortOrder' => 'in:asc,desc',
            'pageNumber' => 'numeric|min:1',
            'recordsPerPage' => 'numeric|min:0',
            'search' => 'json'
                ], []);

        if ($validator->fails()) // Return error message if validation fails
            return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

        $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'modified_on';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
        $pager = [];

        $clientList = \App\Models\Backend\Client::leftjoin("entity as e", "e.id", "client.entity_id")
                        ->select('client.id','client.entity_id','e.trading_name', 'client.first_name', 'client.last_name', 'client.userfullname', 'client.email', 'client.mobile_no', 'client.birthdate', 'client.user_lastlogin','client.is_active')->where("client.is_active", "1")->where("client.parent_id", "0");
        //echo getSQL($clientList);exit;
        if (!isset($clientList))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The client does not exist', ['error' => 'The client does not exist']);

         $entity_ids = checkUserClientAllocation(loginUser());
        if (is_array($entity_ids)) {
            $clientList = $clientList->whereRaw("e.id IN (" . implode(",", $entity_ids) . ")");
        }
        //for search
        if ($request->has('search')) {
            $search = $request->get('search');
            $alias = array('entity_id' => 'client');
            $clientList = search($clientList, $search, $alias);
        }
        
       
        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $clientList = $clientList->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;
            //count number of total records
            $totalRecords = $clientList->count();

            $clientList = $clientList->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);

            $clientList = $clientList->get();

            $filteredRecords = count($clientList);

            $pager = ['sortBy' => $sortByName,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $d = $clientList->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Entity Name', 'First Name', 'Last Name', 'User Full Name', 'To Email', 'Mobile no', 'Birthdate', 'Last Login'];

            if (!empty($d)) {
                $columnData = array();
                $i = 1;
                foreach ($d as $data) {
                    //$position = config('constant.position');
                    $columnData[] = $i;
                    $columnData[] = $data['trading_name'];
                    $columnData[] = $data['first_name'];
                    $columnData[] = $data['last_name'];
                    $columnData[] = $data['userfullname'];
                    $columnData[] = $data['email'];
                    $columnData[] = $data['mobile_no'];
                    $columnData[] = $data['birthdate'];
                    $columnData[] = $data['user_lastlogin'];
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'ClientList', 'xlsx', 'A1:H1');
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Client List data', ['data' => $clientList], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Client history api failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get client history.', ['error' => 'Could not get client history.']);
          } */
    }

    /**
     * Store contact details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        //try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required',
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required:unique',
                'is_active' => 'required|in:1,0'], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            
            $billing = \App\Models\Backend\Billing::where("entity_id", $request->input('entity_id'))->first();
            $checkClient = \App\Models\Backend\Client::where("email", $request->input('email'))->count();
            if($checkClient == 0){
            // store client details
            $client = \App\Models\Backend\Client::create([
                'entity_id' => $request->input('entity_id'),
                'parent_id' => 0,
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'userfullname' => $request->input('first_name') .' '. $request->input('last_name'),
                'email' => $request->input('email'),
                'mobile_no' => $request->input('mobile_no'),
                'birthdate' => $request->input('birthdate'),
                'password' => '$2y$10$UF2oounxaXvsJap1ZfmUEOx9tJo0u9mjT9CZDBgqt7xqsU/lO9Nee',
                'wdata' => '123456',
                'full_resource' => $billing->full_time_resource,
                'is_active' => 1,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => loginUser()
            ]);
            
             $emailTemplate = \App\Models\Backend\EmailTemplate::where('code', 'NEWCLIENT')->first();
                if ($emailTemplate->is_active == 1) {
                    $data['to'] = $request->input('email');
                    $data['from'] = 'noreply-bdms@befree.com.au';
                    $data['subject'] = $emailTemplate->subject;
                    $data['content'] = $emailTemplate->content;
                    storeMail('', $data);
                }
            }else{
               return createResponse(config('httpResponse.UNPROCESSED'), 'Email ID already exist', ['error' => 'Email ID already exist']);
             
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Client has been added successfully', ['data' => $client]);
       /* } catch (\Exception $e) {
            app('log')->error("Contact remark creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add contact remark', ['error' => 'Could not add contact remark']);
        }*/
    }

    /**
     * update contact details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // contact id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required',
                'first_name' => 'required',
                'last_name' => 'required',
                'email' => 'required:unique',
                'is_active' => 'required|in:1,0'], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $contactRemark = \App\Models\Backend\Client::find($id);

            if (!$contactRemark)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Contact does not exist', ['error' => 'The Contact does not exist']);

            $updateData = array();

            // Filter the fields which need to be updated
            $updateData = filterFields(['entity_id', 'parent_id', 'first_name', 'last_name', 'email',
                'full_resource', 'is_active'], $request);
            $updateData['userfullname'] = $request->input('first_name') .' '. $request->input('last_name');
            $contactRemark->modified_by = app('auth')->guard()->id();
            $contactRemark->modified_on = date('Y-m-d H:i:s');
            //update the details
            $contactRemark->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Client has been updated successfully', ['message' => 'Client has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Client details.', ['error' => 'Could not update Client details.']);
        }
    }

}
