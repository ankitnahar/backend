<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Billing extends Model
{
    protected $table = 'billing_basic';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;
    
    public function EntityId(){
        return $this->belongsTo(\App\Models\Backend\Entity::class, 'entity_id', 'id')->whereIn("discontinue_stage",[0,1]);
    }
     //get realted entity 
    public static function getRelatedEntity($entity_id){
        return Billing::with('EntityId:name,billing_name,trading_name,id,discontinue_stage')->select("billing_basic.entity_id")->where("billing_basic.parent_id",$entity_id)->get();
    }
    
    public function createdBy(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public static function billingbasicData(){
       return  $billingList = Billing::with("createdBy:id,userfullname")
                ->leftjoin("entity as e","e.id","billing_basic.entity_id")
                ->leftJoin('billing_services as bs', function($query) {
                    $query->on('bs.entity_id', '=', 'billing_basic.entity_id');
                    $query->on('bs.is_active', '=', DB::raw("1"));
                    $query->on('bs.is_latest', '=', DB::raw("1"));
                })
                ->leftjoin("services as s","s.id","bs.service_id")
                ->select("e.code","e.name","e.billing_name","e.trading_name","e.billing_from","e.discontinue_stage",DB::raw("GROUP_CONCAT(bs.service_id) as service_id"),DB::raw("GROUP_CONCAT(s.service_name) as service"),"billing_basic.*");      
        
    }
    
    public static function billingData(){
        return  Billing::with("createdBy:id,userfullname")
                ->leftjoin("entity as e","e.id","billing_basic.entity_id")
                ->leftjoin("billing_services as bs","bs.entity_id","billing_basic.entity_id")
                ->leftjoin("services as s","s.id","bs.service_id")
                ->leftjoin("frequency as f","f.id","bs.frequency_id")
                ->select("billing_basic.*","e.code","e.name","e.billing_from","e.billing_name","e.billing_from","e.trading_name","bs.id as billing_service_id","bs.service_id",'s.service_name', 'bs.service_id', 'bs.is_updated','bs.contract_signed_date',  'bs.inc_in_ff', 'bs.frequency_id','f.frequency_name', 'bs.auto_invoice', 'bs.recurring_id')
                ->where("bs.is_active","1")
                ->where("bs.is_latest","1")
                ->where("e.discontinue_stage","!=","2");
    }
    
    public static function showbillingData(){
        return  Billing::with("createdBy:id,userfullname")
                ->leftjoin("entity as e","e.id","billing_basic.entity_id")
                ->leftJoin('billing_services as bs', function($query) {
                    $query->on('bs.entity_id', '=', 'billing_basic.entity_id');
                    $query->on('bs.is_active', '=', DB::raw("1"));
                    $query->on('bs.is_latest', '=', DB::raw("1"));
                })
                ->leftjoin("services as s","s.id","bs.service_id")
                ->leftjoin("frequency as f","f.id","bs.frequency_id")
                ->select("billing_basic.*","e.code","e.name","e.billing_name","bs.is_active as active_service","e.trading_name","bs.id as billing_service_id","bs.service_id",'s.service_name', 'bs.service_id', 'bs.is_updated','bs.contract_signed_date',  'bs.inc_in_ff', 'bs.frequency_id','f.frequency_name', 'bs.auto_invoice', 'bs.recurring_id')
                ->where("e.discontinue_stage","!=","2");
    }
    public static function entityBillingData($entityIds,$serviceId,$parentId = 0){
        $billing = Billing::leftjoin("billing_services as b","b.entity_id","billing_basic.entity_id")
                ->select("b.id as billing_id","billing_basic.entity_id","b.inc_in_ff")
                ->where("b.is_latest","1")
                ->where("b.is_active","1")
                ->where("b.service_id",$serviceId);
        if($parentId == 0){
                $billing = $billing->whereRaw("billing_basic.entity_id IN ($entityIds)");
        }else{
            $billing = $billing->whereRaw("billing_basic.parent_id IN ($entityIds)");
        }
        return $billing;
    }
    
    public static function clientNotOnDDR(){
       // 2=Harris Park, 8=MaxTax Pty Ltd-Adelaide, 9=MaxTax Pty Ltd-Parramatta, 14=Superrecords
        return Billing::leftjoin("billing_services as bs", "bs.entity_id", "billing_basic.entity_id")
                ->leftjoin("entity as e", "e.id", "billing_basic.entity_id")
                ->leftJoin("services as s", "s.id", "bs.service_id")
                ->leftJoin('entity_allocation as ea', function($query) {
                    $query->on('ea.entity_id', '=', 'billing_basic.entity_id');
                    $query->whereRaw('FIND_IN_SET(ea.service_id,bs.service_id)');
                })                
                ->leftJoin('user as ut', function($query) {
                    $query->where('ut.id',DB::raw('JSON_VALUE(ea.allocation_json,"$.9")'));
                })
                ->select(['e.id', 'e.code', 'e.name','e.trading_name',DB::raw('GROUP_CONCAT(DISTINCT s.service_name) AS service_name_data'),
                    DB::raw('GROUP_CONCAT(DISTINCT ut.userfullname) AS tam_name'),
                    'e.discontinue_stage', 'e.id as entity_id', DB::raw('(SELECT TRUNCATE(SUM(paid_amount),2) FROM invoice WHERE (`entity_id` = `billing_basic`.`entity_id` OR billing_basic.parent_id=entity_id)  AND `status_id` = 9)  AS amount,
                            IF(bs.service_id=1,bs.inc_in_ff,"0") AS inc_in_ff')])
                ->where("billing_basic.payment_id", "3")
                ->whereRaw("billing_basic.entity_grouptype_id NOT IN(2,8,9,14)")
                ->where("billing_basic.ddr_followup","1")
                ->where("billing_basic.parent_id", "0")
                ->where("bs.is_latest","1")
                ->where("e.discontinue_stage","!=","2");
                
    }
    
     public static function boot() {
        parent::boot();
        self::updating(function($billing) {
            $col_name = [
                'contact_person' => 'Contact Person',
                'to_email' => 'To Email',
                'cc_email' => 'CC Email',
                'address' => 'Address',
                'notice_period' => 'Notice Period',
                'category_id' => 'Category',
                'full_time_resource' => 'Full Time Resource',
                'debtor_followup' => 'Debtor Follow Up',
                'merge_invoice' => 'Merge Invoice',
                'payment_id' => 'Payment',
                'ddr_rec' => 'DDR Rec',
                'card_id' => 'Card',
                'surcharge'=> 'Surcharge',
                'card_number'=> 'Card Number',
                'entity_grouptype_id'=> 'Entity Group Type',
                'state_id'=> 'Job Type',
                'notes'=> 'Notes',
                'bk_comment'=> 'Bk Comment',
                'payroll_comment'=> 'Payroll Comment',
                'ddr_followup'=> 'DDR Follow Up',
                'merge_ff' => 'Merge Fixed fee',
                'related_entity' => 'Related Entity'
            ];
            $changesArray = \App\Http\Controllers\Backend\Billing\BillingController::saveHistory($billing, $col_name);
           if(!empty($changesArray)){
            //Insert value in audit table
            BillingAudit::create([
                'entity_id' => $billing->entity_id,
                'changes' => json_encode($changesArray),
                'modified_on' => date('Y-m-d H:i:s'),
                'modified_by' => loginUser()
            ]);
           }
        });
    }
    
    public static function getBillingReportData(){
        return Billing::leftjoin("entity as e","e.id","billing_basic.entity_id")
                ->leftjoin("entity as ep","ep.id","e.parent_id")
                ->leftJoin('entity_allocation as ea', function($query) {
                    $query->on('ea.entity_id', '=', 'billing_basic.entity_id');
                    $query->on('ea.service_id','=',DB::raw('1'));
                })               
                ->where("e.discontinue_stage","!=","2");
                
    }
    
    public static function reportArrangeData($data) {
        $user = \App\Models\User::select('userfullname','id')->get()->pluck('userfullname','id')->toArray();
        
        $designationids = Designation::select("designation_name")->where("is_display_in_allocation","1")->get();
        foreach($designationids as $designation){
        $arrDDOption[$designation->designation_name] = $user;
        } 
        $arrDDOption['service name'] = Services::where('is_active','=', '1')->get()->pluck('service_name','id')->toArray();
        $arrDDOption['Card name'] = Card::get()->pluck('name','id')->toArray();
        $arrDDOption['Client Belong To'] = EntityGroupclientBelongs::where('is_active','=', '1')->get()->pluck('name','id')->toArray();
        $arrDDOption['Full time resource'] =  config('constant.fulltimeresource');
        $arrDDOption['Category'] = config('constant.category');
        $arrDDOption['Payment'] = config('constant.payment');
        $arrDDOption['State'] = State::get()->pluck('state_name','id')->toArray();
        $arrDDOption['Debtor followup'] =  $arrDDOption['DDR Receive'] = $arrDDOption['Merge invoice'] = $arrDDOption['Is active'] = config('constant.yesNo');
        
        
        foreach ($data->toArray() as $key => $value) {   
            foreach ($value as $rowkey => $rowvalue) { 
                if($rowkey == 'Category'){
                    $parentId = ($value['parent_entity'] >  0 ) ? 'Related Entity' : '';
                 $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] . ' '. $parentId : '') : $rowvalue. ' '. $parentId; 
                   
                }else{
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue; 
                }
                
                }            
        } 
        
        return $data;
    }
}
