<?php

namespace App\Http\Controllers\Backend\Entity;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Backend\Entity;

/**
 * This is a client class controller.
 * 
 */
class EntityController extends Controller {

    /**
     * Get clients detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function index(Request $request) {
        //try {
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
        $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
        $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
        $pager = [];
        $entity = Entity::select('id', 'code', 'name', 'billing_name', 'trading_name', 'is_parent', 'is_dashboard', 'parent_id', 'discontinue_stage', 'xero_email_id', 'created_by', 'ap_notes', 'ar_notes', 'bk_notes', 'bk_review_notes', 'dm_notes', 'payroll_notes', 'software_notes', 'version_notes', 'tax_notes', 'billing_from','team_type')
                ->with('created_by:id,userfullname,email', "parentEntity:id,trading_name");

        $right = checkButtonRights(18, 'all_entity');
        if ($right == false) {
            $entity_ids = checkUserClientAllocation(app('auth')->guard()->id());
            if (is_array($entity_ids)) {
                $entityId = implode(",", $entity_ids);
                $entity = $entity->whereRaw('id IN (' . $entityId . ')');
            }
        } else {
            $id = loginUser();
            $entityallocationList = \App\Models\Backend\EntityAllocationOther::select("entity_id", "other", "id")
                    ->whereRaw("NOT FIND_IN_SET($id,other)");
            if ($entityallocationList->count() > 0) {
                foreach ($entityallocationList->get() as $en) {
                    $otherList = $en->other . "," . $id;
                    \App\Models\Backend\EntityAllocationOther::where("id", $en->id)->update(["other" => $otherList]);
                }
            }
        }
        if ($request->has('search')) {
            $search = $request->get('search');
            $entity = search($entity, $search);
        }
        //echo getSQL($entity);exit;
        // Check if all records are requested 
        if ($request->has('records') && $request->get('records') == 'all') {
            $entity = $entity->orderBy($sortBy, $sortOrder)->get();
        } else { // Else return paginated records
            // Define pager parameters
            $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
            $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
            $skip = ($pageNumber - 1) * $recordsPerPage;
            $take = $recordsPerPage;

            //count number of total records
            $totalRecords = $entity->count();

            $entity = $entity->orderBy($sortBy, $sortOrder)
                    ->skip($skip)
                    ->take($take);
            $entity = $entity->get();
            $filteredRecords = count($entity);

            $pager = ['sortBy' => $sortBy,
                'sortOrder' => $sortOrder,
                'pageNumber' => $pageNumber,
                'recordsPerPage' => $recordsPerPage,
                'totalRecords' => $totalRecords,
                'filteredRecords' => $filteredRecords];
        }
        $entity = Entity::arrangeData($entity);

        return createResponse(config('httpResponse.SUCCESS'), "Clients list.", ['data' => $entity], $pager);
        /* } catch (\Exception $e) {
          app('log')->error("Client listing failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing clients", ['error' => 'Server error.']);
          } */
    }

    /**
     * Store client details
     *
     * ;
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function store(Request $request) {
        //try {
        //validate request parameters
        $validator = app('validator')->make($request->all(), [
            'name' => 'required',
            'billing_name' => 'required',
            'trading_name' => 'required',
            'abn_number' => 'numeric|digits:11',
            'abn_branch_code' => 'numeric|digits_between:1,3',
            'tfn_number' => 'numeric',
            'contract_signed_date' => 'required|date_format:Y-m-d',
            'reviewer_budgeted_unit' => 'required|numeric|digits_between:1,3',
            'is_parent' => 'required|numeric|in:0,1',
            'parent_id' => 'required_if:is_parent,0|numeric'
                ], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $code = generateClientCode($request->get('billing_name'));

        // store client details
        $client = Entity::create([
                    'code' => $code,
                    'name' => $request->get('name'),
                    'billing_name' => $request->get('billing_name'),
                    'trading_name' => $request->get('trading_name'),
                    'related_entity' => $request->get('related_entity'),
                    'related_entity_id' => $request->get('related_entity_id'),
                    'abn_number' => $request->get('abn_number'),
                    'is_parent' => $request->get('is_parent'),
                    'parent_id' => $request->get('parent_id'),
                    'abn_branch_code' => $request->get('abn_branch_code'),
                    'abn_register_date' => $request->get('abn_register_date'),
                    'tfn_number' => $request->get('tfn_number'),
                    'business_type' => $request->get('business_type'),
                    'entity_type' => $request->get('entity_type'),
                    'entity_type_ifother' => ($request->get('entity_type') == 3) ? $request->get('entity_type_ifother') : '',
                    'bk_doneby' => $request->get('bk_doneby'),
                    'bk_doneby_ifother' => $request->get('bk_doneby') == 3 ? $request->get('bk_doneby_ifother') : '',
                    'gst_register' => $request->get('gst_register') != '' ? $request->get('gst_register') : '-1',
                    'gst_register_date' => $request->get('gst_register_date'),
                    'bas_frequency' => $request->get('bas_frequency'),
                    'bas_accrualorcash' => $request->get('bas_accrualorcash'),
                    'payg_frequency' => $request->get('payg_frequency'),
                    'financial_institution_updateon_ato' => $request->get('financial_institution_updateon_ato') != '' ? $request->get('financial_institution_updateon_ato') : '-1',
                    'financial_institution_updateon_ato_ifother' => $request->get('financial_institution_updateon_ato') == 2 ? $request->get('financial_institution_updateon_ato_ifother') : '',
                    'statement_delivery_preference' => $request->get('statement_delivery_preference'),
                    'entity_registerfor_fbt' => $request->get('entity_registerfor_fbt'),
                    'entity_registerfor_fueltaxcredit' => $request->get('entity_registerfor_fueltaxcredit') != '' ? $request->get('entity_registerfor_fueltaxcredit') : '-1',
                    'group_client_belongsto' => $request->get('group_client_belongsto'),
                    'franchise' => $request->get('franchise'),
                    'website' => $request->get('website'),
                    'xero_email_id' => $request->has('xero_email_id') ? $request->get('xero_email_id') : '',
                    'dashboard_reason' => ($request->has('is_dashboard') == 0) ? $request->get('dashboard_reason') : '',
                    'contract_signed_date' => $request->get('contract_signed_date'),
                    'reviewer_budgeted_unit' => $request->get('reviewer_budgeted_unit'),
                    "user_signature" => $request->has('user_signature') ? $request->get('user_signature') : 0,
                    "entity_business_type" => $request->has('entity_business_type'),
                    "billing_from" => $request->has('billing_from') ? $request->get('billing_from') : 0,
                    "team_type" => $request->has('team_type') ? $request->get('team_type') : 0,
                    "feedback_assignee" => $request->has('feedback_assignee') ? $request->get('feedback_assignee') : 0,
                    'created_by' => app('auth')->guard()->id(),
                    'created_on' => date('Y-m-d H:i:s')
        ]);
        $parentCategory = 0;
        if ($request->get('is_parent') == 0 && $request->get('parent_id') > 0) {
            $parentCategory = \App\Models\Backend\Billing::where('entity_id', $request->get('parent_id'))->select('category_id')->first();
            $parentCategory = $parentCategory->category_id;
        }
        if ($request->input('billing_from') == 0) {
            \App\Models\Backend\Billing::insert(['entity_id' => $client->id,
                'category_id' => $parentCategory,
                'contact_person' => 'Billing',
                'to_email' => 'billing@superrecords.com.au',
                'address' => '.',
                'notice_period' => '2',
                'full_time_resource' => 1,
                'debtor_followup' => 0,
                'merge_invoice' => 0,
                'payment_id' => 3,
                'ddr_rec' => 0,
                'card_id' => 0,
                'surcharge' => 0,
                'entity_grouptype_id' => 14,
                'state_id' => 2,
                'is_active' => 1,
                'notes' => '',
                'ddr_followup' => 0,
                'created_by' => app('auth')->guard()->id(),
                'created_on' => date('Y-m-d H:i:s')]);
            \App\Http\Controllers\Backend\Billing\BillingServicesController::addBillingForOther($client->id);

        autoAssignAllEntityUser($client->id);
        return createResponse(config('httpResponse.SUCCESS'), 'Entity has been added successfully', ['data' => $client]);
//        } catch (\Exception $e) {
//            app('log')->error("Entity creation failed " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add entity', ['error' => 'Could not add entity']);
//        }
    }

    /**
     * get particular client details
     *
     * @param  int  $id   //Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id) {
        //try {
        $validator = app('validator')->make($request->all(), [
            'tab' => 'required|numeric'], []);

        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $tab = $request->get('tab');
        if ($tab == 1)
            $entity = Entity::with('created_by:id,userfullname,email', "parentEntity:id,trading_name","feedbackAssignee:id,userfullname")->where('id', $id)->find($id);
        else
            $entity = Entity::select(app('db')->raw('JSON_EXTRACT(dynamic_json, "$.' . $tab . '") as dynamic_json'), 'created_by')->with('created_by:id,userfullname,email')->where('id', $id)->find($id);

        if (!isset($entity))
            return createResponse(config('httpResponse.NOT_FOUND'), 'The entity does not exist', ['error' => 'The entity does not exist']);

        $tabs = Entity::entityTab($id);
        $group = \App\Models\Backend\Dynamicgroup::all();
        //send client information
        return createResponse(config('httpResponse.SUCCESS'), 'Entity data', ['data' => $entity, 'tabs' => $tabs, 'group' => $group]);
        /* } catch (\Exception $e) {
          app('log')->error("Entity details api failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get entity.', ['error' => 'Could not get entity.']);
          } */
    }

    /**
     * update entity details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Client id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        //try {
        $entity = Entity::find($id);
        if (!$entity)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Entity does not exist', ['error' => 'The Entity does not exist']);

        $validator = app('validator')->make($request->all(), [
            'tab' => 'required|numeric'], []);

        $requiredValidationFields = array();
        
        if ($request->get('tab') == 1) {
            if ($entity->billing_name != $request->get('billing_name')) {
                $entity->xero_contact_id = '';
            }
            $user = getLoginUserHierarchy();


            $requiredValidationFields['name'] = 'required';
            $requiredValidationFields['billing_name'] = 'required';
            $requiredValidationFields['trading_name'] = 'required';
            $requiredValidationFields['is_parent'] = 'required|in:1,0';
            $requiredValidationFields['parent_id'] = ($requiredValidationFields['is_parent'] == 0) ? 'required' : '';
            $requiredValidationFields['contract_signed_date'] = 'required|date_format:Y-m-d';
            $requiredValidationFields['reviewer_budgeted_unit'] = 'required|numeric|digits_between:1,3';

            $entity->name = $request->get('name');
            if ($user->designation_id != config('constant.SUPERADMIN')) {
                $Dynamicfield = \App\Models\Backend\UserFieldRight::where("user_id", $user->user_id)->where("field_id", "2")->first();
                if ($Dynamicfield->add_edit == 1) {
                    $entity->billing_name = $request->get('billing_name');
                }
            } else {
                $entity->billing_name = $request->get('billing_name');
            }
            $entity->entity_business_type = $request->get('entity_business_type');
            $entity->trading_name = $request->get('trading_name');
            $entity->billing_from = $request->get('billing_from');
            $entity->team_type = $request->get('team_type');
            $entity->feedback_assignee = $request->get('feedback_assignee');
            $entity->is_parent = $request->get('is_parent');
            $entity->parent_id = $request->get('parent_id');
            $entity->related_entity = $request->get('related_entity');
            $entity->related_entity_id = $request->get('related_entity_id');
            $entity->abn_number = $request->get('abn_number');
            $entity->abn_branch_code = $request->get('abn_branch_code');
            $entity->abn_register_date = $request->get('abn_register_date');
            $entity->tfn_number = $request->get('tfn_number');
            $entity->business_type = $request->get('business_type');
            $entity->entity_type = $request->get('entity_type');
            $entity->entity_type_ifother = ($request->get('entity_type') == 3) ? $request->get('entity_type_ifother') : '';
            $entity->bk_doneby = $request->get('bk_doneby');
            $entity->bk_doneby_ifother = ($request->get('bk_doneby') == 3) ? $request->get('bk_doneby_ifother') : '';
            $entity->gst_register = $request->get('gst_register');
            $entity->gst_register_date = $request->get('gst_register_date');
            $entity->bas_frequency = $request->get('bas_frequency');
            $entity->bas_accrualorcash = $request->get('bas_accrualorcash');
            $entity->payg_frequency = $request->get('payg_frequency');
            $entity->financial_institution_updateon_ato = $request->get('financial_institution_updateon_ato');
            $entity->financial_institution_updateon_ato_ifother = ($request->get('financial_institution_updateon_ato')) == 2 ? $request->get('financial_institution_updateon_ato_ifother') : '';
            $entity->statement_delivery_preference = $request->get('statement_delivery_preference');
            $entity->entity_registerfor_fbt = $request->get('entity_registerfor_fbt');
            $entity->entity_registerfor_fueltaxcredit = $request->get('entity_registerfor_fueltaxcredit');
            $entity->group_client_belongsto = $request->get('group_client_belongsto');
            $entity->franchise = $request->get('franchise');
            $entity->website = $request->get('website');
            $entity->dashboard_reason = ($request->get('is_dashboard') == 0) ? $request->get('dashboard_reason') : '';
            $entity->is_dashboard = $request->get('is_dashboard');
            $entity->xero_email_id = $request->has('xero_email_id') ? $request->get('xero_email_id') : '';
            $entity->myob_email_id = $request->has('myob_email_id') ? $request->get('myob_email_id') : '';
            $entity->user_signature = $request->has('user_signature') ? $request->get('user_signature') : '';
            $entity->contract_signed_date = $request->get('contract_signed_date');
            $entity->reviewer_budgeted_unit = $request->get('reviewer_budgeted_unit');
        } else {
            $requestedFieldvalue = \GuzzleHttp\json_decode($request->get('dynamic_json'), true);

            if (!defined('TAB_ID'))
                define('TAB_ID', $request->get('tab'));

            $tab_id = TAB_ID;
            $dynamicFields = \App\Models\Backend\Dynamicfield::where('group_id', $tab_id)->get()->toArray();

            foreach ($dynamicFields as $key => $value) {
                // Check out dynamic fields is mandtory or not
                $validationCriteria = array();
                if ($value['is_mandatory'] == 1 || $value['field_value_type'] == 'N' || $value['field_length'] > 0) {
                    if ($value['is_mandatory'] == 1)
                        $validationCriteria[] = 'required';

                    // Checko fields is numberic
                    if ($value['field_value_type'] == 'N')
                        $validationCriteria[] = 'numeric';

                    // Check fields charecter length
                    if ($value['field_length'] > 0)
                        $validationCriteria[] = 'digits:' . $value['field_length'];

                    //if(!empty($validationCriteria)){
                    $rules = implode('|', $validationCriteria);
                    $requiredValidationFields[$value['field_name']] = $rules;
                    //}
                }
                // Set value for validation
                if (isset($requestedFieldvalue[$value['id']]))
                    $request->request->set($value['field_name'], $requestedFieldvalue[$value['id']]);
            }

            $saveFieldvalue = array();
            foreach ($requestedFieldvalue as $key => $value) {
                if ($value != '')
                    $saveFieldvalue[$key] = $value;
            }

            $existingJson = array();
            if ($entity->dynamic_json != '') {
                $existingJson = (array) \GuzzleHttp\json_decode($entity->dynamic_json, true);
            }
            $existingJson[$tab_id] = (object) $saveFieldvalue;
            $entity->dynamic_json = json_encode($existingJson, true);

            // For software
            if ($tab_id == 2) {
                if ($request->has('software_notes') && $request->get('software_notes') != '')
                    $entity->software_notes = $request->get('software_notes');

                if ($request->has('version_notes') && $request->get('version_notes') != '')
                    $entity->version_notes = $request->get('version_notes');
            }

            // For bookkeeping
            if ($tab_id == 3) {
                if ($request->has('bk_notes') && $request->get('bk_notes') != '')
                    $entity->bk_notes = $request->get('bk_notes');

                if ($request->has('bk_review_notes') && $request->get('bk_review_notes') != '')
                    $entity->bk_review_notes = $request->get('bk_review_notes');
            }

            // For payroll
            if ($tab_id == 4) {
                if ($request->has('payroll_notes') && $request->get('payroll_notes') != '')
                    $entity->payroll_notes = $request->get('payroll_notes');
            }

            // For Accounts Payable
            if ($tab_id == 5) {
                if ($request->has('ap_notes') && $request->get('ap_notes') != '')
                    $entity->ap_notes = $request->get('ap_notes');
            }

            // For Accounts Receivable
            if ($tab_id == 6) {
                if ($request->has('ar_notes') && $request->get('ar_notes') != '')
                    $entity->ar_notes = $request->get('ar_notes');
            }

            // For Debtors Management
            if ($tab_id == 7) {
                if ($request->has('dm_notes') && $request->get('dm_notes') != '')
                    $entity->dm_notes = $request->get('dm_notes');
            }

            // For Taxation
            if ($tab_id == 8) {
                if ($request->has('tax_notes') && $request->get('tax_notes') != '')
                    $entity->tax_notes = $request->get('tax_notes');
            }
        }

        $validator = app('validator')->make($request->all(), $requiredValidationFields, []);
        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), 'Please enter valid input', ['error' => $validator->errors()->first()]);

        $parentCategory = 0;
        $entity->save();
        
        return createResponse(config('httpResponse.SUCCESS'), 'Client has been updated successfully', ['message' => 'Client has been updated successfully']);
//        } catch (\Exception $e) {
//            app('log')->error("Client updation failed : " . $e->getMessage());
//            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update client details.', ['error' => 'Could not update client details.']);
//        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 02, 2018
     * Reason: Destory entity data.
     */

    public function destroy(Request $request, $id) {
        try {
            $client = Client::find($id);
            // Check weather client exists or not
            if (!isset($client))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Client does not exist', ['error' => 'Client does not exist']);

            $client->delete();

            return createResponse(config('httpResponse.SUCCESS'), 'Client has been deleted successfully', ['message' => 'Client has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Client deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete client.', ['error' => 'Could not delete client.']);
        }
    }

    /* Created by: Jayesh Shingrakhiya
     * Created on: July 02, 2018
     * Reason: Checkout trading and legal information duplication.
     */

    public function checkDuplication(Request $request) {
        try {
            $entity = new Entity;
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'numeric',
                'data' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $data = \GuzzleHttp\json_decode($request->get('data'));
            foreach ($data as $key => $value) {
                $entity = $entity->where($key, $value);
                $name = str_replace('_', ' ', $key);
            }

            if ($request->get('entity_id') != 0)
                $entity = $entity->where('id', '!=', $request->get('entity_id'));

            if ($entity->count() == 0)
                return createResponse(config('httpResponse.SUCCESS'), 'Great, this ' . $name . ' is available.', ['message' => 'Great, this ' . $name . ' is available.', 'errorCode' => 0]);
            else
                return createResponse(config('httpResponse.SUCCESS'), 'Regret, this ' . $name . ' is NOT available.', ['message' => 'Regret, this ' . $name . ' is NOT available.', 'errorCode' => 1]);
        } catch (\Exception $e) {
            app('log')->error("Check duplication failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Check duplication failed.', ['error' => 'Check duplication failed.']);
        }
    }

    /*
     * Created By - Jayesh Shingrakhiya
     * Created On - July 02, 2018
     * Common function for save history
     */

    public static function saveHistory($model, $col_name) {
        $yesNo = array('related_entity', 'gst_register');
        $yesNoOther = array('financial_institution_updateon_ato');
        $yesNoNa = array('entity_registerfor_fbt', 'entity_registerfor_fueltaxcredit');
        $Dropdown = array('related_entity_id', 'entity_type', 'bk_doneby', 'bas_frequency', 'bas_accrualorcash', 'payg_frequency', 'statement_delivery_preference', 'entity_registerfor_fueltaxcredit', 'group_client_belongsto', 'franchise');
        $dynamicJson = array('dynamic_json');
        $notSaveHistory = array('software_notes', 'version_notes', 'ap_notes', 'ar_notes', 'bk_notes', 'bk_review_notes', 'dm_notes', 'payroll_notes', 'tax_notes');
        if (!empty($model->getDirty())) {
            $defaultText = 'Blank';
            $tab_id = 1;
            foreach ($model->getDirty() as $key => $newValue) {
                if (in_array($key, $notSaveHistory))
                    goto end;

                $oldValue = $model->getOriginal($key);
                $colname = isset($col_name[$key]) ? $col_name[$key] : $key;

                if (in_array($key, $yesNo)) {
                    $constant = config('constant.yesNo');
                    if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                        $oldValue = $constant[$oldValue];
                    else
                        $oldValue = $defaultText;

                    $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                    $displayName = ucfirst($colname);
                }
                else if (in_array($key, $yesNoOther)) {
                    $constant = config('constant.yesNoOther');
                    if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                        $oldValue = $constant[$oldValue];
                    else
                        $oldValue = $defaultText;

                    $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                    $displayName = ucfirst($colname);
                }
                else if (in_array($key, $yesNoNa)) {
                    $constant = config('constant.yesNoNa');
                    if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                        $oldValue = $constant[$oldValue];
                    else
                        $oldValue = $defaultText;

                    $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                    $displayName = ucfirst($colname);
                }
                else if (in_array($key, $Dropdown)) {
                    if ($key == 'related_entity_id') {
                        $oldExplod = explode(',', $oldValue);
                        $newExplod = explode(',', $newValue);
                        $oldValue = \App\Models\Backend\Entity::select('id', 'name')->whereIn('id', $oldExplod)->get()->pluck('name', 'id')->toArray();
                        $newValue = \App\Models\Backend\Entity::select('id', 'name')->whereIn('id', $newExplod)->get()->pluck('name', 'id')->toArray();
                        if (!empty($oldValue))
                            $oldValue = implode(",", $oldValue);
                        else
                            $oldValue = $defaultText;

                        $newValue = implode(",", $newValue);
                        $displayName = ucfirst($colname);
                    }
                    else if ($key == 'entity_type') {
                        $constant = config('constant.entityType');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                    else if ($key == 'bk_doneby') {
                        $constant = config('constant.bkDoneby');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                    else if ($key == 'bas_frequency') {
                        $constant = config('constant.basFrequency');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                    else if ($key == 'bas_accrualorcash') {
                        $constant = config('constant.basAccrualorcash');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                    else if ($key == 'payg_frequency') {
                        $constant = config('constant.basAccrualorcash');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                    else if ($key == 'statement_delivery_preference') {
                        $constant = config('constant.statementDeliveryPreference');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                    else if ($key == 'group_client_belongsto') {
                        $constant = config('constant.groupClientBelongsto');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                    else if ($key == 'franchise') {
                        $constant = config('constant.franchise');
                        if (isset($constant[$oldValue]) && $constant[$oldValue] != '')
                            $oldValue = $constant[$oldValue];
                        else
                            $oldValue = $defaultText;

                        $newValue = isset($constant[$newValue]) ? $constant[$newValue] : '';
                        $displayName = ucfirst($colname);
                    }
                } else if (in_array($key, $dynamicJson)) {
                    if ($oldValue != '')
                        $oldValue = \GuzzleHttp\json_decode($oldValue, true);
                    else
                        $oldValue = '';

                    $newValue = \GuzzleHttp\json_decode($newValue, true);
                    $response = self::dynamicFieldsHistory($oldValue, $newValue, $defaultText);
                    $diff_col_val = $response['changes'];
                    $tab_id = $response['tab_id'];
                }
                else {
                    $displayName = ucfirst($colname);
                }

                if (isset($displayName) && $displayName != '' && isset($oldValue) && $oldValue != '' && isset($newValue) && $newValue != '')
                    $diff_col_val[$key] = ['display_name' => $displayName, 'old_value' => $oldValue, 'new_value' => $newValue];

                end:
            }
        }

        $response = array();
        if (isset($diff_col_val)) {
            $response['changes'] = $diff_col_val;
            $response['tab_id'] = $tab_id;
        }
        return $response;
    }

    /*
     * Created By - Jayesh Shingrakhiya
     * Created On - July 03, 2018
     * Common function for save dynamic fields history
     */

    public static function dynamicFieldsHistory($oldValue, $newValue, $default) {
        $newChanges = $oldChanges = $finalChanges = array();
        $tab_id = TAB_ID;

        $dynamicFields = \App\Models\Backend\Dynamicfield::select('id', 'field_title')->where('group_id', $tab_id)->get()->pluck('field_title', 'id')->toArray();

        foreach ($newValue as $key => $value) {
            foreach ($value as $fieldId => $fieldValue) {
                if (isset($oldValue[$key][$fieldId]) && $oldValue[$key][$fieldId] != $fieldValue) {
                    $finalChanges[$fieldId]['display_name'] = isset($dynamicFields[$fieldId]) ? $dynamicFields[$fieldId] : '';
                    $finalChanges[$fieldId]['old_value'] = $oldValue[$key][$fieldId];
                    $finalChanges[$fieldId]['new_value'] = $fieldValue;
                } else if (!isset($oldValue[$key][$fieldId])) {
                    $finalChanges[$fieldId]['display_name'] = isset($dynamicFields[$fieldId]) ? $dynamicFields[$fieldId] : '';
                    $finalChanges[$fieldId]['old_value'] = $default;
                    $finalChanges[$fieldId]['new_value'] = $fieldValue;
                }
            }
        }

        if (!empty($oldValue)) {
            foreach ($oldValue as $key => $value) {
                foreach ($value as $fieldId => $fieldValue) {
                    if (!isset($newValue[$key][$fieldId])) {
                        $finalChanges[$fieldId]['display_name'] = isset($dynamicFields[$fieldId]) ? $dynamicFields[$fieldId] : '';
                        $finalChanges[$fieldId]['old_value'] = $fieldValue;
                        $finalChanges[$fieldId]['new_value'] = $default;
                    }
                }
            }
        }
        $response['changes'] = $finalChanges;
        $response['tab_id'] = $tab_id;
        return $response;
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // Entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'entity_id' => 'required|numeric',
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];
            $entity_id = $request->get('entity_id');

            $entityHistory = \App\Models\Backend\EntityAudit::with('modifiedBy:id,userfullname,email')->where('entity_id', $entity_id)->where('tab', $id);
            if ($request->has('search')) {
                $search = $request->get('search');
                $entityHistory = search($entityHistory, $search);
            }
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $entityHistory = $entityHistory->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;

                //count number of total records
                $totalRecords = $entityHistory->count();
                $entityHistory = $entityHistory->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $entityHistory = $entityHistory->get();

                $filteredRecords = count($entityHistory);

                $pager = ['sortBy' => $sortBy,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Entity checklist history detail', ['data' => $entityHistory], $pager);
        } catch (\Exception $e) {
            app('log')->error("Entity checklist history failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while entity checklist history", ['error' => 'Server error.']);
        }
    }

    /**
     * update entity details
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function infoDashboard(Request $request) {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'information.created_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $information = \App\Models\Backend\Information::informationData();

            //check client allocation
            $entity_ids = checkUserClientAllocation(loginUser());
            if (is_array($entity_ids)) {
                $information = $information->whereRaw("e.id IN (" . implode(",", $entity_ids) . ")");
            }

            $information = $information->where("information.stage_id", 1);
            $information = $information->groupBy("information.id");

            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $alias = array("entity_id" => "information", "start_period" => "information", "created_by" => "information");
                $information = search($information, $search, $alias);
            }

            if ($request->has('technical_account_manager')) {
                $tam = $request->get('technical_account_manager');
                $information = $information->whereRaw("JSON_EXTRACT(information.team_json, '$.9') = '" . $tam . "'");
            }

            if ($request->has('team_member')) {
                $tm = $request->get('team_member');
                $information = $information->whereRaw("JSON_EXTRACT(information.team_json, '$.61') = '" . $tm . "'");
            }

            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $information = $information->leftjoin("user as u", "u.id", "information.$sortBy");
                $sortBy = 'userfullname';
            }

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                if ($sortBy != '') {
                    $information = $information->orderBy($sortBy, $sortOrder)->get();
                } else {
                    $information = $information->orderByRaw("information.id desc")->get();
                }
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $information->get()->count();
                if ($sortBy != '') {
                    $information = $information->orderBy($sortBy, $sortOrder)
                            ->skip($skip)
                            ->take($take);
                } else {
                    $information = $information->orderByRaw("information.id desc")
                            ->skip($skip)
                            ->take($take);
                }
                $information = $information->get();

                $filteredRecords = count($information);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Information list.", ['data' => $information], $pager);
        } catch (\Exception $e) {
            app('log')->error("Information listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Information", ['error' => 'Server error.']);
        }
    }

    public function getSubclient(Request $request, $id) {
        try {
            $subclientList = Entity::select("trading_name", "id", "parent_id")
                            ->whereRaw("(parent_id = $id or id =$id)")->where("discontinue_stage", "!=", "2")->get();
            return createResponse(config('httpResponse.SUCCESS'), "SubClient list.", ['data' => $subclientList], '');
        } catch (\Exception $e) {
            app('log')->error("Information listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Sub client", ['error' => 'Server error.']);
        }
    }

    public function checklistDownload(Request $request){
          //try {
        //validate request parameters       
       
        $checklist = \App\Models\Backend\EntityChecklist::getReportData();
        //check client allocation
        $entity_ids = checkUserClientAllocation(loginUser());
        if (is_array($entity_ids))
            $checklist = $checklist->whereRaw("entity_checklist.entity_id IN (" . implode(",", $entity_ids) . ")"); 
        $checklist = $checklist->groupBy("entity_checklist.entity_id","entity_checklist.id");
        $checklist = $checklist->get();
        //For download excel if excel =1
        if ($request->has('excel') && $request->get('excel') == 1) {
            //format data in array 
            $dataChecklist = $checklist->toArray();
            $column = array();
            $column[] = ['Sr.No', 'Parent Trading Name', 'Client Code', 'Trading Name', 'TAM','TL','ATL', 'checklist Name', 'is Alpplicable'];
            
            if (!empty($dataChecklist)) {

                $columnData = array();
                $i = 1;
                foreach ($dataChecklist as $data) {                  
                    $columnData[] = $i;
                    $columnData[] = $data['parent_trading_name'];
                    $columnData[] = $data['code'];
                    $columnData[] = $data['trading_name'];
                    $columnData[] = $data['tam_name'];
                    $columnData[] = $data['tl_name'];
                    $columnData[] = $data['atl_name'];
                    $columnData[] = $data['name'];
                    $columnData[] = ($data['is_applicable'] == 0) ? 'No' :'Yes';
                    $column[] = $columnData;
                    $columnData = array();
                    $i++;
                }
            }
            return exportExcelsheet($column, 'EntityChecklistList', 'xlsx', 'A1:H1');
        }
        /* } catch (\Exception $e) {
          app('log')->error("Feedback listing failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing feedback", ['error' => 'Server error.']);
          } */
    }

}
