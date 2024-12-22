<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityAllocationOtherAudit extends Model
{
    // Table name which we used from database
    protected $guarded = ['id'];
    protected $table = 'entity_allocation_other_audit';
    protected $hidden = [ ];
    public $timestamps = false;    
   
     public function modifiedBy() {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
