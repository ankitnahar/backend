<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class BillingHostingUser extends Model {

    protected $guarded = ['id'];
    protected $table = 'billing_hosting_user';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy() {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public static function getReportData(){
       return BillingHostingUser::leftjoin("entity as e","e.id","billing_hosting_user.entity_id")
               ->leftjoin("entity as ep","ep.id","e.parent_id")
              ->where("e.discontinue_stage","!=","2");
    }
    
    public static function reportArrangeData($data) {    
        $arrDDOption['User Type'] = config('constant.hostingUserType');
        $arrDDOption['Active'] = config('constant.yesNo');
        foreach ($data->toArray() as $key => $value) {             
            foreach ($value as $rowkey => $rowvalue) { 
                $data[$key][$rowkey] = (isset($arrDDOption[$rowkey])) ? ((isset($arrDDOption[$rowkey][$rowvalue])) ? $arrDDOption[$rowkey][$rowvalue] : '') : $rowvalue; 
                }            
        } 
        
        return $data;
    }
}
