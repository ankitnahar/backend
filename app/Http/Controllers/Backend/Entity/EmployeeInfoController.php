<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\EntityEmployeeInfo;

class EmployeeInfoController extends Controller {

    /**
     * Get Employee Information detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_employee_information.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $employeeInfo = EntityEmployeeInfo::getEntityEmployeeInfo();
             //check client allocation
            $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids))
                $employeeInfo = $employeeInfo->whereRaw("entity_employee_information.entity_id IN (". implode(",",$entity_ids).")");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $employeeInfo = search($employeeInfo, $search);
            }
            // for relation ship sorting
            if($sortBy =='name' || $sortBy =='billing_name' || $sortBy =='trading_name'){
                $employeeInfo =$employeeInfo->leftjoin("entity as e","e.id","entity_employee_information.entity_id");
            }
           if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $employeeInfo =$employeeInfo->leftjoin("user as u","u.id","entity_employee_information.$sortBy");
                $sortBy = 'userfullname';
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $employeeInfo = $employeeInfo->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $employeeInfo->count();

                $employeeInfo = $employeeInfo->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $employeeInfo = $employeeInfo->get(['entity_employee_information.*']);

                $filteredRecords = count($employeeInfo);

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
                $data = $employeeInfo->toArray();
                $column = array();
                $column[] = ['Sr.No','Entity Name','Trading Name','Billing Name', 'Employee first name', 'Employee last name','Line 1', 'City','State','Post code','Email','Date of birth','Start date','Tax file number','Employee membership','Superannuation fund', 'Created on', 'Created by', 'Modified On', 'Modified By'];
                if (!empty($data)) {
                    $columnData = array();
                    $i = 1;
                    foreach ($data as $data) {
                        $columnData[] = $i;
                        $columnData[] = $data['entity_id']['name'];
                        $columnData[] = $data['entity_id']['trading_name'];
                        $columnData[] = $data['entity_id']['billing_name'];
                        $columnData[] = $data['emp_first_name'];
                        $columnData[] = $data['emp_last_name'];
                        $columnData[] = $data['line1'];
                        $columnData[] = $data['city'];
                        $columnData[] = $data['state'];
                        $columnData[] = $data['post_code'];
                        $columnData[] = $data['email'];
                        $columnData[] = $data['date_of_birth'];
                        $columnData[] = $data['start_date'];
                        $columnData[] = $data['tax_file_number'];
                        $columnData[] = $data['employee_membership'];
                        $columnData[] = $data['superannuation_fund'];
                        $columnData[] = $data['created_on'];
                        $columnData[] = $data['created_by']['created_by'];
                        $columnData[] = $data['modified_on'];
                        $columnData[] = $data['modified_by']['modified_by'];                        
                        $column[] = $columnData;
                        $columnData = array();
                        $i++;
                    }
                }
                return exportExcelsheet($column, 'EmployeeInfoList', 'xlsx', 'A1:U1');
            }
            return createResponse(config('httpResponse.SUCCESS'), "Employee Info list.", ['data' => $employeeInfo], $pager);
        } catch (\Exception $e) {
            app('log')->error("Employee Info listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing employee info", ['error' => 'Server error.']);
        }
    }
    /**
     * Store Employee info details
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
                'email' => 'email',
                'date_of_birth' => 'date',
                'start_date' => 'date',
                'post_code' => 'max:8',
                'tax_file_number' => 'min:8|max:9'
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store client details
            $loginUser = loginUser();
            $employeeInfo = EntityEmployeeInfo::create([
                        'entity_id' => $request->input('entity_id'),
                        'emp_first_name' => $request->input('emp_first_name'),
                        'emp_last_name' => $request->input('emp_last_name'),
                        'line1' => $request->input('line1'),
                        'city' => $request->input('city'),
                        'state' => $request->input('state'),
                        'post_code' => $request->input('post_code'),
                        'email' => $request->input('email'),
                        'date_of_birth' => date("Y-m-d",strtotime($request->input('date_of_birth'))),
                        'start_date' => date("Y-m-d",strtotime($request->input('start_date'))),
                        'tax_file_number' => $request->input('tax_file_number'),
                        'employee_membership' => $request->input('employee_membership'),
                        'superannuation_fund' => $request->input('superannuation_fund'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity Employee Info has been added successfully', ['data' => $employeeInfo]);
       } catch (\Exception $e) {
            app('log')->error("Entity Employee Info creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity employee info', ['error' => 'Could not add entity employee info']);
        }
    }

    /**
     * update Employee info details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // employeeinfo id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'numeric',
                'email' => 'email',
                'date_of_birth' => 'date',
                'start_date' => 'date',
                'post_code' => 'max:8',
                'tax_file_number' => 'min:8|max:9'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $employeeInfo = EntityEmployeeInfo::find($id);

            if (!$employeeInfo)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity Employee Info does not exist', ['error' => 'The Entity Feedback does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['entity_id','emp_first_name','emp_last_name','line1','city','state','post_code','email','tax_file_number','employee_membership','superannuation_fund'], $request);
            $updateData['date_of_birth'] = date("Y-m-d",strtotime($request->input('date_of_birth')));
            $updateData['start_date'] = date("Y-m-d",strtotime($request->input('start_date')));
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $employeeInfo->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Entity Employee Info has been updated successfully', ['message' => 'Entity Employee Info has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity Employee Info updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update entity employee info details.', ['error' => 'Could not update entity employee info details.']);
        }
    }
   /**
     * get particular Feedback details
     *
     * @param  int  $id   //feedback id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $employeeInfo = EntityEmployeeInfo::getEntityEmployeeInfo()->find($id);

            if (!isset($employeeInfo))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The entity employee info does not exist', ['error' => 'The entity employee info does not exist']);

            //send EmployeeInfo information
            return createResponse(config('httpResponse.SUCCESS'), 'Employee Info data', ['data' => $employeeInfo]);
        } catch (\Exception $e) {
            app('log')->error("Entity employee info details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get entity employee info.', ['error' => 'Could not get entity employee info.']);
        }
    }
    
    public function destroy(Request $request, $id) {
        try {
            $employeeInfo = EntityEmployeeInfo::find($id);
            // Check weather dynamic group exists or not
            if (!isset($employeeInfo))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Entity employee info does not exist', ['error' => 'Entity employee info does not exist']);

            $employeeInfo->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Entity employee info has been deleted successfully', ['message' => 'Entity employee info has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Entity employee info deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete entity employee info.', ['error' => 'Could not delete entity employee info.']);
        }
    }   

}
