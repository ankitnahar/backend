<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\EntityOtherInfo;

/**
 * This is a Other Account Class controller.
 * 
 */
class OtherInformationController extends Controller {

    /**
     * Get Other Informtion Listing
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $entity_id) {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_other_info.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $otherInformation = EntityOtherInfo::OtherInformationData($entity_id);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $otherInformation = search($otherInformation, $search);
            }
            // for relation ship sorting
            if($sortBy =='created_by'){                
                $otherInformation =$otherInformation->leftjoin("user as u","u.id","entity_other_info.$sortBy");
                $sortBy = 'userfullname';
            }

            // for relation ship sorting
            if($sortBy =='account_name'){                
                $otherInformation =$otherInformation->leftjoin("other_account as o","o.id","entity_other_info.$sortBy");
            }
            
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $otherInformation = $otherInformation->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $otherInformation->count();

                $otherInformation = $otherInformation->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $otherInformation = $otherInformation->get(['entity_other_info.*']);

                $filteredRecords = count($otherInformation);

                $pager = ['sortBy'   => $sortByName,
                    'sortOrder'      => $sortOrder,
                    'pageNumber'     => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords'   => $totalRecords,
                    'filteredRecords'=> $filteredRecords];
            }   

            return createResponse(config('httpResponse.SUCCESS'), "Other Information list.", ['data' => $otherInformation], $pager);
        } catch (\Exception $e) {
            app('log')->error("Other Information listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Other Information", ['error' => 'Server error.']);
        }
    }

    /**
     * Store Other Information details
     *
     * ;
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $entity_id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'otheraccount_id' => 'required|numeric',
                'view_access' => 'required|in:0,1,2'
            ]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store Other Account details
            $loginUser = loginUser();
            $otherInfo = EntityOtherInfo::create([
                        'otheraccount_id' => $request->input('otheraccount_id'),
                        'entity_id' => $entity_id,
                        'view_access' => $request->input('view_access'),
                        'befree_comment' => $request->input('befree_comment'),
                        'internal_comment' => $request->input('internal_comment'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Information has been added successfully', ['data' => $otherInfo]);
       } catch (\Exception $e) {
            app('log')->error("Information creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Information', ['error' => 'Could not add Information']);
        }
    }

    /**
     * get particular account details
     *
     * @param  int  $id   //account id
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id) {
        try {
            $otherInfo = EntityOtherInfo::with('createdBy:userfullname as created_by,id','otherAccountId:account_name,id')->find($id);

            if (!isset($otherInfo))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Other Information does not exist', ['error' => 'The Other Information does not exist']);

            //send other information
            return createResponse(config('httpResponse.SUCCESS'), 'Other Information data', ['data' => $otherInfo]);
        } catch (\Exception $e) {
            app('log')->error("Other Information details api failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Other Information.', ['error' => 'Could not get Other Information.']);
        }
    }

    /**
     * update other account details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Other Account id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'otheraccount_id' => 'numeric',
                'view_access' => 'in:1,0',
                'is_active' => 'in:1,0'
            ]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $otherInfo = EntityOtherInfo::find($id);

            if (!$otherInfo)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Other Information does not exist', ['error' => 'The Other Information does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['otheraccount_id', 'befree_comment', 'internal_comment', 'is_active'], $request);
            //update the details
            $otherInfo->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Other Information has been updated successfully', ['message' => 'Other Information has been updated successfully']);
        } catch (\Exception $e) {
            dd($e->getMessage());
            exit;
            app('log')->error("Other Information updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update other details.', ['error' => 'Could not update other information details.']);
        }
    }

    public static function saveHistory($model, $col_name) {
        $yesNo = array('is_active');
        $yesNoNa = array('viewing_rights');
        $Dropdown = array('otheraccount_id');
        $diff_col_val = array();
        if (!empty($model->getDirty())) {
            foreach ($model->getDirty() as $key => $newValue) {
                $oldValue = $model->getOriginal($key);
                $colname = isset($col_name[$key]) ? $col_name[$key] : $key;

                if (in_array($key, $yesNo)) {
                    $constant = config('constant.yesNo');
                    if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                        $oldValue = $constant[$oldValue];
                    else
                        $oldValue = 0;

                    $newValue = $constant[$newValue];
                    $displayName = ucfirst($colname);
                }
                else if (in_array($key, $yesNoNa)) {
                    $constant = config('constant.yesNoNa');
                    if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                        $oldValue = $constant[$oldValue];
                    else
                        $oldValue = 2;

                    $newValue = $constant[$newValue];
                    $displayName = ucfirst($colname);
                }
                else if (in_array($key, $Dropdown)) {
                    if ($key == 'otheraccount_id') {
                        $otherAccount = \App\Models\Backend\OtherAccount::getAccount();
                        $oldValue = ($oldValue != '') ? $bank[$oldValue] : '';
                        $newValue = ($newValue != '') ? $bank[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                } else {
                    $displayName = ucfirst($colname);
                }

                if (isset($displayName) && $displayName != '' && isset($oldValue) && $oldValue != '' && isset($newValue) && $newValue != '')
                    $diff_col_val[$key] = ['display_name' => $displayName, 'old_value' => $oldValue, 'new_value' => $newValue];
            }
        }
        return $diff_col_val;
    }

    /**
     * get particular bank information history
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

            $otherinfoHistory = \App\Models\Backend\EntityOtherInfoAudit::with('modifiedBy:userfullname,id')->where("other_account_id", $id);

            if (!isset($otherinfoHistory))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The other information does not exist', ['error' => 'The other information does not exist']);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $otherinfoHistory = search($otherinfoHistory, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $otherinfoHistory = $otherinfoHistory->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $otherinfoHistory->count();

                $otherinfoHistory = $otherinfoHistory->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $otherinfoHistory = $otherinfoHistory->get();

                $filteredRecords = count($otherinfoHistory);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Other information history data', ['data' => $otherinfoHistory], $pager);
        } catch (\Exception $e) {
            app('log')->error("Other information history api failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get other information history.', ['error' => 'Could not get other information history.']);
        }
    }

}
