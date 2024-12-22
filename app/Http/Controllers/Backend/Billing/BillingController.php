<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Billing;
use DB;

class BillingController extends Controller {

    /**
     * Get invoice detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function billingbasic(Request $request) {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'billing_basic.entity_id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : '';
            $pager = [];

            $billing = Billing::billingData();
            //check client allocation
            $right = checkButtonRights(99, 'all_entity');
            if ($right ==false) { 
            $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids))
                $billing = $billing->whereRaw("e.id IN(". implode(",",$entity_ids).")");
            }
            // echo $billing = $billing->toSql();exit;
            $billing = $billing->groupBy("bs.id");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array("entity_id" => "billing_basic", "service_id" => "bs", "created_on" => "billing_basic", "parent_id" => "billing_basic");
                $billing = search($billing, $search, $alias);
            }
            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $billing = $billing->leftjoin("user as u", "u.id", "billing_basic.$sortBy");
                $sortBy = 'userfullname';
            }
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $billing = $billing->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $billing->get()->count();

                $billing = $billing->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $billing = $billing->get();

                $filteredRecords = count($billing);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Billing list.", ['data' => $billing], $pager);
        } catch (\Exception $e) {
            app('log')->error("Billing listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Billing", ['error' => 'Server error.']);
        }
    }

    /**
     * Get invoice detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json',
                'discountinue_stage' => 'numeric'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'billing_basic.entity_id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $discontinue = ($request->has('discountinue_stage')) ? $request->get('discountinue_stage') : '';
            $pager = [];

            $billingServices = \App\Models\Backend\BillingServices::select("entity_id", "service_id", "is_updated")
                            ->where("is_latest", "1")
                            ->where("is_active", "1")
                            /* ->whereIn("service_id", [1, 2, 6]) */->orderBy("entity_id")->get();

            $billingList = Billing::billingbasicData();
            //check client allocation
            $right = checkButtonRights(99, 'all_service');
            if ($right == false) {
                $userHierarchy = getLoginUserHierarchy();
                $serviceRight = $userHierarchy->other_right != '' ? $userHierarchy->other_right : '0';
                $billingList = $billingList->whereRaw("bs.service_id IN ($serviceRight)");
            }
            $right = checkButtonRights(99, 'all_entity');
            if ($right ==false) { 
            $entity_ids = checkUserClientAllocation(loginUser());

            if (is_array($entity_ids)) {
                $entity_ids = implode(",", $entity_ids);
                $billingList = $billingList->whereRaw("(billing_basic.entity_id IN ($entity_ids) OR billing_basic.parent_id IN ($entity_ids))");
            }
            }

            $billingList = $billingList->groupBy("e.id");
            // echo $billingList = $billingList->toSql();exit;
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array("entity_id" => "billing_basic", "service_id" => "bs", "created_on" => "billing_basic", "parent_id" => "billing_basic");
                $billingList = search($billingList, $search, $alias);
            }
            // for relation ship sorting
            if ($sortBy == 'created_by') {
                $billingList = $billingList->leftjoin("user as u", "u.id", "billing_basic.$sortBy");
                $sortBy = 'userfullname';
            }
            //echo $discontinue;exit;
            if ($discontinue == 0) {
                $billingList = $billingList->where("e.discontinue_stage", "!=", "2");
            }
            //echo $billingList = $billingList->toSql();exit;
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $billingList = $billingList->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $billingList->get()->count();

                $billingList = $billingList->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $billingList = $billingList->get();

                $filteredRecords = count($billingList);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), "Billing list.", ['data' => $billingList, 'servicesUpdated' => $billingServices], $pager);
        } catch (\Exception $e) {
            app('log')->error("Billing listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Billing", ['error' => 'Server error.']);
        }
    }

    /**
     * update invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'contact_person' => 'required',
            'to_email' => 'required|email_array',
            'cc_email' => 'email_array',
            'address' => 'required',
            'notice_period' => 'required|numeric',
            'category_id' => 'required|numeric',
            'full_time_resource' => 'numeric',
            'debtor_followup' => 'numeric',
            'merge_invoice' => 'required|numeric',
            'merge_ff' => 'required|numeric',
            'payment_id' => 'required|numeric',
            'ddr_rec' => 'required|numeric',
            'card_id' => 'required_if:payment_id,2|numeric',
            'surcharge' => 'required_if:payment_id,2|numeric',
            'card_number' => 'required_if:payment_id,2|numeric',
            'entity_grouptype_id' => 'required|numeric',
            'state_id' => 'required|numeric',
            'service' => 'required|array'
                ], []);


        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);


        $billing = Billing::find($id);

        if (!$billing)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Billing does not exist', ['error' => 'The Billing does not exist']);

        $countAuto = \App\Models\Backend\BillingServices::where("entity_id", $billing->entity_id)
                        ->where("auto_invoice", "1")
                        ->where("is_active", "1")
                        ->where("is_latest", "1")->count();

        if (!empty($request->input("related_entity")) && $countAuto > 0) {
            return createResponse(config('httpResponse.UNPROCESSED'),'Auto invoice feature is already enabled for this client hence related invoices cannot be generated' , ['error' => 'Auto invoice feature is already enabled for this client hence related invoices cannot be generated']);
        }
        $updateData = array();
        // Filter the fields which need to be updated
        $updateData = filterFields(['parent_id', 'contact_person', 'to_email', 'cc_email', 'address', 'notice_period', 'category_id', 'full_time_resource',
            'debtor_followup', 'merge_invoice', 'merge_ff', 'payment_id', 'ddr_rec', 'card_id', 'surcharge', 'card_number', 'entity_grouptype_id', 'state_id', 'is_active',
            'notes', 'ddr_followup'], $request);
        //Save history for releated

        $newrelatedHistory = (!empty($request->input("related_entity"))) ? $request->input("related_entity") : array();
        $old = Billing::where("parent_id", $billing->entity_id)->select(DB::raw("GROUP_CONCAT(entity_id) as entity_id"))->first();
        $old = isset($old->entity_id) ? explode(",", $old->entity_id) : array();
        $newArray = array_diff($newrelatedHistory, $old);
        $oldArray = array_diff($old, $newrelatedHistory);

        if (!empty($newArray) || !empty($oldArray)) {
            $old = Billing::leftjoin("entity as e", "e.id", "billing_basic.entity_id")
                            ->select(DB::raw('GROUP_CONCAT(trading_name) as name'))->where("billing_basic.parent_id", $billing->entity_id)->first();
            $new = \App\Models\Backend\Entity::select(DB::raw('GROUP_CONCAT(trading_name) as name'))->whereIn("id", $request->input("related_entity"))->first();

            $relatedArray['related_entity'] = [
                'display_name' => 'Releated Entity for Common Invoice',
                'old_value' => isset($old->name) ? $old->name : '',
                'new_value' => isset($new->name) ? $new->name : '',
            ];
            if (!empty($relatedArray)) {
                //Insert value in audit table
                \App\Models\Backend\BillingAudit::create([
                    'entity_id' => $billing->entity_id,
                    'changes' => json_encode($relatedArray),
                    'modified_on' => date('Y-m-d H:i:s'),
                    'modified_by' => loginUser()
                ]);
            }
        }
        
        /*if($request->has("agreement_letter")){
            $data['location'] = 'agreementLetter';
            $data['entity_id'] = $billing->entity_id;
            $data['module_id'] = 'entity';            
            uploadDocument($request,$data);
        }*/
        


        Billing::where("parent_id", $billing->entity_id)->update(["parent_id" => 0]);
        if ($request->input("related_entity") != '' && !empty($request->input("related_entity"))) {
            $updateData['is_related'] = 1;
            $updateData['merge_invoice'] = 0;
            \App\Models\Backend\Billing::whereIn("entity_id", $request->input("related_entity"))->update(["parent_id" => $billing->entity_id]);
        } else {
            $updateData['is_related'] = 0;
            \App\Models\Backend\Billing::whereIn("entity_id", $request->input("related_entity"))->update(["parent_id" => $billing->entity_id]);
        }
        $loginUser = loginUser();
        if ($request->input("ddr_rec") == 1 && $billing->ddr_rec == 0) {
            $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", 'DDR')->first();
            if ($emailTemplate->is_active) {
                $data['to'] = $emailTemplate->to;
                $data['cc'] = $emailTemplate->cc;
                $data['bcc'] = $emailTemplate->bcc;
                $data['subject'] = $emailTemplate->subject;
                $entity_name = \App\Models\Backend\Entity::select("name")->find($billing->entity_id);
                $loginUserName = \App\Models\User::find($loginUser);
                $content = html_entity_decode(str_replace(array('[ENTITYNAME]', '[UPDATEDBY]'), array($entity_name->name, $loginUserName->userfullname), $emailTemplate->content));
                $data['content'] = $content;
                storeMail($request, $data);
            }
        }

        // send email for big client notification
        if (in_array($request->input('category_id'), array(1, 2)) && in_array($billing->category_id, array(3, 4, 5, 6))) {
            $emailTemplate = \App\Models\Backend\EmailTemplate::where("code", 'BIG')->first();
            if ($emailTemplate->is_active) {
                $data['to'] = $emailTemplate->to;
                $data['cc'] = $emailTemplate->cc;
                $data['bcc'] = $emailTemplate->bcc;
                $data['subject'] = $emailTemplate->subject;
                $entity_name = \App\Models\Backend\Entity::select("name")->find($billing->entity_id);
                $loginUserName = \App\Models\User::find($loginUser);
                $content = html_entity_decode(str_replace(array('[ENTITYNAME]', '[UPDATEDBY]'), array($entity_name->name, $loginUserName->userfullname), $emailTemplate->content));
                $data['content'] = $content;
                storeMail($request, $data);
            }
        }

        //update the details
        $billing->update($updateData);
        // update system setup         
        \App\Models\Backend\SystemSetupEntityStage::UpdateStage($billing->entity_id, '8', 'Y');
        if ($request->has('service')) {
            $services = $request->get('service');
            foreach ($services as $service) {
                if ($service['is_active'] == 0 && $service['billing_service_id'] == 0) {
                    continue;
                } else {
                    // if service will in active then that service data also in active is latest change to zero
                    $serviceData = [
                        'entity_id' => $billing->entity_id,
                        'service_id' => $service['service_id'],
                        'contract_signed_date' => $service['contract_signed_date'],
                        'is_active' => $service['is_active'],
                        'is_latest' => $service['is_active'],
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => loginUser()];

                    if (isset($service['billing_service_id']) && $service['billing_service_id'] != 0) {
                        \App\Models\Backend\BillingServices::where("id", $service['billing_service_id'])->update($serviceData);
                    } else {
                        \App\Models\Backend\BillingServices::Insert($serviceData);
                    }
                    if ($service['is_active'] == 1 && ($service['service_id'] == 1 || $service['service_id'] == 2 || $service['service_id'] == 6)) {
                        $allocation = \App\Models\Backend\EntityAllocation::where("service_id", $service['service_id'])
                                ->where("entity_id", $billing->entity_id);
                        //echo $allocation->count();exit;
                        $serviceId = $service['service_id'];
                        $user = \App\Models\User::leftjoin("user_hierarchy as uh", "uh.user_id", "user.id")
                                ->select("user.id")
                                ->where("uh.designation_id", "15")
                                ->whereRaw("FIND_IN_SET($serviceId,team_id)")
                                ->orderBy("user.userfullname", "asc");
                        if ($user->count() > 0) {
                            $user = $user->first();
                            $designation = \App\Models\Backend\Designation::where("is_display_in_allocation", "1")->where("is_active", "1")
                                            ->orderBy("sort_order", "asc")->get();
                            // showArray($designation);exit;
                            foreach ($designation as $row) {
                                if ($row->id == 15) {
                                    $designationArray[] = $row->id;
                                    $userArray[] = $user->id;
                                } else {
                                    $designationArray[] = $row->id;
                                    $userArray[] = 0;
                                }
                            }
                            $allocationJson = array_combine($designationArray, $userArray);
                            //showArray(json_encode($allocationJson));exit;
                            if ($allocation->count() != 0) {
                                $allocation = $allocation->first();
                                if ($allocation->allocation_json == '') {
                                    \App\Models\Backend\EntityAllocation::where("id", $allocation->id)
                                            ->update(["allocation_json" => json_encode($allocationJson)]);
                                }
                            } else {
                                $allocation = \App\Models\Backend\EntityAllocation::create([
                                            'entity_id' => $billing->entity_id,
                                            'service_id' => $service['service_id'],
                                            'allocation_json' => json_encode($allocationJson),
                                            'created_on' => date('Y-m-d H:i:s'),
                                            'created_by' => loginUser(),
                                            'modified_on' => date('Y-m-d H:i:s'),
                                            'modified_by' => loginUser()]);
                            }
                        }

                        if ($service['service_id'] == 1 || $service['service_id'] == 2) {
                            $billingService = \App\Models\Backend\DirectoryEntity::where("entity_id", $billing->entity_id)
                                    ->where("service_id", $service['service_id']);
                            if ($service['contract_signed_date'] != '0000-00-00' && $service['contract_signed_date'] != null && $service['contract_signed_date'] != '') {
                                $month = date("m");
                                if ($month > 7) {
                                    $year = date("Y", strtotime("+1 Year"));
                                } else {
                                    $year = date("Y");
                                }
                                $checkDirectoryAlready = \App\Models\Backend\DirectoryServiceCreation::where("entity_id", $billing->entity_id)
                                                ->where("service_id", $service['service_id'])->where("year", $year);
                                if ($checkDirectoryAlready->count() == 0) {
                                    $checkbkService = \App\Models\Backend\DirectoryEntity::where("entity_id", $billing->entity_id)->where("year", $year)->where("service_id", $service['service_id']);

                                    if ($checkbkService->count() == 0) {
                                        $datainsert = array('entity_id' => $billing->entity_id,
                                            "service_id" => $service['service_id'],
                                            "year" => $year,
                                            "folder_id" => 0,
                                            "created_on" => date('y-m-d h:i:s'),
                                            "created_by" => loginUser());

                                        \App\Models\Backend\DirectoryServiceCreation::insert($datainsert);
                                    }
                                }
                            }
                        }
                    }
                    // service in active then recurring will be stop
                    if ($service['is_active'] == 0) {

                        // if service deallocated that time remove allocation also
                        \App\Models\Backend\EntityAllocation::where("service_id", $service['service_id'])
                                ->where("entity_id", $billing->entity_id)->update(["allocation_json" => '']);

                        $recurring = \App\Models\Backend\InvoiceRecurring::select("id", "entity_id")->where("service_id", $service['service_id'])
                                        ->whereRaw("FIND_IN_SET($billing->entity_id,entity_id)")->first();
                        if (!empty($recurring)) {
                            $entityIds = explode(",", $recurring->entity_id);
                            foreach ($entityIds as $entity) {
                                if ($entity != $billing->entity_id) {
                                    $recurringEntityArray[] = $entity;
                                }
                            }
                            $recurringEntity = implode(",", $recurringEntityArray);
                            \App\Models\Backend\InvoiceRecurring::where("id", $recurring->id)->update(["entity_id" => $recurringEntity]);
                        }
                        //update subactivity data also

                        if ($service['service_id'] == 1 || $service['service_id'] == 2) {
                            \App\Models\Backend\BillingServicesSubactivity::where("entity_id", $billing->entity_id)
                                    ->where("service_id", $service['service_id'])
                                    ->where("is_latest", 1)
                                    ->update(["is_latest" => "0"]);
                        }
                    }
                }
            }
        }



        return createResponse(config('httpResponse.SUCCESS'), 'Billing info has been updated successfully', ['message' => 'Billing info has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Billing info updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Billing details.', ['error' => 'Could not update Billing details.']);
          } */
    }

    /*
     * Created By - Pankaj
     * Created On - 25/04/2018
     * Common function for save history
     */

    public static function saveHistory($model, $col_name) {
        $ArrayYesNo = array('is_active', 'debtor_followup', 'merge_invoice', 'merge_ff', 'ddr_rec', 'ddr_followup');
        $ArrayDropdown = array('category_id', 'payment_id', 'card_id', 'full_time_resource', 'state_id', 'entity_grouptype_id', 'related_entity');
        $userArray = array('sales_person_id');
        $diff_col_val = array();
        if (!empty($model->getDirty())) {
            foreach ($model->getDirty() as $key => $value) {
                $oldValue = $model->getOriginal($key);
                if ($key == 'is_related' || ($value == '0' && $oldValue == '0.00') || ($value == '0' && $oldValue == '')) {
                    continue;
                }
                $colname = isset($col_name[$key]) ? $col_name[$key] : $key;
                if (in_array($key, $ArrayYesNo)) {
                    $oldval = ($oldValue == '1') ? 'Yes' : 'No';
                    $newval = ($value == '1') ? 'Yes' : 'No';
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldval,
                        'new_value' => $newval,
                    ];
                } else if (in_array($key, $ArrayDropdown)) {
                    if ($key == 'category_id') {
                        $category = config("constant.category");
                        $oldval = ($oldValue > 0 && $oldValue < 5) ? $category[$oldValue] : '';
                        $newval = ($value  > 0 && $value < 5) ? $category[$value] : '';
                    }if ($key == 'payment_id') {
                        $payment = config("constant.payment");
                        $oldval = ($oldValue != '') ? $payment[$oldValue] : '';
                        $newval = ($value != '') ? $payment[$value] : '';
                    } else if ($key == 'card_id') {
                        $card = \App\Models\Backend\Card::get()->pluck("name", "id")->toArray();
                        $oldval = ($oldValue != '') ? $card[$oldValue] : '';
                        $newval = ($value != '') ? $card[$value] : '';
                    } else if ($key == 'full_time_resource') {
                        $fullTime = config("constant.fulltimeresource");
                        $oldval = ($oldValue != '') ? $fullTime[$oldValue] : '';
                        $newval = ($value != '') ? $fullTime[$value] : '';
                    } else if ($key == 'state_id') {
                        $state = \App\Models\Backend\State::get()->pluck("state_name", "state_id")->toArray();
                        $oldval = ($oldValue != '') ? $state[$oldValue] : '';
                        $newval = ($value != '') ? $state[$value] : '';
                    } else if ($key == 'entity_grouptype_id') {
                        $entityGroup = \App\Models\Backend\EntityGroupclientBelongs::where("is_active", "1")->get()->pluck("name", "id")->toArray();
                        $oldval = ($oldValue != '') ? $entityGroup[$oldValue] : '';
                        $newval = ($value != '') ? $entityGroup[$value] : '';
                    }
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldval,
                        'new_value' => $newval,
                    ];
                } else if (in_array($key, $userArray)) {
                    $old = \App\Models\User::find($oldValue);
                    $new = \App\Models\User::find($value);
                    $oldValue = $old->userfullname;
                    $value = $new->userfullname;
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                } else {
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                }
            }
            return $diff_col_val;
        }
        return $diff_col_val;
    }

    /**
     * update user history details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $user_id,$type
     * @return Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $entityId) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'modified_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $history = \App\Models\Backend\BillingAudit::with("modifiedBy:id,userfullname")->where("entity_id", $entityId);

            if (!isset($history))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The billing history does not exist', ['error' => 'The billing history does not exist']);

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $history = search($history, $search);
            }
            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $history = $history->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $history->count();

                $history = $history->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $history = $history->get();

                $filteredRecords = count($history);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Billing history', ['data' => $history], $pager);
        } catch (\Exception $e) {
            app('log')->error("Could not load billing history : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not load billing history.', ['error' => 'Could not load billing history.']);
        }
    }

    /**
     * get particular details
     *
     * @param  int  $id   //billing id
     * @return Illuminate\Http\JsonResponse
     */
    public function show($id) {
        try {
            $relatedEntity = Billing::select(DB::raw("GROUP_CONCAT(entity_id) as entity_ids"))->where("parent_id", $id)->first();

            $billingServiceList = Billing::showbillingData()->where("billing_basic.entity_id", $id);
            $billingArray = array();

            $services = \App\Models\Backend\Services::where("parent_id", "0")->get();
            foreach ($services as $service) {
                if ($service->id != '') {
                    $billingArray['service'][$service->id] = array("billing_service_id" => '',
                        "service_id" => $service->id,
                        "service_name" => $service->service_name,
                        "contract_signed_date" => '',
                        "is_active" => '0');
                }
            }
            foreach ($billingServiceList->get() as $billing) {

                $billingList['id'] = $billing->id;
                $billingList['entity_id'] = $billing->entity_id;
                $billingList['parent_id'] = $billing->parent_id;
                $billingList['contact_person'] = $billing->contact_person;
                $billingList['to_email'] = $billing->to_email;
                $billingList['cc_email'] = $billing->cc_email;
                $billingList['address'] = $billing->address;
                $billingList['notice_period'] = $billing->notice_period;
                $billingList['category_id'] = $billing->category_id;
                $billingList['full_time_resource'] = $billing->full_time_resource;
                $billingList['debtor_followup'] = $billing->debtor_followup;
                $billingList['merge_invoice'] = $billing->merge_invoice;
                $billingList['payment_id'] = $billing->payment_id;
                $billingList['ddr_rec'] = $billing->ddr_rec;
                $billingList['card_id'] = $billing->card_id;
                $billingList['surcharge'] = $billing->surcharge;
                $billingList['card_number'] = $billing->card_number;
                $billingList['entity_grouptype_id'] = $billing->entity_grouptype_id;
                $billingList['state_id'] = $billing->state_id;
                $billingList['notes'] = $billing->notes;
                $billingList['ddr_followup'] = $billing->ddr_followup;
                $billingList['is_related'] = $billing->is_related;
                $billingList['created_by'] = $billing->created_by;
                $billingList['created_on'] = $billing->created_on;
                $billingList['code'] = $billing->code;
                $billingList['name'] = $billing->name;
                $billingList['billing_name'] = $billing->billing_name;
                $billingList['trading_name'] = $billing->trading_name;
                $billingArray[$billing->entity_id] = $billingList;
                if ($billing->service_id != '') {
                    $billingArray['service'][$billing->service_id] = array("billing_service_id" => $billing->billing_service_id,
                        "service_id" => $billing->service_id,
                        "service_name" => $billing->service_name,
                        "contract_signed_date" => $billing->contract_signed_date,
                        "is_active" => $billing->active_service);
                }
            }
            $billingArray['related_entity'] = $relatedEntity->entity_ids;
            if ($billingServiceList->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Billing Basic does not exist', ['error' => 'The Billing Basic does not exist']);

            //send crmnotes information
            return createResponse(config('httpResponse.SUCCESS'), 'Billing Basic data', ['data' => $billingArray]);
        } catch (\Exception $e) {
            app('log')->error("Billing Basic details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Billing Basic.', ['error' => 'Could not get Billing Basic.']);
        }
    }

    public function relatedentity($id) {
        try {
            $relatedList = Billing::leftjoin("entity as e", "e.id", "billing_basic.entity_id")
                            ->select("e.trading_name", "e.id")
                            ->where("billing_basic.is_related", "!=", "1")
                            ->where("billing_basic.parent_id", "=", "0")
                            ->orWhere("billing_basic.parent_id", $id)->where("e.id", "!=", "''")->get();

            //send crmnotes information
            return createResponse(config('httpResponse.SUCCESS'), 'Related entity data', ['data' => $relatedList]);
        } catch (\Exception $e) {
            app('log')->error("Related entity details failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get related entity.', ['error' => 'Could not get related entity.']);
        }
    }

}

?>