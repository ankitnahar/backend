<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class Dynamicgroup extends Model
{
    const CREATED_AT = 'created_on';
    const UPDATED_AT = 'modified_on';
    protected $guarded = ['id'];
    protected $table = 'dynamic_group';
    protected $fillable = ['group_name', 'is_active', 'sort_order'];
    protected $hidden = ['created_by', 'updated_by'];
    
    public function createdBy()
    {
        return $this->belongsTo('App\Models\User', 'created_by', 'id');
    }
    
    public function modifiedBy()
    {
        return $this->belongsTo('App\Models\User', 'modified_by', 'id');
    }
    
   public static function getDynamicGroupListing(){
      return Dynamicgroup::with('createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id');
             
   }
}
