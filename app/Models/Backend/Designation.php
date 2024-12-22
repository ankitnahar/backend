<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB;

class Designation extends Model {

    protected $guarded = ['id'];
    protected $table = 'designation';
    protected $hidden = [];
    public $timestamps = false;

    public static function getDesignation() {
        return Designation::where('is_active', 1)->get('designation_name', 'id')->toArray();
    }

    public function createdBy(){
       return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function modifiedBy(){
       return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
    public static function designationData($id='') { 
        $designation = Designation::with('parent','createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id')->where("is_active","1");
        if($id!=''){
            $designation = $designation->where('designation.id',$id);
        }        
        return $designation;
    }

    //get parent designation
    public function parent()
    {
    return $this->belongsTo(Designation::class, 'parent_id')->select("designation_name","id","parent_id",DB::raw('IF(designation.is_mandatory=1,"Yes","No") AS is_mandatory'));
    } 
    
    public static function allDesignation() {
        $designation = Designation::where('is_active', 1)->get(['designation_name', 'id']);
        foreach($designation as $row){
            $designationArray[$row->id] = $row->designation_name;
        }
        return $designationArray;
    }
   
    
}
