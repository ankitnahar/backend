<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class EntityAllocation extends Model
{
    // Table name which we used from database
    protected $guarded = ['id'];
    protected $table = 'entity_allocation';
    protected $hidden = [ ];
    public $timestamps = false;    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id')->select('id','userfullname');
    }
    
    public function ModifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id')->select('id','userfullname');
    }
    
    public static function getEntityAllocation($entity_id){
        return BillingServices::leftJoin('entity_allocation as ea', function($join) use ($entity_id) {
                            $join->on('ea.entity_id', '=', 'billing_services.entity_id');
                            $join->on('ea.service_id', '=', 'billing_services.service_id');
                        })
                        ->select("ea.*")
                        ->where("billing_services.entity_id",$entity_id)
                        ->where("billing_services.is_latest",db::raw("1"))
                        ->whereRaw("billing_services.service_id IN (1,2,6)")->get();
    }
    
    public static function getAllocationReportData(){
        return EntityAllocation::leftjoin("entity as e","e.id","entity_allocation.entity_id")
                 ->leftjoin("entity as ep","ep.id","e.parent_id")
                ->leftjoin("billing_services as b", function($join) {
                            $join->on('b.entity_id', '=', 'entity_allocation.entity_id');
                            $join->on('b.service_id', '=', 'entity_allocation.service_id');
                        })
                        ->where("b.is_latest",db::raw("1"))
                        ->where("b.is_active",db::raw("1"))
                        ->where("e.discontinue_stage","!=","2")
                        ->whereRaw("b.service_id IN (1,2,6)");
    }
    
    public static function reportArrangeData($data) {  
        $user = \App\Models\User::get()->pluck('userfullname','id')->toArray();
        
        $designationids = Designation::select("designation_name")->where("is_display_in_allocation","1")->where("is_active","1")->get();
        foreach($designationids as $designation){
        $arrDDOption[$designation->designation_name] = $user;
        }
        $arrDDOption['Service Name'] = Services::where('is_active','=', '1')->get()->pluck('service_name','id')->toArray();
        foreach ($data->toArray() as $key => $value) {            
            foreach ($value as $rowkey => $rowvalue) {                
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue;     
            }            
        }       
        return $data;
    }
}
