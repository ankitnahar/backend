<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
class UpdateBillingCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Billing';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            //get FF detail whose date = current date
            $date = date('Y-m-d');
            $updateBilling = \App\Models\Backend\FFBillingServices::
                            leftjoin("ff_proposal as f", "f.id", "ff_billing_services.ff_id")
                            ->select("month", "year", "ff_billing_services.*")
                            ->where("billing_update_date", $date)->orderBy("entity_id");
            $serviceMasterIds = config('constant.serviceMasterIds');
            // showArray($updateBilling->count());exit;
            if ($updateBilling->count() > 0) {
                foreach ($updateBilling->get() as $row) {
                    try {
                        //get current service value
                        $billingService = \App\Models\Backend\BillingServices::where("entity_id", $row->entity_id)
                                ->where("is_latest", "1")
                                ->where("service_id", $row->service_id)
                                ->where("is_active", "1")
                                ->first();

                        $newBilling = \App\Models\Backend\BillingServices::create([
                                    'service_id' => $row->service_id,
                                    'contract_signed_date' => $billingService->contract_signed_date,
                                    'entity_id' => $billingService->entity_id,
                                    'recurring_id' => $row->recurring_id,
                                    'auto_invoice' => $row->auto_invoice,
                                    'frequency_id' => $row->frequency_id,
                                    'payroll_freqency_id' => $row->payroll_freqency_id,
                                    'calc_id' => $row->calc_id,
                                    'default_rph' => $row->default_rph,
                                    'inc_in_ff' => ($row->bk_in_ff == 1) ? 1 : 0,
                                    'bk_in_ff' => $row->bk_in_ff,
                                    'ff_rph' => $row->ff_rph,
                                    'fixed_fee' => $row->fixed_fee,
                                    'ff_start_date' => $row->ff_start_date,
                                    'fixed_total_amount' => $row->fixed_total_amount,
                                    'fixed_total_unit' => $row->fixed_total_unit,
                                    'notes' => $billingService->notes,
                                    'is_latest' => 1,
                                    'is_updated' => 1,
                                    'is_active' => 1,
                                    'created_on' => date('Y-m-d h:i:s'),
                                    'created_by' => loginUser()
                        ]);

                        if ($row->service_id == 1) {
                            \App\Http\Controllers\Backend\Billing\BillingServicesController::updateRecurring($row->recurring_id, $billingService->entity_id, 1);
            
                            //get ff master value
                            $ffMaster = \App\Models\Backend\FFBillingMasterAmount::select("master_id", "amount")
                                            ->where("ff_id", $row->ff_id)
                                            ->get()->pluck("amount", "master_id")->toArray();
                            
                            //get current bk rph
                            $billingBkRph = \App\Models\Backend\BillingBKRPH::where("billing_id", $billingService->id)
                                    ->whereIn("master_id",[8,9,10,11])
                                            ->where("is_latest", "1")->get();
                            

                            foreach ($billingBkRph as $bkRPh) {

                                $amt = $ffMaster[$serviceMasterIds[$bkRPh->service_id]];
                                $ff = 0;
                                if ($amt >= '0' && $amt >= '0.00') {
                                    $ff = 1;
                                }

                                if ($serviceMasterIds[$bkRPh->master_id] == '8') {
                                    $RPH = $row->ar_rph;
                                } else if ($serviceMasterIds[$bkRPh->master_id] == '9') {
                                    $RPH = $row->ap_rph;
                                } else if ($serviceMasterIds[$bkRPh->master_id] == '10') {
                                    $RPH = $row->dm_rph;
                                } else {
                                    $RPH = $row->bkpayroll_rph;
                                }


                                \App\Models\Backend\BillingBKRPH::create([
                                    'billing_id' => $newBilling->id,
                                    'service_id' => $serviceMasterIds[$bkRPh->master_id],
                                    'contract_signed_date' => $bkRPh->contract_signed_date,
                                    'inc_in_ff' => $ff,
                                    'rph' => $RPH,
                                    'fixed_fee' => $ffMaster[$serviceMasterIds[$bkRPh->master_id]],
                                    'ff_start_date' => $bkRPh->ff_start_date,
                                    'is_latest' => 1,
                                    'created_on' => date('Y-m-d h:i:s'),
                                    'created_by' => loginUser()]);
                            }

                            \App\Models\Backend\BillingBKRPH::where("billing_id", $billingService->id)->update(["is_latest" => 0]);
                            
                        }

                        //update Subactivity
                        $ffSubactivity = \App\Models\Backend\FFBillingSubactivity::where("ff_id", $row->ff_id)->get();

                        // billing subactivity 9,140,1230 not change
                        $billingSubactivity = \App\Models\Backend\BillingServicesSubactivity::
                                        where("billing_id", $billingService->id)
                                        ->where("is_latest", 1)->whereIn("subactivity_code", [9, 140, 1230])->get();
                        foreach ($billingSubactivity as $r) {
                            $subactivityArray[] = array(
                                'billing_id' => $newBilling->id,
                                'entity_id' => $row->entity_id,
                                'service_id' => $row->service_id,
                                'subactivity_code' => $r->subactivity_code,
                                'inc_in_ff' => $r->inc_in_ff,
                                'frequency_id' => $r->frequency_id,
                                'fixed_fee' => $r->fixed_fee,
                                'price' => $r->price,
                                'no_of_value' => $r->no_of_value,
                                'fixed_value' => $r->fixed_value,
                                'is_latest' => 1,
                                'created_on' => date("Y-m-d H:i:s"),
                                'created_by' => loginUser());
                        }

                        // add other subactivity
                        foreach ($ffSubactivity as $ro) {
                            $subactivityArray[] = array(
                                'billing_id' => $newBilling->id,
                                'entity_id' => $row->entity_id,
                                'service_id' => $row->service_id,
                                'subactivity_code' => $ro->subactivity_code,
                                'inc_in_ff' => $ro->inc_in_ff,
                                'frequency_id' => $ro->frequency_id,
                                'fixed_fee' => $ro->fixed_fee,
                                'price' => $ro->price,
                                'no_of_value' => $ro->no_of_value,
                                'fixed_value' => $ro->fixed_value,
                                'is_latest' => 1,
                                'created_on' => date("Y-m-d H:i:s"),
                                'created_by' => loginUser());
                        }
                        \App\Models\Backend\BillingServicesSubactivity::insert($subactivityArray);

                        \App\Models\Backend\BillingServicesSubactivity::where("billing_id", $billingService->id)->update(["is_latest" => 0]);

                        \App\Models\Backend\BillingServices::where("id", $billingService->id)->update(["is_latest" => 0]);
                        //update 
                    } catch (Exception $ex) {
                        app('log')->channel('updatebilling')->error("Fixed fee Update Billing info failed : " . $e->getMessage());
                    }
                }
            }
        } catch (Exception $ex) {
            $cronName = "Update Billing";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
