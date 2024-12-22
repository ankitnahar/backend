<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityCrmNotes extends Model {

    protected $guarded = ['id'];
    protected $table = 'entity_crm_notes';
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

    public static function getEntityCrmNotes(){
        return EntityCrmNotes::with('EntityId:name,billing_name,trading_name,id','createdBy:userfullname as created_by,id','modifiedBy:userfullname as modified_by,id');
    }

}
