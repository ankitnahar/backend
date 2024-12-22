<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\EntityAllocation;
use DB;

class EntityAllocationController extends Controller {

    /**
     * Get Entity Allocation detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $id) {
        //try {
        $entityAllocation = \App\Models\Backend\EntityAllocation::getEntityAllocation($id);
        $otherAllocation = \App\Models\Backend\EntityAllocationOther::where("entity_id", $id)->first();
        $allocationArray = array();
        //convert allocation json to designation user format
        if (!empty($entityAllocation)) {
            foreach ($entityAllocation as $allocation) {
                if ($allocation->allocation_json != '') {

                    $serviceAllocation = json_decode($allocation->allocation_json, true);
                    foreach ($serviceAllocation as $key => $value) {
                        $designation = \App\Models\Backend\Designation::where("is_active", "1")->where("id", $key);
                        if ($designation->count() == 0) {
                            continue;
                        }
                        $allocationArray[$allocation->service_id][] = array("designation_id" => $key, "user_id" => $value);
                    }
                }
                if (!empty($allocationArray)) {
                    $entityServiceAllocation['allocation_json'] = json_encode($allocationArray);
                } else {
                    $entityServiceAllocation['allocation_json'] = "";
                }
            }
        } else {
            $entityServiceAllocation['allocation_json'] = "";
        }
        $entityServiceAllocation['entity_id'] = $id;
        $entityServiceAllocation['other'] = $otherAllocation->other;

        return createResponse(config('httpResponse.SUCCESS'), "Entity Allocation list.", ['data' => $entityServiceAllocation]);
        /* } catch (\Exception $e) {
          app('log')->error("Entity Allocation listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Entity Allocation", ['error' => 'Server error.']);
          } */
    }

    /**
     * Store client allocation details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $id) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'allocation_json' => 'required|json'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        // store bank information details
        $loginUser = loginUser();
        $postValues = json_decode($request->get('allocation_json'), true);
        foreach ($postValues as $key => $value) {
            $serviceId = $key;
            $designationArray = $userArray = array();
            foreach ($value as $row) {
                if ($row['designation_id'] == 9) {
                    \App\Models\Backend\SystemSetupEntityStage::UpdateStage($id, 3, 'Y');
                }
                if ($row['designation_id'] == 15) {
                    \App\Models\Backend\SystemSetupEntityStage::UpdateStage($id, 2, 'Y');
                }
                $designationArray[] = $row['designation_id'];
                $userArray[] = $row['user_id'];
            }

            $allocationJson = array_combine($designationArray, $userArray);
            $allocation = EntityAllocation::where("entity_id", $id)->where("service_id", $serviceId)->first();
            if (!isset($allocation)) {
                $allocation = EntityAllocation::create([
                            'entity_id' => $id,
                            'service_id' => $serviceId,
                            'allocation_json' => json_encode($allocationJson),
                            'created_on' => date('Y-m-d H:i:s'),
                            'created_by' => $loginUser,
                            'modified_on' => date('Y-m-d H:i:s'),
                            'modified_by' => $loginUser
                ]);
            } else {
                //Save history
                $colName = [
                    '15' => 'Division Head',
                    '59' => 'Business Unit Head',
                    '9' => 'Technical Account Manager',
                    '14' => 'Associate Technical Account Manager',
                    '60' => 'Team Lead',
                    '61' => 'Associate Team Lead',
                    '10' => 'Team Member',
                    '68' => 'Review Manager',
                    '69' => 'Associate Review Manager',
                    '70' => 'Review Lead',
                    '71' => 'Reviewer',
                    '73' => 'Associate Review Lead',
                    '62' => 'Senior Technical Head',
                    '63' => 'Technical Head',
                    '75' => 'Associate Technical Head',
                    '64' => 'QC Manager',
                    '65' => 'Associate QC Manager',
                    '66' => 'QC Lead',
                    '72' => 'Associate QC Lead',
                    '67' => 'QC Member',
                    'team_id' => 'Team',
                    'service_id' => 'Service',
                    'designation_id' => 'Designation'
                ];
                //Old and new value
                if ($allocation->allocation_json != '') {
                    $allocationValue = json_encode($allocationJson);
                    $old = json_decode($allocation->allocation_json, true);
                    $new = json_decode($allocationValue, true);
                    $newuserEmailList = array();
                    $diff = (array_diff_assoc($new, $old));
                    if (!empty($diff)) {
                        foreach ($diff as $key => $row) {
                            $colname = isset($colName[$key]) ? $colName[$key] : $key;
                            if (!empty($old[$key])) {
                                $OldUsername = \App\Models\User::where("send_email","1")->find($old[$key]);
                            }
                            if (!empty($row)) {
                                $NewUsername = \App\Models\User::where("send_email","1")->find($row);
                                if(!empty($NewUsername) && isset($NewUsername->email)) {
                                $newuserEmailList[] = $NewUsername->email;
                                }
                            }
                            $diffcloumn[$key] = [
                                'display_name' => ucfirst($colname),
                                'old_value' => isset($OldUsername->userfullname) ? $OldUsername->userfullname : '',
                                'new_value' => isset($NewUsername->userfullname) ? $NewUsername->userfullname : '',
                            ];

                            \App\Models\Backend\EntityAllocationAudit::create([
                                'entity_id' => $id,
                                'service_id' => $serviceId,
                                'changes' => json_encode($diffcloumn),
                                'modified_on' => date('Y-m-d H:i:s'),
                                'modified_by' => $loginUser
                            ]);
                        }
                        $userMailList = array_unique($newuserEmailList);
                        $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", 'CATEAM')->first();
                        if ($emailTemplate->is_active) {
                            for ($i = 0; $i < count($userMailList); $i++) {
                                if (isset($userMailList[$i]) && $userMailList[$i] != '') {
                                    $data['to'] = $userMailList[$i];
                                    $data['cc'] = $emailTemplate->cc;
                                    $data['bcc'] = $emailTemplate->bcc;
                                    $data['subject'] = $emailTemplate->subject;
                                    $msg = html_entity_decode($emailTemplate->content);
                                    $entity_name = \App\Models\Backend\Entity::select("name")->find($id);
                                    $content = replaceString("[ENTITYNAME]", $entity_name->name, $msg);
                                    $loginUserName = \App\Models\User::find($loginUser);
                                    $content = replaceString("[UPDATEDBY]", $loginUserName->userfullname, $content);
                                    $data['content'] = $content;
                                    storeMail($request, $data);
                                }
                            }
                        }
                    }
                }

                $allocation = EntityAllocation::where('id', $allocation->id)
                        ->update(['allocation_json' => json_encode($allocationJson)]);
            }
        }
        //for other allocation
        if ($request->has('other')) {
            $other = self::storeOtherAllocation($request->input('other'), $id);
        } else {
            $other = self::storeOtherAllocation('', $id);
        }
        return createResponse(config('httpResponse.SUCCESS'), 'Entity Allocation has been update successfully', ['data' => 'Entity Allocation has been update successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Entity Allocation creation failed " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity allocation', ['error' => 'Could not add entity allocation']);
          } */
    }

    /**
     * Store other allocation details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public static function storeOtherAllocation($otherVal, $id) {
        $loginUser = loginUser();
        $other = \App\Models\Backend\EntityAllocationOther::where("entity_id", $id)->first();
        $userTabRight = \App\Models\Backend\UserTabRight::select(app('db')->raw('GROUP_CONCAT(user_id) as user_id'))->whereRaw('FIND_IN_SET(42, other_right)')->where('tab_id', 18)->first();
        $otherAllocation = $other['other'] . ',' . $userTabRight->user_id;
        $otherAllocation = explode(",",$otherAllocation);
        $otherAllocation = array_unique($otherAllocation);
        $otherAllocation = array_values($otherAllocation);
        $otherAllocation = implode(",", $otherAllocation);
        \App\Models\Backend\EntityAllocationOther::where("entity_id",$id)->update(["other" => $otherAllocation]);
        if (!isset($other)) {
            $otherData = \App\Models\Backend\EntityAllocationOther::create([
                        'entity_id' => $id,
                        'other' => $otherVal
            ]);

            return $other;
        } else {
            $other = $other->toArray();
            $otherAllocation = "";
            if ($otherVal != '') {
                $allocationArray = array();
                // convert string to array
                $OtherArray = explode(",", $otherVal);
                //check if user already in designation then remove to other allocation
                //check user id exist or not in entity allocation
                $entityAllocation = \App\Models\Backend\EntityAllocation::getEntityAllocation($id);
                foreach ($entityAllocation as $allocation) {
                    if ($allocation->allocation_json != '') {
                        $serviceAllocation = json_decode($allocation->allocation_json, true);
                        foreach ($serviceAllocation as $key => $value) {
                            $allocationArray[$value] = $value;
                        }
                    }
                }
                $otherAllocation = array();
                for ($i = 0; $i < count($OtherArray); $i++) {
                    //if not exist then value store in other
                    if (!array_key_exists($OtherArray[$i], $allocationArray)) {
                        $otherAllocation[] = $OtherArray[$i];
                    }
                }

                if (!empty($otherAllocation)) {
                    $otherAllocation = array_unique($otherAllocation);
                    $otherAllocation = array_values($otherAllocation);
                    $otherAllocation = implode(",", $otherAllocation);
                } else {
                    $otherAllocation = "";
                }
            }
            //showArray($otherAllocation);
            //for save history
            if ($other['other'] != $otherAllocation) {
                if ($other['other'] != '') {
                    $old = \App\Models\User::getAllUserName($other['other']);
                } else {
                    $old = '';
                }
                if ($otherAllocation != '' && !empty($otherAllocation)) {
                    $new = \App\Models\User::getAllUserName($otherAllocation);
                } else {
                    $new = '';
                }
                $diff_col_val['other'] = [
                    'display_name' => 'Other',
                    'old_value' => $old,
                    'new_value' => $new,
                ];
                \App\Models\Backend\EntityAllocationOtherAudit::create([
                    'entity_id' => $id,
                    'changes' => json_encode($diff_col_val),
                    'modified_on' => date('Y-m-d H:i:s'),
                    'modified_by' => $loginUser
                ]);
            }
            if ($otherAllocation != '') {                
                $other = \App\Models\Backend\EntityAllocationOther::where("entity_id", $id)
                        ->update(["other" => $otherAllocation]);
            }
            return $other;
        }
    }

    /**
     * get particular entity allocation history
     *
     * @param  int  $id   //entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $id) {
        try {
            //validate request parameters
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

            $allocationHistory = \App\Models\Backend\EntityAllocationAudit::with('modifiedBy:userfullname,id')->where("entity_id", $id);

            if (!isset($allocationHistory))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The entity allocation history does not exist', ['error' => 'The entity allocation does not exist']);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $allocationHistory = search($allocationHistory, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $allocationHistory = $allocationHistory->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $allocationHistory->count();

                $allocationHistory = $allocationHistory->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $allocationHistory = $allocationHistory->get();

                $filteredRecords = count($allocationHistory);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), 'entity allocation history data', ['data' => $allocationHistory], $pager);
        } catch (\Exception $e) {
            app('log')->error("entity allocation history api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get entity allocation history.', ['error' => 'Could not get entity allocation history.']);
        }
    }

    /**
     * get particular entity allocation other history
     *
     * @param  int  $id   //entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function otherhistory(Request $request, $id) {
        try {
            //validate request parameters
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

            $allocationHistory = \App\Models\Backend\EntityAllocationOtherAudit::with('modifiedBy:userfullname,id')->where("entity_id", $id);

            if (!isset($allocationHistory))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The entity allocation other history does not exist', ['error' => 'The entity allocation other does not exist']);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $allocationHistory = search($allocationHistory, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $allocationHistory = $allocationHistory->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $allocationHistory->count();

                $allocationHistory = $allocationHistory->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $allocationHistory = $allocationHistory->get();

                $filteredRecords = count($allocationHistory);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), 'entity allocation other history data', ['data' => $allocationHistory], $pager);
        } catch (\Exception $e) {
            app('log')->error("entity allocation other history api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get entity allocation other history.', ['error' => 'Could not get entity allocation other history.']);
        }
    }

}
