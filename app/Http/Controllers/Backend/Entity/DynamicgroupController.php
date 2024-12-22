<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Dynamicgroup;

class DynamicgroupController extends Controller
{
  /**
     * Get Dynamic Group detail
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'dynamic_group.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $Dynamicgroup = Dynamicgroup::getDynamicGroupListing();
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $Dynamicgroup = search($Dynamicgroup, $search);
            }
            // for relation ship sorting
            if($sortBy =='created_by' || $sortBy =='modified_by'){                
                $Dynamicgroup =$Dynamicgroup->leftjoin("user as u","u.id","dynamic_group.$sortBy");
                $sortBy = 'userfullname';
            } 
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $Dynamicgroup = $Dynamicgroup->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $Dynamicgroup->count();

                $Dynamicgroup = $Dynamicgroup->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $Dynamicgroup = $Dynamicgroup->get(['dynamic_group.*']);

                $filteredRecords = count($Dynamicgroup);

                $pager = ['sortBy'   => $sortByName,
                    'sortOrder'      => $sortOrder,
                    'pageNumber'     => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords'   => $totalRecords,
                    'filteredRecords'=> $filteredRecords];
            }           
            return createResponse(config('httpResponse.SUCCESS'), "Dynamic Group list.", ['data' => $Dynamicgroup], $pager);
        } catch (\Exception $e) {
            app('log')->error("Dynamic Group listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing dynamic group", ['error' => 'Server error.']);
        }
    }
    /**
     * Store Dynamic Group details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'group_name' => 'required|unique:dynamic_group',
                'sort_order' => 'required|numeric',
                'is_active' => 'required|in:0,1',
                    ], ['group_name.unique' => "Group Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store dynamic group details
            $loginUser = loginUser();
            $dynamicgroup = Dynamicgroup::create([
                        'group_name' => $request->input('group_name'),
                        'sort_order' => $request->input('sort_order'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Dynamic Group has been added successfully', ['data' => $dynamicgroup]);
       } catch (\Exception $e) {
            app('log')->error("Dynamic Group creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add dynamic group', ['error' => 'Could not add dynamic group']);
        }
    }

    /**
     * update Dynamic Group details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // dynamicGroup id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'group_name' => 'unique:dynamic_group,group_name,'.$id,
                'sort_order' => 'numeric',
                'is_active' =>  'in:0,1',
                    ], ['group_name.unique' => "Group Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $dynamicGroup = Dynamicgroup::find($id);

            if (!$dynamicGroup)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Dynamic Group does not exist', ['error' => 'The Dynamic group does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['group_name', 'is_active','sort_order'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $dynamicGroup->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Dynamic Group has been updated successfully', ['message' => 'Dynamic Group has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Dynamic Group updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update dynamic Group details.', ['error' => 'Could not update dynamic group details.']);
        }
    }
   /**
     * get particular dynamic group details
     *
     * @param  int  $id   //dynamic group id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $dynamicGroup = Dynamicgroup::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($dynamicGroup))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The dynamic group does not exist', ['error' => 'The dynamic group does not exist']);

            //send dynamic group information
            return createResponse(config('httpResponse.SUCCESS'), 'Dynamic Group data', ['data' => $dynamicGroup]);
        } catch (\Exception $e) {
            app('log')->error("Dynamic Group details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get dynamic group.', ['error' => 'Could not get dynamic group.']);
        }
    }
    /**
     * delete particular dynamic group 
     *
     * @param  int  $id   //dynamic group id
     * @return Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id) {
        try {
            $dynamicGroup = Dynamicgroup::find($id);
            // Check weather dynamic group exists or not
            if (!isset($dynamicGroup))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Dynamic Group does not exist', ['error' => 'Dynamic Group does not exist']);

            $dynamicGroup->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Dynamic Group has been deleted successfully', ['message' => 'Dynamic Group has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Dynamic Group deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete dynamic group.', ['error' => 'Could not delete dynamic group.']);
        }
    }
}