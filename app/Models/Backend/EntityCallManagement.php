<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityCallManagement extends Model {

    protected $guarded = ['id'];
    protected $table = 'entity_management_call';
    protected $hidden = [ ];
    public $timestamps = false;
    
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by', 'id');
    }
    
    public function ModifiedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
    
     public function EntityId()
    {
        return $this->belongsTo(Entity::class, 'entity_id', 'id');
    }

    public static function getEntityCallManagement(){
        return EntityCallManagement::with('EntityId:name,billing_name,trading_name,id','createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id');
    }
}
