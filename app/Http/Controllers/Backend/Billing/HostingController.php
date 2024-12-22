<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HostingController extends Controller {

    /**
     * update invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // hosting user id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'is_setup_cost' => 'required|numeric',
                'setup_cost' => 'required_if:is_setup_cost,1|decimal',
                'basic_rate' => 'required|decimal',
                'permium_rate' => 'required|decimal',
                'auto_invoice' => 'required|in:1,0',
                'recurring_id' => 'required_if:auto_invoice,1',
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $billingServices = \App\Models\Backend\BillingServices::select('id', 'entity_id', 'service_id','inc_in_ff', 'contract_signed_date', 'recurring_id', 'auto_invoice', 'is_setup_cost', 'setup_cost', 'basic_rate', 'permium_rate', 'notes')
                            ->where("entity_id", $id)->where("service_id", "5")->where("is_latest", "1");

            if ($billingServices->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Services is not active', ['error' => 'The Billing Services is not active']);

            $billingServices = $billingServices->first();
            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['recurring_id', 'is_setup_cost', 'setup_cost', 'basic_rate', 'permium_rate', 'notes'], $request);
            $updateData['auto_invoice'] = BillingServicesController::updateAuto($id, $request->input('auto_invoice'));
            $updateData['inc_in_ff'] =1;
            //update recurring in recurring table also
            if ($request->has('recurring_id')) {
                BillingServicesController::updateRecurring($request->input('recurring_id'), $id, 5);
            }

            $invoicesCount = \App\Models\Backend\Invoice::where("billing_id", $billingServices->id)->count();

            if ($billingServices->is_updated == 0 || $invoicesCount == 0) {

                $updateData['is_updated'] = 1;
                $billingServices->update($updateData);
            } else {
                $array_diff = array_diff_assoc($updateData, $billingServices->getOriginal());
                //showArray($array_diff);exit;
                if (!empty($array_diff)) {
                    //update the details
                    $updateData['is_latest'] = 0;
                    $updateData['is_updated'] = 1;
                    $updateData['is_active'] = 1;
                    $billingServices->update($updateData);

                    $newBilling = \App\Models\Backend\BillingServices::create([
                                'service_id' => 5,
                                'contract_signed_date' => $billingServices->contract_signed_date,
                                'entity_id' => $billingServices->entity_id,
                                'recurring_id' => $request->input('recurring_id'),
                                'auto_invoice' => $request->input('auto_invoice'),
                                'is_setup_cost' => $request->input('is_setup_cost'),
                                'setup_cost' => $request->input('setup_cost'),
                                'inc_in_ff' => 1,
                                'basic_rate' => $request->input('basic_rate'),
                                'permium_rate' => $request->input('permium_rate'),
                                'notes' => $request->has('notes') ? $request->input('notes') : $billingServices->notes,
                                'is_latest' => 1,
                                'is_updated' => 1,
                                'is_active' => 1,
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => loginUser()
                    ]);
                }
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Hosting Billing Info has been updated successfully', ['message' => 'Hosting Billing Info has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Hosting Billing Info updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Hosting details.', ['error' => 'Could not update Hosting Billing Info.']);
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
            $billingServices = \App\Models\Backend\BillingServices::select('recurring_id', 'auto_invoice', 'is_setup_cost', 'setup_cost', 'basic_rate', 'permium_rate', 'notes', 'is_updated')
                    ->where("entity_id", $id)
                    ->where("service_id", "5")
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
     * Get Hosting User detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function indexUser(Request $request, $entityId) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0'
                    ], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            // define soring parameters
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'billing_hosting_user.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $hostingUser = \App\Models\Backend\BillingHostingUser::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')
                    ->where("entity_id", $entityId);

            // Check if all records are requested
            if ($request->has('records') && $request->get('records') == 'all') {
                $hostingUser = $hostingUser->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $hostingUser->count();

                $hostingUser = $hostingUser->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $hostingUser = $hostingUser->get();

                $filteredRecords = count($hostingUser);

                $pager = [
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Hosting User list.", ['data' => $hostingUser], $pager);
        } catch (\Exception $e) {
            app('log')->error("Hosting User listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Hosting User", ['error' => 'Server error.']);
        }
    }

    /**
     * Store hosting user details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function storeUser(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'username' => 'required|unique:billing_hosting_user,username',
                'rate' => 'required',
                'plan_type' => 'required|in:P,B,S',
                'is_active' => 'required|in:0,1',
                'activedate' => 'required|date',
                'inactivedate' => 'required_if:is_active,0|date'
                    ], ['username.unique' => "Hosting User Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store hosting user details
            $loginUser = loginUser();
            $hostingUser = \App\Models\Backend\BillingHostingUser::create([
                        'entity_id' => $id,
                        'username' => $request->input('username'),
                        'rate' => $request->input('rate'),
                        'plan_type' => $request->input('plan_type'),
                        'activedate' => date("Y-m-d", strtotime($request->input('activedate'))),
                        'inactivedate' => $request->has('inactivedate') ? date("Y-m-d", strtotime($request->input('inactivedate'))) : '0000-00-00',
                        'is_active' => $request->input('is_active'),
                        'notes' => $request->input('notes'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Hosting User has been added successfully', ['data' => $hostingUser]);
        } catch (\Exception $e) {
            app('log')->error("Hosting User creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add hosting user', ['error' => 'Could not add hosting user']);
        }
    }

    /**
     * update Hosting User details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // hosting user id
     * @return Illuminate\Http\JsonResponse
     */
    public function updateUser(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'username' => 'unique:billing_hosting_user,username,' . $id,
                'is_active' => 'in:0,1',
                'inactivedate' => 'required_if:is_active,0|date'
                    ], ['hosting user_name.unique' => "Hosting User Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $hostingUser = \App\Models\Backend\BillingHostingUser::find($id);

            if (!$hostingUser)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Hosting User does not exist', ['error' => 'The Hosting User does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['username', 'rate', 'plan_type', 'is_active', 'notes'], $request);

            if ($request->has('activedate'))
                $updateData['activedate'] = date('Y-m-d', strtotime($request->input('activedate')));
            if ($request->has('inactivedate'))
                $updateData['inactivedate'] = date('Y-m-d', strtotime($request->input('inactivedate')));

            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $hostingUser->update($updateData);

            return createResponse(config('httpResponse.SUCCESS'), 'Hosting User has been updated successfully', ['message' => 'Hosting User has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Hosting User updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update hosting user details.', ['error' => 'Could not update hosting user details.']);
        }
    }

    /**
     * get particular hosting user details
     *
     * @param  int  $id   //hosting user id
     * @return Illuminate\Http\JsonResponse
     */
    public function showUser($id) {
        try {
            $hostingUser = \App\Models\Backend\BillingHostingUser::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')->find($id);

            if (!isset($hostingUser))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The hosting user does not exist', ['error' => 'The hosting user does not exist']);

            //send hosting user information
            return createResponse(config('httpResponse.SUCCESS'), 'Hosting User data', ['data' => $hostingUser]);
        } catch (\Exception $e) {
            app('log')->error("Hosting User details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get hosting user.', ['error' => 'Could not get hosting user.']);
        }
    }

}

?>