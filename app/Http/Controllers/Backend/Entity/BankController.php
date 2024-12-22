<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\Bank;

class BankController extends Controller {

    /**
     * Get Bank detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'banks.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $bank = Bank::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id');
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $bank = search($bank, $search);
            }
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $bank =$bank->leftjoin("user as u","u.id","banks.$sortBy");
                $sortBy = 'userfullname';
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $bank = $bank->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $bank->count();

                $bank = $bank->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $bank = $bank->get(['banks.*']);

                $filteredRecords = count($bank);

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
                $data = $bank->toArray();
                $column = array();
                $column[] = ['Sr.No', 'Bank name', 'Created on', 'Created By', 'Modified On', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['bank_name'];
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['modified_by'];                        
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'BankList', 'xlsx', 'A1:F1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Bank list.", ['data' => $bank], $pager);
        } catch (\Exception $e) {
            app('log')->error("Bank listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Bank", ['error' => 'Server error.']);
        }
    }
    /**
     * Store bank details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'bank_name' => 'required|unique:banks',
                'is_active' => 'required|in:0,1',
                    ], ['bank_name.unique' => "Bank Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store bank details
            $loginUser = loginUser();
            $bank = Bank::create([
                        'bank_name' => $request->input('bank_name'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Bank has been added successfully', ['data' => $bank]);
       } catch (\Exception $e) {
            app('log')->error("Bank creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add bank', ['error' => 'Could not add bank']);
        }
    }

    /**
     * update Bank details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'bank_name' => 'unique:banks,bank_name,'.$id,
                'is_active' =>  'in:0,1',
                    ], ['bank_name.unique' => "Bank Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $bank = Bank::find($id);

            if (!$bank)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Bank does not exist', ['error' => 'The Bank does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['bank_name', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $bank->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Bank has been updated successfully', ['message' => 'Bank has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Bank updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update bank details.', ['error' => 'Could not update bank details.']);
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
            $bank = Bank::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($bank))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The bank does not exist', ['error' => 'The bank does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Bank data', ['data' => $bank]);
        } catch (\Exception $e) {
            app('log')->error("Bank details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get bank.', ['error' => 'Could not get bank.']);
        }
    }   
      

}
