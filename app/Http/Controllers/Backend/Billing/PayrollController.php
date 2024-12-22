<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PayrollController extends Controller {

    /**
     * update payroll details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // entity id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'inc_in_ff' => 'required|numeric|in:1,0',
                'ff_rph' => 'required|decimal',
                'fixed_fee' => 'required_if:inc_in_ff,1|decimal',
                'recurring_id' => 'required_if:auto_invoice,1',
                'auto_invoice' => 'required|numeric',
                'frequency_id' => 'required|numeric',
                'payroll_frequency_id' => 'required_if:inc_in_ff,1|numeric',
                'calc_id' => 'required|numeric',
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $billingServices = \App\Models\Backend\BillingServices::select('id', 'entity_id', 'service_id', 'contract_signed_date', 'is_updated', 'recurring_id', 'auto_invoice', 'frequency_id', 'inc_in_ff', 'ff_rph', 'fixed_fee', 'payroll_frequency_id', 'calc_id', 'notes')
                            ->where("entity_id", $id)->where("service_id", "2")->where("is_latest", "1");

            if ($billingServices->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Services is not active', ['error' => 'The Billing Services is not active']);

            $billingServices = $billingServices->first();

            $updateData = array();
            // Filter the fields which need to be updated            
            $updateData = filterFields(['recurring_id', 'frequency_id', 'payroll_frequency_id', 'inc_in_ff', 'calc_id', 'ff_rph',
                'notes'], $request);
            $updateData['fixed_fee'] = ($request->input('inc_in_ff') == 1) ? $request->input('fixed_fee') : '0.00';
            $updateData['auto_invoice'] = BillingServicesController::updateAuto($id, $request->input('auto_invoice'));
//update recurring in recurring table also
            if ($request->has('recurring_id')) {
                BillingServicesController::updateRecurring($request->input('recurring_id'), $id, 2);
            }
            $masterId = array(1, 2);

            $invoicesCount = \App\Models\Backend\Invoice::where("billing_id", $billingServices->id)->count();

            if ($billingServices->is_updated == 0 || $invoicesCount == 0) {
                $updateData['is_updated'] = 1;
                $array_diff = array_diff_assoc($updateData, $billingServices->getOriginal());
                //showArray($array_diff);
                if ($billingServices->is_updated == 0) {
                    \App\Models\Backend\BillingServicesSubactivity::addSubactivity($billingServices->entity_id, 2, $billingServices->id, $masterId, $request->input('calc_id'), 0);
                } /*else if (!empty($array_diff)) {
                   // \App\Models\Backend\BillingServicesSubactivity::addSubactivity($billingServices->entity_id, 2, $billingServices->id, $masterId, $request->input('calc_id'), $billingServices->id);
                }*/
                $billingServices->update($updateData);
            } else {
                $array_diff = array_diff_assoc($updateData, $billingServices->getOriginal());
                $bkService = $diff = 0;
                if (!empty($array_diff)) {

                    //update the details
                    $updateData['is_latest'] = 0;
                    $updateData['is_updated'] = 1;
                    $updateData['is_active'] = 1;
                    $billingServices->update($updateData);

                    $newBilling = \App\Models\Backend\BillingServices::create([
                                'service_id' => 2,
                                'contract_signed_date' => $billingServices->contract_signed_date,
                                'entity_id' => $billingServices->entity_id,
                                'recurring_id' => $request->input('recurring_id'),
                                'auto_invoice' => $request->input('auto_invoice'),
                                'frequency_id' => $request->input('frequency_id'),
                                'payroll_frequency_id' => $request->input('payroll_frequency_id'),
                                'inc_in_ff' => $request->input('inc_in_ff'),
                                'calc_id' => $request->input('calc_id'),
                                'ff_rph' => $request->input('ff_rph'),
                                'fixed_fee' => ($request->input('inc_in_ff') == 1) ? $request->input('fixed_fee') : 0,
                                'notes' => $request->has('notes') ? $request->input('notes') : $billingServices->notes,
                                'is_latest' => 1,
                                'is_updated' => 1,
                                'is_active' => 1,
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => loginUser()
                    ]);

                    // update billing subactivity value
                    \App\Models\Backend\BillingServicesSubactivity::addSubactivity($billingServices->entity_id, 2, $newBilling->id, $masterId, 0, $billingServices->id);
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Payroll Billing Info has been updated successfully', ['message' => 'Payroll Billing Info has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Bk Billing Info updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update bank details.', ['error' => 'Could not update Bk Billing Info.']);
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
            $billingServices = \App\Models\Backend\BillingServices::
                    select('recurring_id', 'auto_invoice', 'frequency_id', 'payroll_frequency_id', 'inc_in_ff', 'calc_id', 'ff_rph', 'fixed_fee', 'notes', 'is_updated')->where("entity_id", $id)
                    ->where("service_id", "2")
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
     * Get Payroll Calc detail
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function calcindex(Request $request) {
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
            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'billing_payroll_calc.id';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'asc';
            $pager = [];

            $calc = \App\Models\Backend\BillingPayrollCalc::with('createdBy:userfullname as created_by,id', 'modifiedBy:userfullname as modified_by,id')
                    ->join("billing_payroll_subactivity as bs", "bs.calc_id", "billing_payroll_calc.id")
                    ->select("billing_payroll_calc.*");
            //for search
            if ($request->has('search')) {
                $search = $request->get('search');
                $calc = search($calc, $search);
            }
            // for relation ship sorting
            if ($sortBy == 'created_by' || $sortBy == 'modified_by') {
                $calc = $calc->leftjoin("user as u", "u.id", "billing_payroll_calc.$sortBy");
                $sortBy = 'userfullname';
            }
            $calc = $calc->groupBy("billing_payroll_calc.id");
            // Check if all records are requested 
            if ($request->has('records') && $request->get('records') == 'all') {
                $calc = $calc->orderBy($sortBy, $sortOrder)->get();
            } else { // Else return paginated records
                // Define pager parameters
                $pageNumber = ($request->has('pageNumber')) ? $request->get('pageNumber') : config('pager.pageNumber');
                $recordsPerPage = ($request->has('recordsPerPage')) ? $request->get('recordsPerPage') : config('pager.recordsPerPage');
                $skip = ($pageNumber - 1) * $recordsPerPage;
                $take = $recordsPerPage;
                //count number of total records
                $totalRecords = $calc->get()->count();

                $calc = $calc->orderBy($sortBy, $sortOrder)
                        ->skip($skip)
                        ->take($take);

                $calc = $calc->get();

                $filteredRecords = count($calc);

                $pager = ['sortBy' => $sortByName,
                    'sortOrder' => $sortOrder,
                    'pageNumber' => $pageNumber,
                    'recordsPerPage' => $recordsPerPage,
                    'totalRecords' => $totalRecords,
                    'filteredRecords' => $filteredRecords];
            }

            return createResponse(config('httpResponse.SUCCESS'), "Payroll Calc list.", ['data' => $calc], $pager);
        } catch (\Exception $e) {
            app('log')->error("Payroll Calc listing failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), "Error while listing Payroll Calc", ['error' => 'Server error.']);
        }
    }

    /**
     * Store payroll calc details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function calcstore(Request $request) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'name' => 'required|unique:billing_payroll_calc',
                    ], ['name.unique' => "Payroll Calc Name has already been taken"]);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store payroll calc details
            $loginUser = loginUser();
            $calc = \App\Models\Backend\BillingPayrollCalc::create([
                        'name' => $request->input('name'),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser,
                        'modified_on' => date('Y-m-d H:i:s'),
                        'modified_by' => $loginUser
            ]);

            // Add payroll subactivity
            $subactivity = \App\Models\Backend\SubActivity::whereIn("master_id", [1, 2])->where("visible", 1)->get();
            foreach ($subactivity as $row) {
                $billing_subactivity[] = array('calc_id' => $calc->id,
                    'subactivity_code' => $row->subactivity_code,
                    'inc_in_ff' => '0',
                    'frequency_id' => '0',
                    'fixed_fee' => '0',
                    'price' => '0',
                    'fixed_value' => '0',
                    'no_of_value' => '0',
                    'created_on' => date("Y-m-d H:i:s"),
                    'created_by' => loginUser());
            }
            \App\Models\Backend\BillingPayrollSubactivity::insert($billing_subactivity);

            return createResponse(config('httpResponse.SUCCESS'), 'Payroll Calc has been added successfully', ['data' => $calc]);
        } catch (\Exception $e) {
            app('log')->error("Payroll Calc creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add payroll calc', ['error' => 'Could not add payroll calc']);
        }
    }

    /**
     * update Payroll Calc details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // payroll calc id
     * @return Illuminate\Http\JsonResponse
     */
    public function calcupdate(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'name' => 'unique:billing_payroll_calc,name,' . $id,
                'calc_array' => 'array',
                    ], ['name.unique' => "Payroll Calc Name has already been taken"]);

            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $calc = \App\Models\Backend\BillingPayrollCalc::find($id);

            if (!$calc)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Payroll Calc does not exist', ['error' => 'The Payroll Calc does not exist']);

            $updateData = array();
            // Filter the fields which need to be updated
            $loginUser = loginUser();
            $updateData = filterFields(['name'], $request);
            $updateData['modified_on'] = date('Y-m-d H:i:s');
            $updateData['modified_by'] = $loginUser;
            //update the details
            $calc->update($updateData);

            $subactivityDetail = $request->get('calc_array');
            foreach ($subactivityDetail as $row) {
                $subactivityData = [
                    'inc_in_ff' => isset($row['inc_in_ff']) ? $row['inc_in_ff'] : '0',
                    'frequency_id' => isset($row['frequency_id']) ? $row['frequency_id'] : '0',
                    'fixed_fee' => isset($row['fixed_fee']) ? $row['fixed_fee'] : '0',
                    'price' => isset($row['price']) ? $row['price'] : '0',
                    'fixed_value' => isset($row['fixed_value']) ? $row['fixed_value'] : '0',
                    'no_of_value' => isset($row['no_of_value']) ? $row['no_of_value'] : '0',
                    'created_on' => date("Y-m-d H:i:s"),
                    'created_by' => loginUser()];
                if ($row['id'] == '' || $row['id'] == null) {
                    $subactivityData = [
                        'calc_id' => $id,
                        'subactivity_code' => $row['subactivity_code'],
                        'inc_in_ff' => isset($row['inc_in_ff']) ? $row['inc_in_ff'] : '0',
                        'frequency_id' => isset($row['frequency_id']) ? $row['frequency_id'] : '0',
                        'fixed_fee' => isset($row['fixed_fee']) ? $row['fixed_fee'] : '0',
                        'price' => isset($row['price']) ? $row['price'] : '0',
                        'fixed_value' => isset($row['fixed_value']) ? $row['fixed_value'] : '0',
                        'no_of_value' => isset($row['no_of_value']) ? $row['no_of_value'] : '0',
                        'created_on' => date("Y-m-d H:i:s"),
                        'created_by' => loginUser()];
                    \App\Models\Backend\BillingPayrollSubactivity::insert($subactivityData);
                } else {
                    \App\Models\Backend\BillingPayrollSubactivity::where("id", $row['id'])->update($subactivityData);
                }
            }
            return createResponse(config('httpResponse.SUCCESS'), 'Payroll Calc has been updated successfully', ['message' => 'Payroll Calc has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Payroll Calc updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update payroll calc details.', ['error' => 'Could not update payroll calc details.']);
        }
    }

    /**
     * get particular payroll calc details
     *
     * @param  int  $id   //calc id
     * @return Illuminate\Http\JsonResponse
     */
    public function calcShow($id) {
        //try {
        $payrollCalc = \App\Models\Backend\BillingPayrollCalc::find($id);

        /* $payrollSubactivity = \App\Models\Backend\BillingPayrollSubactivity::
          leftjoin("subactivity as s", "s.subactivity_code", "billing_payroll_subactivity.subactivity_code")
          ->leftjoin("master_activity as m", "m.id", "s.master_id")
          ->select("m.name as masterName", "s.subactivity_full_name", "s.is_no_of_employee", "s.is_inc_in_ff", "s.is_frequency", "s.is_price", "billing_payroll_subactivity.*")
          ->where("calc_id", $id)->get(); */
        $payrollSubactivity = \App\Models\Backend\SubActivity::
                leftjoin("billing_payroll_subactivity as ps", function($query) use($id) {
                    $query->on('ps.subactivity_code', '=', 'subactivity.subactivity_code');
                    $query->on('ps.calc_id', '=', app('db')->raw($id));
                })
                ->leftjoin("master_activity as m", "m.id", "subactivity.master_id")
                ->select("m.name as masterName", "subactivity.subactivity_full_name", "subactivity.subactivity_code", "subactivity.is_no_of_employee", "subactivity.is_inc_in_ff", "subactivity.is_frequency", "subactivity.is_price", "ps.id", "ps.frequency_id", "ps.inc_in_ff", "ps.fixed_fee", "ps.price", "ps.fixed_value", "ps.no_of_value")
                ->where("subactivity.visible", "1")
                ->where("subactivity.chargeable", "1")
                ->whereIn("subactivity.master_id", [1, 2])
                ->get();
        $subactivityArray = array();
        foreach ($payrollSubactivity as $row) {
            $subactivityArray[$row->masterName][] = array(
                "id" => $row->id,
                "subactivity_code" => $row->subactivity_code,
                "subactivity" => $row->subactivity_full_name,
                "frequency_id" => $row->frequency_id,
                "inc_in_ff" => $row->inc_in_ff,
                "fixed_fee" => $row->fixed_fee,
                "price" => $row->price,
                "fixed_value" => $row->fixed_value,
                "no_of_value" => $row->no_of_value,
                "is_no_of_employee" => $row->is_no_of_employee,
                "is_inc_in_ff" => $row->is_inc_in_ff,
                "is_frequency" => $row->is_frequency,
                "is_price" => $row->is_price
            );
        }
        $payrollCalc['subactivity'] = $subactivityArray;
        if (!$payrollCalc)
            return createResponse(config('httpResponse.NOT_FOUND'), 'The Payroll Calc does not exist', ['error' => 'The Payroll Calc does not exist']);

        //send software information
        return createResponse(config('httpResponse.SUCCESS'), 'Payroll Calc data', ['data' => $payrollCalc]);
        /* } catch (\Exception $e) {
          app('log')->error("Payroll Calc details api failed : " . $e->getMessage());

          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Payroll Calc.', ['error' => 'Could not get Payroll Calc.']);
          } */
    }

}

?>