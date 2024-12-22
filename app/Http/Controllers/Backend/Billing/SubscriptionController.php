<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SubscriptionController extends Controller {

    /**
     * update invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // software id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'fixed_fee' => 'required|decimal',
                'ff_start_date' => 'required|date',
                'auto_invoice' => 'required|in:1,0',
                'frequency_id' => 'required|numeric',
                'software_id' => 'required|numeric',
                'plan_id' => 'required|numeric',
                'discount' => 'required|decimal',
                'standard_fee' => 'required|decimal',
                'recurring_id' => 'required_if:auto_invoice,1',
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $billingServices = \App\Models\Backend\BillingServices::select('id', 'entity_id', 'service_id', 'inc_in_ff', 'contract_signed_date', 'recurring_id', 'auto_invoice', 'frequency_id', 'inc_in_ff', 'fixed_fee', 'ff_start_date', 'software_id', 'plan_id', 'discount', 'standard_fee', 'notes')
                            ->where("entity_id", $id)->where("service_id", "7")->where("is_latest", "1");

            if ($billingServices->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Services is not active', ['error' => 'The Billing Services is not active']);

            $billingServices = $billingServices->first();

            $updateData = array();
            // Filter the fields which need to be updated

            $updateData = filterFields(['recurring_id', 'auto_invoice', 'frequency_id', 'software_id',
                'plan_id', 'discount', 'standard_fee', 'fixed_fee', 'notes'], $request);
            $updateData['inc_in_ff'] = 1;
            $updateData['ff_start_date'] = date('Y-m-d', strtotime($request->input('ff_start_date')));
            $updateData['auto_invoice'] = BillingServicesController::updateAuto($id, $request->input('auto_invoice'));

            //update recurring in recurring table also
            if ($request->has('recurring_id')) {
                BillingServicesController::updateRecurring($request->input('recurring_id'), $id, 7);
            }

            //check if invoice generate or not
            $invoicesCount = \App\Models\Backend\Invoice::where("billing_id", $billingServices->id)->count();

            if ($billingServices->is_updated == 0 || $invoicesCount == 0) {
                $updateData['is_updated'] = 1;
                $billingServices->update($updateData);
            } else {
                $array_diff = array_diff_assoc($updateData, $billingServices->getOriginal());

                //showArray($array_diff);exit;
                if (!empty($array_diff)) {
                    //update the details
                    $updateData['is_latest'] = '0';
                    $updateData['is_updated'] = 1;
                    $updateData['is_active'] = 1;
                    $billingServices->update($updateData);

                    $newBilling = \App\Models\Backend\BillingServices::create([
                                'service_id' => 7,
                                'contract_signed_date' => $billingServices->contract_signed_date,
                                'entity_id' => $billingServices->entity_id,
                                'recurring_id' => $request->input('recurring_id'),
                                'auto_invoice' => $request->input('auto_invoice'),
                                'frequency_id' => $request->input('frequency_id'),
                                'software_id' => $request->input('software_id'),
                                'inc_in_ff' => 1,
                                'plan_id' => $request->input('plan_id'),
                                'discount' => $request->input('discount'),
                                'standard_fee' => $request->input('standard_fee'),
                                'fixed_fee' => $request->input('fixed_fee'),
                                'ff_start_date' => date("Y-m-d", strtotime($request->input('ff_start_date'))),
                                'notes' => $request->has('notes') ? $request->input('notes') : $billingServices->notes,
                                'is_latest' => 1,
                                'is_updated' => 1,
                                'is_active' => 1,
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => loginUser()
                    ]);
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Subscription Billing Info has been updated successfully', ['message' => 'Subscription Billing Info has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Subscription Billing Info updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Subscription details.', ['error' => 'Could not update Subscription Billing Info.']);
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
            $billingServices = \App\Models\Backend\BillingServices::select('recurring_id', 'auto_invoice', 'frequency_id', 'software_id', 'plan_id', 'discount', 'standard_fee', 'fixed_fee', 'ff_start_date', 'notes')
                    ->where("entity_id", $id)
                    ->where("service_id", "7")
                    ->where("is_latest", "1");

            if ($billingServices->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Billing Services does not exist', ['error' => 'The Billing Services does not exist']);

            //send crmnotes information
            return createResponse(config('httpResponse.SUCCESS'), 'Billing Basic data', ['data' => $billingServices->first()]);
        } catch (\Exception $e) {
            app('log')->error("Billing Services details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Billing Services.', ['error' => 'Could not get Billing Services.']);
        }
    }

    /**
     * Store software details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function softwareStore(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'software_plan' => 'required|unique:billing_subscription_plan,software_plan',
                'is_active' => 'required|in:0,1',
                    ], ['software.unique' => "software Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store software details
            $loginUser = loginUser();
            $software = \App\Models\Backend\BillingSubscriptionSoftware::create([
                        'software_plan' => $request->input('software_plan'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Software has been added successfully', ['data' => $software]);
        } catch (\Exception $e) {
            app('log')->error("Software creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Software', ['error' => 'Could not add Software']);
        }
    }

    /**
     * Store software details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function planStore(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'parent_id' => 'required|numeric',
                'software_plan' => 'required',
                'amount' => 'required',
                'is_active' => 'required|in:0,1',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store software details
            $loginUser = loginUser();
            $software = \App\Models\Backend\BillingSubscriptionSoftware::create([
                        'parent_id' => $request->input('parent_id'),
                        'software_plan' => $request->input('software_plan'),
                        'amount' => $request->input('amount'),
                        'is_active' => $request->input('is_active'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Plan has been added successfully', ['data' => $software]);
        } catch (\Exception $e) {
            app('log')->error("Plan creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Plan', ['error' => 'Could not add Plan']);
        }
    }

    /**
     * update Software details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // software id
     * @return Illuminate\Http\JsonResponse
     */
    public function updateSoftware(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'software_plan' => 'unique:billing_subscription_plan,software_plan,' . $id,
                'is_active' => 'in:0,1',
                    ], ['software.unique' => "Software Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $software = \App\Models\Backend\BillingSubscriptionSoftware::find($id);

            if (!$software)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Software does not exist', ['error' => 'The Software does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['software_plan', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $software->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Software has been updated successfully', ['message' => 'Software has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Software updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update software details.', ['error' => 'Could not update software details.']);
        }
    }

    /**
     * update Software details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // software id
     * @return Illuminate\Http\JsonResponse
     */
    public function updatePlan(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'software_plan' => 'unique:billing_subscription_plan,software_plan,' . $id,
                'is_active' => 'in:0,1',
                    ], ['plan.unique' => "Plan Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $plan = \App\Models\Backend\BillingSubscriptionSoftware::find($id);

            if (!$plan)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Software does not exist', ['error' => 'The Plan does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['parent_id', 'software_plan', 'amount', 'is_active'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details

            // if plan amount change then change all billing entity 
            $amt = $request->input('amount');
            if ($plan->amount != $amt) {
                $subscription = \App\Models\Backend\BillingServices::where("service_id", "7")
                                ->where("is_latest", "1")
                                ->where("is_active", "1")
                                ->where("software_id", $plan->parent_id)->where("plan_id", $id)->get();
                foreach ($subscription as $row) {
                    if ($amt != $row->standard_fee) {
                        $ff = $amt;
                        if ($row->discount != '' || $row->discount != '0.00') {
                            $ff = number_format(($amt - (($amt * $row->discount) / 100)), 2);
                        }
                        \App\Models\Backend\BillingServices::where("id", $row->id)->update(["is_latest" => 0]);
                        $newBilling = \App\Models\Backend\BillingServices::create([
                                    'service_id' => 7,
                                    'contract_signed_date' => $row->contract_signed_date,
                                    'entity_id' => $row->entity_id,
                                    'recurring_id' => $row->recurring_id,
                                    'auto_invoice' => $row->auto_invoice,
                                    'frequency_id' => $row->frequency_id,
                                    'software_id' => $row->software_id,
                                    'inc_in_ff' => 1,
                                    'plan_id' => $row->plan_id,
                                    'discount' => $row->discount,
                                    'standard_fee' => $amt,
                                    'fixed_fee' => $ff,
                                    'ff_start_date' => $row->ff_start_date,
                                    'notes' => $row->notes,
                                    'is_latest' => 1,
                                    'is_updated' => 1,
                                    'is_active' => 1,
                                    'created_on' => date('Y-m-d H:i:s'),
                                    'created_by' => $loginUser
                        ]);
                    }
                }
            }
            $plan->update($updateData);
            return createResponse(config('httpResponse.SUCCESS'), 'Plan has been updated successfully', ['message' => 'Plan has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Plan updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Plan details.', ['error' => 'Could not update Plan details.']);
        }
    }

    /**
     * get particular software details
     *
     * @param  int  $id   //software id
     * @return Illuminate\Http\JsonResponse
     */
    public function softwareList(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $software = \App\Models\Backend\BillingSubscriptionSoftware::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')
                    ->where("parent_id", "=", "0");

            if ($request->has('search')) {
                $search = $request->get('search');
                $software = search($software, $search);
            }
            if ($request->has('records') && $request->get('records') == 'all') {
                $software = $software->orderBy($sortBy, $sortOrder)->get();
            } else {
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                $totalRecords = $software->get()->count();

                $software = $software->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $software = $software->get();

                $filteredRecords = count($software);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            //send software information
            return createResponse(config('httpResponse.SUCCESS'), 'software List', ['data' => $software], $pager);
        } catch (\Exception $e) {
            app('log')->error("software details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get software.', ['error' => 'Could not get software.']);
        }
    }

    /**
     * get particular software details
     *
     * @param  int  $id   //software id
     * @return Illuminate\Http\JsonResponse
     */
    public function planList(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $plan = \App\Models\Backend\BillingSubscriptionSoftware::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id', 'parentId:software_plan,id')
                    ->where("parent_id", "!=", "0");

            if ($request->has('search')) {
                $search = $request->get('search');
                $plan = search($plan, $search);
            }
            if ($request->has('records') && $request->get('records') == 'all') {
                $plan = $plan->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                $totalRecords = $plan->get()->count();

                $plan = $plan->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $plan = $plan->get();

                $filteredRecords = count($plan);


                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }
            //send software information
            return createResponse(config('httpResponse.SUCCESS'), 'plan List', ['data' => $plan], $pager);
        } catch (\Exception $e) {
            app('log')->error("plan details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get plan.', ['error' => 'Could not get plan.']);
        }
    }

    /**
     * get particular software details
     *
     * @param  int  $id   //software id
     * @return Illuminate\Http\JsonResponse
     */
    public function softwareShow($id) {
        try {
            $software = \App\Models\Backend\BillingSubscriptionSoftware::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id', 'parentId:software_plan,id')->find($id);

            if (!isset($software))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The software plan does not exist', ['error' => 'The software plan does not exist']);

            //send software information
            return createResponse(config('httpResponse.SUCCESS'), 'Software Plan data', ['data' => $software]);
        } catch (\Exception $e) {
            app('log')->error("Software plan details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get software plan.', ['error' => 'Could not get software plan.']);
        }
    }

}

?>