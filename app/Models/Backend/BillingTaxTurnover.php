<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class BillingTaxTurnover extends Model {

    protected $table = 'billing_tax_turnover';
    protected $guarded = ['id'];
    protected $hidden = [];
    public $timestamps = false;
    
    public function createdBy(){
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public static function getReportData(){
       return BillingTaxTurnover::leftjoin("entity as e","e.id","billing_tax_turnover.entity_id")
               ->leftjoin("entity as ep","ep.id","e.parent_id")
               ->leftJoin('entity_allocation as ea', function($query) {
                    $query->on('ea.entity_id', '=', 'billing_tax_turnover.entity_id');
                    $query->on('ea.service_id','=',DB::raw('6'));
                })  
              ->where("e.discontinue_stage","!=","2");
    }
    
    public static function reportArrangeData($data) {    
        $arrDDOption['Tax Condition'] = config('constant.taxCondition');
        foreach ($data->toArray() as $key => $value) {             
            foreach ($value as $rowkey => $rowvalue) { 
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue; 
                }            
        } 
        
        return $data;
    }
}
