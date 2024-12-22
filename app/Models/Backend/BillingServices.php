<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class BillingServices extends Model
{
    protected $table = 'billing_services';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;
    
    public static function getServices($id) {
        $specialNotes = BillingServices::where("is_latest", "=", 1)->where("entity_id", "=", $id)->select(["services.id", "services.service_name"])
                ->leftjoin('services', 'services.id', '=', 'service_id')->pluck('services.service_name', 'services.id');
        return $specialNotes;
    }
    
    public static function getBilling($id) {
    return $billing = \App\Models\Backend\BillingServices::leftjoin("billing_basic as b","b.entity_id","billing_services.entity_id")
            ->leftjoin("services as s", "s.id", "billing_services.service_id")
            ->select('b.parent_id','b.contact_person','b.to_email','b.cc_email','b.address','b.notice_period','b.category_id','b.full_time_resource',
        'b.debtor_followup', 'b.merge_invoice','b.payment_id','b.ddr_rec', 'b.card_id','b.surcharge', 'b.card_number','b.entity_grouptype_id', 'b.state_id','b.is_active',
        "b.notes as billing_notes","billing_services.*","s.service_name")
            ->find($id);
    }
    
     public static function boot() {
        parent::boot();
        static::updating(function($billingServices) {
            $col_name = [
                    'contract_signed_date' => "Contract Signed Date",
                    'recurring_id' => 'Recurring',
                    'auto_invoice' =>'Auto Invoice',
                    'frequency_id' => 'Frequency',
                    'payroll_frequency_id' => 'Payroll 404 Frequency',
                    'calc_id' => 'payroll calc',
                    'software_id' => 'Software',
                    'plan_id' => 'Plan',
                    'discount' => 'Discount',
                    'standard_fee' => 'Standard Fee',
                    'state_id' => 'State',
                    'is_setup_cost' => 'Is Setup Cost',
                    'setup_cost' => 'Setup Cost',
                    'basic_rate' => 'Basic Rate',
                    'permium_rate' => 'Permium Rate',
                    'befree_invoice' => 'Befree Invoice',
                    'monthly_amount' => 'Monthly Amount',
                    'balance_amount' => 'Balance Amount',
                    'audit_fee' => 'Audit Fee',
                    'audit_fee_inc' => 'Audit Fee incuded',
                    'default_rph' => 'Default RPH',
                    'inc_in_ff' => 'Inc In Fee',
                    'bk_in_ff' => 'BK Inc Fee',
                    'ff_rph' => 'FF RPH',
                    'fixed_fee' => 'Fixed Fee',
                    'ff_start_date' => 'FF Start Date',
                    'fixed_total_amount' => 'Fixed Total Amount',
                    'fixed_total_unit' => 'Fixed Total Unit',
                    'is_latest' => 'Is Latest',
                    'is_updated' => 'Is Updated',
                    'is_active' => 'Is Active'
            ];
           
            $changesArray = \App\Http\Controllers\Backend\Billing\BillingServicesController::saveHistory($billingServices, $col_name);
          // showArray(json_encode($changesArray));exit;
            //Insert value in audit table
            if(!empty($changesArray)){
            BillingServicesAudit::create([
                'entity_id' => $billingServices->entity_id,
                'service_id' => $billingServices->service_id,
                'changes' => json_encode($changesArray),
                'modified_on' => date('Y-m-d H:i:s'),
                'modified_by' => loginUser()
            ]);
            }
        });
    }
    
    public static function getBillingServicesReportData(){
        return BillingServices::leftjoin("entity as e","e.id","billing_services.entity_id")
                ->leftjoin("entity as ep","ep.id","e.parent_id")
                ->leftJoin('entity_allocation as ea', function($query) {
                    $query->on('ea.entity_id', '=', 'billing_services.entity_id');
                    $query->on('ea.service_id','=','billing_services.service_id');
                })
                ->where("billing_services.is_active","1")
                ->where("billing_services.is_updated","1")
                ->where("billing_services.is_latest","1")
                ->where("e.discontinue_stage","!=","2");
                
    }
    
    public static function reportArrangeData($data) {            
        $user = \App\Models\User::select('userfullname','id')->get()->pluck('userfullname','id')->toArray();
        
        $designationids = Designation::select("designation_name")->where("is_display_in_allocation","1")->get();
        foreach($designationids as $designation){
        $arrDDOption[$designation->designation_name] = $user;
        } 
        $arrDDOption['Service'] = Services::where('is_active','=', '1')->get()->pluck('service_name','id')->toArray();
        $arrDDOption['Frequency'] = $arrDDOption['Payroll frequency'] = Frequency::where('is_active','=', '1')->get()->pluck('frequency_name','id')->toArray();
        $arrDDOption['Recurring'] = InvoiceRecurring::where('is_active','=', '1')->get()->pluck('recurring_name','id')->toArray();
        $arrDDOption['Payroll Calculator'] = BillingPayrollCalc::get()->pluck('name','id')->toArray();
        $arrDDOption['Software'] = BillingSubscriptionSoftware::where('parent_id','=', '0')->get()->pluck('software_plan','id')->toArray();
        $arrDDOption['Plan'] = BillingSubscriptionSoftware::where('parent_id','!=', '0')->get()->pluck('software_plan','id')->toArray();
        
        $arrDDOption['Auto invoice'] = $arrDDOption['is_setup_cost'] = $arrDDOption['Befree invoice']= $arrDDOption['Audit fee inc'] = $arrDDOption['Inc inc ff'] = $arrDDOption['Bk inc ff'] =  $arrDDOption['AR Inc in FF'] = $arrDDOption['Ap Inc in ff'] = $arrDDOption['DM inc in ff']= $arrDDOption['BK Payroll Inc in ff'] = config('constant.yesNo');
        foreach ($data->toArray() as $key => $value) {             
            foreach ($value as $rowkey => $rowvalue) { 
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue; 
                }            
        } 
        
        return $data;
    }
    
    public static function getFFClientList(){
      $ffProposalEntity = \App\Models\Backend\FFProposal::select(DB::raw("GROUP_CONCAT(DISTINCT entity_id) as entity_id"))->first();
      
        return Billing::leftjoin("billing_services","billing_services.entity_id","billing_basic.entity_id")
                ->leftjoin("entity as e","e.id","billing_services.entity_id")
                ->leftjoin("services as s","s.id","billing_services.service_id")
                 ->leftJoin('entity_allocation as ea', function($query) {
                    $query->on('ea.entity_id', '=', 'billing_services.entity_id');
                    $query->on('ea.service_id','=', 'billing_services.service_id');
                })
                 ->leftJoin('user as ut', function($query) {
                            $query->where('ut.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                })
                ->leftJoin('ff_service_comment as fsc', function($query) {
                    $query->on('fsc.entity_id', '=', 'billing_services.entity_id');
                    $query->on('fsc.service_id','=', 'billing_services.service_id');
                })
                ->leftjoin("ff_service_comment as fs","fs.entity_id","billing_services.entity_id")
                ->select("billing_basic.entity_id","e.code","e.name","e.billing_name", "e.trading_name", "s.service_name", "ut.userfullname as tam_name", "e.discontinue_stage", "billing_services.inc_in_ff","billing_services.service_id","fsc.comment")
                ->whereIn("billing_services.service_id",[1])
                ->where("billing_services.inc_in_ff","0")
                ->where("billing_services.is_active","1")
                ->where("billing_services.is_updated","1")
                ->where("billing_services.is_latest","1")
                ->whereNotIn("billing_basic.entity_grouptype_id",[2,8,9,14,17])
                ->whereRaw("billing_services.entity_id NOT IN($ffProposalEntity->entity_id)")
                ->where("e.discontinue_stage","!=","2");
    }
    
    public static function getAutoRecurring($RecurringDate,$serviceId){
      
       return  BillingServices::leftjoin("entity as e","e.id","billing_services.entity_id")
                ->leftJoin('invoice_recurring as ir', function($query) {
                            $query->whereRaw("FIND_IN_SET(billing_services.entity_id,ir.entity_id)");
                })
               ->leftjoin("invoice_recurring_detail as ird","ird.recurring_id","ir.id")
                 ->leftJoin('entity_allocation as ea', function($query) use($serviceId){
                    $query->on('ea.entity_id', '=', 'billing_services.entity_id');
                    $query->where('ea.service_id',$serviceId);
                })
                ->select("billing_services.entity_id","e.billing_name as name","billing_services.service_id","billing_services.fixed_fee","billing_services.fixed_total_amount","ir.service_id as recuring_service_id",DB::raw("JSON_EXTRACT(allocation_json, '$.15') AS division_head,JSON_EXTRACT(allocation_json, '$.9') AS TAM,JSON_EXTRACT(allocation_json, '$.60') AS TL"),"monthly_amount")
                ->whereRaw("billing_services.service_id = ir.service_id")
                 ->where("billing_services.is_active","1")
                ->where("billing_services.auto_invoice","1")
                ->where("billing_services.is_latest","1")
                ->where("ird.invoice_date",$RecurringDate)
                ->where("e.discontinue_stage","!=","2")
                ->groupBy("billing_services.entity_id","billing_services.service_id")
                ->get();
    }
    
     public static function getOtherAutoRecurring($RecurringDate){
         return  BillingServices::leftjoin("entity as e","e.id","billing_services.entity_id")
                ->leftJoin('invoice_recurring as ir', function($query) {
                            $query->whereRaw("FIND_IN_SET(billing_services.entity_id,ir.entity_id)");
                })
               ->leftjoin("invoice_recurring_detail as ird","ird.recurring_id","ir.id")                
                ->select("billing_services.entity_id","e.name","billing_services.service_id","billing_services.fixed_fee")
                ->whereIn("billing_services.service_id",[4,5,6])
                 ->where("billing_services.is_active","1")
                ->where("billing_services.auto_invoice","1")
                ->where("billing_services.is_latest","1")
                ->where("ird.invoice_date",$RecurringDate)
                ->where("e.discontinue_stage","!=","2")
                ->groupBy("billing_services.entity_id","billing_services.service_id")
                ->get();
     }
     
     public static function checkTAMonService($entityId){
          $serviceDetail = \App\Models\Backend\BillingServices::leftJoin('entity_allocation as ea', function($query) {
                                $query->on('ea.entity_id', '=', 'billing_services.entity_id');
                                $query->on('ea.service_id', '=', 'billing_services.service_id');
                            })
                            ->leftjoin("services as s", "s.id", "billing_services.service_id")
                            ->leftJoin('user as ut', function($query) {
                                $query->where('ut.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                            })
                            ->select("billing_services.service_id", "billing_services.entity_id", "ut.userfullname as tam_name", "ut.id as tam_id","s.service_name")
                            ->where("billing_services.is_active", "1")
                            ->where("billing_services.is_updated", "1")
                            ->where("billing_services.is_latest", "1")
                            ->where("billing_services.entity_id", $entityId)
                             ->whereIn("billing_services.service_id", [1, 2, 6]);
            $serviceId = array();
            $tamId = array();
            $tam_name = array();
           $reason ='';
           if($serviceDetail->count() > 0){
            foreach($serviceDetail->get() as $service){
                if($service->tam_id != null && $service->tam_id!= 0){
                    $serviceId[] = $service->service_id;
                    $tamId[] = $service->tam_id;
                    $tam_name[] = $service->tam_name;
                    $service_name[] = $service->service_name;
                    $serviceTam[] = array("service_id" => $service->service_id,"tam_id" =>$service->tam_id,
                        "service_name" =>$service->service_name,"tam_name" => $service->tam_name);
                }else{                    
                   return $reason;                   
                }                
            }    
            $reason = array("service_id" => $serviceId,"tam_id" => $tamId,"service_name" => $service_name,"tam_name" =>$tam_name,"service_tam" => $serviceTam);
           }
            return $reason;
     }
     
     public static function checkTAMService($entityId){
          $serviceDetail = \App\Models\Backend\BillingServices::leftJoin('entity_allocation as ea', function($query) {
                                $query->on('ea.entity_id', '=', 'billing_services.entity_id');
                                $query->on('ea.service_id', '=', 'billing_services.service_id');
                            })
                            ->leftjoin("services as s", "s.id", "billing_services.service_id")
                            ->leftJoin('user as ut', function($query) {
                                $query->where('ut.id', DB::raw('JSON_VALUE(ea.allocation_json,"$.60")'));
                            })
                            ->select("billing_services.service_id", "billing_services.entity_id", "ut.userfullname as tam_name", "ut.id as tam_id","s.service_name")
                            ->where("billing_services.is_active", "1")
                            ->where("billing_services.is_updated", "1")
                            ->where("billing_services.is_latest", "1")
                            ->where("billing_services.entity_id", $entityId)
                             ->whereIn("billing_services.service_id", [1, 2, 6]);
            $serviceId = array();
            $tamId = array();
            $tam_name = array();
           $reason ='';
           if($serviceDetail->count() > 0){
            foreach($serviceDetail->get() as $service){               
                    $serviceId[] = $service->service_id;
                    if($service->tam_id !='' && $service->tam_id !='null'){
                    $tamId[] = $service->tam_id;
                    $tam_name[] = $service->tam_name;
                    }
                    $service_name[] = $service->service_name;
                    $serviceTam[] = array("service_id" => $service->service_id,"tam_id" =>$service->tam_id,
                        "service_name" =>$service->service_name,"tam_name" => $service->tam_name);
                             
            }    
            $reason = array("service_id" => $serviceId,"tam_id" => $tamId,"service_name" => $service_name,"tam_name" =>$tam_name,"service_tam" => $serviceTam);
           }
            return $reason;
     }
    
}
