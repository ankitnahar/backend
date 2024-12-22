<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TaxController extends Controller {

    /**
     * update invoice details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $id   // turnover id
     * @return Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id) {
        try {
            $validator = app('validator')->make($request->all(), [
                'ff_rph' => 'required|decimal'
                    ], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
            $billingServices = \App\Models\Backend\BillingServices::select('id', 'entity_id', 'service_id', 'contract_signed_date', 'ff_rph', 'notes')
                            ->where("entity_id", $id)->where("service_id", "6")->where("is_latest", "1");

            if ($billingServices->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Services is not active', ['error' => 'The Billing Services is not active']);

            $billingServices = $billingServices->first();
            $updateData = array();
            // Filter the fields which need to be updated
            $updateData = filterFields(['ff_rph', 'notes'], $request);

            //check invoice generate or not
            $invoicesCount = \App\Models\Backend\Invoice::where("billing_id", $billingServices->id)->count();

            if ($billingServices->is_updated == 0 || $invoicesCount == 0) {

                $updateData['is_updated'] = 1;
                $billingServices->update($updateData);
            } else {
                $array_diff = array_diff_assoc($updateData, $billingServices->getOriginal());

                //showArray($array_diff);exit;
                $bkService = $diff = 0;
                if (!empty($array_diff)) {
                    //update the details
                    $updateData['is_latest'] = '0';
                    $updateData['is_updated'] = 1;
                    $updateData['is_active'] = 1;
                    $billingServices->update($updateData);

                    $newBilling = \App\Models\Backend\BillingServices::create([
                                'service_id' => 6,
                                'contract_signed_date' => $billingServices->contract_signed_date,
                                'entity_id' => $id,
                                'ff_rph' => $request->input('ff_rph'),
                                'notes' => $request->has('notes') ? $request->input('notes') : $billingServices->notes,
                                'is_latest' => 1,
                                'is_updated' => 1,
                                'is_active' => 1,
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => loginUser()
                    ]);
                }
            }

            /* Added by Jayesh Shingrakhiya
             * Added on Nov 29, 2019
             * Reason update quote stage while billing service get update
             */
            $checkForQuote = \App\Models\Backend\QuoteMaster::where('entity_id', $billingServices->entity_id)->whereRaw('FIND_IN_SET(6, service_id)')->where('stage_id', 7);

            if ($checkForQuote->count() > 0) {
                $checkForQuote = $checkForQuote->first();
                $quoteMasterId = $checkForQuote->id;
                $isBillingStage = \App\Models\Backend\QuoteLeadAgreedServices::where('quote_master_id', $quoteMasterId)->where('is_billingteam_done', 0);
                if ($isBillingStage->count() > 0) {
                    $tempObj = $isBillingStage->count();
                    $isBillingStage->where('service_id', 6)->update(['is_billingteam_done' => 1]);
                    if ($tempObj == 1) {
                        \App\Models\Backend\QuoteMaster::where('id', $quoteMasterId)->update(['stage_id' => 8]);

                        $quoteLog = array();
                        $quoteLog['quote_master_id'] = $quoteMasterId;
                        $quoteLog['stage_id'] = 8;
                        $quoteLog['is_stage_knockback'] = 0;
                        $quoteLog['created_by'] = app('auth')->guard()->id();
                        $quoteLog['created_on'] = date('Y-m-d H:i:s');
                        \App\Models\Backend\QuoteStageLog::create($quoteLog);
                    }
                }
            }

            return createResponse(config('httpResponse.SUCCESS'), 'Tax Billing Info has been updated successfully', ['message' => 'Tax Billing Info has been updated successfully']);
        } catch (\Exception $e) {
            app('log')->error("Tax Billing Info updation failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update tax details.', ['error' => 'Could not update Tax Billing Info.']);
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
            $billingServices = \App\Models\Backend\BillingServices::select('ff_rph', 'notes')
                    ->where("entity_id", $id)
                    ->where("service_id", "6")
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
     * Store turnover details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @return Illuminate\Http\JsonResponse
     */
    public function turnoverStore(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'year' => 'required',
                'amount' => 'required|decimal',
                'option' => 'required|in:Charged,Quoted',
                'turnover' => 'numeric',
                    ], []);

            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            // store turnover details
            $loginUser = loginUser();
            $turnover = \App\Models\Backend\BillingTaxTurnover::create([
                        'entity_id' => $id,
                        'tax_year' => $request->input('year'),
                        'tax_amount' => $request->input('amount'),
                        'tax_condition' => $request->input('option'),
                        'turnover' => $request->input('turnover'),
                        'notes' => $request->has('notes') ? $request->input('notes') : '',
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => $loginUser
            ]);

            return createResponse(config('httpResponse.SUCCESS'), 'Turnover has been added successfully', ['data' => $turnover]);
        } catch (\Exception $e) {
            app('log')->error("Turnover creation failed " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not add Turnover', ['error' => 'Could not add Turnover']);
        }
    }

    /**
     * get particular turnover details
     *
     * @param  int  $id   //turnover id
     * @return Illuminate\Http\JsonResponse
     */
    public function turnoverList($id) {
        try {
            $turnover = \App\Models\Backend\BillingTaxTurnover::with('createdBy:userfullname as created_by,id')->where("entity_id", $id)->where("is_deleted", "0");

            //send turnover information
            return createResponse(config('httpResponse.SUCCESS'), 'Turnover List', ['data' => $turnover->get()]);
        } catch (\Exception $e) {
            app('log')->error("Turnover details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get turnover.', ['error' => 'Could not get turnover.']);
        }
    }

    /**
     * delete particular turnover details
     *
     * @param  int  $id   //turnover id
     * @return Illuminate\Http\JsonResponse
     */
    public function turnoverDestroy(Request $request, $id) {
        try {
            $turnover = \App\Models\Backend\BillingTaxTurnover::find($id);
            // Check weather turnover exists or not
            if (!isset($turnover))
                return createResponse(config('httpResponse.NOT_FOUND'), 'Turnover does not exist', ['error' => 'Turnover does not exist']);

            $turnover->is_deleted = 1;
            $turnover->deleted_by = loginUser();
            $turnover->deleted_on = date('Y-m-d H:i:s');
            $turnover->save();

            return createResponse(config('httpResponse.SUCCESS'), 'Turnover has been deleted successfully', ['message' => 'Turnover has been deleted successfully']);
        } catch (\Exception $e) {
            app('log')->error("Turnover deletion failed : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not delete turnover.', ['error' => 'Could not delete turnover.']);
        }
    }

}

?>