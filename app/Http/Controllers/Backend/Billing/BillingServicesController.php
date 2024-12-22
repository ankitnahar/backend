<?php

namespace App\Http\Controllers\Backend\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;

class BillingServicesController extends Controller {

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
            'default_rph' => 'required|decimal',
            'bk_in_ff' => 'required|numeric|in:1,0',
            'ff_rph' => 'required|decimal',
            'fixed_fee' => 'required_if:bk_in_ff,1|decimal',
            'ff_start_date' => 'required_if:bk_in_ff,1',
            'fixed_total_amount' => 'required|decimal',
            'fixed_total_unit' => 'required|numeric',
            'auto_invoice' => 'required|in:1,0',
            'frequency_id' => 'required|numeric',
            'recurring_id' => 'required_if:auto_invoice,1',
                ], []);
        // If validation fails then return error response
        if ($validator->fails())
            return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);
        $billingServices = \App\Models\Backend\BillingServices::select('id', 'entity_id', 'service_id', 'contract_signed_date', 'is_updated', 'recurring_id', 'auto_invoice', 'frequency_id', 'default_rph', 'inc_in_ff', 'bk_in_ff', 'ff_rph', 'fixed_fee', 'ff_start_date', 'fixed_total_amount', 'fixed_total_unit', 'notes')
                        ->where("entity_id", $id)->where("service_id", "1")->where("is_latest", "1");

        if ($billingServices->count() == 0)
            return createResponse(config('httpResponse.NOT_FOUND'), 'Billing Services does not exist', ['error' => 'The Billing Services does not exist']);

        $billingServices = $billingServices->first();

        $updateData = array();
        // Filter the fields which need to be updated

        $updateData = filterFields(['recurring_id', 'frequency_id', 'default_rph', 'bk_in_ff', 'ff_rph',
            'fixed_fee', 'ff_start_date', 'fixed_total_amount', 'fixed_total_unit', 'notes'], $request);
        $updateData['inc_in_ff'] = $request->input('bk_in_ff');
        $updateData['auto_invoice'] = self::updateAuto($id, $request->input('auto_invoice'));
        //update recurring in recurring table also
        if ($request->has('recurring_id')) {
            self::updateRecurring($request->input('recurring_id'), $id, 1);
        }

        $serviceMasterIds = config('constant.serviceMasterIds');
        $master_id = array(6, 7, 22);
        $inc_in_ff = 0;
        if ($request->input('bk_in_ff') == 1) {
            $inc_in_ff = 1;
            if ($request->input('ff_rph') == 0 || $request->input('ff_rph') == '0.00') {
                return createResponse(config('httpResponse.NOT_FOUND'), 'If Inc In FF Yes Then RPH should be greater then Zero', ['error' => 'If Inc In FF Yes Then RPH should be greater then Zero']);
            }
            $master_id[] = 5;
        }

        foreach ($request->input('service_rph') as $row) {
            if ($row['inc_in_ff'] == 1) {
                $inc_in_ff = 1;
                if ($row['rph'] == 0 || $row['rph'] == '0.00') {
                    return createResponse(config('httpResponse.NOT_FOUND'), 'If Inc In FF Yes Then RPH should be greater then Zero', ['error' => 'If Inc In FF Yes Then RPH should be greater then Zero']);
                }
                $master_id[] = $serviceMasterIds[$row['service_id']];
            }
        }
        $subService = config('constant.subService');
        foreach ($request->input('service_rph') as $row) {
            if ($row['contract_signed_date'] != '0000-00-00' && $row['contract_signed_date'] != null && $row['contract_signed_date'] != '') {
                $month = date("m");
                if ($month > 7) {
                    $year = date("Y", strtotime("+1 Year"));
                } else {
                    $year = date("Y");
                }
                $checkService = \App\Models\Backend\DirectoryEntity::where("entity_id", $id)->where("year", $year)->where("service_id", $row['service_id']);
                $checkDirectoryAlready = \App\Models\Backend\DirectoryServiceCreation::where("entity_id", $id)
                                ->where("service_id", $row['service_id'])->where("year", $year);
                if ($checkDirectoryAlready->count() == 0) {
                    if ($checkService->count() == 0) {
                        $folderId = \App\Models\Backend\DirectoryEntity::where("entity_id", $id)->where("year", $year)->where("directory_id", "1");
                        if ($folderId->count() > 0) {
                            $folderId = $folderId->first();
                            $datainsert = array('entity_id' => $id,
                                "service_id" => $row['service_id'],
                                "year" => $year,
                                "folder_id" => $folderId->id,
                                "created_on" => date('y-m-d h:i:s'),
                                "created_by" => loginUser());
                            \App\Models\Backend\DirectoryServiceCreation::insert($datainsert);
                        }
                    }
                }
                $subclient = \App\Models\Backend\SubClient::where("entity_id", $id)->where("is_active", "1");
                if ($subclient->count() > 0) {
                    foreach ($subclient->get() as $sub) {
                        $checksubclientService = \App\Models\Backend\DirectoryEntity::where("entity_id", $id)->where("subclient_id", $sub->id)->where("year", $year)->where("service_id", $row['service_id']);
                        $checksubclientDirectoryAlready = \App\Models\Backend\DirectoryServiceCreation::where("entity_id", $id)->where("subclient_id", $sub->id)
                                        ->where("service_id", $row['service_id'])->where("year", $year);
                        if ($checksubclientDirectoryAlready->count() == 0) {
                            if ($checksubclientService->count() == 0) {
                                $subfolderId = \App\Models\Backend\DirectoryEntity::where("entity_id", $id)
                                                ->where("subclient_id", $sub->id)->where("year", $year)->where("directory_id", "1");
                                if ($subfolderId->count() > 0) {
                                    $subfolderId = $subfolderId->first();
                                    $datasubinsert = array('entity_id' => $id,
                                        "subclient_id" => $sub->id,
                                        "service_id" => $row['service_id'],
                                        "year" => $year,
                                        "folder_id" => $subfolderId->id,
                                        "created_on" => date('y-m-d h:i:s'),
                                        "created_by" => loginUser());
                                    \App\Models\Backend\DirectoryServiceCreation::insert($datasubinsert);
                                }
                            }
                        }
                    }
                }
            }
        }

        $invoicesCount = \App\Models\Backend\Invoice::where("billing_id", $billingServices->id)->count();

        if ($billingServices->is_updated == 0 || $invoicesCount == 0) {
            $updateData['is_updated'] = 1;

            $array_diff = array_diff_assoc($updateData, $billingServices->getOriginal());
            // echo $billingServices->is_updated;exit;
            if ($billingServices->is_updated == 0) {
                $this->UpdateBKBilling($request->input('service_rph'), 0, $billingServices->id, 1, $id);
                \App\Models\Backend\BillingServicesSubactivity::addSubactivity($billingServices->entity_id, 1, $billingServices->id, $master_id, 0, 0);
            } else if (!empty($array_diff)) {
                $this->UpdateBKBilling($request->input('service_rph'), 0, $billingServices->id, 1, $id);
                // \App\Models\Backend\BillingServicesSubactivity::addSubactivity($billingServices->entity_id, 1, $billingServices->id, $master_id, 0, $billingServices->id);
            } else {
                $diff = 0;
                foreach ($request->input('service_rph') as $row) {
                    if ($row['id'] != '') {
                        $billingBKOld = \App\Models\Backend\BillingBKRPH::find($row['id']);
                        $arrayDiff = array_diff_assoc($row, $billingBKOld->getOriginal());
                        $arrayDiffOld = array_diff_assoc($billingBKOld->getOriginal(), $row);
                        $diffVal = array_merge_recursive($arrayDiffOld, $arrayDiff);
                        if (!empty($diffVal)) {
                            $diff = 1;
                            break;
                        }
                    }
                }
                // showArray($diff);exit;
                if ($diff == 1) {
                    $this->UpdateBKBilling($request->input('service_rph'), 0, $billingServices->id, 1, $id);
                    \App\Models\Backend\BillingServicesSubactivity::addSubactivity($billingServices->entity_id, 1, $billingServices->id, $master_id, 0, 0);
                }
            }
            $billingServices->update($updateData);
        } else {
            $array_diff = array_diff_assoc($updateData, $billingServices->getOriginal());
            $bkService = $diff = 0;
            //showArray($array_diff);exit;
            if (!empty($array_diff)) {
                //update the details
                $updateData['is_latest'] = 0;
                $updateData['is_updated'] = 1;
                $updateData['is_active'] = 1;
                $billingServices->update($updateData);

                $newBilling = \App\Models\Backend\BillingServices::create([
                            'service_id' => 1,
                            'contract_signed_date' => $billingServices->contract_signed_date,
                            'entity_id' => $billingServices->entity_id,
                            'recurring_id' => $request->input('recurring_id'),
                            'auto_invoice' => $request->input('auto_invoice'),
                            'frequency_id' => $request->input('frequency_id'),
                            'default_rph' => $request->input('default_rph'),
                            'inc_in_ff' => $inc_in_ff,
                            'bk_in_ff' => $request->input('bk_in_ff'),
                            'ff_rph' => $request->input('ff_rph'),
                            'fixed_fee' => $request->input('fixed_fee'),
                            'ff_start_date' => $request->input('ff_start_date'),
                            'fixed_total_amount' => $request->input('fixed_total_amount'),
                            'fixed_total_unit' => $request->input('fixed_total_unit'),
                            'notes' => $request->has('notes') ? $request->input('notes') : $billingServices->notes,
                            'is_latest' => 1,
                            'is_updated' => 1,
                            'is_active' => 1,
                            'created_on' => date('Y-m-d H:i:s'),
                            'created_by' => loginUser()
                ]);

                //Update billing subactivity if billing info changes
                \App\Models\Backend\BillingServicesSubactivity::addSubactivity($billingServices->entity_id, 1, $newBilling->id, $master_id, 0, $billingServices->id);


                $this->UpdateBKBilling($request->input('service_rph'), $newBilling->id, $billingServices->id, 2, $id);
            } else {
                foreach ($request->input('service_rph') as $row) {
                    $billingBKOld = \App\Models\Backend\BillingBKRPH::find($row['id']);
                    if (!empty($billingBKOld)) {
                        $arrayDiff = array_diff_assoc($row, $billingBKOld->getOriginal());
                        $arrayDiffOld = array_diff_assoc($billingBKOld->getOriginal(), $row);
                        $diffVal = array_merge_recursive($arrayDiffOld, $arrayDiff);
                        if (!empty($diffVal)) {
                            $diff = 1;
                            break;
                        }
                    } else if ($row['id'] == null || $row['id'] == '') {
                        $diff = 2;
                        break;
                    }
                }
                if ($diff == 1) {
                    $this->UpdateBKBilling($request->input('service_rph'), 0, $billingServices->id, 2, $id);
                    \App\Models\Backend\BillingServicesSubactivity::addSubactivity($billingServices->entity_id, 1, $billingServices->id, $master_id, 0, 0);
                }
                if ($diff == 2) {
                    $this->UpdateBKBilling($request->input('service_rph'), 0, $billingServices->id, 1, $id);
                }
            }
        }

        /* Added by Jayesh Shingrakhiya
         * Added on Nov 29, 2019
         * Reason update quote stage while billing service get update
         */
        $checkForQuote = \App\Models\Backend\QuoteMaster::where('entity_id', $billingServices->entity_id)->where('stage_id', 7);

        if ($checkForQuote->count() > 0) {
            $checkForQuote = $checkForQuote->first();
            $quoteMasterId = $checkForQuote->id;
            $isBillingStage = \App\Models\Backend\QuoteLeadAgreedServices::where('quote_master_id', $quoteMasterId)->where('is_billingteam_done', 0);
            if ($isBillingStage->count() > 0) {
                $isBillingStage->whereIn('service_id', [1,2,6])->update(['is_billingteam_done' => 1]);
                if ($isBillingStage->count() == 0) {
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
        return createResponse(config('httpResponse.SUCCESS'), 'Bk Billing Info has been updated successfully', ['message' => 'Bk Billing Info has been updated successfully']);
        /* } catch (\Exception $e) {
          app('log')->error("Bk Billing Info updation failed : " . $e->getMessage());
          return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not update Bk Billing Info details.', ['error' => 'Could not update Bk Billing Info.']);
          } */
    }

    public static function updateRecurring($recurringId, $entityId, $serviceId) {
        //find entity if recurring already set
        if ($recurringId >= 0 && $recurringId != '') {
            $recurringEntity = \App\Models\Backend\InvoiceRecurring::where("service_id", $serviceId)
                            ->whereRaw("FIND_IN_SET(" . $entityId . ",entity_id)")->where("id", "!=", $recurringId);
            if ($recurringEntity->count() > 0) {
                $recurringEntity = $recurringEntity->get();
                foreach ($recurringEntity as $rec) {
                    $entityIds = explode(",", $rec->entity_id);
                    if (in_array($entityId, $entityIds)) {
                        unset($entityIds[array_search($entityId, $entityIds)]);
                    }
                    $entityValues = '';
                    if (!empty($entityIds)) {
                        $entityValues = implode(",", $entityIds);
                    }
                    \App\Models\Backend\InvoiceRecurring::where("id", $rec->id)->update(["entity_id" => $entityValues]);
                }
            }
            $recurring = \App\Models\Backend\InvoiceRecurring::where("id", $recurringId)->whereRaw("FIND_IN_SET(" . $entityId . ",entity_id)");
            if ($recurring->count() == 0) {
                $recurringEntity = \App\Models\Backend\InvoiceRecurring::where("id", $recurringId)->first();
                if ($recurringEntity->rec_type == 1) {
                    if ($recurringEntity->entity_id != '') {
                        $entityId = $recurringEntity->entity_id . "," . $entityId;
                    }
                    \App\Models\Backend\InvoiceRecurring::where("id", $recurringId)
                            ->update(["rec_type" => "2", "entity_id" => $entityId]);
                } else {
                    if ($recurringEntity->entity_id != '') {
                        $entityId = $recurringEntity->entity_id . "," . $entityId;
                    }
                    \App\Models\Backend\InvoiceRecurring::where("id", $recurringId)
                            ->update(["entity_id" => $entityId]);
                }
            }
        } else {
            $recurringEntity = \App\Models\Backend\InvoiceRecurring::where("service_id", $serviceId)
                    ->whereRaw("FIND_IN_SET(" . $entityId . ",entity_id)");

            if ($recurringEntity->count() > 0) {
                $recurringEntity = $recurringEntity->get();
                foreach ($recurringEntity as $rec) {
                    $entityIds = explode(",", $rec->entity_id);
                    if (in_array($entityId, $entityIds)) {
                        unset($entityIds[array_search($entityId, $entityIds)]);
                    }
                    $entityValues = '';
                    if (!empty($entityIds)) {
                        $entityValues = implode(",", $entityIds);
                    }
                    \App\Models\Backend\InvoiceRecurring::where("id", $rec->id)->update(["entity_id" => $entityValues]);
                }
            }
        }
    }

    public static function UpdateBKBilling($serviceRPH, $NewBillingId, $billingId, $type, $entityId) {

        foreach ($serviceRPH as $row) {
            if ($row['id'] != '') {
                $billingBKOld = \App\Models\Backend\BillingBKRPH::find($row['id']);
                $arrayDiff = array_diff_assoc($row, $billingBKOld->getOriginal());
                $arrayDiffOld = array_diff_assoc($billingBKOld->getOriginal(), $row);
                $diffVal = array_merge_recursive($arrayDiffOld, $arrayDiff);
                // showArray($arrayDiffOld);
                //showArray($diffVal);exit;
                if (!empty($diffVal)) {
                    \App\Models\Backend\BillingBKRPH::saveAudit($diffVal, $billingBKOld, $entityId, $row['service_id']);
                }
            }
            $newbillingBk = array(
                'billing_id' => $NewBillingId != 0 ? $NewBillingId : $billingId,
                'service_id' => $row['service_id'],
                'contract_signed_date' => ($row['contract_signed_date'] == '') ? '0000-00-00' : $row['contract_signed_date'],
                'inc_in_ff' => $row['inc_in_ff'],
                'rph' => $row['rph'],
                'fixed_fee' => isset($row['fixed_fee']) ? $row['fixed_fee'] : '0.00',
                'ff_start_date' => isset($row['ff_start_date']) ? $row['ff_start_date'] : '0000-00-00',
                'is_latest' => 1,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => loginUser());

            $allServiceArray[] = $newbillingBk;
            if ($type == 1) {
                if ($row['id'] != '') {
                    $billingBK = \App\Models\Backend\BillingBKRPH::find($row['id']);
                    \App\Models\Backend\BillingBKRPH::where("id", $row['id'])->update($newbillingBk);
                } else {
                    \App\Models\Backend\BillingBKRPH::create($newbillingBk);
                }
            } else if ($type == 2 && !empty($diffVal)) {
                $billingBK = \App\Models\Backend\BillingBKRPH::find($row['id']);
                \App\Models\Backend\BillingBKRPH::where("id", $row['id'])->update(["is_latest" => 0]);
                \App\Models\Backend\BillingBKRPH::create($newbillingBk);
            }
        }
    }

    /**
     * get particular details
     *
     * @param  int  $id   //billing id
     * @return Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id) {
        try {

            $billingServices = \App\Models\Backend\BillingServices::
                    leftjoin("services as s", "s.id", "billing_services.service_id")
                    ->select('billing_services.id', 'service_id', 's.service_name', 'contract_signed_date', 'recurring_id', 'auto_invoice', 'frequency_id', 'default_rph', 'bk_in_ff', 'ff_rph', 'fixed_fee', 'ff_start_date', 'fixed_total_amount', 'fixed_total_unit', 'notes', 'is_updated')
                    ->where("entity_id", $id)
                    ->where("service_id", "1")
                    ->where("is_latest", "1");

            if ($billingServices->count() == 0)
                return createResponse(config('httpResponse.NOT_FOUND'), 'The Billing Services does not exist', ['error' => 'The Billing Services does not exist']);

            $billingServices = $billingServices->first();

            $id = $billingServices->id;
            $billingRPH = \App\Models\Backend\Services::leftjoin("billing_bk_rph as br", function($query) use($id) {
                                $query->on('br.service_id', '=', 'services.id');
                                $query->on('br.billing_id', '=', DB::raw($id));
                                $query->on('br.is_latest', '=', DB::raw("1"));
                            })
                            ->select("services.service_name", "services.id as service_id", 'br.id', 'br.billing_id', 'br.contract_signed_date', 'br.inc_in_ff', 'br.rph', 'br.fixed_fee', 'br.ff_start_date', 'br.is_latest', 'br.created_on', 'br.created_by')
                            ->whereIn("services.id", [8, 9, 10, 11])->get();



            $billingServices['service_rph'] = $billingRPH;
            //send crmnotes information
            return createResponse(config('httpResponse.SUCCESS'), 'Billing Basic data', ['data' => $billingServices]);
        } catch (\Exception $e) {
            app('log')->error("Billing Services details api failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get Billing Services.', ['error' => 'Could not get Billing Services.']);
        }
    }

    public function getrecurring(Request $request) {
        try {
            $validator = app('validator')->make($request->all(), [
                'service_id' => 'required|numeric',
                'inc_in_ff' => 'required|numeric',
                'frequency_id' => 'required|numeric'], []);
            // If validation fails then return error response
            if ($validator->fails())
                return createResponse(config('httpResponse.UNPROCESSED'), config('message.VALIDATION'), ['error' => $validator->errors()->first()]);

            $recurring = \App\Models\Backend\InvoiceRecurring::where("service_id", $request->input('service_id'))
                            ->where("fixed_fee", $request->input('inc_in_ff'))
                            ->where("frequency_id", $request->input('frequency_id'))
                            ->where("is_active", "1")->get();

            return createResponse(config('httpResponse.SUCCESS'), 'Recurring data', ['data' => $recurring]);
        } catch (\Exception $e) {
            app('log')->error("Recurring List details failed : " . $e->getMessage());

            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not get recurring list.', ['error' => 'Could not get recurring list.']);
        }
    }

    public static function saveHistory($model, $col_name) {
        $ArrayYesNo = array('is_active', 'is_updated', 'bk_in_ff', 'is_setup_cost', 'audit_fee_inc', 'befree_invoice', 'is_latest', 'auto_invoice');
        $ArrayDropdown = array('payroll_frequency_id', 'frequency_id', 'recurring_id', 'calc_id', 'software_id', 'state_id', 'plan_id');
        $userArray = array('sales_person_id');
        if (!empty($model->getDirty())) {
            $diff_col_val = array();
            foreach ($model->getDirty() as $key => $value) {
                if ($key == 'is_updated' || $key == 'is_latest' || $key == 'is_active') {
                    continue;
                }
                $oldValue = $model->getOriginal($key);
                $colname = isset($col_name[$key]) ? $col_name[$key] : $key;
                if (in_array($key, $ArrayYesNo)) {
                    $oldval = ($oldValue == '1') ? 'Yes' : 'No';
                    $newval = ($value == '1') ? 'Yes' : 'No';
                    $oldValue = $oldval;
                    $value = $newval;
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                } else if ($key == 'inc_in_ff') {
                    $oldval = ($oldValue == '1') ? 'Yes' : (($oldValue == '2') ? 'Quoted' : 'No');
                    $newval = ($value == '1') ? 'Yes' : (($oldValue == '2') ? 'Quoted' : 'No');
                    $oldValue = $oldval;
                    $value = $newval;
                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldValue,
                        'new_value' => $value,
                    ];
                } else if (in_array($key, $ArrayDropdown)) {
                    if ($key == 'recurring_id') {
                        $recurring = \App\Models\Backend\InvoiceRecurring::get()->pluck("recurring_name", "id")->toArray();
                        $oldval = ($oldValue != '' && $oldValue != 0) ? $recurring[$oldValue] : '';
                        $newval = ($value != '' && $value != 0) ? $recurring[$value] : '';
                    }
                    if ($key == 'payroll_frequency_id' || $key == 'frequency_id') {
                        $frequency = \App\Models\Backend\Frequency::where("is_active", "1")->get()->pluck("frequency_name", "id")->toArray();
                        $oldval = ($oldValue != '') ? $frequency[$oldValue] : '';
                        $newval = ($value != '') ? $frequency[$value] : '';
                    } else if ($key == 'calc_id') {
                        $payroll = \App\Models\Backend\BillingPayrollCalc::get()->pluck("name", "id")->toArray();
                        $oldval = ($oldValue != '' && $oldValue != '0') ? $payroll[$oldValue] : '';
                        $newval = ($value != '') ? $payroll[$value] : '';
                    } else if ($key == 'software_id' || $key == 'plan_id') {
                        $software = \App\Models\Backend\BillingSubscriptionSoftware::where("is_active", "1")->get()->pluck("software_plan", "id")->toArray();
                        $oldval = ($oldValue != '') ? $software[$oldValue] : '';
                        $newval = ($value != '') ? $software[$value] : '';
                    } else if ($key == 'state_id') {
                        $state = \App\Models\Backend\State::get()->pluck("state_name", "id")->toArray();
                        $oldval = ($oldValue != '') ? $state[$oldValue] : '';
                        $newval = ($value != '') ? $state[$value] : '';
                    }

                    $diff_col_val[$key] = [
                        'display_name' => ucfirst($colname),
                        'old_value' => $oldval,
                        'new_value' => $newval,
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
     * select billing history details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $user_id,$type
     * @return Illuminate\Http\JsonResponse
     */
    public function history(Request $request, $id) {
        try {
            //validate request parameters
            $validator = app('validator')->make($request->all(), [
                'service_id' => 'required|numeric',
                'sortOrder' => 'in:asc,desc',
                'pageNumber' => 'numeric|min:1',
                'recordsPerPage' => 'numeric|min:0',
                'search' => 'json'], []);

            if ($validator->fails()) // Return error message if validation fails
                return createResponse(config('httpResponse.UNPROCESSED'), "Request parameter missing.", ['error' => $validator->errors()->first()]);

            $sortByName = $sortBy = ($request->has('sortBy')) ? $request->get('sortBy') : 'modified_on';
            $sortOrder = ($request->has('sortOrder')) ? $request->get('sortOrder') : 'desc';
            $pager = [];

            $history = \App\Models\Backend\BillingServicesAudit::with("modifiedBy:id,userfullname")->where("entity_id", $id)->where("service_id", $request->get('service_id'));

            if (!isset($history))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The billing Services history does not exist', ['error' => 'The billing Services history does not exist']);

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
            return createResponse(config('httpResponse.SUCCESS'), 'Billing Services history', ['data' => $history], $pager);
        } catch (\Exception $e) {
            app('log')->error("Could not load billing Services history : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not load billing Services history.', ['error' => 'Could not load billing  Services history.']);
        }
    }

    /**
     * select billing bk rph history details
     *
     *
     * @param  Illuminate\Http\Request  $request
     * @param  int                      $user_id,$type
     * @return Illuminate\Http\JsonResponse
     */
    public function historyBKService(Request $request, $id) {
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

            $history = \App\Models\Backend\BillingBKRPHAudit::with("modifiedBy:id,userfullname")->where("billing_id", $id);

            if (!isset($history))
                return createResponse(config('httpResponse.NOT_FOUND'), 'The billing Services history does not exist', ['error' => 'The billing Services history does not exist']);

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
            return createResponse(config('httpResponse.SUCCESS'), 'Billing Services history', ['data' => $history], $pager);
        } catch (\Exception $e) {
            app('log')->error("Could not load billing Services history : " . $e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'), 'Could not load billing Services history.', ['error' => 'Could not load billing  Services history.']);
        }
    }

    public static function updateAuto($entityId, $auto) {
        if ($auto == 1) {
            $BillingRelated = \App\Models\Backend\Billing::select("is_related")->where("entity_id", $entityId)->first();
            if ($BillingRelated->is_related == 1) {
                $auto = 0;
            }
        }
        return $auto;
    }

    public static function addBillingForOther($entityId) {
        for ($service = 1; $service <= 2; $service++) {
           
            // if service will in active then that service data also in active is latest change to zero
            $serviceData = [
                'entity_id' => $entityId,
                'service_id' => $service,
                'inc_in_ff' => 1,
                'ff_rph' => '28.00',
                'contract_signed_date' => date("Y-m-d"),
                'is_updated'=>1,
                'is_active' => 1,
                'is_latest' => 1,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => loginUser()];
           $billingService= \App\Models\Backend\BillingServices::where("entity_id",$entityId)->where("service_id",$service);
            if ($billingService->count() == 0) {   
                \App\Models\Backend\BillingServices::Insert($serviceData);
            }
            $allocation = \App\Models\Backend\EntityAllocation::where("service_id", $service)
                    ->where("entity_id", $entityId);
            //echo $allocation->count();exit;
            $serviceId = $service;
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
                                'entity_id' => $entityId,
                                'service_id' => $service,
                                'allocation_json' => json_encode($allocationJson),
                                'created_on' => date('Y-m-d H:i:s'),
                                'created_by' => loginUser(),
                                'modified_on' => date('Y-m-d H:i:s'),
                                'modified_by' => loginUser()]);
                }
            }

            if ($service == 1 || $service == 2) {
                $billingService = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)
                        ->where("service_id", $service);
                $month = date("m");
                if ($month > 7) {
                    $year = date("Y", strtotime("+1 Year"));
                } else {
                    $year = date("Y");
                }
                $checkDirectoryAlready = \App\Models\Backend\DirectoryServiceCreation::where("entity_id", $entityId)
                                ->where("service_id", $service)->where("year", $year);
                if ($checkDirectoryAlready->count() == 0) {
                    $checkbkService = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("year", $year)->where("service_id", $service);

                    if ($checkbkService->count() == 0) {
                        $datainsert = array('entity_id' => $entityId,
                            "service_id" => $service,
                            "year" => $year,
                            "folder_id" => 0,
                            "created_on" => date('y-m-d h:i:s'),
                            "created_by" => loginUser());

                        \App\Models\Backend\DirectoryServiceCreation::insert($datainsert);
                    }
                }
            }
        }



        $serviceMasterIds = config('constant.serviceMasterIds');
        $master_id = array(6, 7, 22);
        $inc_in_ff = 0;
        
        $subService = config('constant.subService');
        for ($subService = 8; $subService <= 11; $subService++) {
            $month = date("m");
            if ($month > 7) {
                $year = date("Y", strtotime("+1 Year"));
            } else {
                $year = date("Y");
            }
            $checkService = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("year", $year)->where("service_id", $subService);
            $checkDirectoryAlready = \App\Models\Backend\DirectoryServiceCreation::where("entity_id", $entityId)
                            ->where("service_id", $subService)->where("year", $year);
            if ($checkDirectoryAlready->count() == 0) {
                if ($checkService->count() == 0) {
                    $folderId = \App\Models\Backend\DirectoryEntity::where("entity_id", $entityId)->where("year", $year)->where("directory_id", "1");
                    if ($folderId->count() > 0) {
                        $folderId = $folderId->first();
                        $datainsert = array('entity_id' => $entityId,
                            "service_id" => $subService,
                            "year" => $year,
                            "folder_id" => $folderId->id,
                            "created_on" => date('y-m-d h:i:s'),
                            "created_by" => loginUser());
                        \App\Models\Backend\DirectoryServiceCreation::insert($datainsert);
                    }
                }
            }
        }
    }

}

?>