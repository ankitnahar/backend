<?php

namespace App\Http\Controllers\Backend\Entity;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Backend\EntityBankInfo;

class BankInformationController extends Controller {

    /**
     * Get Bank Information detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $entity_id) {
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

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'entity_bank_info.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $bankInformation = EntityBankInfo::BankInformationData($entity_id);
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $bankInformation = search($bankInformation, $search);
            }
            // for relation ship sorting
            if ($sortBy == 'bank_name') {
                $bankInformation = $bankInformation->leftjoin("banks as b", "b.id", "entity_bank_info.bank_id");
            }
            if ($sortBy == 'type_name') {
                $bankInformation = $bankInformation->leftjoin("bank_type as t", "t.id", "entity_bank_info.type_id");
            }
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $bankInformation = $bankInformation->leftjoin("user as u", "u.id", "entity_bank_info.$sortBy");
                $sortBy = 'userfullname';
            }

            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $bankInformation = $bankInformation->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $bankInformation->count();

                $bankInformation = $bankInformation->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $bankInformation = $bankInformation->get(['entity_bank_info.*']);

                $filteredRecords = count($bankInformation);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), "Bank Information list.", ['data' => $bankInformation], $pager);
        } catch (\Exception $e) {
            app('log')->error("Bank Information listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Bank Information", ['error' => 'Server error.']);
        }
    }

    /**
     * Store bank details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request, $entity_id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'bank_id' => 'required|numeric',
                'type_id' => 'required|numeric',
                'is_bank_or_credit_card' => 'required|in:1,2,3',
                'account_no' => 'required|numeric',
                'viewing_rights' => 'in:0,1,2',
                'auto_feed_up' => 'in:0,1,2',
                'is_active' => 'in:1,0'
                    ], ["account_no.unique" => "Account No has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store bank information details
            $loginUser = loginUser();
            $bankInformation = EntityBankInfo::create([
                        'entity_id' => $entity_id,
                        'bank_id' => $request->input('bank_id'),
                        'type_id' => $request->input('type_id'),
                        'is_bank_or_credit_card' => $request->input('is_bank_or_credit_card'),
                        'bsb_notes' => $request->input('bsb_notes'),
                        'account_no' => $request->input('account_no'),
                        'viewing_rights' => $request->input('viewing_rights'),
                        'follow_up_notes' => $request->input('follow_up_notes'),
                        'auto_feed_up' => $request->input('auto_feed_up'),
                        'notes' => $request->input('notes'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Bank Information has been added successfully', ['data' => $bankInformation]);
        } catch (\Exception $e) {
            app('log')->error("Bank Information creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add bank information', ['error' => 'Could not add bank information']);
        }
    }

    /**
     * update Bank Information details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $entity_id   // bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'bank_id' => 'numeric',
                'type_id' => 'numeric',
                'is_bank_or_credit_card' => 'numeric|in:1,2,3',
                'is_active' => 'in:1,0',
                'account_no' => 'numeric',
                'viewing_rights' => 'in:0,1,2',
                'auto_feed_up' => 'in:0,1,2',
                'is_active' => 'in:1,0'
                    ], []);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $bankInformation = EntityBankInfo::find($id);

            if (!$bankInformation)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Bank Information does not exist', ['error' => 'The Bank Information does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['bank_id', 'type_id', 'bsb_notes', 'account_no', 'notes', 'is_bank_or_credit_card',  'follow_up_notes',
                'viewing_rights', 'auto_feed_up', 'is_active'], $request);
            //update the details
            $bankInformation->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Bank Information has been updated successfully', ['message' => 'Bank Information has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Bank Information updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update bank details.', ['error' => 'Could not update bank information details.']);
        }
    }

    public static function saveHistory($model, $col_name) {
        $yesNo = array('is_bank_or_credit_card', 'is_active');
        $yesNoNa = array('viewing_rights', 'auto_feed_up');
        $Dropdown = array('bank_id', 'type_id');
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
                    if ($key == 'bank_id') {
                        $bank = \App\Models\Backend\Bank::getBank();
                        $oldValue = ($oldValue != '') ? $bank[$oldValue] : '';
                        $newValue = ($newValue != '') ? $bank[$newValue] : '';
                        $displayName = ucfirst($colname);
                    } else if ($key == 'type_id') {
                        $account = \App\Models\Backend\Accounttype::getAccountType();
                        $oldValue = ($oldValue != '') ? $account[$oldValue] : '';
                        $newValue = ($newValue != '') ? $account[$newValue] : '';
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
     * get particular bank information details
     *
     * @param  int  $id   //bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $bank = EntityBankInfo::with('createdBy:userfullname as created_by,id', 'bankId:bank_name,id', 'TypeId:type_name,id')->find($id);

            if (!isset($bank))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The bank information does not exist', ['error' => 'The bank information does not exist']);

            //send bank information
            return createResponse(config('httpResponse.SUCCESS'), 'Bank information data', ['data' => $bank]);
        } catch (\Exception $e) {
            app('log')->error("Bank information api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get bank information.', ['error' => 'Could not get bank information.']);
        }
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

            $bankinfoHistory = \App\Models\Backend\EntityBankInfoAudit::with('modifiedBy:userfullname,id')->where("bank_info_id", $id);

            if (!isset($bankinfoHistory))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The bank information does not exist', ['error' => 'The bank information does not exist']);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $bankinfoHistory = search($bankinfoHistory, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $bankinfoHistory = $bankinfoHistory->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $bankinfoHistory->count();

                $bankinfoHistory = $bankinfoHistory->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $bankinfoHistory = $bankinfoHistory->get();

                $filteredRecords = count($bankinfoHistory);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Bank information history data', ['data' => $bankinfoHistory], $pager);
        } catch (\Exception $e) {
            app('log')->error("Bank information history api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get bank information history.', ['error' => 'Could not get bank information history.']);
        }
    }

}
