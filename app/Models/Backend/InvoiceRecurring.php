<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class InvoiceRecurring extends Model {

    protected $table = 'invoice_recurring';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;

    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }

    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }

    /*public static function invoiceRecurringData() {
        return InvoiceRecurring::with('createdBy:id,userfullname','modifiedBy:id,userfullname')
                        ->leftjoin("invoice_recurring_detail as ird", "ird.recurring_id", "invoice_recurring.id")
                        ->leftjoin("services as s", "s.id", "invoice_recurring.service_id")
                        ->leftjoin("frequency as f", "f.id", "invoice_recurring.frequency_id")
                        ->leftJoin('entity as e', function($query) {
                            $query->whereRaw('FIND_IN_SET(e.id,invoice_recurring.entity_id)');
                        })
                        ->select('invoice_recurring.id', 'recurring_name', 'rec_type', 'entity_id', 'service_id', 'fixed_fee', 'frequency_id', 'next_due', 'last_due', 'inv_logic', 'inv_days', 'inv_weekday', 'repetition_type', 'repetition as times', 'last_due as repeat_date', 'last_due as repeat_indefinitely', 'notes', 'invoice_recurring.is_active','invoice_recurring.created_by','invoice_recurring.created_on', 'invoice_recurring.modified_by', 'invoice_recurring.modified_on', "ird.invoice_date", "s.service_name", "f.frequency_name", DB::raw("GROUP_CONCAT(DISTINCT e.billing_name) as entity_name"));
    }*/

    public static function getServices($recurringId,$entityId) {
        $serviceIds = InvoiceRecurring::select(DB::raw("GROUP_CONCAT(service_id) as service_id"))->whereRaw("FIND_IN_SET (" . $entityId . ",entity_id)");
        if($recurringId != 0){
           $serviceIds = $serviceIds->where("id","!=",$recurringId);
        }
       
         $serviceIdArray = 0;
         if($serviceIds->count() > 0){
             $serviceIds = $serviceIds->first();             
             $serviceIdArray = $serviceIds->service_id;
         }
        return BillingServices::leftjoin("services as s", "s.id", "billing_services.service_id")
                        ->select("s.id", "s.service_name", "billing_services.frequency_id", "billing_services.inc_in_ff")
                        ->where("billing_services.is_active", "1")
                        ->where("billing_services.is_latest", "1")
                        ->where("billing_services.service_id", "!=", "6")
                        ->where("billing_services.entity_id", $entityId)
                        ->whereRaw("billing_services.service_id NOT IN ($serviceIdArray)")->groupBy("s.id");
    }

    public static function getEntity($serviceId, $ff, $frequencyId, $recurringId) {

        $entityIds = InvoiceRecurring::select("entity_id")->where("invoice_recurring.service_id", $serviceId)
                ->where("invoice_recurring.frequency_id", $frequencyId)
                ->where("invoice_recurring.fixed_fee", $ff)
                ->where("invoice_recurring.entity_id", "!=", "''");
        if ($recurringId != 0) {
            $entityIds = $entityIds->where("invoice_recurring.id", "!=", $recurringId);
        }
        $entityIds = $entityIds->get();
       // showArray($entityIds); exit;
        $Ids = "";
        foreach ($entityIds as $id) {
            if ($id->entity_id != '') {                
                $id->entity_id = rtrim($id->entity_id, ',');
                $Ids .= $id->entity_id . ',';
            }
        }
        $Ids = str_replace(",,", ",", $Ids);
        $Ids = rtrim($Ids, ',');
        // showArray($Ids);exit;
        $billingEntity = BillingServices::leftjoin("entity as e", "e.id", "billing_services.entity_id")
                ->leftjoin("billing_basic as b", "b.entity_id", "billing_services.entity_id")
                ->select("e.id", "e.trading_name","e.billing_name")
                ->where("billing_services.service_id", $serviceId)
                ->where("billing_services.service_id", "!=", "6")
                ->where("billing_services.inc_in_ff", $ff)
                ->where("billing_services.frequency_id", $frequencyId)
                ->where("billing_services.is_latest", "1")
                ->where("b.parent_id", "0")
                ->where("billing_services.is_active", "1");
        if ($Ids != '')
            $billingEntity = $billingEntity->whereRaw("billing_services.entity_id NOT IN ($Ids)");

        $billingEntity = $billingEntity->where('e.discontinue_stage', "!=", "2")
                        ->groupby("e.id")->get();
        // showArray($billingEntity);exit;
        return $billingEntity;
    }

    /*
     * Created by - Pankaj
     * save history when user information update 
     */

    public static function boot() {
        parent::boot();
        self::updating(function($recurring) {
            $col_name = [
                'recurring_name' => 'Recurring Name',
                'rec_type' => 'Recurring Type',
                'entity_id' => 'Client Name',
                'service_id' => 'Service',
                'fixed_fee' => 'Fixed Fee',
                'frequency_id' => 'Frequency',
                'next_due' => 'Start Date',
                'last_due' => 'Last Date',
                'inv_logic' => 'Invoice Logic',
                'inv_days' => 'Invoice Days',
                'inv_weekday' => 'Invoice Day',
                'repetition_type' => 'Repetition Type',
                'repetition' => 'Repetition',
                'notes' => 'Notes',
                'is_active' => 'Is Active'
            ];
            $changesArray = \App\Http\Controllers\Backend\Invoice\InvoiceRecurringController::saveHistory($recurring, $col_name);

            $updatedBy = loginUser();
            //Insert value in audit table
            if(!empty($changesArray)){
            InvoiceRecurringAudit::create([
                'recurring_id' => $recurring->id,
                'changes' => json_encode($changesArray),
                'modified_on' => date('Y-m-d H:i:s'),
                'modified_by' => $updatedBy
            ]);
            }
        });
    }

}
