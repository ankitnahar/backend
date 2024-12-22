<?php

namespace App\Models\Backend;

use Illuminate\Database\Eloquent\Model;

class EntityChecklistAudit extends Model {
    
    protected $guarded = ['id'];
    protected $table = 'entity_checklist_audit';
    public $timestamps = false;
    
    public function modified_by()
    {
        return $this->belongsTo(\App\Models\User::class, 'modified_by', 'id');
    }
}
