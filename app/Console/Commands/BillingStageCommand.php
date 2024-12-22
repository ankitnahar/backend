<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Exception;

class BillingStageCommand extends Command {

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:stage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Billing System Setup Stage';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        try {
            $billing = \App\Models\Backend\BillingServices::
                    leftjoin("entity as e","e.id","billing_services.entity_id")
                    ->select("billing_services.entity_id")
                    ->where("billing_services.is_updated","0")
                    ->where("billing_services.is_latest","1")
                    ->where("billing_services.is_active","1")
                    ->where("e.discontinue_stage","!=","2");
            
            $systemSetup = \App\Models\Backend\BillingServices::
                    leftjoin("entity as e","e.id","billing_services.entity_id")
                    ->leftjoin("system_setup_entity_stage as sses","sses.entity_id","billing_services.entity_id")
                    ->select("billing_services.entity_id")
                    ->where("stage_id","8")
                    ->where("status","N")
                    ->where("e.discontinue_stage","!=","2")
                    ->groupBy("entity_id")
                    ->get()->pluck("entity_id")->toArray();
           // showArray($systemSetup);
            if ($billing->get()->count() > 0) {
                foreach ($billing->get() as $row) {                    
                    try {
                        $notUpdateEntity[] = $row->entity_id;
                        \App\Models\Backend\SystemSetupEntityStage::UpdateStage($row->entity_id, 8, 'N');                        
                        
                    } catch (Exception $ex) {
                        app('log')->channel('billingstage')->error("Billing Stage Updation failed : " . $e->getMessage());
                    }
                }
            }
            $remaning = array_diff($systemSetup, $notUpdateEntity);
            if(!empty($remaning)){
                $remaning = implode(",",$remaning);
                \App\Models\Backend\SystemSetupEntityStage::whereRaw("entity_id IN ($remaning)")
                    ->where("stage_id","8")
                    ->update(["status" => 'Y']);
            }
            
        } catch (Exception $e) {
            $cronName = "Billing System Setup";
            $message = $ex->getMessage();
            cronNotWorking($cronName, $message);
        }
    }

}
