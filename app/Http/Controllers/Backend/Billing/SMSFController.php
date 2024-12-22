<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SMSFController extends Controller {

    /**
     * update invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // bank id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'befree_invoice' => 'required|in:1,0',
                'frequency_id' => 'required|numeric',
                'ff_start_date' => 'required|date',
                'fixed_fee' => 'required|decimal',
                'monthly_amount' => 'required|decimal',
                'balance_amount' => 'required|decimal',
                'audit_fee_inc' => 'required|in:1,0',
                'audit_fee' => 'required_if:audit_fee_inc,0|decimal',
                'auto_invoice' => 'required|in:1,0',
                'recurring_id' => 'required_if:auto_invoice,1',
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $billingServices = \App\Models\Backend\BillingServices::select('id', 'entity_id', 'service_id', 'contract_signed_date','inc_in_ff', 'recurring_id', 'auto_invoice', 'frequency_id', 'befree_invoice', 'monthly_amount', 'balance_amount', 'audit_fee', 'audit_fee_inc', 'fixed_fee', 'ff_start_date', 'notes')
                            ->where("entity_id", $id)->where("service_id", "4")->where("is_latest", "1");

            if ($billingServices->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Services is not active', ['error' => 'The Billing Services is not active']);

            $billingServices = $billingServices->first();
            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['recurring_id', 'frequency_id', 'befree_invoice', 'monthly_amount',
                'balance_amount', 'fixed_fee', 'audit_fee', 'audit_fee_inc', 'notes'], $request);
            $updateData['inc_in_ff'] = 1;
            $updateData['ff_start_date'] = date('Y-m-d', strtotime($request->input('ff_start_date')));
            $updateData['auto_invoice'] = BillingServicesController::updateAuto($id, $request->input('auto_invoice'));

            //update recurring in recurring table also
            if ($request->has('recurring_id')) {
                BillingServicesController::updateRecurring($request->input('recurring_id'), $id, 4);
            }

            // first time value insert we will not inset data again we will add value only
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
                                'service_id' => 4,
                                'contract_signed_date' => $billingServices->contract_signed_date,
                                'entity_id' => $billingServices->entity_id,
                                'recurring_id' => $request->input('recurring_id'),
                                'auto_invoice' => $request->input('auto_invoice'),
                                'frequency_id' => $request->input('frequency_id'),
                                'befree_invoice' => $request->input('befree_invoice'),
                                'inc_in_ff' => 1,
                                'fixed_fee' => $request->input('fixed_fee'),
                                'monthly_amount' => $request->input('monthly_amount'),
                                'balance_amount' => $request->input('balance_amount'),
                                'audit_fee_inc' => $request->input('audit_fee_inc'),
                                'audit_fee' => ($request->input('audit_fee_inc') == 0) ? $request->input('audit_fee') : '0.00',
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
            return createResponse(config('httpResponse.SUCCESS'), 'SMSF Billing Info has been updated successfully', ['message' => 'SMSF Billing Info has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("SMSF Billing Info updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update SMSF details.', ['error' => 'Could not update SMSF Billing Info.']);
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
            $billingServices = \App\Models\Backend\BillingServices::select('recurring_id', 'auto_invoice', 'frequency_id', 'befree_invoice', 'monthly_amount', 'balance_amount', 'fixed_fee', 'audit_fee', 'audit_fee_inc', 'notes', 'ff_start_date')->where("entity_id", $id)
                    ->where("service_id", "4")
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

}

?>