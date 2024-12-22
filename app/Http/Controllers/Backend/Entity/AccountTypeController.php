<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Accounttype;

class AccountTypeController extends Controller {

   
    /**
     * Get Account type detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'bank_type.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $accounttype = Accounttype::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id');
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $accounttype = search($accounttype, $search);
            }
            
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $accounttype =$accounttype->leftjoin("user as u","u.id","bank_type.$sortBy");
                $sortBy = 'userfullname';
            }           
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $accounttype = $accounttype->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $accounttype->count();

                $accounttype = $accounttype->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $accounttype = $accounttype->get(['bank_type.*']);

                $filteredRecords = count($accounttype);

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
                $data = $accounttype->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Type name', 'Created on', 'Created By', 'Modified On', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['type_name'];
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['modified_by'];                        
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'AccountTypeList', 'xlsx', 'A1:F1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Account type list.", ['data' => $accounttype], $pager);
        } catch (\Exception $e) {
            app('log')->error("Account type listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Account type", ['error' => 'Server error.']);
        }
    }
    /**
     * Store Account Type details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'type_name' => 'required|unique:bank_type',
                'is_active' => 'required|in:0,1',
                    ], ['type_name.unique' => "Account Type Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store account details
            $loginUser = loginUser();
            $accountType = Accounttype::create([
                        'type_name' => $request->input('type_name'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Account Type has been added successfully', ['data' => $accountType]);
       } catch (\Exception $e) {
            app('log')->error("Account type creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add account type', ['error' => 'Could not add Account Type']);
        }
    }

    /**
     * update Account Type details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Account Type
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'type_name' => 'unique:bank_type,type_name,'.$id,
                'is_active' =>  'in:0,1',
                    ], ['type_name.unique' => "Account Type Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $accountType = Accounttype::find($id);

            if (!$accountType)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Account Type does not exist', ['error' => 'The Account Type does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['type_name', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $accountType->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Account Type has been updated successfully', ['message' => 'Account Type has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Bank updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Account Type details.', ['error' => 'Could not update Account Type details.']);
        }
    }
   /**
     * get particular Account details
     *
     * @param  int  $id   //account id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $accountType = Accounttype::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($accountType))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The account type does not exist', ['error' => 'The account type does not exist']);

            //send account information
            return createResponse(config('httpResponse.SUCCESS'), 'Account Type data', ['data' => $accountType]);
        } catch (\Exception $e) {
            app('log')->error("Account Type details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get account type.', ['error' => 'Could not get account type.']);
        }
    }    
     
}
