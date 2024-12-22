<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;
use DB; 

class Dynamicfield extends Model
{
    protected $guarded = ['id'];
    protected $fillable = [];
    protected $table = 'dynamic_field';
    protected $hidden = [ ];
    public $timestamps = false;   
   
    
    static function getColumn($tabel, $column){
        $data = DB::select(DB::raw("SELECT column_name, column_comment FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='".$tabel."' AND column_name = '".$column."' LIMIT 1"));
        $str = explode(",", $data[0]->column_comment);
        $data = array();
        foreach($str as $key => $value){
            $key = explode('=>', $value);
            $data[trim($key[0])] = $key[1];
        }
        return $data;
    }
    
    //Designation ise field data
    public static function fieldData($id) {
        return Dynamicfield::leftjoin('dynamic_group as dg', "dg.id", "=", "dynamic_field.group_id")
                ->leftJoin('designation_field_right as dt', function($join)use ($id) {
                    $join->on('dt.field_id', '=', 'dynamic_field.id');
                    $join->on('dt.designation_id', '=', DB::raw($id));
                })
                ->select(['dynamic_field.id', 'dg.group_name', 'dynamic_field.field_title', 'dt.view', 'dt.add_edit'])
                ->where("dynamic_field.is_active", "=", 1)
                ->orderby("dynamic_field.field_name", "asc");        
    }
    
    //user wise field data
    public static function userfieldData($id) {
    return Dynamicfield::leftjoin('dynamic_group as dg', "dg.id", "=", "dynamic_field.group_id")
                ->leftJoin('user_field_right as dt', function($join)use ($id) {
                    $join->on('dt.field_id', '=', 'dynamic_field.id');
                    $join->on('dt.user_id', '=', DB::raw($id));
                })
                ->select(['dynamic_field.id', 'dg.group_name', 'dynamic_field.field_title', 'dt.view', 'dt.add_edit'])
                ->where("dynamic_field.is_active", "=", 1)
                ->orderby("dynamic_field.field_name", "asc");
    }     
    
    
    //get name as per id
    public static function getname($id){
         $field = Dynamicfield::select("field_title")->where("id", "=", $id)->first();
         return $field->field_title;
    }
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    public function modifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    public function groupId()
    {
        return $this->belongsTo(Dynamicgroup::class, 'group_id', 'id');
    }
    
    public static function getDynamicFieldListing(){
      return Dynamicfield::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id','groupId:group_name,id');                          
                            
    }    
    
}