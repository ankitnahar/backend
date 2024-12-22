<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;
use DB;

class AdjustInvoiceCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:adjust';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Adjust Invoice create';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $date = date("Y-m-d");
            if($date =='2024-01-01' || $date=='2024-04-01' || $date == '2024-07-01' || $date == '2024-10-01'){
                        $adjuctBilling = \App\Models\Backend\Billing::
                    leftjoin("entity as e", "e.id", "billing_basic.entity_id")
                    ->select("billing_basic.entity_id")
                    ->where("billing_basic.adjuct_wip", "1")
                    ->where("e.discontinue_stage", "!=", "2")->whereRaw("e.id Not in (2,8,10)");
            if ($adjuctBilling->count() > 0) {  
                foreach ($adjuctBilling->get() as $row) {
                    $billingBK = \App\Models\Backend\BillingServices::select("id")
                            ->where("service_id","1")
                            ->where("is_latest","1")
                            ->where("is_active","1")
                            ->where("entity_id",$row->entity_id);
                     $timesheetBk = \App\Models\Backend\Timesheet::where("entity_id",$row->entity_id)
                            ->where('date',"<=",$date)
                            ->where('service_id',"1")
                            ->where('billing_status',"0");
                    if($billingBK->count() >0 && $timesheetBk->count() > 0){ 
                        $billingBK =$billingBK->first();
                        $checkInvoice = \App\Models\Backend\Invoice::where("entity_id",$row->entity_id)->where("status_id",10)
                                ->where("to_period",$date);
                        
                        if($checkInvoice->count() ==0){
                    $invoiceId =  \App\Models\Backend\Invoice::create([
                        'parent_id' => '0',
                        'entity_id' => $row->entity_id,
                        'service_id' => 1,
                        'billing_id' => $billingBK->id,
                        'from_period' => '0000-00-00',
                        'to_period' => $date,
                        'invoice_type' => 'Manual',
                        'status_id' => '10',
                        'gross_amount' => 0,
                        "adjusted"=>1,
                        "dismiss_reason" => 'Adjusted by System',
                        'net_amount' => 0,
                        'created_by' => 1,
                        'created_on' => date('Y-m-d')
                    ]);
                    \App\Models\Backend\Timesheet::where("entity_id",$row->entity_id)
                            ->where('date',"<=",$date)
                            ->where('service_id',"1")
                            ->where('billing_status',"0")
                            ->update(["billing_status" => 3,"invoice_id" => $invoiceId->id
                                ]); 
                    }
                    }
                    $billingPayroll = \App\Models\Backend\BillingServices::select("id")
                            ->where("service_id","2")
                            ->where("is_latest","1")
                            ->where("is_active","1")
                            ->where("entity_id",$row->entity_id);
                    $timesheetPayroll = \App\Models\Backend\Timesheet::where("entity_id",$row->entity_id)
                            ->where('date',"<=",$date)
                            ->where('service_id',"2")
                            ->where('billing_status',"0");
                    if($billingPayroll->count() >0 && $timesheetPayroll->count() > 0){ 
                        $billingPayroll =$billingPayroll->first();
                    $invoiceId =  \App\Models\Backend\Invoice::create([
                        'parent_id' => '0',
                        'entity_id' => $row->entity_id,
                        'service_id' => 2,
                        'billing_id' => $billingPayroll->id,
                        'from_period' => '0000-00-00',
                        'to_period' => $date,
                        'invoice_type' => 'Manual',
                        'status_id' => '10',
                        'gross_amount' => 0,
                        "adjusted"=>1,
                        "dismiss_reason" => 'Adjusted by System',
                        'net_amount' => 0,
                        'created_by' => 1,
                        'created_on' => date('Y-m-d')
                    ]);
                    \App\Models\Backend\Timesheet::where("entity_id",$row->entity_id)
                            ->where('date',"<=",$date)
                            ->where('service_id',"2")
                            ->where('billing_status',"0")
                            ->update(["billing_status" => 3,"invoice_id" => $invoiceId->id
                                ]); 
                    }
                }
            }
            }
       } catch (Exception $e) {
            $cronName = "Adjuct invoice";
            $message = $e->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
